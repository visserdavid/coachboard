<?php
declare(strict_types=1);

class StaffRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getAllStaff(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `user` ORDER BY active DESC, first_name ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getStaffById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `user` WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function createStaff(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO `user`
             (first_name, email, is_administrator, is_trainer, is_coach, is_assistant, active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())'
        );
        $stmt->execute([
            $data['first_name'],
            $data['email'],
            $data['is_administrator'] ? 1 : 0,
            $data['is_trainer']       ? 1 : 0,
            $data['is_coach']         ? 1 : 0,
            $data['is_assistant']     ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStaff(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `user`
             SET first_name = ?, email = ?,
                 is_administrator = ?, is_trainer = ?, is_coach = ?, is_assistant = ?,
                 updated_at = NOW()
             WHERE id = ?'
        );
        return $stmt->execute([
            $data['first_name'],
            $data['email'],
            $data['is_administrator'] ? 1 : 0,
            $data['is_trainer']       ? 1 : 0,
            $data['is_coach']         ? 1 : 0,
            $data['is_assistant']     ? 1 : 0,
            $id,
        ]);
    }

    public function deactivateStaff(int $id): bool
    {
        if ($this->isLastAdministrator($id)) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE `user` SET active = 0, updated_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    public function reactivateStaff(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE `user` SET active = 1, updated_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    public function isLastAdministrator(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM `user` WHERE is_administrator = 1 AND active = 1'
        );
        $stmt->execute();
        $count = (int) $stmt->fetchColumn();

        if ($count > 1) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT is_administrator FROM `user` WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        return $user !== false && (int) $user['is_administrator'] === 1;
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM `user` WHERE email = ? AND id != ?'
            );
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM `user` WHERE email = ?'
            );
            $stmt->execute([$email]);
        }
        return (int) $stmt->fetchColumn() > 0;
    }
}
