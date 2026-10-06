<?php

namespace App\Enums;

/**
 * Which part of a profile a correction is about. The codes are the API
 * contract; the web shows its own Macedonian labels, the admin panel the
 * English ones below. Some apply to doctors only, some to facilities only.
 */
enum ProfileCorrectionField: string
{
    case Name = 'name';
    case Title = 'title';
    case Specialty = 'specialty';
    case Workplace = 'workplace';
    case Address = 'address';
    case Contact = 'contact';
    case OfficeHours = 'office_hours';
    case Photo = 'photo';
    case Description = 'description';
    case Doctors = 'doctors';
    case Departments = 'departments';
    case NoLongerPractising = 'no_longer_practising';
    case Closed = 'closed';
    case Other = 'other';

    /**
     * @return list<self>
     */
    public static function forDoctor(): array
    {
        return [
            self::Name,
            self::Title,
            self::Specialty,
            self::Workplace,
            self::Address,
            self::Contact,
            self::OfficeHours,
            self::Photo,
            self::Description,
            self::NoLongerPractising,
            self::Other,
        ];
    }

    /**
     * @return list<self>
     */
    public static function forFacility(): array
    {
        return [
            self::Name,
            self::Address,
            self::Contact,
            self::OfficeHours,
            self::Photo,
            self::Description,
            self::Doctors,
            self::Departments,
            self::Closed,
            self::Other,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Name => 'Name',
            self::Title => 'Title',
            self::Specialty => 'Specialty',
            self::Workplace => 'Workplace',
            self::Address => 'City or address',
            self::Contact => 'Phone, e-mail or website',
            self::OfficeHours => 'Office hours',
            self::Photo => 'Photo',
            self::Description => 'Description, education or experience',
            self::Doctors => 'Doctors listed',
            self::Departments => 'Departments',
            self::NoLongerPractising => 'No longer practises here',
            self::Closed => 'Closed or moved',
            self::Other => 'Other',
        };
    }
}
