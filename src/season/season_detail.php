<?php
declare(strict_types=1);

$activePage = 'settings';

require_once dirname(__DIR__, 2) . '/src/season/SeasonRepository.php';
require_once dirname(__DIR__, 2) . '/src/season/SeasonService.php';

$repo    = new SeasonRepository();
$service = new SeasonService();

$id     = (int) ($_GET['id'] ?? 0);
$season = $repo->getSeasonById($id);

if ($season === null) {
    http_response_code(404);
    $_SESSION['flash'] = t('error.not_found');
    redirect(APP_URL . '/public/index.php?page=season&action=list');
}

$team   = $repo->getTeamBySeason($id);
$teamId = $team ? (int) $team['id'] : null;
$phases = $repo->getPhasesBySeason($id);

$errors  = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'update_phase' && $teamId !== null) {
        $phaseId = (int) ($_POST['phase_id'] ?? 0);
        $phase   = $repo->getPhaseById($phaseId);

        if ($phase !== null && (int) $phase['season_id'] === $id) {
            $repo->updatePhase($phaseId, [
                'label'      => trim($_POST['label'] ?? ''),
                'focus'      => trim($_POST['focus'] ?? ''),
                'start_date' => $phase['start_date'],
                'end_date'   => $phase['end_date'],
            ]);
            $_SESSION['flash'] = t('action.save') . ' ✓';
            redirect(APP_URL . '/public/index.php?page=season&action=detail&id=' . $id);
        }
    }

    if ($action === 'update_training_days' && $teamId !== null) {
        $days = array_map('intval', (array) ($_POST['training_days'] ?? []));
        $repo->setTrainingDays($teamId, $days);
        $_SESSION['flash'] = t('action.save') . ' ✓';
        redirect(APP_URL . '/public/index.php?page=season&action=detail&id=' . $id);
    }
}

$trainingDays = $teamId !== null ? $repo->getTrainingDaysByTeam($teamId) : [];
$range        = $repo->getSeasonDateRange($id);

$pdo = Database::getInstance()->getConnection();

$matchCount = 0;
$sessionCount = 0;
$playerCount  = 0;

if ($teamId !== null) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM `match` WHERE team_id = ? AND deleted_at IS NULL');
    $stmt->execute([$teamId]);
    $matchCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM `training_session` WHERE team_id = ?');
    $stmt->execute([$teamId]);
    $sessionCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM `player` WHERE team_id = ? AND deleted_at IS NULL');
    $stmt->execute([$teamId]);
    $playerCount = (int) $stmt->fetchColumn();
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/public/index.php?page=season&action=list"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title"><?= e($season['name']) ?></h1>
    <?php if ($season['active']): ?>
        <span class="badge badge--success"><?= e(t('season.active')) ?></span>
    <?php else: ?>
        <span></span>
    <?php endif; ?>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-message"><?= e($flash) ?></div>
<?php endif; ?>

<!-- Summary stats -->
<div class="card">
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:0.5rem; text-align:center;">
        <div>
            <div style="font-size:1.5rem; font-weight:800; color:var(--color-primary);"><?= $matchCount ?></div>
            <div class="text-sm text-muted"><?= e(t('season.matches')) ?></div>
        </div>
        <div>
            <div style="font-size:1.5rem; font-weight:800; color:var(--color-primary);"><?= $sessionCount ?></div>
            <div class="text-sm text-muted"><?= e(t('season.sessions')) ?></div>
        </div>
        <div>
            <div style="font-size:1.5rem; font-weight:800; color:var(--color-primary);"><?= $playerCount ?></div>
            <div class="text-sm text-muted"><?= e(t('season.players')) ?></div>
        </div>
    </div>
</div>

<!-- Date range (no phases) -->
<?php if (!$season['has_phases'] && $range['season_start']): ?>
    <div class="card">
        <div class="text-sm text-muted mb-1"><?= e(t('season.phase.start')) ?> – <?= e(t('season.phase.end')) ?></div>
        <strong><?= e(date('d M Y', strtotime($range['season_start']))) ?>
            – <?= e(date('d M Y', strtotime($range['season_end']))) ?></strong>
    </div>
<?php endif; ?>

<!-- Phases -->
<?php if ($season['has_phases'] && !empty($phases)): ?>
    <?php foreach ($phases as $phase): ?>
        <form method="POST" action="<?= e(APP_URL) ?>/public/index.php?page=season&action=detail&id=<?= $id ?>">
            <input type="hidden" name="_action" value="update_phase">
            <input type="hidden" name="phase_id" value="<?= (int) $phase['id'] ?>">
            <div class="card">
                <div class="flex-between mb-1">
                    <strong>
                        <?= e($phase['label'] ?: t('phase.label', ['number' => $phase['number']])) ?>
                    </strong>
                    <span class="text-sm text-muted">
                        <?= e(date('d M Y', strtotime($phase['start_date']))) ?>
                        – <?= e(date('d M Y', strtotime($phase['end_date']))) ?>
                    </span>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e(t('season.phase.label')) ?></label>
                    <input type="text" name="label" class="form-input"
                           value="<?= e($phase['label'] ?? '') ?>">
                </div>
                <div class="form-group" style="margin-bottom:0.75rem;">
                    <label class="form-label"><?= e(t('season.phase.focus')) ?></label>
                    <textarea name="focus" class="form-textarea"><?= e($phase['focus'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn--secondary btn--sm">
                    <?= e(t('action.save')) ?>
                </button>
            </div>
        </form>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Training days -->
<form method="POST" action="<?= e(APP_URL) ?>/public/index.php?page=season&action=detail&id=<?= $id ?>">
    <input type="hidden" name="_action" value="update_training_days">
    <div class="card">
        <label class="form-label"><?= e(t('season.training_days')) ?></label>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; margin-top:0.35rem; margin-bottom:0.75rem;">
            <?php for ($d = 1; $d <= 7; $d++): ?>
                <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;">
                    <input type="checkbox" name="training_days[]" value="<?= $d ?>"
                           <?= in_array($d, $trainingDays, true) ? 'checked' : '' ?>>
                    <?= e(t('week.' . $d)) ?>
                </label>
            <?php endfor; ?>
        </div>
        <button type="submit" class="btn btn--secondary btn--sm">
            <?= e(t('action.save')) ?>
        </button>
    </div>
</form>

<!-- Add manual training session -->
<?php if ($teamId !== null): ?>
<form method="POST" action="<?= e(APP_URL) ?>/public/index.php?page=season&action=add_training">
    <input type="hidden" name="team_id" value="<?= $teamId ?>">
    <div class="card">
        <label class="form-label"><?= e(t('season.add_training')) ?></label>
        <div class="flex gap-1" style="margin-top:0.35rem;">
            <input type="date" name="date" class="form-input"
                   <?php if ($range['season_start']): ?>
                       min="<?= e($range['season_start']) ?>" max="<?= e($range['season_end']) ?>"
                   <?php endif; ?> required>
            <button type="submit" class="btn btn--primary btn--sm" style="white-space:nowrap;">
                <?= e(t('action.add')) ?>
            </button>
        </div>
    </div>
</form>
<?php endif; ?>
<?php

$content = ob_get_clean();
$title   = $season['name'];
require dirname(__DIR__, 2) . '/templates/layout.php';
