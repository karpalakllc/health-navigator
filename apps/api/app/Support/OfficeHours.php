<?php

namespace App\Support;

final class OfficeHours
{
    /** @var array<string, string> */
    public const DAY_OPTIONS = [
        'Пон' => 'Пон',
        'Вто' => 'Вто',
        'Сре' => 'Сре',
        'Чет' => 'Чет',
        'Пет' => 'Пет',
        'Саб' => 'Саб',
        'Нед' => 'Нед',
    ];

    /**
     * @param  array<string, string>|null  $hours
     * @return list<array{day: string, hours: string}>
     */
    public static function toRows(?array $hours): array
    {
        if ($hours === null || $hours === []) {
            return [];
        }

        $rows = [];

        foreach (self::DAY_OPTIONS as $day) {
            if (isset($hours[$day]) && trim((string) $hours[$day]) !== '') {
                $rows[] = ['day' => $day, 'hours' => (string) $hours[$day]];
            }
        }

        foreach ($hours as $day => $time) {
            if (! isset(self::DAY_OPTIONS[$day]) && trim((string) $time) !== '') {
                $rows[] = ['day' => (string) $day, 'hours' => (string) $time];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{day?: string, hours?: string}>|null  $rows
     * @return array<string, string>|null
     */
    public static function fromRows(?array $rows): ?array
    {
        if ($rows === null || $rows === []) {
            return null;
        }

        $hours = [];

        foreach ($rows as $row) {
            $day = trim((string) ($row['day'] ?? ''));
            $time = trim((string) ($row['hours'] ?? ''));

            if ($day === '' || $time === '') {
                continue;
            }

            $hours[$day] = $time;
        }

        return $hours === [] ? null : $hours;
    }
}
