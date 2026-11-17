
<?php
require_once __DIR__ . '/auth.php';

// Only the owner can create staff accounts.
if (($currentUser['role'] ?? '') !== 'owner') {
    http_response_code(403);
    exit('Access denied.');
}

$message = '';
$error = '';

if (empty($_SESSION['staff_token'])) {
    $_SESSION['staff_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['staff_token'] ?? '';

    if (!hash_equals($_SESSION['staff_token'], $token)) {
        $error = 'Invalid request. Please refresh the page.';
    } else {

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid name and email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must contain at least 8 characters.';
        } else {

            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $statement = $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, role, is_active)
                     VALUES (:name, :email, :password_hash, :role, 1)'
                );

                $statement->execute([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => $hash,
                    'role' => 'staff'
                ]);

                $message = 'Staff account created successfully!';

                $_SESSION['staff_token'] = bin2hex(random_bytes(32));

            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'This email address is already registered.';
                } else {
                    error_log($e->getMessage());
                    $error = 'Unable to create the staff account.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Staff | TailorTrack</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="main-content">

    <section class="form-panel">

        <h1>Create Staff Account</h1>

        <?php if ($message !== ''): ?>
            <p class="success-alert">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <p role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form method="post">

            <input type="hidden"
                   name="staff_token"
                   value="<?= htmlspecialchars($_SESSION['staff_token'], ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-grid">

                <div class="form-field full-width">
                    <label for="name">Staff Name</label>
                    <input type="text" id="name" name="name"
                           maxlength="100" required>
                </div>

                <div class="form-field full-width">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                           maxlength="190" required>
                </div>

                <div class="form-field full-width">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           minlength="8" required>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" class="button-primary">
                    Create Staff Account
                </button>

                <a href="index.php" class="button-secondary">
                    Back to Dashboard
                </a>
            </div>

        </form>

    </section>

</div>

</body>
</html>
