<?php
declare(strict_types=1);

class AuthService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function requestLink(string $email): bool
    {
        $email = trim($email);

        $stmt = $this->pdo->prepare(
            'SELECT id, first_name, email FROM `user` WHERE email = ? AND active = 1 LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Neutral response — never reveal whether the address exists
        if ($user === false) {
            return true;
        }

        $token     = $this->generateToken();
        $expiresAt = date('Y-m-d H:i:s', time() + 900); // 15 minutes

        $insert = $this->pdo->prepare(
            'INSERT INTO magic_link (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())'
        );
        $insert->execute([$user['id'], $token, $expiresAt]);

        return $this->sendMagicLinkEmail($user['email'], $token);
    }

    public function verifyToken(string $token): ?array
    {
        // Clean up expired tokens opportunistically
        $this->pdo->prepare(
            'DELETE FROM magic_link WHERE expires_at < NOW()'
        )->execute();

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

    private function sendMagicLinkEmail(string $to, string $token): bool
    {
        $link    = APP_URL . '/public/index.php?page=auth&action=verify&token=' . urlencode($token);
        $subject = t('auth.email_subject');

        $body = '<p>' . e(t('auth.email_body')) . '</p>'
              . '<p><a href="' . e($link) . '">' . e($link) . '</a></p>'
              . '<p><small>' . e(t('auth.email_expires')) . '</small></p>';

        $mailer = new Mailer();
        return $mailer->send($to, $subject, $body);
    }
}
