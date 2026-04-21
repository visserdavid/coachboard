<?php
declare(strict_types=1);

class Auth
{
    private static bool $userLoaded = false;
    private static ?array $currentUser = null;

    public static function isLoggedIn(): bool
    {
        return self::getCurrentUser() !== null;
    }

    public static function getCurrentUser(): ?array
    {
        if (!self::hasSessionUserId()) {
            return null;
        }

        if (self::$userLoaded) {
            return self::$currentUser;
        }

        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM `user` WHERE id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user === false) {
            self::logout();
            return null;
        }

        self::$userLoaded = true;
        self::$currentUser = $user;
        $_SESSION['user'] = $user;
        return $user;
    }

    public static function requireLogin(): void
    {
        if (self::getCurrentUser() === null) {
            redirect(APP_URL . '/index.php?page=auth&action=login');
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();

        $user = self::getCurrentUser();
        if ($user === null || empty($user[$role])) {
            $_SESSION['flash'] = t('auth.access_denied');
            redirect(APP_URL . '/index.php?page=dashboard');
        }
    }

    public static function logout(): void
    {
        self::$userLoaded = false;
        self::$currentUser = null;
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
            'samesite' => 'Lax',
        ]);
    }

    private static function hasSessionUserId(): bool
    {
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }
}
