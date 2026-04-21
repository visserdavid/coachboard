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
        $match = $this->repo->getMatchById($matchId);
        if ($match === null) {
            return false;
        }

        $allowedPlayerIds = array_flip($this->getPresentPlayerIds($matchId, (int) $match['team_id']));
        $players = $this->repo->getMatchPlayers($matchId);
        $starters = [];
        $seenPlayerIds = [];

        foreach ($players as $player) {
            if ((bool) $player['is_guest']) {
                if ((bool) $player['in_starting_eleven']) {
                    $starters[(int) $player['id']] = true;
                }
                continue;
            }

            $playerId = (int) ($player['player_id'] ?? 0);
            if ($playerId <= 0 || !isset($allowedPlayerIds[$playerId]) || isset($seenPlayerIds[$playerId])) {
                return false;
            }
            $seenPlayerIds[$playerId] = true;
            if ((bool) $player['in_starting_eleven']) {
                $starters[(int) $player['id']] = true;
            }
        }

        if (count($starters) !== 11) {
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
        if ($match === null) {
            return '';
        }

        $token = (string) ($match['livestream_token'] ?? '');
        if ($token === '' || strlen($token) < 32) {
            $token = $this->generateLivestreamToken($matchId);
        }

        return APP_URL . '/live.php?token=' . $token;
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
        $match = $this->repo->getMatchById($matchId);
        if ($match === null) {
            return false;
        }

        $allowedPlayerIds = array_flip($this->getPresentPlayerIds($matchId, (int) $match['team_id']));
        $existingPlayers  = [];
        foreach ($this->repo->getMatchPlayers($matchId) as $matchPlayer) {
            $existingPlayers[(int) $matchPlayer['id']] = $matchPlayer;
        }
        $seenPlayerIds = [];

        foreach ($players as $playerData) {
            $matchPlayerId = isset($playerData['match_player_id']) ? (int) $playerData['match_player_id'] : 0;
            $playerId = isset($playerData['player_id']) ? (int) $playerData['player_id'] : 0;

            if ($matchPlayerId > 0 && isset($existingPlayers[$matchPlayerId])) {
                $existing = $existingPlayers[$matchPlayerId];
                if ((bool) $existing['is_guest']) {
                    $this->repo->updateMatchPlayer($matchPlayerId, [
                        'in_starting_eleven' => !empty($playerData['in_starting_eleven']) ? 1 : 0,
                        'position_label'     => $playerData['position_label'] ?? null,
                        'pos_x'              => isset($playerData['pos_x']) && $playerData['pos_x'] !== '' ? (float) $playerData['pos_x'] : null,
                        'pos_y'              => isset($playerData['pos_y']) && $playerData['pos_y'] !== '' ? (float) $playerData['pos_y'] : null,
                    ]);
                    continue;
                }
            }

            if ($playerId <= 0 || !isset($allowedPlayerIds[$playerId]) || isset($seenPlayerIds[$playerId])) {
                continue;
            }
            $seenPlayerIds[$playerId] = true;

            $targetMatchPlayerId = $matchPlayerId;
            if ($targetMatchPlayerId <= 0 || !isset($existingPlayers[$targetMatchPlayerId])) {
                $existing = $this->repo->getMatchPlayerByPlayerId($matchId, $playerId);
                $targetMatchPlayerId = (int) ($existing['id'] ?? 0);
            }
            if ($targetMatchPlayerId <= 0) {
                continue;
            }

            $this->repo->updateMatchPlayer($targetMatchPlayerId, [
                'in_starting_eleven' => !empty($playerData['in_starting_eleven']) ? 1 : 0,
                'position_label'     => $playerData['position_label'] ?? null,
                'pos_x'              => isset($playerData['pos_x']) && $playerData['pos_x'] !== '' ? (float) $playerData['pos_x'] : null,
                'pos_y'              => isset($playerData['pos_y']) && $playerData['pos_y'] !== '' ? (float) $playerData['pos_y'] : null,
            ]);
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
        $this->hydrateEventParticipants($matchId, $data);
        $data['match_id']   = $matchId;
        $data['event_type'] = $data['event_type'] ?? 'goal';
        $data['half']       = $data['half']   ?? $this->getCurrentHalfNumber($matchId);
        $data['minute']     = $data['minute'] ?? $this->getCurrentMinute($matchId);
        return $this->repo->createEvent($data);
    }

    public function registerCard(int $matchId, array $data): int
    {
        $this->hydrateEventParticipants($matchId, $data);
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

        $offPlayer = $this->resolveMatchPlayer($matchId, $data['player_off_match_player_id'] ?? null, $data['player_off_id'] ?? null);
        $onPlayer  = $this->resolveMatchPlayer($matchId, $data['player_on_match_player_id'] ?? null, $data['player_on_id'] ?? null);

        if ($offPlayer === null || $onPlayer === null || (int) $offPlayer['id'] === (int) $onPlayer['id']) {
            return 0;
        }

        $data['player_off_match_player_id'] = (int) $offPlayer['id'];
        $data['player_on_match_player_id']  = (int) $onPlayer['id'];
        $data['player_off_id'] = $offPlayer['player_id'] !== null ? (int) $offPlayer['player_id'] : null;
        $data['player_on_id']  = $onPlayer['player_id'] !== null ? (int) $onPlayer['player_id'] : null;

        // Move incoming player to position of outgoing player
        $this->repo->updatePosition(
            (int) $onPlayer['id'],
            (float) $offPlayer['pos_x'],
            (float) $offPlayer['pos_y'],
            (string) $offPlayer['position_label']
        );
        $this->repo->moveToStartingEleven((int) $onPlayer['id']);
        $this->repo->moveToBench((int) $offPlayer['id']);

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
        $offPlayer  = $this->resolveMatchPlayer($matchId, $sub['player_off_match_player_id'] ?? null, $sub['player_off_id'] ?? null);
        $onPlayer   = $this->resolveMatchPlayer($matchId, $sub['player_on_match_player_id'] ?? null, $sub['player_on_id'] ?? null);

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
            $elapsedSeconds = (int) ($half['elapsed_seconds'] ?? 0);
            if ($half['started_at'] === null && $elapsedSeconds === 0) {
                continue;
            }
            $totalSeconds += $this->getHalfElapsedSeconds($half);
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
            $halfDuration = $this->getHalfElapsedSeconds($half);
            if ($halfDuration <= 0) {
                continue;
            }
            $halfTimelines[(int) $half['number']] = [
                'offset'   => $cumulativeSeconds,
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
            $subSeconds = $halfTimelines[$halfNum]['offset'] + min(
                $halfTimelines[$halfNum]['duration'],
                max(0, ($minute - 1) * 60)
            );
            $subEvents[] = [
                'second'        => $subSeconds,
                'player_off_match_player_id' => (int) ($sub['player_off_match_player_id'] ?? 0),
                'player_on_match_player_id'  => (int) ($sub['player_on_match_player_id'] ?? 0),
            ];
        }

        // Calculate playing time per match_player
        $result = [];
        foreach ($players as $mp) {
            $mpId      = (int) $mp['id'];
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
                    if ($sub['player_on_match_player_id'] === $mpId) {
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
                if ($sub['player_off_match_player_id'] === $mpId) {
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
        $this->repo->clearLivestreamToken($matchId);
        return true;
    }

    public function getScoreFromEvents(int $matchId): array
    {
        $events = $this->repo->getMatchEvents($matchId);
        $scored    = 0;
        $conceded  = 0;
        foreach ($events as $e) {
            if ($e['event_type'] === 'goal') {
                if ($this->isMissedPenalty($e)) {
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
        return bin2hex(random_bytes(32));
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

    private function hydrateEventParticipants(int $matchId, array &$data): void
    {
        if (array_key_exists('match_player_id', $data) || array_key_exists('player_id', $data)) {
            $player = $this->resolveMatchPlayer($matchId, $data['match_player_id'] ?? null, $data['player_id'] ?? null);
            $data['match_player_id'] = $player !== null ? (int) $player['id'] : null;
            $data['player_id'] = ($player !== null && $player['player_id'] !== null) ? (int) $player['player_id'] : null;
        }

        if (array_key_exists('assist_match_player_id', $data) || array_key_exists('assist_player_id', $data)) {
            $assist = $this->resolveMatchPlayer($matchId, $data['assist_match_player_id'] ?? null, $data['assist_player_id'] ?? null);
            $data['assist_match_player_id'] = $assist !== null ? (int) $assist['id'] : null;
            $data['assist_player_id'] = ($assist !== null && $assist['player_id'] !== null) ? (int) $assist['player_id'] : null;
        }
    }

    private function resolveMatchPlayer(int $matchId, mixed $matchPlayerId, mixed $playerId): ?array
    {
        $matchPlayerId = (int) $matchPlayerId;
        if ($matchPlayerId > 0) {
            $matchPlayer = $this->repo->getMatchPlayerById($matchPlayerId);
            if ($matchPlayer !== null && (int) $matchPlayer['match_id'] === $matchId) {
                return $matchPlayer;
            }
        }

        $playerId = (int) $playerId;
        if ($playerId > 0) {
            return $this->repo->getMatchPlayerByPlayerId($matchId, $playerId);
        }

        return null;
    }

    private function getHalfElapsedSeconds(array $half): int
    {
        $elapsedSeconds = (int) ($half['elapsed_seconds'] ?? 0);
        if ($half['started_at'] === null || $half['stopped_at'] !== null) {
            return $elapsedSeconds;
        }

        return $elapsedSeconds + max(0, time() - strtotime((string) $half['started_at']));
    }

    private function isMissedPenalty(array $event): bool
    {
        return ($event['scored_via'] ?? null) === 'penalty' && (int) ($event['penalty_scored'] ?? 1) === 0;
    }
}
