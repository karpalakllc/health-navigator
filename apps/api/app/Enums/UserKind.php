<?php

namespace App\Enums;

enum UserKind: string
{
    case Staff = 'staff';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Client => 'Client',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
