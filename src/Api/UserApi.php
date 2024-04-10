<?php

declare(strict_types=1);

namespace Zolinga\Rms\Api;

use Zolinga\Rms\User;
use Zolinga\System\Events\{RequestEvent, RequestResponseEvent, ListenerInterface, AuthorizeEvent};

class UserApi implements ListenerInterface
{

    /**
     * Login attempt event.
     * 
     *   array{username: string, password: string} $event->request
     * 
     * Example:
     * 
     * ```javascript
     *    import api from '/dist/system/api.js';
     *    const resp = await api.dispatchEvent('rms:login', {username: 'user', password: 'password'});
     * ```
     * 
     * @param RequestResponseEvent $event
     * @return void
     */
    public function onLogin(RequestResponseEvent $event): void
    {
        global $api;

        $username = $event->request['username'] ?? null;
        $password = $event->request['password'] ?? null;
        $remember = $event->request['remember'] ?? false;
        
        if (!$username || !$password) {
            $event->setStatus($event::STATUS_BAD_REQUEST, dgettext("zolinga-rms", "Username and password are required."));
            return;
        }

        if (!$api->user->login($username, $password)) {
            $event->setStatus($event::STATUS_UNAUTHORIZED, dgettext("zolinga-rms", "Invalid username or password."));
            return;
        }

        if ($remember) {
            $api->user->remember();
        }
        
        $event->response['user'] = [
            "username" => $api->user->username,
            "id" => $api->user->id,
            // Other event listeners can add more user data here
            // just register a listener with lower priority
            // check if event status is OK and amend the response
        ];
        $event->setStatus($event::STATUS_OK, dgettext("zolinga-rms", "Login successful."));
    }

    public function onRegister(RequestResponseEvent $event): void {
        global $api;

        $username = $event->request['username'] ?? null;
        $password = $event->request['password'] ?? null;
        
        if (!$username || !$password) {
            $event->setStatus($event::STATUS_BAD_REQUEST, dgettext("zolinga-rms", "Username and password are required."));
            return;
        }

        if (isset($event->request['password2']) && $event->request['password2'] !== $password) {
            $event->setStatus($event::STATUS_BAD_REQUEST, dgettext("zolinga-rms", "Passwords do not match."));
            return;
        }

        if (filter_var($username, FILTER_VALIDATE_EMAIL) === false) {
            $event->setStatus($event::STATUS_BAD_REQUEST, dgettext("zolinga-rms", "Username must be a valid email address."));
            return;
        }

        if ($api->rms->findUser($username)) {
            $event->setStatus($event::STATUS_CONFLICT, dgettext("zolinga-rms", "User already exists."));
            return;
        }

        $user = $api->rms->createUser([
            "username" => $username,
            "password" => $password,
        ]);

        if (!$api->user->login($username, $password)) {
            $event->setStatus($event::STATUS_ERROR, dgettext("zolinga-rms", "User created but could not login."));
            return;
        }

        $event->setStatus($event::STATUS_OK, dgettext("zolinga-rms", "User created."));
    }

    public function onLogout(RequestEvent $event): void {
        global $api;
        $api->user->logout();
        $event->setStatus($event::STATUS_OK, dgettext("zolinga-rms", "Logout successful."));
    }

}
