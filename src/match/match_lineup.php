<?php
declare(strict_types=1);

$activePage      = 'match';
$matchRepo       = new MatchRepository();
$matchService    = new MatchService();
$formationRepo   = new FormationRepository();
$playerRepo      = new PlayerRepository();
$trainingRepo    = new TrainingRepository();
$seasonRepo      = new SeasonRepository();

$id    = (int) ($_GET['id'] ?? 0);
$match = $matchRepo->getMatchById($id);

if ($match === null) {
    $_SESSION['flash'] = t('match.not_found');
    redirect(APP_URL . '/index.php?page=match');
}

$teamId = (int) $match['team_id'];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['_action'] ?? '';

    if ($postAction === 'save_lineup') {
        // Receive array of positions: player_id, match_player_id (existing), position_label, pos_x, pos_y, in_starting_eleven
        // We build the full lineup from POST and persist it
        $rawPositions = (array) ($_POST['positions'] ?? []);

        // Get current match players to know which guests exist
        $currentPlayers = $matchRepo->getMatchPlayers($id);
        $guestPlayers   = array_filter($currentPlayers, fn($p) => (bool) $p['is_guest']);

        // Clear non-guest match players, then re-add
        $pdo = Database::getInstance()->getConnection();
        $pdo->prepare('DELETE FROM match_player WHERE match_id = ? AND is_guest = 0')->execute([$id]);

        foreach ($rawPositions as $pos) {
            $playerId = isset($pos['player_id']) && $pos['player_id'] !== '' ? (int) $pos['player_id'] : null;
            if ($playerId === null) {
                continue;
            }
            $matchRepo->saveMatchPlayer($id, [
                'player_id'          => $playerId,
                'is_guest'           => 0,
                'in_starting_eleven' => (int) ($pos['in_starting_eleven'] ?? 0),
                'position_label'     => $pos['position_label'] ?? null,
                'pos_x'              => isset($pos['pos_x']) && $pos['pos_x'] !== '' ? (float) $pos['pos_x'] : null,
                'pos_y'              => isset($pos['pos_y']) && $pos['pos_y'] !== '' ? (float) $pos['pos_y'] : null,
            ]);
        }

        redirect(APP_URL . '/index.php?page=match&action=lineup&id=' . $id);
    }

    if ($postAction === 'confirm') {
        $success = $matchService->confirmPreparation($id);
        if ($success) {
            $_SESSION['flash'] = t('match.status.prepared');
            redirect(APP_URL . '/index.php?page=match');
        }
        $_SESSION['flash'] = t('match.lineup.incomplete');
        redirect(APP_URL . '/index.php?page=match&action=lineup&id=' . $id);
    }

    if ($postAction === 'load_template') {
        $templateId = (int) ($_POST['template_match_id'] ?? 0);
        if ($templateId > 0) {
            $matchService->loadLineupFromTemplate($id, $templateId);
        } else {
            // Start fresh — clear non-guest players and add all present
            $pdo = Database::getInstance()->getConnection();
            $pdo->prepare('DELETE FROM match_player WHERE match_id = ? AND is_guest = 0')->execute([$id]);
            $matchService->ensureAllPresentPlayersInRoster($id, $teamId);
        }
        redirect(APP_URL . '/index.php?page=match&action=lineup&id=' . $id);
    }

    if ($postAction === 'change_formation') {
        $formationId = (int) ($_POST['formation_id'] ?? 0);
        if ($formationId > 0) {
            $matchRepo->setFormation($id, $formationId);
        }
        redirect(APP_URL . '/index.php?page=match&action=lineup&id=' . $id);
    }
}

// Ensure all present players are in the roster on first visit
$matchService->ensureAllPresentPlayersInRoster($id, $teamId);

// Reload match (formation_id may have been set)
$match = $matchRepo->getMatchById($id);

// Formation
$allFormations = $formationRepo->getAllFormations();
$formationId   = $match['formation_id'] ? (int) $match['formation_id'] : null;
if ($formationId === null) {
    $defaultFormation = $formationRepo->getDefaultFormation();
    if ($defaultFormation !== null) {
        $formationId = (int) $defaultFormation['id'];
        $matchRepo->setFormation($id, $formationId);
    }
}
$formation  = $formationId ? $formationRepo->getFormationById($formationId) : null;
$positions  = $formationId ? $formationRepo->getPositionsByFormation($formationId) : [];

