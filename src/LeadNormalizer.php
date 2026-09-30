<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class LeadNormalizer
{
    public const COLUMNS = [
        'external_id', 'created_at', 'first_name', 'last_name', 'phone',
        'email', 'city', 'source', 'utm_campaign', 'product', 'budget_uah',
        'status', 'manager', 'comment', 'next_contact_at',
    ];

    /** @param array<string, string|null> $row
     *  @return list<string|float|null>
     */
    public function normalize(array $row, int $line): array
    {
        $externalId = $this->text($row['external_id'] ?? null);
        if ($externalId === null) {
            throw new InvalidArgumentException("Строка {$line}: external_id обязателен.");
        }

        $phone = $this->text($row['phone'] ?? null);
        if ($phone !== null && preg_match('/^\d{10,15}$/', $phone)) {
            $phone = '+' . $phone;
        }

        $budget = $this->text($row['budget_uah'] ?? null);

        return [
            $externalId,
            $this->date($row['created_at'] ?? null, $line, 'created_at'),
            $this->text($row['first_name'] ?? null),
            $this->text($row['last_name'] ?? null),
            $phone,
            $this->text($row['email'] ?? null),
            $this->text($row['city'] ?? null),
            $this->text($row['source'] ?? null),
            $this->text($row['utm_campaign'] ?? null),
            $this->text($row['product'] ?? null),
            $budget === null ? null : (float) str_replace(',', '.', $budget),
            $this->text($row['status'] ?? null),
            $this->text($row['manager'] ?? null),
            $this->text($row['comment'] ?? null),
            $this->date($row['next_contact_at'] ?? null, $line, 'next_contact_at'),
        ];
    }

    private function text(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function date(?string $value, int $line, string $field): ?string
    {
        $value = $this->text($value);
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            $serial = (float) $value;
            $days = (int) floor($serial);
            $seconds = (int) round(($serial - $days) * 86400);
            $date = new DateTimeImmutable('1899-12-30 00:00:00', new DateTimeZone('UTC'));
            return $date->modify("+{$days} days")->modify("+{$seconds} seconds")->format('Y-m-d H:i:s');
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            throw new InvalidArgumentException("Строка {$line}: неверная дата в {$field}.");
        }
    }
}

