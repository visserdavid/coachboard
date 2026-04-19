<?php
declare(strict_types=1);

$staffRepo = new StaffRepository();
$editId    = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit    = $editId !== null;
$member    = null;
$errors    = [];

if ($isEdit) {
    $member = $staffRepo->getStaffById($editId);
    if ($member === null) {
        $_SESSION['flash'] = t('error.not_found');
        redirect(APP_URL . '/public/index.php?page=staff&action=list');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName       = trim($_POST['first_name'] ?? '');
    $email           = trim(strtolower($_POST['email'] ?? ''));
    $isAdministrator = !empty($_POST['is_administrator']);
    $isTrainer       = !empty($_POST['is_trainer']);
    $isCoach         = !empty($_POST['is_coach']);
    $isAssistant     = !empty($_POST['is_assistant']);

    if ($firstName === '') {
        $errors[] = t('staff.first_name') . ' ' . t('error.required');
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('staff.email_invalid');
    } elseif ($staffRepo->emailExists($email, $isEdit ? $editId : null)) {
        $errors[] = t('staff.email_taken');
    }
    if (!$isAdministrator && !$isTrainer && !$isCoach && !$isAssistant) {
        $errors[] = t('staff.roles_required');
    }

    // Prevent removing administrator role from last admin
    if ($isEdit && !$isAdministrator && $staffRepo->isLastAdministrator($editId)) {
        $errors[] = t('staff.last_admin_error');
    }

    if (empty($errors)) {
        $data = [
            'first_name'       => $firstName,
            'email'            => $email,
            'is_administrator' => $isAdministrator,
            'is_trainer'       => $isTrainer,
            'is_coach'         => $isCoach,
            'is_assistant'     => $isAssistant,
        ];

        if ($isEdit) {
            $staffRepo->updateStaff($editId, $data);
        } else {
            $staffRepo->createStaff($data);
        }

        $_SESSION['flash'] = t('staff.saved');
        redirect(APP_URL . '/public/index.php?page=staff&action=list');
    }
}

$firstName       = $_POST['first_name']      ?? ($member['first_name']       ?? '');
$email           = $_POST['email']           ?? ($member['email']            ?? '');
$isAdministrator = isset($_POST['first_name'])
    ? !empty($_POST['is_administrator'])
    : (!empty($member['is_administrator']));
$isTrainer       = isset($_POST['first_name'])
    ? !empty($_POST['is_trainer'])
    : (!empty($member['is_trainer']));
$isCoach         = isset($_POST['first_name'])
    ? !empty($_POST['is_coach'])
    : (!empty($member['is_coach']));
$isAssistant     = isset($_POST['first_name'])
    ? !empty($_POST['is_assistant'])
    : (!empty($member['is_assistant']));

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/public/index.php?page=staff&action=list"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title">
        <?= $isEdit ? e(t('staff.edit')) : e(t('staff.add')) ?>
    </h1>
</div>

<?php if (!empty($errors)): ?>
    <div class="flash-message flash-message--error">
        <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST" action="<?= e(APP_URL) ?>/public/index.php?page=staff&action=<?= $isEdit ? 'edit&id=' . $editId : 'create' ?>">
    <div class="card">
        <div class="form-group">
            <label class="form-label" for="first_name"><?= e(t('staff.first_name')) ?></label>
            <input class="form-input" type="text" id="first_name" name="first_name"
                   value="<?= e($firstName) ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="email"><?= e(t('staff.email')) ?></label>
            <input class="form-input" type="email" id="email" name="email"
                   value="<?= e($email) ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label"><?= e(t('staff.roles')) ?></label>
            <div class="flex gap-2" style="flex-wrap:wrap;">
                <label class="flex gap-1" style="align-items:center;cursor:pointer;">
                    <input type="checkbox" name="is_administrator" value="1"
                           <?= $isAdministrator ? 'checked' : '' ?>>
                    <?= e(t('staff.role.administrator')) ?>
                </label>
                <label class="flex gap-1" style="align-items:center;cursor:pointer;">
                    <input type="checkbox" name="is_trainer" value="1"
                           <?= $isTrainer ? 'checked' : '' ?>>
                    <?= e(t('staff.role.trainer')) ?>
                </label>
                <label class="flex gap-1" style="align-items:center;cursor:pointer;">
                    <input type="checkbox" name="is_coach" value="1"
                           <?= $isCoach ? 'checked' : '' ?>>
                    <?= e(t('staff.role.coach')) ?>
                </label>
                <label class="flex gap-1" style="align-items:center;cursor:pointer;">
                    <input type="checkbox" name="is_assistant" value="1"
                           <?= $isAssistant ? 'checked' : '' ?>>
                    <?= e(t('staff.role.assistant')) ?>
                </label>
            </div>
        </div>
        <button type="submit" class="btn btn--primary"><?= e(t('action.save')) ?></button>
    </div>
</form>
<?php

$content = ob_get_clean();
$title   = $isEdit ? t('staff.edit') : t('staff.add');
require dirname(__DIR__, 2) . '/templates/layout.php';
