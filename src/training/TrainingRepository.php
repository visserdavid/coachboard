<?php
declare(strict_types=1);

class TrainingRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getSessionsByTeam(int $teamId, ?int $seasonId = null): array
    {
        $sql = 'SELECT ts.*,
                       p.id    AS phase_id,
                       p.number AS phase_number,
                       p.label  AS phase_label
                FROM training_session ts
                JOIN team t ON t.id = ts.team_id
                LEFT JOIN phase p
                    ON p.season_id = t.season_id
                    AND ts.date BETWEEN p.start_date AND p.end_date
                WHERE ts.team_id = ?';
        $params = [$teamId];

        if ($seasonId !== null) {
            $sql    .= ' AND t.season_id = ?';
            $params[] = $seasonId;
        }

        $sql .= ' ORDER BY ts.date ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getSessionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ts.*,
                    p.id    AS phase_id,
                    p.number AS phase_number,
                    p.label  AS phase_label
             FROM training_session ts
             JOIN team t ON t.id = ts.team_id
             LEFT JOIN phase p
                 ON p.season_id = t.season_id
                 AND ts.date BETWEEN p.start_date AND p.end_date
             WHERE ts.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function createSession(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_session (team_id, date, cancelled, notes)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['team_id'],
            $data['date'],
            $data['cancelled'] ?? 0,
            $data['notes'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateSession(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE training_session SET notes = ?, cancelled = ? WHERE id = ?'
        );
        return $stmt->execute([
            $data['notes'] ?? null,
            (int) ($data['cancelled'] ?? 0),
            $id,
        ]);
    }

    public function getFocusBySession(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT focus FROM training_focus WHERE training_session_id = ?'
        );
        $stmt->execute([$sessionId]);
        return array_column($stmt->fetchAll(), 'focus');
    }

    public function setFocus(int $sessionId, array $focusValues): bool
    {
        $this->pdo->prepare(
            'DELETE FROM training_focus WHERE training_session_id = ?'
        )->execute([$sessionId]);

        if (empty($focusValues)) {
            return true;
        }

        $valid = ['attacking', 'defending', 'transitioning'];
        $stmt  = $this->pdo->prepare(
            'INSERT INTO training_focus (training_session_id, focus) VALUES (?, ?)'
        );
        foreach ($focusValues as $focus) {
            if (in_array($focus, $valid, true)) {
                $stmt->execute([$sessionId, $focus]);
            }
        }
        return true;
    }

    public function getAttendanceBySession(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, p.first_name, p.squad_number
             FROM attendance a
             JOIN player p ON p.id = a.player_id
             WHERE a.context_type = \'training_session\'
               AND a.context_id = ?
               AND p.deleted_at IS NULL
             ORDER BY p.squad_number IS NULL, p.squad_number ASC, p.first_name ASC'
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll();
    }

    public function getAttendanceByPlayer(int $playerId, int $teamId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, ts.date
             FROM attendance a
             JOIN training_session ts ON ts.id = a.context_id
             WHERE a.player_id = ?
               AND a.context_type = \'training_session\'
               AND ts.team_id = ?
             ORDER BY ts.date ASC'
        );
        $stmt->execute([$playerId, $teamId]);
        return $stmt->fetchAll();
    }

    public function saveAttendance(
        int $playerId,
        string $contextType,
        int $contextId,
        array $data
    ): bool {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM attendance
             WHERE player_id = ? AND context_type = ? AND context_id = ? LIMIT 1'
        );
        $stmt->execute([$playerId, $contextType, $contextId]);
        $existingId = $stmt->fetchColumn();

        if ($existingId !== false) {
            $stmt = $this->pdo->prepare(
                'UPDATE attendance
                 SET status = ?, absence_reason = ?, injury_note = ?
                 WHERE id = ?'
            );
            return $stmt->execute([
                $data['status'],
                $data['absence_reason'] ?? null,
                $data['injury_note'] ?? null,
                $existingId,
            ]);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO attendance
             (player_id, context_type, context_id, status, absence_reason, injury_note)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        return $stmt->execute([
            $playerId,
            $contextType,
            $contextId,
            $data['status'],
            $data['absence_reason'] ?? null,
            $data['injury_note'] ?? null,
        ]);
    }

    public function getRecentAttendance(int $playerId, int $teamId, int $count = 5): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.status
             FROM attendance a
             JOIN training_session ts ON ts.id = a.context_id
             WHERE a.player_id = ?
               AND a.context_type = \'training_session\'
               AND ts.team_id = ?
               AND ts.cancelled = 0
             ORDER BY ts.date DESC
             LIMIT ?'
        );
        $stmt->execute([$playerId, $teamId, $count]);
        $rows = array_column($stmt->fetchAll(), 'status');
        return array_reverse($rows);
    }

    public function getFocusForSessions(array $sessionIds): array
    {
        if (empty($sessionIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT training_session_id, focus
             FROM training_focus
             WHERE training_session_id IN ($placeholders)"
        );
        $stmt->execute(array_values($sessionIds));

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['training_session_id']][] = $row['focus'];
        }
        return $result;
    }

    public function getAttendanceSummariesBySessions(array $sessionIds): array
    {
        if (empty($sessionIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT a.context_id, a.status, COUNT(*) AS cnt
             FROM attendance a
             JOIN player p ON p.id = a.player_id
             WHERE a.context_type = 'training_session'
               AND a.context_id IN ($placeholders)
               AND p.deleted_at IS NULL
             GROUP BY a.context_id, a.status"
        );
        $stmt->execute(array_values($sessionIds));

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $sid = (int) $row['context_id'];
            if (!isset($result[$sid])) {
                $result[$sid] = ['present' => 0, 'absent' => 0, 'injured' => 0];
            }
            $result[$sid][$row['status']] = (int) $row['cnt'];
        }
        return $result;
    }
}
