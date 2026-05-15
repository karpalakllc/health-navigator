<?php

namespace App\Enums;

enum FacilityType: string
{
    case Clinic = 'clinic';
    case Hospital = 'hospital';
    case Laboratory = 'laboratory';
    case Pharmacy = 'pharmacy';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Types exposed on GET /facilities (excludes pharmacy; use /pharmacies).
     *
     * @return list<string>
     */
    public static function clinicalValues(): array
    {
        return [
            self::Clinic->value,
            self::Hospital->value,
            self::Laboratory->value,
        ];
    }
}
