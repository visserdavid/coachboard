<?php
declare(strict_types=1);

class FormationService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function createFormation(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO formation (name, outfield_players, is_default)
             VALUES (?, ?, 0)'
        );
        $stmt->execute([
            $data['name'],
            (int) $data['outfield_players'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateFormation(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE formation SET name = ?, outfield_players = ? WHERE id = ?'
        );
        return $stmt->execute([
            $data['name'],
            (int) $data['outfield_players'],
            $id,
        ]);
    }

    public function deleteFormation(int $id): bool
    {
        // Refuse if default
        $stmt = $this->pdo->prepare('SELECT is_default FROM formation WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return false;
        }
        if ((int) $row['is_default'] === 1) {
            return false;
        }

        // Refuse if used in any match
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM `match` WHERE formation_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return false;
        }

        $this->pdo->prepare('DELETE FROM formation_position WHERE formation_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM formation WHERE id = ?')->execute([$id]);
        return true;
    }

    public function setDefault(int $id): bool
    {
        $this->pdo->prepare('UPDATE formation SET is_default = 0')->execute();
        $stmt = $this->pdo->prepare('UPDATE formation SET is_default = 1 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function savePositions(int $formationId, array $positions): bool
    {
        $this->pdo->prepare(
            'DELETE FROM formation_position WHERE formation_id = ?'
        )->execute([$formationId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO formation_position (formation_id, position_label, line, pos_x, pos_y)
             VALUES (?, ?, ?, ?, ?)'
        );

        $validLines = ['goalkeeper', 'defence', 'midfield', 'attack'];

        foreach ($positions as $pos) {
            $label = substr(trim((string) ($pos['position_label'] ?? '')), 0, 50);
            $line  = (string) ($pos['line'] ?? '');
            $posX  = max(0.0, min(100.0, (float) ($pos['pos_x'] ?? 50)));
            $posY  = max(0.0, min(100.0, (float) ($pos['pos_y'] ?? 50)));

            if ($label === '' || !in_array($line, $validLines, true)) {
                continue;
            }

            $stmt->execute([$formationId, $label, $line, $posX, $posY]);
        }

        return true;
    }

    public function isDeleteable(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT is_default FROM formation WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return ['ok' => false, 'reason' => 'not_found'];
        }
        if ((int) $row['is_default'] === 1) {
            return ['ok' => false, 'reason' => 'default'];
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM `match` WHERE formation_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['ok' => false, 'reason' => 'in_use'];
        }
        return ['ok' => true, 'reason' => ''];
    }
}
