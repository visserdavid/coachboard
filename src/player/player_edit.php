<?php
declare(strict_types=1);

$activePage = 'squad';

$id         = (int) ($_GET['id'] ?? 0);
$playerRepo = new PlayerRepository();
$playerSvc  = new PlayerService();

$player = $playerRepo->getPlayerById($id);
if ($player === null) {
    $_SESSION['flash'] = t('error.not_found');
    redirect(APP_URL . '/index.php?page=squad');
}

$user    = Auth::getCurrentUser();
$canEdit = $user && (!empty($user['is_administrator']) || !empty($user['is_assistant']));
$canSkills = $user && (!empty($user['is_administrator']) || !empty($user['is_trainer']));

if (!$canEdit && !$canSkills) {
    http_response_code(403);
    $_SESSION['flash'] = t('error.forbidden');
    redirect(APP_URL . '/index.php?page=squad&action=profile&id=' . $id);
}

// Determine season via team
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare('SELECT season_id FROM team WHERE id = ? LIMIT 1');
$stmt->execute([$player['team_id']]);
$teamRow  = $stmt->fetch();
$seasonId = $teamRow ? (int) $teamRow['season_id'] : 0;

$skills   = $playerRepo->getSkillsByPlayer($id, $seasonId);
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'update_details' && $canEdit) {
        $data = [
            'team_id'        => $player['team_id'],
            'first_name'     => trim($_POST['first_name'] ?? ''),
            'squad_number'   => !empty($_POST['squad_number']) ? (int) $_POST['squad_number'] : null,
            'preferred_foot' => $_POST['preferred_foot'] ?? null,
            'preferred_line' => $_POST['preferred_line'] ?? null,
            'photo_consent'  => !empty($_POST['photo_consent']) ? 1 : 0,
        ];

        // Normalize empty strings to null
        if ($data['preferred_foot'] === '') $data['preferred_foot'] = null;
        if ($data['preferred_line'] === '') $data['preferred_line'] = null;

        try {
            $playerSvc->updatePlayer($id, $data);

            // Handle photo upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $playerSvc->uploadPhoto($id, $_FILES['photo']);
            }

            $_SESSION['flash'] = t('player.saved');
            redirect(APP_URL . '/index.php?page=squad&action=edit&id=' . $id);
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        } catch (Exception $e) {
            $errors[] = t('error.general');
        }

        // Reload player after save
        $player = $playerRepo->getPlayerById($id) ?? $player;
    }

    if ($action === 'update_skills' && $canSkills) {
        try {
            $skillData = [
                'pace'        => $_POST['pace'] ?? null,
                'shooting'    => $_POST['shooting'] ?? null,
                'passing'     => $_POST['passing'] ?? null,
                'dribbling'   => $_POST['dribbling'] ?? null,
                'defending'   => $_POST['defending'] ?? null,
                'physicality' => $_POST['physicality'] ?? null,
            ];
            $playerSvc->saveSkills($id, $seasonId, $skillData);
            $_SESSION['flash'] = t('skill.saved');
            redirect(APP_URL . '/index.php?page=squad&action=edit&id=' . $id);
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        $skills = $playerRepo->getSkillsByPlayer($id, $seasonId);
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$skillKeys = ['pace', 'shooting', 'passing', 'dribbling', 'defending', 'physicality'];

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/index.php?page=squad&action=profile&id=<?= $id ?>"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title"><?= e(t('player.edit')) ?></h1>
    <span></span>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-message"><?= e($flash) ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="card" style="border-left:3px solid var(--color-danger);">
        <?php foreach ($errors as $err): ?>
            <p class="text-danger text-sm"><?= e($err) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($canEdit): ?>
<!-- Section 1: Basic details -->
<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="_action" value="update_details">
    <div class="card">
        <h3 style="font-size:var(--font-size-sm);font-weight:600;color:var(--color-neutral);margin-bottom:0.75rem;">
            <?= e(t('player.name')) ?> &amp; <?= e(t('player.photo')) ?>
        </h3>

        <div class="form-group">
            <label class="form-label"><?= e(t('player.name')) ?></label>
            <input type="text" name="first_name" class="form-input"
                   value="<?= e($player['first_name']) ?>" required maxlength="100">
        </div>

        <div class="form-group">
            <label class="form-label"><?= e(t('player.squad_number')) ?></label>
            <input type="number" name="squad_number" class="form-input"
                   value="<?= $player['squad_number'] !== null ? (int) $player['squad_number'] : '' ?>"
                   min="1" max="99" placeholder="—">
        </div>

        <div class="form-group">
            <label class="form-label"><?= e(t('player.preferred_foot')) ?></label>
            <?php foreach (['' => t('player.preferred_foot.none'), 'right' => t('player.preferred_foot.right'), 'left' => t('player.preferred_foot.left')] as $val => $label): ?>
                <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;margin-top:0.35rem;">
                    <input type="radio" name="preferred_foot" value="<?= e($val) ?>"
                           <?= ($player['preferred_foot'] ?? '') === $val ? 'checked' : '' ?>>
                    <?= e($label) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="form-group">
            <label class="form-label"><?= e(t('player.preferred_line')) ?></label>
            <?php foreach (['' => t('player.line.none'), 'goalkeeper' => t('player.line.goalkeeper'), 'defence' => t('player.line.defence'), 'midfield' => t('player.line.midfield'), 'attack' => t('player.line.attack')] as $val => $label): ?>
                <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;margin-top:0.35rem;">
                    <input type="radio" name="preferred_line" value="<?= e($val) ?>"
                           <?= ($player['preferred_line'] ?? '') === $val ? 'checked' : '' ?>>
                    <?= e($label) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="form-group">
            <label class="form-label"><?= e(t('player.photo_upload')) ?></label>
            <?php if (!empty($player['photo_path'])): ?>
                <img src="<?= e(APP_URL . '/' . $player['photo_path']) ?>"
                     alt="" style="width:64px;height:64px;border-radius:50%;object-fit:cover;display:block;margin-bottom:0.5rem;">
            <?php endif; ?>
            <input type="file" name="photo" accept="image/jpeg" class="form-input"
                   style="padding:0.4rem;">
            <span class="text-sm text-muted">JPEG, max 2MB</span>
        </div>

        <div class="form-group" style="margin-bottom:0.75rem;">
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                <input type="checkbox" name="photo_consent" value="1"
                       <?= $player['photo_consent'] ? 'checked' : '' ?>>
                <span class="text-sm"><?= e(t('player.photo_consent')) ?></span>
            </label>
        </div>

        <button type="submit" class="btn btn--primary btn--full">
            <?= e(t('action.save')) ?>
        </button>
    </div>
</form>
<?php endif; ?>

<?php if ($canSkills): ?>
<!-- Section 2: Season baseline skills -->
<form method="POST">
    <input type="hidden" name="_action" value="update_skills">
    <div class="card">
        <h3 style="font-size:var(--font-size-sm);font-weight:600;color:var(--color-neutral);margin-bottom:0.75rem;">
            <?= e(t('skill.baseline')) ?>
        </h3>

        <?php foreach ($skillKeys as $skill): ?>
            <?php $val = $skills[$skill] ?? ''; ?>
            <div class="form-group">
                <div class="flex-between" style="margin-bottom:0.25rem;">
                    <label class="form-label" style="margin:0;" for="skill-<?= $skill ?>">
                        <?= e(t('skill.' . $skill)) ?>
                    </label>
                    <span id="skill-<?= $skill ?>-val" style="font-weight:700;color:var(--color-accent);">
                        <?= $val !== '' ? (int) $val : '—' ?>
                    </span>
                </div>
                <input type="range" id="skill-<?= $skill ?>" name="<?= $skill ?>"
                       min="0" max="5" step="1" value="<?= $val !== '' ? (int) $val : 0 ?>"
                       class="form-input" style="padding:0.25rem 0;"
                       oninput="document.getElementById('skill-<?= $skill ?>-val').textContent = this.value > 0 ? this.value : '—'">
            </div>
        <?php endforeach; ?>

        <p class="text-sm text-muted mb-2" style="margin-top:0.25rem;">
            <?= e(t('skill.baseline_note')) ?>
        </p>

        <button type="submit" class="btn btn--primary btn--full">
            <?= e(t('action.save')) ?>
        </button>
    </div>
</form>
<?php endif; ?>
<?php

$content = ob_get_clean();
$title   = t('player.edit');
require dirname(__DIR__, 2) . '/templates/layout.php';
