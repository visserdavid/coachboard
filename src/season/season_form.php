<?php
declare(strict_types=1);

$activePage = 'settings';

require_once dirname(__DIR__, 2) . '/src/season/SeasonRepository.php';
require_once dirname(__DIR__, 2) . '/src/season/SeasonService.php';

$repo    = new SeasonRepository();
$service = new SeasonService();

$errors  = [];
$values  = [
    'mode'           => 'blank',
    'name'           => '',
    'has_phases'     => '0',
    'season_start'   => '',
    'season_end'     => '',
    'phases'         => [
        ['label' => '', 'start_date' => '', 'end_date' => '', 'focus' => ''],
        ['label' => '', 'start_date' => '', 'end_date' => '', 'focus' => ''],
    ],
    'training_days'  => [],
    'copy_season_id' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['mode']           = $_POST['mode'] ?? 'blank';
    $values['name']           = trim($_POST['name'] ?? '');
    $values['has_phases']     = $_POST['has_phases'] ?? '0';
    $values['season_start']   = trim($_POST['season_start'] ?? '');
    $values['season_end']     = trim($_POST['season_end'] ?? '');
    $values['phases']         = $_POST['phases'] ?? [];
    $values['training_days']  = array_map('intval', (array) ($_POST['training_days'] ?? []));
    $values['copy_season_id'] = trim($_POST['copy_season_id'] ?? '');

    // Validate
    if ($values['name'] === '') {
        $errors[] = t('season.name') . ' ' . t('error.required');
    }

    if (empty($values['training_days'])) {
        $errors[] = t('season.training_days') . ' ' . t('error.required');
    }

    $hasPhases = $values['has_phases'] === '1';

    if ($hasPhases) {
        foreach ($values['phases'] as $i => $ph) {
            if (empty($ph['start_date']) || empty($ph['end_date'])) {
                $errors[] = t('phase.label', ['number' => $i + 1]) . ': ' . t('error.required');
            }
        }
    } else {
        if (empty($values['season_start']) || empty($values['season_end'])) {
            $errors[] = t('season.phase.start') . '/' . t('season.phase.end') . ' ' . t('error.required');
        } elseif ($values['season_start'] >= $values['season_end']) {
            $errors[] = t('season.phase_overlap_error');
        }
    }

    if ($values['mode'] === 'copy' && empty($values['copy_season_id'])) {
        $errors[] = t('season.copy_squad_from') . ' ' . t('error.required');
    }

    if (empty($errors)) {
        try {
            $data = [
                'name'          => $values['name'],
                'has_phases'    => $hasPhases,
                'training_days' => $values['training_days'],
            ];

            if ($hasPhases) {
                $phases = [];
                foreach ($values['phases'] as $ph) {
                    if (!empty($ph['start_date']) && !empty($ph['end_date'])) {
                        $phases[] = $ph;
                    }
                }
                $data['phases'] = $phases;
            } else {
                $data['season_start'] = $values['season_start'];
                $data['season_end']   = $values['season_end'];
            }

            if ($values['mode'] === 'copy' && !empty($values['copy_season_id'])) {
                $seasonId = $service->createSeasonFromCopy((int) $values['copy_season_id'], $data);
            } else {
                $seasonId = $service->createNewSeason($data);
            }

            $team      = $repo->getTeamBySeason($seasonId);
            $teamId    = $team ? (int) $team['id'] : 0;
            $count     = $service->generateTrainingSchedule($teamId, $seasonId);

            $_SESSION['flash'] = t('season.created') . ' '
                . t('season.schedule_generated', ['count' => $count]);

            $_SESSION['active_season'] = $repo->getActiveSeason();
            $_SESSION['active_phases'] = $repo->getPhasesBySeason(
                (int) ($_SESSION['active_season']['id'] ?? 0)
            );

            redirect(APP_URL . '/index.php?page=season&action=detail&id=' . $seasonId);

        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        } catch (Exception $e) {
            $errors[] = t('error.general');
        }
    }
}

$existingSeasons = $repo->getAllSeasons();

