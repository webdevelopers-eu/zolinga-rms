<?php

declare(strict_types=1);

namespace Zolinga\Rms\Api;

use Zolinga\Rms\User;
use Zolinga\System\Events\{RequestResponseEvent, ListenerInterface, AuthorizeEvent};
use Zolinga\Commons\Email;

/**
 * Settings API
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-05-30
 */
class SettingsApi implements ListenerInterface
{
    public function onSettings(RequestResponseEvent $event): void
    {
        switch ($event->request['op']) {
            case 'get':
                $this->getSettings($event);
                break;
            case 'set':
                $this->setSettings($event);
                break;
        }
    }

    private function getSettings(RequestResponseEvent $event): void
    {
        global $api;

        $event->response['data'] = [
            'username' => $api->user->username
        ];
        $event->setStatus($event::STATUS_OK, _('Settings loaded'));
    }

    private function setSettings(RequestResponseEvent $event): void
    {
        global $api;

        // Check password
        $currentPassword = $event->request['currentPassword']
            or throw new \InvalidArgumentException(_('Current password is required'), 403);

        if ($api->user->password !== null) { // if logged in using Google or similar, password is null
            $api->user->validatePassword($currentPassword)
                or throw new \InvalidArgumentException(_('Current password is incorrect'), 403);
        }

        // Check new password
        $password = $event->request['password'];
        $password2 = $event->request['confirmPassword'];
        if ($password) {
            $this->changePassword($password, $password2);
        }
 
        // Username
        $username = $event->request['username'];
        if ($username != $api->user->username) {
            if ($api->rms->findUser($username)) {
                throw new \InvalidArgumentException(sprintf(_('Username %s is already taken'), $username), 403);
            }
            $api->user->username = $username;
        }

        $api->user->save();
        $event->setStatus($event::STATUS_OK, _('Settings saved'));
    }

    private function changePassword(string $password, string $password2): void
    {
        global $api;
        
        if ($password != $password2) {
            throw new \InvalidArgumentException(_('New password and confirmation password do not match'), 403);
        }

        if (strlen($password) < 6) {
            throw new \InvalidArgumentException(_('Password must be at least 6 characters long'), 403);
        }

        $api->user->setPassword($password);
    }
}
