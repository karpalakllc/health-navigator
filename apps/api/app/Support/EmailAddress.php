<?php

namespace App\Support;

/**
 * The one definition of "the same address", shared by the auth requests, the
 * User model and the migration that normalised existing rows. If these ever
 * disagree, lookups miss and duplicate accounts become possible again.
 */
final class EmailAddress
{
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
