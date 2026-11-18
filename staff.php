
<?php
require_once __DIR__ . '/auth.php';

// Only the owner can access staff management.
if (($currentUser['role'] ?? '') !== 'owner') {
    http_response_code(403);
    exit('Access denied.');
}

function escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';

if (empty($_SESSION['staff_management_token'])) {
    $_SESSION['staff_management_token'] = bin2hex(random_bytes(32));
}

// Handle staff management actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['staff_token'] ?? '';

    if (!hash_equals($_SESSION['staff_management_token'], $token)) {

        $error = 'Invalid request. Please refresh the page.';

    } else {

        $action = $_POST['action'] ?? '';
        $staffId = filter_var(
            $_POST['staff_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        try {

            if ($action === 'create') {

                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';

                if ($name === '' || strlen($name) > 100 ||
                    !filter_var($email, FILTER_VALIDATE_EMAIL)) {

                    $error = 'Enter a valid name and email address.';

                } elseif (strlen($password) < 8) {

                    $error = 'Password must contain at least 8 characters.';

                } else {

                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    $statement = $pdo->prepare(
                        'INSERT INTO users
                         (name, email, password_hash, role, is_active)
                         VALUES (:name, :email, :password_hash, :role, 1)'
                    );

                    $statement->execute([
                        'name' => $name,
                        'email' => $email,
                        'password_hash' => $hash,
                        'role' => 'staff'
                    ]);

                    $message = 'Staff account created successfully!';
                }

            } elseif ($action === 'edit' && $staffId) {

                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');

                if ($name === '' || strlen($name) > 100 ||
                    !filter_var($email, FILTER_VALIDATE_EMAIL)) {

                    $error = 'Enter valid staff details.';

                } else {

                    $statement = $pdo->prepare(
                        "UPDATE users
                         SET name = :name, email = :email
                         WHERE id = :id AND role = 'staff'"
                    );

                    $statement->execute([
                        'name' => $name,
                        'email' => $email,
                        'id' => $staffId
                    ]);

                    $message = 'Staff details updated successfully!';
                }

            } elseif ($action === 'toggle' && $staffId) {

                $statement = $pdo->prepare(
                    "UPDATE users
                     SET is_active = CASE
                         WHEN is_active = 1 THEN 0
                         ELSE 1
                     END
                     WHERE id = :id AND role = 'staff'"
                );

                $statement->execute(['id' => $staffId]);

                $message = 'Staff account status updated successfully!';

            } else {

                $error = 'Invalid action.';

            }

        } catch (PDOException $e) {

            error_log($e->getMessage());

            if ($e->getCode() === '23000') {
                $error = 'This email address is already registered.';
            } else {
                $error = 'Unable to complete the staff management action.';
            }
        }
    }

    $_SESSION['staff_management_token'] = bin2hex(random_bytes(32));
}

// Load staff accounts.
$staffMembers = [];

