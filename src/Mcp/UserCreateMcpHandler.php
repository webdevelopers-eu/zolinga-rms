<?php

declare(strict_types=1);

namespace Zolinga\Rms\Mcp;

use Zolinga\Rms\User;
use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Events\Mcp\Tools\CallEvent;

/**
 * MCP tool for creating RMS users.
 *
 * Restricted by the manifest to the "member of administrators" right. Creates
 * a new user account with a mandatory e-mail username. The password is
 * optional; when omitted the server generates a random password nobody knows,
 * so the account can only be used after the owner resets it. Optionally sets
 * canLogin/lang, given/family name meta data and grants rights.
 *
 * Example:
 *
 *   {"name": "userCreate", "arguments": {"username": "jane@example.com"}}
 *   {"name": "userCreate", "arguments": {"username": "jane@example.com", "password": "secret123"}}
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-11-07
 */
final class UserCreateMcpHandler implements ListenerInterface
{
    private const PASSWORD_MIN_LENGTH = 6;

    /** Meta fields accepted directly as request arguments. */
    private const META_FIELDS = ['givenName', 'familyName'];

    /**
     * Handle the "userCreate" MCP tool call.
     *
     * @param CallEvent $event
     * @return void
     */
    public function onCreate(CallEvent $event): void
    {
        global $api;

        $username = $this->parseUsername($event);
        if ($username === null) {
            return;
        }

        $password = $this->parsePassword($event);
        if ($password === null) {
            return;
        }

        if ($api->rms->findUser($username)) {
            $event->setStatus($event::STATUS_CONFLICT, 'User "' . $username . '" already exists.');
            return;
        }

        try {
            $user = $api->rms->createUser(['username' => $username, 'password' => $password]);
            $this->applyOptional($user, $event);
            $this->applyRights($user, $event);
        } catch (\InvalidArgumentException $e) {
            $event->setStatus($event::STATUS_BAD_REQUEST, $e->getMessage());
            return;
        } catch (\Exception $e) {
            $event->setStatus($event::STATUS_ERROR, $e->getMessage());
            return;
        }

        $event->response['user'] = $this->serialize($user);
        $event->setStatus($event::STATUS_OK, 'User "' . $user->username . '" created with ID ' . $user->id . '.');
    }

    /**
     * Validate and return the "username" argument, or set a BAD_REQUEST status.
     *
     * @param CallEvent $event
     * @return string|null
     */
    private function parseUsername(CallEvent $event): ?string
    {
        $username = $event->request['username'] ?? null;

        if (!is_string($username) || trim($username) === '') {
            $event->setStatus($event::STATUS_BAD_REQUEST, 'Missing or empty "username" argument.');
            return null;
        }

        $username = trim($username);

        if (filter_var($username, FILTER_VALIDATE_EMAIL) === false) {
            $event->setStatus($event::STATUS_BAD_REQUEST, 'Invalid "username". Expected a valid e-mail address.');
            return null;
        }

        return $username;
    }

    /**
     * Validate and return the "password" argument, or set a BAD_REQUEST status.
     *
     * When the argument is omitted a random password is generated that is never
     * returned to the caller and cannot be guessed. Such an account is unusable
     * until the owner resets the password.
     *
     * @param CallEvent $event
     * @return string|null
     */
    private function parsePassword(CallEvent $event): ?string
    {
        $password = $event->request['password'] ?? null;

        if ($password === null) {
            return md5(random_bytes(32) . microtime(true));
        }

        if (!is_string($password) || $password === '') {
            $event->setStatus($event::STATUS_BAD_REQUEST, 'Invalid or empty "password" argument. Omit it to have one generated.');
            return null;
        }

        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            $event->setStatus($event::STATUS_BAD_REQUEST, 'Password must be at least ' . self::PASSWORD_MIN_LENGTH . ' characters long.');
            return null;
        }

        return $password;
    }

    /**
     * Apply the optional scalar fields (canLogin, lang) and meta fields.
     *
     * @param User $user
     * @param CallEvent $event
     * @return void
     */
    private function applyOptional(User $user, CallEvent $event): void
    {
        $req = $event->request;

        if (isset($req['canLogin'])) {
            $user->canLogin = (bool) $req['canLogin'];
        }

        if (isset($req['lang'])) {
            $user->lang = (string) $req['lang'];
        }

        $user->save();

        foreach (self::META_FIELDS as $field) {
            if (isset($req[$field])) {
                $user->meta[$field] = (string) $req[$field];
            }
        }
    }

    /**
     * Grant the rights listed in the optional "grant" argument.
     *
     * @throws \InvalidArgumentException when "grant" is not a list of strings.
     * @param User $user
     * @param CallEvent $event
     * @return void
     */
    private function applyRights(User $user, CallEvent $event): void
    {
        $grant = $event->request['grant'] ?? [];

        if (!is_array($grant)) {
            throw new \InvalidArgumentException('Invalid "grant". Expected an array of right names.');
        }

        $rights = [];
        foreach ($grant as $right) {
            if (!is_string($right) || trim($right) === '') {
                throw new \InvalidArgumentException('Invalid "grant" item. Expected a non-empty string.');
            }
            $rights[] = trim($right);
        }

        if (!$rights) {
            return;
        }

        $user->grant(...$rights);
    }

    /**
     * Serialize the freshly created user into the response shape.
     *
     * @param User $user
     * @return array{id:int, username:string, canLogin:bool, lang:string, created:int|null, givenName:string|null, familyName:string|null, permissions:array<string>}
     */
    private function serialize(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'username' => (string) $user->username,
            'canLogin' => (bool) $user->canLogin,
            'lang' => (string) $user->lang,
            'created' => $user->created !== null ? (int) $user->created : null,
            'givenName' => $this->metaString($user, 'givenName'),
            'familyName' => $this->metaString($user, 'familyName'),
            'permissions' => array_values(array_map(fn ($command): string => (string) $command, $user->listPermissions())),
        ];
    }

    /**
     * Read a meta value as a string or null.
     *
     * @param User $user
     * @param string $field
     * @return string|null
     */
    private function metaString(User $user, string $field): ?string
    {
        $value = $user->meta[$field] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
