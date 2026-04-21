<?php
declare(strict_types=1);

$activePage = 'squad';

require_once dirname(__DIR__, 2) . '/src/player/PlayerRepository.php';
require_once dirname(__DIR__, 2) . '/src/season/SeasonRepository.php';
require_once dirname(__DIR__, 2) . '/src/stats/StatsRepository.php';

$id         = (int) ($_GET['id'] ?? 0);
$playerRepo = new PlayerRepository();
$seasonRepo = new SeasonRepository();
$statsRepo  = new StatsRepository();

$player = $playerRepo->getPlayerById($id);
if ($player === null) {
    $_SESSION['flash'] = t('error.not_found');
    redirect(APP_URL . '/index.php?page=squad');
}

// Determine the season for this player via their team
$stmt = Database::getInstance()->getConnection()->prepare(
    'SELECT * FROM team WHERE id = ? LIMIT 1'
);
$stmt->execute([$player['team_id']]);
$team = $stmt->fetch() ?: null;

$teamId   = $team ? (int) $team['id'] : 0;
$seasonId = $team ? (int) $team['season_id'] : 0;

// Phase filter
$phases        = $seasonRepo->getPhasesBySeason($seasonId);
$activePhaseId = isset($_GET['phase_id']) ? (int) $_GET['phase_id'] : null;
$activePhase   = null;
if ($activePhaseId !== null) {
    foreach ($phases as $ph) {
        if ((int) $ph['id'] === $activePhaseId) {
            $activePhase = $ph;
            break;
        }
    }
    if ($activePhase === null) {
        $activePhaseId = null;
    }
}

$skills     = $playerRepo->getSkillsByPlayer($id, $seasonId);
$avgRatings = $playerRepo->getAverageRatingsByPlayer($id, $seasonId);
$stats      = $activePhaseId !== null
    ? $statsRepo->getPlayerStatsByPhase($id, $teamId, $activePhaseId)
    : $statsRepo->getPlayerStats($id, $teamId, $seasonId);

$user    = Auth::getCurrentUser();
$canEdit = $user && (!empty($user['is_administrator']) || !empty($user['is_assistant']));

$skillKeys  = ['pace', 'shooting', 'passing', 'dribbling', 'defending', 'physicality'];
$hasSkills  = $skills !== null && array_filter(
    array_intersect_key($skills, array_flip($skillKeys)),
    fn($v) => $v !== null
);

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/index.php?page=squad"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <span></span>
    <?php if ($canEdit): ?>
        <a href="<?= e(APP_URL) ?>/index.php?page=squad&action=edit&id=<?= $id ?>"
           class="btn btn--secondary btn--sm"><?= e(t('action.edit')) ?></a>
    <?php else: ?>
        <span></span>
    <?php endif; ?>
</div>

<!-- Header -->
<div class="card" style="text-align:center; padding:1.5rem 1rem;">
    <?php if (!empty($player['photo_path'])): ?>
        <img src="<?= e(APP_URL . '/' . $player['photo_path']) ?>"
             alt="<?= e($player['first_name']) ?>"
             style="width:96px;height:96px;border-radius:50%;object-fit:cover;margin:0 auto 0.75rem;">
    <?php else: ?>
        <div class="player-circle"
             style="width:96px;height:96px;font-size:2rem;background:var(--color-primary);margin:0 auto 0.75rem;">
            <?= e(mb_strtoupper(mb_substr($player['first_name'], 0, 1))) ?>
        </div>
    <?php endif; ?>
    <h2 style="font-size:1.5rem;font-weight:800;margin-bottom:0.25rem;">
        <?= e($player['first_name']) ?>
    </h2>
    <?php if ($player['squad_number'] !== null): ?>
        <span class="badge badge--neutral" style="font-size:1rem;">#<?= (int) $player['squad_number'] ?></span>
    <?php endif; ?>
</div>

