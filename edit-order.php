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

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid order ID.');
}

try {
    $statement = $pdo->prepare(
        'SELECT orders.*, customers.name AS customer_name
         FROM orders
         INNER JOIN customers ON orders.customer_id = customers.id
         WHERE orders.id = :id'
    );
    $statement->execute(['id' => $id]);
    $order = $statement->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to load order.');
}

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$values = [
    'garment' => $order['garment'],
    'quantity' => (string) $order['quantity'],
    'unit_price' => $order['unit_price'],
    'fitting_date' => $order['fitting_date'] ?? '',
    'due_date' => $order['due_date'],
    'measurements' => $order['measurements'],
    'instructions' => $order['instructions']
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $value) {
        $values[$key] = input($key);
    }

    if (!hash_equals($_SESSION['csrf_token'], input('csrf_token'))) {
        $errors[] = 'Please refresh the page and try again.';
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

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Use the same order lock as payment recording.
            $statement = $pdo->prepare(
                'SELECT id FROM orders WHERE id = :id FOR UPDATE'
            );
            $statement->execute(['id' => $id]);

            if (!$statement->fetch()) {
                $pdo->rollBack();
                $errors[] = 'This order no longer exists.';
            } else {
                // Compare the new total with payments using exact decimals.
                $statement = $pdo->prepare(
                    'SELECT
                        (
                            CAST(:quantity AS DECIMAL(10,0)) *
                            CAST(:price AS DECIMAL(12,2))
                        ) >= COALESCE(SUM(amount), 0) AS allowed
                     FROM payments
                     WHERE order_id = :order_id'
                );

                $statement->execute([
                    'quantity' => $quantity,
                    'price' => $values['unit_price'],
                    'order_id' => $id
                ]);

                if ((int) $statement->fetchColumn() !== 1) {
                    $pdo->rollBack();
                    $errors[] = 'The new order total cannot be less than payments already received.';
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE orders SET
                            garment = :garment,
                            quantity = :quantity,
                            unit_price = :unit_price,
                            fitting_date = :fitting_date,
                            due_date = :due_date,
                            measurements = :measurements,
                            instructions = :instructions
                         WHERE id = :id'
                    );

                    $statement->execute([
                        'garment' => $values['garment'],
                        'quantity' => $quantity,
                        'unit_price' => $values['unit_price'],
                        'fitting_date' => $values['fitting_date'] === ''
                            ? null : $values['fitting_date'],
                        'due_date' => $values['due_date'],
                        'measurements' => $values['measurements'],
                        'instructions' => $values['instructions'],
                        'id' => $id
                    ]);

                    $pdo->commit();

                    $_SESSION['order_success'] =
                        'Order #' . $id . ' updated successfully!';

                    session_write_close();
                    header('Location: orders.php', true, 303);
                    exit;
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $errors[] = 'Unable to update order. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TailorTrack | Edit order</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="main-content">
    <header class="page-header">
        <p class="eyebrow">WORKSPACE / ORDERS / EDIT</p>
        <h1>Edit order #<?= (int) $id ?></h1>
        <p class="description">
            Customer: <?= escape($order['customer_name']) ?>
        </p>
    </header>

    <section class="form-panel">
        <h2>Order details</h2>
        <p class="description">Fields marked * are required.</p>

        <?php if (!empty($errors)): ?>
            <div role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="edit-order.php?id=<?= (int) $id ?>">
            <input type="hidden" name="csrf_token"
                   value="<?= escape($_SESSION['csrf_token']) ?>">

            <div class="form-grid">
                <div class="form-field full-width">
                    <label for="garment">Garment *</label>
                    <input type="text" id="garment" name="garment"
                           value="<?= escape($values['garment']) ?>"
                           maxlength="100" required>
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
                           min="0" max="9999999999.99" step="0.01" required>
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
                              rows="4" maxlength="5000"><?= escape($values['measurements']) ?></textarea>
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
                <a href="order-details.php?id=<?= (int) $id ?>"
                   class="button-secondary">
                    Cancel
                </a>

                <button type="submit" class="button-primary">
                    Save changes
                </button>
            </div>
        </form>
    </section>
</main>
</body>
</html>