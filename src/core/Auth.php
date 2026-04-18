<?php
declare(strict_types=1);

class Auth
{
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }

    public static function getCurrentUser(): ?array
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        if (isset($_SESSION['user'])) {
            return $_SESSION['user'];
        }

        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM `user` WHERE id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user === false) {
            self::logout();
            return null;
        }

        $_SESSION['user'] = $user;
        return $user;
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            redirect(APP_URL . '/public/index.php?page=auth&action=login');
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();

        $user = self::getCurrentUser();
        if ($user === null || empty($user[$role])) {
            http_response_code(403);
            exit(e(t('error.forbidden')));
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public static function configureSessionCookieParams(): void
    {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
}
