<?php

declare(strict_types=1);

namespace Zolinga\Rms;

use Stringable;
use Exception, InvalidArgumentException;
use const Zolinga\System\IS_INTERACTIVE;


/**
 * Class representing a user in the RMS system.
 * 
 * The user is identified by its unique ID or username which is an e-mail and can be loaded from the database.
 * 
 * $existingUser = $api->rms->getUser(123);
 * $otherUser = $api->rms->getUser("user@example.com");
 * 
 * Create a new user:
 * 
 * $newUser = $api->rms->createUser(["username" => "user@example.com"]);
 * echo "User created: $newUser\n";
 * 
 * Grant, revoke and check rights:
 * 
 * $user->grant("create user", "remove user");
 * $user->revoke("remove user");
 * $user->hasRight("create user");
 * $user->hasRight("create user", "manage users");     
 * $user->hasRightsAll("read reports", "member of the board");
 * $allowed = $user->filterRights([
 *      "create user", 
 *      "remove user", 
 *      "read reports"
 * ]);
 * 
 * @property ?int $id The unique ID of the user.
 * @property ?string $username The e-mail of the user.
 * @property ?string $password The password hash of the user. You set the password in a plain-text password but reading the password returns the hash.
 * @property ?int $removed The date and time the user was removed. If 0, the user is not removed.
 * @property ?bool $canLogin Whether the user can login.
 * @property ?int $created The date and time the user was created.
 * @property ?int $modified The date and time the user was last modified.
 * @property ?int $lastLogin The date and time of the last login.
 * @property ?string $lastLoginFrom The IP address of the last login.
 * @property bool $isModified True if the user data has been modified and object needs saving by calling User::save() method.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-11
 */
class User
{
    /**
     * The DB-row of user data.
     * 
     * @var array{ 
     *      id: ?int, 
     *      username: ?string, 
     *      password: ?string,
     *      lang: ?string,
     *      removed: ?int, 
     *      canLogin: ?int, 
     *      created: ?int, 
     *      modified: ?int, 
     *      lastLogin: ?int, 
     *      lastLoginFrom: ?string} $data
     */
    public private(set) array $data = [
        'id' => null,
        'username' => null,
        'password' => null,
        'lang' => null, // 'll_CC' format, e.g. 'en_US
        'removed' => null, // '0' means 'not removed', otherwise the timestamp of removal
        'canLogin' => null,
        'created' => null,
        'modified' => null,
        'lastLogin' => null,
        'lastLoginFrom' => null,
    ];

    private const PASSWORD_MIN_LENGTH = 6;

    /**
     * List of modified properties.
     * 
     * @var array<string> $modifiedFields
     */
    private array $modifiedFields = [];


    /**
     * Key-value store for user meta data.
     *
     * @var Meta
     */
    public private(set) Meta $meta;

    /**
     * Constructor
     *
     * @param null|string|integer|array{ 
     *      id: ?int, 
     *      username: ?string, 
     *      password: ?string,
     *      removed: ?int, 
     *      canLogin: ?int, 
     *      created: ?int, 
     *      modified: ?int, 
     *      lastLogin: ?int, 
     *      lastLoginFrom: ?string} $who
     */
    public function __construct(null|string|int|array $who = null)
    {
        if (!is_null($who)) {
            $this->load($who);
        }
    }

    /**
     * Load the user data from the database or array, by ID, username or e-mail.
     *
     * @param string|integer|array{ 
     *      id: ?int, 
     *      username: ?string, 
     *      password: ?string,
     *      lang: ?string
     *      removed: ?int, 
     *      canLogin: ?int, 
     *      created: ?int, 
     *      modified: ?int, 
     *      lastLogin: ?int, lo
     *      lastLoginFrom: ?string} $who
     */
    protected function load(string|int|array $who): void
    {
        if ($this->id) {
            $this->reset();
        }

        switch (gettype($who)) {
            case 'array':
                $this->loadFromArray($who);
                break;
            case 'integer':
                $this->loadById($who);
                break;
            case 'string':
                $this->loadByUsername($who);
                break;
        }
    }

