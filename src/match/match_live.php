<?php
declare(strict_types=1);

$activePage = 'match';
$matchRepo  = new MatchRepository();

$id    = (int) ($_GET['id'] ?? 0);
$match = $matchRepo->getMatchById($id);

if ($match === null) {
    $_SESSION['flash'] = t('match.not_found');
    redirect(APP_URL . '/public/index.php?page=match');
}

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/public/index.php?page=match"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title" style="font-size:1rem;"><?= e($match['opponent']) ?></h1>
    <span></span>
</div>
<div class="card">
    <p class="text-muted"><?= e(t('match.status.active')) ?></p>
</div>
<?php
$content = ob_get_clean();
$title   = t('match.status.active');
require dirname(__DIR__, 2) . '/templates/layout.php';
