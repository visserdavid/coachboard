<?php
declare(strict_types=1);

class PlayerRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getPlayersByTeam(int $teamId, bool $includeDeleted = false): array
    {
        $sql = 'SELECT * FROM `player` WHERE team_id = ?';
        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' ORDER BY squad_number IS NULL, squad_number ASC, first_name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$teamId]);
        return $stmt->fetchAll();
    }

    public function getPlayerById(int $id, bool $includeDeleted = false): ?array
    {
        $sql = 'SELECT * FROM `player` WHERE id = ?';
        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function createPlayer(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `player`
             (team_id, first_name, squad_number, preferred_foot, preferred_line, photo_consent)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['team_id'],
            $data['first_name'],
            $data['squad_number'] ?? null,
            $data['preferred_foot'] ?? null,
            $data['preferred_line'] ?? null,
            $data['photo_consent'] ?? 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePlayer(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `player`
             SET first_name = ?, squad_number = ?, preferred_foot = ?,
                 preferred_line = ?, photo_consent = ?
             WHERE id = ?'
        );
        return $stmt->execute([
            $data['first_name'],
            $data['squad_number'] ?? null,
            $data['preferred_foot'] ?? null,
            $data['preferred_line'] ?? null,
            $data['photo_consent'] ?? 0,
            $id,
        ]);
    }

    public function updatePhotoPath(int $id, ?string $photoPath): bool
    {
        $stmt = $this->pdo->prepare('UPDATE `player` SET photo_path = ? WHERE id = ?');
        return $stmt->execute([$photoPath, $id]);
    }

    public function deletePlayer(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `player` SET deleted_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    public function restorePlayer(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `player` SET deleted_at = NULL WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    public function squadNumberExists(int $teamId, int $number, ?int $excludePlayerId = null): bool
    {
        $sql  = 'SELECT COUNT(*) FROM `player`
                 WHERE team_id = ? AND squad_number = ? AND deleted_at IS NULL';
        $args = [$teamId, $number];
        if ($excludePlayerId !== null) {
            $sql  .= ' AND id != ?';
            $args[] = $excludePlayerId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getSkillsByPlayer(int $playerId, int $seasonId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `player_skill`
             WHERE player_id = ? AND season_id = ? LIMIT 1'
        );
        $stmt->execute([$playerId, $seasonId]);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function saveSkills(int $playerId, int $seasonId, array $skills): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `player_skill`
             (player_id, season_id, pace, shooting, passing, dribbling, defending, physicality)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 pace = VALUES(pace),
                 shooting = VALUES(shooting),
                 passing = VALUES(passing),
                 dribbling = VALUES(dribbling),
                 defending = VALUES(defending),
                 physicality = VALUES(physicality)'
        );
        return $stmt->execute([
            $playerId,
            $seasonId,
            $this->clampSkill($skills['pace'] ?? null),
            $this->clampSkill($skills['shooting'] ?? null),
            $this->clampSkill($skills['passing'] ?? null),
            $this->clampSkill($skills['dribbling'] ?? null),
            $this->clampSkill($skills['defending'] ?? null),
            $this->clampSkill($skills['physicality'] ?? null),
        ]);
    }

    public function getAverageRatingsByPlayer(int $playerId, int $seasonId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                 ROUND(AVG(mr.pace))         AS pace,
                 ROUND(AVG(mr.shooting))     AS shooting,
                 ROUND(AVG(mr.passing))      AS passing,
                 ROUND(AVG(mr.dribbling))    AS dribbling,
                 ROUND(AVG(mr.defending))    AS defending,
                 ROUND(AVG(mr.physicality))  AS physicality,
                 COUNT(mr.id)                AS rating_count
             FROM match_rating mr
             JOIN `match` m ON m.id = mr.match_id
             JOIN team t ON t.id = m.team_id
             WHERE mr.player_id = ? AND t.season_id = ? AND m.deleted_at IS NULL'
        );
        $stmt->execute([$playerId, $seasonId]);
        $result = $stmt->fetch();

        if ($result === false || (int) $result['rating_count'] === 0) {
            return null;
        }

        unset($result['rating_count']);
        return $result;
    }

    public function getPlayerSeasonStats(int $playerId, int $seasonId): array
    {
        $pdo = $this->pdo;

        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(mp.playing_time_seconds), 0) / 60 AS minutes,
                    COUNT(mp.id) AS matches_played
             FROM match_player mp
             JOIN `match` m ON m.id = mp.match_id
             JOIN team t ON t.id = m.team_id
             WHERE mp.player_id = ? AND t.season_id = ? AND m.deleted_at IS NULL
             AND m.status IN (\'active\', \'finished\')'
        );
        $stmt->execute([$playerId, $seasonId]);
        $matchRow = $stmt->fetch();

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM match_event me
             JOIN `match` m ON m.id = me.match_id
             JOIN team t ON t.id = m.team_id
             WHERE me.player_id = ? AND me.event_type = \'goal\'
             AND t.season_id = ? AND m.deleted_at IS NULL'
        );
        $stmt->execute([$playerId, $seasonId]);
        $goals = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM match_event me
             JOIN `match` m ON m.id = me.match_id
             JOIN team t ON t.id = m.team_id
             WHERE me.assist_player_id = ? AND me.event_type = \'goal\'
             AND t.season_id = ? AND m.deleted_at IS NULL'
        );
        $stmt->execute([$playerId, $seasonId]);
        $assists = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM attendance a
             WHERE a.player_id = ? AND a.context_type = \'training_session\'
             AND a.status = \'present\'
             AND a.context_id IN (
                 SELECT ts.id FROM training_session ts
                 JOIN team t ON t.id = ts.team_id
                 WHERE t.season_id = ? AND ts.cancelled = 0 AND ts.date <= CURDATE()
             )'
        );
        $stmt->execute([$playerId, $seasonId]);
        $trainingPresent = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM training_session ts
             JOIN team t ON t.id = ts.team_id
             WHERE t.season_id = ? AND ts.cancelled = 0 AND ts.date <= CURDATE()'
        );
        $stmt->execute([$seasonId]);
        $trainingTotal = (int) $stmt->fetchColumn();

        return [
            'playing_time_minutes' => (int) round((float) $matchRow['minutes']),
            'matches_played'       => (int) $matchRow['matches_played'],
            'goals'                => $goals,
            'assists'              => $assists,
            'training_present'     => $trainingPresent,
            'training_total'       => $trainingTotal,
        ];
    }

    private function clampSkill(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int) $value;
        return ($int >= 1 && $int <= 5) ? $int : null;
    }
}
