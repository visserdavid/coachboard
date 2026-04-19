<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1259A8">
    <title><?= e($title ?? t('app.name')) ?> — <?= e(t('app.name')) ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/css/style.css">
</head>
<body>
    <main class="page-content">
        <?= $content ?? '' ?>
    </main>
    <?php require __DIR__ . '/nav.php'; ?>
    <script src="<?= APP_URL ?>/js/app.js"></script>
</body>
</html>