    /**
     * Getter
     *
     * @param string $name
     * @return null|string|integer|boolean|array<mixed>|Meta
     */
    public function __get(string $name): null|string|int|bool|array|Meta
    {
        switch ($name) {
            case 'isModified':
                return (bool) $this->modifiedFields;
            case 'canLogin':
                return (bool) $this->data[$name];
            default:
                if (!key_exists($name, $this->data)) {
                    throw new \InvalidArgumentException("Property $name does not exist.");
                }
                return $this->data[$name];
        }
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    public function __set(string $name, null|string|int|bool $value): void
    {
        if (isset($this->data[$name]) && $this->data[$name] === $value) {
            return; // not modified
        }

        switch ($name) {
            case 'id':
                throw new \InvalidArgumentException("Property $name is read-only and is generated by database.");
            case 'removed':
                throw new \InvalidArgumentException("Property $name is read-only. Use User::markAsRemoved() method instead.");
            case 'username':
                $this->data[$name] = filter_var($value, FILTER_VALIDATE_EMAIL) ? (string) $value : throw new \InvalidArgumentException("Property $name must be a string.");
                break;
            case 'password':
                /** @var string $value */
                $this->setPassword($value);
                break;
            case 'canLogin':
                $this->data[$name] = is_bool($value) || is_int($value) ? intval($value) : throw new \InvalidArgumentException("Property $name must be a boolean.");
                break;
            case 'lastLogin':
                $this->data[$name] = is_int($value) ? $value : throw new \InvalidArgumentException("Property $name must be an integer.");
                break;
            case 'lastLoginFrom':
                $this->data[$name] = is_string($value) ? $value : throw new \InvalidArgumentException("Property $name must be a string.");
                break;
            case 'lang':
                $lang = \Locale::getPrimaryLanguage((string) $value)
                    or throw new \InvalidArgumentException("Property $name must be a valid language code in format ll_CC.");
                $region = \Locale::getRegion((string) $value)
                    or throw new \InvalidArgumentException("Property $name must be a valid language code in format ll_CC.");
                $this->data[$name] = "{$lang}_{$region}";
                break;
            default:
                throw new \InvalidArgumentException("Property $name does not exist.");
        }

        $this->modifiedFields["$name"] = $name;
    }

    /**
     * Set the password of the user.
     *
     * @param string $plainTextPassword
     * @return string The password hash $this->password
     */
    public function setPassword(string $plainTextPassword): string
    {
        if (strlen($plainTextPassword) < self::PASSWORD_MIN_LENGTH) {
            throw new \InvalidArgumentException(sprintf(dgettext("zolinga-rms", "Password must be at least %d characters long."), self::PASSWORD_MIN_LENGTH));
        }
        $this->data['password'] = password_hash($plainTextPassword, PASSWORD_DEFAULT);
        $this->modifiedFields['password'] = 'password';
        return $this->password;
    }

    /**
     * Validate the given plain-text password against the user's password hash.
     *
     * @param string $plainTextPassword
     * @return bool True if the password is valid, false otherwise.
     */
    public function validatePassword(string $plainTextPassword): bool
    {
        if (!$this->canLogin) return false;
        if (empty($plainTextPassword)) return false;
        if (strlen($plainTextPassword) < self::PASSWORD_MIN_LENGTH) return false;

        $ret = password_verify($plainTextPassword, $this->password);

        return $ret;
    }

    /**
     * Load data into object from the database by numeric user ID.
     *
     * @throws \InvalidArgumentException If the user with the given ID does not exist.
     * @param integer $id
     * @return User $this on success
     */
    private function loadById(int $id): User
    {
        global $api;

        if ($this->modifiedFields) {
            throw new Exception("User data has been modified and not saved.");
        }

        $res = $api->db->query("SELECT * FROM rmsUsers WHERE id = ?", $id)->current();
        if (!$res) {
            throw new \InvalidArgumentException("User with ID $id does not exist.");
        }

        $this->data = $res;
        $this->modifiedFields = [];
        $this->meta = new Meta($this);

        return $this;
    }

    /**
     * Load data into object from the database by username.
     * 
     * Note: If there are users marked as "removed" (see User::markAsRemoved()) with 
     * the same username they will be ignored. To load "removed" users use User::loadById() method instead.
     *
     * @throws \InvalidArgumentException If the user with the given username does not exist.
     * @param string $username
     * @return User $this on success
     */
    private function loadByUsername(string $username): User
    {
        global $api;

        if ($this->modifiedFields || $this->data['id']) {
            throw new Exception("User data has been modified or User object is already loaded.");
        }

        if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(dgettext("zolinga-rms", "Username must be a valid e-mail."));
        }

        $res = $api->db->query("SELECT * FROM rmsUsers WHERE username = ? and removed = 0", $username)->current();
        if (!$res) {
            throw new \InvalidArgumentException(sprintf(dgettext("zolinga-rms", "User with username %s does not exist."), $username));
        }

        $this->data = $res;
        $this->modifiedFields = [];
        $this->meta = new Meta($this);

        return $this;
    }

