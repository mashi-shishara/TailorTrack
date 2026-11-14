<?php
require_once __DIR__ . '/config.php';

$summary = null;
$dashboardError = '';

try {
    // Use the shop's local date for overdue orders.
    $today = (new DateTimeImmutable(
        'now',
        new DateTimeZone('Asia/Colombo')
    ))->format('Y-m-d');

    $statement = $pdo->prepare(
        "SELECT
            COUNT(CASE
                WHEN status IN ('Received', 'Cutting', 'Sewing', 'Fitting')
                THEN 1
            END) AS active_orders,

            COUNT(CASE
                WHEN status = 'Ready'
                THEN 1
            END) AS ready_orders,

            COUNT(CASE
                WHEN due_date < :today
                AND status IN ('Received', 'Cutting', 'Sewing', 'Fitting')
                THEN 1
            END) AS overdue_orders
         FROM orders"
    );

    $statement->execute(['today' => $today]);
    $summary = $statement->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $dashboardError = 'Unable to load order totals. Please refresh the page.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>TailorTrack | Dashboard</title>

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
                <a href="index.php" class="active" aria-current="page">
                    Overview
                </a>

                <a href="orders.php">Orders</a>
                <a href="customers.php">Customers</a>
                <button type="button" disabled>Fittings</button>
                <a href="payments.php">Payments</a>
                <button type="button" disabled>Studio settings</button>
            </nav>

            <p class="sidebar-note">
                Every measurement. Every order. One workspace.
            </p>
        </aside>

        <main class="main-content">
            <header class="page-header">
                <p class="eyebrow">WORKSPACE / OVERVIEW</p>
                <h1>Studio overview</h1>
                <p class="description">
                    Keep your tailoring work organised.
                </p>
            </header>

            <section class="welcome-panel">
                <h2>Good work is in the details.</h2>
                <p>
                    Manage your customers, orders and fittings
                    from one place.
                </p>
            </section>

           <section class="summary-section" aria-label="Order summary">

    <article class="summary-card highlight">
        <h2>Active orders</h2>
       <p class="summary-value">
    <?= $summary !== null ? (int) $summary['active_orders'] : '—' ?>
</p>
        <p class="summary-note">Orders currently in progress</p>
    </article>

    <article class="summary-card">
        <h2>Ready for collection</h2>
        <p class="summary-value">
    <?= $summary !== null ? (int) $summary['ready_orders'] : '—' ?>
</p>
        <p class="summary-note">Finished and ready to collect</p>
    </article>

    <article class="summary-card">
        <h2>Overdue orders</h2>
        <p class="summary-value">
    <?= $summary !== null ? (int) $summary['overdue_orders'] : '—' ?>
</p>
        <p class="summary-note">Orders past their delivery date</p>
    </article>

    <article class="summary-card">
        <h2>Outstanding balance</h2>
        <p class="summary-value">LKR —</p>
        <p class="summary-note">Remaining payments to receive</p>
    </article>

</section>

<?php if ($dashboardError !== ''): ?>
    <p class="data-notice" role="alert">
        <?= htmlspecialchars($dashboardError, ENT_QUOTES, 'UTF-8') ?>
    </p>
<?php else: ?>
    <p class="data-notice">
        Order counts are up to date.
        Outstanding balance will be available after payment tracking is added.
    </p>
<?php endif; ?>
        </main>

    </div>

</body>
</html>