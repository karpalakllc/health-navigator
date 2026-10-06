<?php

namespace App\Enums;

/**
 * The coarse account type the API reports as `role` (User::accountRole()),
 * derived from Spatie roles and `user_kind`. Display only: authorization is
 * Spatie roles and permissions, never this value.
 *
 * Also the vocabulary of the legacy `users.role` column, dropped in
 * 2026_10_13_100000 (whose down() restores it with these values).
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Member = 'member';
}
