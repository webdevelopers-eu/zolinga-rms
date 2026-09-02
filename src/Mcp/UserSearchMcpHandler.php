<?php

declare(strict_types=1);

namespace Zolinga\Rms\Mcp;

use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Events\Mcp\Tools\CallEvent;

/**
 * MCP tool for searching RMS users.
 *
 * Returns an array of user records matching the given criteria. All criteria
 * are optional; when none are provided all users are returned (up to the limit).
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-09-03
 */
final class UserSearchMcpHandler implements ListenerInterface
{
    private const DEFAULT_LIMIT = 100;
    private const MAX_LIMIT = 1000;

    /**
     * Handle the "userSearch" MCP tool call.
     *
     * @param CallEvent $event
     * @return void
     */
    public function onUserSearch(CallEvent $event): void
    {
        global $api;

        $req = $event->request;

        $where = [];
        $params = [];

        // username - LIKE search
        $username = $req['username'] ?? null;
        if (is_string($username) && $username !== '') {
            $where[] = 'username LIKE ?';
            $params[] = '%' . $username . '%';
        }

        // id - exact match
        $id = $req['id'] ?? null;
        if ($id !== null) {
            $id = is_int($id) ? $id : (ctype_digit((string) $id) ? (int) $id : null);
            if ($id === null) {
                $event->setStatus($event::STATUS_BAD_REQUEST, 'Invalid "id". Expected an integer.');
                return;
            }
            $where[] = 'id = ?';
            $params[] = $id;
        }

        // lastLoginFrom - exact match
        $lastLoginFrom = $req['lastLoginFrom'] ?? null;
        if (is_string($lastLoginFrom) && $lastLoginFrom !== '') {
            $where[] = 'lastLoginFrom = ?';
            $params[] = $lastLoginFrom;
        }

        $this->addRange($where, $params, 'created', $req['createdFrom'] ?? null, $req['createdTo'] ?? null, $event);
        $this->addRange($where, $params, 'modified', $req['modifiedFrom'] ?? null, $req['modifiedTo'] ?? null, $event);
        $this->addRange($where, $params, 'lastLogin', $req['lastLoginAfter'] ?? null, $req['lastLoginBefore'] ?? null, $event);

        // limit
        $limit = $req['limit'] ?? self::DEFAULT_LIMIT;
        $limit = is_int($limit) ? $limit : (ctype_digit((string) $limit) ? (int) $limit : self::DEFAULT_LIMIT);
        $limit = max(1, min($limit, self::MAX_LIMIT));

        $sql = 'SELECT id, username, removed, canLogin, created, modified, lastLogin, lastLoginFrom '
            . 'FROM rmsUsers';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id ASC LIMIT ' . $limit;

        $rows = $api->db->query($sql, ...$params)->fetchAll();

        $event->response['data'] = array_map(fn (array $row): array => [
            'id' => (int) $row['id'],
            'username' => $row['username'],
            'removed' => (int) $row['removed'],
            'canLogin' => (bool) $row['canLogin'],
            'created' => $row['created'] !== null ? (int) $row['created'] : null,
            'modified' => $row['modified'] !== null ? (int) $row['modified'] : null,
            'lastLogin' => $row['lastLogin'] !== null ? (int) $row['lastLogin'] : null,
            'lastLoginFrom' => $row['lastLoginFrom'],
        ], $rows);

        $event->setStatus($event::STATUS_OK, count($event->response['data']) . ' user(s) found.');
    }

    /**
     * Add a date-range WHERE clause for a timestamp column.
     *
     * Accepts either Unix timestamps (int) or date strings parseable by strtotime().
     *
     * @param array<string> $where Reference to the WHERE clauses array.
     * @param array<int|float|string|bool|null> $params Reference to the bound params array.
     * @param string $column The DB column name.
     * @param mixed $from The lower bound (inclusive).
     * @param mixed $to The upper bound (inclusive).
     * @param CallEvent $event For error reporting.
     * @return void
     */
    private function addRange(
        array &$where,
        array &$params,
        string $column,
        mixed $from,
        mixed $to,
        CallEvent $event
    ): void {
        $fromTs = $this->toTimestamp($from);
        if ($from !== null && $fromTs === null) {
            $event->setStatus($event::STATUS_BAD_REQUEST, "Invalid \"{$column}From\". Expected a Unix timestamp or date string.");
            return;
        }
        $toTs = $this->toTimestamp($to);
        if ($to !== null && $toTs === null) {
            $event->setStatus($event::STATUS_BAD_REQUEST, "Invalid \"{$column}To\". Expected a Unix timestamp or date string.");
            return;
        }

        if ($fromTs !== null) {
            $where[] = "$column >= ?";
            $params[] = $fromTs;
        }
        if ($toTs !== null) {
            $where[] = "$column <= ?";
            $params[] = $toTs;
        }
    }

    /**
     * Convert a value to a Unix timestamp or null if invalid.
     *
     * @param mixed $value
     * @return int|null
     */
    private function toTimestamp(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }
        if (is_string($value) && $value !== '') {
            $ts = strtotime($value);
            return $ts === false ? null : $ts;
        }
        return null;
    }
}