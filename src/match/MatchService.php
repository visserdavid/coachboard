<?php
declare(strict_types=1);

class MatchService
{
    private MatchRepository $repo;
    private FormationRepository $formationRepo;

    public function __construct()
    {
        $this->repo          = new MatchRepository();
        $this->formationRepo = new FormationRepository();
    }

    public function createMatch(array $data): int
    {
        $errors = $this->validateMatch($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(', ', $errors));
        }

        $data['opponent']         = $this->normaliseOpponent($data['opponent']);
        $data['livestream_token'] = $this->generateToken();

        return $this->repo->createMatch($data);
    }

    public function updateMatch(int $id, array $data): bool
    {
        if (isset($data['opponent'])) {
            $data['opponent'] = $this->normaliseOpponent($data['opponent']);
        }
        return $this->repo->updateMatch($id, $data);
    }

    public function deleteMatch(int $id): bool
    {
        return $this->repo->deleteMatch($id);
    }

    public function setStatus(int $matchId, string $status): bool
    {
        $valid = ['planned', 'prepared', 'active', 'finished'];
        if (!in_array($status, $valid, true)) {
            return false;
        }
        return $this->repo->setStatus($matchId, $status);
    }

    public function confirmPreparation(int $matchId): bool
    {
        $players = $this->repo->getMatchPlayers($matchId);
        $starters = array_filter($players, fn($p) => (bool) $p['in_starting_eleven']);
        if (count($starters) < 11) {
            return false;
        }
        return $this->repo->setStatus($matchId, 'prepared');
    }

    public function generateLivestreamToken(int $matchId): string
    {
        $token = $this->generateToken();
        $this->repo->setLivestreamToken($matchId, $token);
        return $token;
    }

    public function getLivestreamUrl(int $matchId): string
    {
        $match = $this->repo->getMatchById($matchId);
        if ($match === null || empty($match['livestream_token'])) {
            return '';
        }
        return APP_URL . '/live.php?token=' . $match['livestream_token'];
    }

    public function loadLineupFromTemplate(int $matchId, int $templateMatchId): bool
    {
        $match          = $this->repo->getMatchById($matchId);
        $templatePlayers = $this->repo->getMatchPlayers($templateMatchId);

        if ($match === null || empty($templatePlayers)) {
            return false;
        }

        // Get present player IDs for current match from attendance
        $presentPlayerIds = $this->getPresentPlayerIds($matchId, (int) $match['team_id']);

        // Remove existing lineup for current match (keep attendance records)
        $this->repo->clearMatchPlayers($matchId);

        foreach ($templatePlayers as $tp) {
            if ((bool) $tp['is_guest']) {
                continue;
            }
            $playerId = (int) $tp['player_id'];
            if (!in_array($playerId, $presentPlayerIds, true)) {
                continue;
            }
            $this->repo->saveMatchPlayer($matchId, [
                'player_id'          => $playerId,
                'is_guest'           => 0,
                'in_starting_eleven' => $tp['in_starting_eleven'],
                'position_label'     => $tp['position_label'],
                'pos_x'              => $tp['pos_x'],
                'pos_y'              => $tp['pos_y'],
            ]);
        }

        // Add remaining present players to bench
        $existing = array_column($this->repo->getMatchPlayers($matchId), 'player_id');
        foreach ($presentPlayerIds as $pid) {
            if (!in_array((string) $pid, $existing, false) && !in_array($pid, array_map('intval', $existing), true)) {
                $this->repo->saveMatchPlayer($matchId, [
                    'player_id'          => $pid,
                    'is_guest'           => 0,
                    'in_starting_eleven' => 0,
                ]);
            }
        }

        return true;
    }

    public function saveLineup(int $matchId, array $players): bool
    {
        $this->repo->clearMatchPlayers($matchId);
        foreach ($players as $playerData) {
            $this->repo->saveMatchPlayer($matchId, $playerData);
        }
        return true;
    }

    public function addGuestPlayer(int $matchId, array $guestData): bool
    {
        return $this->repo->saveMatchPlayer($matchId, [
            'player_id'          => null,
            'is_guest'           => 1,
            'guest_name'         => trim($guestData['guest_name'] ?? ''),
            'guest_squad_number' => isset($guestData['guest_squad_number']) ? (int) $guestData['guest_squad_number'] : null,
            'in_starting_eleven' => 0,
        ]);
    }

    public function removeGuestPlayer(int $matchPlayerId): bool
    {
        return $this->repo->removeMatchPlayer($matchPlayerId);
    }

    public function ensureAllPresentPlayersInRoster(int $matchId, int $teamId): void
    {
        $presentIds = $this->getPresentPlayerIds($matchId, $teamId);
        $existing   = array_map('intval', array_column($this->repo->getMatchPlayers($matchId), 'player_id'));

        foreach ($presentIds as $pid) {
            if (!in_array($pid, $existing, true)) {
                $this->repo->saveMatchPlayer($matchId, [
                    'player_id'          => $pid,
                    'is_guest'           => 0,
                    'in_starting_eleven' => 0,
                ]);
            }
        }
    }

    private function getPresentPlayerIds(int $matchId, int $teamId): array
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            "SELECT DISTINCT a.player_id
             FROM attendance a
             JOIN player p ON p.id = a.player_id
             WHERE a.context_type = 'match'
               AND a.context_id = ?
               AND a.status = 'present'
               AND p.team_id = ?
               AND p.deleted_at IS NULL"
        );
        $stmt->execute([$matchId, $teamId]);
        return array_map('intval', array_column($stmt->fetchAll(), 'player_id'));
    }

    // -------------------------------------------------------------------------
    // Live match: halves
    // -------------------------------------------------------------------------

    public function startHalf(int $matchId, int $halfNumber): bool
    {
        $half = $this->repo->getHalfByNumber($matchId, $halfNumber);

        if ($half === null) {
            $halfId = $this->repo->createHalf($matchId, $halfNumber);
        } else {
            if ($half['started_at'] !== null && $half['stopped_at'] === null) {
                return false; // already running
            }
            $halfId = (int) $half['id'];
        }

        $this->repo->startHalf($halfId);
        $this->repo->setStatus($matchId, 'active');
        return true;
    }

    public function stopHalf(int $matchId, int $halfNumber): bool
    {
        $half = $this->repo->getHalfByNumber($matchId, $halfNumber);
        if ($half === null || $half['started_at'] === null || $half['stopped_at'] !== null) {
            return false;
        }
        return $this->repo->stopHalf((int) $half['id']);
    }

    public function resumeHalf(int $matchId, int $halfNumber): bool
    {
        $half = $this->repo->getHalfByNumber($matchId, $halfNumber);
        if ($half === null || $half['stopped_at'] === null) {
            return false;
        }
        return $this->repo->resumeHalf((int) $half['id']);
    }

    // -------------------------------------------------------------------------
    // Live match: events
    // -------------------------------------------------------------------------

    public function registerGoal(int $matchId, array $data): int
    {
        $data['match_id']   = $matchId;
        $data['event_type'] = $data['event_type'] ?? 'goal';
        $data['half']       = $data['half']   ?? $this->getCurrentHalfNumber($matchId);
        $data['minute']     = $data['minute'] ?? $this->getCurrentMinute($matchId);
        return $this->repo->createEvent($data);
    }

    public function registerCard(int $matchId, array $data): int
    {
        $data['match_id'] = $matchId;
        $data['half']     = $data['half']   ?? $this->getCurrentHalfNumber($matchId);
        $data['minute']   = $data['minute'] ?? $this->getCurrentMinute($matchId);
        return $this->repo->createEvent($data);
    }

    public function registerNote(int $matchId, array $data): int
    {
        $data['match_id']   = $matchId;
        $data['event_type'] = 'note';
        $data['half']       = $data['half']   ?? $this->getCurrentHalfNumber($matchId);
        $data['minute']     = $data['minute'] ?? $this->getCurrentMinute($matchId);
        return $this->repo->createEvent($data);
    }

    public function deleteEvent(int $eventId): bool
    {
        return $this->repo->deleteEvent($eventId);
    }

    // -------------------------------------------------------------------------
    // Live match: substitutions
    // -------------------------------------------------------------------------

    public function registerSubstitution(int $matchId, array $data): int
    {
        $data['match_id'] = $matchId;
        $data['half']     = $data['half']   ?? $this->getCurrentHalfNumber($matchId);
        $data['minute']   = $data['minute'] ?? $this->getCurrentMinute($matchId);

        // Move players on the pitch
        $offPlayer = $this->repo->getMatchPlayerByPlayerId($matchId, (int) $data['player_off_id']);
        $onPlayer  = $this->repo->getMatchPlayerByPlayerId($matchId, (int) $data['player_on_id']);

        if ($offPlayer !== null && $onPlayer !== null) {
            // Move incoming player to position of outgoing player
            $this->repo->updatePosition(
                (int) $onPlayer['id'],
                (float) $offPlayer['pos_x'],
                (float) $offPlayer['pos_y'],
                (string) $offPlayer['position_label']
            );
            $this->repo->moveToStartingEleven((int) $onPlayer['id']);
            $this->repo->moveToBench((int) $offPlayer['id']);
        }

        return $this->repo->createSubstitution($data);
    }

    public function undoSubstitution(int $substitutionId): bool
    {
        $pdo  = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare('SELECT * FROM substitution WHERE id = ? LIMIT 1');
        $stmt->execute([$substitutionId]);
        $sub = $stmt->fetch();
        if (!$sub) {
            return false;
        }

        $matchId    = (int) $sub['match_id'];
        $offPlayer  = $this->repo->getMatchPlayerByPlayerId($matchId, (int) $sub['player_off_id']);
        $onPlayer   = $this->repo->getMatchPlayerByPlayerId($matchId, (int) $sub['player_on_id']);

        if ($offPlayer !== null && $onPlayer !== null) {
            // Restore outgoing player to the field, send incoming player back to bench
            $this->repo->updatePosition(
                (int) $offPlayer['id'],
                (float) $onPlayer['pos_x'],
                (float) $onPlayer['pos_y'],
                (string) $onPlayer['position_label']
            );
            $this->repo->moveToStartingEleven((int) $offPlayer['id']);
            $this->repo->moveToBench((int) $onPlayer['id']);
        }

        return $this->repo->deleteSubstitution($substitutionId);
    }

    // -------------------------------------------------------------------------
    // Live match: time calculations
    // -------------------------------------------------------------------------

    public function getCurrentMinute(int $matchId): int
    {
        $halves = $this->repo->getMatchHalves($matchId);
        $totalSeconds = 0;

        foreach ($halves as $half) {
            if ($half['started_at'] === null) {
                continue;
            }
            $start = strtotime($half['started_at']);
            $end   = $half['stopped_at'] !== null ? strtotime($half['stopped_at']) : time();
            $totalSeconds += max(0, $end - $start);
        }

        return (int) floor($totalSeconds / 60) + 1;
    }

    public function getCurrentHalfNumber(int $matchId): int
    {
        $halves = $this->repo->getMatchHalves($matchId);
        foreach (array_reverse($halves) as $half) {
            if ($half['started_at'] !== null) {
                return (int) $half['number'];
            }
        }
        return 1;
    }

    public function calculatePlayingTime(int $matchId): array
    {
        $halves  = $this->repo->getMatchHalves($matchId);
        $players = $this->repo->getMatchPlayers($matchId);
        $subs    = $this->repo->getSubstitutions($matchId);

        // Build timeline: half start/stop times in seconds from match start
        $halfTimelines = [];
        $cumulativeSeconds = 0;
        foreach ($halves as $half) {
            if ($half['started_at'] === null) {
                continue;
            }
            $halfStart    = strtotime($half['started_at']);
            $halfEnd      = $half['stopped_at'] !== null ? strtotime($half['stopped_at']) : time();
            $halfDuration = max(0, $halfEnd - $halfStart);
            $halfTimelines[(int) $half['number']] = [
                'offset'   => $cumulativeSeconds, // seconds from match start when this half began
                'start_ts' => $halfStart,
                'end_ts'   => $halfEnd,
                'duration' => $halfDuration,
            ];
            $cumulativeSeconds += $halfDuration;
        }

        $totalMatchSeconds = $cumulativeSeconds;

        // Build substitution events: match-second when each sub occurred
        $subEvents = [];
        foreach ($subs as $sub) {
            $halfNum = (int) $sub['half'];
            $minute  = (int) $sub['minute'];
            if (!isset($halfTimelines[$halfNum])) {
                continue;
            }
            // Approximate seconds from match start = half offset + (minute - 1) * 60
            $subSeconds = $halfTimelines[$halfNum]['offset'] + max(0, ($minute - 1) * 60);
            $subEvents[] = [
                'second'        => $subSeconds,
                'player_off_id' => (int) $sub['player_off_id'],
                'player_on_id'  => (int) $sub['player_on_id'],
            ];
        }

        // Calculate playing time per match_player
        $result = [];
        foreach ($players as $mp) {
            $mpId      = (int) $mp['id'];
            $playerId  = isset($mp['player_id']) ? (int) $mp['player_id'] : null;
            $isStarter = (bool) $mp['in_starting_eleven'];

            if ($totalMatchSeconds === 0) {
                $result[$mpId] = 0;
                continue;
            }

            if ($isStarter) {
                $onSecond  = 0;
                $offSecond = $totalMatchSeconds;
            } else {
                // Bench player — check if they came on
                $onSecond  = null;
                $offSecond = $totalMatchSeconds;
                foreach ($subEvents as $sub) {
                    if ($playerId !== null && $sub['player_on_id'] === $playerId) {
                        $onSecond = $sub['second'];
                        break;
                    }
                }
                if ($onSecond === null) {
                    $result[$mpId] = 0;
                    continue;
                }
            }

            // Check if this player was substituted off
            foreach ($subEvents as $sub) {
                if ($playerId !== null && $sub['player_off_id'] === $playerId) {
                    if ($sub['second'] >= ($onSecond ?? 0)) {
                        $offSecond = $sub['second'];
                        break;
                    }
                }
            }

            $result[$mpId] = max(0, $offSecond - ($onSecond ?? 0));
        }

        return $result;
    }

    public function closeMatch(int $matchId, int $goalsScored, int $goalsConceded): bool
    {
        $match = $this->repo->getMatchById($matchId);
        if ($match === null || $match['status'] !== 'active') {
            return false;
        }

        // Calculate and save playing time for all players
        $playingTimes = $this->calculatePlayingTime($matchId);
        foreach ($playingTimes as $matchPlayerId => $seconds) {
            $this->repo->updatePlayingTime($matchPlayerId, $seconds);
        }

        $this->repo->setScore($matchId, $goalsScored, $goalsConceded);
        $this->repo->setStatus($matchId, 'finished');
        return true;
    }

    public function getScoreFromEvents(int $matchId): array
    {
        $events = $this->repo->getMatchEvents($matchId);
        $scored    = 0;
        $conceded  = 0;
        foreach ($events as $e) {
            if ($e['event_type'] === 'goal') {
                if ($e['scored_via'] === 'penalty' && $e['penalty_scored'] == 0) {
                    continue; // missed penalty
                }
                $scored++;
            } elseif ($e['event_type'] === 'own_goal') {
                $conceded++;
            }
        }
        return ['scored' => $scored, 'conceded' => $conceded];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function normaliseOpponent(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return $name;
        }
        return mb_strtoupper(mb_substr($name, 0, 1)) . mb_strtolower(mb_substr($name, 1));
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function validateMatch(array $data): array
    {
        $errors = [];

        if (empty($data['date']) || !strtotime($data['date'])) {
            $errors[] = 'date ' . t('error.required');
        }

        if (empty($data['opponent']) || mb_strlen($data['opponent']) > 150) {
            $errors[] = 'opponent ' . t('error.required');
        }

        if (!in_array($data['home_away'] ?? '', ['home', 'away'], true)) {
            $errors[] = 'home_away ' . t('error.required');
        }

        if (!in_array($data['match_type'] ?? '', ['league', 'tournament', 'friendly'], true)) {
            $errors[] = 'match_type ' . t('error.required');
        }

        $duration = (int) ($data['half_duration_minutes'] ?? 0);
        if ($duration < 20 || $duration > 60) {
            $errors[] = 'half_duration_minutes ' . t('error.required');
        }

        return $errors;
    }
}