// Current match players
$matchPlayers = $matchRepo->getMatchPlayers($id);
$starters     = array_filter($matchPlayers, fn($p) => (bool) $p['in_starting_eleven'] && !(bool) $p['is_guest']);
$benchPlayers = array_filter($matchPlayers, fn($p) => !(bool) $p['in_starting_eleven'] && !(bool) $p['is_guest']);
$guestPlayers = array_filter($matchPlayers, fn($p) => (bool) $p['is_guest']);

// Index starters by position_label for easy lookup
$startersByLabel = [];
foreach ($starters as $s) {
    if ($s['position_label'] !== null) {
        $startersByLabel[$s['position_label']] = $s;
    }
}

// All active players (for the selector)
$allPlayers = $playerRepo->getPlayersByTeam($teamId);

// Recent training attendance for each player (last 5 non-cancelled sessions)
$attendanceDots = [];
foreach ($allPlayers as $p) {
    $attendanceDots[(int) $p['id']] = $trainingRepo->getRecentAttendance((int) $p['id'], $teamId, 5);
}

// Attendance map for injury notes
$pdo = Database::getInstance()->getConnection();
$attStmt = $pdo->prepare(
    "SELECT * FROM attendance
     WHERE context_type = 'match' AND context_id = ? AND status = 'injured'"
);
$attStmt->execute([$id]);
$injuredMap = [];
foreach ($attStmt->fetchAll() as $att) {
    $injuredMap[(int) $att['player_id']] = $att['injury_note'];
}

// Template options
$templates = $matchRepo->getRecentMatchesForTemplate($teamId, 5);
// Exclude current match
$templates = array_filter($templates, fn($t) => (int) $t['id'] !== $id);

// Placed player IDs (starters)
$placedPlayerIds = array_map(fn($s) => (int) $s['player_id'], $starters);

// Players available for bench / selector (present, not a guest)
$rosterPlayerIds = array_map(fn($mp) => (int) $mp['player_id'], array_filter($matchPlayers, fn($p) => !(bool) $p['is_guest']));

$starterCount = count($starters);

$dateLabel     = date('j M', strtotime($match['date']));
$livestreamUrl = $matchService->getLivestreamUrl($id);
$flash         = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/index.php?page=match&action=prepare&id=<?= $id ?>"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title" style="font-size:1rem;"><?= e($match['opponent']) ?> · <?= e($dateLabel) ?></h1>
    <span></span>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-message"><?= e($flash) ?></div>
<?php endif; ?>

<!-- Progress indicator -->
<div class="progress-bar">
    <div class="progress-bar-step progress-bar-step--done"></div>
    <div class="progress-bar-step progress-bar-step--active"></div>
    <span class="text-sm text-muted" style="white-space:nowrap;">
        <?= e(t('match.prepare.step', ['current' => '2', 'total' => '2'])) ?>
    </span>
</div>

