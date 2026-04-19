<?php
declare(strict_types=1);

$activePage   = 'match';
$matchRepo    = new MatchRepository();
$matchService = new MatchService();

$id    = (int) ($_GET['id'] ?? 0);
$match = $matchRepo->getMatchById($id);

if ($match === null) {
    $_SESSION['flash'] = t('match.not_found');
    redirect(APP_URL . '/public/index.php?page=match');
}

if ($match['status'] !== 'finished') {
    redirect(APP_URL . '/public/index.php?page=match&action=live&id=' . $id);
}

$matchId = (int) $match['id'];

// POST: reopen match (administrator only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'reopen') {
    Auth::requireRole('is_administrator');
    $pdo  = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare(
        "UPDATE `match` SET status = 'active', goals_scored = NULL, goals_conceded = NULL WHERE id = ?"
    );
    $stmt->execute([$matchId]);
    redirect(APP_URL . '/public/index.php?page=match&action=live&id=' . $matchId);
}

// Data
$players      = $matchRepo->getMatchPlayers($matchId);
$events       = $matchRepo->getMatchEvents($matchId);
$subs         = $matchRepo->getSubstitutions($matchId);
$playingTimes = $matchService->calculatePlayingTime($matchId);

$starters = array_values(array_filter($players, fn($p) => (bool) $p['in_starting_eleven']));
$bench    = array_values(array_filter($players, fn($p) => !(bool) $p['in_starting_eleven']));

// Per-player stats
$playerGoals   = [];
$playerAssists = [];
$playerCards   = [];
foreach ($events as $e) {
    $pid = $e['player_id'] !== null ? (int) $e['player_id'] : null;
    $aid = $e['assist_player_id'] !== null ? (int) $e['assist_player_id'] : null;
    if ($e['event_type'] === 'goal') {
        if ($e['scored_via'] !== 'penalty' || $e['penalty_scored'] != 0) {
            if ($pid !== null) { $playerGoals[$pid]   = ($playerGoals[$pid]   ?? 0) + 1; }
            if ($aid !== null) { $playerAssists[$aid] = ($playerAssists[$aid] ?? 0) + 1; }
        }
    }
    if (in_array($e['event_type'], ['yellow_card', 'red_card'], true) && $pid !== null) {
        $playerCards[$pid] = $e['event_type'];
    }
}

$isHome   = $match['home_away'] === 'home';
$scored   = (int) ($match['goals_scored']   ?? 0);
$conceded = (int) ($match['goals_conceded'] ?? 0);
if ($isHome) {
    $result = $scored > $conceded ? 'win' : ($scored < $conceded ? 'loss' : 'draw');
} else {
    $result = $scored > $conceded ? 'win' : ($scored < $conceded ? 'loss' : 'draw');
}

$resultColor = match ($result) {
    'win'  => 'var(--color-success)',
    'loss' => 'var(--color-danger)',
    default => 'var(--color-neutral)',
};

$dateLabel = date('j M Y', strtotime($match['date']));
$flash     = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/public/index.php?page=match"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title" style="font-size:1rem;">
        <?= e($match['opponent']) ?> · <?= e($dateLabel) ?>
    </h1>
    <span></span>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-message"><?= e($flash) ?></div>
<?php endif; ?>

<!-- Final score -->
<div class="card" style="text-align:center; border-left:4px solid <?= $resultColor ?>; margin-bottom:0.75rem;">
    <div class="text-sm text-muted mb-1"><?= e(t('match.score')) ?></div>
    <div style="font-size:2rem; font-weight:700; line-height:1.1;">
        <?= $scored ?> – <?= $conceded ?>
    </div>
    <div class="text-sm" style="color:<?= $resultColor ?>; font-weight:600; margin-top:0.25rem;">
        <?= e(t('match.result.' . $result)) ?>
        · <?= e($match['opponent']) ?>
        · <?= e(t('match.' . $match['home_away'])) ?>
        · <?= e(t('match.type.' . $match['match_type'])) ?>
    </div>
</div>

