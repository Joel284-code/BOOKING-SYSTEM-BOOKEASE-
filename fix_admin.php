<?php

require_once __DIR__ . '/includes/database.php';

try {

    $db = new Database();
    $conn = $db->getConnection();

    // Generate a fresh password hash
    $passwordHash = password_hash('password', PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        UPDATE users
        SET
            password = ?,
            role = 'admin',
            status = 'active',
            email_verified = 1
        WHERE email = ?
    ");

    $stmt->execute([
        $passwordHash,
        'admin@bookease.test'
    ]);

    if ($stmt->rowCount() > 0) {

        echo "<h2 style='color:green;'>Admin account fixed successfully.</h2>";

        echo "<p><strong>Email:</strong> admin@bookease.test</p>";
        echo "<p><strong>Password:</strong> password</p>";

    } else {

        echo "<h2 style='color:red;'>Admin account was not found.</h2>";

        echo "<p>Make sure admin@bookease.test exists in the users table.</p>";
    }

} catch (Throwable $e) {

    echo "<h2 style='color:red;'>Error</h2>";

    echo "<p>" .
        htmlspecialchars($e->getMessage()) .
        "</p>";
}