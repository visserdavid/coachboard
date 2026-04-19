<?php
declare(strict_types=1);

class FormationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getAllFormations(): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM formation ORDER BY is_default DESC, name ASC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getFormationById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM formation WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function getPositionsByFormation(int $formationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM formation_position
             WHERE formation_id = ?
             ORDER BY pos_y ASC, pos_x ASC'
        );
        $stmt->execute([$formationId]);
        return $stmt->fetchAll();
    }

    public function getDefaultFormation(): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM formation WHERE is_default = 1 LIMIT 1'
        );
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result !== false) {
            return $result;
        }

        // Fallback to any formation
        $stmt = $this->pdo->prepare('SELECT * FROM formation LIMIT 1');
        $stmt->execute();
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }
}
