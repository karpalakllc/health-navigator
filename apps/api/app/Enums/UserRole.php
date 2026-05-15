<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Member = 'member';

    public function isStaff(): bool
    {
        return match ($this) {
            self::Admin, self::Moderator => true,
            self::Member => false,
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
