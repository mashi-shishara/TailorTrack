<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config.php';

$userId = $_SESSION['user_id'] ?? null;

if (!is_int($userId) || $userId < 1) {
    header('Location: login.php');
    exit;
}

try {
    $statement = $pdo->prepare(
        'SELECT id, name, email, role
         FROM users
         WHERE id = :id AND is_active = 1'
    );

    $statement->execute(['id' => $userId]);
    $currentUser = $statement->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(503);
    exit('Unable to verify your account. Please try again.');
}

if (!$currentUser) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}