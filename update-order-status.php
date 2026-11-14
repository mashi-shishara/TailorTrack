<?php
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
    exit('Invalid request. Return to Orders, refresh and try again.');
}

$id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$status = $_POST['status'] ?? '';

$allowedStatuses = [
    'Received',
    'Cutting',
    'Sewing',
    'Fitting',
    'Ready',
    'Collected',
    'Cancelled'
];

if (
    !$id ||
    $id < 1 ||
    !is_string($status) ||
    !in_array($status, $allowedStatuses, true)
) {
    http_response_code(400);
    exit('Invalid order or status.');
}

try {
    $statement = $pdo->prepare(
        'SELECT id FROM orders WHERE id = :id'
    );
    $statement->execute(['id' => $id]);

    if (!$statement->fetch()) {
        http_response_code(404);
        exit('Order not found.');
    }

    $statement = $pdo->prepare(
        'UPDATE orders SET status = :status WHERE id = :id'
    );

    $statement->execute([
        'status' => $status,
        'id' => $id
    ]);

    $_SESSION['order_status_success'] =
        'Order #' . $id . ' status updated to ' . $status . '.';

    session_write_close();
    header('Location: orders.php#order-list', true, 303);
    exit;
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to update status. Return to Orders and try again.');
}