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
     * bin/zolinga rms:user --user=<email or id> 
     *      [--grant=<permission>] 
     *      [--revoke=<permission>]
     *      [--hasRight=<permission>]
     *      [--list]
     * 
     * @param RequestResponseEvent $event
     * @return void
     */
    public function onRequest(RequestResponseEvent $event)
    {
        global $api;

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