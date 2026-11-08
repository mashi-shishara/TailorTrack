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

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid customer ID.');
}

try {
    $statement = $pdo->prepare(
        'SELECT id, name, phone, notes FROM customers WHERE id = :id'
    );
    $statement->execute(['id' => $id]);
    $customer = $statement->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to load customer. Please try again.');
}

if (!$customer) {
    http_response_code(404);
    exit('Customer not found.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$name = $customer['name'];
$phone = $customer['phone'];
$notes = $customer['notes'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = input('name');
    $phone = input('phone');
    $notes = input('notes');

    if (!hash_equals($_SESSION['csrf_token'], input('csrf_token'))) {
        $errors[] = 'Please refresh the page and try again.';
    }

    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Enter a name with 1 to 100 characters.';
    }

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
                'UPDATE customers
                 SET name = :name, phone = :phone, notes = :notes
                 WHERE id = :id'
            );

            $statement->execute([
                'name' => $name,
                'phone' => $phone,
                'notes' => $notes,
                'id' => $id
            ]);

            $_SESSION['customer_success'] =
                'Customer updated successfully!';

            session_write_close();
            header('Location: customers.php', true, 303);
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = 'Unable to update customer. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TailorTrack | Edit customer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="main-content">
        <header class="page-header">
            <p class="eyebrow">WORKSPACE / CUSTOMERS / EDIT</p>
            <h1>Edit customer</h1>
            <p class="description">
                Update customer details below.
                Fields marked * are required.
            </p>
        </header>

        <section class="form-panel">
            <h2>Customer details</h2>

            <?php if (!empty($errors)): ?>
                <div role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post"
                  action="edit-customer.php?id=<?= (int) $id ?>">

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
                        ><?= escape($notes) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="customers.php#customer-list"
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