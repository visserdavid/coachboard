<?php
declare(strict_types=1);

class SeasonRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getActiveSeason(): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `season` WHERE active = 1 AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute();
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function getAllSeasons(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `season` WHERE deleted_at IS NULL ORDER BY id DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getSeasonById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `season` WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function createSeason(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `season` (name, has_phases, active) VALUES (?, ?, 0)'
        );
        $stmt->execute([$data['name'], $data['has_phases'] ? 1 : 0]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateSeason(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `season` SET name = ?, has_phases = ? WHERE id = ?'
        );
        return $stmt->execute([$data['name'], $data['has_phases'] ? 1 : 0, $id]);
    }

    public function getPhasesBySeason(int $seasonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `phase` WHERE season_id = ? ORDER BY number ASC'
        );
        $stmt->execute([$seasonId]);
        return $stmt->fetchAll();
    }

    public function getPhaseById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `phase` WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function createPhase(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `phase` (season_id, number, label, focus, start_date, end_date)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['season_id'],
            $data['number'],
            $data['label'] ?? null,
            $data['focus'] ?? null,
            $data['start_date'],
            $data['end_date'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePhase(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `phase` SET label = ?, focus = ?, start_date = ?, end_date = ? WHERE id = ?'
        );
        return $stmt->execute([
            $data['label'] ?? null,
            $data['focus'] ?? null,
            $data['start_date'],
            $data['end_date'],
            $id,
        ]);
    }

    public function getCurrentPhase(int $seasonId): ?array
    {
        $today = date('Y-m-d');
        $stmt  = $this->pdo->prepare(
            'SELECT * FROM `phase`
             WHERE season_id = ? AND start_date <= ? AND end_date >= ?
             LIMIT 1'
        );
        $stmt->execute([$seasonId, $today, $today]);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function getTrainingDaysByTeam(int $teamId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT day_of_week FROM `team_training_day`
             WHERE team_id = ? ORDER BY day_of_week ASC'
        );
        $stmt->execute([$teamId]);
        return array_column($stmt->fetchAll(), 'day_of_week');
    }

    public function setTrainingDays(int $teamId, array $daysOfWeek): bool
    {
        $this->pdo->prepare(
            'DELETE FROM `team_training_day` WHERE team_id = ?'
        )->execute([$teamId]);

        if (empty($daysOfWeek)) {
            return true;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `team_training_day` (team_id, day_of_week) VALUES (?, ?)'
        );
        foreach ($daysOfWeek as $day) {
            $stmt->execute([$teamId, (int) $day]);
        }
        return true;
    }

    public function getTeamBySeason(int $seasonId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `team` WHERE season_id = ? LIMIT 1'
        );
        $stmt->execute([$seasonId]);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function createTeam(int $seasonId, string $name): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `team` (season_id, name) VALUES (?, ?)'
        );
        $stmt->execute([$seasonId, $name]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getSeasonDateRange(int $seasonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT MIN(start_date) AS season_start, MAX(end_date) AS season_end
             FROM `phase` WHERE season_id = ?'
        );
        $stmt->execute([$seasonId]);
        return $stmt->fetch() ?: ['season_start' => null, 'season_end' => null];
    }
}
