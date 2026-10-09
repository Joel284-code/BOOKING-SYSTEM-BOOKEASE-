<?php
/**
 * BookEase Booking System
 * File: auth/verify-email.php
 */

require_once "../includes/database.php";

$message = "";
$success = false;

/*
|--------------------------------------------------------------------------
| Get Verification Token
|--------------------------------------------------------------------------
*/

$token = trim(
    $_GET['token'] ?? ''
);


/*
|--------------------------------------------------------------------------
| Validate Verification Token
|--------------------------------------------------------------------------
*/

if ($token === '') {

    $message =
        "Invalid or missing verification token.";

} else {

    /*
     * Find user using verification token
     */

    $stmt = $conn->prepare(
        "SELECT
            id,
            email,
            email_verified
         FROM users
         WHERE verification_token = ?
         LIMIT 1"
    );

    if (!$stmt) {

        $message =
            "A database error occurred. Please try again.";

    } else {

        $stmt->bind_param(
            "s",
            $token
        );

        if (!$stmt->execute()) {

            $message =
                "Unable to verify your email. Please try again.";

        } else {

            $result =
                $stmt->get_result();

            /*
             * Token not found
             */

            if ($result->num_rows !== 1) {

                $message =
                    "This verification link is invalid or has already been used.";

            } else {

                $user =
                    $result->fetch_assoc();

                /*
                 * Check whether email is already verified
                 */

                if ((int) $user['email_verified'] === 1) {

                    $message =
                        "Your email address is already verified.";

                    $success = true;

                } else {

                    /*
                     * Verify email
                     *
                     * The token is also checked during the UPDATE
                     * to prevent an invalid token from being used.
                     */

                    $updateStmt = $conn->prepare(
                        "UPDATE users
                         SET
                            email_verified = 1,
                            verification_token = NULL
                         WHERE id = ?
                         AND verification_token = ?"
                    );

                    if (!$updateStmt) {

                        $message =
                            "A database error occurred. Please try again.";

                    } else {

                        $updateStmt->bind_param(
                            "is",
                            $user['id'],
                            $token
                        );

                        if ($updateStmt->execute()) {

                            if ($updateStmt->affected_rows > 0) {

                                $message =
                                    "Your email address has been verified successfully.";

                                $success = true;

                            } else {

                                $message =
                                    "This verification link is no longer valid.";
                            }

                        } else {

                            $message =
                                "Unable to verify your email. Please try again.";
                        }

                        $updateStmt->close();
                    }
                }
            }
        }

        $stmt->close();
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
        Email Verification - BookEase
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
            align-items: center;
            justify-content: center;

            padding: 20px;
        }

        .card {
            width: 100%;
            max-width: 450px;

            background: #ffffff;

            padding: 40px;

            text-align: center;

            border-radius: 14px;

            box-shadow:
                0 10px 35px rgba(0, 0, 0, 0.08);
        }

        .logo {
            font-size: 30px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 25px;
        }

        .logo span {
            color: #2563eb;
        }

        .icon {
            width: 80px;
            height: 80px;

            margin: 0 auto 20px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 40px;

            background:
                <?= $success
                    ? '#dcfce7'
                    : '#fee2e2' ?>;
        }

        h1 {
            margin: 0 0 15px;

            color: #0f172a;

            font-size: 26px;
        }

        p {
            color: #64748b;

            line-height: 1.6;

            margin: 0;
        }

        .success-message {
            color: #166534;
        }

        .error-message {
            color: #991b1b;
        }

        .btn {
            display: inline-block;

            margin-top: 25px;

            padding: 13px 25px;

            background: #2563eb;

            color: #ffffff;

            text-decoration: none;

            border-radius: 7px;

            font-weight: 600;

            transition: background 0.2s ease;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .secondary-link {
            display: block;

            margin-top: 15px;

            color: #64748b;

            text-decoration: none;

            font-size: 14px;
        }

        .secondary-link:hover {
            color: #2563eb;
        }

        @media (max-width: 500px) {

            .card {
                padding: 30px 20px;
            }

            h1 {
                font-size: 23px;
            }

            .logo {
                font-size: 26px;
            }
        }

    </style>

</head>

<body>

<div class="card">

    <div class="logo">
        Book<span>Ease</span>
    </div>


    <div class="icon">

        <?= $success ? '✓' : '!' ?>

    </div>


    <h1>

        <?= $success
            ? 'Email Verified'
            : 'Verification Failed' ?>

    </h1>


    <p
        class="<?= $success
            ? 'success-message'
            : 'error-message' ?>"
    >

        <?= htmlspecialchars(
            $message,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </p>


    <?php if ($success): ?>

        <a
            href="login.php"
            class="btn"
        >
            Continue to Login
        </a>

    <?php else: ?>

        <a
            href="register.php"
            class="btn"
        >
            Create Account
        </a>

        <a
            href="login.php"
            class="secondary-link"
        >
            ← Back to Login
        </a>

    <?php endif; ?>

</div>

</body>

</html>