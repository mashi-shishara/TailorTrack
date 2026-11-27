
<?php
require_once __DIR__ . '/auth.php';

// Both Owner and Staff can update order status.

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
    $pdo->beginTransaction();

    // Get current status.
    $statement = $pdo->prepare(
        'SELECT id, status FROM orders WHERE id = :id FOR UPDATE'
    );

    $statement->execute(['id' => $id]);
    $order = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $pdo->rollBack();
        http_response_code(404);
        exit('Order not found.');
    }

    $oldStatus = $order['status'];

    // Save only when the status actually changes.
    if ($oldStatus !== $status) {

        // Update order status.
        $statement = $pdo->prepare(
            'UPDATE orders SET status = :status WHERE id = :id'
        );

        $statement->execute([
            'status' => $status,
            'id' => $id
        ]);

        // Save status history.
        $statement = $pdo->prepare(
            'INSERT INTO order_status_history
             (order_id, old_status, new_status, changed_by)
             VALUES (:order_id, :old_status, :new_status, :changed_by)'
        );

        $statement->execute([
            'order_id' => $id,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'changed_by' => $currentUser['id']
        ]);
    }

    $pdo->commit();

    $_SESSION['order_status_success'] =
        $oldStatus === $status
            ? 'Order #' . $id . ' already has this status.'
            : 'Order #' . $id . ' status updated to ' . $status . '.';

    session_write_close();
    header('Location: orders.php#order-list', true, 303);
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to update status. Return to Orders and try again.');
}
