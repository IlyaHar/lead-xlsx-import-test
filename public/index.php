<?php

declare(strict_types=1);

use App\Database;
use App\LeadImporter;

require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/XlsxReader.php';
require dirname(__DIR__) . '/src/LeadNormalizer.php';
require dirname(__DIR__) . '/src/LeadImporter.php';

$result = null;
$error = null;
$total = null;

try {
    $database = Database::connect();
    $total = (int) $database->query('SELECT COUNT(*) FROM leads')->fetchColumn();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $upload = $_FILES['file'] ?? null;
        if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Выберите XLSX-файл размером до 32 МБ.');
        }
        if (($upload['size'] ?? 0) > 32 * 1024 * 1024) {
            throw new RuntimeException('Размер файла превышает 32 МБ.');
        }
        if (strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new RuntimeException('Поддерживаются только файлы .xlsx.');
        }

        $result = (new LeadImporter($database))->import((string) $upload['tmp_name']);
        $total = $result['total'];
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Импорт заявок</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; color: #eef1ff; background: radial-gradient(circle at 15% 10%, #27345f 0, transparent 33%), #0c1020; }
        main { width: min(680px, calc(100% - 32px)); padding: 42px; border: 1px solid #2e385c; border-radius: 24px; background: rgba(17, 23, 43, .92); box-shadow: 0 28px 80px #05071299; }
        .eyebrow { color: #91a7ff; font-size: 13px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        h1 { margin: 10px 0 12px; font-size: clamp(32px, 6vw, 48px); line-height: 1.05; }
        p { color: #aeb7d4; line-height: 1.6; }
        .count { margin: 28px 0; padding: 18px 20px; border-radius: 14px; background: #0c1124; }
        .count strong { display: block; color: white; font-size: 28px; }
        form { display: grid; gap: 14px; }
        input[type=file] { width: 100%; padding: 16px; border: 1px dashed #58658f; border-radius: 12px; background: #121831; color: #cbd2ea; }
        button { min-height: 52px; border: 0; border-radius: 12px; color: #081027; background: #9db0ff; font: inherit; font-weight: 800; cursor: pointer; }
        button:hover { background: #b3c1ff; }
        .message { margin: 22px 0 0; padding: 16px 18px; border-radius: 12px; line-height: 1.55; }
        .success { color: #b9f6d3; background: #123526; border: 1px solid #246344; }
        .error { color: #ffc3c3; background: #3c1c25; border: 1px solid #71313f; }
        small { color: #7f89aa; }
    </style>
</head>
<body>
<main>
    <div class="eyebrow">CRM / XLSX</div>
    <h1>Импорт заявок</h1>
    <p>Загрузите исходную Excel-книгу. Данные читаются потоком и записываются пакетами по 500 строк.</p>

    <div class="count"><small>Заявок в базе</small><strong><?= number_format((int) $total, 0, ',', ' ') ?></strong></div>

    <form method="post" enctype="multipart/form-data">
        <input type="file" name="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
        <button type="submit">Импортировать</button>
    </form>

    <?php if ($result !== null): ?>
        <div class="message success">
            Импортировано строк: <strong><?= number_format($result['processed'], 0, ',', ' ') ?></strong>.<br>
            Время: <?= number_format($result['seconds'], 2, ',', ' ') ?> с · пик памяти: <?= number_format($result['peak_memory_mb'], 1, ',', ' ') ?> МБ.
        </div>
    <?php elseif ($error !== null): ?>
        <div class="message error"><?= e($error) ?></div>
    <?php endif; ?>
</main>
</body>
</html>

