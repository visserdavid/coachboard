<?php
declare(strict_types=1);

function t(string $key, array $replace = []): string
{
    static $strings = null;

    if ($strings === null) {
        $path = dirname(__DIR__, 2) . '/lang/' . APP_LANG . '.json';
        $json = file_get_contents($path);
        $strings = $json !== false ? json_decode($json, true) : [];
        if (!is_array($strings)) {
            $strings = [];
        }
    }

    $value = $strings[$key] ?? $key;

    foreach ($replace as $placeholder => $replacement) {
        $value = str_replace('{' . $placeholder . '}', (string) $replacement, $value);
    }

    return $value;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function getCsrfToken(): string
{
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(getCsrfToken()) . '">';
}

function validateCsrfToken(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['_csrf_token'])
        && is_string($_SESSION['_csrf_token'])
        && hash_equals($_SESSION['_csrf_token'], $token);
}

function requireCsrfToken(): void
{
    if (validateCsrfToken($_POST['_csrf'] ?? null)) {
        return;
    }

    http_response_code(403);
    $_SESSION['flash'] = t('error.forbidden');
    redirect(APP_URL . '/index.php?page=dashboard');
}

function getActiveSeason(): ?array
{
    return $_SESSION['active_season'] ?? null;
}

function getActivePhases(): array
{
    return $_SESSION['active_phases'] ?? [];
}

function getCurrentPhase(): ?array
{
    $today  = date('Y-m-d');
    $phases = getActivePhases();
    foreach ($phases as $phase) {
        if ($phase['start_date'] <= $today && $phase['end_date'] >= $today) {
            return $phase;
        }
    }
    return null;
}

function seasonHasPhases(): bool
{
    return (bool) (getActiveSeason()['has_phases'] ?? false);
}
