<?php
declare(strict_types=1);

$activePage   = 'match';
$matchRepo    = new MatchRepository();
$matchService = new MatchService();
$formationRepo = new FormationRepository();

$id    = (int) ($_GET['id'] ?? 0);
$match = $matchRepo->getMatchById($id);

if ($match === null) {
    $_SESSION['flash'] = t('match.not_found');
    redirect(APP_URL . '/public/index.php?page=match');
}

if ($match['status'] === 'finished') {
    redirect(APP_URL . '/public/index.php?page=match&action=review&id=' . $id);
}

if (!in_array($match['status'], ['prepared', 'active'], true)) {
    redirect(APP_URL . '/public/index.php?page=match');
}

$matchId = (int) $match['id'];
$teamId  = (int) $match['team_id'];
$backUrl = APP_URL . '/public/index.php?page=match&action=live&id=' . $matchId;

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['_action'] ?? '';

    switch ($postAction) {
        case 'start_half':
            $halfNum = (int) ($_POST['half'] ?? 1);
            if (in_array($halfNum, [1, 2], true)) {
                $matchService->startHalf($matchId, $halfNum);
            }
            break;

        case 'stop_half':
            $halfNum = (int) ($_POST['half'] ?? 1);
            if (in_array($halfNum, [1, 2], true)) {
                $matchService->stopHalf($matchId, $halfNum);
            }
            break;

        case 'resume_half':
            $halfNum = (int) ($_POST['half'] ?? 1);
            if (in_array($halfNum, [1, 2], true)) {
                $matchService->resumeHalf($matchId, $halfNum);
            }
            break;

        case 'make_sub':
            $playerOffId = (int) ($_POST['player_off_id'] ?? 0);
            $playerOnId  = (int) ($_POST['player_on_id']  ?? 0);
            if ($playerOffId > 0 && $playerOnId > 0) {
                $matchService->registerSubstitution($matchId, [
                    'player_off_id' => $playerOffId,
                    'player_on_id'  => $playerOnId,
                ]);
            }
            break;

        case 'undo_sub':
            $subId = (int) ($_POST['substitution_id'] ?? 0);
            if ($subId > 0) {
                $matchService->undoSubstitution($subId);
            }
            break;

        case 'change_position':
            $mpId     = (int) ($_POST['match_player_id'] ?? 0);
            $posX     = (float) ($_POST['pos_x']         ?? 0);
            $posY     = (float) ($_POST['pos_y']         ?? 0);
            $posLabel = trim($_POST['position_label']    ?? '');
            if ($mpId > 0 && $posLabel !== '') {
                $matchRepo->updatePosition($mpId, $posX, $posY, $posLabel);
            }
            break;

        case 'register_goal':
        case 'register_own_goal':
            $eventType    = ($postAction === 'register_own_goal') ? 'own_goal' : 'goal';
            $scoredVia    = $_POST['scored_via']      ?? 'open_play';
            $penaltyScored = isset($_POST['penalty_scored']) ? (int) $_POST['penalty_scored'] : null;
            $playerIdRaw  = isset($_POST['player_id']) && $_POST['player_id'] !== '' ? (int) $_POST['player_id'] : null;
            $assistIdRaw  = isset($_POST['assist_player_id']) && $_POST['assist_player_id'] !== '' ? (int) $_POST['assist_player_id'] : null;
            $zone         = $_POST['zone'] ?? null;

            $validZones = ['tl', 'tm', 'tr', 'ml', 'mm', 'mr', 'bl', 'bm', 'br'];
            $validVia   = ['open_play', 'free_kick', 'penalty'];
            if ($zone !== null && !in_array($zone, $validZones, true)) { $zone = null; }
            if (!in_array($scoredVia, $validVia, true)) { $scoredVia = 'open_play'; }

            $matchService->registerGoal($matchId, [
                'event_type'       => $eventType,
                'player_id'        => $playerIdRaw,
                'assist_player_id' => $assistIdRaw,
                'scored_via'       => $scoredVia,
                'penalty_scored'   => $penaltyScored,
                'zone'             => $zone,
            ]);
            break;

        case 'register_card':
            $cardType = $_POST['card_type'] ?? 'yellow_card';
            if (!in_array($cardType, ['yellow_card', 'red_card'], true)) { $cardType = 'yellow_card'; }
            $cardPlayerId = isset($_POST['player_id']) && $_POST['player_id'] !== '' ? (int) $_POST['player_id'] : null;
            if ($cardPlayerId !== null) {
                $matchService->registerCard($matchId, [
                    'event_type' => $cardType,
                    'player_id'  => $cardPlayerId,
                ]);
            }
            break;

        case 'register_note':
            $noteText = trim($_POST['note_text'] ?? '');
            if ($noteText !== '') {
                $matchService->registerNote($matchId, ['note_text' => $noteText]);
            }
            break;

        case 'delete_event':
            $eventId = (int) ($_POST['event_id'] ?? 0);
            if ($eventId > 0) {
                $matchService->deleteEvent($eventId);
            }
            break;

        case 'close_match':
            $goalsScored   = (int) ($_POST['goals_scored']   ?? 0);
            $goalsConceded = (int) ($_POST['goals_conceded'] ?? 0);
            $halfNum       = (int) ($_POST['half']           ?? 2);
            // Stop the half first, then close
            $matchService->stopHalf($matchId, $halfNum);
            $ok = $matchService->closeMatch($matchId, $goalsScored, $goalsConceded);
            if ($ok) {
                redirect(APP_URL . '/public/index.php?page=match&action=review&id=' . $matchId);
            }
            break;
    }

    redirect($backUrl);
}

