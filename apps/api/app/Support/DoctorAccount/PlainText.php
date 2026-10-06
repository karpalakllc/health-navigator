<?php

namespace App\Support\DoctorAccount;

/**
 * What a linked doctor types is stored as plain text: markup is stripped, so
 * nothing they write can ever render as HTML on the public profile.
 */
final class PlainText
{
    /** One line: tags stripped, runs of whitespace collapsed. */
    public static function line(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));

        return $text === '' ? null : $text;
    }

    /** Several paragraphs: tags stripped, line breaks kept (at most one blank line). */
    public static function paragraphs(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = strip_tags(str_replace(["\r\n", "\r"], "\n", $value));
        $text = preg_replace('/[ \t]+\n/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        $text = trim($text);

        return $text === '' ? null : $text;
    }
}
