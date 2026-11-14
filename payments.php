<?php
session_start();

require_once __DIR__ . '/config.php';

if (empty($_SESSION['payment_token'])) {
    $_SESSION['payment_token'] = bin2hex(random_bytes(32));
}

$paymentSuccess = $_SESSION['payment_success'] ?? '';
$paymentError = $_SESSION['payment_error'] ?? '';

unset($_SESSION['payment_success'], $_SESSION['payment_error']);

function escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$orders = [];
$loadError = '';

try {
    $statement = $pdo->query(
        "SELECT
            orders.id,
            customers.name AS customer_name,
            orders.garment,
            (orders.quantity * orders.unit_price)
                - COALESCE(paid.total_paid, 0) AS balance
         FROM orders
         INNER JOIN customers
            ON orders.customer_id = customers.id
         LEFT JOIN (
            SELECT order_id, SUM(amount) AS total_paid
            FROM payments
            GROUP BY order_id
         ) AS paid ON paid.order_id = orders.id
         WHERE orders.status <> 'Cancelled'
           AND (orders.quantity * orders.unit_price)
                > COALESCE(paid.total_paid, 0)
         ORDER BY orders.id DESC"
    );

    $orders = $statement->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $loadError = 'Unable to load orders. Please refresh the page.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TailorTrack | Payments</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-layout">
    <aside class="sidebar">
        <div class="brand">
            <h2>TailorTrack</h2>
            <p>Your studio, in sync.</p>
        </div>

        <nav class="navigation" aria-label="Main navigation">
            <a href="index.php">Overview</a>
            <a href="orders.php">Orders</a>
            <a href="customers.php">Customers</a>
            <button type="button" disabled>Fittings</button>
            <a href="payments.php" class="active" aria-current="page">
                Payments
            </a>
            <button type="button" disabled>Studio settings</button>
        </nav>

        <p class="sidebar-note">
            Every measurement. Every order. One workspace.
        </p>
    </aside>

    <main class="main-content">
        <header class="page-header">
            <p class="eyebrow">WORKSPACE / PAYMENTS</p>
            <h1>Payments</h1>
            <p class="description">
                Record payments received for your orders.
            </p>
        </header>

        <section class="form-panel">
            <h2>Record a payment</h2>

            <p id="payment-notice" class="description">
    Record money already received. Fields marked * are required.
</p>

<?php if ($paymentSuccess !== ''): ?>
    <p class="success-alert" role="status">
        <?= escape($paymentSuccess) ?>
    </p>
<?php endif; ?>

<?php if ($paymentError !== ''): ?>
    <p role="alert">
        <strong><?= escape($paymentError) ?></strong>
    </p>
<?php endif; ?>

            <?php if ($loadError !== ''): ?>
                <p role="alert"><?= escape($loadError) ?></p>
            <?php elseif (empty($orders)): ?>
                <p class="description">
                    No orders with an outstanding balance.
                </p>
            <?php endif; ?>

            <form id="payment-form" method="post" action="save-payment.php"
      aria-describedby="payment-notice">

    <input type="hidden" name="payment_token"
           value="<?= escape($_SESSION['payment_token']) ?>">
                <div class="form-grid">
                    <div class="form-field full-width">
                        <label for="payment-order">Order *</label>

                        <select id="payment-order" name="order_id" required>
                            <option value="">Select an order</option>

                            <?php foreach ($orders as $order): ?>
                                <option
                                    value="<?= (int) $order['id'] ?>"
                                    data-balance="<?= escape($order['balance']) ?>"
                                >
                                    <?= escape(
                                        '#' . $order['id'] . ' — ' .
                                        $order['customer_name'] . ' — ' .
                                        $order['garment']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field full-width">
                        <label for="payment-balance">Outstanding balance</label>
                        <output id="payment-balance"
                                for="payment-order"
                                aria-live="polite">LKR —</output>
                    </div>

                    <div class="form-field">
                        <label for="payment-amount">Amount received (LKR) *</label>
                        <input type="number" id="payment-amount" name="amount"
                               min="0.01" max="9999999999.99" step="0.01"
                               placeholder="Example: 1000.00" required>
                    </div>

                    <div class="form-field">
                        <label for="payment-method">Payment method *</label>
                        <select id="payment-method" name="method" required>
                            <option value="Cash">Cash</option>
                            <option value="Bank transfer">Bank transfer</option>
                        </select>
                    </div>

                    <div class="form-field full-width">
                        <label for="payment-note">Note (optional)</label>
                        <input type="text" id="payment-note" name="note"
                               maxlength="250"
                               placeholder="Example: Advance payment">
                    </div>
                </div>

                <div class="form-actions">
                    <a href="payments.php" class="button-secondary">
                        Clear form
                    </a>

                    <button type="submit" class="button-primary"
        <?= empty($orders) || $loadError !== '' ? 'disabled' : '' ?>>
    Save payment
</button>
                </div>
            </form>
        </section>
    </main>
</div>

<script>
    const orderSelect = document.getElementById('payment-order');
    const balanceOutput = document.getElementById('payment-balance');

    function showBalance() {
        const selected = orderSelect.options[orderSelect.selectedIndex];
        const balance = selected ? selected.dataset.balance : '';

        balanceOutput.textContent = balance ? `LKR ${balance}` : 'LKR —';
    }

    orderSelect.addEventListener('change', showBalance);
    showBalance();
</script>
</body>
</html>