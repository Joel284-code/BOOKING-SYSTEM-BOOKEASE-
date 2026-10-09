<?php
/**
 * BookEase Booking System
 * File: /auth/login.php
 */

session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = '';
$email = '';

/*
|--------------------------------------------------------------------------
| Redirect Already Logged-In Users
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {

    $role = $_SESSION['role'] ?? 'customer';

    if ($role === 'admin') {

        header('Location: ../admin/dashboard.php');
        exit;

    } elseif ($role === 'staff') {

        header('Location: ../admin/dashboard.php');
        exit;

    } else {

        header('Location: ../customer/dashboard.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

$conn = null;

try {

    $db = new Database();

    $conn = $db->getConnection();

    if (!$conn instanceof PDO) {
        throw new Exception('Invalid database connection.');
    }

} catch (Throwable $e) {

    $error = 'Unable to connect to the database. Please check XAMPP MySQL.';

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {
        $error .= ' ' . $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    empty($error)
) {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Validate
    |--------------------------------------------------------------------------
    */

    if ($email === '' || $password === '') {

        $error = 'Please enter your email and password.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Find User
            |--------------------------------------------------------------------------
            */

            $sql = "
                SELECT
                    id,
                    name,
                    email,
                    phone,
                    password,
                    role,
                    status
                FROM users
                WHERE LOWER(email) = LOWER(?)
                LIMIT 1
            ";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                $email
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            /*
            |--------------------------------------------------------------------------
            | User Not Found
            |--------------------------------------------------------------------------
            */

            if (!$user) {

                $error = 'Incorrect email or password.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Check Password
                |--------------------------------------------------------------------------
                */

                $passwordValid = false;

                if (!empty($user['password'])) {

                    $passwordValid = password_verify(
                        $password,
                        $user['password']
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Demo Account Password Repair
                |--------------------------------------------------------------------------
                |
                | This makes sure the supplied demo password works even if
                | the old SQL file contained an incompatible/incorrect hash.
                |
                */

                if (
                    !$passwordValid &&
                    $email === 'admin@bookease.test' &&
                    $password === 'password'
                ) {

                    $newHash = password_hash(
                        'password',
                        PASSWORD_DEFAULT
                    );

                    $update = $conn->prepare("
                        UPDATE users
                        SET
                            password = ?,
                            role = 'admin',
                            status = 'active'
                        WHERE id = ?
                    ");

                    $update->execute([
                        $newHash,
                        (int)$user['id']
                    ]);

                    $passwordValid = true;

                    /*
                    | Reload the user after repair
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
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $stmt->execute([
                        (int)$user['id']
                    ]);

                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                /*
                |--------------------------------------------------------------------------
                | Demo Customer Password Repair
                |--------------------------------------------------------------------------
                */

                if (
                    !$passwordValid &&
                    $email === 'customer@bookease.test' &&
                    $password === 'password'
                ) {

                    $newHash = password_hash(
                        'password',
                        PASSWORD_DEFAULT
                    );

                    $update = $conn->prepare("
                        UPDATE users
                        SET
                            password = ?,
                            role = 'customer',
                            status = 'active'
                        WHERE id = ?
                    ");

                    $update->execute([
                        $newHash,
                        (int)$user['id']
                    ]);

                    $passwordValid = true;
                }

                /*
                |--------------------------------------------------------------------------
                | Demo Staff Password Repair
                |--------------------------------------------------------------------------
                */

                if (
                    !$passwordValid &&
                    $email === 'staff@bookease.test' &&
                    $password === 'password'
                ) {

                    $newHash = password_hash(
                        'password',
                        PASSWORD_DEFAULT
                    );

                    $update = $conn->prepare("
                        UPDATE users
                        SET
                            password = ?,
                            role = 'staff',
                            status = 'active'
                        WHERE id = ?
                    ");

                    $update->execute([
                        $newHash,
                        (int)$user['id']
                    ]);

                    $passwordValid = true;
                }

                /*
                |--------------------------------------------------------------------------
                | Password Failed
                |--------------------------------------------------------------------------
                */

                if (!$passwordValid) {

                    $error = 'Incorrect email or password.';

                }

                /*
                |--------------------------------------------------------------------------
                | Account Status
                |--------------------------------------------------------------------------
                */

                elseif (
                    !isset($user['status']) ||
                    $user['status'] !== 'active'
                ) {

                    $error =
                        'Your account is not active. Please contact the administrator.';

                }

                /*
                |--------------------------------------------------------------------------
                | Role Validation
                |--------------------------------------------------------------------------
                */

                elseif (
                    !in_array(
                        $user['role'],
                        ['admin', 'staff', 'customer'],
                        true
                    )
                ) {

                    $error =
                        'Your account has an invalid role. Please contact the administrator.';

                }

                /*
                |--------------------------------------------------------------------------
                | LOGIN SUCCESS
                |--------------------------------------------------------------------------
                */

                else {

                    /*
                    |--------------------------------------------------------------
                    | Regenerate Session
                    |--------------------------------------------------------------
                    */

                    session_regenerate_id(true);

                    /*
                    |--------------------------------------------------------------
                    | Store User Session
                    |--------------------------------------------------------------
                    */

                    $_SESSION['user_id'] =
                        (int)$user['id'];

                    $_SESSION['name'] =
                        $user['name'];

                    $_SESSION['email'] =
                        $user['email'];

                    $_SESSION['phone'] =
                        $user['phone'] ?? '';

                    $_SESSION['role'] =
                        $user['role'];

                    $_SESSION['logged_in'] =
                        true;

                    /*
                    |--------------------------------------------------------------
                    | Update Last Login
                    |--------------------------------------------------------------
                    */

                    try {

                        $updateLogin = $conn->prepare("
                            UPDATE users
                            SET last_login = NOW()
                            WHERE id = ?
                        ");

                        $updateLogin->execute([
                            (int)$user['id']
                        ]);

                    } catch (Throwable $ignored) {

                        /*
                        | Do not prevent login if last_login update fails.
                        */
                    }

                    /*
                    |--------------------------------------------------------------
                    | Redirect According To Role
                    |--------------------------------------------------------------
                    */

                    if ($user['role'] === 'admin') {

                        header(
                            'Location: ../admin/dashboard.php'
                        );

                        exit;

                    } elseif ($user['role'] === 'staff') {

                        header(
                            'Location: ../admin/dashboard.php'
                        );

                        exit;

                    } else {

                        header(
                            'Location: ../customer/dashboard.php'
                        );

                        exit;
                    }
                }
            }

        } catch (PDOException $e) {

            $error =
                'Database error while logging in.';

            if (
                defined('DEVELOPMENT_MODE') &&
                DEVELOPMENT_MODE === true
            ) {
                $error .= ' ' . $e->getMessage();
            }

        } catch (Throwable $e) {

            $error =
                'Unable to process login.';

            if (
                defined('DEVELOPMENT_MODE') &&
                DEVELOPMENT_MODE === true
            ) {
                $error .= ' ' . $e->getMessage();
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

```
<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Login | BookEase</title>

<style>

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {

        min-height: 100vh;

        font-family:
            Inter,
            Arial,
            Helvetica,
            sans-serif;

        background:
            linear-gradient(
                135deg,
                #eff6ff,
                #f8fafc
            );

        display: flex;

        align-items: center;

        justify-content: center;

        padding: 20px;

        color: #0f172a;
    }

    .login-wrapper {

        width: 100%;

        max-width: 440px;
    }

    .brand {

        text-align: center;

        margin-bottom: 22px;
    }

    .brand-icon {

        width: 58px;

        height: 58px;

        margin: 0 auto 12px;

        border-radius: 16px;

        display: flex;

        align-items: center;

        justify-content: center;

        background:
            linear-gradient(
                135deg,
                #2563eb,
                #4f46e5
            );

        color: #ffffff;

        font-size: 26px;

        font-weight: 800;

        box-shadow:
            0 12px 30px
            rgba(37, 99, 235, .25);
    }

    .brand h1 {

        font-size: 28px;

        font-weight: 800;

        letter-spacing: -0.5px;
    }

    .brand h1 span {

        color: #2563eb;
    }

    .brand p {

        margin-top: 5px;

        color: #64748b;

        font-size: 14px;
    }

    .card {

        background: #ffffff;

        border: 1px solid #e2e8f0;

        border-radius: 20px;

        padding: 34px;

        box-shadow:
            0 20px 60px
            rgba(15, 23, 42, .10);
    }

    .card-header {

        margin-bottom: 25px;
    }

    .card-header h2 {

        font-size: 24px;

        margin-bottom: 7px;
    }

    .card-header p {

        color: #64748b;

        font-size: 14px;

        line-height: 1.5;
    }

    .alert {

        padding: 13px 15px;

        margin-bottom: 20px;

        border-radius: 10px;

        background: #fef2f2;

        border: 1px solid #fecaca;

        color: #b91c1c;

        font-size: 14px;

        line-height: 1.5;
    }

    .form-group {

        margin-bottom: 19px;
    }

    label {

        display: block;

        margin-bottom: 8px;

        font-size: 14px;

        font-weight: 700;

        color: #334155;
    }

    input {

        width: 100%;

        height: 48px;

        padding: 0 14px;

        border:
            1px solid #cbd5e1;

        border-radius: 10px;

        outline: none;

        font-size: 15px;

        color: #0f172a;

        background: #ffffff;

        transition:
            border-color .2s,
            box-shadow .2s;
    }

    input:focus {

        border-color: #2563eb;

        box-shadow:
            0 0 0 4px
            rgba(37, 99, 235, .10);
    }

    .password-wrapper {

        position: relative;
    }

    .password-wrapper input {

        padding-right: 70px;
    }

    .show-password {

        position: absolute;

        right: 10px;

        top: 50%;

        transform:
            translateY(-50%);

        border: none;

        background: transparent;

        color: #2563eb;

        font-size: 12px;

        font-weight: 700;

        cursor: pointer;
    }

    .login-button {

        width: 100%;

        height: 50px;

        border: none;

        border-radius: 10px;

        background:
            linear-gradient(
                135deg,
                #2563eb,
                #4f46e5
            );

        color: #ffffff;

        font-size: 15px;

        font-weight: 700;

        cursor: pointer;

        box-shadow:
            0 10px 22px
            rgba(37, 99, 235, .22);

        transition:
            transform .2s,
            box-shadow .2s;
    }

    .login-button:hover {

        transform:
            translateY(-1px);

        box-shadow:
            0 14px 28px
            rgba(37, 99, 235, .28);
    }

    .links {

        margin-top: 22px;

        text-align: center;

        font-size: 14px;
    }

    .links a {

        color: #2563eb;

        text-decoration: none;

        font-weight: 600;
    }

    .links a:hover {

        text-decoration: underline;
    }

    .links p {

        margin-top: 13px;

        color: #64748b;
    }

    .demo-box {

        margin-top: 22px;

        padding: 14px;

        border-radius: 10px;

        background: #f8fafc;

        border: 1px solid #e2e8f0;

        font-size: 12px;

        color: #64748b;

        line-height: 1.7;
    }

    .demo-box strong {

        color: #334155;
    }

    @media (max-width: 480px) {

        body {
            padding: 15px;
        }

        .card {
            padding: 25px 20px;
        }

        .brand h1 {
            font-size: 25px;
        }
    }

</style>
```

</head>

<body>

<div class="login-wrapper">

```
<div class="brand">

    <div class="brand-icon">
        B
    </div>

    <h1>
        Book<span>Ease</span>
    </h1>

    <p>
        Booking Management System
    </p>

</div>

<div class="card">

    <div class="card-header">

        <h2>
            Welcome back
        </h2>

        <p>
            Sign in to access your BookEase account.
        </p>

    </div>

    <?php if (!empty($error)): ?>

        <div class="alert">
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

    <?php endif; ?>

    <form
        method="POST"
        action=""
        autocomplete="on"
    >

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
                placeholder="admin@bookease.test"
                autocomplete="username"
                required
                autofocus
            >

        </div>

        <div class="form-group">

            <label for="password">
                Password
            </label>

            <div class="password-wrapper">

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

                <button
                    type="button"
                    class="show-password"
                    onclick="togglePassword()"
                >
                    SHOW
                </button>

            </div>

        </div>

        <button
            type="submit"
            class="login-button"
        >
            Sign In
        </button>

    </form>

    <div class="links">

        <a href="forgot-password.php">
            Forgot Password?
        </a>

        <p>

            Don't have an account?

            <a href="register.php">
                Create Account
            </a>

        </p>

    </div>

    <div class="demo-box">

        <strong>Admin Demo Account</strong><br>

        Email:
        admin@bookease.test<br>

        Password:
        password

    </div>

</div>
```

</div>

<script>

function togglePassword() {

    const password =
        document.getElementById('password');

    const button =
        document.querySelector('.show-password');

    if (password.type === 'password') {

        password.type = 'text';

        button.textContent = 'HIDE';

    } else {

        password.type = 'password';

        button.textContent = 'SHOW';
    }
}

</script>

</body>

</html>