<!-- Template + Formation selectors -->
<div class="card" style="margin-bottom:0.75rem;">
    <div class="form-group" style="margin-bottom:0.75rem;">
        <label class="form-label"><?= e(t('match.lineup.template')) ?></label>
        <form method="POST"
              action="<?= e(APP_URL) ?>/index.php?page=match&action=lineup&id=<?= $id ?>">
            <?= csrfField() ?>
            <input type="hidden" name="_action" value="load_template">
            <div style="display:flex; gap:0.5rem;">
                <select name="template_match_id" class="form-select" style="flex:1;">
                    <option value="0"><?= e(t('match.lineup.template_default')) ?></option>
                    <?php foreach ($templates as $tpl): ?>
                        <option value="<?= (int) $tpl['id'] ?>">
                            <?= e(date('j M', strtotime($tpl['date']))) ?> — <?= e($tpl['opponent']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn--secondary btn--sm">
                    <?= e(t('action.confirm')) ?>
                </button>
            </div>
        </form>
    </div>

    <?php if (!empty($allFormations)): ?>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label"><?= e(t('match.lineup.formation')) ?></label>
            <form method="POST"
                  action="<?= e(APP_URL) ?>/index.php?page=match&action=lineup&id=<?= $id ?>">
                <?= csrfField() ?>
                <input type="hidden" name="_action" value="change_formation">
                <div style="display:flex; gap:0.5rem;">
                    <select name="formation_id" class="form-select" style="flex:1;">
                        <?php foreach ($allFormations as $f): ?>
                            <option value="<?= (int) $f['id'] ?>"
                                    <?= (int) $f['id'] === $formationId ? 'selected' : '' ?>>
                                <?= e($f['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn--secondary btn--sm">
                        <?= e(t('action.confirm')) ?>
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Pitch view -->
<?php if (!empty($positions)): ?>
    <div class="pitch-wrap" style="margin-bottom:0.75rem;">
        <div class="pitch-inner">
            <?php foreach ($positions as $pos): ?>
                <?php
                $label    = $pos['position_label'];
                $assigned = $startersByLabel[$label] ?? null;
                $x        = (float) $pos['pos_x'];
                $y        = (float) $pos['pos_y'];
                $name     = $assigned ? ($assigned['first_name'] ?? $assigned['guest_name'] ?? '?') : '+';
                $initials = $assigned ? mb_strtoupper(mb_substr($name, 0, 2)) : '+';
                ?>
                <div class="pitch-position"
                     style="left:<?= $x ?>%; top:<?= $y ?>%;"
                     onclick="openPositionModal(<?= htmlspecialchars(json_encode([
                         'label'     => $label,
                         'playerId'  => $assigned ? (int) $assigned['player_id'] : null,
                         'mpId'      => $assigned ? (int) $assigned['id'] : null,
                         'posX'      => $x,
                         'posY'      => $y,
                     ]), ENT_QUOTES) ?>)">
                    <div class="pitch-circle <?= $assigned ? 'pitch-circle--filled' : 'pitch-circle--empty' ?>">
                        <?= $assigned ? e(mb_strtoupper(mb_substr($name, 0, 2))) : '+' ?>
                    </div>
                    <div class="pitch-name"><?= $assigned ? e($name) : e($label) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php elseif ($formation === null): ?>
    <div class="card">
        <p class="text-muted text-sm"><?= e(t('match.lineup.formation')) ?>: —</p>
    </div>
<?php endif; ?>

<!-- Bench -->
<div class="card" style="margin-bottom:0.75rem;">
    <strong style="display:block; margin-bottom:0.5rem;">
        <?= e(t('match.lineup.bench')) ?>
        <span class="text-muted text-sm">(<?= count($benchPlayers) + count($guestPlayers) ?>)</span>
    </strong>

    <?php foreach ($benchPlayers as $bp): ?>
        <?php
        $pid  = (int) $bp['player_id'];
        $dots = $attendanceDots[$pid] ?? [];
        $injNote = $injuredMap[$pid] ?? null;
        ?>
        <div class="bench-player"
             onclick="openBenchModal(<?= $pid ?>, <?= htmlspecialchars(json_encode($bp['first_name']), ENT_QUOTES) ?>)">
            <div class="player-circle player-circle--sm">
                <?= e(mb_strtoupper(mb_substr($bp['first_name'], 0, 2))) ?>
            </div>
            <div class="bench-player-info">
                <div class="bench-player-name">
                    <?= e($bp['first_name']) ?>
                    <?php if ($bp['squad_number'] !== null): ?>
                        <span class="text-muted">#<?= (int) $bp['squad_number'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="bench-player-meta" style="display:flex; gap:0.4rem; align-items:center;">
                    <div class="att-dots">
                        <?php
                        $displayDots = array_reverse($dots);
                        for ($di = 0; $di < 5; $di++):
                            $dotStatus = $displayDots[$di] ?? null;
                            $dotClass  = $dotStatus ? 'att-dot--' . $dotStatus : 'att-dot--none';
                        ?>
                            <div class="att-dot <?= e($dotClass) ?>"></div>
                        <?php endfor; ?>
                    </div>
                    <?php if ($injNote): ?>
                        <span style="color:var(--color-accent);">⚠ <?= e($injNote) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($guestPlayers as $gp): ?>
        <div class="bench-player">
            <div class="player-circle player-circle--sm" style="background:var(--color-neutral);">
                <?= e(mb_strtoupper(mb_substr($gp['guest_name'] ?? 'G', 0, 2))) ?>
            </div>
            <div class="bench-player-info">
                <div class="bench-player-name">
                    <?= e($gp['guest_name'] ?? '') ?>
                    <span class="badge badge--accent" style="font-size:0.65rem;"><?= e(t('match.guest')) ?></span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($benchPlayers) && empty($guestPlayers)): ?>
        <p class="text-muted text-sm"><?= e(t('player.no_players')) ?></p>
    <?php endif; ?>
</div>

