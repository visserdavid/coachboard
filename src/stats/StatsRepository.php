<?php
declare(strict_types=1);

class StatsRepository
{
    private PDO $pdo;
    private const COUNTABLE_GOAL_SQL = "me.event_type = 'goal'
        AND NOT (me.scored_via = 'penalty' AND COALESCE(me.penalty_scored, 1) = 0)";

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getSeasonSummary(int $teamId, int $seasonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                COUNT(*) AS matches_played,
                SUM(CASE WHEN goals_scored > goals_conceded THEN 1 ELSE 0 END) AS wins,
                SUM(CASE WHEN goals_scored = goals_conceded THEN 1 ELSE 0 END) AS draws,
                SUM(CASE WHEN goals_scored < goals_conceded THEN 1 ELSE 0 END) AS losses,
                COALESCE(SUM(goals_scored), 0) AS goals_scored,
                COALESCE(SUM(goals_conceded), 0) AS goals_conceded
             FROM `match`
             WHERE team_id = ? AND status = \'finished\' AND deleted_at IS NULL'
        );
        $stmt->execute([$teamId]);
        $row = $stmt->fetch();

        return [
            'matches_played' => (int) ($row['matches_played'] ?? 0),
            'wins'           => (int) ($row['wins'] ?? 0),
            'draws'          => (int) ($row['draws'] ?? 0),
            'losses'         => (int) ($row['losses'] ?? 0),
            'goals_scored'   => (int) ($row['goals_scored'] ?? 0),
            'goals_conceded' => (int) ($row['goals_conceded'] ?? 0),
            'top_scorer'     => $this->getTopScorers($teamId, $seasonId, 1)[0] ?? null,
            'top_assists'    => $this->getTopAssists($teamId, $seasonId, 1)[0] ?? null,
        ];
    }

    public function getPhaseSummary(int $teamId, int $phaseId): array
    {
        $phase = $this->getPhaseById($phaseId);
        if ($phase === null) {
            return $this->emptySummary();
        }

        $stmt = $this->pdo->prepare(
            'SELECT
                COUNT(*) AS matches_played,
                SUM(CASE WHEN goals_scored > goals_conceded THEN 1 ELSE 0 END) AS wins,
                SUM(CASE WHEN goals_scored = goals_conceded THEN 1 ELSE 0 END) AS draws,
                SUM(CASE WHEN goals_scored < goals_conceded THEN 1 ELSE 0 END) AS losses,
                COALESCE(SUM(goals_scored), 0) AS goals_scored,
                COALESCE(SUM(goals_conceded), 0) AS goals_conceded
             FROM `match`
             WHERE team_id = ? AND status = \'finished\' AND deleted_at IS NULL
               AND date BETWEEN ? AND ?'
        );
        $stmt->execute([$teamId, $phase['start_date'], $phase['end_date']]);
        $row = $stmt->fetch();

        $topScorer  = $this->getTopScorersByPhase($teamId, $phaseId, 1)[0] ?? null;
        $topAssists = $this->getTopAssistsByPhase($teamId, $phaseId, 1)[0] ?? null;

        return [
            'matches_played' => (int) ($row['matches_played'] ?? 0),
            'wins'           => (int) ($row['wins'] ?? 0),
            'draws'          => (int) ($row['draws'] ?? 0),
            'losses'         => (int) ($row['losses'] ?? 0),
            'goals_scored'   => (int) ($row['goals_scored'] ?? 0),
            'goals_conceded' => (int) ($row['goals_conceded'] ?? 0),
            'top_scorer'     => $topScorer,
            'top_assists'    => $topAssists,
        ];
    }

    public function getPlayerStats(int $playerId, int $teamId, int $seasonId): array
    {
        $playing = $this->queryPlayingTime(
            'WHERE mp.player_id = ? AND m.team_id = ? AND m.deleted_at IS NULL
              AND m.status IN (\'active\', \'finished\')',
            [$playerId, $teamId]
        );

        $goals   = $this->queryGoals(
            'WHERE me.player_id = ? AND m.team_id = ? AND m.deleted_at IS NULL',
            [$playerId, $teamId]
        );
        $assists = $this->queryAssists(
            'WHERE me.assist_player_id = ? AND m.team_id = ? AND m.deleted_at IS NULL',
            [$playerId, $teamId]
        );

        [$present, $total] = $this->queryTrainingAttendance(
            'WHERE ts.team_id = ? AND ts.cancelled = 0 AND ts.date <= CURDATE()',
            [$teamId],
            $playerId
        );

        $avgRating = $this->queryAverageRating(
            'WHERE mr.player_id = ? AND m.team_id = ? AND m.deleted_at IS NULL',
            [$playerId, $teamId]
        );

        return [
            'playing_time_minutes'    => $playing['minutes'],
            'matches_played'          => $playing['matches'],
            'goals'                   => $goals,
            'assists'                 => $assists,
            'training_attendance_pct' => $total > 0 ? (int) round($present / $total * 100) : 0,
            'average_rating'          => $avgRating,
        ];
    }

    public function getPlayerStatsByPhase(int $playerId, int $teamId, int $phaseId): array
    {
        $phase = $this->getPhaseById($phaseId);
        if ($phase === null) {
            return $this->emptyPlayerStats();
        }

        $playing = $this->queryPlayingTime(
            'WHERE mp.player_id = ? AND m.team_id = ? AND m.deleted_at IS NULL
              AND m.status IN (\'active\', \'finished\')
              AND m.date BETWEEN ? AND ?',
            [$playerId, $teamId, $phase['start_date'], $phase['end_date']]
        );

        $goals = $this->queryGoals(
            'WHERE me.player_id = ? AND m.team_id = ? AND m.deleted_at IS NULL
               AND m.date BETWEEN ? AND ?',
            [$playerId, $teamId, $phase['start_date'], $phase['end_date']]
        );
        $assists = $this->queryAssists(
            'WHERE me.assist_player_id = ? AND m.team_id = ? AND m.deleted_at IS NULL
               AND m.date BETWEEN ? AND ?',
            [$playerId, $teamId, $phase['start_date'], $phase['end_date']]
        );

        [$present, $total] = $this->queryTrainingAttendance(
            'WHERE ts.team_id = ? AND ts.cancelled = 0 AND ts.date <= CURDATE()
               AND ts.date BETWEEN ? AND ?',
            [$teamId, $phase['start_date'], $phase['end_date']],
            $playerId
        );

        $avgRating = $this->queryAverageRating(
            'WHERE mr.player_id = ? AND m.team_id = ? AND m.deleted_at IS NULL
               AND m.date BETWEEN ? AND ?',
            [$playerId, $teamId, $phase['start_date'], $phase['end_date']]
        );

        return [
            'playing_time_minutes'    => $playing['minutes'],
            'matches_played'          => $playing['matches'],
            'goals'                   => $goals,
            'assists'                 => $assists,
            'training_attendance_pct' => $total > 0 ? (int) round($present / $total * 100) : 0,
            'average_rating'          => $avgRating,
        ];
    }

    public function getTopScorers(int $teamId, int $seasonId, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.first_name AS name, COUNT(me.id) AS goals
             FROM match_event me
             JOIN `match` m ON m.id = me.match_id
             JOIN player p ON p.id = me.player_id
             WHERE m.team_id = ? AND ' . self::COUNTABLE_GOAL_SQL . '
               AND m.deleted_at IS NULL AND p.deleted_at IS NULL
             GROUP BY me.player_id, p.first_name
             ORDER BY goals DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$teamId]);
        return $stmt->fetchAll();
    }

    public function getTopAssists(int $teamId, int $seasonId, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.first_name AS name, COUNT(me.id) AS assists
             FROM match_event me
             JOIN `match` m ON m.id = me.match_id
             JOIN player p ON p.id = me.assist_player_id
             WHERE m.team_id = ? AND ' . self::COUNTABLE_GOAL_SQL . '
               AND me.assist_player_id IS NOT NULL
               AND m.deleted_at IS NULL AND p.deleted_at IS NULL
             GROUP BY me.assist_player_id, p.first_name
             ORDER BY assists DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$teamId]);
        return $stmt->fetchAll();
    }

    public function getTopScorersByPhase(int $teamId, int $phaseId, int $limit = 5): array
    {
        $phase = $this->getPhaseById($phaseId);
        if ($phase === null) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT p.first_name AS name, COUNT(me.id) AS goals
             FROM match_event me
             JOIN `match` m ON m.id = me.match_id
             JOIN player p ON p.id = me.player_id
             WHERE m.team_id = ? AND ' . self::COUNTABLE_GOAL_SQL . '
               AND m.deleted_at IS NULL AND p.deleted_at IS NULL
               AND m.date BETWEEN ? AND ?
             GROUP BY me.player_id, p.first_name
             ORDER BY goals DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$teamId, $phase['start_date'], $phase['end_date']]);
        return $stmt->fetchAll();
    }

    public function getTopAssistsByPhase(int $teamId, int $phaseId, int $limit = 5): array
    {
        $phase = $this->getPhaseById($phaseId);
        if ($phase === null) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT p.first_name AS name, COUNT(me.id) AS assists
             FROM match_event me
             JOIN `match` m ON m.id = me.match_id
             JOIN player p ON p.id = me.assist_player_id
             WHERE m.team_id = ? AND ' . self::COUNTABLE_GOAL_SQL . '
               AND me.assist_player_id IS NOT NULL
               AND m.deleted_at IS NULL AND p.deleted_at IS NULL
               AND m.date BETWEEN ? AND ?
             GROUP BY me.assist_player_id, p.first_name
             ORDER BY assists DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$teamId, $phase['start_date'], $phase['end_date']]);
        return $stmt->fetchAll();
    }

    public function getPlayingTimeBalance(int $teamId, int $seasonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                p.id AS player_id,
                p.first_name AS name,
                ROUND(COALESCE(SUM(mp.playing_time_seconds), 0) / 60) AS playing_time_minutes,
                COUNT(CASE WHEN mp.playing_time_seconds > 0 THEN 1 END) AS matches_played
             FROM player p
             LEFT JOIN match_player mp ON mp.player_id = p.id
             LEFT JOIN `match` m ON m.id = mp.match_id
                 AND m.team_id = ? AND m.deleted_at IS NULL
                 AND m.status IN (\'active\', \'finished\')
             WHERE p.team_id = ? AND p.deleted_at IS NULL
             GROUP BY p.id, p.first_name
             ORDER BY playing_time_minutes DESC, p.first_name ASC'
        );
        $stmt->execute([$teamId, $teamId]);

        return array_map(function (array $row): array {
            return [
                'player_id'            => (int) $row['player_id'],
                'name'                 => $row['name'],
                'playing_time_minutes' => (int) $row['playing_time_minutes'],
                'matches_played'       => (int) $row['matches_played'],
            ];
        }, $stmt->fetchAll());
    }

    public function getTrainingAttendanceRanking(int $teamId, int $seasonId): array
    {
        // Total non-cancelled past sessions for this team
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM training_session
             WHERE team_id = ? AND cancelled = 0 AND date <= CURDATE()'
        );
        $stmt->execute([$teamId]);
        $total = (int) $stmt->fetchColumn();

        if ($total === 0) {
            return [];
        }

        // Per-player present count, joined to all players even if no attendance rows
        $stmt = $this->pdo->prepare(
            'SELECT
                p.id AS player_id,
                p.first_name AS name,
                COUNT(a.id) AS present
             FROM player p
             LEFT JOIN attendance a ON a.player_id = p.id
                 AND a.context_type = \'training_session\'
                 AND a.status = \'present\'
                 AND a.context_id IN (
                     SELECT id FROM training_session
                     WHERE team_id = ? AND cancelled = 0 AND date <= CURDATE()
                 )
             WHERE p.team_id = ? AND p.deleted_at IS NULL
             GROUP BY p.id, p.first_name
             ORDER BY present DESC, p.first_name ASC'
        );
        $stmt->execute([$teamId, $teamId]);

        return array_map(function (array $row) use ($total): array {
            $present = (int) $row['present'];
            return [
                'player_id'  => (int) $row['player_id'],
                'name'       => $row['name'],
                'present'    => $present,
                'total'      => $total,
                'percentage' => (int) round($present / $total * 100),
            ];
        }, $stmt->fetchAll());
    }

    // --- Private helpers ---

    private function getPhaseById(int $phaseId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM phase WHERE id = ? LIMIT 1');
        $stmt->execute([$phaseId]);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    private function queryPlayingTime(string $where, array $params): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                ROUND(COALESCE(SUM(mp.playing_time_seconds), 0) / 60) AS minutes,
                COUNT(CASE WHEN mp.playing_time_seconds > 0 THEN 1 END) AS matches
             FROM match_player mp
             JOIN `match` m ON m.id = mp.match_id
             ' . $where
        );
        $stmt->execute($params);
        $row = $stmt->fetch();
        return [
            'minutes' => (int) ($row['minutes'] ?? 0),
            'matches' => (int) ($row['matches'] ?? 0),
        ];
    }

    private function queryGoals(string $where, array $params): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(me.id) FROM match_event me
             JOIN `match` m ON m.id = me.match_id
             ' . $where . ' AND ' . self::COUNTABLE_GOAL_SQL
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function queryAssists(string $where, array $params): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(me.id) FROM match_event me
             JOIN `match` m ON m.id = me.match_id
             ' . $where . ' AND ' . self::COUNTABLE_GOAL_SQL
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Returns [present_count, total_count].
     * $tsWhere filters training_session; $tsParams are bound before player_id.
     */
    private function queryTrainingAttendance(string $tsWhere, array $tsParams, int $playerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM training_session ts ' . $tsWhere
        );
        $stmt->execute($tsParams);
        $total = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM attendance a
             WHERE a.player_id = ? AND a.context_type = \'training_session\'
               AND a.status = \'present\'
               AND a.context_id IN (
                   SELECT id FROM training_session ts ' . $tsWhere . '
               )'
        );
        $stmt->execute(array_merge([$playerId], $tsParams));
        $present = (int) $stmt->fetchColumn();

        return [$present, $total];
    }

    private function queryAverageRating(string $where, array $params): ?int
    {
        // Average the per-row skill average (nulls excluded per skill)
        $stmt = $this->pdo->prepare(
            'SELECT ROUND(AVG(skill_avg)) AS avg_rating, COUNT(*) AS cnt FROM (
                SELECT (
                    COALESCE(mr.pace, 0) + COALESCE(mr.shooting, 0) +
                    COALESCE(mr.passing, 0) + COALESCE(mr.dribbling, 0) +
                    COALESCE(mr.defending, 0) + COALESCE(mr.physicality, 0)
                ) / NULLIF(
                    (mr.pace IS NOT NULL) + (mr.shooting IS NOT NULL) +
                    (mr.passing IS NOT NULL) + (mr.dribbling IS NOT NULL) +
                    (mr.defending IS NOT NULL) + (mr.physicality IS NOT NULL)
                , 0) AS skill_avg
                FROM match_rating mr
                JOIN `match` m ON m.id = mr.match_id
                ' . $where . '
            ) t'
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        if ($row === false || (int) $row['cnt'] === 0 || $row['avg_rating'] === null) {
            return null;
        }

        return (int) $row['avg_rating'];
    }

    private function emptySummary(): array
    {
        return [
            'matches_played' => 0,
            'wins'           => 0,
            'draws'          => 0,
            'losses'         => 0,
            'goals_scored'   => 0,
            'goals_conceded' => 0,
            'top_scorer'     => null,
            'top_assists'    => null,
        ];
    }

    private function emptyPlayerStats(): array
    {
        return [
            'playing_time_minutes'    => 0,
            'matches_played'          => 0,
            'goals'                   => 0,
            'assists'                 => 0,
            'training_attendance_pct' => 0,
            'average_rating'          => null,
        ];
    }
}