try {

    $statement = $pdo->query(
        "SELECT id, name, email, is_active, created_at
         FROM users
         WHERE role = 'staff'
         ORDER BY id DESC"
    );

    $staffMembers = $statement->fetchAll();

} catch (PDOException $e) {

    error_log($e->getMessage());
    $error = 'Unable to load staff accounts.';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>TailorTrack | Staff Management</title>

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

            <a href="fittings.php">Fittings</a>

            <a href="payments.php">Payments</a>

            <a href="staff.php" class="active" aria-current="page">
                Staff Management
            </a>

            <button type="button" disabled>
                Studio settings
            </button>

        </nav>

        <p class="sidebar-note">
            Every measurement. Every order. One workspace.
        </p>

    </aside>


    <main class="main-content">

        <header class="page-header">

            <p class="eyebrow">WORKSPACE / STAFF</p>

            <h1>Staff Management</h1>

            <p class="description">
                Manage your studio staff accounts.
            </p>

        </header>


        <?php if ($message !== ''): ?>

            <p class="success-alert" role="status">
                <?= escape($message) ?>
            </p>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <p role="alert">
                <strong><?= escape($error) ?></strong>
            </p>

        <?php endif; ?>


        <!-- Create Staff -->

        <section class="form-panel">

            <h2>Add New Staff</h2>

            <form method="post">

                <input type="hidden"
                       name="staff_token"
                       value="<?= escape($_SESSION['staff_management_token']) ?>">

                <input type="hidden"
                       name="action"
                       value="create">

                <div class="form-grid">

                    <div class="form-field full-width">

                        <label for="staff-name">Staff Name *</label>

                        <input type="text"
                               id="staff-name"
                               name="name"
                               maxlength="100"
                               required>

                    </div>


                    <div class="form-field full-width">

                        <label for="staff-email">Email Address *</label>

                        <input type="email"
                               id="staff-email"
                               name="email"
                               maxlength="190"
                               required>

                    </div>


                    <div class="form-field full-width">

                        <label for="staff-password">Password *</label>

                        <input type="password"
                               id="staff-password"
                               name="password"
                               minlength="8"
                               required>

                    </div>

                </div>


                <div class="form-actions">

                    <button type="submit" class="button-primary">
                        Create Staff Account
                    </button>

                </div>

            </form>

        </section>


        <!-- Staff List -->

        <section class="form-panel">

            <h2>Staff Accounts</h2>

            <p class="description">
                Total staff accounts: <?= count($staffMembers) ?>
            </p>


            <?php if (empty($staffMembers)): ?>

                <p class="description">
                    No staff accounts found.
                </p>

            <?php else: ?>

                <div class="table-scroll"
                     role="region"
                     aria-label="Staff accounts"
                     tabindex="0">

                    <table class="customer-table">

                        <thead>

                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Created</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($staffMembers as $staff): ?>

                                <tr>

                                    <td>

                                        <form method="post"
                                              id="edit-form-<?= (int) $staff['id'] ?>">

                                            <input type="hidden"
                                                   name="staff_token"
                                                   value="<?= escape($_SESSION['staff_management_token']) ?>">

                                            <input type="hidden"
                                                   name="action"
                                                   value="edit">

                                            <input type="hidden"
                                                   name="staff_id"
                                                   value="<?= (int) $staff['id'] ?>">

                                        </form>

                                        <input type="text"
                                               name="name"
                                               value="<?= escape($staff['name']) ?>"
                                               maxlength="100"
                                               required
                                               form="edit-form-<?= (int) $staff['id'] ?>">

                                    </td>


                                    <td>

                                        <input type="email"
                                               name="email"
                                               value="<?= escape($staff['email']) ?>"
                                               maxlength="190"
                                               required
                                               form="edit-form-<?= (int) $staff['id'] ?>">

                                    </td>


                                    <td>
                                        <?= escape($staff['created_at']) ?>
                                    </td>


                                    <td>

                                        <?php if ((int) $staff['is_active'] === 1): ?>

                                            <span>Active</span>

                                        <?php else: ?>

                                            <span>Inactive</span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <div class="form-actions">

                                            <button type="submit"
                                                    class="button-primary"
                                                    form="edit-form-<?= (int) $staff['id'] ?>">
                                                Save
                                            </button>


                                            <form method="post">

                                                <input type="hidden"
                                                       name="staff_token"
                                                       value="<?= escape($_SESSION['staff_management_token']) ?>">

                                                <input type="hidden"
                                                       name="action"
                                                       value="toggle">

                                                <input type="hidden"
                                                       name="staff_id"
                                                       value="<?= (int) $staff['id'] ?>">

                                                <?php if ((int) $staff['is_active'] === 1): ?>

                                                    <button type="submit"
                                                            class="button-secondary">
                                                        Deactivate
                                                    </button>

                                                <?php else: ?>

                                                    <button type="submit"
                                                            class="button-secondary">
                                                        Activate
                                                    </button>

                                                <?php endif; ?>

                                            </form>

                                        </div>

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

</body>
</html>