    /**
     * Load data from the given array into the object.
     *
     * @param array{ 
     *      id: ?int, 
     *      username: ?string, 
     *      password: ?string,
     *      removed: ?int, 
     *      canLogin: ?int, 
     *      created: ?int, 
     *      modified: ?int, 
     *      lastLogin: ?int, 
     *      lastLoginFrom: ?string} $data
     * @return User $this
     */
    private function loadFromArray(array $data): User
    {
        $merge = array_intersect_key($data, $this->data);

        $this->data = array_merge($this->data, $merge);
        $this->modifiedFields = [];
        $this->meta = new Meta($this);
        
        return $this;
    }

    /**
     * Save the user data to the database.
     *
     * @return User $this on success
     */
    public function save(): User
    {
        global $api;

        $this->loadedCheck("Cannot save the user.");

        if ($this->modifiedFields) {
            $modifiedValues = array_intersect_key($this->data, $this->modifiedFields);
            $api->db->queryExpand("UPDATE rmsUsers SET ?? WHERE id = ? LIMIT 1", $modifiedValues, $this->id);
            $this->modifiedFields = [];
        }
        return $this;
    }

    /**
     * Do not call directly. Use $api->rms->createUser() instead.
     *
     * @throws \Exception If the user cannot be created (already exists etc.)
     * @throws \InvalidArgumentException If the username is not set.
     * @return User
     */
    public function create(): User
    {
        global $api;

        if (!$this->username) {
            throw new \InvalidArgumentException("Cannot create the user. You have to specify at least the username.");
        }

        $modifiedValues = array_filter($this->data, fn ($v) => $v !== null, ARRAY_FILTER_USE_BOTH);
        $id = $api->db->queryExpand("INSERT INTO rmsUsers (`??`) VALUES ('??')", array_keys($modifiedValues), $modifiedValues)
            or throw new \Exception("Failed to create the user.");

        $this->modifiedFields = [];
        $this->loadById($id);

        if (!IS_INTERACTIVE) {
            $this->meta['landingPage'] = $api->analytics->landingPage;
            $this->meta['referrerPage'] = $api->analytics->referrerPage;
        }
        
        return $this;
    }

    /**
     * Remove the user from the database and reset this object.
     *
     * @return void
     */
    public function remove(): void
    {
        global $api;

        $api->rms->removeUser($this);
    }

    /**
     * Do not call directly. Use $api->rms->removeUser() instead.
     * @return User $this on success
     */
    public function wipe(): User
    {
        global $api;

        $this->loadedCheck("Cannot remove the user.");

        $api->db->query("DELETE FROM rmsUsers WHERE id = ? LIMIT 1", $this->id);
        $this->reset();
        $this->data['removed'] = time();

        return $this;
    }

