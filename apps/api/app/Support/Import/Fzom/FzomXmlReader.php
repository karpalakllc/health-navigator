<?php

namespace App\Support\Import\Fzom;

use Generator;
use RuntimeException;
use XMLReader;

/**
 * Streams <Lekar> rows out of a ФЗОМ XML file with XMLReader, one element in
 * memory at a time (the files are ~5–6 MB and grow). Only whitelisted child
 * elements are read; see FzomRow for what is dropped and why.
 */
final class FzomXmlReader
{
    /** Child elements we keep, by name. Everything else is skipped unread. */
    /** <Lekar> rows without a facsimile, a name or an institution, since construction. */
    public int $skipped = 0;

    public const KEPT = [
        'TipDogovor', 'TipDogovorID', 'DanocenBroj', 'ShifraZU', 'ZdravstvenaUstanova',
        'RabotnaEdinica', 'Dejnost', 'Specijalnosti', 'Adresa', 'Mesto', 'Faksimil',
        'Ime', 'Prezime', 'ValidenOd', 'ValidenDo',
    ];

    /**
     * @return Generator<int, FzomRow>
     */
    public function rows(string $path, string $label): Generator
    {
        $reader = XMLReader::open($path, null, LIBXML_NONET | LIBXML_COMPACT);

        if ($reader === false) {
            throw new RuntimeException("Cannot open {$label}.");
        }

        $previous = libxml_use_internal_errors(true);
        $seenRoot = false;

        try {
            while ($this->readOrFail($reader, $label)) {
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }

                if ($reader->depth === 0) {
                    if ($reader->name !== 'Lekari') {
                        throw new RuntimeException("{$label}: unexpected root element <{$reader->name}>.");
                    }

                    $seenRoot = true;

                    continue;
                }

                if ($reader->depth === 1 && $reader->name === 'Lekar') {
                    $row = $this->readRow($reader, $label);

                    if ($row !== null) {
                        yield $row;
                    } else {
                        $this->skipped++;
                    }
                }
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $seenRoot) {
            throw new RuntimeException("{$label}: no <Lekari> root element.");
        }
    }

    private function readOrFail(XMLReader $reader, string $label): bool
    {
        $more = $reader->read();
        $error = libxml_get_last_error();

        if ($error !== false && $error->level >= LIBXML_ERR_ERROR) {
            throw new RuntimeException(sprintf('%s: malformed XML at line %d.', $label, $error->line));
        }

        return $more;
    }

    /**
     * Reads the direct children of the current <Lekar>, keeping text of the
     * whitelisted ones; nested elements (PricinaOtsustvo, RedovnaZamena) are
     * skipped with next() so their content is never read.
     */
    private function readRow(XMLReader $reader, string $label): ?FzomRow
    {
        if ($reader->isEmptyElement) {
            return null;
        }

        $fields = [];
        $depth = $reader->depth;

        $this->readOrFail($reader, $label);

        while (! ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth)) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->depth === $depth + 1) {
                if (in_array($reader->name, self::KEPT, true)) {
                    $fields[$reader->name] = $this->text($reader->readString());
                }

                // On to the next sibling without reading this element's subtree.
                if (! $reader->next()) {
                    throw new RuntimeException("{$label}: truncated <Lekar> element.");
                }

                continue;
            }

            if (! $this->readOrFail($reader, $label)) {
                throw new RuntimeException("{$label}: truncated <Lekar> element.");
            }
        }

        $facsimile = $fields['Faksimil'] ?? null;
        $first = $fields['Ime'] ?? null;
        $last = $fields['Prezime'] ?? null;
        $facilityName = $fields['ZdravstvenaUstanova'] ?? null;

        if ($facsimile === null || ($first === null && $last === null) || $facilityName === null) {
            return null;
        }

        return new FzomRow(
            file: $label,
            contractTypeId: (int) ($fields['TipDogovorID'] ?? 0),
            contractType: $fields['TipDogovor'] ?? '',
            facilityCode: $fields['ShifraZU'] ?? null,
            taxNumber: $fields['DanocenBroj'] ?? null,
            facilityName: $facilityName,
            workUnit: $fields['RabotnaEdinica'] ?? null,
            activity: $fields['Dejnost'] ?? null,
            specialties: $fields['Specijalnosti'] ?? null,
            address: $fields['Adresa'] ?? null,
            town: $fields['Mesto'] ?? null,
            facsimile: $facsimile,
            firstName: $first ?? '',
            lastName: $last ?? '',
            validFrom: $this->date($fields['ValidenOd'] ?? null),
            validTo: $this->date($fields['ValidenDo'] ?? null),
        );
    }

    private function text(string $value): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : $value;
    }

    private function date(?string $value): ?string
    {
        if ($value === null || preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $match) !== 1) {
            return null;
        }

        return $match[1];
    }
}
