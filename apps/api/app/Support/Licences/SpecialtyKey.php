<?php

namespace App\Support\Licences;

use App\Support\Import\NameKey;

/**
 * Matching key for a specialty's wording, so „гинекологија и акушерство“,
 * „ГИНЕКОЛОГИЈА И АКУШЕРСТВО“ and the ФЗОМ spelling with a Latin "A" typed
 * into a Cyrillic word meet. Same folding as people's names (NameKey): upper
 * case Cyrillic, Latin look-alikes folded, hyphens and punctuation dropped.
 */
final class SpecialtyKey
{
    public static function for(string $text): string
    {
        return NameKey::for($text);
    }

    /**
     * A ФЗОМ `Specijalnosti` value lists one or more specialties separated by
     * commas: „ИНТЕРНА МЕДИЦИНА, КАРДИОЛОГИЈА“.
     *
     * @return list<string>
     */
    public static function splitList(string $text): array
    {
        $items = [];

        foreach (explode(',', $text) as $item) {
            $item = trim($item);

            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }
}
