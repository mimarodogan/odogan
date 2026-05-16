<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Newsletter / sidebar / below-post sponsor slotu (Tier 9).
 *
 * Tarih aralığı + placement bazında aktif olanları döner; ağırlığa göre
 * rastgele seçim yapılır (weight RNG).
 */
final class SponsorSlot
{
    public static function findById(int $id): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM sponsor_slots WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
    }

    public static function all(int $limit = 200): array
    {
        return Database::instance()->fetchAll(
            'SELECT * FROM sponsor_slots ORDER BY active DESC, updated_at DESC LIMIT ' . max(1, $limit)
        );
    }

    public static function create(array $data): int
    {
        return (int) Database::instance()->insert('sponsor_slots', $data);
    }

    public static function update(int $id, array $data): int
    {
        return Database::instance()->update('sponsor_slots', $data, 'id = :wid', [':wid' => $id]);
    }

    public static function delete(int $id): int
    {
        return Database::instance()->delete('sponsor_slots', 'id = :wid', [':wid' => $id]);
    }

    /**
     * Bir yerleşim için aktif slotları döner (tarih aralığı geçerli).
     */
    public static function activeFor(string $placement): array
    {
        $now = date('Y-m-d H:i:s');
        return Database::instance()->fetchAll(
            "SELECT * FROM sponsor_slots
             WHERE active = 1
               AND placement = :p
               AND (starts_at IS NULL OR starts_at <= :n1)
               AND (ends_at IS NULL OR ends_at >= :n2)
             ORDER BY weight DESC, RAND() LIMIT 10",
            [':p' => $placement, ':n1' => $now, ':n2' => $now]
        );
    }

    /**
     * Ağırlığa göre tek slot seç.
     */
    public static function pickFor(string $placement): ?array
    {
        $items = self::activeFor($placement);
        if (empty($items)) return null;
        $total = 0;
        foreach ($items as $it) { $total += max(1, (int) $it['weight']); }
        $r = random_int(1, $total);
        $acc = 0;
        foreach ($items as $it) {
            $acc += max(1, (int) $it['weight']);
            if ($r <= $acc) {
                self::bumpView((int) $it['id']);
                return $it;
            }
        }
        return $items[0] ?? null;
    }

    public static function bumpView(int $id): void
    {
        Database::instance()->run(
            'UPDATE sponsor_slots SET view_count = view_count + 1 WHERE id = :id',
            [':id' => $id]
        );
    }

    public static function bumpClick(int $id): void
    {
        Database::instance()->run(
            'UPDATE sponsor_slots SET click_count = click_count + 1 WHERE id = :id',
            [':id' => $id]
        );
    }
}
