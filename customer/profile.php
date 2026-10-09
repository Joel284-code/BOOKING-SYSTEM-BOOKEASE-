<?php
/**
 * BookEase
 * Customer Profile
 * File: /customer/profile.php
 */

session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

try {

    $db = new Database();

    $conn = $db->getConnection();

    if (!$conn instanceof PDO) {
        throw new Exception(
            "Database connection was not created correctly."
        );
    }

} catch (Throwable $e) {

    die(
        "Database connection error: "
        . htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = '';
$success = '';

$user = [
    'name' => '',
    'email' => '',
    'phone' => ''
];


/*
|--------------------------------------------------------------------------
| Load User
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            email,
            phone
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $userId
    ]);

    $userData =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$userData) {

        session_destroy();

        header("Location: ../auth/login.php");

        exit;
    }


    $user = array_merge(
        $user,
        $userData
    );


} catch (PDOException $e) {

    $error =
        "Unable to load your profile.";
}


/*
|--------------------------------------------------------------------------
| Update Profile
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['update_profile'])
) {

    $name = trim(
        $_POST['name'] ?? ''
    );

    $email = trim(
        $_POST['email'] ?? ''
    );

    $phone = trim(
        $_POST['phone'] ?? ''
    );


    /*
    |----------------------------------------------------------------------
    | Validation
    |----------------------------------------------------------------------
    */

    if ($name === '') {

        $error =
            "Please enter your name.";

    } elseif ($email === '') {

        $error =
            "Please enter your email address.";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            "Please enter a valid email address.";

    } else {

        try {

            /*
            |------------------------------------------------------------------
            | Check whether email belongs to another user
            |------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                AND id != ?
                LIMIT 1
            ");

            $stmt->execute([
                $email,
                $userId
            ]);

            $existingUser =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if ($existingUser) {

                $error =
                    "That email address is already being used by another account.";

            } else {

                /*
                |--------------------------------------------------------------
                | Update
                |--------------------------------------------------------------
                */

                $stmt = $conn->prepare("
                    UPDATE users

                    SET
                        name = ?,
                        email = ?,
                        phone = ?

                    WHERE id = ?
                ");

                $stmt->execute([
                    $name,
                    $email,
                    $phone,
                    $userId
                ]);


                /*
                |--------------------------------------------------------------
                | Update Session
                |--------------------------------------------------------------
                */

                $_SESSION['name'] =
                    $name;

                $_SESSION['email'] =
                    $email;


                /*
                |--------------------------------------------------------------
                | Update Local Data
                |--------------------------------------------------------------
                */

                $user['name'] =
                    $name;

                $user['email'] =
                    $email;

                $user['phone'] =
                    $phone;


                $success =
                    "Your profile has been updated successfully.";
            }


        } catch (PDOException $e) {

            $error =
                "Unable to update your profile. Please try again.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| Change Password
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['change_password'])
) {

    $currentPassword =
        $_POST['current_password'] ?? '';

    $newPassword =
        $_POST['new_password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';


    if (
        $currentPassword === ''
        ||
        $newPassword === ''
        ||
        $confirmPassword === ''
    ) {

        $error =
            "Please fill in all password fields.";

    } elseif (
        strlen($newPassword) < 6
    ) {

        $error =
            "New password must be at least 6 characters.";

    } elseif (
        $newPassword !== $confirmPassword
    ) {

        $error =
            "New password and confirmation password do not match.";

    } else {

        try {

            /*
            |------------------------------------------------------------------
            | Get Current Password
            |------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                SELECT password
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $userId
            ]);

            $account =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (
                !$account
                ||
                !password_verify(
                    $currentPassword,
                    $account['password']
                )
            ) {

                $error =
                    "Current password is incorrect.";

            } else {

                /*
                |--------------------------------------------------------------
                | Hash New Password
                |--------------------------------------------------------------
                */

                $hashedPassword =
                    password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );


                $stmt = $conn->prepare("
                    UPDATE users

                    SET password = ?

                    WHERE id = ?
                ");

                $stmt->execute([
                    $hashedPassword,
                    $userId
                ]);


                $success =
                    "Your password has been changed successfully.";
            }


        } catch (PDOException $e) {

            $error =
                "Unable to change your password.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


$appName = defined('APP_NAME')
    ? APP_NAME
    : 'BookEase';

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
        My Profile | <?= e($appName) ?>
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

            background:
                linear-gradient(
                    135deg,
                    #f8fafc,
                    #eef4ff
                );

            color: #1e293b;
        }


        /* NAVBAR */

        .navbar {

            background: white;

            border-bottom:
                1px solid #e2e8f0;

            padding:
                16px 5%;

            display: flex;

            align-items: center;

            justify-content: space-between;

            position: sticky;

            top: 0;

            z-index: 1000;
        }


        .logo {

            color: #2563eb;

            font-size: 24px;

            font-weight: 800;

            text-decoration: none;
        }


        .logo span {
            color: #0f172a;
        }


        .nav-links {

            display: flex;

            align-items: center;

            gap: 8px;

            flex-wrap: wrap;
        }


        .nav-links a {

            text-decoration: none;

            color: #475569;

            padding:
                9px 12px;

            border-radius: 7px;

            font-size: 14px;

            font-weight: 600;
        }


        .nav-links a:hover {

            background: #eff6ff;

            color: #2563eb;
        }


        .nav-links .active {

            background: #eff6ff;

            color: #2563eb;
        }


        /* CONTAINER */

        .container {

            width: 92%;

            max-width: 950px;

            margin:
                40px auto 60px;
        }


        /* HEADER */

        .page-header {

            margin-bottom: 25px;
        }


        .page-header h1 {

            margin:
                0 0 7px;

            font-size: 30px;
        }


        .page-header p {

            margin: 0;

            color: #64748b;
        }


        /* ALERTS */

        .alert {

            padding:
                15px 18px;

            border-radius: 9px;

            margin-bottom: 20px;

            font-weight: 600;
        }


        .alert-success {

            background: #dcfce7;

            color: #166534;

            border:
                1px solid #bbf7d0;
        }


        .alert-error {

            background: #fee2e2;

            color: #991b1b;

            border:
                1px solid #fecaca;
        }


        /* GRID */

        .grid {

            display: grid;

            grid-template-columns:
                1.4fr 1fr;

            gap: 22px;
        }


        /* CARD */

        .card {

            background: white;

            border:
                1px solid #e2e8f0;

            border-radius: 15px;

            padding: 25px;

            box-shadow:
                0 8px 25px
                rgba(15,23,42,.06);
        }


        .card h2 {

            margin:
                0 0 20px;

            font-size: 19px;
        }


        /* FORM */

        .form-group {

            margin-bottom: 18px;
        }


        label {

            display: block;

            margin-bottom: 7px;

            font-size: 13px;

            font-weight: 700;

            color: #334155;
        }


        .form-control {

            width: 100%;

            padding:
                12px 13px;

            border:
                1px solid #cbd5e1;

            border-radius: 8px;

            font-size: 14px;

            outline: none;

            background: white;
        }


        .form-control:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37,99,235,.10);
        }


        /* BUTTON */

        .btn {

            display: inline-block;

            border: none;

            cursor: pointer;

            text-decoration: none;

            padding:
                11px 18px;

            border-radius: 8px;

            font-size: 14px;

            font-weight: 700;
        }


        .btn-primary {

            background: #2563eb;

            color: white;
        }


        .btn-primary:hover {

            background: #1d4ed8;
        }


        .btn-secondary {

            background: #e2e8f0;

            color: #334155;
        }


        /* ACCOUNT */

        .avatar {

            width: 85px;

            height: 85px;

            border-radius: 50%;

            margin: 0 auto 18px;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #7c3aed
                );

            color: white;

            font-size: 32px;

            font-weight: 800;
        }


        .account-name {

            text-align: center;

            font-size: 20px;

            font-weight: 800;

            margin-bottom: 5px;
        }


        .account-email {

            text-align: center;

            color: #64748b;

            font-size: 13px;

            margin-bottom: 25px;
        }


        .info-row {

            display: flex;

            justify-content: space-between;

            gap: 15px;

            padding:
                12px 0;

            border-bottom:
                1px solid #eef2f7;

            font-size: 13px;
        }


        .info-row:last-child {
            border-bottom: none;
        }


        .info-label {

            color: #64748b;
        }


        .info-value {

            font-weight: 700;

            text-align: right;

            word-break: break-word;
        }


        /* RESPONSIVE */

        @media (max-width: 800px) {

            .grid {

                grid-template-columns: 1fr;
            }

            .navbar {

                flex-direction: column;

                gap: 12px;

                align-items: flex-start;
            }

            .nav-links {

                width: 100%;
            }

        }


        @media (max-width: 500px) {

            .container {

                width: 94%;

                margin-top: 25px;
            }

            .card {

                padding: 20px;
            }

            .nav-links a {

                font-size: 12px;

                padding:
                    8px 9px;
            }

        }

    </style>

</head>


<body>


<!-- NAVBAR -->

<nav class="navbar">

    <a
        href="dashboard.php"
        class="logo"
    >
        Book<span>Ease</span>
    </a>


    <div class="nav-links">

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="../services/index.php">
            Services
        </a>

        <a href="my-bookings.php">
            My Bookings
        </a>

        <a
            href="profile.php"
            class="active"
        >
            Profile
        </a>

        <a href="../logout.php">
            Logout
        </a>

    </div>

</nav>


<!-- CONTENT -->

<main class="container">


    <div class="page-header">

        <h1>
            My Profile
        </h1>

        <p>
            Manage your BookEase account information.
        </p>

    </div>


    <?php if ($success): ?>

        <div class="alert alert-success">

            <?= e($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert alert-error">

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <div class="grid">


        <!-- PROFILE FORM -->

        <div class="card">

            <h2>
                Personal Information
            </h2>


            <form
                method="POST"
                action=""
            >


                <div class="form-group">

                    <label for="name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        id="name"
                        class="form-control"
                        value="<?= e(
                            $user['name'] ?? ''
                        ) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="form-control"
                        value="<?= e(
                            $user['email'] ?? ''
                        ) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        type="text"
                        name="phone"
                        id="phone"
                        class="form-control"
                        value="<?= e(
                            $user['phone'] ?? ''
                        ) ?>"
                        placeholder="Enter your phone number"
                    >

                </div>


                <button
                    type="submit"
                    name="update_profile"
                    value="1"
                    class="btn btn-primary"
                >
                    Save Changes
                </button>


            </form>

        </div>


        <!-- ACCOUNT INFORMATION -->

        <div class="card">


            <div class="avatar">

                <?= e(
                    strtoupper(
                        substr(
                            $user['name'] ?? 'C',
                            0,
                            1
                        )
                    )
                ) ?>

            </div>


            <div class="account-name">

                <?= e(
                    $user['name'] ?? 'Customer'
                ) ?>

            </div>


            <div class="account-email">

                <?= e(
                    $user['email'] ?? ''
                ) ?>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Account ID
                </span>

                <span class="info-value">

                    #<?= (int) $userId ?>

                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Role
                </span>

                <span class="info-value">

                    <?= e(
                        ucfirst(
                            $_SESSION['role']
                            ?? 'customer'
                        )
                    ) ?>

                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Phone
                </span>

                <span class="info-value">

                    <?= e(
                        $user['phone']
                        ?: '-'
                    ) ?>

                </span>

            </div>


        </div>


    </div>


    <!-- PASSWORD -->

    <div
        class="card"
        style="margin-top:22px;"
    >

        <h2>
            Change Password
        </h2>


        <form
            method="POST"
            action=""
        >


            <div class="form-group">

                <label for="current_password">
                    Current Password
                </label>

                <input
                    type="password"
                    name="current_password"
                    id="current_password"
                    class="form-control"
                    required
                >

            </div>


            <div class="form-group">

                <label for="new_password">
                    New Password
                </label>

                <input
                    type="password"
                    name="new_password"
                    id="new_password"
                    class="form-control"
                    minlength="6"
                    required
                >

            </div>


            <div class="form-group">

                <label for="confirm_password">
                    Confirm New Password
                </label>

                <input
                    type="password"
                    name="confirm_password"
                    id="confirm_password"
                    class="form-control"
                    minlength="6"
                    required
                >

            </div>


            <button
                type="submit"
                name="change_password"
                value="1"
                class="btn btn-primary"
            >
                Change Password
            </button>


        </form>

    </div>


</main>


</body>


