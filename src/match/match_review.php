<?php
declare(strict_types=1);

$activePage   = 'match';
$matchRepo    = new MatchRepository();
$matchService = new MatchService();

$id    = (int) ($_GET['id'] ?? 0);
$match = $matchRepo->getMatchById($id);

if ($match === null) {
    $_SESSION['flash'] = t('match.not_found');
    redirect(APP_URL . '/index.php?page=match');
}

if ($match['status'] !== 'finished') {
    redirect(APP_URL . '/index.php?page=match&action=live&id=' . $id);
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
    redirect(APP_URL . '/index.php?page=match&action=live&id=' . $matchId);
}

// Data
$players      = $matchRepo->getMatchPlayers($matchId);
$events       = $matchRepo->getMatchEvents($matchId);
$subs         = $matchRepo->getSubstitutions($matchId);
$playingTimes = $matchService->calculatePlayingTime($matchId);
$ratings      = $matchRepo->getMatchRatings($matchId);

// Present players for ratings (from attendance)
$pdo        = Database::getInstance()->getConnection();
$attStmt    = $pdo->prepare(
    "SELECT a.player_id, p.first_name, p.squad_number
     FROM attendance a
     JOIN player p ON p.id = a.player_id
     WHERE a.context_type = 'match'
       AND a.context_id = ?
       AND a.status = 'present'
       AND p.deleted_at IS NULL
     ORDER BY p.squad_number IS NULL ASC, p.squad_number ASC, p.first_name ASC"
);
$attStmt->execute([$matchId]);
$presentPlayers = $attStmt->fetchAll();

// Result
$scored   = (int) ($match['goals_scored']   ?? 0);
$conceded = (int) ($match['goals_conceded'] ?? 0);
$result   = $scored > $conceded ? 'win' : ($scored < $conceded ? 'loss' : 'draw');
$resultColor = match ($result) {
    'win'  => 'var(--color-success)',
    'loss' => 'var(--color-danger)',
    default => 'var(--color-neutral)',
};

// Per-player stats
$playerGoals   = [];
$playerAssists = [];
$playerCards   = [];
foreach ($events as $e) {
    $pid = $e['player_id'] !== null ? (int) $e['player_id'] : null;
    $aid = $e['assist_player_id'] !== null ? (int) $e['assist_player_id'] : null;
    if ($e['event_type'] === 'goal') {
        if (!($e['scored_via'] === 'penalty' && $e['penalty_scored'] == 0)) {
            if ($pid !== null) { $playerGoals[$pid]   = ($playerGoals[$pid]   ?? 0) + 1; }
            if ($aid !== null) { $playerAssists[$aid] = ($playerAssists[$aid] ?? 0) + 1; }
        }
    }
    if (in_array($e['event_type'], ['yellow_card', 'red_card'], true) && $pid !== null) {
        $playerCards[$pid] = $e['event_type'];
    }
}

// Merged timeline (events + subs sorted by half/minute)
$timeline = [];
foreach ($events as $e) {
    $timeline[] = ['type' => 'event', 'half' => (int) $e['half'], 'minute' => (int) $e['minute'], 'data' => $e];
}
foreach ($subs as $s) {
    $timeline[] = ['type' => 'sub', 'half' => (int) $s['half'], 'minute' => (int) $s['minute'], 'data' => $s];
}
usort($timeline, fn($a, $b) => $a['half'] !== $b['half'] ? $a['half'] <=> $b['half'] : $a['minute'] <=> $b['minute']);

// Playing time sorted descending
$playersSorted = $players;
usort($playersSorted, function ($a, $b) use ($playingTimes) {
    return ($playingTimes[(int) $b['id']] ?? 0) <=> ($playingTimes[(int) $a['id']] ?? 0);
});

$dateLabel = date('j M Y', strtotime($match['date']));
$skillKeys = ['pace', 'shooting', 'passing', 'dribbling', 'defending', 'physicality'];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/index.php?page=match"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title" style="font-size:1rem;">
        <?= e($match['opponent']) ?> · <?= e($dateLabel) ?>
    </h1>
    <span></span>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-message"><?= e($flash) ?></div>
