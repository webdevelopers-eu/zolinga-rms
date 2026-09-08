<?php
declare(strict_types=1);

namespace Zolinga\Rms\Api;

use Zolinga\Rms\User;
use Zolinga\System\Events\{ContentEvent, RequestEvent, RequestResponseEvent, ListenerInterface, AuthorizeEvent};
use Zolinga\Commons\Email;

/**
 * User CLI API
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-04-30
 */
class UserCliApi implements ListenerInterface
{


    private function checkRight(User $user, string $right, ?bool $expects, &$msg): bool
    {
        if ($user->hasRight($right)) {
            $msg = "User " . $user->username . " has permission " . json_encode($right);
            $ret = true;
         } else {
            $msg = "User " . $user->username . " does not have permission " . json_encode($right);
            $ret = false;
        }

        if (is_bool($expects) && $expects !== $ret) {
            $msg = "User " . $user->username . " " . ($ret ? "has" : "does not have") . " permission " . $right . " but it was expected to " . ($expects ? "have" : "not have") . " it.";
            throw new \Exception($msg);
        }
        
        return $ret;
    }

    /**
     * bin/zolinga rms:user --user=<email-or-id> 
     *      [--grant=<permission>] 
     *      [--revoke=<permission>]
     *      [--hasRight=<permission>]
     *      [--list]
     *      [--login | --login=<email-or-id>]
     * 
     * The --login option logs the user in without a password (CLI only).
     * It does not update lastLogin/lastLoginFrom. It is a CLI hack so that
     * subsequent events in the same run are authorized as this user.
     * 
     * @param RequestResponseEvent $event
     * @return void
     */
    public function onRequest(RequestResponseEvent $event)
    {
        global $api;

        if ($event->request['login'] ?? false) {
            $loginAs = !is_bool($event->request['login']) ? $event->request['login'] : $event->request['user'] ?? null;
            if (!$loginAs) throw new \Exception("Missing `bin/zolinga rms:user --login=<email-or-id>` or `bin/zolinga rms:user --user=<email-or-id> --login` argument. As who should I log in?");
            $event->response['loggedIn'] = $api->user->loginNoPassword($loginAs, true);
        }

        if (!isset($event->request['user'])) {
            return; // maybe just a --login=<email-or-id> request
        }

        $user = $api->rms->findUser($event->request['user'] ?? 0)
            or throw new \Exception("User " . json_encode($event->request['user'] ?? null) ." not found. Specify correct --user=<email or id>");

        $event->response['userName'] = $user->username;
        $event->response['userId'] = $user->id;
        $event->response['report'] = [];

        if ($event->request['grant'] ?? false) {
            $user->grant($event->request['grant']);
            $event->response['hasRight'] = $this->checkRight($user, $event->request['grant'], true, $event->response['report']['grant']);
        } 
        if ($event->request['revoke'] ?? false) {
            $user->revoke($event->request['revoke']);
            $event->response['hasRight'] = $this->checkRight($user, $event->request['revoke'], false, $event->response['report']['revoke']);
        }
        if ($event->request['hasRight'] ?? false) {
            $event->response['hasRight'] = $this->checkRight($user, $event->request['hasRight'], null, $event->response['report']['hasRight']);
        }
        if ($event->request['list'] ?? false) {
            $event->response['permissions'] = $user->listPermissions();
            $event->response['report']['list'] = "Listed " . count($event->response['permissions']) . " permissions for user " . $user->username;
        }
        if ($event->request['setPassword'] ?? false) {
            $user->setPassword($event->request['setPassword']);
            $user->save();
            $event->response['report']['setPassword'] = "Set password for user " . $user->username;
        }

        $event->setStatus($event::STATUS_OK, "Done.");
    }
}