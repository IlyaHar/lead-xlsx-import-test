<?php

declare(strict_types=1);

namespace App;

use Generator;
use RuntimeException;
use XMLReader;
use ZipArchive;

final class XlsxReader
{
    private const XML_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const MAX_UNCOMPRESSED_BYTES = 160_000_000;

    /** @var list<string> */
    private array $sharedStrings = [];

    private ZipArchive $archive;

    public function __construct(private readonly string $path)
    {
        $this->archive = new ZipArchive();
        if ($this->archive->open($path) !== true) {
            throw new RuntimeException('Файл не является корректным XLSX-архивом.');
        }

        $totalSize = 0;
        for ($i = 0; $i < $this->archive->numFiles; $i++) {
            $stat = $this->archive->statIndex($i);
            $totalSize += (int) ($stat['size'] ?? 0);
            if ($totalSize > self::MAX_UNCOMPRESSED_BYTES) {
                throw new RuntimeException('Распакованный XLSX превышает допустимый размер.');
            }
        }

        if ($this->archive->locateName('xl/worksheets/sheet1.xml') === false) {
            throw new RuntimeException('В книге отсутствует первый лист.');
        }

        $this->loadSharedStrings();
    }

    public function __destruct()
    {
        if (isset($this->archive)) {
            $this->archive->close();
        }
    }

    /** @return Generator<int, array<string, string|null>> */
    public function rows(): Generator
    {
        $reader = $this->openXml('xl/worksheets/sheet1.xml');
        $headers = null;

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                    continue;
                }

                $cells = $this->readRow($reader);
                if ($headers === null) {
                    $headers = [];
                    foreach ($cells as $column => $value) {
                        $name = trim((string) $value);
                        if ($name !== '') {
                            $headers[$column] = $name;
                        }
                    }
                    continue;
                }

                $row = [];
                foreach ($headers as $column => $name) {
                    $row[$name] = $cells[$column] ?? null;
                }
                yield $row;
            }
        } finally {
            $reader->close();
        }
    }

    /** @return array<int, string|null> */
    private function readRow(XMLReader $reader): array
    {
        $rowDepth = $reader->depth;
        $cells = [];

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'row' && $reader->depth === $rowDepth) {
                break;
            }
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'c') {
                continue;
            }

            $reference = $reader->getAttribute('r') ?: '';
            $type = $reader->getAttribute('t') ?: '';
            $column = $this->columnIndex($reference);
            $value = $this->readCellValue($reader);

            if ($type === 's' && $value !== null) {
                $value = $this->sharedStrings[(int) $value] ?? null;
            }
            $cells[$column] = $value;
        }

        return $cells;
    }

    private function readCellValue(XMLReader $reader): ?string
    {
        $cellDepth = $reader->depth;
        $value = null;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'c' && $reader->depth === $cellDepth) {
                break;
            }
            if ($reader->nodeType === XMLReader::ELEMENT && ($reader->localName === 'v' || $reader->localName === 't')) {
                $value = $reader->readString();
            }
        }

        return $value;
    }

    private function loadSharedStrings(): void
    {
        if ($this->archive->locateName('xl/sharedStrings.xml') === false) {
            return;
        }

        $reader = $this->openXml('xl/sharedStrings.xml');
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                    continue;
                }

                $depth = $reader->depth;
                $value = '';
                while ($reader->read()) {
                    if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si' && $reader->depth === $depth) {
                        break;
                    }
                    if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't') {
                        $value .= $reader->readString();
                    }
                }
                $this->sharedStrings[] = $value;
            }
        } finally {
            $reader->close();
        }
    }

    private function openXml(string $entry): XMLReader
    {
        $stream = $this->archive->getStream($entry);
        if ($stream === false) {
            throw new RuntimeException("Не удалось прочитать {$entry}.");
        }

        $temp = tmpfile();
        if ($temp === false) {
            fclose($stream);
            throw new RuntimeException('Не удалось создать временный файл.');
        }
        stream_copy_to_stream($stream, $temp);
        fclose($stream);

        $metadata = stream_get_meta_data($temp);
        $reader = new XMLReader();
        if (!$reader->open($metadata['uri'], null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            fclose($temp);
            throw new RuntimeException("Не удалось разобрать {$entry}.");
        }

        // XMLReader keeps the underlying temporary file open until close().
        return $reader;
    }

    private function columnIndex(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)/', $reference, $matches)) {
            return 0;
        }

        $index = 0;
        foreach (str_split($matches[1]) as $letter) {
            $index = $index * 26 + ord($letter) - 64;
        }
        return $index - 1;
    }
}

