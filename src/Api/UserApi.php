<?php

declare(strict_types=1);

namespace Zolinga\Rms\Api;

use Zolinga\Rms\User;
use Zolinga\System\Events\{ContentEvent, RequestEvent, RequestResponseEvent, ListenerInterface, AuthorizeEvent};
use Zolinga\Commons\Email;

class UserApi implements ListenerInterface
{
    private const RECOVERY_LINK_EXPIRATION = 3600;

    public function onRecover(RequestResponseEvent $event): void
    {
        global $api;

        $username = $event->request['username'] ?? null;

        if (!$username) {
            $event->setStatus($event::STATUS_BAD_REQUEST, dgettext("zolinga-rms", "Username is required."));
            return;
        }

        if (filter_var($username, FILTER_VALIDATE_EMAIL) === false) {
            $event->setStatus($event::STATUS_BAD_REQUEST, dgettext("zolinga-rms", "Username must be a valid email address."));
            return;
        }

        $user = $api->rms->findUser($username);

        if ($user && !$this->sendRecoveryEmail($user, $event->request['referrer'] ?? "")) {
            $event->setStatus($event::STATUS_ERROR, dgettext("zolinga-rms", "Could not send recovery email."));
            return;
        }

        // $api->rms->sendRecoveryEmail($user);
        $event->response['showCard'] = 'sign-in'; // show login card after reset
        $event->setStatus($event::STATUS_OK, dgettext("zolinga-rms", "Recovery email sent. Check your inbox and follow the instructions."));
    }

    public function onReset(RequestResponseEvent $event): void
    {
        global $api;

        if ($event->request['password'] != $event->request['password2']) {
            $event->setStatus($event::STATUS_BAD_REQUEST, dgettext("zolinga-rms", "Passwords do not match."));
            return;
        }

        if (strlen($event->request['password']) < 6) {
            $event->setStatus($event::STATUS_BAD_REQUEST, dgettext("zolinga-rms", "Password must be at least 6 characters long."));
            return;
        }

        $event->response['showCard'] = 'sign-in'; // show login card after reset or if invalid link.
        $hash = $event->request['hash'] ?? null;
        try {
            $user = $this->getUserByRecoveryHash($hash);
            $user->setPassword($event->request['password']);
            $user->save();
        } catch (\Exception $e) {
            $event->setStatus($event::STATUS_ERROR, $e->getMessage());
            return;
        }

        $event->setStatus($event::STATUS_OK, dgettext("zolinga-rms", "Password reset successful."));
    }

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

    public function onRegister(RequestResponseEvent $event): void
    {
        global $api;

        $username = $event->request['username'] ?? null;
        $password = $event->request['password'] ?? null;
        $givenName = $event->request['givenName'] ?? null;
        $familyName = $event->request['familyName'] ?? null;

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
            "givenName" => $givenName,
            "familyName" => $familyName,
        ]);

        if (!$api->user->login($username, $password)) {
            $event->setStatus($event::STATUS_ERROR, dgettext("zolinga-rms", "User created but could not login."));
            return;
        }

        $event->setStatus($event::STATUS_OK, dgettext("zolinga-rms", "Welcome! You are now registered and logged in."));
    }

    // we just need to wake up the user object so it sets cookies
    public function onContent(ContentEvent $event): void
    {
        global $api;
        // wake up the user object
        /** @phpstan-ignore-next-line */
        $api->user;
    }

    public function onLogout(RequestEvent $event): void
    {
        global $api;
        $api->user->logout();
        $event->setStatus($event::STATUS_OK, dgettext("zolinga-rms", "You have been logged out."));
    }

    private function sendRecoveryEmail(User $user, string $referrer): bool
    {
        global $api;

        $file = $api->locale->getLocalizedFile("private://zolinga-rms/templates/email-recover-password.html");
        $hash = $this->generateHash($user, time() + self::RECOVERY_LINK_EXPIRATION);

        $recoveryLink = $_SERVER['HTTP_ORIGIN'];
        $recoveryLink .= parse_url($referrer, PHP_URL_PATH);
        $recoveryLink .= "#!recover=$hash";

        $html = file_get_contents($file)
            or throw new \Exception("Could not read email template.");
        $html = str_replace("{{recoveryLink}}", $recoveryLink, $html);

        // temp file
        file_put_contents(sys_get_temp_dir() . '/last-recovery-email.html', $html);

        $email = new Email();
        $email->setMessage(false, $html);

        return $email->send($user->username);
    }

    /**
     * Validate the hash and return the user.
     * 
     * @throws \Exception if the hash is invalid or expired.
     * @param string $hash
     * @return User
     */
    private function getUserByRecoveryHash(string $hash): User
    {
        global $api;

        list($idBase, $expirationBase, $hashBase) = explode("-", "$hash--");
        $id = (int) base_convert($idBase, 36, 10);
        $expiration = (int) base_convert($expirationBase, 36, 10);

        if (!$id || !$expiration) {
            throw new \Exception(dgettext("zolinga-rms", "Invalid or expired recovery link."));
        }

        if ($expiration < time()) {
            throw new \Exception(dgettext("zolinga-rms", "Invalid or expired recovery link."));
        }

        $user = $api->rms->findUser($id);
        if (!$user) {
            throw new \Exception(dgettext("zolinga-rms", "User not found."));
        }

        if ($hash !== $this->generateHash($user, $expiration)) {
            throw new \Exception(dgettext("zolinga-rms", "Invalid or expired recovery link."));
        }

        return $user;
    }

    /**
     * Generate recovery link hash with encoded user id and expiration time.
     *
     * @param User $user
     * @param integer $expiration
     * @return string
     */
    private function generateHash(User $user, int $expiration): string
    {
        $expirationBase = base_convert("$expiration", 10, 36);
        $idBase = base_convert("$user->id", 10, 36);
        $hash = substr(hash('sha256', "$user->id $expiration $user->password"), 0, 12);
        $hashBase = base_convert("$hash", 16, 36);

        return "$idBase-$expirationBase-$hashBase";
    }
}
