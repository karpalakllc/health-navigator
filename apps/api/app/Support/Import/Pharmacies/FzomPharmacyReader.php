<?php

namespace App\Support\Import\Pharmacies;

use RuntimeException;
use XMLReader;
use XMLWriter;

/**
 * Reads the pharmacy contract units out of ФЗОМ's „Шифрарник на лекари“
 * (the same XML import:fzom reads): the <Lekar> rows whose TipDogovorID is
 * a pharmacy contract (import.pharmacies.contract_types).
 *
 * ФЗОМ lists one row per pharmacist and contract unit. Only the unit's
 * institution fields are read — code, tax number, legal and branch name,
 * address, town, contract end — and rows are folded into one per ФЗО code
 * (ShifraZU): a branch. The pharmacists' names and facsimiles are never
 * read (XMLReader skips their elements unread), nor kept in the snapshot.
 */
final class FzomPharmacyReader
{
    /** Child elements we read, by name. Everything else is skipped. */
    public const KEPT = [
        'TipDogovorID', 'DanocenBroj', 'ShifraZU', 'ZdravstvenaUstanova', 'RabotnaEdinica', 'Adresa', 'Mesto', 'ValidenDo',
    ];

    /** Rows of a pharmacy contract without a ФЗО code or a name, in the last read. */
    public int $skipped = 0;

    /**
     * @return array<string, array{code: string, tax_number: string|null, legal_name: string, branch: string, address: string|null, town: string|null, valid_to: string|null}> by ФЗО code
     */
    public function branches(string $path, string $label): array
    {
        $types = array_map('intval', (array) config('import.pharmacies.contract_types', [4]));
        $branches = [];
        $this->skipped = 0;

        $reader = XMLReader::open($path, null, LIBXML_NONET | LIBXML_COMPACT);

        if ($reader === false) {
            throw new RuntimeException("Cannot open {$label}.");
        }

        $previous = libxml_use_internal_errors(true);
        $seenRoot = false;

        try {
            while ($this->read($reader, $label)) {
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

                if ($reader->depth !== 1 || $reader->name !== 'Lekar' || $reader->isEmptyElement) {
                    continue;
                }

                $fields = $this->keptChildren($reader, $label);

                if (! in_array((int) ($fields['TipDogovorID'] ?? 0), $types, true)) {
                    continue;
                }

                $code = $fields['ShifraZU'] ?? null;
                $legal = $fields['ZdravstvenaUstanova'] ?? null;

                if ($code === null || $legal === null) {
                    $this->skipped++;

                    continue;
                }

                $validTo = isset($fields['ValidenDo']) && preg_match('/^(\d{4}-\d{2}-\d{2})/', $fields['ValidenDo'], $m) === 1 ? $m[1] : null;
                $known = $branches[$code] ?? null;

                // A unit's fields are the same on every row; the contract end
                // is per pharmacist: the latest one.
                $branches[$code] = [
                    'code' => $code,
                    'tax_number' => $known['tax_number'] ?? ($fields['DanocenBroj'] ?? null),
                    'legal_name' => $known['legal_name'] ?? $legal,
                    'branch' => $known['branch'] ?? ($fields['RabotnaEdinica'] ?? $legal),
                    'address' => $known['address'] ?? ($fields['Adresa'] ?? null),
                    'town' => $known['town'] ?? ($fields['Mesto'] ?? null),
                    'valid_to' => max((string) ($known['valid_to'] ?? ''), (string) $validTo) ?: null,
                ];
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $seenRoot) {
            throw new RuntimeException("{$label}: no <Lekari> root element.");
        }

        ksort($branches, SORT_STRING);

        return $branches;
    }

    /**
     * The snapshot we keep: one <Lekar> per branch with the fields above
     * only (no person). Readable by branches() again.
     */
    public function minimise(string $from, string $to, string $label): void
    {
        $branches = $this->branches($from, $label);
        $type = (string) (((array) config('import.pharmacies.contract_types', [4]))[0] ?? 4);
        $writer = new XMLWriter;

        if (! $writer->openUri($to)) {
            throw new RuntimeException("Cannot write the {$label} snapshot.");
        }

        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('Lekari');

        foreach ($branches as $branch) {
            $writer->startElement('Lekar');
            $writer->writeElement('TipDogovorID', $type);

            foreach ([
                'DanocenBroj' => $branch['tax_number'], 'ShifraZU' => $branch['code'], 'ZdravstvenaUstanova' => $branch['legal_name'],
                'RabotnaEdinica' => $branch['branch'], 'Adresa' => $branch['address'], 'Mesto' => $branch['town'], 'ValidenDo' => $branch['valid_to'],
            ] as $name => $value) {
                if ($value !== null) {
                    $writer->writeElement($name, $value);
                }
            }

            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();
        $writer->flush();
    }

    /**
     * @return array<string, string>
     */
    private function keptChildren(XMLReader $reader, string $label): array
    {
        $fields = [];
        $depth = $reader->depth;

        $this->read($reader, $label);

        while (! ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth)) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->depth === $depth + 1) {
                if (in_array($reader->name, self::KEPT, true)) {
                    $value = trim((string) preg_replace('/\s+/u', ' ', $reader->readString()));

                    if ($value !== '') {
                        $fields[$reader->name] = $value;
                    }
                }

                if (! $reader->next()) {
                    throw new RuntimeException("{$label}: truncated <Lekar> element.");
                }

                continue;
            }

            if (! $this->read($reader, $label)) {
                throw new RuntimeException("{$label}: truncated <Lekar> element.");
            }
        }

        return $fields;
    }

    private function read(XMLReader $reader, string $label): bool
    {
        $more = $reader->read();
        $error = libxml_get_last_error();

        if ($error !== false && $error->level >= LIBXML_ERR_ERROR) {
            throw new RuntimeException(sprintf('%s: malformed XML at line %d.', $label, $error->line));
        }

        return $more;
    }
}