<!-- Basic info -->
<?php if (!empty($player['preferred_foot']) || !empty($player['preferred_line'])): ?>
<div class="card">
    <?php if (!empty($player['preferred_foot'])): ?>
        <div class="flex-between mb-1">
            <span class="text-sm text-muted"><?= e(t('player.preferred_foot')) ?></span>
            <strong><?= e(t('player.preferred_foot.' . $player['preferred_foot'])) ?></strong>
        </div>
    <?php endif; ?>
    <?php if (!empty($player['preferred_line'])): ?>
        <div class="flex-between">
            <span class="text-sm text-muted"><?= e(t('player.preferred_line')) ?></span>
            <strong><?= e(t('player.line.' . $player['preferred_line'])) ?></strong>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Skill radar -->
<?php if ($hasSkills): ?>
<div class="card" style="text-align:center;">
    <h3 style="font-size:var(--font-size-sm);font-weight:600;color:var(--color-neutral);margin-bottom:0.5rem;">
        <?= e(t('skill.title')) ?>
    </h3>
    <?= renderRadarChart($skills) ?>
</div>
<?php endif; ?>

<!-- Season statistics -->
<div class="card">
    <div class="flex-between mb-1">
        <h3 style="font-size:var(--font-size-sm);font-weight:600;color:var(--color-neutral);margin:0;">
            <?= e(t('dashboard.season_stats')) ?>
        </h3>
    </div>

    <?php if (seasonHasPhases() && !empty($phases)): ?>
    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-bottom:0.75rem;">
        <a href="?page=squad&action=profile&id=<?= $id ?>"
           class="badge <?= $activePhaseId === null ? 'badge--primary' : 'badge--neutral' ?>"
           style="text-decoration:none;">
            <?= e(t('stats.filter.full_season')) ?>
        </a>
        <?php foreach ($phases as $ph): ?>
            <a href="?page=squad&action=profile&id=<?= $id ?>&phase_id=<?= (int) $ph['id'] ?>"
               class="badge <?= $activePhaseId === (int) $ph['id'] ? 'badge--primary' : 'badge--neutral' ?>"
               style="text-decoration:none;">
                <?= e($ph['label'] ?: t('stats.filter.phase', ['number' => $ph['number']])) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
        <div>
            <div style="font-size:1.25rem;font-weight:800;color:var(--color-primary);">
                <?= $stats['playing_time_minutes'] ?> <span style="font-size:0.75rem;font-weight:400;"><?= e(t('stats.minutes')) ?></span>
            </div>
            <div class="text-sm text-muted"><?= e(t('player.stats.playing_time')) ?></div>
        </div>
        <div>
            <div style="font-size:1.25rem;font-weight:800;color:var(--color-primary);">
                <?= $stats['matches_played'] ?>
            </div>
            <div class="text-sm text-muted"><?= e(t('player.stats.matches')) ?></div>
        </div>
        <div>
            <div style="font-size:1.25rem;font-weight:800;color:var(--color-primary);">
                <?= $stats['goals'] ?>
            </div>
            <div class="text-sm text-muted"><?= e(t('player.stats.goals')) ?></div>
        </div>
        <div>
            <div style="font-size:1.25rem;font-weight:800;color:var(--color-primary);">
                <?= $stats['assists'] ?>
            </div>
            <div class="text-sm text-muted"><?= e(t('player.stats.assists')) ?></div>
        </div>
    </div>
    <?php if ($stats['training_attendance_pct'] > 0): ?>
        <div style="margin-top:0.75rem; padding-top:0.75rem; border-top:1px solid var(--color-border);">
            <div class="flex-between">
                <span class="text-sm text-muted"><?= e(t('player.stats.attendance')) ?></span>
                <strong><?= $stats['training_attendance_pct'] ?>%</strong>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($stats['average_rating'] !== null): ?>
        <div style="margin-top:0.5rem; padding-top:0.5rem; border-top:1px solid var(--color-border);">
            <div class="flex-between">
                <span class="text-sm text-muted"><?= e(t('player.average_rating')) ?></span>
                <span style="color:var(--color-accent);">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <?= $s <= $stats['average_rating'] ? '★' : '☆' ?>
                    <?php endfor; ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Average match rating (per skill) -->
