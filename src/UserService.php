<?php

declare(strict_types=1);

namespace Zolinga\Rms;

use Zolinga\System\Events\{AuthorizeEvent, ServiceInterface};

/** @var true \Zolinga\System\SECURE_CONNECTION */

use const Zolinga\System\SECURE_CONNECTION;

/**
* The $api->user service representing the current user.
*
* @author Daniel Sevcik <danny@zolinga.net>
* @date 2024-03-11
*/
class UserService extends User implements ServiceInterface
{
    const AUTOLOGIN_EXPIRE = 60 * 60 * 24 * 30; // 30 days
    
    public function __construct()
    {
        global $api;
        
        if (!is_array($_SESSION['rms'] ?? null)) {
            $_SESSION['rms'] = [];
        }
        
        if (isset($_SESSION['rms']['user'])) {
            $id = $_SESSION['rms']['user'];
            /** @phpstan-ignore-next-line */
        } elseif (isset($_COOKIE['rmsa']) && SECURE_CONNECTION) {
            try {
                $id = $this->parseAutologinToken($_COOKIE['rmsa']);
            } catch (\Throwable $e) { // fail silently
                $this->clearAutologinCookie();
                $id = null;
            }
        } else {
            $id = null;
        }
        
        $this->setLoggedInFlagCookie((bool) $id);
        
        try {
            parent::__construct($id);
        } catch (\Throwable $e) { // usually when user with $id does not exist
            $api->log->error("rms.login", $e);
            $this->reset();
        }
    }
    
    /**
    * Load the user by ID or username and store the ID in the session.
    *
    * @param User|string|integer|array<mixed> $who
    * @return bool true if the user was found and logged in, false otherwise
    */
    public function loginNoPassword(string|int|array $who): bool
    {
        global $api;
        
        try {
            parent::load($who);
            $_SESSION['rms']['user'] = $this->id;
            $this->setLoggedInFlagCookie((bool) $this->id);
        } catch (\Throwable $e) {
            $api->log->error("rms.login", $e);
            $this->reset();
            return false;
        }
        $this->updateAfterLogin();
        return true;
    }
    
    public function login(string $username, string $password): bool
    {
        global $api;
        
        try {
            if (strlen($password) < 6) {
                throw new \Exception("Password too short.");
            }
            
            $user = $api->rms->findUser($username);
            if (!$user) {
                $api->log->warning("rms.login", "User $username not found.", ["username" => $username]);
                $this->reset();
                return false;
            }
            if (!$user->validatePassword($password)) {
                $api->log->warning("rms.login", "Invalid password for user $username.", ["username" => $username]);
                $this->reset();
                return false;
            }
            $this->loginNoPassword($user->data);
        } catch (\Throwable $e) {
            $api->log->error("rms.login", $e);
            $this->reset();
            return false;
        }
        
        $this->updateAfterLogin();
        $api->log->info("rms.login", "User $this logged in.", ["id" => $this->id, "username" => $this->username]);
        return true;
    }
    
    private function updateAfterLogin(): void {
        $this->lastLogin = time();
        $this->lastLoginFrom = $_SERVER['REMOTE_ADDR'] ?? null;
        $this->save();
    }
    
    protected function reset(): void
    {
        unset($_SESSION['rms']['user']);
        $this->setLoggedInFlagCookie(false);
        $this->clearAutologinCookie();
    }
    
    private function clearAutologinCookie(): void
    {
        unset($_COOKIE['rmsa']);
        setcookie('rmsa', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
    
    public function logout(): void
    {
        global $api;
        
        $api->log->info("rms.login", "User $this logged out.");
        $this->reset();
    }
    
    public function onAuthorize(AuthorizeEvent $event): void
    {
        global $api;
        
        if (!$this->id) return; // not loaded yet - no rights
        $rights = array_map(strval(...), $event->unauthorized);
        $authorized = $this->filterRights($rights);
        $event->authorize(...$authorized);
    }
    
    /**
    * Set autologin cookie.
    *
    * @return void
    */
    public function remember(): void
    {
        global $api;
        
        /** @phpstan-ignore-next-line */
        $enabled = SECURE_CONNECTION && $this->id;
        /** @var true $enabled */
        /** @phpstan-ignore-next-line */
        if (!$enabled) {
            $api->log->warning(
                "rms.remember", 
                "Autologin not enabled: no secure connection or user not logged in.", 
                [
                    "secure" => SECURE_CONNECTION, 
                    "userId" => $this->id,
                    "serverName" => $_SERVER['SERVER_NAME']
                    ]
                );
                return; // not logged in
            }
            
            $token = $this->genAutologinToken();
            
            // Set auto-login cookie
            // Options: expires, path, domain, secure, httponly and samesite
            // setcookie('rmsa', $token, $expire, '/', '', true, true);
            setcookie('rmsa', $token, [
                'expires' => time() + self::AUTOLOGIN_EXPIRE,
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
        
        /**
        * Validate and parse the autologin token into User ID
        *
        * @throws \Exception if user does not exist
        * @param string $token
        * @return integer|null integer if token is valid, null otherwise
        */
        private function parseAutologinToken(string $token): ?int
        {
            list($id, $expire, $hash) = explode('-', $token . '--');
            $id = (int) $id;
            $expire = (int) base_convert($expire, 36, 10);
            
            // Hash does not match - probably IP changed, browser changed, or tampered with
            if ($this->hashAutologinToken($id, $expire) !== $hash) {
                return null;
            }
            
            // Expired or somebody generated a suspicious long lifespan token, block both
            if ($expire < time() || $expire > time() + self::AUTOLOGIN_EXPIRE) {
                return null;
            }
            
            return (int) $id;
        }
        
        /**
        * Generate autologin token.
        *
        * @return string
        */
        private function genAutologinToken(): string
        {
            $expire = time() + self::AUTOLOGIN_EXPIRE;
            $expireEncoded = base_convert((string) $expire, 10, 36);
            $token = "{$this->id}-{$expireEncoded}-" . $this->hashAutologinToken($this->id, $expire);
            return $token;
        }
        
        /**
        * The hash is dependent on following values:
        * 
        * - user ID
        * - expiration time
        * - remote IP address
        * - user agent
        * - user password hash
        *
        * @throws \Exception if user not found
        * @param integer $userId
        * @param integer $expire
        * @return string hash
        */
        private function hashAutologinToken(int $userId, int $expire): string
        {
            global $api;
            
            $passHash = $api->db->query("SELECT password FROM rmsUsers WHERE id = ?", $userId)['password']
            or throw new \Exception("User $userId not found.");
            
            $hashString = $userId . $expire . @$_SERVER['REMOTE_ADDR'] . @$_SERVER['HTTP_USER_AGENT'] . $passHash;
            
            // calculate the bytes needed to store the PHP_MAX_INT binary
            return base_convert(substr(sha1($hashString), 4, 28), 16, 36);
        }
        
        /**
        * Set the cookie for javascript - is the user logged in?
        * 
        * This does not need to be secure as it is used only as a hint for the javascript.
        *
        * @param boolean $status
        * @return void
        */
        private function setLoggedInFlagCookie(bool $status): void
        {
            if (!headers_sent()) {
                setcookie('rmsIn', $status ? '1' : '0', [
                    'expires' => 0,
                    'path' => '/',
                    'domain' => '',
                    'secure' => false, // no need to secure this
                    'httponly' => false,
                    'samesite' => 'lax'
                ]);
            }
        }
    }
    