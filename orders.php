<?php
session_start();

require_once __DIR__ . '/config.php';

function escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function input($key)
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function validDate($value)
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

    return $date !== false
        && $date->format('Y-m-d') === $value
        && $value >= '1000-01-01'
        && $value <= '9999-12-31';
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = $_SESSION['order_success'] ?? '';
unset($_SESSION['order_success']);

$customers = [];
$loadError = '';
$errors = [];

$values = [
    'customer_id' => '',
    'garment' => '',
    'quantity' => '1',
    'unit_price' => '',
    'fitting_date' => '',
    'due_date' => '',
    'measurements' => '',
    'instructions' => ''
];

try {
    $statement = $pdo->query(
        'SELECT id, name, phone FROM customers ORDER BY name, id'
    );
    $customers = $statement->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $loadError = 'Unable to load customers. Please refresh the page.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $default) {
        $values[$key] = input($key);
    }

    if (!hash_equals($_SESSION['csrf_token'], input('csrf_token'))) {
        $errors[] = 'Please refresh the page and try again.';
    }

    $customerIds = array_map('strval', array_column($customers, 'id'));

    if (!in_array($values['customer_id'], $customerIds, true)) {
        $errors[] = 'Select an existing customer.';
    }

    if (
        $values['garment'] === '' ||
        mb_strlen($values['garment']) > 100
    ) {
        $errors[] = 'Enter a garment name with 1 to 100 characters.';
    }

    $quantity = filter_var(
        $values['quantity'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 10000]]
    );

    if ($quantity === false) {
        $errors[] = 'Quantity must be a whole number from 1 to 10000.';
    }

    // Validate money as decimal text, without rounding it.
    if (!preg_match(
        '/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/',
        $values['unit_price']
    )) {
        $errors[] = 'Enter a valid unit price with up to 2 decimal places.';
    }

    if (!validDate($values['due_date'])) {
        $errors[] = 'Enter a valid delivery date.';
    }

    if ($values['fitting_date'] !== '') {
        if (!validDate($values['fitting_date'])) {
            $errors[] = 'Enter a valid fitting date.';
        } elseif (
            validDate($values['due_date']) &&
            $values['fitting_date'] > $values['due_date']
        ) {
            $errors[] = 'Fitting date cannot be after delivery date.';
        }
    }

    if (
        mb_strlen($values['measurements']) > 5000 ||
        mb_strlen($values['instructions']) > 5000
    ) {
        $errors[] = 'Measurements and instructions must each be 5000 characters or fewer.';
    }

    if (empty($errors) && $loadError === '') {
        try {
            $statement = $pdo->prepare(
                'INSERT INTO orders
                 (customer_id, garment, quantity, unit_price,
                  measurements, instructions, fitting_date, due_date)
                 VALUES
                 (:customer_id, :garment, :quantity, :unit_price,
                  :measurements, :instructions, :fitting_date, :due_date)'
            );

            $statement->execute([
                'customer_id' => $values['customer_id'],
                'garment' => $values['garment'],
                'quantity' => $quantity,
                'unit_price' => $values['unit_price'],
                'measurements' => $values['measurements'],
                'instructions' => $values['instructions'],
                'fitting_date' => $values['fitting_date'] === ''
                    ? null
                    : $values['fitting_date'],
                'due_date' => $values['due_date']
            ]);

            $_SESSION['order_success'] = 'Order saved successfully!';

            session_write_close();
            header('Location: orders.php', true, 303);
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Unable to save order. Please try again.';
        }
    }
}
$orders = [];
$orderListError = '';