    /**
     * Mark the user in DB as removed.
     * 
     * The user is not removed from the database but only "removed" flag is set.
     * Such user cannot log in and system will not return it in the list of users.
     * 
     * You don't need to save the user after calling this method. It saves the user automatically.
     * 
     * @return User $this on success
     */
    public function markAsRemoved(): User
    {
        global $api;

        $this->loadedCheck("Cannot mark the user as removed.");
        $this->canLogin = false;

        $this->data['removed'] = time();
        $this->modifiedFields['removed'] = 'removed';

        return $this->save();
    }

    /**
     * Get the list of all rights the user has.
     * 
     * Note: The array keys are preserved.
     *
     * @return array<Command> of rights the user has. The array keys are preserved.
     */
    public function listPermissions(): array 
    {
        global $api;

        $this->loadedCheck("Cannot list permissions.");

        $permissions = $api->db->query("
            SELECT c.command
            FROM rmsRights as r
            LEFT JOIN rmsCommands as c ON (r.commandHash = c.hash)
            WHERE r.userId = ?
            ", 
            $this->id
        )->fetchFirstColumnAll();

        return array_map(fn ($p) => new Command($p), $permissions);
    }

    /**
     * Grants rights to the user by creating and inserting commands into the database.
     * 
     * Example:
     * 
     * $user->grant("create user", "remove user");
     *
     * @param string|Command|Stringable ...$commands The commands to grant.
     * @return void
     */
    public function grant(string|Command|Stringable ...$commands): void
    {
        global $api;

        $this->loadedCheck("Cannot grant rights.");

        foreach ($commands as $command) {
            if (!($command instanceof Command)) {
                $command = new Command((string) $command);
            }
            $command->create(); // must be before the next line as there is a foreign key constraint
            $api->db->query("INSERT IGNORE INTO rmsRights (userId, commandHash) VALUES (?, ?)", $this->id, $command->hash);
        }
    }

    /**
     * Revoke rights from the user.
     * 
     * Example:
     * 
     * $user->revoke("remove user");
     *
     * @param string|Command|Stringable ...$commands The commands to revoke.
     * @return void
     */
    public function revoke(string|Command|Stringable ...$commands): void
    {
        global $api;

        $this->loadedCheck("Cannot revoke rights.");

        foreach ($commands as $command) {
            if (!($command instanceof Command)) {
                $command = new Command((string) $command);
            }
            $api->db->query("DELETE FROM rmsRights WHERE userId = ? AND commandHash = ?", $this->id, $command->hash);
        }
    }

