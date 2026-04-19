<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/index.php?page=match');
}

$matchId    = (int) ($_POST['match_id'] ?? ($_GET['id'] ?? 0));
$matchRepo  = new MatchRepository();
$match      = $matchRepo->getMatchById($matchId);

if ($match === null || $match['status'] !== 'finished') {
    redirect(APP_URL . '/index.php?page=match');
}

$playerIds = array_map('intval', (array) ($_POST['player_ids'] ?? []));
$skillKeys = ['pace', 'shooting', 'passing', 'dribbling', 'defending', 'physicality'];

foreach ($playerIds as $playerId) {
    if ($playerId <= 0) {
        continue;
    }
    $skills = [];
    foreach ($skillKeys as $skill) {
        $raw = $_POST[$skill][$playerId] ?? '';
        if ($raw !== '' && is_numeric($raw)) {
            $val = (int) $raw;
            if ($val >= 1 && $val <= 5) {
                $skills[$skill] = $val;
            }
        }
    }
    if (!empty($skills)) {
        $matchRepo->upsertMatchRating($matchId, $playerId, $skills);
    }
}

$_SESSION['flash'] = t('rating.saved');
redirect(APP_URL . '/index.php?page=match&action=review&id=' . $matchId);