<?php endif; ?>

<!-- Section 1: Result summary -->
<div class="card" style="text-align:center; border-left:4px solid <?= $resultColor ?>; margin-bottom:0.75rem;">
    <div class="text-sm text-muted mb-1">
        <?= e(t('match.' . $match['home_away'])) ?>
        · <?= e(t('match.type.' . $match['match_type'])) ?>
        · <?= e($dateLabel) ?>
    </div>
    <div style="font-size:2.5rem; font-weight:800; line-height:1.1; color:var(--color-text);">
        <?= $scored ?> – <?= $conceded ?>
    </div>
    <div class="text-sm mt-1" style="color:<?= $resultColor ?>; font-weight:700; text-transform:uppercase; letter-spacing:.05em;">
        <?= e(t('review.result.' . $result)) ?>
    </div>
</div>

<!-- Section 2: Event timeline -->
<?php if (!empty($timeline)): ?>
<div class="card" style="margin-bottom:0.75rem;">
    <h3 class="text-sm text-muted mb-2" style="font-weight:600;"><?= e(t('review.timeline')) ?></h3>
    <?php foreach ($timeline as $entry): ?>
        <?php if ($entry['type'] === 'sub'):
            $sub = $entry['data']; ?>
        <div class="live-event-row">
            <span class="live-event-min"><?= (int) $sub['minute'] ?>'</span>
            <span class="live-event-icon">↕</span>
            <span class="live-event-text">
                ↑ <?= e($sub['player_on_name']) ?> · ↓ <?= e($sub['player_off_name']) ?>
            </span>
        </div>
        <?php else:
            $ev     = $entry['data'];
            $evMin  = (int) $ev['minute'];
            $evHalf = (int) $ev['half'];
            $evPlayer = $ev['player_name'] ?? null;
            $evAssist = $ev['assist_name'] ?? null;
            $evIcon = '';
            $evText = '';
            switch ($ev['event_type']) {
                case 'goal':
                    if ($ev['scored_via'] === 'penalty' && $ev['penalty_scored'] == 0) {
                        $evIcon = '✕';
                        $evText = t('live.event.via.penalty') . ' ' . t('live.event.penalty_missed');
                        if ($evPlayer) { $evText .= ' · ' . $evPlayer; }
                    } else {
                        $evIcon = '⚽';
                        $evText = $evPlayer ?? t('live.event.unknown');
                        if ($evAssist) { $evText .= ' · ' . $evAssist; }
                        if ($ev['scored_via'] !== 'open_play') {
                            $evText .= ' (' . t('live.event.via.' . $ev['scored_via']) . ')';
                        }
                        if (!empty($ev['zone'])) {
                            $evText .= ' · ' . t('zone.' . $ev['zone']);
                        }
                    }
                    break;
                case 'own_goal':
                    $evIcon = '⚽';
                    $evText = t('event.own_goal');
                    if (!empty($ev['zone'])) { $evText .= ' · ' . t('zone.' . $ev['zone']); }
                    break;
                case 'yellow_card':
                    $evIcon = '🟨';
                    $evText = $evPlayer ?? '';
                    break;
                case 'red_card':
                    $evIcon = '🟥';
                    $evText = $evPlayer ?? '';
                    break;
                case 'note':
                    $evIcon = '📋';
                    $evText = $ev['note_text'] ?? '';
                    break;
            }
        ?>
        <div class="live-event-row">
            <span class="live-event-min"><?= $evMin ?>'</span>
            <span class="live-event-icon"><?= $evIcon ?></span>
            <span class="live-event-text"><?= e($evText) ?></span>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Section 3: Playing time -->
<div class="card" style="margin-bottom:0.75rem;">
    <h3 class="text-sm text-muted mb-2" style="font-weight:600;"><?= e(t('review.playing_time')) ?></h3>
    <?php foreach ($playersSorted as $p):
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
                <?php $num = (bool) $p['is_guest'] ? ($p['guest_squad_number'] ?? null) : ($p['squad_number'] ?? null); ?>
                <?php if ($num !== null): ?><span><?= (int) $num ?></span><?php endif; ?>
            </div>
            <div class="live-player-name">
                <?= e($name) ?>
                <?php if ((bool) $p['is_guest']): ?>
                    <span class="badge badge--neutral" style="font-size:0.7rem;"><?= e(t('guest.label')) ?></span>
                <?php endif; ?>
            </div>
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

