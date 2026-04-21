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

$pdo = Database::getInstance()->getConnection();
$allowedStmt = $pdo->prepare(
    "SELECT DISTINCT a.player_id
     FROM attendance a
     JOIN player p ON p.id = a.player_id
     WHERE a.context_type = 'match'
       AND a.context_id = ?
       AND a.status = 'present'
       AND p.deleted_at IS NULL"
);
$allowedStmt->execute([$matchId]);
$allowedPlayerIds = array_flip(array_map('intval', array_column($allowedStmt->fetchAll(), 'player_id')));

$playerIds = array_unique(array_map('intval', (array) ($_POST['player_ids'] ?? [])));
$skillKeys = ['pace', 'shooting', 'passing', 'dribbling', 'defending', 'physicality'];

foreach ($playerIds as $playerId) {
    if ($playerId <= 0 || !isset($allowedPlayerIds[$playerId])) {
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
