<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/src/core/helpers.php';
require_once dirname(__DIR__) . '/src/core/Database.php';
require_once dirname(__DIR__) . '/src/match/MatchRepository.php';
require_once dirname(__DIR__) . '/src/match/FormationRepository.php';
require_once dirname(__DIR__) . '/src/match/MatchService.php';

date_default_timezone_set(APP_TIMEZONE);

$token = preg_replace('/[^a-f0-9]/', '', strtolower($_GET['token'] ?? ''));

function renderNotFound(): void
{
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e(t('app.name')) ?></title>
        <link rel="stylesheet" href="<?= e(APP_URL) ?>/public/css/style.css">
    </head>
    <body>
        <main class="page-content" style="padding-top:3rem; text-align:center;">
            <p class="text-muted"><?= e(t('livestream.not_found')) ?></p>
        </main>
    </body>
    </html>
    <?php
    exit;
}

if (empty($token)) {
    renderNotFound();
}

$pdo   = Database::getInstance()->getConnection();
$stmt  = $pdo->prepare('SELECT * FROM `match` WHERE livestream_token = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$token]);
$match = $stmt->fetch() ?: null;

if ($match === null) {
    renderNotFound();
}

$matchId   = (int) $match['id'];
$matchRepo = new MatchRepository();
$matchSvc  = new MatchService();

// Players with photo info
$playerStmt = $pdo->prepare(
    'SELECT mp.*,
            p.first_name, p.squad_number, p.photo_path, p.photo_consent
     FROM match_player mp
     LEFT JOIN player p ON p.id = mp.player_id
     WHERE mp.match_id = ?
     ORDER BY mp.in_starting_eleven DESC, p.squad_number IS NULL, p.squad_number ASC, p.first_name ASC'
);
$playerStmt->execute([$matchId]);
$players = $playerStmt->fetchAll();

$events = $matchRepo->getMatchEvents($matchId);
$subs   = $matchRepo->getSubstitutions($matchId);
$halves = $matchRepo->getMatchHalves($matchId);

// Half status
$half1 = null;
$half2 = null;
foreach ($halves as $h) {
    if ((int) $h['number'] === 1) { $half1 = $h; }
    if ((int) $h['number'] === 2) { $half2 = $h; }
}

$isFinished = $match['status'] === 'finished';

if ($isFinished) {
    $halfStatus = t('livestream.full_time');
} elseif ($half1 === null) {
    $halfStatus = t('livestream.not_started');
} elseif ($half1['started_at'] !== null && $half1['stopped_at'] === null) {
    $halfStatus = t('live.half.first');
} elseif ($half1['stopped_at'] !== null && ($half2 === null || $half2['started_at'] === null)) {
    $halfStatus = t('livestream.half_time');
} elseif ($half2 !== null && $half2['started_at'] !== null && $half2['stopped_at'] === null) {
    $halfStatus = t('live.half.second');
} elseif ($half2 !== null && $half2['stopped_at'] !== null) {
    $halfStatus = t('livestream.full_time');
} else {
    $halfStatus = t('livestream.not_started');
}

// Score
if ($isFinished) {
    $scored   = (int) ($match['goals_scored']   ?? 0);
    $conceded = (int) ($match['goals_conceded'] ?? 0);
} else {
    $calc     = $matchSvc->getScoreFromEvents($matchId);
    $scored   = $calc['scored'];
    $conceded = $calc['conceded'];
}

// Team name
$teamStmt = $pdo->prepare('SELECT name FROM team WHERE id = ? LIMIT 1');
$teamStmt->execute([(int) $match['team_id']]);
$teamRow  = $teamStmt->fetch();
$teamName = $teamRow ? $teamRow['name'] : t('nav.squad');

$isHome = $match['home_away'] === 'home';
if ($isHome) {
    $leftLabel  = $teamName;
    $rightLabel = $match['opponent'];
    $leftScore  = $scored;
    $rightScore = $conceded;
} else {
    $leftLabel  = $match['opponent'];
    $rightLabel = $teamName;
    $leftScore  = $conceded;
    $rightScore = $scored;
}

// Starters and bench
$starters = array_values(array_filter($players, fn($p) => (bool) $p['in_starting_eleven']));
$bench    = array_values(array_filter($players, fn($p) => !(bool) $p['in_starting_eleven']));

// Merged timeline (most recent first)
$timeline = [];
foreach ($events as $e) {
    $timeline[] = ['type' => 'event', 'half' => (int) $e['half'], 'minute' => (int) $e['minute'], 'data' => $e];
}
foreach ($subs as $s) {
    $timeline[] = ['type' => 'sub', 'half' => (int) $s['half'], 'minute' => (int) $s['minute'], 'data' => $s];
}
usort($timeline, fn($a, $b) => $b['half'] !== $a['half'] ? $b['half'] <=> $a['half'] : $b['minute'] <=> $a['minute']);

function liveInitials(string $name): string
{
    return mb_strtoupper(mb_substr(trim($name), 0, 1));
}

function playerDisplayName(array $p): string
{
    return (bool) $p['is_guest'] ? ($p['guest_name'] ?? '?') : ($p['first_name'] ?? '?');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="60">
    <meta name="theme-color" content="#1259A8">
    <title><?= e($teamName . ' vs ' . $match['opponent']) ?> — <?= e(t('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/public/css/style.css">
    <style>
        body { padding-bottom: 2rem; }
        .ls-header {
            background: var(--color-primary);
            color: #fff;
            padding: 1rem;
            text-align: center;
        }
        .ls-match-label {
            font-size: var(--font-size-sm);
            opacity: 0.85;
            margin-bottom: 0.25rem;
        }
        .ls-score {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.02em;
        }
        .ls-score-teams {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: var(--font-size-sm);
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .ls-half-status {
            font-size: var(--font-size-sm);
            opacity: 0.85;
            margin-top: 0.25rem;
        }
        .ls-refresh-note {
            font-size: 0.75rem;
            opacity: 0.65;
            margin-top: 0.5rem;
        }
        .ls-section {
            max-width: 480px;
            margin: 0 auto;
            padding: 1rem;
        }
        .ls-section-title {
            font-size: var(--font-size-sm);
            font-weight: 700;
            color: var(--color-neutral);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
        }
        .ls-event-row {
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
            padding: 0.4rem 0;
            border-bottom: 1px solid var(--color-border);
            font-size: var(--font-size-sm);
        }
        .ls-event-row:last-child { border-bottom: none; }
        .ls-event-min {
            min-width: 2.5rem;
            font-weight: 700;
            color: var(--color-neutral);
        }
        .ls-squad-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }
        .ls-squad-name {
            background: var(--color-card-bg);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            padding: 0.25rem 0.5rem;
            font-size: var(--font-size-sm);
        }
        .ls-footer {
            text-align: center;
            padding: 1.5rem 1rem;
            font-size: 0.75rem;
            color: var(--color-neutral);
        }
        .ls-footer a { color: var(--color-neutral); }
    </style>
</head>
<body>

<!-- Header -->
<div class="ls-header">
    <div class="ls-match-label"><?= e(t('app.name')) ?></div>
    <div class="ls-score-teams">
        <span><?= e($leftLabel) ?></span>
        <span><?= e($rightLabel) ?></span>
    </div>
    <div class="ls-score"><?= $leftScore ?> – <?= $rightScore ?></div>
    <div class="ls-half-status"><?= e($halfStatus) ?></div>
    <div class="ls-refresh-note"><?= e(t('livestream.refreshing')) ?></div>
</div>

<!-- Section 1: Lineup on pitch -->
<div class="ls-section">
    <div class="ls-section-title"><?= e(t('livestream.lineup')) ?></div>
    <?php if (!empty($starters)): ?>
    <div class="pitch-wrap">
        <div class="pitch-inner">
            <?php foreach ($starters as $p):
                $posX     = $p['pos_x'] !== null ? (float) $p['pos_x'] : null;
                $posY     = $p['pos_y'] !== null ? (float) $p['pos_y'] : null;
                $name     = playerDisplayName($p);
                $hasPhoto = !empty($p['photo_path']) && !empty($p['photo_consent']);
                if ($posX === null || $posY === null) { continue; }
            ?>
                <div class="pitch-position"
                     style="left:<?= round($posX, 2) ?>%; top:<?= round($posY, 2) ?>%;">
                    <div class="pitch-circle pitch-circle--filled"
                         style="overflow:hidden;">
                        <?php if ($hasPhoto): ?>
                            <img src="<?= e(APP_URL . '/public/' . $p['photo_path']) ?>"
                                 alt="<?= e($name) ?>"
                                 style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <?= e(liveInitials($name)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="pitch-name"><?= e($name) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Section 2: Event timeline -->
<?php if (!empty($timeline)): ?>
<div class="ls-section" style="padding-top:0;">
    <div class="ls-section-title"><?= e(t('livestream.timeline')) ?></div>
    <div class="card" style="padding:0.5rem 0.75rem;">
        <?php foreach ($timeline as $entry):
            if ($entry['type'] === 'sub'):
                $sub = $entry['data'];
            ?>
            <div class="ls-event-row">
                <span class="ls-event-min"><?= (int) $sub['minute'] ?>'</span>
                <span>↕</span>
                <span><?= e($sub['player_on_name']) ?> · ↓ <?= e($sub['player_off_name']) ?></span>
            </div>
            <?php else:
                $ev     = $entry['data'];
                $evMin  = (int) $ev['minute'];
                $evIcon = '';
                $evText = '';
                switch ($ev['event_type']) {
                    case 'goal':
                        $evIcon = '⚽';
                        if ($ev['scored_via'] === 'penalty' && $ev['penalty_scored'] == 0) {
                            $evText = ($ev['player_name'] ?? '') . ' · ' . t('live.event.via.penalty') . ' ' . t('live.event.penalty_missed');
                        } else {
                            $evText = $ev['player_name'] ?? t('live.event.unknown');
                            if (!empty($ev['assist_name'])) {
                                $evText .= ' · ' . $ev['assist_name'];
                            }
                            if ($ev['scored_via'] !== 'open_play') {
                                $evText .= ' (' . t('live.event.via.' . $ev['scored_via']) . ')';
                            }
                        }
                        break;
                    case 'own_goal':
                        $evIcon = '⚽';
                        $evText = t('event.own_goal');
                        break;
                    case 'yellow_card':
                        $evIcon = '🟨';
                        $evText = $ev['player_name'] ?? '';
                        break;
                    case 'red_card':
                        $evIcon = '🟥';
                        $evText = $ev['player_name'] ?? '';
                        break;
                    case 'note':
                        $evIcon = '📋';
                        $evText = $ev['note_text'] ?? '';
                        break;
                }
            ?>
            <div class="ls-event-row">
                <span class="ls-event-min"><?= $evMin ?>'</span>
                <span><?= $evIcon ?></span>
                <span><?= e($evText) ?></span>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Section 3: Squad -->
<div class="ls-section" style="padding-top:0;">
    <div class="ls-section-title"><?= e(t('livestream.squad')) ?></div>
    <?php if (!empty($starters)): ?>
    <div class="card" style="margin-bottom:0.5rem;">
        <div class="text-sm text-muted mb-1" style="font-weight:600;"><?= e(t('livestream.starting')) ?></div>
        <div class="ls-squad-list">
            <?php foreach ($starters as $p): ?>
                <span class="ls-squad-name"><?= e(playerDisplayName($p)) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($bench)): ?>
    <div class="card">
        <div class="text-sm text-muted mb-1" style="font-weight:600;"><?= e(t('livestream.bench')) ?></div>
        <div class="ls-squad-list">
            <?php foreach ($bench as $p): ?>
                <span class="ls-squad-name"><?= e(playerDisplayName($p)) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Footer -->
<div class="ls-footer">
    <?= e(t('livestream.powered_by')) ?> ·
    <a href="https://github.com/visserdavid/coachboard" target="_blank" rel="noopener">GitHub</a>
</div>

</body>
</html>