<!-- Section 4: Post-match ratings -->
<div class="card" style="margin-bottom:0.75rem;">
    <h3 class="text-sm text-muted mb-1" style="font-weight:600;"><?= e(t('rating.title')) ?></h3>
    <?php if (empty($ratings)): ?>
        <p class="text-sm text-muted mb-2"><?= e(t('rating.optional_note')) ?></p>
    <?php endif; ?>
    <?php if (!empty($presentPlayers)): ?>
    <form method="POST"
          action="<?= e(APP_URL) ?>/index.php?page=match&action=rate&id=<?= $matchId ?>">
        <?= csrfField() ?>
        <input type="hidden" name="match_id" value="<?= $matchId ?>">
        <?php foreach ($presentPlayers as $p):
            $pid = (int) $p['player_id'];
            $r   = $ratings[$pid] ?? [];
        ?>
            <input type="hidden" name="player_ids[]" value="<?= $pid ?>">
            <div style="padding:0.75rem 0; border-bottom:1px solid var(--color-border);">
                <div style="font-weight:600; font-size:var(--font-size-sm); margin-bottom:0.5rem;">
                    <?php if ($p['squad_number'] !== null): ?>
                        <span class="text-muted" style="margin-right:0.4rem;">#<?= (int) $p['squad_number'] ?></span>
                    <?php endif; ?>
                    <?= e($p['first_name']) ?>
                </div>
                <?php foreach ($skillKeys as $skill):
                    $val = (int) ($r[$skill] ?? 0);
                ?>
                    <div class="flex-between" style="margin-bottom:0.3rem;">
                        <span class="text-sm text-muted"><?= e(t('skill.' . $skill)) ?></span>
                        <div class="star-group" style="display:inline-flex; gap:0.1rem;">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <span class="star"
                                      style="cursor:pointer; font-size:1.4rem; color:var(--color-accent); user-select:none; touch-action:manipulation;">
                                    <?= $s <= $val ? '★' : '☆' ?>
                                </span>
                            <?php endfor; ?>
                            <input type="hidden" name="<?= $skill ?>[<?= $pid ?>]"
                                   value="<?= $val > 0 ? $val : '' ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn--primary btn--full" style="margin-top:1rem;">
            <?= e(t('rating.save')) ?>
        </button>
    </form>
    <?php endif; ?>
</div>

<!-- Reopen match (administrator only) -->
<?php if (!empty($_SESSION['user']['is_administrator'])): ?>
<div style="margin-bottom:1.5rem;">
    <form method="POST"
          action="<?= e(APP_URL) ?>/index.php?page=match&action=review&id=<?= $matchId ?>"
          onsubmit="return confirm(<?= e(json_encode(t('review.reopen_confirm'))) ?>)">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="reopen">
        <button type="submit" class="btn btn--secondary btn--full btn--sm" style="color:var(--color-danger);">
            <?= e(t('review.reopen')) ?>
        </button>
    </form>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.star-group').forEach(function(group) {
    var stars = Array.from(group.querySelectorAll('.star'));
    var input = group.querySelector('input[type="hidden"]');

    function render(value) {
        stars.forEach(function(s, i) {
            s.textContent = i < value ? '★' : '☆';
        });
    }

    stars.forEach(function(star, index) {
        star.addEventListener('click', function() {
            input.value = index + 1;
            render(index + 1);
        });
        star.addEventListener('mouseenter', function() { render(index + 1); });
    });

    group.addEventListener('mouseleave', function() {
        render(parseInt(input.value) || 0);
    });
});
</script>

<?php
$content = ob_get_clean();
$title   = $match['opponent'] . ' · ' . $dateLabel;
require dirname(__DIR__, 2) . '/templates/layout.php';
