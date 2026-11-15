<?php
require_once __DIR__ . '/config.php';

function escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$today = (new DateTimeImmutable(
    'now',
    new DateTimeZone('Asia/Colombo')
))->format('Y-m-d');

$fittings = [];
$loadError = '';

try {
    $statement = $pdo->query(
        "SELECT
            orders.id,
            customers.name AS customer_name,
            customers.phone,
            orders.garment,
            orders.fitting_date,
            orders.due_date,
            orders.status
         FROM orders
         INNER JOIN customers
            ON orders.customer_id = customers.id
         WHERE orders.fitting_date IS NOT NULL
           AND orders.status IN (
               'Received', 'Cutting', 'Sewing', 'Fitting'
           )
         ORDER BY orders.fitting_date ASC, orders.id ASC"
    );

    $fittings = $statement->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $loadError = 'Unable to load fittings. Please refresh the page.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TailorTrack | Fittings</title>
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
            <a href="fittings.php" class="active" aria-current="page">
                Fittings
            </a>
            <a href="payments.php">Payments</a>
            <button type="button" disabled>Studio settings</button>
        </nav>

        <p class="sidebar-note">
            Every measurement. Every order. One workspace.
        </p>
    </aside>

    <main class="main-content">
        <header class="page-header">
            <p class="eyebrow">WORKSPACE / FITTINGS</p>
            <h1>Fittings</h1>
            <p class="description">
                Review fitting dates for orders still in progress.
            </p>
        </header>

        <section class="form-panel">
            <h2>Fitting schedule</h2>

            <?php if ($loadError !== ''): ?>
                <p role="alert"><?= escape($loadError) ?></p>

            <?php elseif (empty($fittings)): ?>
                <p class="description">
                    No fitting dates recorded for orders in progress.
                    Open an order and use Edit order to set a fitting date.
                </p>

                <a href="orders.php#order-list" class="button-secondary">
                    View orders
                </a>

            <?php else: ?>
                <p class="description">
                    Scheduled orders: <?= count($fittings) ?>.
                    Past dates may need review.
                </p>

                <div class="table-scroll"
                     role="region"
                     aria-label="Fitting schedule"
                     tabindex="0">
                    <table class="customer-table fitting-table">
                        <thead>
                            <tr>
                                <th scope="col">Order</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Phone</th>
                                <th scope="col">Garment</th>
                                <th scope="col">Fitting date</th>
                                <th scope="col">Timing</th>
                                <th scope="col">Order status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($fittings as $fitting): ?>
                                <?php
                                if ($fitting['fitting_date'] < $today) {
                                    $timing = 'Past date';
                                    $timingClass = 'fitting-past';
                                } elseif ($fitting['fitting_date'] === $today) {
                                    $timing = 'Today';
                                    $timingClass = 'fitting-today';
                                } else {
                                    $timing = 'Upcoming';
                                    $timingClass = 'fitting-upcoming';
                                }
                                ?>

                                <tr>
                                    <td>
                                        <a href="order-details.php?id=<?= (int) $fitting['id'] ?>">
                                            #<?= (int) $fitting['id'] ?>
                                        </a>
                                    </td>
                                    <td><?= escape($fitting['customer_name']) ?></td>
                                    <td class="fitting-nowrap"><?= escape($fitting['phone']) ?></td>
                                    <td><?= escape($fitting['garment']) ?></td>
                                    <td class="fitting-nowrap"><?= escape($fitting['fitting_date']) ?></td>
                                    <td>
                                        <span class="fitting-badge <?= escape($timingClass) ?>">
                                            <?= escape($timing) ?>
                                        </span>
                                    </td>
                                    <td><?= escape($fitting['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>