<?php if ($avgRatings !== null): ?>
<div class="card">
    <h3 style="font-size:var(--font-size-sm);font-weight:600;color:var(--color-neutral);margin-bottom:0.75rem;">
        <?= e(t('rating.season_average')) ?>
    </h3>
    <?php foreach ($skillKeys as $skill): ?>
        <?php $val = (int) ($avgRatings[$skill] ?? 0); ?>
        <div class="flex-between" style="margin-bottom:0.4rem;">
            <span class="text-sm"><?= e(t('skill.' . $skill)) ?></span>
            <span style="color:var(--color-accent);">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                    <?= $s <= $val ? '★' : '☆' ?>
                <?php endfor; ?>
            </span>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php

$content = ob_get_clean();
$title   = $player['first_name'];
require dirname(__DIR__, 2) . '/templates/layout.php';

function renderRadarChart(array $skills): string
{
    $cx       = 120;
    $cy       = 115;
    $maxR     = 75;
    $numAxes  = 6;
    $keys     = ['pace', 'shooting', 'passing', 'dribbling', 'defending', 'physicality'];
    $labelR   = $maxR + 20;
    $svg      = '';

    // Grid levels
    for ($level = 1; $level <= 5; $level++) {
        $r   = ($level / 5) * $maxR;
        $pts = [];
        for ($i = 0; $i < $numAxes; $i++) {
            $angle = deg2rad(-90 + $i * 60);
            $pts[] = round($cx + $r * cos($angle), 1) . ',' . round($cy + $r * sin($angle), 1);
        }
        $opacity = $level === 5 ? '0.2' : '0.1';
        $svg .= '<polygon points="' . implode(' ', $pts) . '" '
              . 'fill="none" stroke="var(--color-neutral)" stroke-width="1" opacity="' . $opacity . '"/>';
    }

    // Axis lines
    for ($i = 0; $i < $numAxes; $i++) {
        $angle = deg2rad(-90 + $i * 60);
        $x     = round($cx + $maxR * cos($angle), 1);
        $y     = round($cy + $maxR * sin($angle), 1);
        $svg  .= '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . $x . '" y2="' . $y . '" '
               . 'stroke="var(--color-border)" stroke-width="1"/>';
    }

    // Data polygon
    $dataPts = [];
    foreach ($keys as $i => $key) {
        $val   = min(5, max(0, (float) ($skills[$key] ?? 0)));
        $angle = deg2rad(-90 + $i * 60);
        $r     = ($val / 5) * $maxR;
        $dataPts[] = round($cx + $r * cos($angle), 1) . ',' . round($cy + $r * sin($angle), 1);
    }
    $svg .= '<polygon points="' . implode(' ', $dataPts) . '" '
          . 'fill="var(--color-accent)" fill-opacity="0.3" stroke="var(--color-accent)" stroke-width="2"/>';

    // Labels
    $labelNames = ['Pace', 'Shooting', 'Passing', 'Dribbling', 'Defending', 'Physicality'];
    foreach ($keys as $i => $key) {
        $angle  = deg2rad(-90 + $i * 60);
        $lx     = round($cx + $labelR * cos($angle), 1);
        $ly     = round($cy + $labelR * sin($angle), 1);
        $anchor = 'middle';
        if ($lx < $cx - 5) $anchor = 'end';
        if ($lx > $cx + 5) $anchor = 'start';
        $svg .= '<text x="' . $lx . '" y="' . ($ly + 4) . '" '
              . 'text-anchor="' . $anchor . '" '
              . 'font-size="10" fill="var(--color-neutral)">'
              . htmlspecialchars(t('skill.' . $key), ENT_QUOTES, 'UTF-8')
              . '</text>';
    }

    return '<svg viewBox="0 0 240 230" width="240" height="230" style="overflow:visible;display:block;margin:0 auto;">'
         . $svg
         . '</svg>';
}
