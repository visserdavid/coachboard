<?php
declare(strict_types=1);

class SeasonService
{
    private SeasonRepository $repo;
    private PDO $pdo;

    public function __construct()
    {
        $this->repo = new SeasonRepository();
        $this->pdo  = Database::getInstance()->getConnection();
    }

    public function createNewSeason(array $data): int
    {
        $shouldActivate = $this->repo->getActiveSeason() === null;

        if (!empty($data['has_phases']) && !empty($data['phases'])) {
            $this->validatePhases($data['phases']);
        }

        $seasonId = $this->repo->createSeason([
            'name'       => $data['name'],
            'has_phases' => !empty($data['has_phases']),
        ]);

        $teamId = $this->repo->createTeam($seasonId, $data['name']);
        $this->repo->setTrainingDays($teamId, $data['training_days'] ?? []);

        $this->createPhases($seasonId, $data);

        if ($shouldActivate) {
            $this->setActiveSeason($seasonId);
        }

        return $seasonId;
    }

    public function createSeasonFromCopy(int $sourceSeasonId, array $data): int
    {
        $seasonId = $this->createNewSeason($data);

        $sourceTeam = $this->repo->getTeamBySeason($sourceSeasonId);
        if ($sourceTeam !== null) {
            $newTeam = $this->repo->getTeamBySeason($seasonId);
            if ($newTeam !== null) {
                $this->copyPlayers((int) $sourceTeam['id'], (int) $newTeam['id']);
            }
        }

        return $seasonId;
    }

    public function setActiveSeason(int $id): bool
    {
        $season = $this->repo->getSeasonById($id);
        if ($season === null) {
            return false;
        }

        $this->pdo->prepare('UPDATE `season` SET active = 0 WHERE deleted_at IS NULL')->execute();
        $this->pdo->prepare('UPDATE `season` SET active = 1 WHERE id = ?')->execute([$id]);

        return true;
    }

    public function generateTrainingSchedule(int $teamId, int $seasonId): int
    {
        $range = $this->repo->getSeasonDateRange($seasonId);
        if ($range['season_start'] === null || $range['season_end'] === null) {
            return 0;
        }

        $trainingDays = $this->repo->getTrainingDaysByTeam($teamId);
        if (empty($trainingDays)) {
            return 0;
        }

        $existing = $this->getExistingSessionDates($teamId);

        $current = new DateTime($range['season_start']);
        $end     = new DateTime($range['season_end']);
        $created = 0;

        $stmt = $this->pdo->prepare(
            'INSERT INTO `training_session` (team_id, date) VALUES (?, ?)'
        );

        while ($current <= $end) {
            $dow  = (int) $current->format('N'); // 1=Mon, 7=Sun
            $date = $current->format('Y-m-d');

            if (in_array($dow, $trainingDays, true) && !in_array($date, $existing, true)) {
                $stmt->execute([$teamId, $date]);
                $created++;
            }

            $current->modify('+1 day');
        }

        return $created;
    }

    public function addManualTrainingSession(int $teamId, string $date): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id FROM `season` s
             JOIN `team` t ON t.season_id = s.id
             WHERE t.id = ? AND s.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$teamId]);
        $season = $stmt->fetch();

        if ($season === false) {
            throw new RuntimeException(t('error.general'));
        }

        $range = $this->repo->getSeasonDateRange((int) $season['id']);
        if ($date < $range['season_start'] || $date > $range['season_end']) {
            throw new RuntimeException(t('season.error.date_out_of_range'));
        }

        $existing = $this->getExistingSessionDates($teamId);
        if (in_array($date, $existing, true)) {
            throw new RuntimeException(t('season.error.session_exists'));
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `training_session` (team_id, date) VALUES (?, ?)'
        );
        $stmt->execute([$teamId, $date]);
        return (int) $this->pdo->lastInsertId();
    }

    public function validatePhases(array $phases): bool
    {
        if (empty($phases)) {
            throw new InvalidArgumentException(t('season.phase_gap_error'));
        }

        usort($phases, fn($a, $b) => strcmp($a['start_date'], $b['start_date']));

        foreach ($phases as $i => $phase) {
            if ($phase['start_date'] >= $phase['end_date']) {
                throw new InvalidArgumentException(t('season.phase_overlap_error'));
            }

            if ($i === 0) {
                continue;
            }

            $prevEnd   = new DateTime($phases[$i - 1]['end_date']);
            $currStart = new DateTime($phase['start_date']);

            $prevEnd->modify('+1 day');

            if ($prevEnd > $currStart) {
                throw new InvalidArgumentException(t('season.phase_overlap_error'));
            }

            if ($prevEnd < $currStart) {
                throw new InvalidArgumentException(t('season.phase_gap_error'));
            }
        }

        return true;
    }

    private function createPhases(int $seasonId, array $data): void
    {
        if (!empty($data['has_phases']) && !empty($data['phases'])) {
            foreach ($data['phases'] as $i => $phase) {
                $this->repo->createPhase([
                    'season_id'  => $seasonId,
                    'number'     => $i + 1,
                    'label'      => trim($phase['label'] ?? ''),
                    'focus'      => trim($phase['focus'] ?? ''),
                    'start_date' => $phase['start_date'],
                    'end_date'   => $phase['end_date'],
                ]);
            }
        } else {
            $this->repo->createPhase([
                'season_id'  => $seasonId,
                'number'     => 1,
                'label'      => null,
                'focus'      => null,
                'start_date' => $data['season_start'],
                'end_date'   => $data['season_end'],
            ]);
        }
    }

    private function copyPlayers(int $sourceTeamId, int $newTeamId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT first_name, squad_number, preferred_foot, preferred_line,
                    photo_path, photo_consent
             FROM `player`
             WHERE team_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$sourceTeamId]);
        $players = $stmt->fetchAll();

        $insert = $this->pdo->prepare(
            'INSERT INTO `player`
             (team_id, first_name, squad_number, preferred_foot, preferred_line,
              photo_path, photo_consent)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($players as $p) {
            $insert->execute([
                $newTeamId,
                $p['first_name'],
                $p['squad_number'],
                $p['preferred_foot'],
                $p['preferred_line'],
                $p['photo_path'],
                $p['photo_consent'],
            ]);
        }
    }

    private function getExistingSessionDates(int $teamId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT date FROM `training_session` WHERE team_id = ?'
        );
        $stmt->execute([$teamId]);
        return array_column($stmt->fetchAll(), 'date');
    }
}
