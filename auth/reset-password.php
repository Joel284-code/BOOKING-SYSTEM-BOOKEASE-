<?php
/**
 * BookEase Booking System
 * File: auth/reset-password.php
 */

session_start();

require_once "../includes/database.php";

$error = "";
$success = "";
$user = null;

/*
|--------------------------------------------------------------------------
| Get Reset Token
|--------------------------------------------------------------------------
*/

$token = trim(
    $_GET['token']
    ?? $_POST['token']
    ?? ''
);

/*
|--------------------------------------------------------------------------
| Validate Reset Token
|--------------------------------------------------------------------------
*/

if ($token === '') {

    $error = "Invalid or missing password reset link.";

} else {

    $stmt = $conn->prepare(
        "SELECT
            id,
            email,
            reset_expires
         FROM users
         WHERE reset_token = ?
         LIMIT 1"
    );

    if (!$stmt) {

        $error = "A database error occurred. Please try again.";

    } else {

        $stmt->bind_param("s", $token);

        if (!$stmt->execute()) {

            $error = "Unable to validate the reset link.";

        } else {

            $result = $stmt->get_result();

            if ($result->num_rows !== 1) {

                $error = "This password reset link is invalid.";

            } else {

                $user = $result->fetch_assoc();

                /*
                 * Check expiration
                 */

                if (
                    empty($user['reset_expires']) ||
                    strtotime($user['reset_expires']) <= time()
                ) {

                    $error =
                        "This password reset link has expired.";

                    $user = null;
                }
            }
        }

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| Reset Password
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $user !== null &&
    $error === ''
) {

    $password =
        $_POST['password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';

    /*
     * Validate password
     */

    if ($password === '') {

        $error =
            "Please enter a new password.";

    } elseif (strlen($password) < 6) {

        $error =
            "Password must contain at least 6 characters.";

    } elseif ($password !== $confirmPassword) {

        $error =
            "Passwords do not match.";

    } else {

        /*
         * Hash new password
         */

        $hashedPassword =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );

        if ($hashedPassword === false) {

            $error =
                "Unable to secure the new password.";

        } else {

            /*
             * Update password and invalidate
             * the reset token.
             */

            $stmt = $conn->prepare(
                "UPDATE users
                 SET
                    password = ?,
                    reset_token = NULL,
                    reset_expires = NULL
                 WHERE id = ?
                 AND reset_token = ?"
            );

            if (!$stmt) {

                $error =
                    "A database error occurred. Please try again.";

            } else {

                $stmt->bind_param(
                    "sis",
                    $hashedPassword,
                    $user['id'],
                    $token
                );

                if ($stmt->execute()) {

                    if ($stmt->affected_rows > 0) {

                        $success =
                            "Your password has been reset successfully.";

                        /*
                         * Remove user data from the page
                         * so the form cannot be submitted again.
                         */

                        $user = null;

                    } else {

                        $error =
                            "This reset link is no longer valid.";
                    }

                } else {

                    $error =
                        "Unable to reset your password. Please try again.";
                }

                $stmt->close();
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
        Reset Password - BookEase
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }

        .logo {
            text-align: center;
            font-size: 30px;
            font-weight: bold;
            margin-bottom: 25px;
            color: #0f172a;
        }

        .logo span {
            color: #2563eb;
        }

        .card {
            background: #ffffff;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin: 0 0 10px;
            color: #0f172a;
        }

        .subtitle {
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #334155;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
            outline: none;
        }

        input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
        }

        .btn {
            width: 100%;
            padding: 13px;
            background: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
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
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        .back:hover {
            text-decoration: underline;
        }

        .password-help {
            font-size: 13px;
            color: #64748b;
            margin-top: 6px;
        }

        @media (max-width: 500px) {

            .container {
                padding: 15px;
            }

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
            Reset Password
        </h1>

        <p class="subtitle">
            Create a new password for your BookEase account.
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

            </div>

            <a
                href="login.php"
                class="back"
            >
                Go to Login
            </a>


        <?php elseif ($user !== null && $error === ''): ?>

            <form method="POST">

                <input
                    type="hidden"
                    name="token"
                    value="<?= htmlspecialchars(
                        $token,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >


                <div class="form-group">

                    <label for="password">
                        New Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter new password"
                        minlength="6"
                        required
                    >

                    <div class="password-help">
                        Password must contain at least 6 characters.
                    </div>

                </div>


                <div class="form-group">

                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm new password"
                        minlength="6"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="btn"
                >
                    Reset Password
                </button>

            </form>


        <?php else: ?>

            <a
                href="forgot-password.php"
                class="back"
            >
                Request a New Reset Link
            </a>

            <a
                href="login.php"
                class="back"
            >
                ← Back to Login
            </a>

        <?php endif; ?>

    </div>

</div>

</body>

</html>