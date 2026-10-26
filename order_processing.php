<?php

declare(strict_types=1);

/**
 * Тестовое задание: обработка заказов.
 * Запуск: php orders.php [путь_к_json]
 */

// ---------- Часть 1. Загрузка и валидация ----------

/**
 * Загружает заказы из JSON-файла.
 *
 * @return array{orders: array<int, array>, errors: array<int, string>}
 */
function loadOrders(string $path): array
{
    $errors = [];

    if (!is_file($path) || !is_readable($path)) {
        return ['orders' => [], 'errors' => ["Файл не найден или недоступен: {$path}"]];
    }

    $json = file_get_contents($path);
    if ($json === false) {
        return ['orders' => [], 'errors' => ["Не удалось прочитать файл: {$path}"]];
    }

    try {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        return ['orders' => [], 'errors' => ["Ошибка декодирования JSON: " . $e->getMessage()]];
    }

    if (!is_array($data)) {
        return ['orders' => [], 'errors' => ['Корневой элемент JSON должен быть массивом заказов.']];
    }

    $orders = [];
    $required = ['id', 'customer', 'amount', 'status'];
    $allowedStatuses = ['paid', 'pending', 'cancelled'];

    foreach ($data as $index => $raw) {
        if (!is_array($raw)) {
            $errors[] = "Заказ #{$index}: элемент не является объектом.";
            continue;
        }

        // Проверка обязательных полей
        $missing = array_diff($required, array_keys($raw));
        if ($missing) {
            $errors[] = "Заказ #{$index}: отсутствуют поля: " . implode(', ', $missing);
            continue;
        }

        // Проверка суммы
        if (!is_int($raw['amount']) && !is_float($raw['amount'])) {
            $errors[] = "Заказ #{$raw['id']}: сумма имеет некорректный тип ("
                . gettype($raw['amount']) . ").";
            continue;
        }
        if ($raw['amount'] < 0) {
            $errors[] = "Заказ #{$raw['id']}: отрицательная сумма ({$raw['amount']}).";
            continue;
        }

        // Проверка статуса
        if (!in_array($raw['status'], $allowedStatuses, true)) {
            $errors[] = "Заказ #{$raw['id']}: неизвестный статус '{$raw['status']}'.";
            continue;
        }

        $orders[] = $raw;
    }

    return ['orders' => $orders, 'errors' => $errors];
}

// ---------- Часть 2. Основная логика ----------

function filterPaid(array $orders): array
{
    return array_values(array_filter(
        $orders,
        static fn(array $o): bool => $o['status'] === 'paid'
    ));
}

function totalAmount(array $orders): float
{
    return array_sum(array_column($orders, 'amount'));
}

function mostExpensive(array $orders): ?array
{
    if ($orders === []) {
        return null;
    }
    usort($orders, static fn(array $a, array $b): int => $b['amount'] <=> $a['amount']);
    return $orders[0];
}

// ---------- Часть 3. Дополнительно (Вариант Б) ----------

/**
 * Группировка заказов по статусам.
 *
 * @return array<string, int>
 */
function groupByStatus(array $orders): array
{
    $groups = [];
    foreach ($orders as $order) {
        $groups[$order['status']] = ($groups[$order['status']] ?? 0) + 1;
    }
    ksort($groups);
    return $groups;
}

// ---------- Точка входа ----------

$path = $argv[1] ?? 'orders.json';
$result = loadOrders($path);
$orders = $result['orders'];

// Выводим ошибки валидации (если есть), но продолжаем обработку валидных заказов
if ($result['errors']) {
    fwrite(STDERR, "Предупреждения:\n");
    foreach ($result['errors'] as $err) {
        fwrite(STDERR, "  - {$err}\n");
    }
}

if ($orders === []) {
    fwrite(STDERR, "Нет валидных заказов для обработки.\n");
    exit(1);
}

$paid = filterPaid($orders);
$total = totalAmount($paid);
$top = mostExpensive($paid);

echo "Количество оплаченных заказов: " . count($paid) . PHP_EOL;
echo "Общая сумма оплаченных заказов: " . $total . PHP_EOL;

if ($top !== null) {
    echo "Самый дорогой оплаченный заказ: № {$top['id']} на сумму {$top['amount']}" . PHP_EOL;
} else {
    echo "Оплаченных заказов нет." . PHP_EOL;
}

echo PHP_EOL . "Группировка по статусам:" . PHP_EOL;
foreach (groupByStatus($orders) as $status => $count) {
    echo "  {$status}: {$count}" . PHP_EOL;
}

// Вариант В — сохранение результата в JSON
$summary = [
    'paid_count'        => count($paid),
    'paid_total'        => $total,
    'most_expensive_id' => $top['id'] ?? null,
    'groups'            => groupByStatus($orders),
    'warnings'          => $result['errors'],
];
file_put_contents('result.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo PHP_EOL . "Результат сохранён в result.json" . PHP_EOL;