<!-- Confirm button -->
<div style="margin-bottom:1.5rem;">
    <?php $ready = $starterCount >= 11; ?>
    <?php if (!$ready): ?>
        <p class="text-sm text-muted text-center mb-1">
            <?= e(t('match.lineup.incomplete')) ?> (<?= $starterCount ?>/11)
        </p>
    <?php endif; ?>
    <form method="POST" action="<?= e(APP_URL) ?>/index.php?page=match&action=lineup&id=<?= $id ?>"
          id="confirm-form"
          onsubmit="return confirm(<?= e(json_encode(t('match.lineup.confirm_dialog'))) ?>)">
        <?= csrfField() ?>
        <input type="hidden" name="_action" value="confirm">
        <button type="submit" class="btn btn--primary btn--full" <?= $ready ? '' : 'disabled' ?>>
            <?= e(t('match.lineup.confirm')) ?>
        </button>
    </form>
</div>

<!-- Livestream link -->
<?php if (!empty($livestreamUrl)): ?>
<div class="card" style="margin-bottom:0.75rem;">
    <div class="text-sm text-muted mb-1" style="font-weight:600;"><?= e(t('livestream.link')) ?></div>
    <div class="text-sm mb-2" style="word-break:break-all; color:var(--color-primary);"><?= e($livestreamUrl) ?></div>
    <p class="text-sm text-muted mb-2"><?= e(t('livestream.share_note')) ?></p>
    <button type="button" class="btn btn--secondary btn--sm" id="copy-link-btn"
            onclick="copyLivestreamLink(<?= e(json_encode($livestreamUrl)) ?>)">
        <?= e(t('livestream.copy')) ?>
    </button>
</div>
<?php endif; ?>

<!-- Player position modal (bottom sheet) -->
<div id="pos-modal" class="player-modal-overlay" style="display:none;" onclick="closeModal(event)">
    <div class="player-modal">
        <div class="player-modal-title" id="pos-modal-title"><?= e(t('match.lineup.assign')) ?></div>
        <div id="pos-modal-content"></div>
    </div>
</div>

<!-- Hidden form for saving a single position assignment -->
<form id="assign-form" method="POST"
      action="<?= e(APP_URL) ?>/index.php?page=match&action=lineup&id=<?= $id ?>">
    <?= csrfField() ?>
    <input type="hidden" name="_action" value="save_lineup">
    <div id="assign-positions-container"></div>
</form>

<script>
var currentPositionData = null;

var rosterPlayers = <?= json_encode(array_values(array_map(function($mp) use ($attendanceDots, $injuredMap) {
    $pid = (int) $mp['player_id'];
    return [
        'id'           => $pid,
        'name'         => $mp['first_name'],
        'number'       => $mp['squad_number'],
        'inStarting'   => (bool) $mp['in_starting_eleven'],
        'positionLabel'=> $mp['position_label'],
        'posX'         => $mp['pos_x'],
        'posY'         => $mp['pos_y'],
        'attDots'      => $attendanceDots[$pid] ?? [],
        'injNote'      => $injuredMap[$pid] ?? null,
    ];
}, array_values(array_filter($matchPlayers, fn($p) => !(bool) $p['is_guest']))))) ?>;

var formationPositions = <?= json_encode(array_values(array_map(fn($p) => [
    'label' => $p['position_label'],
    'posX'  => (float) $p['pos_x'],
    'posY'  => (float) $p['pos_y'],
], $positions))) ?>;

function getPlayerById(id) {
    return rosterPlayers.find(function(p) { return p.id === id; });
}

function getAssignedToPosition(label) {
    return rosterPlayers.find(function(p) { return p.inStarting && p.positionLabel === label; });
}

