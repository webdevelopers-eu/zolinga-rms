<?php

declare(strict_types=1);

namespace Zolinga\Rms;

use Zolinga\System\Events\{AuthorizeEvent, ServiceInterface};

/**
 * The $api->user service representing the current user.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-11
 */
class UserService extends User implements ServiceInterface
{
    public function __construct()
    {
        if (!is_array($_SESSION['rms'] ?? null)) {
            $_SESSION['rms'] = [];
        }

        parent::__construct($_SESSION['rms']['user'] ?? null);
    }

    public function login(string $username, string $password): bool
    {
        global $api;

        try {
            $this->load($username);
        } catch (\Throwable $e) {
            $api->log->error("rms.login", $e);
            $this->reset();
            return false;
        }

        if (!$this->validatePassword($password)) {
            $api->log->warning("rms.login", "Invalid password for user $username.", ["username" => $username]);
            $this->reset();
            return false;
        }

        $_SESSION['rms']['user'] = $this->id;
        $api->log->info("rms.login", "User $this logged in.", ["id" => $this->id, "username" => $this->username]);
        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['rms']['user']);
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
}
