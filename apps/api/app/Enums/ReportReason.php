<?php

namespace App\Enums;

/**
 * Why a member reported a review or forum post. The codes are the API
 * contract; the web shows its own Macedonian labels for them, the admin panel
 * the English ones below.
 */
enum ReportReason: string
{
    case Spam = 'spam';
    case Abuse = 'abuse';
    case FalseInformation = 'false_information';
    case PersonalData = 'personal_data';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam or advertising',
            self::Abuse => 'Abuse or harassment',
            self::FalseInformation => 'False information',
            self::PersonalData => 'Personal data',
            self::Other => 'Other',
        };
    }

    /** As the web's report dialog words it, for staff email. */
    public function macedonianLabel(): string
    {
        return match ($this) {
            self::Spam => 'Спам или рекламирање',
            self::Abuse => 'Навреда или вознемирување',
            self::FalseInformation => 'Невистинити информации',
            self::PersonalData => 'Лични или здравствени податоци',
            self::Other => 'Друго',
        };
    }
}
