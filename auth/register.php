<?php
/**
 * BookEase Booking System
 * File: auth/register.php
 */

session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$error = "";
$success = "";
$verificationLink = "";


/*
|--------------------------------------------------------------------------
| Redirect logged-in users
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id'])) {

    if (
        isset($_SESSION['role']) &&
        $_SESSION['role'] === 'admin'
    ) {

        header("Location: ../admin/dashboard.php");
        exit;

    } else {

        header("Location: ../customer/dashboard.php");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Database connection
|--------------------------------------------------------------------------
*/

try {

    $db = new Database();
    $conn = $db->getConnection();

    if (!$conn instanceof PDO) {
        throw new Exception("Database connection failed.");
    }

} catch (Exception $e) {

    $error = "Unable to connect to the database.";
}


/*
|--------------------------------------------------------------------------
| Registration
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {

    $name = trim(
        $_POST['name'] ?? ''
    );

    $email = trim(
        $_POST['email'] ?? ''
    );

    $phone = trim(
        $_POST['phone'] ?? ''
    );

    $password =
        $_POST['password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validate information
    |--------------------------------------------------------------------------
    */

    if ($name === '') {

        $error =
            "Full name is required.";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            "Please enter a valid email address.";

    } elseif ($phone === '') {

        $error =
            "Phone number is required.";

    } elseif (strlen($password) < 6) {

        $error =
            "Password must contain at least 6 characters.";

    } elseif ($password !== $confirmPassword) {

        $error =
            "Passwords do not match.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Check whether email already exists
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([
                $email
            ]);

            $existingUser =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if ($existingUser) {

                $error =
                    "An account with this email already exists.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Hash password
                |--------------------------------------------------------------------------
                */

                $hashedPassword =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


                /*
                |--------------------------------------------------------------------------
                | Create customer account
                |--------------------------------------------------------------------------
                |
                | We are NOT using email_verified or
                | verification_token because those columns
                | are not currently present in your database.
                |
                */

                $stmt = $conn->prepare("
                    INSERT INTO users
                    (
                        name,
                        email,
                        phone,
                        password,
                        role,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        'customer',
                        'active'
                    )
                ");

                $stmt->execute([
                    $name,
                    $email,
                    $phone,
                    $hashedPassword
                ]);


                if ($stmt->rowCount() > 0) {

                    $success =
                        "Account created successfully. "
                        . "You can now login.";

                } else {

                    $error =
                        "Registration failed. Please try again.";
                }
            }

        } catch (PDOException $e) {

            /*
            | Do not display database details
            | to the customer.
            */

            $error =
                "Registration failed. Please try again.";

        } catch (Exception $e) {

            $error =
                "Something went wrong. Please try again.";
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

    <title>Create Account - BookEase</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            padding: 20px;

            font-family: Arial, sans-serif;

            background: #f1f5f9;

            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;
        }


        .container {
            width: 100%;
            max-width: 500px;
        }


        .logo {
            text-align: center;

            font-size: 30px;
            font-weight: bold;

            margin-bottom: 20px;

            color: #0f172a;
        }


        .logo span {
            color: #2563eb;
        }


        .card {
            background: #ffffff;

            padding: 35px;

            border-radius: 12px;

            box-shadow:
                0 10px 35px rgba(
                    0,
                    0,
                    0,
                    0.08
                );
        }


        h1 {
            margin-top: 0;
            margin-bottom: 8px;

            color: #0f172a;
        }


        .subtitle {
            color: #64748b;

            margin-bottom: 25px;

            line-height: 1.5;
        }


        .form-group {
            margin-bottom: 17px;
        }


        label {
            display: block;

            margin-bottom: 7px;

            font-weight: 600;

            color: #334155;
        }


        input {
            width: 100%;

            padding: 13px 14px;

            border: 1px solid #cbd5e1;

            border-radius: 7px;

            font-size: 15px;

            outline: none;
        }


        input:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px rgba(
                    37,
                    99,
                    235,
                    0.10
                );
        }


        .btn {
            width: 100%;

            padding: 13px;

            background: #2563eb;

            border: none;

            color: white;

            border-radius: 7px;

            font-size: 16px;

            font-weight: 600;

            cursor: pointer;
        }


        .btn:hover {
            background: #1d4ed8;
        }


        .alert {
            padding: 13px;

            border-radius: 7px;

            margin-bottom: 20px;

            line-height: 1.5;
        }


        .error {
            background: #fee2e2;

            color: #991b1b;

            border: 1px solid #fecaca;
        }


        .success {
            background: #dcfce7;

            color: #166534;

            border: 1px solid #bbf7d0;
        }


        .bottom {
            text-align: center;

            margin-top: 20px;

            color: #64748b;
        }


        .bottom a {
            color: #2563eb;

            text-decoration: none;

            font-weight: 500;
        }


        .bottom a:hover {
            text-decoration: underline;
        }


        @media (max-width: 480px) {

            .card {
                padding: 25px;
            }

            .logo {
                font-size: 26px;
            }

        }

    </style>

</head>


<body>


<div class="container">


    <div class="logo">

        Book<span>Ease</span>

    </div>


    <div class="card">


        <h1>
            Create Account
        </h1>


        <p class="subtitle">
            Create your customer account.
        </p>


        <?php if ($error): ?>

            <div class="alert error">

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <?php if ($success): ?>

            <div class="alert success">

                <?= htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

                <br><br>

                <a href="login.php">
                    Continue to Login
                </a>

            </div>

        <?php endif; ?>


        <?php if (!$success): ?>


        <form method="POST" action="">


            <div class="form-group">

                <label for="name">
                    Full Name
                </label>


                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="John Doe"
                    value="<?= htmlspecialchars(
                        $name ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    autocomplete="name"
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
                    placeholder="john@example.com"
                    value="<?= htmlspecialchars(
                        $email ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    autocomplete="email"
                    required
                >

            </div>


            <div class="form-group">

                <label for="phone">
                    Phone Number
                </label>


                <input
                    type="text"
                    id="phone"
                    name="phone"
                    placeholder="0712345678"
                    value="<?= htmlspecialchars(
                        $phone ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    autocomplete="tel"
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
                    placeholder="Minimum 6 characters"
                    autocomplete="new-password"
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
                    autocomplete="new-password"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn"
            >
                Create Account
            </button>


        </form>


        <?php endif; ?>


        <div class="bottom">

            Already have an account?

            <a href="login.php">
                Login
            </a>

        </div>


    </div>


</div>


</body>

</html>