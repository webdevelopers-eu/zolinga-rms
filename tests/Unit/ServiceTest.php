<?php

declare(strict_types=1);

namespace Zolinga\Rms\Tests\Unit;

use Zolinga\Rms\User;
use PHPUnit\Framework\TestCase;
use \WeakReference;

/**
 * Class ServiceTest
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-12
 */
class ServiceTest extends TestCase
{
    public const USERNAME = "unittest@zolinga.net";

    public function createUser(): User
    {
        global $api;

        // Remove test user if it exists
        $user = $api->rms->findUser(self::USERNAME);
        if ($user) {
            $api->rms->removeUser($user);
        }

        return $api->rms->createUser(["username" => self::USERNAME, "password" => "123123"]);
    }

    protected function removeUser(): void
    {
        global $api;

        $user = $api->rms->findUser(self::USERNAME);
        $this->assertInstanceOf(User::class, $user);
        $api->rms->removeUser($user);
    }

    public function testCreateUser(): void
    {
        global $api;

        $user = $this->createUser();

        $this->assertTrue($user->validatePassword("123123"));

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(self::USERNAME, $user->username);
        $this->assertIsInt($user->id);
        $this->assertFalse($user->isModified, "User should not be modified after creation");

        $api->rms->removeUser($user);
    }

    public function testFindUser(): void
    {
        global $api;

        $user0 = $this->createUser();
        $this->assertInstanceOf(User::class, $user0);
        $this->assertEquals(self::USERNAME, $user0->username);

        $user1 = $api->rms->findUser(self::USERNAME);
        $this->assertTrue($user0 === $user1, "findUser should return the same instance for the same user");

        $user2 = $api->rms->findUser(self::USERNAME);
        $this->assertTrue($user1 === $user2, "findUser should return the same instance for the same user");

        $user3 = $api->rms->getUser($user0->id);
        $this->assertTrue($user0 === $user3, "getUser should return the same instance for the same user");

        $user4 = $api->rms->getUser(self::USERNAME);
        $this->assertTrue($user0 === $user4, "getUser should return the same instance for the same user");

        $this->removeUser();
    }

    public function testRemoveUser(): void
    {
        global $api;

        $user0 = $this->createUser();
        $this->removeUser();

        $user1 = $api->rms->findUser(self::USERNAME);
        $this->assertFalse($user1, "User should not be found after removal");

        $user2 = $this->createUser();
        $this->assertFalse($user2 === $user0, "New user should not be the same instance as the removed user");

        $this->removeUser();
    }

    public function testMarkUserAsRemoved(): void
    {
        global $api;

        $user0 = $this->createUser();

        $user0->markAsRemoved();
        $this->assertFalse($user0->isModified, "User should be saved automatically after marking as removed");

        $this->assertFalse($api->rms->findUser($user0->id), "User marked as removed should not be found using an ID");
        $this->assertFalse($api->rms->findUser(self::USERNAME), "User marked as removed should not be found using a username");

        $this->testRemoveUser();
    }

    public function testUserModification(): void
    {
        global $api;

        $user = $this->createUser();
        $user->password = "password";
        $this->assertTrue($user->isModified, "User should be modified after changing password");

        $user->save();
        $this->assertFalse($user->isModified, "User should not be modified after saving");

        $this->testRemoveUser();
    }

    public function testUserPasswordValidation(): void
    {
        $user = $this->createUser();
        $user->password = "password";
        $user->save();

        $match = $user->validatePassword("password");
        $this->assertTrue($match, "validatePassword should return true for the correct password");

        $match = $user->validatePassword("wrong");
        $this->assertFalse($match, "validatePassword should return false for the wrong password");

        $emptyPassword = "";
        $this->assertFalse($user->validatePassword($emptyPassword), "validatePassword should return false for an empty password");

        $shortPassword = "123";
        $this->assertFalse($user->validatePassword($shortPassword), "validatePassword should return false for a password shorter than 4 characters");

        $longPassword = "thisisaverylongpasswordthatexceedsthemaximumallowedlength";
        $this->assertFalse($user->validatePassword($longPassword), "validatePassword should return false for a password longer than the maximum allowed length");

        // New password should be saved
        $user->password = "password123";

        $validPassword = "password123";
        $this->assertTrue($user->validatePassword($validPassword), "validatePassword should return true for a valid password");

        $user->save();

        $user->canLogin = false;
        $this->assertFalse($user->validatePassword($validPassword), "validatePassword should return false for a user that cannot login");

        $this->testRemoveUser();
    }

    public function testRights(): void
    {
        $user = $this->createUser();
        $rights = [
            "unit.test right",
            "unit.test right 2",
            "unit.test right 3"
        ];

        $this->assertFalse($user->hasRight(...$rights), "User should not have the right before granting it");
        $this->assertFalse($user->hasRightsAll(...$rights), "User should not have the rights before granting them");

        $user->grant($rights[0], $rights[1]);
        $this->assertTrue($user->hasRight(...$rights), "User should have the right after granting it");
        $this->assertFalse($user->hasRightsAll(...$rights), "User should not have all the rights after granting only some");

        $user->revoke($rights[1]);
        $this->assertTrue($user->hasRight(...$rights), "User should have the first right after revoking the second");
        $this->assertFalse($user->hasRightsAll(...$rights), "User should not have all the rights after granting only some");

        $user->grant($rights[1], $rights[2]);
        $this->assertTrue($user->hasRight(...$rights), "User should have the rights after granting them");
        $this->assertTrue($user->hasRightsAll(...$rights), "User should have all the rights after granting them");

        $user->revoke(...$rights);
        $this->assertFalse($user->hasRight(...$rights), "User should not have the rights after revoking them");
        $this->assertFalse($user->hasRightsAll(...$rights), "User should not have all the rights after revoking them");

        $this->assertEmpty($user->filterRights($rights), "User should have no rights after revoking them");
        $user->grant(...$rights);
        $this->assertCount(count($rights), $user->filterRights($rights), "User should have all the rights after granting them");
    }

    public function testWeakReference(): void
    {
        $userWeak = WeakReference::create($this->createUser());
        $this->assertNull($userWeak->get(), "Weak reference should return null since there should not be any references and object should be garbage collected");
        $this->removeUser();
    }
}
