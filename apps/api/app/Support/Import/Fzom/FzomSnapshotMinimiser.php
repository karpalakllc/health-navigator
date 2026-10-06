<?php

namespace App\Support\Import\Fzom;

use RuntimeException;
use XMLReader;
use XMLWriter;

/**
 * Rewrites a downloaded ФЗОМ file into the snapshot we keep: the same
 * <Lekari>/<Lekar> structure with only the elements FzomXmlReader reads.
 *
 * Never stored, not even in the private audit snapshot: nurses' names
 * (ClenNaTim), absence reasons and validity status (PricinaOtsustvo,
 * StatusValidnostID — can reveal sick or maternity leave), substitutions
 * (RedovnaZamena, VZamena*), and pharmacy rows (excluded contract types).
 * The original's sha256 and size are kept in the run's metadata instead.
 *
 * Streams with XMLReader / XMLWriter, one <Lekar> in memory at a time.
 */
final class FzomSnapshotMinimiser
{
    public function minimise(string $from, string $to, string $label): void
    {
        $reader = XMLReader::open($from, null, LIBXML_NONET | LIBXML_COMPACT);

        if ($reader === false) {
            throw new RuntimeException("Cannot open {$label}.");
        }

        $writer = new XMLWriter;

        if (! $writer->openUri($to)) {
            $reader->close();

            throw new RuntimeException("Cannot write the {$label} snapshot.");
        }

        $previous = libxml_use_internal_errors(true);
        $excludedTypes = array_map('intval', (array) config('import.fzom.excluded_contract_types'));
        $seenRoot = false;

        try {
            $writer->startDocument('1.0', 'UTF-8');
            $writer->startElement('Lekari');

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

                if (in_array((int) ($fields['TipDogovorID'] ?? 0), $excludedTypes, true)) {
                    continue;
                }

                $writer->startElement('Lekar');

                foreach ($fields as $name => $value) {
                    $writer->writeElement($name, $value);
                }

                $writer->endElement();
            }

            if (! $seenRoot) {
                throw new RuntimeException("{$label}: no <Lekari> root element.");
            }

            $writer->endElement();
            $writer->endDocument();
        } finally {
            $writer->flush();
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * The whitelisted direct children of the current <Lekar>, in document
     * order; every other subtree is skipped unread.
     *
     * @return array<string, string>
     */
    private function keptChildren(XMLReader $reader, string $label): array
    {
        $fields = [];
        $depth = $reader->depth;

        $this->read($reader, $label);

        while (! ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth)) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->depth === $depth + 1) {
                if (in_array($reader->name, FzomXmlReader::KEPT, true)) {
                    $fields[$reader->name] = $reader->readString();
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