// ─── Data loading ────────────────────────────────────────────────────────────

$halves  = $matchRepo->getMatchHalves($matchId);
$players = $matchRepo->getMatchPlayers($matchId);
$events  = $matchRepo->getMatchEvents($matchId);
$subs    = $matchRepo->getSubstitutions($matchId);
$score   = $matchService->getScoreFromEvents($matchId);
$minute  = $matchService->getCurrentMinute($matchId);

// Index halves
$half1 = null;
$half2 = null;
foreach ($halves as $h) {
    if ((int) $h['number'] === 1) { $half1 = $h; }
    if ((int) $h['number'] === 2) { $half2 = $h; }
}

// Determine half state
if ($half1 === null) {
    $halfState = 'before';
} elseif ($half1['started_at'] !== null && $half1['stopped_at'] === null) {
    $halfState = 'h1_running';
} elseif ($half1['started_at'] !== null && $half1['stopped_at'] !== null && ($half2 === null || $half2['started_at'] === null)) {
    $halfState = 'half_time';
} elseif ($half2 !== null && $half2['started_at'] !== null && $half2['stopped_at'] === null) {
    $halfState = 'h2_running';
} elseif ($half2 !== null && $half2['started_at'] !== null && $half2['stopped_at'] !== null) {
    $halfState = 'h2_stopped';
} else {
    $halfState = 'before';
}

$halfLabel = match ($halfState) {
    'h1_running' => t('live.half.first'),
    'half_time'  => t('live.half.half_time'),
    'h2_running' => t('live.half.second'),
    'h2_stopped' => t('live.half.full_time'),
    default      => '',
};

// Players by role
$starters = array_values(array_filter($players, fn($p) => (bool) $p['in_starting_eleven']));
$bench    = array_values(array_filter($players, fn($p) => !(bool) $p['in_starting_eleven']));

// Name helper
function livePlayerName(array $p): string
{
    return (bool) $p['is_guest']
        ? ($p['guest_name'] ?? '?')
        : ($p['first_name'] ?? '?');
}

// Per-player event stats (goals, assists, cards)
$playerGoals   = [];
$playerAssists = [];
$playerCards   = [];
foreach ($events as $e) {
    $pid = $e['player_id'] !== null ? (int) $e['player_id'] : null;
    $aid = $e['assist_player_id'] !== null ? (int) $e['assist_player_id'] : null;
    if ($e['event_type'] === 'goal') {
        if ($e['scored_via'] !== 'penalty' || $e['penalty_scored'] != 0) {
            if ($pid !== null) { $playerGoals[$pid] = ($playerGoals[$pid] ?? 0) + 1; }
            if ($aid !== null) { $playerAssists[$aid] = ($playerAssists[$aid] ?? 0) + 1; }
        }
    }
    if (in_array($e['event_type'], ['yellow_card', 'red_card'], true) && $pid !== null) {
        $playerCards[$pid] = $e['event_type'];
    }
}

// Playing time per match_player
$playingTimes = $matchService->calculatePlayingTime($matchId);

// Formation positions for position-change modal
$formationId = $match['formation_id'] ? (int) $match['formation_id'] : null;
$positions   = $formationId ? $formationRepo->getPositionsByFormation($formationId) : [];

// Team name for score header
$pdo      = Database::getInstance()->getConnection();
$teamStmt = $pdo->prepare('SELECT name FROM team WHERE id = ? LIMIT 1');
$teamStmt->execute([$teamId]);
$teamRow  = $teamStmt->fetch();
$teamName = $teamRow ? $teamRow['name'] : t('nav.squad');

$isHome  = $match['home_away'] === 'home';
$scored  = $score['scored'];
$conceded = $score['conceded'];

// Home/away display
if ($isHome) {
    $leftLabel  = $teamName;
    $leftScore  = $scored;
    $rightScore = $conceded;
    $rightLabel = $match['opponent'];
} else {
    $leftLabel  = $match['opponent'];
    $leftScore  = $conceded;
    $rightScore = $scored;
    $rightLabel = $teamName;
}

$dateLabel = date('j M', strtotime($match['date']));
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

<!-- Score header -->
<div class="live-score-header">
    <div class="live-score-team"><?= e($leftLabel) ?></div>
    <div class="live-score-center">
        <div class="live-score-value"><?= $leftScore ?> – <?= $rightScore ?></div>
        <?php if ($halfLabel !== ''): ?>
            <div class="live-score-half"><?= e($halfLabel) ?></div>
        <?php endif; ?>
        <?php if (in_array($halfState, ['h1_running', 'h2_running'], true)): ?>
            <div class="live-score-minute"><?= $minute ?><?= e(t('live.minute')) ?></div>
        <?php endif; ?>
    </div>
    <div class="live-score-team live-score-team--right"><?= e($rightLabel) ?></div>
</div>

