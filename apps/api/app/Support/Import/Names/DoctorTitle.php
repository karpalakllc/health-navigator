<?php

namespace App\Support\Import\Names;

/**
 * Canonical academic titles for doctors.title: „Проф д-р др. сци“, „prof.
 * dr“, „Проф. Д-р“ and „проф.д-р“ all become „проф. д-р“ / „проф. д-р
 * сци.“. Job roles („раководител на оддел“, „директор“) and a specialty
 * spelled out („специјалист невролог“) are not titles and are dropped — the
 * specialty is a link of its own. A title with anything the rules do not
 * know is left as it is and reported as uncertain.
 */
final class DoctorTitle
{
    private const SPECIALISANT = '(специјализант)';

    /**
     * Patterns tried at each position, longest first. Values are the
     * canonical token; null drops the match (a role, a spelled-out
     * specialty).
     *
     * @var list<array{0: string, 1: string|null}>
     */
    private const PATTERNS = [
        ['в\.?\s*н\.?\s*сор(?:аботник)?', 'виш науч. сор.'],
        ['виш\.?\s*науч(?:ен)?\.?\s*со[рeе](?:аботник)?', 'виш науч. сор.'],
        ['виш\.?\s*науч(?:ен)?\.?\s*сов(?:етник|ет)?', 'виш науч. сов.'],
        ['н(?:ауч(?:ен)?)?\.?\s*со[рeе](?:аботник)?', 'науч. сор.'],
        ['науч(?:ен)?\.?\s*сов(?:етник|ет)?', 'науч. сов.'],
        ['академик', 'академик'],
        ['вонр(?:еден)?|вон', 'вонр.'],
        ['насл(?:овен)?', 'насл.'],
        ['проф(?:есор)?', 'проф.'],
        ['емеритус', 'емеритус'],
        ['доц(?:ент)?', 'доц.'],
        ['асс?(?:истент)?', 'асс.'],
        ['прим(?:ариус)?', 'прим.'],
        ['(?:суп|суб)спец(?:ијалист)?', 'субспец.'],
        ['специјализант', self::SPECIALISANT],
        ['специјалист(?:\s+(?:по\s+)?(?!д-р|др)[\p{L}\-]+)+', null],
        ['спец(?:ијалист)?', 'спец.'],
        ['докторанд', 'докторанд'],
        ['(?:д-р|др|д\.р|доктор)\s*\.?\s*(?:мед\.?\s*)?(?:сци|сц|науки)(?:\s*\.?\s*мед)?', 'д-р сци.'],
        ['(?:д-р|др|доктор)\s*\.?\s*мед\.?\s*унив', 'д-р мед. унив.'],
        ['д-р|др|доктор', 'д-р'],
        ['(?:м-р|мр)\s*\.?\s*(?:сци|сц)', 'м-р сци.'],
        ['м-р|мр|магистер', 'м-р'],
        ['мед\.?\s*(?:сци|науки)|сци|сц', 'сци.'],
        ['(?:раководител|директор|шеф|началник|основач)(?![\p{L}])[^,\/]*', null],
    ];

    /** @var array<string, string> Latin spellings some sites use */
    private const LATIN = [
        'dr' => 'д-р', 'prof' => 'проф', 'prim' => 'прим', 'spec' => 'спец', 'sci' => 'сци', 'mr' => 'м-р',
        'as' => 'асс', 'ass' => 'асс', 'doc' => 'доц', 'dр' => 'д-р', 'aс' => 'асс', 'мр' => 'м-р',
    ];

    public static function clean(?string $raw): ?CleanedName
    {
        $original = trim((string) preg_replace('/\s+/u', ' ', (string) $raw));

        if ($original === '') {
            return null;
        }

        $parsed = self::parse($original);

        if ($parsed === null) {
            return new CleanedName($original, $original !== $raw ? ['spacing'] : [], 'title_unrecognised');
        }

        return new CleanedName($parsed, $parsed !== $raw ? ['title_format'] : []);
    }

    /**
     * Whether one word of a person's name is a title abbreviation („Д-р“,
     * „Проф.“, „dr.“). A bare „Др“ / „Mr“ needs the caller's context.
     */
    public static function isTitleWord(string $word): bool
    {
        $word = trim($word);

        if ($word === '' || preg_match('/^[\p{L}\-.]+$/u', $word) !== 1) {
            return false;
        }

        $parsed = self::parse($word);

        return $parsed !== null && $parsed !== '' && $parsed !== self::SPECIALISANT;
    }

    /**
     * The canonical title, '' when it held only roles, or null when part of
     * it is not recognised.
     */
    private static function parse(string $title): ?string
    {
        $text = mb_strtolower($title, 'UTF-8');
        $text = (string) preg_replace_callback(
            '/(?<![\p{L}])([a-zрс]{2,4})(?![\p{L}])/u',
            fn (array $m): string => self::LATIN[$m[1]] ?? $m[1],
            $text,
        );
        // FESC, FEBTCS…: fellowships keep their capitals.
        $fellowships = [];
        $text = (string) preg_replace_callback('/(?<![\p{L}])(fe[a-z]{2,6})(?![\p{L}])/u', function (array $m) use (&$fellowships): string {
            $fellowships[] = mb_strtoupper($m[1]);

            return ' ';
        }, $text);

        $tokens = [];
        $specialisant = false;
        $position = 0;
        $length = strlen($text);

        while ($position < $length) {
            if (preg_match('/\G[\s.,;\/()\-]+/u', $text, $skip, 0, $position) === 1) {
                $position += strlen($skip[0]);

                continue;
            }

            $matched = false;

            foreach (self::PATTERNS as [$pattern, $canonical]) {
                if (preg_match('/\G(?:'.$pattern.')(?![\p{L}])\.?/u', $text, $match, 0, $position) === 1) {
                    $position += strlen($match[0]);
                    $matched = true;

                    if ($canonical === self::SPECIALISANT) {
                        $specialisant = true;
                    } elseif ($canonical !== null && end($tokens) !== $canonical) {
                        $tokens[] = $canonical;
                    }

                    break;
                }
            }

            if (! $matched) {
                return null;
            }
        }

        $value = implode(' ', [...$tokens, ...$fellowships]);

        if ($specialisant) {
            $value = trim($value.' '.self::SPECIALISANT);
        }

        return mb_substr($value, 0, 60);
    }
}
