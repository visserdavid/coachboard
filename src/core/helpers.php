<?php
declare(strict_types=1);

function t(string $key, array $replace = []): string
{
    static ?array $strings = null;

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