<!-- Half controls -->
<div class="live-half-controls">
    <?php if ($halfState === 'before'): ?>
        <form method="POST" action="<?= e($backUrl) ?>"
              onsubmit="return confirm(<?= e(json_encode(t('live.half.confirm_start'))) ?>)">
            <input type="hidden" name="_action" value="start_half">
            <input type="hidden" name="half" value="1">
            <button class="btn btn--primary btn--full"><?= e(t('live.half.start_first')) ?></button>
        </form>

    <?php elseif ($halfState === 'h1_running'): ?>
        <form method="POST" action="<?= e($backUrl) ?>"
              onsubmit="return confirm(<?= e(json_encode(t('live.half.confirm_stop'))) ?>)">
            <input type="hidden" name="_action" value="stop_half">
            <input type="hidden" name="half" value="1">
            <button class="btn btn--secondary btn--full"><?= e(t('live.half.stop_first')) ?></button>
        </form>

    <?php elseif ($halfState === 'half_time'): ?>
        <div style="display:flex; gap:0.5rem;">
            <form method="POST" action="<?= e($backUrl) ?>"
                  onsubmit="return confirm(<?= e(json_encode(t('live.half.confirm_start'))) ?>)"
                  style="flex:2;">
                <input type="hidden" name="_action" value="start_half">
                <input type="hidden" name="half" value="2">
                <button class="btn btn--primary btn--full"><?= e(t('live.half.start_second')) ?></button>
            </form>
            <form method="POST" action="<?= e($backUrl) ?>" style="flex:1;">
                <input type="hidden" name="_action" value="resume_half">
                <input type="hidden" name="half" value="1">
                <button class="btn btn--secondary btn--full" style="font-size:0.85rem;">
                    <?= e(t('live.half.resume')) ?>
                </button>
            </form>
        </div>

    <?php elseif ($halfState === 'h2_running'): ?>
        <button class="btn btn--secondary btn--full"
                onclick="openCloseMatchModal(<?= $scored ?>, <?= $conceded ?>, 2)">
            <?= e(t('live.half.stop_second')) ?>
        </button>

    <?php elseif ($halfState === 'h2_stopped'): ?>
        <div style="display:flex; gap:0.5rem;">
            <button class="btn btn--primary btn--full"
                    onclick="openCloseMatchModal(<?= $scored ?>, <?= $conceded ?>, 2)"
                    style="flex:2;">
                <?= e(t('live.close.confirm')) ?>
            </button>
            <form method="POST" action="<?= e($backUrl) ?>" style="flex:1;">
                <input type="hidden" name="_action" value="resume_half">
                <input type="hidden" name="half" value="2">
                <button class="btn btn--secondary btn--full" style="font-size:0.85rem;">
                    <?= e(t('live.half.resume')) ?>
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Tab navigation -->
<div class="live-tabs">
    <button class="live-tab live-tab--active" id="tab-pitch-btn"
            onclick="switchTab('pitch')"><?= e(t('lineup.pitch')) ?></button>
    <button class="live-tab" id="tab-players-btn"
            onclick="switchTab('players')"><?= e(t('lineup.players')) ?></button>
</div>

<!-- Pitch tab -->
<div id="tab-pitch" class="live-tab-content">
    <?php if (!empty($starters)): ?>
        <div class="pitch-wrap">
            <div class="pitch-inner">
                <?php foreach ($starters as $sp): ?>
                    <?php
                    $name   = livePlayerName($sp);
                    $initls = mb_strtoupper(mb_substr($name, 0, 2));
                    $posX   = $sp['pos_x'] !== null ? (float) $sp['pos_x'] : 50.0;
                    $posY   = $sp['pos_y'] !== null ? (float) $sp['pos_y'] : 50.0;
                    $pid    = (int) $sp['player_id'];
                    $mpId   = (int) $sp['id'];
                    $isGuest = (bool) $sp['is_guest'];
                    ?>
                    <div class="pitch-position"
                         style="left:<?= $posX ?>%; top:<?= $posY ?>%;"
                         <?php if (!$isGuest): ?>
                         onclick="openPlayerSheet(<?= htmlspecialchars(json_encode([
                             'mpId'          => $mpId,
                             'playerId'      => $pid,
                             'name'          => $name,
                             'positionLabel' => $sp['position_label'],
                             'posX'          => $posX,
                             'posY'          => $posY,
                         ]), ENT_QUOTES) ?>)"
                         <?php endif; ?>
                         >
                        <div class="pitch-circle pitch-circle--filled">
                            <?= e($initls) ?>
                        </div>
                        <div class="pitch-name"><?= e($name) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card"><p class="text-muted text-sm"><?= e(t('player.no_players')) ?></p></div>
    <?php endif; ?>

    <!-- Bench -->
    <?php if (!empty($bench)): ?>
        <div class="card" style="margin-top:0.75rem;">
            <div class="text-sm text-muted mb-1"><?= e(t('lineup.bench')) ?></div>
            <?php foreach ($bench as $bp): ?>
                <?php
                $bName   = livePlayerName($bp);
                $bPid    = (int) $bp['player_id'];
                $bIsGuest = (bool) $bp['is_guest'];
                ?>
                <div class="bench-player">
                    <div class="player-circle player-circle--sm"
                         style="<?= $bIsGuest ? 'background:var(--color-neutral);' : '' ?>">
                        <?= e(mb_strtoupper(mb_substr($bName, 0, 2))) ?>
                    </div>
                    <div class="bench-player-info">
                        <div class="bench-player-name">
                            <?= e($bName) ?>
                            <?php if ($bp['squad_number'] !== null): ?>
                                <span class="text-muted">#<?= (int) $bp['squad_number'] ?></span>
                            <?php endif; ?>
                            <?php if ($bIsGuest): ?>
                                <span class="badge badge--accent" style="font-size:0.65rem;"><?= e(t('match.guest')) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Players tab -->
