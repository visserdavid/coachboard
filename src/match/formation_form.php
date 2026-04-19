<?php
declare(strict_types=1);

$activePage = 'settings';

$formationRepo     = new FormationRepository();
$formationService  = new FormationService();
$editId            = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit            = $editId !== null;
$formation         = null;
$existingPositions = [];
$errors            = [];

if ($isEdit) {
    $formation = $formationRepo->getFormationById($editId);
    if ($formation === null) {
        $_SESSION['flash'] = t('error.not_found');
        redirect(APP_URL . '/public/index.php?page=formation&action=list');
    }
    $existingPositions = $formationRepo->getPositionsByFormation($editId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name            = trim($_POST['name'] ?? '');
    $outfieldPlayers = (int) ($_POST['outfield_players'] ?? 10);
    $positions       = $_POST['positions'] ?? [];

    if ($name === '') {
        $errors[] = t('formation.name') . ' ' . t('error.required');
    }
    if ($outfieldPlayers < 7 || $outfieldPlayers > 11) {
        $errors[] = t('formation.outfield_range_error');
    }

    if (empty($errors)) {
        $data = ['name' => $name, 'outfield_players' => $outfieldPlayers];

        if ($isEdit) {
            $formationService->updateFormation($editId, $data);
            $formationService->savePositions($editId, $positions);
            $savedId = $editId;
        } else {
            $savedId = $formationService->createFormation($data);
            $formationService->savePositions($savedId, $positions);
        }

        $_SESSION['flash'] = t('formation.saved');
        redirect(APP_URL . '/public/index.php?page=formation&action=list');
    }

    // Re-populate from POST on error
    $existingPositions = array_values((array) $positions);
}

$name            = $_POST['name']             ?? ($formation['name']             ?? '');
$outfieldPlayers = isset($_POST['name'])
    ? (int) ($_POST['outfield_players'] ?? 10)
    : (int) ($formation['outfield_players'] ?? 10);

$validLines = ['goalkeeper', 'defence', 'midfield', 'attack'];

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/public/index.php?page=formation&action=list"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title">
        <?= $isEdit ? e(t('formation.edit')) : e(t('formation.add')) ?>
    </h1>
</div>

<?php if (!empty($errors)): ?>
    <div class="flash-message flash-message--error">
        <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST"
      action="<?= e(APP_URL) ?>/public/index.php?page=formation&action=<?= $isEdit ? 'edit&id=' . $editId : 'create' ?>">
    <div class="card">
        <div class="form-group">
            <label class="form-label" for="name"><?= e(t('formation.name')) ?></label>
            <input class="form-input" type="text" id="name" name="name"
                   value="<?= e($name) ?>" required maxlength="50">
        </div>
        <div class="form-group">
            <label class="form-label" for="outfield_players"><?= e(t('formation.outfield_players')) ?></label>
            <input class="form-input" type="number" id="outfield_players" name="outfield_players"
                   value="<?= $outfieldPlayers ?>" min="7" max="11" required>
            <div class="text-sm text-muted mt-1"><?= e(t('formation.outfield_note')) ?></div>
        </div>
    </div>

    <div class="card">
        <div class="flex-between" style="margin-bottom:0.75rem;">
            <strong><?= e(t('formation.positions')) ?></strong>
            <button type="button" id="add-position-btn" class="btn btn--secondary btn--sm">
                <?= e(t('formation.add_position')) ?>
            </button>
        </div>

        <div id="positions-container">
            <?php
            $rows = $isEdit && !isset($_POST['name']) ? $existingPositions : $existingPositions;
            foreach ($rows as $i => $pos):
                $posLabel = is_array($pos) ? ($pos['position_label'] ?? '') : '';
                $posLine  = is_array($pos) ? ($pos['line'] ?? 'defence') : 'defence';
                $posX     = is_array($pos) ? ($pos['pos_x'] ?? 50) : 50;
                $posY     = is_array($pos) ? ($pos['pos_y'] ?? 50) : 50;
            ?>
            <div class="position-row" style="display:grid;grid-template-columns:1fr 1fr 5rem 5rem 2rem;gap:0.5rem;margin-bottom:0.5rem;align-items:center;">
                <input class="form-input form-input--sm" type="text" name="positions[<?= $i ?>][position_label]"
                       placeholder="<?= e(t('formation.position_label')) ?>"
                       value="<?= e($posLabel) ?>" maxlength="50" required>
                <select class="form-input form-input--sm" name="positions[<?= $i ?>][line]">
                    <?php foreach ($validLines as $lineVal): ?>
                        <option value="<?= $lineVal ?>" <?= $posLine === $lineVal ? 'selected' : '' ?>>
                            <?= e(t('player.line.' . $lineVal)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input class="form-input form-input--sm" type="number" name="positions[<?= $i ?>][pos_x]"
                       placeholder="X" value="<?= (float) $posX ?>" min="0" max="100" step="0.01">
                <input class="form-input form-input--sm" type="number" name="positions[<?= $i ?>][pos_y]"
                       placeholder="Y" value="<?= (float) $posY ?>" min="0" max="100" step="0.01">
                <button type="button" class="btn btn--danger btn--sm remove-position-btn" title="Remove">×</button>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn--primary" style="margin-top:1rem;">
            <?= e(t('action.save')) ?>
        </button>
    </div>
</form>

<script>
(function () {
    let idx = <?= count($existingPositions) ?>;
    const container = document.getElementById('positions-container');
    const lines = <?= json_encode($validLines) ?>;
    const lineLabels = <?= json_encode(array_combine($validLines, array_map(fn($l) => t('player.line.' . $l), $validLines))) ?>;

    function addRow() {
        const row = document.createElement('div');
        row.className = 'position-row';
        row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 5rem 5rem 2rem;gap:0.5rem;margin-bottom:0.5rem;align-items:center;';

        const labelInput = `<input class="form-input form-input--sm" type="text" name="positions[${idx}][position_label]" placeholder="<?= e(t('formation.position_label')) ?>" maxlength="50" required>`;
        const lineSelect = `<select class="form-input form-input--sm" name="positions[${idx}][line]">${lines.map(l => `<option value="${l}">${lineLabels[l]}</option>`).join('')}</select>`;
        const xInput = `<input class="form-input form-input--sm" type="number" name="positions[${idx}][pos_x]" placeholder="X" value="50" min="0" max="100" step="0.01">`;
        const yInput = `<input class="form-input form-input--sm" type="number" name="positions[${idx}][pos_y]" placeholder="Y" value="50" min="0" max="100" step="0.01">`;
        const removeBtn = `<button type="button" class="btn btn--danger btn--sm remove-position-btn" title="Remove">×</button>`;

        row.innerHTML = labelInput + lineSelect + xInput + yInput + removeBtn;
        container.appendChild(row);
        idx++;
    }

    document.getElementById('add-position-btn').addEventListener('click', addRow);

    container.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-position-btn')) {
            e.target.closest('.position-row').remove();
        }
    });
}());
</script>
<?php

$content = ob_get_clean();
$title   = $isEdit ? t('formation.edit') : t('formation.add');
require dirname(__DIR__, 2) . '/templates/layout.php';
