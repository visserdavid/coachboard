<?php
declare(strict_types=1);

class PlayerService
{
    private PlayerRepository $repo;

    public function __construct()
    {
        $this->repo = new PlayerRepository();
    }

    public function createPlayer(array $data): int
    {
        $this->validatePlayerData($data);
        return $this->repo->createPlayer($data);
    }

    public function updatePlayer(int $id, array $data): bool
    {
        $player = $this->repo->getPlayerById($id);
        if ($player === null) {
            throw new RuntimeException(t('error.not_found'));
        }

        $data['team_id'] = $player['team_id'];
        $this->validatePlayerData($data, $id);
        return $this->repo->updatePlayer($id, $data);
    }

    public function deletePlayer(int $id): bool
    {
        return $this->repo->deletePlayer($id);
    }

    public function uploadPhoto(int $playerId, array $file): bool
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            throw new InvalidArgumentException(t('player.photo_too_large'));
        }

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if ($mimeType !== 'image/jpeg') {
            throw new InvalidArgumentException(t('player.photo_invalid_type'));
        }

        $player = $this->repo->getPlayerById($playerId);
        if ($player === null) {
            return false;
        }

        $filename  = 'player_' . $playerId . '_' . time() . '.jpg';
        $uploadDir = dirname(__DIR__, 2) . '/public/img/players/';
        $dest      = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return false;
        }

        if (!empty($player['photo_path'])) {
            $oldFile = dirname(__DIR__, 2) . '/public/' . $player['photo_path'];
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }

        return $this->repo->updatePhotoPath($playerId, 'img/players/' . $filename);
    }

    public function saveSkills(int $playerId, int $seasonId, array $skills): bool
    {
        $validated = [];
        foreach (['pace', 'shooting', 'passing', 'dribbling', 'defending', 'physicality'] as $skill) {
            $val = $skills[$skill] ?? null;
            if ($val !== null && $val !== '') {
                $int = (int) $val;
                if ($int < 1 || $int > 5) {
                    throw new InvalidArgumentException(t('skill.' . $skill) . ' must be between 1 and 5.');
                }
                $validated[$skill] = $int;
            } else {
                $validated[$skill] = null;
            }
        }
        return $this->repo->saveSkills($playerId, $seasonId, $validated);
    }

    public function copyPlayersToSeason(int $sourceTeamId, int $targetTeamId): int
    {
        $players = $this->repo->getPlayersByTeam($sourceTeamId, false);
        $count   = 0;
        foreach ($players as $p) {
            $this->repo->createPlayer([
                'team_id'        => $targetTeamId,
                'first_name'     => $p['first_name'],
                'squad_number'   => $p['squad_number'],
                'preferred_foot' => $p['preferred_foot'],
                'preferred_line' => $p['preferred_line'],
                'photo_path'     => $p['photo_path'],
                'photo_consent'  => $p['photo_consent'],
            ]);
            $count++;
        }
        return $count;
    }

    private function validatePlayerData(array $data, ?int $excludeId = null): void
    {
        $firstName = trim($data['first_name'] ?? '');
        if ($firstName === '') {
            throw new InvalidArgumentException(t('player.name') . ' ' . t('error.required'));
        }
        if (strlen($firstName) > 100) {
            throw new InvalidArgumentException(t('player.name') . ' is too long (max 100 characters).');
        }

        if (!empty($data['squad_number'])) {
            $num = (int) $data['squad_number'];
            if ($num < 1 || $num > 99) {
                throw new InvalidArgumentException(t('player.squad_number') . ' must be between 1 and 99.');
            }
            if ($this->repo->squadNumberExists((int) $data['team_id'], $num, $excludeId)) {
                throw new InvalidArgumentException(t('player.squad_number_taken'));
            }
        }

        $validFeet = [null, '', 'right', 'left'];
        if (!in_array($data['preferred_foot'] ?? null, $validFeet, true)) {
            throw new InvalidArgumentException('Invalid preferred foot value.');
        }

        $validLines = [null, '', 'goalkeeper', 'defence', 'midfield', 'attack'];
        if (!in_array($data['preferred_line'] ?? null, $validLines, true)) {
            throw new InvalidArgumentException('Invalid preferred line value.');
        }
    }
}