<div id="tab-players" class="live-tab-content" style="display:none;">

    <!-- On-pitch players -->
    <?php if (!empty($starters)): ?>
        <div class="card" style="margin-bottom:0.5rem;">
            <div class="text-sm text-muted mb-1"><?= e(t('match.status.active')) ?></div>
            <?php foreach ($starters as $sp): ?>
                <?php
                $name    = livePlayerName($sp);
                $pid     = (int) $sp['player_id'];
                $mpId    = (int) $sp['id'];
                $pTime   = isset($playingTimes[$mpId]) ? (int) floor($playingTimes[$mpId] / 60) : 0;
                $goals   = $playerGoals[$pid]   ?? 0;
                $assists = $playerAssists[$pid] ?? 0;
                $card    = $playerCards[$pid]   ?? null;
                ?>
                <div class="live-player-row">
                    <div class="live-player-number">
                        <?php if ($sp['squad_number'] !== null): ?>
                            <span><?= (int) $sp['squad_number'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="live-player-name"><?= e($name) ?></div>
                    <div class="live-player-stats">
                        <span class="live-player-time"><?= $pTime ?>'</span>
                        <?php if ($goals > 0): ?>
                            <span class="live-stat-badge live-stat--goal">⚽<?= $goals ?></span>
                        <?php endif; ?>
                        <?php if ($assists > 0): ?>
                            <span class="live-stat-badge live-stat--assist">🅰<?= $assists ?></span>
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
    <?php endif; ?>

    <!-- Bench players -->
    <?php if (!empty($bench)): ?>
        <div class="card" style="margin-bottom:0.5rem;">
            <div class="text-sm text-muted mb-1"><?= e(t('lineup.bench')) ?></div>
            <?php foreach ($bench as $bp): ?>
                <?php
                $name    = livePlayerName($bp);
                $pid     = (int) $bp['player_id'];
                $mpId    = (int) $bp['id'];
                $pTime   = isset($playingTimes[$mpId]) ? (int) floor($playingTimes[$mpId] / 60) : 0;
                $goals   = $playerGoals[$pid]   ?? 0;
                $assists = $playerAssists[$pid] ?? 0;
                $card    = $playerCards[$pid]   ?? null;
                ?>
                <div class="live-player-row live-player-row--bench">
                    <div class="live-player-number">
                        <?php if ($bp['squad_number'] !== null): ?>
                            <span><?= (int) $bp['squad_number'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="live-player-name">
                        <?= e($name) ?>
                        <?php if ((bool) $bp['is_guest']): ?>
                            <span class="badge badge--accent" style="font-size:0.65rem;"><?= e(t('match.guest')) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="live-player-stats">
                        <?php if ($pTime > 0): ?>
                            <span class="live-player-time"><?= $pTime ?>'</span>
                        <?php endif; ?>
                        <?php if ($goals > 0): ?>
                            <span class="live-stat-badge live-stat--goal">⚽<?= $goals ?></span>
                        <?php endif; ?>
                        <?php if ($card !== null): ?>
                            <span class="live-card live-card--<?= $card === 'yellow_card' ? 'yellow' : 'red' ?>"></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Event timeline -->
    <?php if (!empty($events)): ?>
        <div class="card">
            <div class="text-sm text-muted mb-1"><?= e(t('match.score')) ?></div>
            <?php foreach ($events as $ev): ?>
                <?php
                $evType   = $ev['event_type'];
                $evMin    = (int) $ev['minute'];
                $evHalf   = (int) $ev['half'];
                $evPlayer = $ev['player_name'] ?? null;
                $evAssist = $ev['assist_name'] ?? null;
                $evText   = '';
                $evIcon   = '';
                switch ($evType) {
                    case 'goal':
                        if ($ev['scored_via'] === 'penalty' && $ev['penalty_scored'] == 0) {
                            $evIcon = '✕'; $evText = t('live.event.via.penalty') . ' ' . t('live.event.penalty_missed');
                        } elseif ($ev['scored_via'] === 'penalty') {
                            $evIcon = '⚽'; $evText = ($evPlayer ?? t('live.event.unknown')) . ' (' . t('live.event.via.penalty') . ')';
                        } elseif ($ev['scored_via'] === 'free_kick') {
                            $evIcon = '⚽'; $evText = ($evPlayer ?? t('live.event.unknown')) . ' (' . t('live.event.via.free_kick') . ')';
                        } else {
                            $evIcon = '⚽'; $evText = $evPlayer ?? t('live.event.unknown');
                            if ($evAssist) { $evText .= ' · ' . $evAssist; }
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
                <div class="live-event-row" id="event-<?= (int) $ev['id'] ?>">
                    <span class="live-event-min"><?= $evMin ?>'</span>
                    <span class="live-event-icon"><?= $evIcon ?></span>
                    <span class="live-event-text"><?= e($evText) ?></span>
                    <button class="live-event-delete"
                            onclick="confirmDeleteEvent(<?= (int) $ev['id'] ?>, <?= e(json_encode(t('live.event.delete_confirm'))) ?>)"
                            title="<?= e(t('live.event.delete')) ?>">×</button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Substitution log -->
    <?php if (!empty($subs)): ?>
        <div class="card" style="margin-top:0.5rem;">
            <div class="text-sm text-muted mb-1"><?= e(t('live.sub.title')) ?></div>
            <?php foreach ($subs as $sub): ?>
                <div class="live-event-row">
                    <span class="live-event-min"><?= (int) $sub['minute'] ?>'</span>
                    <span class="live-event-icon">↕</span>
                    <span class="live-event-text">
                        ↑ <?= e($sub['player_on_name']) ?>
                        ↓ <?= e($sub['player_off_name']) ?>
                    </span>
                    <form method="POST" action="<?= e($backUrl) ?>" style="display:inline;"
                          onsubmit="return confirm('<?= e(t('action.confirm')) ?>?')">
                        <input type="hidden" name="_action" value="undo_sub">
                        <input type="hidden" name="substitution_id" value="<?= (int) $sub['id'] ?>">
                        <button type="submit" class="live-event-delete"
                                title="<?= e(t('action.undo')) ?>">↩</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- FAB -->
<?php if ($match['status'] === 'active'): ?>
<button class="fab" id="fab-btn" onclick="openEventSheet()" title="<?= e(t('event.add')) ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
         stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
    </svg>
</button>
<?php endif; ?>

<!-- Delete event form (hidden) -->
<form id="delete-event-form" method="POST" action="<?= e($backUrl) ?>">
    <input type="hidden" name="_action" value="delete_event">
    <input type="hidden" name="event_id" id="delete-event-id" value="">
</form>

<!-- ── Modals ──────────────────────────────────────────────────────────────── -->

<!-- Player action sheet (substitution / position change) -->
<div id="player-sheet" class="player-modal-overlay" style="display:none;" onclick="closeSheet(event,'player-sheet')">
    <div class="player-modal">
        <div class="player-modal-title" id="player-sheet-title"></div>
        <div id="player-sheet-content"></div>
    </div>
</div>

<!-- Substitution: incoming player selector -->
<div id="sub-sheet" class="player-modal-overlay" style="display:none;" onclick="closeSheet(event,'sub-sheet')">
    <div class="player-modal">
        <div class="player-modal-title"><?= e(t('live.sub.select_incoming')) ?></div>
        <div id="sub-sheet-content"></div>
        <form id="sub-form" method="POST" action="<?= e($backUrl) ?>">
            <input type="hidden" name="_action" value="make_sub">
            <input type="hidden" name="player_off_id" id="sub-off-id">
            <input type="hidden" name="player_on_id"  id="sub-on-id">
        </form>
    </div>
</div>

<!-- Position change sheet -->
<div id="pos-sheet" class="player-modal-overlay" style="display:none;" onclick="closeSheet(event,'pos-sheet')">
    <div class="player-modal">
        <div class="player-modal-title"><?= e(t('live.position.change')) ?></div>
        <form id="pos-form" method="POST" action="<?= e($backUrl) ?>">
            <input type="hidden" name="_action" value="change_position">
            <input type="hidden" name="match_player_id" id="pos-mp-id">
            <input type="hidden" name="pos_x"           id="pos-x">
            <input type="hidden" name="pos_y"           id="pos-y">
            <input type="hidden" name="position_label"  id="pos-label">
            <div id="pos-sheet-content"></div>
        </form>
    </div>
</div>

<!-- Event registration sheet -->
<div id="event-sheet" class="player-modal-overlay" style="display:none;" onclick="closeSheet(event,'event-sheet')">
    <div class="player-modal">
        <div class="player-modal-title" id="event-sheet-title"><?= e(t('event.add')) ?></div>
        <div id="event-sheet-content"></div>
    </div>
</div>

<!-- Goal form (hidden, submitted via JS) -->
<form id="goal-form" method="POST" action="<?= e($backUrl) ?>">
    <input type="hidden" name="_action"       id="gf-action"    value="register_goal">
    <input type="hidden" name="player_id"     id="gf-player"    value="">
    <input type="hidden" name="assist_player_id" id="gf-assist" value="">
    <input type="hidden" name="scored_via"    id="gf-via"       value="open_play">
    <input type="hidden" name="penalty_scored" id="gf-penalty"  value="">
    <input type="hidden" name="zone"          id="gf-zone"      value="">
</form>

<!-- Card form -->
<form id="card-form" method="POST" action="<?= e($backUrl) ?>">
    <input type="hidden" name="_action"   value="register_card">
    <input type="hidden" name="card_type" id="cf-type"   value="yellow_card">
    <input type="hidden" name="player_id" id="cf-player" value="">
</form>

<!-- Note form -->
<form id="note-form" method="POST" action="<?= e($backUrl) ?>">
    <input type="hidden" name="_action"  value="register_note">
    <input type="hidden" name="note_text" id="nf-text" value="">
</form>

<script>
// ── Data ──────────────────────────────────────────────────────────────────────
var starters = <?= json_encode(array_map(fn($p) => [
    'mpId'          => (int) $p['id'],
    'playerId'      => (int) $p['player_id'],
    'name'          => livePlayerName($p),
    'number'        => $p['squad_number'],
    'positionLabel' => $p['position_label'],
    'posX'          => (float) ($p['pos_x'] ?? 50),
    'posY'          => (float) ($p['pos_y'] ?? 50),
    'isGuest'       => (bool) $p['is_guest'],
], $starters)) ?>;

var bench = <?= json_encode(array_map(fn($p) => [
    'mpId'     => (int) $p['id'],
    'playerId' => (int) $p['player_id'],
    'name'     => livePlayerName($p),
    'number'   => $p['squad_number'],
    'isGuest'  => (bool) $p['is_guest'],
], $bench)) ?>;

var allPlayers = <?= json_encode(array_map(fn($p) => [
    'mpId'     => (int) $p['id'],
    'playerId' => (int) $p['player_id'],
    'name'     => livePlayerName($p),
    'number'   => $p['squad_number'],
    'isStarter'=> (bool) $p['in_starting_eleven'],
    'isGuest'  => (bool) $p['is_guest'],
], $players)) ?>;

var formationPositions = <?= json_encode(array_map(fn($p) => [
    'label' => $p['position_label'],
    'posX'  => (float) $p['pos_x'],
    'posY'  => (float) $p['pos_y'],
], $positions)) ?>;

var currentMinute = <?= $minute ?>;

var labels = {
    makeSub:      <?= json_encode(t('live.sub.title')) ?>,
    changePos:    <?= json_encode(t('live.position.change')) ?>,
    confirmSub:   <?= json_encode(t('live.sub.confirm')) ?>,
    noSubs:       <?= json_encode(t('player.no_players')) ?>,
    goal:         <?= json_encode(t('live.event.goal')) ?>,
    ownGoal:      <?= json_encode(t('live.event.own_goal')) ?>,
    card:         <?= json_encode(t('live.event.card')) ?>,
    note:         <?= json_encode(t('live.event.note')) ?>,
    scorer:       <?= json_encode(t('live.event.scorer')) ?>,
    assist:       <?= json_encode(t('live.event.assist')) ?>,
    unknown:      <?= json_encode(t('live.event.unknown')) ?>,
    openPlay:     <?= json_encode(t('live.event.via.open_play')) ?>,
    freeKick:     <?= json_encode(t('live.event.via.free_kick')) ?>,
    penalty:      <?= json_encode(t('live.event.via.penalty')) ?>,
    penScored:    <?= json_encode(t('live.event.penalty_scored')) ?>,
    penMissed:    <?= json_encode(t('live.event.penalty_missed')) ?>,
    zoneTitle:    <?= json_encode(t('live.event.zone')) ?>,
    yellowCard:   <?= json_encode(t('live.event.yellow')) ?>,
    redCard:      <?= json_encode(t('live.event.red')) ?>,
    confirmBtn:   <?= json_encode(t('action.confirm')) ?>,
    cancelBtn:    <?= json_encode(t('action.cancel')) ?>,
    zoneLabels: {
        tl: <?= json_encode(t('zone.tl')) ?>, tm: <?= json_encode(t('zone.tm')) ?>, tr: <?= json_encode(t('zone.tr')) ?>,
        ml: <?= json_encode(t('zone.ml')) ?>, mm: <?= json_encode(t('zone.mm')) ?>, mr: <?= json_encode(t('zone.mr')) ?>,
        bl: <?= json_encode(t('zone.bl')) ?>, bm: <?= json_encode(t('zone.bm')) ?>, br: <?= json_encode(t('zone.br')) ?>
    }
};

// ── Tab switching ─────────────────────────────────────────────────────────────
function switchTab(tab) {
    document.getElementById('tab-pitch').style.display    = tab === 'pitch'   ? '' : 'none';
    document.getElementById('tab-players').style.display  = tab === 'players' ? '' : 'none';
    document.getElementById('tab-pitch-btn').classList.toggle('live-tab--active',   tab === 'pitch');
    document.getElementById('tab-players-btn').classList.toggle('live-tab--active', tab === 'players');
}

// ── Sheet helpers ─────────────────────────────────────────────────────────────
function closeSheet(evt, id) {
    if (evt && evt.target !== document.getElementById(id)) { return; }
    document.getElementById(id).style.display = 'none';
}

function showSheet(id) {
    document.getElementById(id).style.display = 'flex';
}

// ── Player action sheet ───────────────────────────────────────────────────────
var currentPlayer = null;

function openPlayerSheet(data) {
    currentPlayer = data;
    document.getElementById('player-sheet-title').textContent = data.name;

    var html = '<div class="player-modal-item" onclick="openSubSheet()">' + labels.makeSub + '</div>';
    if (formationPositions.length > 0) {
        html += '<div class="player-modal-item" onclick="openPosSheet()">' + labels.changePos + '</div>';
    }
    document.getElementById('player-sheet-content').innerHTML = html;
    showSheet('player-sheet');
}

// ── Substitution sheet ────────────────────────────────────────────────────────
function openSubSheet() {
    document.getElementById('player-sheet').style.display = 'none';
    document.getElementById('sub-off-id').value = currentPlayer.playerId;

    var available = bench.filter(function(b) { return !b.isGuest; });
    var html = '';
    if (available.length === 0) {
        html = '<p class="text-muted text-sm" style="padding:1rem;">' + labels.noSubs + '</p>';
    } else {
        available.forEach(function(b) {
            var label = (b.number ? '#' + b.number + ' ' : '') + b.name;
            html += '<div class="player-modal-item" onclick="confirmSub(' + b.playerId + ',' + JSON.stringify(b.name) + ')">'
                  + label + '</div>';
        });
    }
    document.getElementById('sub-sheet-content').innerHTML = html;
    showSheet('sub-sheet');
}

function confirmSub(incomingId, incomingName) {
    var msg = labels.confirmSub + ': ' + incomingName + '?';
    if (confirm(msg)) {
        document.getElementById('sub-on-id').value = incomingId;
        document.getElementById('sub-form').submit();
    }
}

// ── Position change sheet ─────────────────────────────────────────────────────
function openPosSheet() {
    document.getElementById('player-sheet').style.display = 'none';
    document.getElementById('pos-mp-id').value = currentPlayer.mpId;

    var html = '';
    formationPositions.forEach(function(fp) {
        if (fp.label === currentPlayer.positionLabel) { return; }
        html += '<div class="player-modal-item" onclick="selectPosition(' +
            JSON.stringify(fp.label) + ',' + fp.posX + ',' + fp.posY + ')">' +
            fp.label + '</div>';
    });
    if (!html) { html = '<p class="text-muted text-sm" style="padding:1rem;">' + labels.noSubs + '</p>'; }
    document.getElementById('pos-sheet-content').innerHTML = html;
    showSheet('pos-sheet');
}

function selectPosition(label, posX, posY) {
    document.getElementById('pos-label').value = label;
    document.getElementById('pos-x').value     = posX;
    document.getElementById('pos-y').value     = posY;
    if (confirm(label + '?')) {
        document.getElementById('pos-form').submit();
    }
}

// ── Event delete ──────────────────────────────────────────────────────────────
function confirmDeleteEvent(eventId, msg) {
    if (confirm(msg)) {
        document.getElementById('delete-event-id').value = eventId;
        document.getElementById('delete-event-form').submit();
    }
}

// ── Event registration FAB ────────────────────────────────────────────────────
function openEventSheet() {
    document.getElementById('event-sheet-title').textContent = currentMinute + <?= json_encode(t('live.minute')) ?> + ' — ' + labels.goal + ' / ' + labels.ownGoal + ' / ' + labels.card + ' / ' + labels.note;
    var html = '<div class="player-modal-item" onclick="startGoalFlow(false)">' + labels.goal + '</div>'
             + '<div class="player-modal-item" onclick="startGoalFlow(true)">'  + labels.ownGoal + '</div>'
             + '<div class="player-modal-item" onclick="startCardFlow()">'      + labels.card + '</div>'
             + '<div class="player-modal-item" onclick="startNoteFlow()">'      + labels.note + '</div>';
    document.getElementById('event-sheet-content').innerHTML = html;
    showSheet('event-sheet');
}

// ── Goal flow ─────────────────────────────────────────────────────────────────
var goalState = {};

function startGoalFlow(isOwnGoal) {
    goalState = { isOwnGoal: isOwnGoal, scorerId: '', assistId: '', via: 'open_play', penScored: 1, zone: '' };
    document.getElementById('event-sheet-title').textContent = isOwnGoal ? labels.ownGoal : labels.goal;

    if (isOwnGoal) {
        showZonePicker(function(zone) {
            goalState.zone = zone;
            showGoalConfirm();
        });
        return;
    }
    showScorerPicker();
}

function showScorerPicker() {
    var html = '<div class="player-modal-item" onclick="setScorer(0)">' + labels.unknown + '</div>';
    starters.forEach(function(p) {
        if (p.isGuest) { return; }
        html += '<div class="player-modal-item" onclick="setScorer(' + p.playerId + ')">' +
            (p.number ? '#' + p.number + ' ' : '') + p.name + '</div>';
    });
    document.getElementById('event-sheet-content').innerHTML = html;
}

function setScorer(pid) {
    goalState.scorerId = pid;
    showAssistPicker();
}

function showAssistPicker() {
    var html = '<div class="player-modal-item" onclick="setAssist(0)">— <?= e(t('event.assist_optional')) ?></div>';
    starters.forEach(function(p) {
        if (p.isGuest || p.playerId === goalState.scorerId) { return; }
        html += '<div class="player-modal-item" onclick="setAssist(' + p.playerId + ')">' +
            (p.number ? '#' + p.number + ' ' : '') + p.name + '</div>';
    });
    document.getElementById('event-sheet-content').innerHTML = html;
}

function setAssist(pid) {
    goalState.assistId = pid;
    showViaPicker();
}

function showViaPicker() {
    var html = '<div class="player-modal-item" onclick="setVia(\'open_play\')">' + labels.openPlay + '</div>'
             + '<div class="player-modal-item" onclick="setVia(\'free_kick\')">'  + labels.freeKick + '</div>'
             + '<div class="player-modal-item" onclick="setVia(\'penalty\')">'    + labels.penalty + '</div>';
    document.getElementById('event-sheet-content').innerHTML = html;
}

function setVia(via) {
    goalState.via = via;
    if (via === 'penalty') {
        showPenaltyPicker();
    } else {
        showZonePicker(function(zone) {
            goalState.zone = zone;
            showGoalConfirm();
        });
    }
}

function showPenaltyPicker() {
    var html = '<div class="player-modal-item" onclick="setPenaltyResult(1)">' + labels.penScored + '</div>'
             + '<div class="player-modal-item" onclick="setPenaltyResult(0)">' + labels.penMissed + '</div>';
    document.getElementById('event-sheet-content').innerHTML = html;
}

function setPenaltyResult(scored) {
    goalState.penScored = scored;
    if (scored) {
        showZonePicker(function(zone) {
            goalState.zone = zone;
            showGoalConfirm();
        });
    } else {
        showGoalConfirm();
    }
}

function showZonePicker(callback) {
    var zones = [['tl','tm','tr'],['ml','mm','mr'],['bl','bm','br']];
    var html = '<p style="font-weight:600;padding:0.5rem 1rem 0.25rem;">' + labels.zoneTitle + '</p>'
             + '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.25rem;padding:0.5rem 1rem;">';
    zones.forEach(function(row) {
        row.forEach(function(z) {
            html += '<button type="button" class="btn btn--secondary btn--sm" '
                  + 'onclick="(window._zoneCallback && window._zoneCallback(\'' + z + '\'))">'
                  + labels.zoneLabels[z] + '</button>';
        });
    });
    html += '</div>';
    window._zoneCallback = callback;
    document.getElementById('event-sheet-content').innerHTML = html;
}

function showGoalConfirm() {
    var scorerName = labels.unknown;
    if (goalState.scorerId) {
        var sp = starters.find(function(p) { return p.playerId === goalState.scorerId; });
        if (sp) { scorerName = sp.name; }
    }
    var summary = currentMinute + <?= json_encode(t('live.minute')) ?>;
    if (goalState.isOwnGoal) {
        summary += ' — ' + labels.ownGoal;
    } else {
        summary += ' — ' + labels.goal + ': ' + scorerName;
        if (goalState.assistId) {
            var ap = starters.find(function(p) { return p.playerId === goalState.assistId; });
            if (ap) { summary += ' (' + ap.name + ')'; }
        }
        if (goalState.via !== 'open_play') {
            summary += ' · ' + (goalState.via === 'free_kick' ? labels.freeKick : labels.penalty);
        }
    }
    if (goalState.zone) { summary += ' · ' + labels.zoneLabels[goalState.zone]; }

    var html = '<div style="padding:1rem;">'
             + '<p style="margin-bottom:1rem;font-weight:500;">' + summary + '</p>'
             + '<button type="button" class="btn btn--primary btn--full" onclick="submitGoal()">' + labels.confirmBtn + '</button>'
             + '</div>';
    document.getElementById('event-sheet-content').innerHTML = html;
}

function submitGoal() {
    document.getElementById('gf-action').value  = goalState.isOwnGoal ? 'register_own_goal' : 'register_goal';
    document.getElementById('gf-player').value  = goalState.scorerId  || '';
    document.getElementById('gf-assist').value  = goalState.assistId  || '';
    document.getElementById('gf-via').value     = goalState.via;
    document.getElementById('gf-penalty').value = goalState.via === 'penalty' ? goalState.penScored : '';
    document.getElementById('gf-zone').value    = goalState.zone;
    document.getElementById('goal-form').submit();
}

// ── Card flow ─────────────────────────────────────────────────────────────────
var cardState = {};

function startCardFlow() {
    document.getElementById('event-sheet-title').textContent = labels.card;
    var html = '<div class="player-modal-item" onclick="setCardType(\'yellow_card\')">' + labels.yellowCard + '</div>'
             + '<div class="player-modal-item" onclick="setCardType(\'red_card\')">'    + labels.redCard + '</div>';
    document.getElementById('event-sheet-content').innerHTML = html;
}

function setCardType(type) {
    cardState.type = type;
    var html = '';
    allPlayers.forEach(function(p) {
        if (p.isGuest) { return; }
        html += '<div class="player-modal-item" onclick="submitCard(' + p.playerId + ')">'
              + (p.number ? '#' + p.number + ' ' : '') + p.name + '</div>';
    });
    document.getElementById('event-sheet-content').innerHTML = html;
}

function submitCard(pid) {
    if (confirm(labels.confirmBtn + '?')) {
        document.getElementById('cf-type').value   = cardState.type;
        document.getElementById('cf-player').value = pid;
        document.getElementById('card-form').submit();
    }
}

// ── Note flow ─────────────────────────────────────────────────────────────────
function startNoteFlow() {
    document.getElementById('event-sheet-title').textContent = labels.note;
    var html = '<div style="padding:1rem;">'
             + '<textarea id="note-input" class="form-input" rows="3" style="width:100%;margin-bottom:0.75rem;" placeholder="..."></textarea>'
             + '<button type="button" class="btn btn--primary btn--full" onclick="submitNote()">' + labels.confirmBtn + '</button>'
             + '</div>';
    document.getElementById('event-sheet-content').innerHTML = html;
    setTimeout(function() { var el = document.getElementById('note-input'); if (el) { el.focus(); } }, 100);
}

function submitNote() {
    var text = (document.getElementById('note-input') || {}).value || '';
    if (!text.trim()) { return; }
    document.getElementById('nf-text').value = text.trim();
    document.getElementById('note-form').submit();
}

// ── Close match modal ─────────────────────────────────────────────────────────
function openCloseMatchModal(scored, conceded, halfNum) {
    var msg = <?= json_encode(t('live.half.confirm_stop')) ?>;
    if (!confirm(msg)) { return; }

    document.getElementById('event-sheet-title').textContent = <?= json_encode(t('live.close.title')) ?>;
    var html = '<div style="padding:1rem;">'
             + '<p style="margin-bottom:0.75rem;">' + <?= json_encode(t('live.close.score')) ?> + '</p>'
             + '<div style="display:flex;gap:1rem;margin-bottom:1rem;">'
             + '<div style="flex:1;">'
             + '<label class="form-label"><?= e(t('live.close.goals_scored')) ?></label>'
             + '<input type="number" id="close-scored"   class="form-input" value="' + scored   + '" min="0" max="99">'
             + '</div>'
             + '<div style="flex:1;">'
             + '<label class="form-label"><?= e(t('live.close.goals_conceded')) ?></label>'
             + '<input type="number" id="close-conceded" class="form-input" value="' + conceded + '" min="0" max="99">'
             + '</div></div>'
             + '<button type="button" class="btn btn--primary btn--full" onclick="submitCloseMatch(' + halfNum + ')">'
             + <?= json_encode(t('live.close.confirm')) ?>
             + '</button>'
             + '<button type="button" class="btn btn--secondary btn--full" style="margin-top:0.5rem;" onclick="closeSheet(null,\'event-sheet\')">'
             + <?= json_encode(t('live.close.cancel')) ?>
             + '</button>'
             + '</div>';
    document.getElementById('event-sheet-content').innerHTML = html;
    showSheet('event-sheet');
}

function submitCloseMatch(halfNum) {
    var s = parseInt(document.getElementById('close-scored').value   || 0, 10);
    var c = parseInt(document.getElementById('close-conceded').value || 0, 10);
    var msg = <?= json_encode(t('live.close.confirm_dialog')) ?>;
    if (!confirm(msg)) { return; }

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = <?= json_encode($backUrl) ?>;
    [['_action','close_match'],['goals_scored',s],['goals_conceded',c],['half',halfNum]].forEach(function(pair) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = pair[0]; inp.value = pair[1];
        form.appendChild(inp);
    });
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php
$content = ob_get_clean();
$title   = $match['opponent'] . ' · ' . $dateLabel;
require dirname(__DIR__, 2) . '/templates/layout.php';
