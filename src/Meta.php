<?php

declare(strict_types=1);

namespace Zolinga\Rms;
use ArrayAccess;

/**
 * Class to access RMS User's meta data.
 * 
 * Meta data are accessible as ordinary array elements. They get 
 * loaded/saved from/to the database on demand.
 * 
 * E.g. 
 *  $api->user->meta['name'] = 'John Doe';
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-05-28
 */
class Meta implements ArrayAccess {
    private User $user;
    private const JSON_FORMAT = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
    private array $meta = [];

    public function __construct(User $user) {
        $this->user = $user;
    }

    public function __get(string $name): void {
        throw new \Exception('RMS: Cannot access meta data directly. Use array access');
    }

    public function __set(string $name, mixed $value): void {
        throw new \Exception('RMS: Cannot set meta data directly. Use array access');
    }

    public function getMeta(string $prop): mixed {
        global $api;

        if (!$this->user->id) {
            return null;
        }

        if (!array_key_exists($prop, $this->meta)) {
            $this->meta[$prop] = json_decode($api->db->query(<<<SQL
                SELECT 
                    `data` 
                FROM 
                    `rmsMeta` 
                WHERE 
                    `userId` = ? AND `prop` = ?
                SQL, $this->user->id, $prop)['data'] ?? 'null', true);
        }

        return $this->meta[$prop];
    }

    public function setMeta(string $prop, mixed $data): void {
        global $api;

        if (!$this->user->id) {
            throw new \Exception('RMS: Cannot set meta data on user. User ID not set');
        }

        if ($data === null) {
            $this->deleteMeta($prop);
            return;
        }

        $api->db->query(<<<SQL
            INSERT INTO 
                `rmsMeta` 
            SET 
                `userId` = ?, `prop` = ?, `data` = ?
            ON DUPLICATE KEY UPDATE 
                `data` = VALUES(`data`)
            SQL, $this->user->id, $prop, json_encode($data, self::JSON_FORMAT));

        $this->meta[$prop] = $data;
    }

    public function deleteMeta(string $prop): void {
        global $api;

        if (!$this->user->id) {
            throw new \Exception('RMS: Cannot set meta data on user. User ID not set');
        }
        
        $api->db->query(<<<SQL
            DELETE FROM 
                `rmsMeta` 
            WHERE 
                `userId` = ? AND `prop` = ?
            SQL, $this->user->id, $prop);

        $this->meta[$prop] = null;
    }

    public function offsetExists($offset): bool {
        return $this->getMeta($offset) !== null;
    }

    public function offsetGet($offset): mixed {
        return $this->getMeta($offset);
    }

    public function offsetSet($offset, $value): void {
        $this->setMeta($offset, $value);
    }

    public function offsetUnset($offset): void {
        $this->deleteMeta($offset);
    }

    /**
     * Search for users by meta data.
     * 
     * @param string $key The meta key to search for.
     * @param mixed $value The value of the meta key to search for.
     * @param int|null $limit The maximum number of results to return. Default is null (no limit).
     * @return array<User> Array of User objects matching the search criteria.
     */
    public static function search(string $key, mixed $value, ?int $limit = null): array {
        global $api;

        $q=<<<SQL
            SELECT 
                userId 
            FROM 
                rmsMeta
            WHERE 
                prop = ? AND data = ?
            SQL;
        $params = [$key, json_encode($value, self::JSON_FORMAT)];

        if ($limit !== null) {
            $q .= ' LIMIT ?';
            $params[] = $limit;
        }

        $ids = $api->db->query($q, ...$params)->fetchFirstColumnAll();

        return array_map(
            fn($id) => User::getUser($id),
            $ids
        );
    }
}