function openPositionModal(data) {
    currentPositionData = data;
    var modal = document.getElementById('pos-modal');
    var title = document.getElementById('pos-modal-title');
    var content = document.getElementById('pos-modal-content');

    title.textContent = data.label;
    var html = '';

    // If position is filled — show clear option
    if (data.playerId) {
        html += '<div class="player-modal-item" onclick="clearPosition(' + JSON.stringify(data.label) + ')" style="color:var(--color-danger);">';
        html += '<?= e(t('match.lineup.clear')) ?>';
        html += '</div>';
    }

    // Show available bench players (not assigned to a starting position)
    var available = rosterPlayers.filter(function(p) { return !p.inStarting && p.id !== data.playerId; });
    available.forEach(function(p) {
        var dots = p.attDots.slice().reverse();
        var dotsHtml = '';
        for (var i = 0; i < 5; i++) {
            var s = dots[i] || null;
            var cls = s ? 'att-dot--' + s : 'att-dot--none';
            dotsHtml += '<div class="att-dot ' + cls + '"></div>';
        }
        html += '<div class="player-modal-item" onclick="assignPlayer(' + p.id + ', ' + JSON.stringify(data.label) + ')">';
        html += '<div class="player-circle player-circle--sm">' + p.name.substr(0,2).toUpperCase() + '</div>';
        html += '<div class="bench-player-info">';
        html += '<div class="bench-player-name">' + escHtml(p.name);
        if (p.number) html += ' <span class="text-muted">#' + p.number + '</span>';
        html += '</div>';
        html += '<div class="bench-player-meta" style="display:flex;gap:0.4rem;align-items:center;"><div class="att-dots">' + dotsHtml + '</div>';
        if (p.injNote) html += '<span style="color:var(--color-accent);">⚠ ' + escHtml(p.injNote) + '</span>';
        html += '</div></div></div>';
    });

    if (!available.length && !data.playerId) {
        html += '<p class="text-muted text-sm" style="padding:0.5rem 0;"><?= e(t('player.no_players')) ?></p>';
    }

    content.innerHTML = html;
    modal.style.display = 'flex';
}

function openBenchModal(playerId, name) {
    var modal = document.getElementById('pos-modal');
    var title = document.getElementById('pos-modal-title');
    var content = document.getElementById('pos-modal-content');

    title.textContent = name;
    var html = '';

    // Show unoccupied formation positions
    formationPositions.forEach(function(fp) {
        if (!getAssignedToPosition(fp.label)) {
            html += '<div class="player-modal-item" onclick="assignPlayer(' + playerId + ', ' + JSON.stringify(fp.label) + ')">';
            html += '<span>' + escHtml(fp.label) + '</span>';
            html += '</div>';
        }
    });

    if (!html) {
        html = '<p class="text-muted text-sm" style="padding:0.5rem 0;"><?= e(t('match.lineup.incomplete')) ?></p>';
    }

    content.innerHTML = html;
    modal.style.display = 'flex';
}

function assignPlayer(playerId, positionLabel) {
    // Find the position's x/y
    var fp = formationPositions.find(function(p) { return p.label === positionLabel; });

    // If that position was occupied, move incumbent to bench
    var incumbent = getAssignedToPosition(positionLabel);
    if (incumbent) {
        incumbent.inStarting = false;
        incumbent.positionLabel = null;
        incumbent.posX = null;
        incumbent.posY = null;
    }

    // If this player was already in another position, vacate it
    var player = getPlayerById(playerId);
    if (player) {
        player.inStarting = true;
        player.positionLabel = positionLabel;
        player.posX = fp ? fp.posX : null;
        player.posY = fp ? fp.posY : null;
    }

    closeModal();
    submitLineup();
}

function clearPosition(positionLabel) {
    var player = getAssignedToPosition(positionLabel);
    if (player) {
        player.inStarting = false;
        player.positionLabel = null;
        player.posX = null;
        player.posY = null;
    }
    closeModal();
    submitLineup();
}

function submitLineup() {
    var container = document.getElementById('assign-positions-container');
    container.innerHTML = '';
    rosterPlayers.forEach(function(p, idx) {
        var f = function(n, v) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'positions[' + idx + '][' + n + ']';
            input.value = v !== null && v !== undefined ? v : '';
            container.appendChild(input);
        };
        f('player_id', p.id);
        f('in_starting_eleven', p.inStarting ? 1 : 0);
        f('position_label', p.positionLabel);
        f('pos_x', p.posX);
        f('pos_y', p.posY);
    });
    document.getElementById('assign-form').submit();
}

function closeModal(event) {
    if (!event || event.target === document.getElementById('pos-modal')) {
        document.getElementById('pos-modal').style.display = 'none';
    }
}

function escHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str || ''));
    return d.innerHTML;
}

function copyLivestreamLink(url) {
    var btn = document.getElementById('copy-link-btn');
    var original = btn.textContent;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
            btn.textContent = <?= json_encode(t('livestream.copied')) ?>;
            setTimeout(function() { btn.textContent = original; }, 2000);
        });
    } else {
        var ta = document.createElement('textarea');
        ta.value = url;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);
        btn.textContent = <?= json_encode(t('livestream.copied')) ?>;
        setTimeout(function() { btn.textContent = original; }, 2000);
    }
}
</script>
<?php

$content = ob_get_clean();
$title   = t('match.lineup.title');
require dirname(__DIR__, 2) . '/templates/layout.php';
