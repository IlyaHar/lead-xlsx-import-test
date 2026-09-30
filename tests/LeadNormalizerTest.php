<?php

declare(strict_types=1);

use App\LeadNormalizer;

require dirname(__DIR__) . '/src/LeadNormalizer.php';

$normalizer = new LeadNormalizer();
$row = array_fill_keys(LeadNormalizer::COLUMNS, null);
$row['external_id'] = 'LD-000001';
$row['created_at'] = '45848.213125';
$row['phone'] = '380672341057';
$row['budget_uah'] = '23700.0';
$row['next_contact_at'] = '45861.213125';

$actual = $normalizer->normalize($row, 2);
$expected = [
    'LD-000001', '2025-07-10 05:06:54', null, null, '+380672341057',
    null, null, null, null, null, 23700.0, null, null, null, '2025-07-23 05:06:54',
];

if ($actual !== $expected) {
    fwrite(STDERR, "LeadNormalizer test failed\n" . var_export($actual, true) . "\n");
    exit(1);
}

echo "LeadNormalizer test passed\n";

