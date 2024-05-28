# Zolinga Rights Management System

The Zolinga RMS module provides:

- `$api->user`
    > The service representing current user. Allows the user to log in, log out, check if user is logged in, get user's ID, check, grant and revoke user's rights and more.
- `$api->rms`
    > The service for managing user rights. Allows to create, delete, get and find users.
- [Authorization Provider](:ref:event:system:authorize)
    > This module provides also [Authorization Provider](:ref:event:system:authorize) that will treat `zolinga.json`'s `rights` property as a RMS right and will check if the user has the specified right.
- [Login Remote Event](:ref:event:rms:login) and [Logout Remote Event](:ref:event:rms:logout)
    > You can dispatch these events from your Javascript or PHP to log in or log out the user.

# Fundamentals

The Zolinga RMS module is based on the following rights philosophy:

- **Rights are granted, not denied.**
    > This means that if you want to deny a right to a user, you simply do not grant it.
- **Rights are string identifiers.**
    > This means that you can use any string as a right identifier. You can use a simple string like `read` or a more complex string like `user:edit:email` or `see hidden-content`.

Example:

If you want to grant a user the right to edit a file, you would grant the right `edit some/file.txt`.

```php
$api->user->grant("edit some/file.txt");
echo $api->user->hasRight("edit some/file.txt") ? "has right" : "does not have right";
$api->user->revoke("edit some/file.txt");
```
If you want to set the right on arbitrary user, not the current user, you need to get the `\Zolinga\Rms\User` object first.

```php
$user = $api->rms->getUser(123); // (int) by user id
$user->remove();

$otherUser = $api->rms->getUser("user@example.com"); // (string) by username
$hasRights = $otherUser->filterRights(["edit some/file.txt", "edit some/other.txt"]);
// $hasRights will return only ["edit some/file.txt"]

$can = $otherUser->hasRight("edit some/file.txt", "edit some/other.txt"); 
// $can is true because at least one right matches

$can = $otherUser->hasRightsAll("edit some/file.txt", "edit some/other.txt");
// $can is false because not all rights match
```

# Logging In and Out Using Javascript

```javascript
import api from '/dist/system/js/api.js';

const event = await api.dispatchEvent('rms:login', {username, password});

if (event.ok) {
    console.log('Logged in');
} else {
    console.error('Login failed');
}
```

The same goes for [rms:logout](:ref:event:rms:logout) event.

# Tips and Tricks

## Membership

You can use the membership as a right. For example, you can grant the right `member of {GROUP}` to all users that are members of a certain group.

```php
$groups = ["group1", "group2", "group3"];

$user->grant("member of group1", "member of group3");

// Convert to array ["group1" => "member of group1", "group2" => "member of group2", ...]
$rights = array_combine( 
    $groups, 
    array_map(fn($name) => "member of $name", $groups)
);

// Filter rights and return only the keys (group names)
$membership = array_keys($user->filterRights($rights));

print_r($membership);
// Output: Array ( [0] => group1, [1] => group3 )
```

List all users that are members of a group:

```php
$userIds = $api->rms->findUserIdsByRight("member of group1");
$users = array_map(fn($id) => $api->rms->getUser($id), $userIds);

// Remove them from the group
array_walk($users, fn($user) => $user->revoke("member of group1"));
```

## Metadata

Each `User` object (including `$api->user`) has a `meta` property that can be used retrieve and store additional user data structures that must be JSON-serializable. Setting new data into array automatically saves data into DB.

```php
$api->user->meta["key"] = ["value" => "data"];
echo $api->user->meta["key"]["value"]; // Output: data
unset($api->user->meta["key"]); // same as $api->user->meta["key"] = null;
```

Note that only one-level array is supported for setting data.

```
$api->user->meta["key"]["subkey"] = "value"; // ERROR This will not work
$api->user->meta["key"] = ["subkey" => "value"]; // This will work
```

The key can be any string max 255 characters long.