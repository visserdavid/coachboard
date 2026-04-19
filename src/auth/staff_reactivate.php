<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/index.php?page=staff&action=list');
}

$id        = (int) ($_POST['id'] ?? 0);
$staffRepo = new StaffRepository();
$staffRepo->reactivateStaff($id);

$_SESSION['flash'] = t('staff.reactivated');
redirect(APP_URL . '/index.php?page=staff&action=list');
