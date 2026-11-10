<?php
session_start();

require_once __DIR__ . '/config.php';

function escape($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function input($key)
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$name = '';
$phone = '';
$notes = '';
$errors = [];

$success = $_SESSION['customer_success'] ?? '';
unset($_SESSION['customer_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = input('name');
    $phone = input('phone');
    $notes = input('notes');
    $token = input('csrf_token');

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Please refresh the page and try again.';
    }

    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Enter a name with 1 to 100 characters.';
    }

    // Allow phone numbers with spaces, brackets, hyphens and +.
    $phoneDigits = preg_replace('/[^0-9]/', '', $phone);

    if (
        mb_strlen($phone) > 30 ||
        !preg_match('/^\+?[0-9 ()-]+$/', $phone) ||
        strlen($phoneDigits) < 7 ||
        strlen($phoneDigits) > 15
    ) {
        $errors[] = 'Enter a valid phone number with 7 to 15 digits.';
    }

    if (mb_strlen($notes) > 5000) {
        $errors[] = 'Notes must be 5000 characters or fewer.';
    }

    if (empty($errors)) {
        try {
            $statement = $pdo->prepare(
                'INSERT INTO customers (name, phone, notes)
                 VALUES (:name, :phone, :notes)'
            );

            $statement->execute([
                'name' => $name,
                'phone' => $phone,
                'notes' => $notes
            ]);

            $_SESSION['customer_success'] =
                'Customer saved successfully!';

            // Redirect so refreshing does not repeat the save.
            session_write_close();
              header('Location: customers.php', true, 303);
               exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Unable to save the customer. Please try again.';
        }
    }
}
$customers = [];
$listError = '';

$search = $_GET['search'] ?? '';
$search = is_string($search)
    ? mb_substr(trim($search), 0, 100)
    : '';

try {
    if ($search !== '') {
        // Treat %, _ and ! as ordinary search characters.
        $pattern = '%' . strtr($search, [
            '!' => '!!',
            '%' => '!%',
            '_' => '!_'
        ]) . '%';

        $statement = $pdo->prepare(
            "SELECT id, name, phone, notes
             FROM customers
             WHERE name LIKE :name_search ESCAPE '!'
                OR phone LIKE :phone_search ESCAPE '!'
             ORDER BY id DESC"
        );

        $statement->execute([
            'name_search' => $pattern,
            'phone_search' => $pattern
        ]);
    } else {
        $statement = $pdo->query(
            'SELECT id, name, phone, notes
             FROM customers
             ORDER BY id DESC'
        );
    }

    $customers = $statement->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $listError = 'Unable to load customers. Please refresh the page.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TailorTrack | Customers</title>
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

                <a href="customers.php" class="active"
                   aria-current="page">
                    Customers
                </a>

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
                <p class="eyebrow">WORKSPACE / CUSTOMERS</p>
                <h1>Customers</h1>
                <p class="description">
                    Keep your customer details organised.
                </p>
            </header>

            <section class="form-panel">
                <h2>Add a customer</h2>

                <p id="form-notice" class="description">
                    Enter customer details below.
                    Fields marked * are required.
                </p>

                <?php if ($success !== ''): ?>
    <div id="success-message" role="status"
         style="background: #E7EDDC;
                color: #245E50;
                border: 1px solid #245E50;
                padding: 16px;
                margin: 16px 0;
                border-radius: 8px;">
        <strong><?= escape($success) ?></strong>
    </div>
<?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div role="alert">
                        <p><strong>Please check the following:</strong></p>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= escape($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form id="customer-form" method="post" action="customers.php"
      aria-describedby="form-notice">

                    <input type="hidden" name="csrf_token"
                           value="<?= escape($_SESSION['csrf_token']) ?>">

                    <div class="form-grid">
                        <div class="form-field">
                            <label for="customer-name">Full name *</label>
                            <input
                                type="text"
                                id="customer-name"
                                name="name"
                                value="<?= escape($name) ?>"
                                placeholder="Enter customer name"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div class="form-field">
                            <label for="customer-phone">Phone number *</label>
                            <input
                                type="tel"
                                id="customer-phone"
                                name="phone"
                                value="<?= escape($phone) ?>"
                                placeholder="Example: 0771234567"
                                maxlength="30"
                                required
                            >
                        </div>

                        <div class="form-field full-width">
                            <label for="customer-notes">Notes (optional)</label>
                            <textarea
                                id="customer-notes"
                                name="notes"
                                rows="4"
                                maxlength="5000"
                                placeholder="Preferences or special instructions"
                            ><?= escape($notes) ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="customers.php" class="button-secondary">
                            Clear form
                        </a>

                        <button type="submit" class="button-primary">
                            Save customer
                        </button>
                    </div>
                </form>
            </section>
           <section id="customer-list" class="form-panel"
         aria-labelledby="customer-list-title">
    <h2 id="customer-list-title">Customer list</h2>

   <form method="get" action="customers.php#customer-list"
      class="customer-search">
    <div class="form-field">
        <label for="customer-search">Search customers</label>
        <input
            type="search"
            id="customer-search"
            name="search"
            value="<?= escape($search) ?>"
            placeholder="Enter name or phone number"
            maxlength="100"
        >
    </div>

    <button type="submit" class="button-primary">
        Search
    </button>

    <a href="customers.php#customer-list" class="button-secondary">
    Show all
</a>
</form>

    <?php if ($listError !== ''): ?>
        <p role="alert"><?= escape($listError) ?></p>

    <?php elseif (empty($customers)): ?>
        <p class="description">
           <?= $search !== ''
    ? 'No customers match your search.'
    : 'No customers yet. Add your first customer above.' ?>
        </p>

    <?php else: ?>
        <p class="description">
           <?= $search !== '' ? 'Matching customers' : 'Total customers' ?>:
<?= count($customers) ?>
        </p>

        <div class="table-scroll"
             role="region"
             aria-label="Customer list"
             tabindex="0">
            <table class="customer-table">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Name</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Notes</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?= (int) $customer['id'] ?></td>
                            <td><?= escape($customer['name']) ?></td>
                            <td><?= escape($customer['phone']) ?></td>
                            <td class="customer-notes"><?= escape($customer['notes'] !== ''  ? $customer['notes'] : '—' ) ?></td>
                            <td>
    <a href="edit-customer.php?id=<?= (int) $customer['id'] ?>"
       class="button-secondary">
        Edit
    </a>
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
    const customerForm = document.getElementById('customer-form');
    const successMessage = document.getElementById('success-message');

    if (customerForm && successMessage) {
        customerForm.addEventListener('input', function () {
            successMessage.hidden = true;
        }, { once: true });
    }
</script>
</body>
</html>