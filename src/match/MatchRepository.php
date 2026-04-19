<?php
declare(strict_types=1);

class MatchRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getMatchesByTeam(int $teamId, ?int $seasonId = null): array
    {
        $sql = 'SELECT m.*,
                       p.id     AS phase_id,
                       p.number AS phase_number,
                       p.label  AS phase_label
                FROM `match` m
                JOIN team t ON t.id = m.team_id
                LEFT JOIN phase p
                    ON p.season_id = t.season_id
                    AND m.date BETWEEN p.start_date AND p.end_date
                WHERE m.team_id = ?
                  AND m.deleted_at IS NULL';
        $params = [$teamId];

        if ($seasonId !== null) {
            $sql     .= ' AND t.season_id = ?';
            $params[] = $seasonId;
        }

        $sql .= ' ORDER BY m.date ASC, m.kick_off_time ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getMatchById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*,
                    p.id     AS phase_id,
                    p.number AS phase_number,
                    p.label  AS phase_label
             FROM `match` m
             JOIN team t ON t.id = m.team_id
             LEFT JOIN phase p
                 ON p.season_id = t.season_id
                 AND m.date BETWEEN p.start_date AND p.end_date
             WHERE m.id = ? AND m.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function createMatch(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `match`
             (team_id, date, kick_off_time, opponent, home_away, match_type, half_duration_minutes, status, livestream_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'planned\', ?)'
        );
        $stmt->execute([
            $data['team_id'],
            $data['date'],
            $data['kick_off_time'] ?: null,
            $data['opponent'],
            $data['home_away'],
            $data['match_type'],
            $data['half_duration_minutes'],
            $data['livestream_token'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateMatch(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `match`
             SET date = ?, kick_off_time = ?, opponent = ?, home_away = ?,
                 match_type = ?, half_duration_minutes = ?, formation_id = ?,
                 goals_scored = ?, goals_conceded = ?, status = ?
             WHERE id = ? AND deleted_at IS NULL'
        );
        return $stmt->execute([
            $data['date']                  ?? null,
            $data['kick_off_time']         ?: null,
            $data['opponent']              ?? null,
            $data['home_away']             ?? null,
            $data['match_type']            ?? null,
            $data['half_duration_minutes'] ?? null,
            $data['formation_id']          ?? null,
            $data['goals_scored']          ?? null,
            $data['goals_conceded']        ?? null,
            $data['status']                ?? null,
            $id,
        ]);
    }

    public function setStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `match` SET status = ? WHERE id = ? AND deleted_at IS NULL'
        );
        return $stmt->execute([$status, $id]);
    }

    public function setFormation(int $id, ?int $formationId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `match` SET formation_id = ? WHERE id = ? AND deleted_at IS NULL'
        );
        return $stmt->execute([$formationId, $id]);
    }

    public function deleteMatch(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `match` SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL'
        );
        return $stmt->execute([$id]);
    }

    public function getMatchPlayers(int $matchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT mp.*,
                    p.first_name, p.squad_number, p.preferred_line
             FROM match_player mp
             LEFT JOIN player p ON p.id = mp.player_id
             WHERE mp.match_id = ?
             ORDER BY mp.in_starting_eleven DESC, p.squad_number IS NULL, p.squad_number ASC, p.first_name ASC'
        );
        $stmt->execute([$matchId]);
        return $stmt->fetchAll();
    }

    public function getMatchPlayerByPlayerId(int $matchId, int $playerId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM match_player WHERE match_id = ? AND player_id = ? LIMIT 1'
        );
        $stmt->execute([$matchId, $playerId]);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function saveMatchPlayer(int $matchId, array $playerData): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO match_player
             (match_id, player_id, is_guest, guest_name, guest_squad_number,
              in_starting_eleven, position_label, pos_x, pos_y)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        return $stmt->execute([
            $matchId,
            $playerData['player_id']          ?? null,
            $playerData['is_guest']           ?? 0,
            $playerData['guest_name']         ?? null,
            $playerData['guest_squad_number'] ?? null,
            $playerData['in_starting_eleven'] ?? 0,
            $playerData['position_label']     ?? null,
            $playerData['pos_x']              ?? null,
            $playerData['pos_y']              ?? null,
        ]);
    }

    public function updateMatchPlayer(int $matchPlayerId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE match_player
             SET in_starting_eleven = ?, position_label = ?, pos_x = ?, pos_y = ?
             WHERE id = ?'
        );
        return $stmt->execute([
            $data['in_starting_eleven'] ?? 0,
            $data['position_label']     ?? null,
            $data['pos_x']              ?? null,
            $data['pos_y']              ?? null,
            $matchPlayerId,
        ]);
    }

    public function removeMatchPlayer(int $matchPlayerId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM match_player WHERE id = ?');
        return $stmt->execute([$matchPlayerId]);
    }

    public function clearMatchPlayers(int $matchId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM match_player WHERE match_id = ?');
        return $stmt->execute([$matchId]);
    }

    public function getPreviousOpponentResult(int $teamId, string $opponent): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `match`
             WHERE team_id = ?
               AND LOWER(opponent) = LOWER(?)
               AND status = \'finished\'
               AND deleted_at IS NULL
             ORDER BY date DESC
             LIMIT 1'
        );
        $stmt->execute([$teamId, $opponent]);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function getRecentMatchesForTemplate(int $teamId, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, date, opponent FROM `match`
             WHERE team_id = ?
               AND status IN (\'prepared\', \'active\', \'finished\')
               AND deleted_at IS NULL
             ORDER BY date DESC
             LIMIT ?'
        );
        $stmt->execute([$teamId, $limit]);
        return $stmt->fetchAll();
    }

    public function setLivestreamToken(int $id, string $token): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `match` SET livestream_token = ? WHERE id = ?'
        );
        return $stmt->execute([$token, $id]);
    }
}
