<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;
use Throwable;

final class LeadImporter
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly PDO $database,
        private readonly LeadNormalizer $normalizer = new LeadNormalizer(),
    ) {
    }

    /** @return array{processed:int,total:int,seconds:float,peak_memory_mb:float} */
    public function import(string $path): array
    {
        $startedAt = hrtime(true);
        $lock = (int) $this->database->query("SELECT GET_LOCK('lead_import_xlsx', 2)")->fetchColumn();
        if ($lock !== 1) {
            throw new RuntimeException('Другой импорт уже выполняется. Повторите попытку позже.');
        }

        $processed = 0;
        $batch = [];
        $this->database->beginTransaction();

        try {
            $reader = new XlsxReader($path);
            foreach ($reader->rows() as $row) {
                $processed++;
                $batch[] = $this->normalizer->normalize($row, $processed + 1);
                if (count($batch) === self::BATCH_SIZE) {
                    $this->writeBatch($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $this->writeBatch($batch);
            }
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $exception;
        } finally {
            $this->database->query("SELECT RELEASE_LOCK('lead_import_xlsx')");
        }

        $total = (int) $this->database->query('SELECT COUNT(*) FROM leads')->fetchColumn();

        return [
            'processed' => $processed,
            'total' => $total,
            'seconds' => (hrtime(true) - $startedAt) / 1_000_000_000,
            'peak_memory_mb' => memory_get_peak_usage(true) / 1024 / 1024,
        ];
    }

    /** @param list<list<string|float|null>> $rows */
    private function writeBatch(array $rows): void
    {
        $columns = LeadNormalizer::COLUMNS;
        $rowPlaceholder = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $placeholders = implode(',', array_fill(0, count($rows), $rowPlaceholder));
        $updates = implode(', ', array_map(
            static fn (string $column): string => "{$column}=new.{$column}",
            array_slice($columns, 1),
        ));

        $sql = sprintf(
            'INSERT INTO leads (%s) VALUES %s AS new ON DUPLICATE KEY UPDATE %s, imported_at=CURRENT_TIMESTAMP',
            implode(',', $columns),
            $placeholders,
            $updates,
        );

        $values = [];
        foreach ($rows as $row) {
            array_push($values, ...$row);
        }
        $this->database->prepare($sql)->execute($values);
    }
}
