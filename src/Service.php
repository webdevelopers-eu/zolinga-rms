<?php

declare(strict_types=1);

namespace Zolinga\Rms;

use Zolinga\System\Events\ServiceInterface;
use WeakReference;

/**
 * RMS Factory and Management service $api->rms
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-11
 */
class Service implements ServiceInterface
{
    /**
     * List of weakly referenced User instances
     *
     * @var array<WeakReference> $cache
     * @phpstan-ignore-next-line does not support WeakReference?
     */
    private array $cache = [];

    /**
     * Search for a user in the cache and return it if found
     *
     * @param integer|string $who
     * @return User|false
     */
    private function getUserFromCache(int|string $who): User|false
    {
        // Search for the user in the cache where $user->id or $user->username is $who
        foreach ($this->cache as $k => $weak) {
            /** @var User|null $user */
            $user = $weak->get();

            // Remove the weak reference if object was destroyed or if the user was removed
            if ($user === null || $user->removed) {
                unset($this->cache[$k]);
                continue;
            }
            
            if ($user->id === $who || $user->username === $who) {
                return $user;
            }
        }
        return false;
    }

    private function addUserToCache(User $user): void
    {
        $this->cache[] = WeakReference::create($user);
    }

    private function removeUserFromCache(User $user): bool
    {
        foreach ($this->cache as $k => $weak) {
            /** @var User|null $u */
            $u = $weak->get();
            if ($u === null || $u === $user) {
                unset($this->cache[$k]);
                return true;
            }
        }
        return false;
    }

    /**
     * Get a User instance by its ID or username.
     * 
     * Same as Service::findUser but either returns an existing User instance 
     * or throws an exception.
     *  
     * @throws \Exception if the user does not exist
     * @param integer|string $who
     * @return User
     */
    public function getUser(int|string $who): User
    {
        $user = $this->getUserFromCache($who);
        if ($user) return $user;

        $user = new User($who);
        $this->addUserToCache($user);
        return $user;
    }


    /**
     * Same as Service::getUser() but returns false instead 
     * of throwing an Exception if the user does not exist
     *
     * @param integer|string $who
     * @return false|User
     */
    public function findUser(int|string $who): false|User
    {
        global $api;

        // IMPORTANT: when comparing int 0 to string the string gets converted to 0
        if (is_numeric($who)) {
            $who = intval($who);
            $field = 'id';
        } else {
            $field = 'username';
        }

        if (!$who) {
            return false;
        }

        $user = $this->getUserFromCache($who);
        if ($user) {
            return $user;
        }

        $data = $api->db->query('
            SELECT * 
            FROM rmsUsers 
            WHERE removed = 0 AND ' . $field . ' = ?
            LIMIT 1
        ', $who)->current() ?? false;

        if (!is_array($data)) return false;

        $user = new User($data);
        $this->addUserToCache($user);
        return $user;
    }

    /**
     * List all user ids of users that have at least one of the specified rights.
     * 
     * Example:
     * 
     *   $api->rms->findUserIdsByRight("create user", "remove user");
     *   // returns [21, 22, 252];
     *
     * @param string|Command ...$rights
     * @return array<int> list of ordered user ids that have at least one of the specified rights
     */
    public function findUserIdsByRight(string|Command ...$rights): array
    {
        global $api;

        $hashes = array_map(
            fn($right) => $right instanceof Command ? $right->hash : (new Command($right))->hash,
            $rights
        );

        $ret = $api->db->queryExpand(
            'SELECT DISTINCT userId as id FROM rmsRights WHERE commandHash IN ("??") ORDER BY userId ASC', 
            $hashes
        )->fetchFirstColumnAll();

        return $ret;
    }

    /**
     * Create a new user
     * 
     * @throws \Exception if the user already exists
     * @param array{ 
     *      id: ?int, 
     *      username: ?string, 
     *      password: ?string,
     *      removed: ?int, 
     *      canLogin: ?int, 
     *      created: ?int, 
     *      modified: ?int, 
     *      lastLogin: ?int, 
     *      lastLoginFrom: ?string} $data,
     * @return User
     */
    public function createUser(array $data): User
    {
        global $api;

        $user = new User($data);
        if (isset($data['password'])) {
            $user->setPassword($data['password']);
        }
        $user->create();
        $this->addUserToCache($user);
        return $user;
    }

    /**
     * Remove a user.
     * 
     * @param integer|string|User $who
     */
    public function removeUser(int|string|User $who): void
    {
        $user = $who instanceof User ? $who : $this->getUser($who);
        if (!$this->removeUserFromCache($user)) {
            trigger_error("Orphan user instance $user. Was the user already removed or was the user created by other means then calling methods on \$api->rms?", E_USER_WARNING);
        }
        $user->wipe();
    }

    /**
     * Search for users by meta data.
     * 
     * This method allows you to search for users by their meta data.
     * 
     * @param string $key The meta key to search for.
     * @param string $value The value of the meta key to search for.
     * @param bool $returnFirst If true, returns only the first matching user. Default is false.
     * @return null|User|array<User> Array of User objects matching the search criteria.
     */
    public function searchMeta(string $key, string $value, bool $returnFirst = false): array|null|User
    {
        global $api;

       return User::searchMeta(
            key: $key,
            value: $value,
            returnFirst: $returnFirst
        );
    }
}