<?php
require_once __DIR__ . '/auth.php';
session_start();

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';

if (
    !is_string($token) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    http_response_code(403);
    exit('Invalid request. Return to the order and refresh the page.');
}

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$alterationId = filter_input(INPUT_POST, 'alteration_id', FILTER_VALIDATE_INT);
$done = $_POST['done'] ?? '';

if (
    !$orderId || $orderId < 1 ||
    !$alterationId || $alterationId < 1 ||
    !in_array($done, ['0', '1'], true)
) {
    http_response_code(400);
    exit('Invalid alteration details.');
}

try {
    // Confirm that this alteration belongs to the specified order.
    $statement = $pdo->prepare(
        'SELECT id FROM alterations
         WHERE id = :id AND order_id = :order_id'
    );

    $statement->execute([
        'id' => $alterationId,
        'order_id' => $orderId
    ]);

    if (!$statement->fetch()) {
        http_response_code(404);
        exit('Alteration not found for this order.');
    }

    $statement = $pdo->prepare(
        'UPDATE alterations
         SET done = :done
         WHERE id = :id AND order_id = :order_id'
    );

    $statement->execute([
        'done' => (int) $done,
        'id' => $alterationId,
        'order_id' => $orderId
    ]);

    $_SESSION['alteration_success'] = $done === '1'
        ? 'Alteration marked as completed.'
        : 'Alteration marked as pending.';

    session_write_close();

    header(
        'Location: order-details.php?id=' . $orderId . '#alterations',
        true,
        303
    );
    exit;
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to update alteration. Return to the order and try again.');
}