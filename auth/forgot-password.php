<?php
/**
 * Booking System
 * File: auth/forgot-password.php
 */

session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$message = "";
$error = "";
$resetLink = "";

try {

    /*
     * Create database connection
     */
    $db = new Database();
    $conn = $db->getConnection();

    /*
     * Make sure we have a PDO connection
     */
    if (!$conn instanceof PDO) {
        throw new Exception("Database connection failed.");
    }

} catch (Exception $e) {

    $error = "Unable to connect to the database.";
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {

    $email = trim($_POST['email'] ?? '');

    /*
     * Validate email
     */
    if ($email === '') {

        $error = "Please enter your email address.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        try {

            /*
             * Find user
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

            $user = $stmt->fetch(PDO::FETCH_ASSOC);


            /*
             * Always show a generic message
             * so we do not reveal whether
             * an email exists.
             */
            $message =
                "If an account exists for this email, "
                . "a password reset link has been generated.";


            /*
             * Generate reset token
             */
            if ($user) {

                $token = bin2hex(
                    random_bytes(32)
                );

                $expires = date(
                    'Y-m-d H:i:s',
                    time() + 3600
                );


                /*
                 * Save token
                 */
                $stmt = $conn->prepare("
                    UPDATE users
                    SET
                        reset_token = ?,
                        reset_expires = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $token,
                    $expires,
                    $user['id']
                ]);


                /*
                 * Local XAMPP reset link
                 */
                $resetLink =
                    "http://localhost/booking-system/auth/reset-password.php?token="
                    . urlencode($token);
            }

        } catch (PDOException $e) {

            $error =
                "A database error occurred. "
                . "Please try again.";

        } catch (Exception $e) {

            $error =
                "Something went wrong. "
                . "Please try again.";
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

    <title>Forgot Password - BookEase</title>

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
            justify-content: center;
            align-items: center;
        }

        .container {
            width: 100%;
            max-width: 450px;
        }

        .logo {
            text-align: center;
            font-size: 30px;
            font-weight: bold;
            margin-bottom: 25px;
            color: #111827;
        }

        .logo span {
            color: #2563eb;
        }

        .card {
            background: #ffffff;
            padding: 35px;
            border-radius: 12px;

            box-shadow:
                0 10px 35px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #111827;
        }

        .subtitle {
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 7px;
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
                0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn {
            width: 100%;
            padding: 13px;

            border: none;
            border-radius: 7px;

            background: #2563eb;
            color: white;

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

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .reset-link {
            margin-top: 20px;
            padding: 15px;

            background: #fef3c7;
            color: #92400e;

            border: 1px solid #fde68a;
            border-radius: 7px;

            word-break: break-word;
            line-height: 1.5;
        }

        .reset-link a {
            color: #92400e;
            font-weight: bold;
        }

        .back {
            display: block;
            text-align: center;

            margin-top: 20px;

            color: #2563eb;
            text-decoration: none;
        }

        .back:hover {
            text-decoration: underline;
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
            Forgot Password?
        </h1>


        <p class="subtitle">
            Enter your email address to reset your password.
        </p>


        <?php if ($message): ?>

            <div class="alert alert-success">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="alert alert-error">

                <?= htmlspecialchars($error) ?>

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
                    placeholder="you@example.com"
                    value="<?= htmlspecialchars(
                        $_POST['email'] ?? ''
                    ) ?>"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn"
            >
                Generate Reset Link
            </button>

        </form>


        <?php if ($resetLink): ?>

            <div class="reset-link">

                <strong>
                    Development Reset Link
                </strong>

                <br><br>

                <a
                    href="<?= htmlspecialchars($resetLink) ?>"
                >
                    Reset Your Password
                </a>

            </div>

        <?php endif; ?>


        <a
            href="login.php"
            class="back"
        >
            ← Back to Login
        </a>

    </div>

</div>

</body>

</html>