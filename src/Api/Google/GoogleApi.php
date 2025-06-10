<?php

declare(strict_types=1);

namespace Zolinga\Rms\Api\Google;
use Zolinga\System\Events\{RequestResponseEvent, ListenerInterface, RequestEvent};

/**
 * This class is responsible for handling the authentication requests using Google Identity services.
 * 
 * @see https://developers.google.com/identity/sign-in/web/backend-auth
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-04-22
 */
class GoogleApi implements ListenerInterface
{
    public function onGet(RequestResponseEvent $event): void
    {
        global $api;

        $event->response["clientId"] = $api->config["rms"]["google"]["clientId"]
            or throw new \Exception("Google client ID not set in the configuration.");

        $event->setStatus($event::STATUS_OK, "Google client ID retrieved.");
    }

    /**
     * Login using the authentication token.
     */
    public function onLogin(RequestResponseEvent $event): void
    {
        global $api;

        $jwt = new GoogleJwt($event->request["jwt"]);

        if (!$jwt->isValid()) {
            $event->setStatus($event::STATUS_UNAUTHORIZED, _("Google response is invalid. Please log in again."));
            return;
        }

        $mail = $jwt->payload["email"]; // User seem to be authenticated, let's get the email address
        if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            $event->setStatus($event::STATUS_UNAUTHORIZED, "Invalid response data. Please log in again.");
            return;
        }

        $user = $api->rms->findUser($mail);
        if (!$user) {
            $user = $api->rms->createUser([
                "username" => $mail, 
                "password" => null,
                "lang" => $api->locale->locale
            ]);
            $user->meta->setMeta('familyName', $jwt->payload["family_name"] ?? null);
            $user->meta->setMeta('givenName', $jwt->payload["given_name"] ?? null);
            $user->meta->setMeta('picture', $jwt->payload["picture"] ?? null);
        }

        $api->user->loginNoPassword($user->id);
        $event->response["user"] = $api->user->getPublicUserData();

        $event->setStatus($event::STATUS_OK, dgettext("zolinga-rms", "Welcome! You are now logged in."));
    }
}
