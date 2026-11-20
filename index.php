
<?php
require_once __DIR__ . '/auth.php';

$summary = null;
$dashboardError = '';

$userName = $currentUser['name'] ?? 'User';
$userRole = $currentUser['role'] ?? 'staff';
$isOwner = ($userRole === 'owner');

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
            WHEN due_date < :overdue_today
            AND status IN ('Received', 'Cutting', 'Sewing', 'Fitting')
            THEN 1
        END) AS overdue_orders,

        COUNT(CASE
            WHEN due_date = :due_today
            AND status IN ('Received', 'Cutting', 'Sewing', 'Fitting')
            THEN 1
        END) AS due_today_orders,

        COUNT(CASE
            WHEN due_date = :due_tomorrow
            AND status IN ('Received', 'Cutting', 'Sewing', 'Fitting')
            THEN 1
        END) AS due_tomorrow_orders

     FROM orders"
);

$tomorrow = (new DateTimeImmutable(
    $today,
    new DateTimeZone('Asia/Colombo')
))->modify('+1 day')->format('Y-m-d');

$statement->execute([
    'overdue_today' => $today,
    'due_today' => $today,
    'due_tomorrow' => $tomorrow
]);

$summary = $statement->fetch();


} catch (PDOException $e) {
    error_log($e->getMessage());
    $dashboardError = 'Unable to load order totals. Please refresh the page.';
}

$outstandingBalance = null;
$balanceError = '';

try {
    $statement = $pdo->query(
        "SELECT COALESCE(
            SUM(
                GREATEST(
                    (orders.quantity * orders.unit_price)
                    - COALESCE(paid.total_paid, 0),
                    0
                )
            ),
            0.00
        ) AS outstanding_balance
        FROM orders
        LEFT JOIN (
            SELECT order_id, SUM(amount) AS total_paid
            FROM payments
            GROUP BY order_id
        ) AS paid ON paid.order_id = orders.id
        WHERE orders.status <> 'Cancelled'"
    );

    $outstandingBalance = $statement->fetchColumn();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $balanceError = 'Unable to load outstanding balance.';
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

            <a href="fittings.php">Fittings</a>

            <a href="payments.php">Payments</a>

            <?php if ($isOwner): ?>
                <a href="staff.php">Staff Management</a>
            <?php endif; ?>

            <button type="button" disabled>
                Studio settings
            </button>

        </nav>

        <form method="post" action="logout.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['csrf_token'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <button type="submit" class="button-secondary">
                Log out
            </button>

        </form>

        <p class="sidebar-note">
            Every measurement. Every order. One workspace.
        </p>

    </aside>


    <main class="main-content">

        <header class="page-header">

            <p class="eyebrow">WORKSPACE / OVERVIEW</p>

            <h1>Studio overview</h1>

            <p class="description">
                Welcome, <?= htmlspecialchars(
                    $userName,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>!
            </p>

            <p class="description">
                Account role:
                <strong>
                    <?= htmlspecialchars(
                        ucfirst($userRole),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
            </p>

        </header>


        <section class="welcome-panel">

            <?php if ($isOwner): ?>

                <h2>Welcome back, Studio Owner!</h2>

                <p>
                    Manage your studio, staff, customers,
                    orders and payments from one place.
                </p>

            <?php else: ?>

                <h2>Welcome to TailorTrack!</h2>

                <p>
                    Manage your daily tailoring work,
                    customers, orders and fittings.
                </p>

            <?php endif; ?>

        </section>


        <section class="summary-section" aria-label="Order summary">

            <article class="summary-card highlight">

                <h2>Active orders</h2>

                <p class="summary-value">
                    <?= $summary !== null
                        ? (int) $summary['active_orders']
                        : '—' ?>
                </p>

                <p class="summary-note">
                    Orders currently in progress
                </p>

            </article>


            <article class="summary-card">

                <h2>Ready for collection</h2>

                <p class="summary-value">
                    <?= $summary !== null
                        ? (int) $summary['ready_orders']
                        : '—' ?>
                </p>

                <p class="summary-note">
                    Finished and ready to collect
                </p>

            </article>


            <article class="summary-card">

                <h2>Overdue orders</h2>

                <p class="summary-value">
                    <?= $summary !== null
                        ? (int) $summary['overdue_orders']
                        : '—' ?>
                </p>

                <p class="summary-note">
                    Orders past their delivery date
                </p>

            </article>

            <article class="summary-card">

                <h2>Due today</h2>

                <p class="summary-value">
                    <?= $summary !== null
                        ? (int) $summary['due_today_orders']
                        : '—' ?>
                </p>

                <p class="summary-note">
                    Orders that must be completed today
                </p>

            </article>

            <article class="summary-card">

                <h2>Due tomorrow</h2>

                <p class="summary-value">
                    <?= $summary !== null
                        ? (int) $summary['due_tomorrow_orders']
                        : '—' ?>
                </p>

                <p class="summary-note">
                    Orders with deadlines tomorrow
                </p>

            </article>



            <?php if ($isOwner): ?>

    <article class="summary-card">

        <h2>Outstanding balance</h2>

        <p class="summary-value">
            LKR <?= $outstandingBalance !== null
                ? htmlspecialchars(
                    (string) $outstandingBalance,
                    ENT_QUOTES,
                    'UTF-8'
                )
                : '—' ?>
        </p>

        <p class="summary-note">
            Remaining payments to receive
        </p>

    </article>

<?php endif; ?>

        </section>


        <?php if ($dashboardError !== '' || $balanceError !== ''): ?>

            <p class="data-notice" role="alert">
                <?= htmlspecialchars(
                    trim($dashboardError . ' ' . $balanceError),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

        <?php else: ?>

            <p class="data-notice">
                Order counts and outstanding balance update
                when this page loads.
            </p>

        <?php endif; ?>

    </main>

</div>

</body>
</html>