<!-- Event timeline -->
<?php if (!empty($events)): ?>
<div class="card" style="margin-bottom:0.75rem;">
    <div class="text-sm text-muted mb-1"><?= e(t('match.score')) ?></div>
    <?php foreach ($events as $ev): ?>
        <?php
        $evType   = $ev['event_type'];
        $evMin    = (int) $ev['minute'];
        $evPlayer = $ev['player_name'] ?? null;
        $evAssist = $ev['assist_name'] ?? null;
        $evText   = '';
        $evIcon   = '';
        switch ($evType) {
            case 'goal':
                if ($ev['scored_via'] === 'penalty' && $ev['penalty_scored'] == 0) {
                    $evIcon = '✕'; $evText = t('live.event.via.penalty') . ' ' . t('live.event.penalty_missed');
                } else {
                    $evIcon = '⚽';
                    $evText = $evPlayer ?? t('live.event.unknown');
                    if ($evAssist) { $evText .= ' · ' . $evAssist; }
                    if ($ev['scored_via'] !== 'open_play') {
                        $evText .= ' (' . t('live.event.via.' . $ev['scored_via']) . ')';
                    }
                }
                break;
            case 'own_goal':
                $evIcon = '⚽'; $evText = t('event.own_goal');
                break;
            case 'yellow_card':
                $evIcon = '🟨'; $evText = $evPlayer ?? '';
                break;
            case 'red_card':
                $evIcon = '🟥'; $evText = $evPlayer ?? '';
                break;
            case 'note':
                $evIcon = '📋'; $evText = $ev['note_text'] ?? '';
                break;
        }
        ?>
        <div class="live-event-row">
            <span class="live-event-min"><?= $evMin ?>'</span>
            <span class="live-event-icon"><?= $evIcon ?></span>
            <span class="live-event-text"><?= e($evText) ?></span>
        </div>
    <?php endforeach; ?>
    <?php foreach ($subs as $sub): ?>
        <div class="live-event-row">
            <span class="live-event-min"><?= (int) $sub['minute'] ?>'</span>
            <span class="live-event-icon">↕</span>
            <span class="live-event-text">↑ <?= e($sub['player_on_name']) ?> ↓ <?= e($sub['player_off_name']) ?></span>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Playing time per player -->
<div class="card" style="margin-bottom:0.75rem;">
    <div class="text-sm text-muted mb-1"><?= e(t('player.playing_time')) ?></div>
    <?php foreach ($players as $p): ?>
        <?php
        $mpId    = (int) $p['id'];
        $pid     = (int) $p['player_id'];
        $name    = (bool) $p['is_guest'] ? ($p['guest_name'] ?? t('match.guest')) : ($p['first_name'] ?? '?');
        $pTime   = isset($playingTimes[$mpId]) ? (int) floor($playingTimes[$mpId] / 60) : 0;
        $goals   = $playerGoals[$pid]   ?? 0;
        $assists = $playerAssists[$pid] ?? 0;
        $card    = $playerCards[$pid]   ?? null;
        if ($pTime === 0 && $goals === 0 && $assists === 0 && $card === null) { continue; }
        ?>
        <div class="live-player-row">
            <div class="live-player-number">
                <?php if ($p['squad_number'] !== null): ?>
                    <span><?= (int) $p['squad_number'] ?></span>
                <?php endif; ?>
            </div>
            <div class="live-player-name"><?= e($name) ?></div>
            <div class="live-player-stats">
                <span class="live-player-time"><?= $pTime ?>'</span>
                <?php if ($goals > 0): ?>
                    <span class="live-stat-badge live-stat--goal">⚽<?= $goals ?></span>
                <?php endif; ?>
                <?php if ($assists > 0): ?>
                    <span class="live-stat-badge">🅰<?= $assists ?></span>
                <?php endif; ?>
                <?php if ($card === 'yellow_card'): ?>
                    <span class="live-card live-card--yellow"></span>
                <?php elseif ($card === 'red_card'): ?>
                    <span class="live-card live-card--red"></span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Reopen match (administrator only) -->
<?php if (!empty($_SESSION['user']['is_administrator'])): ?>
<div style="margin-bottom:1.5rem;">
    <form method="POST"
          action="<?= e(APP_URL) ?>/public/index.php?page=match&action=review&id=<?= $matchId ?>"
          onsubmit="return confirm(<?= e(json_encode(t('match.reopen') . '?')) ?>)">
        <input type="hidden" name="_action" value="reopen">
        <button type="submit" class="btn btn--secondary btn--full btn--sm" style="color:var(--color-danger);">
            <?= e(t('match.reopen')) ?>
        </button>
    </form>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
$title   = $match['opponent'] . ' · ' . $dateLabel;
require dirname(__DIR__, 2) . '/templates/layout.php';
