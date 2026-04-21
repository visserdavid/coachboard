<?php
declare(strict_types=1);

class TrainingService
{
    private TrainingRepository $repo;
    private PDO $pdo;

    public function __construct()
    {
        $this->repo = new TrainingRepository();
        $this->pdo  = Database::getInstance()->getConnection();
    }

    public function cancelSession(int $sessionId): bool
    {
        $session = $this->repo->getSessionById($sessionId);
        if ($session === null) {
            return false;
        }
        return $this->repo->updateSession($sessionId, [
            'notes'     => $session['notes'],
            'cancelled' => 1,
        ]);
    }

    public function reinstateSession(int $sessionId): bool
    {
        $session = $this->repo->getSessionById($sessionId);
        if ($session === null) {
            return false;
        }
        return $this->repo->updateSession($sessionId, [
            'notes'     => $session['notes'],
            'cancelled' => 0,
        ]);
    }

    public function addManualSession(int $teamId, string $date): int
    {
        return $this->repo->createSession([
            'team_id' => $teamId,
            'date'    => $date,
        ]);
    }

    public function saveSessionContent(int $sessionId, array $focus, ?string $notes): bool
    {
        $session = $this->repo->getSessionById($sessionId);
        if ($session === null) {
            return false;
        }
        $this->repo->setFocus($sessionId, $focus);
        return $this->repo->updateSession($sessionId, [
            'notes'     => $notes,
            'cancelled' => (int) $session['cancelled'],
        ]);
    }

    public function saveAttendance(int $sessionId, array $attendance): bool
    {
        $session = $this->repo->getSessionById($sessionId);
        if ($session === null) {
            return false;
        }

        $allowedPlayerIds = $this->getActivePlayerIdsByTeam((int) $session['team_id']);
        foreach ($attendance as $playerId => $data) {
            $playerId = (int) $playerId;
            if (!isset($allowedPlayerIds[$playerId])) {
                continue;
            }

            $this->repo->saveAttendance(
                $playerId,
                'training_session',
                $sessionId,
                $data
            );
        }
        return true;
    }

    public function getAttendanceSummary(int $sessionId): array
    {
        $records = $this->repo->getAttendanceBySession($sessionId);
        $summary = ['present' => 0, 'absent' => 0, 'injured' => 0, 'total' => 0];
        foreach ($records as $record) {
            if (isset($summary[$record['status']])) {
                $summary[$record['status']]++;
            }
            $summary['total']++;
        }
        return $summary;
    }

    public function getRecentAttendanceForLineup(int $teamId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM training_session
             WHERE team_id = ? AND cancelled = 0
             ORDER BY date DESC LIMIT 5'
        );
        $stmt->execute([$teamId]);
        $sessionIds = array_column($stmt->fetchAll(), 'id');

        if (empty($sessionIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT a.player_id, a.status, ts.date
             FROM attendance a
             JOIN training_session ts ON ts.id = a.context_id
             WHERE a.context_type = 'training_session'
               AND a.context_id IN ($placeholders)
             ORDER BY ts.date ASC"
        );
        $stmt->execute(array_values($sessionIds));

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['player_id']][] = $row['status'];
        }
        return $result;
    }

    private function getActivePlayerIdsByTeam(int $teamId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM player WHERE team_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$teamId]);

        return array_fill_keys(array_map('intval', array_column($stmt->fetchAll(), 'id')), true);
    }
}