    /**
     * Return only those commands that the user has rights to.
     * 
     * Note: The array keys are preserved.
     *
     * @param array<string|Command|Stringable> $commands
     * @return array<string|Command|Stringable> of rights the user has. The array keys are preserved.
     */
    public function filterRights(array $commands): array
    {
        global $api;

        // $this->loadedCheck("Cannot filter rights.");

        $commandObjects = array_map(fn ($command) => $command instanceof Command ? $command : new Command((string) $command), $commands);
        $commandHashes = array_map(fn ($command) => $command->hash, $commandObjects);
    
        if ($this->id) {
            $foundHashes = $api->db->queryExpand("
                SELECT `commandHash` 
                FROM `rmsRights` 
                WHERE `userId` = ? AND commandHash IN ('??')
                ", $this->id, $commandHashes)->fetchFirstColumnAll();
        } else {
            $foundHashes = [];
        }

        return array_filter(
            $commands,
            fn ($k) =>
            in_array($commandObjects[$k]->hash, $foundHashes)
            || 
            ($commandObjects[$k]->text === 'member of users' && $this->id)
            ||
            ($commandObjects[$k]->text === 'member of guests' && !$this->id),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Does the user have at least ONE of the given rights?
     * 
     * Note:
     * 
     * @param string|Command|Stringable ...$commands
     * @return bool
     */
    public function hasRight(string|Command|Stringable ...$commands): bool
    {
        return count($this->filterRights([...$commands])) > 0;
    }

    /**
     * Does the user have ALL of the given rights?
     * 
     * @param string|Command|Stringable ...$commands
     * @return bool
     */
    public function hasRightsAll(string|Command|Stringable ...$commands): bool
    {
        return count($this->filterRights([...$commands])) === count($commands);
    }

    /**
     * Does the user have a special right "member of administrators"?
     *
     * @return bool
     */
    public function isAdministrator(): bool
    {
        return $this->hasRight('member of administrators');
    }

    /**
     * Does the user have a special right "member of guests"?
     *
     * @return boolean
     */
    public function isGuest(): bool
    {
        return $this->hasRight('member of guests');
    }

    /**
     * Grant the special right "member of administrators" to the user.
     * 
     * This right is used to identify the user as an administrator in the system.
     * 
     * @return void
     */
    public function grantAdministrator(): void
    {
        $this->grant('member of administrators');
    }

    /**
     * Revoke the special right "member of administrators" from the user.
     * 
     * @return void
     */
    public function revokeAdministrator(): void
    {
        $this->revoke('member of administrators');
    }

    /**
     * Reset object to its initial state.
     *
     * @return void
     */
    protected function reset(): void
    {
        // set all values to null
        $this->data = array_map(fn ($v) => null, $this->data);
        $this->modifiedFields = [];
        $this->meta = new Meta($this);
    }

    /**
     * Get the meta data of the user. Those data should be safe
     * to be shared with the front-end.
     *
     * @return array
     */
    public function getPublicUserData(): array {
        global $api;

        $tags = [];
        if ($api->user->isAdministrator()) {
            $tags[] = "administrator";
        }
        if ($api->isDebugging()) {
            $tags[] = "debugger";
        }

        // In future we should fire some event to allow other modules to add more data
        return [
            "username" => $api->user->username,
            "id" => $api->user->id,
            "tags" => $tags,
            // Other event listeners can add more user data here
            // just register a listener with lower priority
            // check if event status is OK and amend the response
        ];
    }

    /**
     * Check if the user is loaded and throw an exception if not.
     *
     * @param string $errMsg error message to prepend to the exception message.
     * @return void
     */
    private function loadedCheck(string $errMsg): void
    {
        if (!$this->id) {
            throw new \Exception("$errMsg This user does not exist yet ($this). Load the user or create a new one first.");
        }
    }

    /**
     * Get a user by ID, username or e-mail.
     *
     * @param string|integer|array $who
     * @return User
     */
    public static function getUser(string|int|array $who): User
    {
        global $api;
        return $api->rms->getUser($who);
    }

    /**
     * Create a new user in the RMS system.
     * 
     * This method is a shortcut for creating a new User object and calling its create() method.
     * 
     * @param array $data The user data to create the user with. Must contain at least 'username'.
     * @return User The created user object.
     * @throws \Exception If the user cannot be created.
     */
    public static function createUser(array $data): User
    {
        global $api;
        return $api->rms->createUser($data);
    }

    /**
     * Search for users by meta data.
     * 
     * This method allows you to search for users by their meta data.
     * 
     * @param string $key The meta key to search for.
     * @param string $value The value of the meta key to search for.
     * @param bool $returnFirst If true, returns only the first matching user. Default is false.
     * @return null|User|array<User> Array of User objects matching the search criteria or a single User object if $returnFirst is true.
     */
    public static function searchMeta(string $key, string $value, bool $returnFirst = false): null|User|array
    {
        global $api;

        $users = Meta::search($key, $value, limit: $returnFirst ? 1 : null);
        return $returnFirst ? ($users[0] ?? null) : $users;
    }

    public function __toString(): string
    {
        $flags = [
            'id' => $this->id ? $this->id : ($this->modifiedFields ? 'unsaved' : 'not-loaded'),
            'username' => $this->username ? $this->username : 'anonymous',
        ];
        if ($this->data['removed']) {
            $flags[] = 'removed';
        }
        return "User[" . implode(', ', $flags) . "]";
    }

    public function __destruct()
    {
        if ($this->modifiedFields) {
            trigger_error("User object properties of $this have been modified but not saved: " . implode(', ', $this->modifiedFields), E_USER_WARNING);
        }
    }
}
