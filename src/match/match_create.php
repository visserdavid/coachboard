<?php
declare(strict_types=1);

$activePage   = 'match';
$activeSeason = getActiveSeason();
$seasonRepo   = new SeasonRepository();
$matchService = new MatchService();

$team   = $activeSeason ? $seasonRepo->getTeamBySeason((int) $activeSeason['id']) : null;
$teamId = $team ? (int) $team['id'] : null;

if ($teamId === null) {
    $_SESSION['flash'] = t('dashboard.no_season');
    redirect(APP_URL . '/index.php?page=match');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'team_id'               => $teamId,
        'date'                  => trim($_POST['date'] ?? ''),
        'kick_off_time'         => trim($_POST['kick_off_time'] ?? ''),
        'opponent'              => trim($_POST['opponent'] ?? ''),
        'home_away'             => $_POST['home_away'] ?? '',
        'match_type'            => $_POST['match_type'] ?? 'league',
        'half_duration_minutes' => (int) ($_POST['half_duration_minutes'] ?? 45),
    ];

    try {
        $matchId = $matchService->createMatch($data);
        redirect(APP_URL . '/index.php?page=match&action=prepare&id=' . $matchId);
    } catch (InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    }
}

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/index.php?page=match"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title" style="font-size:1rem;"><?= e(t('match.new')) ?></h1>
    <span></span>
</div>

<?php if (!empty($errors)): ?>
    <div class="card" style="border-left:3px solid var(--color-danger); margin-bottom:1rem;">
        <?php foreach ($errors as $err): ?>
            <p class="text-sm text-danger"><?= e($err) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="<?= e(APP_URL) ?>/index.php?page=match&action=create">
        <?= csrfField() ?>

        <div class="form-group">
            <label class="form-label"><?= e(t('match.opponent')) ?></label>
            <input type="text" name="opponent" class="form-input"
                   value="<?= e($_POST['opponent'] ?? '') ?>"
                   placeholder="<?= e(t('match.opponent')) ?>" required maxlength="150">
        </div>

        <div class="form-group">
            <label class="form-label"><?= e(t('match.date')) ?></label>
            <input type="date" name="date" class="form-input"
                   value="<?= e($_POST['date'] ?? date('Y-m-d')) ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label"><?= e(t('match.kick_off')) ?></label>
            <input type="time" name="kick_off_time" class="form-input"
                   value="<?= e($_POST['kick_off_time'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label"><?= e(t('match.home')) ?> / <?= e(t('match.away')) ?></label>
            <div style="display:flex; gap:0.5rem;">
                <?php foreach (['home', 'away'] as $ha): ?>
                    <label style="display:flex; align-items:center; gap:0.35rem; cursor:pointer; font-weight:normal;">
                        <input type="radio" name="home_away" value="<?= $ha ?>"
                               <?= (($_POST['home_away'] ?? 'home') === $ha) ? 'checked' : '' ?>>
                        <?= e(t('match.' . $ha)) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label"><?= e(t('match.type.league')) ?> / <?= e(t('match.type.tournament')) ?> / <?= e(t('match.type.friendly')) ?></label>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <?php foreach (['league', 'tournament', 'friendly'] as $mt): ?>
                    <label style="display:flex; align-items:center; gap:0.35rem; cursor:pointer; font-weight:normal;">
                        <input type="radio" name="match_type" value="<?= $mt ?>"
                               <?= (($_POST['match_type'] ?? 'league') === $mt) ? 'checked' : '' ?>>
                        <?= e(t('match.type.' . $mt)) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label"><?= e(t('match.half_duration')) ?></label>
            <input type="number" name="half_duration_minutes" class="form-input"
                   value="<?= e((string) ($_POST['half_duration_minutes'] ?? '45')) ?>"
                   min="20" max="60" required>
        </div>

        <button type="submit" class="btn btn--primary btn--full mt-2">
            <?= e(t('match.new')) ?>
        </button>
    </form>
</div>
<?php

$content = ob_get_clean();
$title   = t('match.new');
require dirname(__DIR__, 2) . '/templates/layout.php';
