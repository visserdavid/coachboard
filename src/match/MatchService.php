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
