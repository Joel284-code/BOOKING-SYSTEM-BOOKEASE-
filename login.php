<?php

/*
|--------------------------------------------------------------------------
| BookEase Login
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

    if ($role === 'customer') {

        header(
            'Location: ' .
            BASE_URL .
            '/customer/dashboard.php'
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = '';
$email = '';

/*
|--------------------------------------------------------------------------
| Process Login
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim(
        $_POST['email'] ?? ''
    );

    $password =
        $_POST['password'] ?? '';

    if ($email === '') {

        $error = 'Please enter your email address.';

    } elseif ($password === '') {

        $error = 'Please enter your password.';

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error = 'Please enter a valid email address.';

    } else {

        try {

            $db = new Database();

            $conn = $db->getConnection();

            if (!$conn instanceof PDO) {

                throw new RuntimeException(
                    'Database connection was not created correctly.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Find User
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                SELECT
                    id,
                    name,
                    email,
                    phone,
                    password,
                    role,
                    status
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([
                $email
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            /*
            |--------------------------------------------------------------------------
            | Validate Login
            |--------------------------------------------------------------------------
            */

            if (!$user) {

                $error =
                    'Invalid email or password.';

            } elseif (
                empty($user['password']) ||
                !password_verify(
                    $password,
                    $user['password']
                )
            ) {

                $error =
                    'Invalid email or password.';

            } elseif (
                isset($user['status']) &&
                strtolower(
                    trim($user['status'])
                ) !== 'active'
            ) {

                $error =
                    'Your account is not active. Please contact the administrator.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Successful Login
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);

                $_SESSION['user_id'] =
                    (int) $user['id'];

                $_SESSION['name'] =
                    $user['name'];

                $_SESSION['email'] =
                    $user['email'];

                $_SESSION['phone'] =
                    $user['phone'] ?? '';

                $_SESSION['role'] =
                    strtolower(
                        trim(
                            $user['role'] ?? 'customer'
                        )
                    );

                $_SESSION['logged_in'] = true;

                $_SESSION['login_time'] = time();

                /*
                |--------------------------------------------------------------------------
                | Redirect
                |--------------------------------------------------------------------------
                */

                if (
                    $_SESSION['role'] === 'admin' ||
                    $_SESSION['role'] === 'staff'
                ) {

                    header(
                        'Location: ' .
                        BASE_URL .
                        '/admin/dashboard.php'
                    );

                    exit;
                }

                if (
                    $_SESSION['role'] === 'customer'
                ) {

                    header(
                        'Location: ' .
                        BASE_URL .
                        '/customer/dashboard.php'
                    );

                    exit;
                }

                /*
                |--------------------------------------------------------------------------
                | Invalid Role
                |--------------------------------------------------------------------------
                */

                $_SESSION = [];

                session_destroy();

                $error =
                    'Invalid account role. Please contact the administrator.';
            }

        } catch (Throwable $e) {

            if (
                defined('DEVELOPMENT_MODE') &&
                DEVELOPMENT_MODE === true
            ) {

                $error =
                    $e->getMessage();

            } else {

                $error =
                    'Unable to process your login. Please try again later.';
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
    Login | <?= htmlspecialchars(APP_NAME) ?>
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: linear-gradient(135deg,#eff6ff,#f8fafc);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.container {
    width: 420px;
    max-width: 100%;
    background: white;
    padding: 40px;
    border-radius: 15px;
    box-shadow: 0 15px 40px rgba(15,23,42,.10);
}

.logo {
    text-align: center;
    color: #2563eb;
    font-size: 32px;
    font-weight: bold;
}

.subtitle {
    text-align: center;
    color: #64748b;
    margin: 8px 0 30px;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    padding: 12px 14px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    line-height: 1.5;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #1e293b;
}

input {
    width: 100%;
    padding: 14px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 15px;
    outline: none;
}

input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,.10);
}

button {
    width: 100%;
    padding: 14px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #1d4ed8;
}

.bottom {
    text-align: center;
    margin-top: 25px;
    color: #64748b;
}

.bottom a,
.forgot,
.home {
    color: #2563eb;
    text-decoration: none;
    font-weight: bold;
}

.forgot {
    display: block;
    text-align: right;
    margin-top: 8px;
    font-size: 13px;
}

.home {
    display: block;
    text-align: center;
    margin-top: 20px;
    color: #475569;
    font-size: 14px;
}

</style>

</head>

<body>

<div class="container">

<div class="logo">
    <?= htmlspecialchars(APP_NAME) ?>
</div>

<p class="subtitle">
    Login to your account
</p>

<?php if ($error !== ''): ?>

<div class="error">
    <?= htmlspecialchars(
        $error,
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</div>

<?php endif; ?>

<form method="POST">

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

<label for="password">
    Password
</label>

<input
    type="password"
    id="password"
    name="password"
    placeholder="Enter your password"
    required
>

<a
    href="<?= BASE_URL ?>/auth/forgot-password.php"
    class="forgot"
>
    Forgot Password?
</a>

</div>

<button type="submit">
    Login
</button>

</form>

<div class="bottom">

Don't have an account?

<a href="<?= BASE_URL ?>/register.php">
    Create Account
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