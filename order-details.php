<?php
require_once __DIR__ . '/auth.php';
session_start();
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
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$alterationMessage = $_SESSION['alteration_success'] ?? '';
unset($_SESSION['alteration_success']);

$alterationError = '';
$description = '';
$alterations = [];
$alterationListError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $submittedDescription = $_POST['description'] ?? '';

    $description = is_string($submittedDescription)
        ? trim($submittedDescription)
        : '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $alterationError = 'Please refresh the page and try again.';
    } elseif ($description === '' || mb_strlen($description) > 250) {
        $alterationError = 'Enter an alteration with 1 to 250 characters.';
    } else {
        try {
            $statement = $pdo->prepare(
                'INSERT INTO alterations (order_id, description)
                 VALUES (:order_id, :description)'
            );

            $statement->execute([
                'order_id' => $id,
                'description' => $description
            ]);

            $_SESSION['alteration_success'] =
                'Alteration added successfully!';

            session_write_close();
            header(
                'Location: order-details.php?id=' . $id . '#alterations',
                true,
                303
            );
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $alterationError = 'Unable to save alteration. Please try again.';
        }
    }
}

try {
    $statement = $pdo->prepare(
        'SELECT id, description, done
         FROM alterations
         WHERE order_id = :order_id
         ORDER BY done ASC, id DESC'
    );

    $statement->execute(['order_id' => $id]);
    $alterations = $statement->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $alterationListError = 'Unable to load alterations.';
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
        <a href="edit-order.php?id=<?= (int) $id ?>"
   class="button-secondary">
    Edit order
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
        <section id="alterations" class="form-panel"
         aria-labelledby="alterations-title">
    <h2 id="alterations-title">Fitting alterations</h2>

    <p class="description">
        Record changes needed for this order.
    </p>

    <?php if ($alterationMessage !== ''): ?>
        <p class="success-alert" role="status">
            <?= escape($alterationMessage) ?>
        </p>
    <?php endif; ?>

    <?php if ($alterationError !== ''): ?>
        <p role="alert">
            <?= escape($alterationError) ?>
        </p>
    <?php endif; ?>

    <form method="post"
          action="order-details.php?id=<?= (int) $id ?>#alterations">

        <input type="hidden" name="csrf_token"
               value="<?= escape($_SESSION['csrf_token']) ?>">

        <div class="form-field">
            <label for="alteration-description">
                Required alteration *
            </label>

            <textarea
                id="alteration-description"
                name="description"
                rows="3"
                maxlength="250"
                placeholder="Example: Shorten sleeves by 1 inch"
                required
            ><?= escape($description) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="button-primary">
                Add alteration
            </button>
        </div>
    </form>

    <h3>Alteration checklist</h3>

    <?php if ($alterationListError !== ''): ?>
        <p role="alert"><?= escape($alterationListError) ?></p>

    <?php elseif (empty($alterations)): ?>
        <p class="description">
            No alterations recorded for this order.
        </p>

    <?php else: ?>
        <div class="table-scroll"
             role="region"
             aria-label="Alterations for this order"
             tabindex="0">
            <table class="customer-table">
                <thead>
                    <tr>
                        <th scope="col">Alteration</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($alterations as $alteration): ?>
                        <tr>
                            <td class="detail-text"><?= escape($alteration['description']) ?></td>
                            <td>
                                <?= (int) $alteration['done'] === 1
                                    ? 'Completed'
                                    : 'Pending' ?>
                            </td>
                            <td>
    <form method="post" action="update-alteration.php">
        <input type="hidden" name="csrf_token"
               value="<?= escape($_SESSION['csrf_token']) ?>">

        <input type="hidden" name="order_id"
               value="<?= (int) $id ?>">

        <input type="hidden" name="alteration_id"
               value="<?= (int) $alteration['id'] ?>">

        <input type="hidden" name="done"
               value="<?= (int) $alteration['done'] === 1 ? '0' : '1' ?>">

        <button type="submit" class="button-secondary">
            <?= (int) $alteration['done'] === 1
                ? 'Mark pending'
                : 'Mark completed' ?>
        </button>
    </form>
</td>
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