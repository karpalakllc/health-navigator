<?php

namespace App\Enums;

/**
 * The public reason a published review or forum post was taken down. It is
 * the only thing the public placeholder („Рецензијата е отстранета на … —
 * причина: …“) says about the removal: never the moderator's note, the text
 * or the author. The codes are the API contract; the web shows its own
 * Macedonian labels for them.
 */
enum RemovalCategory: string
{
    case Spam = 'spam';
    case Abuse = 'abuse';
    case FalseInformation = 'false_information';
    case PersonalData = 'personal_data';
    case Illegal = 'illegal';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Report reasons map one to one; „illegal“ is a moderator's choice only. */
    public static function fromReportReason(ReportReason $reason): self
    {
        return match ($reason) {
            ReportReason::Spam => self::Spam,
            ReportReason::Abuse => self::Abuse,
            ReportReason::FalseInformation => self::FalseInformation,
            ReportReason::PersonalData => self::PersonalData,
            ReportReason::Other => self::Other,
        };
    }

    /** For the admin panel (English, with the public Macedonian wording). */
    public function label(): string
    {
        return match ($this) {
            self::Spam => 'Spam or advertising („спам“)',
            self::Abuse => 'Abuse („навреда“)',
            self::FalseInformation => 'False information („лажни информации“)',
            self::PersonalData => 'Personal data („лични податоци“)',
            self::Illegal => 'Illegal content („незаконска содржина“)',
            self::Other => 'Other („друго“)',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $category) {
            $options[$category->value] = $category->label();
        }

        return $options;
    }
}
