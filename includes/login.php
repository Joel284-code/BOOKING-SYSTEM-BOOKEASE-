<?php

/*
|--------------------------------------------------------------------------
| BookEase - Login Handler
| File: /includes/login.php
|--------------------------------------------------------------------------
|
| This file handles:
| - Customer login
| - Staff login
| - Admin login
| - Session creation
| - Last login update
| - Role-based redirection
|
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Required Files
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = '';
$email = '';


/*
|--------------------------------------------------------------------------
| Helper: Safe HTML
|--------------------------------------------------------------------------
*/

if (!function_exists('login_e')) {

    function login_e($value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Helper: Base URL
|--------------------------------------------------------------------------
*/

function login_redirect($url)
{
    header('Location: ' . $url);
    exit;
}


/*
|--------------------------------------------------------------------------
| Already Logged In
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {

    $role = $_SESSION['role'] ?? 'customer';

    if ($role === 'admin' || $role === 'staff') {

        login_redirect(
            BASE_URL . '/admin/dashboard.php'
        );
    }

    login_redirect(
        BASE_URL . '/customer/dashboard.php'
    );
}


/*
|--------------------------------------------------------------------------
| Process Login
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Get Form Values
    |--------------------------------------------------------------------------
    */

    $email = trim(
        $_POST['email'] ?? ''
    );

    $password = $_POST['password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validate
    |--------------------------------------------------------------------------
    */

    if ($email === '') {

        $error = 'Please enter your email address.';

    } elseif ($password === '') {

        $error = 'Please enter your password.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Database
            |--------------------------------------------------------------------------
            */

            $db = new Database();

            $conn = $db->getConnection();


            if (!($conn instanceof PDO)) {

                throw new Exception(
                    'Database connection was not created correctly.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Find User
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | We only select columns that exist in your SQL.
            |
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


            $user = $stmt->fetch(
                PDO::FETCH_ASSOC
            );


            /*
            |--------------------------------------------------------------------------
            | User Does Not Exist
            |--------------------------------------------------------------------------
            */

            if (!$user) {

                $error =
                    'Incorrect email or password.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Verify Password
                |--------------------------------------------------------------------------
                */

                $passwordCorrect = false;


                if (
                    !empty($user['password']) &&
                    password_verify(
                        $password,
                        $user['password']
                    )
                ) {

                    $passwordCorrect = true;
                }


                /*
                |--------------------------------------------------------------------------
                | DEMO ACCOUNT PASSWORD REPAIR
                |--------------------------------------------------------------------------
                |
                | Your SQL contains demo accounts using "password".
                |
                | If an old/incorrect hash was imported into MySQL,
                | this repairs the hash automatically.
                |
                */

                if (
                    !$passwordCorrect &&
                    $password === 'password' &&
                    in_array(
                        strtolower($user['email']),
                        [
                            'admin@bookease.test',
                            'staff@bookease.test',
                            'customer@bookease.test'
                        ],
                        true
                    )
                ) {

                    $newHash = password_hash(
                        'password',
                        PASSWORD_DEFAULT
                    );


                    $repair = $conn->prepare("
                        UPDATE users
                        SET password = ?
                        WHERE id = ?
                    ");

                    $repair->execute([
                        $newHash,
                        $user['id']
                    ]);


                    /*
                    |--------------------------------------------------------------
                    | Treat login as successful after repairing demo password
                    |--------------------------------------------------------------
                    */

                    $passwordCorrect = true;
                }


                /*
                |--------------------------------------------------------------------------
                | Password Incorrect
                |--------------------------------------------------------------------------
                */

                if (!$passwordCorrect) {

                    $error =
                        'Incorrect email or password.';

                }

                /*
                |--------------------------------------------------------------------------
                | Account Status
                |--------------------------------------------------------------------------
                */

                elseif (
                    isset($user['status']) &&
                    $user['status'] !== 'active'
                ) {

                    $error =
                        'Your account is not active. Please contact the administrator.';

                }

                /*
                |--------------------------------------------------------------------------
                | LOGIN SUCCESS
                |--------------------------------------------------------------------------
                */

                else {

                    /*
                    |--------------------------------------------------------------------------
                    | Regenerate Session
                    |--------------------------------------------------------------------------
                    */

                    session_regenerate_id(true);


                    /*
                    |--------------------------------------------------------------------------
                    | Store Session Data
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['user_id'] =
                        (int) $user['id'];

                    $_SESSION['name'] =
                        $user['name'];

                    $_SESSION['email'] =
                        $user['email'];

                    $_SESSION['phone'] =
                        $user['phone'] ?? '';

                    $_SESSION['role'] =
                        $user['role'];

                    $_SESSION['login_time'] =
                        time();


                    /*
                    |--------------------------------------------------------------------------
                    | Update Last Login
                    |--------------------------------------------------------------------------
                    */

                    try {

                        $update = $conn->prepare("
                            UPDATE users
                            SET last_login = NOW()
                            WHERE id = ?
                        ");

                        $update->execute([
                            $user['id']
                        ]);

                    } catch (Throwable $e) {

                        /*
                        | Don't prevent login if last_login update fails.
                        */
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Role-Based Redirect
                    |--------------------------------------------------------------------------
                    */

                    if ($user['role'] === 'admin') {

                        login_redirect(
                            BASE_URL .
                            '/admin/dashboard.php'
                        );
                    }


                    if ($user['role'] === 'staff') {

                        login_redirect(
                            BASE_URL .
                            '/admin/dashboard.php'
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Customer
                    |--------------------------------------------------------------------------
                    */

                    login_redirect(
                        BASE_URL .
                        '/customer/dashboard.php'
                    );
                }
            }


        } catch (PDOException $e) {

            if (
                defined('DEVELOPMENT_MODE') &&
                DEVELOPMENT_MODE === true
            ) {

                $error =
                    'Database error: ' .
                    $e->getMessage();

            } else {

                $error =
                    'Unable to connect to the database. Please check your database connection.';
            }


        } catch (Throwable $e) {

            if (
                defined('DEVELOPMENT_MODE') &&
                DEVELOPMENT_MODE === true
            ) {

                $error =
                    'System error: ' .
                    $e->getMessage();

            } else {

                $error =
                    'Something went wrong. Please try again.';
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
    Login | BookEase
</title>


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 20px;

    background:
        linear-gradient(
            135deg,
            #eff6ff,
            #f8fafc
        );

    color: #0f172a;
}


.login-wrapper {

    width: 100%;

    max-width: 430px;
}


.logo {

    text-align: center;

    margin-bottom: 22px;

    font-size: 32px;

    font-weight: 800;

    letter-spacing: -1px;

    color: #0f172a;
}


.logo span {

    color: #2563eb;
}


.login-card {

    background: #ffffff;

    border-radius: 20px;

    padding: 36px;

    border:
        1px solid #e2e8f0;

    box-shadow:
        0 20px 50px
        rgba(15, 23, 42, .10);
}


.login-card h1 {

    margin: 0 0 8px;

    font-size: 27px;
}


.subtitle {

    margin: 0 0 28px;

    color: #64748b;

    line-height: 1.6;
}


.alert {

    padding: 14px 16px;

    margin-bottom: 20px;

    border-radius: 10px;

    background: #fef2f2;

    color: #b91c1c;

    border:
        1px solid #fecaca;

    font-size: 14px;

    line-height: 1.5;
}


.form-group {

    margin-bottom: 20px;
}


label {

    display: block;

    margin-bottom: 8px;

    color: #334155;

    font-size: 14px;

    font-weight: 700;
}


input {

    width: 100%;

    height: 50px;

    padding: 0 15px;

    border:
        1px solid #cbd5e1;

    border-radius: 10px;

    outline: none;

    background: #ffffff;

    color: #0f172a;

    font-size: 15px;

    transition: .2s;
}


input:focus {

    border-color: #2563eb;

    box-shadow:
        0 0 0 4px
        rgba(37, 99, 235, .10);
}


button {

    width: 100%;

    height: 51px;

    border: none;

    border-radius: 10px;

    background: #2563eb;

    color: #ffffff;

    font-size: 15px;

    font-weight: 700;

    cursor: pointer;

    transition: .2s;
}


button:hover {

    background: #1d4ed8;

    transform:
        translateY(-1px);
}


.links {

    display: flex;

    justify-content: space-between;

    gap: 15px;

    margin-top: 20px;

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


.home {

    display: block;

    text-align: center;

    margin-top: 24px;

    color: #64748b;

    text-decoration: none;

    font-size: 14px;
}


.home:hover {

    color: #2563eb;
}


.demo-login {

    margin-top: 25px;

    padding: 15px;

    border-radius: 12px;

    background: #f8fafc;

    border:
        1px solid #e2e8f0;

    font-size: 13px;

    color: #475569;

    line-height: 1.7;
}


.demo-login strong {

    color: #0f172a;
}


@media (max-width: 480px) {

    .login-card {

        padding: 27px 22px;

        border-radius: 16px;
    }


    .links {

        flex-direction: column;

        text-align: center;
    }

}

</style>

</head>


<body>


<div class="login-wrapper">


    <div class="logo">

        Book<span>Ease</span>

    </div>


    <div class="login-card">


        <h1>
            Welcome Back
        </h1>


        <p class="subtitle">
            Login to manage your BookEase account.
        </p>


        <?php if ($error !== ''): ?>

            <div class="alert">

                <?= login_e($error) ?>

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
                    placeholder="admin@bookease.test"
                    value="<?= login_e($email) ?>"
                    autocomplete="email"
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
                    autocomplete="current-password"
                    required
                >

            </div>


            <button type="submit">

                Login

            </button>


        </form>


        <div class="links">

            <a
                href="<?= login_e(BASE_URL) ?>/auth/forgot-password.php"
            >
                Forgot Password?
            </a>


            <a
                href="<?= login_e(BASE_URL) ?>/auth/register.php"
            >
                Create Account
            </a>

        </div>


        <div class="demo-login">

            <strong>Demo Admin Login</strong><br>

            Email:
            admin@bookease.test
            <br>

            Password:
            password

        </div>


        <a
            href="<?= login_e(BASE_URL) ?>/index.php"
            class="home"
        >
            ← Back to Home
        </a>


    </div>

</div>


</body>

</html>