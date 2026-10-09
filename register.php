<?php

/*
|--------------------------------------------------------------------------
| BookEase Registration
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Redirect Logged-In Users
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['user_id'])) {

    $role = strtolower(
        trim(
            $_SESSION['role'] ?? 'customer'
        )
    );

    if (
        $role === 'admin' ||
        $role === 'staff'
    ) {

        header(
            'Location: ' .
            BASE_URL .
            '/admin/dashboard.php'
        );

        exit;
    }

    header(
        'Location: ' .
        BASE_URL .
        '/customer/dashboard.php'
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$errors = [];

$success = '';

$name = '';
$email = '';
$phone = '';

/*
|--------------------------------------------------------------------------
| Registration
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name =
        trim($_POST['name'] ?? '');

    $email =
        strtolower(
            trim(
                $_POST['email'] ?? ''
            )
        );

    $phone =
        trim(
            $_POST['phone'] ?? ''
        );

    $password =
        $_POST['password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Name
    |--------------------------------------------------------------------------
    */

    if ($name === '') {

        $errors[] =
            'Full name is required.';

    } elseif (strlen($name) < 2) {

        $errors[] =
            'Full name must contain at least 2 characters.';
    }

    /*
    |--------------------------------------------------------------------------
    | Email
    |--------------------------------------------------------------------------
    */

    if ($email === '') {

        $errors[] =
            'Email address is required.';

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $errors[] =
            'Please enter a valid email address.';
    }

    /*
    |--------------------------------------------------------------------------
    | Phone
    |--------------------------------------------------------------------------
    */

    if ($phone !== '') {

        $cleanPhone =
            preg_replace(
                '/[\s\-]/',
                '',
                $phone
            );

        if (
            !preg_match(
                '/^(?:\+254|254|0)(?:7|1)[0-9]{8}$/',
                $cleanPhone
            )
        ) {

            $errors[] =
                'Please enter a valid Kenyan phone number.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Password
    |--------------------------------------------------------------------------
    */

    if ($password === '') {

        $errors[] =
            'Password is required.';

    } elseif (strlen($password) < 6) {

        $errors[] =
            'Password must contain at least 6 characters.';
    }

    /*
    |--------------------------------------------------------------------------
    | Confirm Password
    |--------------------------------------------------------------------------
    */

    if ($password !== $confirmPassword) {

        $errors[] =
            'Passwords do not match.';
    }

    /*
    |--------------------------------------------------------------------------
    | Create Account
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $db =
                new Database();

            $conn =
                $db->getConnection();

            /*
            |--------------------------------------------------------------------------
            | Check Existing Email
            |--------------------------------------------------------------------------
            */

            $check =
                $conn->prepare("
                    SELECT id
                    FROM users
                    WHERE email = ?
                    LIMIT 1
                ");

            $check->execute([
                $email
            ]);

            if ($check->fetch()) {

                $errors[] =
                    'An account with this email already exists.';

            } else {

                $hashedPassword =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                /*
                |--------------------------------------------------------------------------
                | Insert User
                |--------------------------------------------------------------------------
                |
                | email_verified is deliberately NOT included.
                | This prevents the previous "Unknown column" problem.
                |
                */

                $stmt =
                    $conn->prepare("
                        INSERT INTO users
                        (
                            name,
                            email,
                            phone,
                            password,
                            role,
                            status,
                            created_at
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            'customer',
                            'active',
                            NOW()
                        )
                    ");

                $stmt->execute([
                    $name,
                    $email,
                    $phone,
                    $hashedPassword
                ]);

                $success =
                    'Account created successfully. You can now login.';

                $name = '';
                $email = '';
                $phone = '';
            }

        } catch (Throwable $e) {

            if (
                defined('DEVELOPMENT_MODE') &&
                DEVELOPMENT_MODE === true
            ) {

                $errors[] =
                    $e->getMessage();

            } else {

                $errors[] =
                    'Something went wrong while creating your account.';
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Register | <?= htmlspecialchars(APP_NAME) ?>
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f1f5f9;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.container {
    width: 450px;
    max-width: 100%;
    background: white;
    padding: 35px;
    border-radius: 15px;
    box-shadow: 0 15px 40px rgba(0,0,0,.08);
}

.logo {
    text-align: center;
    color: #2563eb;
    font-size: 30px;
    font-weight: bold;
}

.subtitle {
    text-align: center;
    color: #64748b;
    margin: 8px 0 30px;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    margin-bottom: 7px;
    font-weight: 600;
    color: #1e293b;
}

input {
    width: 100%;
    padding: 13px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    outline: none;
    font-size: 15px;
}

input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,.10);
}

button {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 8px;
    background: #2563eb;
    color: white;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #1d4ed8;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    line-height: 1.5;
    font-size: 14px;
}

.success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    line-height: 1.5;
    font-size: 14px;
}

.bottom {
    text-align: center;
    margin-top: 22px;
    color: #64748b;
}

.bottom a,
.home {
    color: #2563eb;
    text-decoration: none;
    font-weight: bold;
}

.home {
    display: block;
    text-align: center;
    margin-top: 18px;
    color: #475569;
    font-size: 14px;
}

.hint {
    display: block;
    margin-top: 5px;
    color: #94a3b8;
    font-size: 12px;
}

</style>

</head>

<body>

<div class="container">

<div class="logo">
    <?= htmlspecialchars(APP_NAME) ?>
</div>

<p class="subtitle">
    Create your booking account
</p>

<?php if (!empty($errors)): ?>

<div class="error">

<?php foreach ($errors as $error): ?>

<div>
    <?= htmlspecialchars(
        $error,
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

<?php if ($success !== ''): ?>

<div class="success">
    <?= htmlspecialchars(
        $success,
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</div>

<?php endif; ?>

<form method="POST">

<div class="form-group">

<label for="name">
    Full Name
</label>

<input
    type="text"
    id="name"
    name="name"
    value="<?= htmlspecialchars(
        $name,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    placeholder="Enter your full name"
    required
>

</div>

<div class="form-group">

<label for="email">
    Email Address
</label>

<input
    type="email"
    id="email"
    name="email"
    value="<?= htmlspecialchars(
        $email,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    placeholder="Enter your email"
    required
>

</div>

<div class="form-group">

<label for="phone">
    Phone Number
</label>

<input
    type="tel"
    id="phone"
    name="phone"
    value="<?= htmlspecialchars(
        $phone,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
    placeholder="0712345678"
>

<span class="hint">
    Example: 0712345678 or +254712345678
</span>

</div>

<div class="form-group">

<label for="password">
    Password
</label>

<input
    type="password"
    id="password"
    name="password"
    placeholder="Minimum 6 characters"
    required
>

</div>

<div class="form-group">

<label for="confirm_password">
    Confirm Password
</label>

<input
    type="password"
    id="confirm_password"
    name="confirm_password"
    placeholder="Repeat your password"
    required
>

</div>

<button type="submit">
    Create Account
</button>

</form>

<div class="bottom">

Already have an account?

<a href="<?= BASE_URL ?>/login.php">
    Login
</a>

</div>

<a
    href="<?= BASE_URL ?>/index.php"
    class="home"
>
    ← Back to Home
</a>

</div>

</body>

</html>