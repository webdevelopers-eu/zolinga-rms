<?php

declare(strict_types=1);

namespace Zolinga\Rms;

use JsonSerializable;

/**
 * Represents an RMS command. RMS command is a string consisting of 
 * a verb and object (e.g. "create user", "remove user", "list users").
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-09
 */
class Command implements JsonSerializable
{
    public readonly string $text;
    public readonly string $hash;

    public function __construct(string $command)
    {
        $this->text = $command;
        $this->hash = sha1($command, true);
    }

    /**
     * Create a new command in the database if it does not exist yet.
     * 
     * You don't need to call this method directly, it is called automatically
     * when you grant rights to a user.
     * 
     * @return void
     */
    public function create(): void
    {
        global $api;
        $api->db->query("INSERT IGNORE INTO rmsCommands (hash, command) VALUES (?, ?)", $this->hash, $this->text);
    }

    public function __toString(): string
    {
        return $this->text;
    }

    public function jsonSerialize(): string
    {
        return $this->text;
    }
}
