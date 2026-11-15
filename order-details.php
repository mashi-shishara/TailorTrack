<?php
require_once __DIR__ . '/config.php';

function escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid order ID.');
}

try {
    $statement = $pdo->prepare(
        'SELECT
            orders.*,
            customers.name AS customer_name,
            customers.phone AS customer_phone,
            (orders.quantity * orders.unit_price) AS total,
            COALESCE(paid.total_paid, 0.00) AS total_paid,
            (orders.quantity * orders.unit_price)
                - COALESCE(paid.total_paid, 0.00) AS balance
         FROM orders
         INNER JOIN customers
            ON orders.customer_id = customers.id
         LEFT JOIN (
            SELECT order_id, SUM(amount) AS total_paid
            FROM payments
            GROUP BY order_id
         ) AS paid ON paid.order_id = orders.id
         WHERE orders.id = :id'
    );

    $statement->execute(['id' => $id]);
    $order = $statement->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to load order details. Please try again.');
}

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$payments = [];
$paymentError = '';

try {
    $statement = $pdo->prepare(
        'SELECT id, amount, method, note, paid_at
         FROM payments
         WHERE order_id = :order_id
         ORDER BY paid_at DESC, id DESC'
    );

    $statement->execute(['order_id' => $id]);
    $payments = $statement->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $paymentError = 'Unable to load payment history.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TailorTrack | Order #<?= (int) $id ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="main-content">
        <a href="orders.php#order-list" class="button-secondary">
            Back to orders
        </a>

        <header class="page-header">
            <p class="eyebrow">WORKSPACE / ORDERS / DETAILS</p>
            <h1>Order #<?= (int) $id ?></h1>
            <p class="description">
                <?= escape($order['customer_name']) ?>
                — <?= escape($order['garment']) ?>
            </p>
        </header>

        <section class="form-panel">
            <h2>Order information</h2>

            <dl class="order-details-grid">
                <div>
                    <dt>Customer</dt>
                    <dd><?= escape($order['customer_name']) ?></dd>
                </div>

                <div>
                    <dt>Phone</dt>
                    <dd><?= escape($order['customer_phone']) ?></dd>
                </div>

                <div>
                    <dt>Garment</dt>
                    <dd><?= escape($order['garment']) ?></dd>
                </div>

                <div>
                    <dt>Status</dt>
                    <dd>
                        <span class="order-status">
                            <?= escape($order['status']) ?>
                        </span>
                    </dd>
                </div>

                <div>
                    <dt>Quantity</dt>
                    <dd><?= (int) $order['quantity'] ?></dd>
                </div>

                <div>
                    <dt>Unit price</dt>
                    <dd>LKR <?= escape($order['unit_price']) ?></dd>
                </div>

                <div>
                    <dt>Fitting date</dt>
                    <dd><?= escape($order['fitting_date'] ?? 'Not scheduled') ?></dd>
                </div>

                <div>
                    <dt>Delivery date</dt>
                    <dd><?= escape($order['due_date']) ?></dd>
                </div>

                <div class="full-width">
                    <dt>Measurements</dt>
                    <dd class="detail-text"><?= escape($order['measurements'] !== '' ? $order['measurements'] : 'Not recorded') ?></dd>
                </div>

                <div class="full-width">
                    <dt>Special instructions</dt>
                    <dd class="detail-text"><?= escape($order['instructions'] !== '' ? $order['instructions'] : 'None') ?></dd>
                </div>
            </dl>
        </section>

        <section class="form-panel">
            <h2>Payment summary</h2>

            <dl class="order-details-grid">
                <div>
                    <dt>Order total</dt>
                    <dd>LKR <?= escape($order['total']) ?></dd>
                </div>

                <div>
                    <dt>Total paid</dt>
                    <dd>LKR <?= escape($order['total_paid']) ?></dd>
                </div>

                <div>
                    <dt>Order total minus payments</dt>
                    <dd><strong>LKR <?= escape($order['balance']) ?></strong></dd>
                </div>
            </dl>

            <?php if ($order['status'] === 'Cancelled'): ?>
                <p class="description">
                    This order is cancelled. The figures above show
                    recorded charges and payments, not a refund calculation.
                </p>
            <?php endif; ?>

            <h3>Payment history</h3>

            <?php if ($paymentError !== ''): ?>
                <p role="alert"><?= escape($paymentError) ?></p>

            <?php elseif (empty($payments)): ?>
                <p class="description">No payments recorded for this order.</p>

            <?php else: ?>
                <div class="table-scroll"
                     role="region"
                     aria-label="Payments for this order"
                     tabindex="0">
                    <table class="customer-table">
                        <thead>
                            <tr>
                                <th scope="col">Payment ID</th>
                                <th scope="col">Amount (LKR)</th>
                                <th scope="col">Method</th>
                                <th scope="col">Date / Time</th>
                                <th scope="col">Note</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>#<?= (int) $payment['id'] ?></td>
                                    <td><?= escape($payment['amount']) ?></td>
                                    <td><?= escape($payment['method']) ?></td>
                                    <td class="payment-date"><?= escape($payment['paid_at']) ?></td>
                                    <td><?= escape($payment['note'] !== '' ? $payment['note'] : '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>