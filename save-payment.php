<?php
session_start();

require_once __DIR__ . '/config.php';

function input($key)
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function paymentError($message)
{
    $_SESSION['payment_error'] = $message;
    session_write_close();
    header('Location: payments.php', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: payments.php');
    exit;
}

$token = input('payment_token');

if (
    empty($_SESSION['payment_token']) ||
    !hash_equals($_SESSION['payment_token'], $token)
) {
    paymentError(
        'This form has expired or was already submitted. Check the balance before trying again.'
    );
}

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$amount = input('amount');
$method = input('method');
$note = input('note');

if (!$orderId || $orderId < 1) {
    paymentError('Select a valid order.');
}

if (
    !preg_match('/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/', $amount) ||
    !preg_match('/[1-9]/', $amount)
) {
    paymentError('Enter an amount greater than zero, with up to 2 decimal places.');
}

if (!in_array($method, ['Cash', 'Bank transfer'], true)) {
    paymentError('Select a valid payment method.');
}

if (mb_strlen($note) > 250) {
    paymentError('The note must be 250 characters or fewer.');
}

try {
    $pdo->beginTransaction();

    // Lock this order while checking and recording its payment.
    $statement = $pdo->prepare(
        'SELECT id, status,
                (quantity * unit_price) AS total
         FROM orders
         WHERE id = :id
         FOR UPDATE'
    );

    $statement->execute(['id' => $orderId]);
    $order = $statement->fetch();

    if (!$order || $order['status'] === 'Cancelled') {
        $pdo->rollBack();
        paymentError('This order is unavailable for payment.');
    }

    $statement = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0) AS total_paid
         FROM payments
         WHERE order_id = :order_id'
    );

    $statement->execute(['order_id' => $orderId]);
    $totalPaid = $statement->fetchColumn();

    // Compare exact decimal values in the database.
    $statement = $pdo->prepare(
        'SELECT
            CAST(:amount AS DECIMAL(12,2)) <=
            (
                CAST(:total AS DECIMAL(24,2)) -
                CAST(:paid AS DECIMAL(24,2))
            ) AS allowed'
    );

    $statement->execute([
        'amount' => $amount,
        'total' => $order['total'],
        'paid' => $totalPaid
    ]);

    if ((int) $statement->fetchColumn() !== 1) {
        $pdo->rollBack();
        paymentError('Payment exceeds the outstanding balance. Check the balance and enter the amount again.');
    }

    $statement = $pdo->prepare(
        'INSERT INTO payments (order_id, amount, method, note, paid_at)
         VALUES (:order_id, :amount, :method, :note, :paid_at)'
    );

    $paidAt = (new DateTimeImmutable(
        'now',
        new DateTimeZone('Asia/Colombo')
    ))->format('Y-m-d H:i:s');

    $statement->execute([
        'order_id' => $orderId,
        'amount' => $amount,
        'method' => $method,
        'note' => $note,
        'paid_at' => $paidAt
    ]);

    $pdo->commit();

    // A successful submission cannot reuse the same form token.
    unset($_SESSION['payment_token']);

    $_SESSION['payment_success'] =
        'Payment recorded successfully for order #' . $orderId . '.';

    session_write_close();
    header('Location: payments.php', true, 303);
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    paymentError(
        'Unable to confirm payment. Check payment records before trying again.'
    );
}