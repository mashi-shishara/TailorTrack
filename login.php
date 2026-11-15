<?php
session_start();

require_once __DIR__ . '/config.php';

function escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['login_token'])) {
    $_SESSION['login_token'] = bin2hex(random_bytes(32));
}

$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = $_POST['email'] ?? '';
    $email = is_string($emailInput) ? trim($emailInput) : '';
    $password = $_POST['password'] ?? '';
    $token = $_POST['login_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['login_token'], $token)
    ) {
        $error = 'Please refresh the page and try again.';
    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        mb_strlen($email) > 190 ||
        !is_string($password) ||
        $password === '' ||
        strlen($password) > 72
    ) {
        $error = 'Incorrect email or password.';
    } else {
        try {
            $statement = $pdo->prepare(
                'SELECT id, password_hash, is_active
                 FROM users WHERE email = :email'
            );

            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            if (
                $user &&
                password_verify($password, $user['password_hash']) &&
                (int) $user['is_active'] === 1
            ) {
                session_regenerate_id(true);

                // Start a clean authenticated session.
                $_SESSION = [
                    'user_id' => (int) $user['id'],
                    'csrf_token' => bin2hex(random_bytes(32))
                ];

                session_write_close();
                header('Location: index.php', true, 303);
                exit;
            }

            $error = 'Incorrect email or password.';
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Unable to log in. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TailorTrack | Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="main-content">
    <section class="form-panel">
        <h1>Welcome to TailorTrack</h1>
        <p class="description">Log in to manage your studio.</p>

        <?php if ($error !== ''): ?>
            <p role="alert"><?= escape($error) ?></p>
        <?php endif; ?>

        <form method="post" action="login.php">
            <input type="hidden" name="login_token"
                   value="<?= escape($_SESSION['login_token']) ?>">

            <div class="form-grid">
                <div class="form-field full-width">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="email"
                           value="<?= escape($email) ?>"
                           maxlength="190"
                           autocomplete="username" required>
                </div>

                <div class="form-field full-width">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password"
                           name="password"
                           autocomplete="current-password" required>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="button-primary">
                    Log in
                </button>
            </div>
        </form>
    </section>
</main>
</body>
</html>