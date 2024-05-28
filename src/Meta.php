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
    private const JSON_FORMAT = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
    private readonly int $userId;
    private array $meta = [];

    public function __construct(User $user) {
        $this->userId = $user->id;
    }

    public function getMeta(string $prop): mixed {
        global $api;

        if (!$this->userId) {
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
                SQL, $this->userId, $prop)['data'] ?? 'null', true);
        }

        return $this->meta[$prop];
    }

    public function setMeta(string $prop, mixed $data): void {
        global $api;

        if (!$this->userId) {
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
            SQL, $this->userId, $prop, json_encode($data, self::JSON_FORMAT));

        $this->meta[$prop] = $data;
    }

    public function deleteMeta(string $prop): void {
        global $api;

        if (!$this->userId) {
            throw new \Exception('RMS: Cannot set meta data on user. User ID not set');
        }
        
        $api->db->query(<<<SQL
            DELETE FROM 
                `rmsMeta` 
            WHERE 
                `userId` = ? AND `prop` = ?
            SQL, $this->userId, $prop);

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
}
