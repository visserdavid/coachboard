<?php
declare(strict_types=1);

class AuthService
{
    private const MAGIC_LINK_TTL_SECONDS = 900;
    private const MAGIC_LINK_RESEND_COOLDOWN_SECONDS = 60;
    private const MAGIC_LINK_IP_WINDOW_SECONDS = 900;
    private const MAGIC_LINK_IP_MAX_REQUESTS = 5;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function requestLink(string $email): bool
    {
        $email = trim(strtolower($email));

        $this->cleanupExpiredLinks();

        if (!$this->allowIpRequest($this->getClientIp())) {
            return true;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, first_name, email FROM `user` WHERE email = ? AND active = 1 LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Neutral response — never reveal whether the address exists
        if ($user === false) {
            return true;
        }

        $userId = (int) $user['id'];
        if ($this->hasRecentLinkRequest($userId)) {
            return true;
        }

        $this->invalidateOutstandingLinks($userId);

        $token     = $this->generateToken();
        $expiresAt = date('Y-m-d H:i:s', time() + self::MAGIC_LINK_TTL_SECONDS);

        $insert = $this->pdo->prepare(
            'INSERT INTO magic_link (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())'
        );
        $insert->execute([$userId, $token, $expiresAt]);

        return $this->sendMagicLinkEmail($user['email'], $token);
    }

    public function verifyToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ml.id, ml.user_id, ml.expires_at, ml.used_at
             FROM magic_link ml
             WHERE ml.token = ?
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $link = $stmt->fetch();

        if ($link === false) {
            return null;
        }

        if ($link['used_at'] !== null) {
            return null;
        }

        if (strtotime($link['expires_at']) <= time()) {
            return null;
        }

        // Mark as used
        $this->pdo->prepare(
            'UPDATE magic_link SET used_at = NOW() WHERE id = ?'
        )->execute([$link['id']]);

        $this->invalidateOutstandingLinks((int) $link['user_id'], (int) $link['id']);
        $this->cleanupExpiredLinks();

        // Load user
        $userStmt = $this->pdo->prepare(
            'SELECT * FROM `user` WHERE id = ? AND active = 1 LIMIT 1'
        );
        $userStmt->execute([$link['user_id']]);
        $user = $userStmt->fetch();

        if ($user === false) {
            return null;
        }

        // Start authenticated session
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user']    = $user;
        session_write_close();

        return $user;
    }

    public function logout(): void
    {
        Auth::logout();
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function hasRecentLinkRequest(int $userId): bool
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::MAGIC_LINK_RESEND_COOLDOWN_SECONDS);
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM magic_link
             WHERE user_id = ?
               AND created_at >= ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $cutoff]);

        return $stmt->fetchColumn() !== false;
    }

    private function invalidateOutstandingLinks(int $userId, ?int $excludeId = null): void
    {
        $sql = 'UPDATE magic_link
                SET used_at = NOW()
                WHERE user_id = ?
                  AND used_at IS NULL
                  AND expires_at > NOW()';
        $params = [$userId];

        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function cleanupExpiredLinks(): void
    {
        $this->pdo->prepare(
            'DELETE FROM magic_link WHERE expires_at < NOW()'
        )->execute();
    }

    private function allowIpRequest(string $ip): bool
    {
        $dir = dirname(__DIR__, 2) . '/storage/rate_limits';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return true;
        }

        $path = $dir . '/magic-link-' . sha1($ip) . '.json';
        $now = time();
        $windowStart = $now - self::MAGIC_LINK_IP_WINDOW_SECONDS;
        $timestamps = [];

        if (is_file($path)) {
            $raw = file_get_contents($path);
            $decoded = $raw !== false ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $timestamps = array_values(array_filter(
                    array_map('intval', $decoded),
                    static fn(int $timestamp): bool => $timestamp >= $windowStart
                ));
            }
        }

        $timestamps[] = $now;
        @file_put_contents($path, json_encode($timestamps), LOCK_EX);

        return count($timestamps) <= self::MAGIC_LINK_IP_MAX_REQUESTS;
    }

    private function getClientIp(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return $ip !== '' ? $ip : 'unknown';
    }

    private function sendMagicLinkEmail(string $to, string $token): bool
    {
        $link    = APP_URL . '/index.php?page=auth&action=verify&token=' . urlencode($token);
        $subject = t('auth.email_subject');

        $body = '<p>' . e(t('auth.email_body')) . '</p>'
              . '<p><a href="' . e($link) . '">' . e($link) . '</a></p>'
              . '<p><small>' . e(t('auth.email_expires')) . '</small></p>';

        $mailer = new Mailer();
        return $mailer->send($to, $subject, $body);
    }
}