try {
    $statement = $pdo->query(
        'SELECT
            orders.id,
            customers.name AS customer_name,
            orders.garment,
            orders.quantity,
            orders.unit_price,
            (orders.quantity * orders.unit_price) AS total,
            orders.due_date,
            orders.status
         FROM orders
         INNER JOIN customers
            ON orders.customer_id = customers.id
         ORDER BY orders.id DESC'
    );

    $orders = $statement->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $orderListError = 'Unable to load orders. Please refresh the page.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TailorTrack | Orders</title>
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
            <a href="orders.php" class="active" aria-current="page">
                Orders
            </a>
            <a href="customers.php">Customers</a>
            <button type="button" disabled>Fittings</button>
            <button type="button" disabled>Payments</button>
            <button type="button" disabled>Studio settings</button>
        </nav>

        <p class="sidebar-note">
            Every measurement. Every order. One workspace.
        </p>
    </aside>

    <main class="main-content">
        <header class="page-header">
            <p class="eyebrow">WORKSPACE / ORDERS</p>
            <h1>Orders</h1>
            <p class="description">
                Keep track of your tailoring orders.
            </p>
        </header>

       <section class="form-panel">
    <h2>Add an order</h2>

    <p id="order-notice" class="description">
        Fields marked * are required.
        Use the same measurements and unit price for all items in this order.
    </p>

    <?php if ($success !== ''): ?>
       <p id="order-success" class="success-alert" role="status">
            <strong><?= escape($success) ?></strong>
        </p>
    <?php endif; ?>

    <?php if ($loadError !== ''): ?>
        <p role="alert"><?= escape($loadError) ?></p>
    <?php elseif (empty($customers)): ?>
        <p>
            Add a customer before creating an order.
            <a href="customers.php">Add customer</a>
        </p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form id="order-form" method="post" action="orders.php"
          aria-describedby="order-notice">

        <input type="hidden" name="csrf_token"
               value="<?= escape($_SESSION['csrf_token']) ?>">

        <div class="form-grid">
            <div class="form-field full-width">
                <label for="order-customer">Customer *</label>
                <select id="order-customer" name="customer_id" required>
                    <option value="">Select a customer</option>

                    <?php foreach ($customers as $customer): ?>
                        <option
                            value="<?= (int) $customer['id'] ?>"
                            <?= (string) $customer['id'] === $values['customer_id']
                                ? 'selected' : '' ?>
                        >
                            <?= escape(
                                $customer['name'] . ' — ' . $customer['phone']
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field full-width">
                <label for="garment">Garment *</label>
                <input type="text" id="garment" name="garment"
                       value="<?= escape($values['garment']) ?>"
                       maxlength="100"
                       placeholder="Example: School uniform shirt" required>
            </div>

            <div class="form-field">
                <label for="quantity">Quantity *</label>
                <input type="number" id="quantity" name="quantity"
                       value="<?= escape($values['quantity']) ?>"
                       min="1" max="10000" step="1" required>
            </div>

            <div class="form-field">
                <label for="unit-price">Unit price (LKR) *</label>
                <input type="number" id="unit-price" name="unit_price"
                       value="<?= escape($values['unit_price']) ?>"
                       min="0" max="9999999999.99" step="0.01"
                       placeholder="Example: 1500.00" required>
            </div>

            <div class="form-field full-width">
                <label for="order-total">Order total</label>
                <output id="order-total" for="quantity unit-price"
                        aria-live="polite">LKR —</output>
            </div>

            <div class="form-field">
                <label for="fitting-date">Fitting date (optional)</label>
                <input type="date" id="fitting-date" name="fitting_date"
                       value="<?= escape($values['fitting_date']) ?>">
            </div>

            <div class="form-field">
                <label for="due-date">Delivery date *</label>
                <input type="date" id="due-date" name="due_date"
                       value="<?= escape($values['due_date']) ?>" required>
            </div>

            <div class="form-field full-width">
                <label for="measurements">
                    Measurements (include inches or cm)
                </label>
                <textarea id="measurements" name="measurements"
                          rows="4" maxlength="5000"
                          placeholder="Example: Chest: 36 inches"><?= escape($values['measurements']) ?></textarea>
            </div>

            <div class="form-field full-width">
                <label for="instructions">
                    Special instructions (optional)
                </label>
                <textarea id="instructions" name="instructions"
                          rows="3" maxlength="5000"><?= escape($values['instructions']) ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <a href="orders.php" class="button-secondary">Clear form</a>

            <button type="submit" class="button-primary"
                    <?= empty($customers) || $loadError !== ''
                        ? 'disabled' : '' ?>>
                Save order
            </button>
        </div>
    </form>
</section>
<section id="order-list" class="form-panel"
         aria-labelledby="order-list-title">
    <h2 id="order-list-title">Order list</h2>

    <?php if ($orderListError !== ''): ?>
        <p role="alert"><?= escape($orderListError) ?></p>

    <?php elseif (empty($orders)): ?>
        <p class="description">
            No orders yet. Add your first order above.
        </p>

    <?php else: ?>
        <p class="description">
            Total orders: <?= count($orders) ?>
        </p>

        <div class="table-scroll"
             role="region"
             aria-label="Order list"
             tabindex="0">
            <table class="customer-table order-table">
                <thead>
                    <tr>
                        <th scope="col">Order ID</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Garment</th>
                        <th scope="col">Quantity</th>
                        <th scope="col">Total (LKR)</th>
                        <th scope="col">Delivery date</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?= (int) $order['id'] ?></td>
                            <td><?= escape($order['customer_name']) ?></td>
                            <td><?= escape($order['garment']) ?></td>
                            <td><?= (int) $order['quantity'] ?></td>
                            <td class="order-amount"><?= escape($order['total']) ?></td>
                            <td class="order-date"><?= escape($order['due_date']) ?></td>
                            <td>
                                <span class="order-status">
                                    <?= escape($order['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
    </main>
</div>
<script>
    const orderForm = document.getElementById('order-form');
    const quantityField = document.getElementById('quantity');
    const priceField = document.getElementById('unit-price');
    const totalOutput = document.getElementById('order-total');
    const successMessage = document.getElementById('order-success');

    function updateTotal() {
        const quantityText = quantityField.value;
        const priceText = priceField.value;

        if (
            !/^[0-9]+$/.test(quantityText) ||
            !/^[0-9]{1,10}(?:\.[0-9]{1,2})?$/.test(priceText)
        ) {
            totalOutput.textContent = 'LKR —';
            return;
        }

        const quantity = Number(quantityText);

        if (quantity < 1 || quantity > 10000) {
            totalOutput.textContent = 'LKR —';
            return;
        }

        // Calculate in cents to avoid decimal rounding errors.
        const [whole, fraction = ''] = priceText.split('.');
        const cents = BigInt(whole) * 100n
            + BigInt(fraction.padEnd(2, '0'));

        const totalCents = cents * BigInt(quantity);
        const rupees = (totalCents / 100n).toLocaleString('en-LK');
        const remainder = (totalCents % 100n).toString().padStart(2, '0');

        totalOutput.textContent = `LKR ${rupees}.${remainder}`;
    }

    quantityField.addEventListener('input', updateTotal);
    priceField.addEventListener('input', updateTotal);

    if (successMessage) {
        orderForm.addEventListener('input', function () {
            successMessage.hidden = true;
        }, { once: true });
    }

    updateTotal();
</script>
</body>
</html>