ob_start();
?>
<div class="page-header">
    <a href="<?= e(APP_URL) ?>/index.php?page=season&action=list"
       class="btn btn--secondary btn--sm"><?= e(t('action.back')) ?></a>
    <h1 class="page-title"><?= e(t('season.new')) ?></h1>
    <span></span>
</div>

<?php if (!empty($errors)): ?>
    <div class="card" style="border-left: 3px solid var(--color-danger);">
        <?php foreach ($errors as $err): ?>
            <p class="text-danger text-sm"><?= e($err) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>
    <div class="card">
        <div class="form-group">
            <label class="form-label"><?= e(t('season.name')) ?></label>
            <input type="text" name="name" class="form-input"
                   value="<?= e($values['name']) ?>" required>
        </div>

        <?php if (!empty($existingSeasons)): ?>
        <div class="form-group">
            <label class="form-label"><?= e(t('season.mode.blank')) ?> / <?= e(t('season.mode.copy')) ?></label>
            <div class="flex gap-1" style="margin-top:0.25rem;">
                <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;">
                    <input type="radio" name="mode" value="blank"
                           <?= $values['mode'] === 'blank' ? 'checked' : '' ?>
                           onchange="document.getElementById('copy-source').style.display='none'">
                    <?= e(t('season.mode.blank')) ?>
                </label>
                <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;">
                    <input type="radio" name="mode" value="copy"
                           <?= $values['mode'] === 'copy' ? 'checked' : '' ?>
                           onchange="document.getElementById('copy-source').style.display='block'">
                    <?= e(t('season.mode.copy')) ?>
                </label>
            </div>
        </div>
        <div id="copy-source" style="display:<?= $values['mode'] === 'copy' ? 'block' : 'none' ?>;">
            <div class="form-group">
                <label class="form-label"><?= e(t('season.copy_squad_from')) ?></label>
                <select name="copy_season_id" class="form-select">
                    <option value="">—</option>
                    <?php foreach ($existingSeasons as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"
                                <?= $values['copy_season_id'] == $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="form-group" style="margin-bottom:0.5rem;">
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                <input type="checkbox" name="has_phases" value="1" id="has-phases-toggle"
                       <?= $values['has_phases'] === '1' ? 'checked' : '' ?>
                       onchange="togglePhaseSection(this.checked)">
                <strong><?= e(t('season.has_phases')) ?></strong>
            </label>
        </div>

        <div id="single-date-range" style="display:<?= $values['has_phases'] === '1' ? 'none' : 'block' ?>;">
            <div class="form-group">
                <label class="form-label"><?= e(t('season.phase.start')) ?></label>
                <input type="date" name="season_start" class="form-input"
                       value="<?= e($values['season_start']) ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label"><?= e(t('season.phase.end')) ?></label>
                <input type="date" name="season_end" class="form-input"
                       value="<?= e($values['season_end']) ?>">
            </div>
        </div>

        <div id="phases-container" style="display:<?= $values['has_phases'] === '1' ? 'block' : 'none' ?>;">
            <?php foreach ($values['phases'] as $i => $ph): ?>
            <div class="phase-row" style="border-top:1px solid var(--color-border); padding-top:0.75rem; margin-top:0.75rem;">
                <div class="flex-between" style="margin-bottom:0.5rem;">
                    <strong class="text-sm"><?= e(t('phase.label', ['number' => $i + 1])) ?></strong>
                    <?php if ($i > 0): ?>
                        <button type="button" class="btn btn--danger btn--sm"
                                onclick="removePhase(this)"><?= e(t('action.delete')) ?></button>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e(t('season.phase.label')) ?></label>
                    <input type="text" name="phases[<?= $i ?>][label]" class="form-input"
                           value="<?= e($ph['label'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e(t('season.phase.start')) ?></label>
                    <input type="date" name="phases[<?= $i ?>][start_date]" class="form-input"
                           value="<?= e($ph['start_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label"><?= e(t('season.phase.end')) ?></label>
                    <input type="date" name="phases[<?= $i ?>][end_date]" class="form-input"
                           value="<?= e($ph['end_date'] ?? '') ?>">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label"><?= e(t('season.phase.focus')) ?></label>
                    <textarea name="phases[<?= $i ?>][focus]" class="form-textarea"><?= e($ph['focus'] ?? '') ?></textarea>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div id="add-phase-btn-wrap" style="display:<?= $values['has_phases'] === '1' ? 'block' : 'none' ?>; margin-top:0.75rem;">
            <button type="button" class="btn btn--secondary btn--sm btn--full"
                    onclick="addPhase()"><?= e(t('season.add_phase')) ?></button>
        </div>
    </div>

    <div class="card">
        <label class="form-label"><?= e(t('season.training_days')) ?></label>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.5rem; margin-top:0.35rem;">
            <?php for ($d = 1; $d <= 7; $d++): ?>
                <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;">
                    <input type="checkbox" name="training_days[]" value="<?= $d ?>"
                           <?= in_array($d, $values['training_days'], true) ? 'checked' : '' ?>>
                    <?= e(t('week.' . $d)) ?>
                </label>
            <?php endfor; ?>
        </div>
    </div>

    <button type="submit" class="btn btn--primary btn--full mt-2">
        <?= e(t('action.save')) ?>
    </button>
</form>

<script>
let phaseCount = <?= count($values['phases']) ?>;

function togglePhaseSection(hasPhases) {
    document.getElementById('single-date-range').style.display = hasPhases ? 'none' : 'block';
    document.getElementById('phases-container').style.display   = hasPhases ? 'block' : 'none';
    document.getElementById('add-phase-btn-wrap').style.display = hasPhases ? 'block' : 'none';
}

function addPhase() {
    const container = document.getElementById('phases-container');
    const i = phaseCount;
    const visibleRows = Array.from(container.querySelectorAll('.phase-row'))
        .filter((row) => row.offsetParent !== null);
    const previousVisibleRow = visibleRows.length > 0 ? visibleRows[visibleRows.length - 1] : null;
    let nextStartDate = '';

    if (previousVisibleRow) {
        const previousEndDateInput = previousVisibleRow.querySelector('input[name$="[end_date]"]');
        const previousEndDate = previousEndDateInput ? previousEndDateInput.value : '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(previousEndDate)) {
            const date = new Date(`${previousEndDate}T00:00:00`);
            if (!Number.isNaN(date.getTime())) {
                date.setDate(date.getDate() + 1);
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                nextStartDate = `${year}-${month}-${day}`;
            }
        }
    }

    const div = document.createElement('div');
    div.className = 'phase-row';
    div.style.cssText = 'border-top:1px solid var(--color-border); padding-top:0.75rem; margin-top:0.75rem;';
    div.innerHTML = `
        <div class="flex-between" style="margin-bottom:0.5rem;">
            <strong class="text-sm"><?= e(t('phase.label', ['number' => ''])) ?>${i + 1}</strong>
            <button type="button" class="btn btn--danger btn--sm" onclick="removePhase(this)"><?= e(t('action.delete')) ?></button>
        </div>
        <div class="form-group">
            <label class="form-label"><?= e(t('season.phase.label')) ?></label>
            <input type="text" name="phases[${i}][label]" class="form-input">
        </div>
        <div class="form-group">
            <label class="form-label"><?= e(t('season.phase.start')) ?></label>
            <input type="date" name="phases[${i}][start_date]" class="form-input" value="${nextStartDate}">
        </div>
        <div class="form-group">
            <label class="form-label"><?= e(t('season.phase.end')) ?></label>
            <input type="date" name="phases[${i}][end_date]" class="form-input">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label class="form-label"><?= e(t('season.phase.focus')) ?></label>
            <textarea name="phases[${i}][focus]" class="form-textarea"></textarea>
        </div>`;
    container.appendChild(div);
    phaseCount++;
}

function removePhase(btn) {
    btn.closest('.phase-row').remove();
}
</script>
<?php

$content = ob_get_clean();
$title   = t('season.new');
require dirname(__DIR__, 2) . '/templates/layout.php';
