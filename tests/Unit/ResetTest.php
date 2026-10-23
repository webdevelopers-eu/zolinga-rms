<?php

declare(strict_types=1);

namespace Zolinga\Rms\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for UserService::reset().
 *
 * A failed login must leave the service in a genuinely logged-out state:
 * not only the session and cookies, but also the loaded user data. Before
 * this, reset() only cleared the session, so loginNoPassword() returned
 * false while $api->user->id, isGuest() and hasRight() still reported the
 * previous user - which let a Bearer token of a disabled account through.
 *
 * @module zolinga-rms
 */
class ResetTest extends TestCase
{
    private const TEST_USER_ID = 3101;

    protected function tearDown(): void
    {
        global $api;
        $api->db->query('UPDATE rmsUsers SET canLogin = 1, removed = 0 WHERE id = ?', self::TEST_USER_ID);
        $api->user->logout();
    }

    public function testFailedLoginClearsLoadedIdentity(): void
    {
        global $api;

        $api->db->query('UPDATE rmsUsers SET canLogin = 0 WHERE id = ?', self::TEST_USER_ID);

        $result = $api->user->loginNoPassword(self::TEST_USER_ID);

        $this->assertFalse($result, 'loginNoPassword() must report failure for a disabled account');
        $this->assertNull($api->user->id, 'The loaded user id must be cleared');
        $this->assertTrue($api->user->isGuest(), 'The service must report a guest after a failed login');
        $this->assertFalse(
            $api->user->hasRight('member of users'),
            'A failed login must not leave any rights behind'
        );
    }

    public function testRemovedUserLoginClearsLoadedIdentity(): void
    {
        global $api;

        $api->db->query('UPDATE rmsUsers SET removed = UNIX_TIMESTAMP() WHERE id = ?', self::TEST_USER_ID);

        $result = $api->user->loginNoPassword(self::TEST_USER_ID);

        $this->assertFalse($result, 'loginNoPassword() must report failure for a removed account');
        $this->assertNull($api->user->id, 'The loaded user id must be cleared');
        $this->assertTrue($api->user->isGuest(), 'The service must report a guest after a failed login');
    }

    public function testSuccessfulLoginStillLoadsIdentity(): void
    {
        global $api;

        $result = $api->user->loginNoPassword(self::TEST_USER_ID);

        $this->assertTrue($result, 'loginNoPassword() must succeed for an active account');
        $this->assertSame(self::TEST_USER_ID, $api->user->id, 'The user must be loaded');
        $this->assertFalse($api->user->isGuest(), 'The service must not report a guest');
    }
}