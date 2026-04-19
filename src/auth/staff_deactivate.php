<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/public/index.php?page=staff&action=list');
}

$id        = (int) ($_POST['id'] ?? 0);
$staffRepo = new StaffRepository();

if ($staffRepo->isLastAdministrator($id)) {
    $_SESSION['flash'] = t('staff.last_admin_error');
    redirect(APP_URL . '/public/index.php?page=staff&action=list');
}

$staffRepo->deactivateStaff($id);
$_SESSION['flash'] = t('staff.deactivated');
redirect(APP_URL . '/public/index.php?page=staff&action=list');
