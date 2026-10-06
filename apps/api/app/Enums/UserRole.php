<?php

namespace App\Enums;

/**
 * The coarse account type the API reports as `role` (User::accountRole()),
 * derived from Spatie roles and `user_kind`. Display only: authorization is
 * Spatie roles and permissions, never this value.
 *
 * Also the vocabulary of the deprecated `users.role` column, which nothing
 * reads or writes any more and which a later migration drops.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Member = 'member';
}
