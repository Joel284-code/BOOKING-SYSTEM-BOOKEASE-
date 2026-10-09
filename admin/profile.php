<?php

/*
|--------------------------------------------------------------------------
| BookEase - Admin Profile
|--------------------------------------------------------------------------
| Location:
| C:\xampp\htdocs\booking-system\admin\profile.php
|--------------------------------------------------------------------------
*/

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Load Core Files
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';


/*
|--------------------------------------------------------------------------
| Start Session
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Helper: Safe Redirect
|--------------------------------------------------------------------------
*/

function adminProfileRedirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}


/*
|--------------------------------------------------------------------------
| Check Login
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['user_id'])) {

    adminProfileRedirect(
        BASE_URL . '/auth/login.php'
    );
}


/*
|--------------------------------------------------------------------------
| Get Current User
|--------------------------------------------------------------------------
*/

$userId = (int) $_SESSION['user_id'];

$role = strtolower(
    trim(
        (string) ($_SESSION['role'] ?? '')
    )
);


/*
|--------------------------------------------------------------------------
| Check Admin / Staff Access
|--------------------------------------------------------------------------
*/

if (!in_array($role, ['admin', 'staff'], true)) {

    adminProfileRedirect(
        BASE_URL . '/customer/dashboard.php'
    );
}


/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

$conn = null;

try {

    $database = new Database();

    $conn = $database->getConnection();

    if (!$conn instanceof PDO) {
        throw new RuntimeException(
            'Invalid database connection.'
        );
    }

} catch (Throwable $e) {

    http_response_code(500);

    die(
        'Database connection error.'
    );
}


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$message = '';
$error = '';


/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
| Creates a token if your application does not already have one.
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] =
        bin2hex(
            random_bytes(32)
        );
}

$csrfToken =
    $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| Load Current User
|--------------------------------------------------------------------------
*/

$admin = [
    'id' => $userId,
    'name' => '',
    'email' => '',
    'phone' => '',
    'role' => $role,
    'status' => 'active'
];


try {

    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            email,
            phone,
            role,
            status
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $userId
    ]);

    $result =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$result) {

        unset(
            $_SESSION['user_id'],
            $_SESSION['name'],
            $_SESSION['email'],
            $_SESSION['phone'],
            $_SESSION['role']
        );

        adminProfileRedirect(
            BASE_URL . '/auth/login.php'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Security Check
    |--------------------------------------------------------------------------
    */

    $databaseRole =
        strtolower(
            trim(
                (string) (
                    $result['role'] ?? ''
                )
            )
        );


    if (
        !in_array(
            $databaseRole,
            ['admin', 'staff'],
            true
        )
    ) {

        unset(
            $_SESSION['user_id'],
            $_SESSION['name'],
            $_SESSION['email'],
            $_SESSION['phone'],
            $_SESSION['role']
        );

        adminProfileRedirect(
            BASE_URL . '/auth/login.php'
        );
    }


    $admin = array_merge(
        $admin,
        $result
    );


    /*
    |--------------------------------------------------------------------------
    | Keep Session Synchronized
    |--------------------------------------------------------------------------
    */

    $_SESSION['user_id'] =
        (int) $admin['id'];

    $_SESSION['name'] =
        (string) ($admin['name'] ?? '');

    $_SESSION['email'] =
        (string) ($admin['email'] ?? '');

    $_SESSION['phone'] =
        (string) ($admin['phone'] ?? '');

    $_SESSION['role'] =
        $databaseRole;


} catch (Throwable $e) {

    $error =
        'Unable to load your profile.';
}


/*
|--------------------------------------------------------------------------
| Process Profile Update
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $error === ''
) {

    /*
    |--------------------------------------------------------------------------
    | Verify CSRF
    |--------------------------------------------------------------------------
    */

    $submittedToken =
        (string) (
            $_POST['csrf_token']
            ?? ''
        );


    if (
        !hash_equals(
            $csrfToken,
            $submittedToken
        )
    ) {

        $error =
            'Invalid request. Please refresh the page and try again.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Get Form Values
        |--------------------------------------------------------------------------
        */

        $name =
            trim(
                (string) (
                    $_POST['name']
                    ?? ''
                )
            );


        $phone =
            trim(
                (string) (
                    $_POST['phone']
                    ?? ''
                )
            );


        $newPassword =
            (string) (
                $_POST['password']
                ?? ''
            );


        /*
        |--------------------------------------------------------------------------
        | Validate Name
        |--------------------------------------------------------------------------
        */

        if ($name === '') {

            $error =
                'Full name is required.';

        } elseif (mb_strlen($name) < 2) {

            $error =
                'Full name must contain at least 2 characters.';

        }


        /*
        |--------------------------------------------------------------------------
        | Validate Phone
        |--------------------------------------------------------------------------
        */

        elseif (
            $phone !== '' &&
            mb_strlen($phone) > 30
        ) {

            $error =
                'Phone number is too long.';

        }


        /*
        |--------------------------------------------------------------------------
        | Validate Password
        |--------------------------------------------------------------------------
        */

        elseif (
            $newPassword !== '' &&
            strlen($newPassword) < 6
        ) {

            $error =
                'New password must contain at least 6 characters.';

        }


        /*
        |--------------------------------------------------------------------------
        | Update Database
        |--------------------------------------------------------------------------
        */

        else {

            try {

                $conn->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | Update Name + Phone
                |--------------------------------------------------------------------------
                */

                if ($newPassword === '') {

                    $stmt = $conn->prepare("
                        UPDATE users
                        SET
                            name = ?,
                            phone = ?
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $name,
                        $phone,
                        $userId
                    ]);

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Hash New Password
                    |--------------------------------------------------------------------------
                    */

                    $hashedPassword =
                        password_hash(
                            $newPassword,
                            PASSWORD_DEFAULT
                        );


                    if ($hashedPassword === false) {

                        throw new RuntimeException(
                            'Unable to secure the new password.'
                        );
                    }


                    $stmt = $conn->prepare("
                        UPDATE users
                        SET
                            name = ?,
                            phone = ?,
                            password = ?
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $name,
                        $phone,
                        $hashedPassword,
                        $userId
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Commit
                |--------------------------------------------------------------------------
                */

                $conn->commit();


                /*
                |--------------------------------------------------------------------------
                | Synchronize Session
                |--------------------------------------------------------------------------
                */

                $_SESSION['name'] =
                    $name;

                $_SESSION['phone'] =
                    $phone;


                /*
                |--------------------------------------------------------------------------
                | Update Local Data
                |--------------------------------------------------------------------------
                */

                $admin['name'] =
                    $name;

                $admin['phone'] =
                    $phone;


                /*
                |--------------------------------------------------------------------------
                | Success
                |--------------------------------------------------------------------------
                */

                $message =
                    'Profile updated successfully.';

            } catch (Throwable $e) {

                if (
                    $conn->inTransaction()
                ) {

                    $conn->rollBack();
                }


                $error =
                    'Unable to update your profile. Please try again.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Initials
|--------------------------------------------------------------------------
*/

$displayName =
    trim(
        (string) (
            $admin['name']
            ?? 'Administrator'
        )
    );


$firstLetter =
    strtoupper(
        mb_substr(
            $displayName !== ''
                ? $displayName
                : 'A',
            0,
            1
        )
    );


/*
|--------------------------------------------------------------------------
| Escape Helper
|--------------------------------------------------------------------------
*/

function profileEscape($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
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
    My Profile | <?= profileEscape(APP_NAME) ?>
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
    background: #f1f5f9;
    color: #1e293b;
}


/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

.topbar {
    background: #0f172a;
    color: #fff;
    min-height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
    gap: 20px;
}

.brand {
    color: #fff;
    text-decoration: none;
    font-size: 20px;
    font-weight: 700;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.nav-links a {
    color: #cbd5e1;
    text-decoration: none;
    padding: 9px 12px;
    border-radius: 7px;
    font-size: 14px;
}

.nav-links a:hover,
.nav-links a.active {
    background: #1e293b;
    color: #fff;
}

.logout {
    background: #dc2626 !important;
    color: #fff !important;
}


/*
|--------------------------------------------------------------------------
| Main
|--------------------------------------------------------------------------
*/

.content {
    max-width: 1000px;
    margin: 0 auto;
    padding: 30px;
}

.page-title {
    margin-bottom: 25px;
}

.page-title h1 {
    margin: 0;
    font-size: 28px;
}

.page-title p {
    margin: 7px 0 0;
    color: #64748b;
}


/*
|--------------------------------------------------------------------------
| Alerts
|--------------------------------------------------------------------------
*/

.alert {
    padding: 14px 16px;
    border-radius: 8px;
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


/*
|--------------------------------------------------------------------------
| Card
|--------------------------------------------------------------------------
*/

.form-card {
    background: #fff;
    padding: 30px;
    border-radius: 14px;
    box-shadow:
        0 5px 20px
        rgba(0, 0, 0, .05);
}


/*
|--------------------------------------------------------------------------
| Profile Header
|--------------------------------------------------------------------------
*/

.profile-header {
    display: flex;
    align-items: center;
    gap: 18px;
    padding-bottom: 25px;
    margin-bottom: 25px;
    border-bottom: 1px solid #e2e8f0;
}

.profile-avatar {
    width: 68px;
    height: 68px;
    min-width: 68px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #2563eb;
    color: #fff;
    font-size: 26px;
    font-weight: bold;
}

.profile-header h2 {
    margin: 0 0 5px;
    font-size: 21px;
}

.profile-header p {
    margin: 0 0 5px;
    color: #64748b;
}

.role {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 20px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 700;
    text-transform: capitalize;
}


/*
|--------------------------------------------------------------------------
| Form
|--------------------------------------------------------------------------
*/

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #334155;
}

.form-control {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 15px;
    outline: none;
    background: #fff;
    color: #1e293b;
}

.form-control:focus {
    border-color: #2563eb;
    box-shadow:
        0 0 0 3px
        rgba(37, 99, 235, .10);
}

.form-control:disabled {
    background: #f8fafc;
    color: #64748b;
    cursor: not-allowed;
}

.help-text {
    margin-top: 6px;
    color: #64748b;
    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| Buttons
|--------------------------------------------------------------------------
*/

.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.btn {
    display: inline-block;
    border: none;
    padding: 13px 20px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
}

.btn-primary {
    background: #2563eb;
    color: #fff;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-secondary {
    background: #e2e8f0;
    color: #334155;
}

.btn-secondary:hover {
    background: #cbd5e1;
}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 800px) {

    .topbar {
        padding: 15px;
        align-items: flex-start;
        flex-direction: column;
    }

    .nav-links {
        width: 100%;
    }

    .content {
        padding: 15px;
    }

}

@media (max-width: 600px) {

    .form-card {
        padding: 20px;
    }

    .page-title h1 {
        font-size: 24px;
    }

    .profile-header {
        align-items: flex-start;
    }

    .nav-links a {
        font-size: 13px;
    }

}

</style>

</head>

<body>


<!--
|--------------------------------------------------------------------------
| ADMIN NAVIGATION
|--------------------------------------------------------------------------
| These links connect this page with the other admin pages.
|--------------------------------------------------------------------------
-->

<header class="topbar">

    <a
        href="<?= profileEscape(BASE_URL) ?>/admin/dashboard.php"
        class="brand"
    >
        <?= profileEscape(APP_NAME) ?>
    </a>


    <nav class="nav-links">

        <a
            href="<?= profileEscape(BASE_URL) ?>/admin/dashboard.php"
        >
            Dashboard
        </a>

        <a
            href="<?= profileEscape(BASE_URL) ?>/admin/bookings.php"
        >
            Bookings
        </a>

        <a
            href="<?= profileEscape(BASE_URL) ?>/admin/customers.php"
        >
            Customers
        </a>

        <a
            href="<?= profileEscape(BASE_URL) ?>/admin/services.php"
        >
            Services
        </a>

        <a
            href="<?= profileEscape(BASE_URL) ?>/admin/payments.php"
        >
            Payments
        </a>

        <a
            href="<?= profileEscape(BASE_URL) ?>/admin/profile.php"
            class="active"
        >
            Profile
        </a>

        <a
            href="<?= profileEscape(BASE_URL) ?>/logout.php"
            class="logout"
        >
            Logout
        </a>

    </nav>

</header>


<main class="content">


    <!-- PAGE TITLE -->

    <div class="page-title">

        <h1>
            My Profile
        </h1>

        <p>
            Manage your <?= profileEscape($role) ?> account.
        </p>

    </div>


    <!-- SUCCESS -->

    <?php if ($message !== ''): ?>

        <div class="alert alert-success">

            <?= profileEscape($message) ?>

        </div>

    <?php endif; ?>


    <!-- ERROR -->

    <?php if ($error !== ''): ?>

        <div class="alert alert-error">

            <?= profileEscape($error) ?>

        </div>

    <?php endif; ?>


    <!-- PROFILE CARD -->

    <div class="form-card">


        <!-- PROFILE HEADER -->

        <div class="profile-header">

            <div class="profile-avatar">

                <?= profileEscape($firstLetter) ?>

            </div>


            <div>

                <h2>

                    <?= profileEscape(
                        $admin['name']
                        ?: 'Administrator'
                    ) ?>

                </h2>

                <p>

                    <?= profileEscape(
                        $admin['email']
                        ?: ''
                    ) ?>

                </p>

                <span class="role">

                    <?= profileEscape(
                        $admin['role']
                        ?: $role
                    ) ?>

                </span>

            </div>

        </div>


        <!-- FORM -->

        <form
            method="POST"
            action=""
            autocomplete="off"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= profileEscape($csrfToken) ?>"
            >


            <!-- NAME -->

            <div class="form-group">

                <label for="name">
                    Full Name
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    value="<?= profileEscape(
                        $admin['name']
                        ?? ''
                    ) ?>"
                    maxlength="100"
                    required
                >

            </div>


            <!-- EMAIL -->

            <div class="form-group">

                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    class="form-control"
                    value="<?= profileEscape(
                        $admin['email']
                        ?? ''
                    ) ?>"
                    disabled
                >

                <div class="help-text">
                    Email is managed separately and cannot be changed here.
                </div>

            </div>


            <!-- PHONE -->

            <div class="form-group">

                <label for="phone">
                    Phone Number
                </label>

                <input
                    type="text"
                    id="phone"
                    name="phone"
                    class="form-control"
                    value="<?= profileEscape(
                        $admin['phone']
                        ?? ''
                    ) ?>"
                    maxlength="30"
                    placeholder="Enter phone number"
                >

            </div>


            <!-- PASSWORD -->

            <div class="form-group">

                <label for="password">
                    New Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="Leave blank to keep current password"
                    minlength="6"
                    autocomplete="new-password"
                >

                <div class="help-text">
                    Leave this field blank if you do not want to change your password.
                </div>

            </div>


            <!-- ACTIONS -->

            <div class="actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Update Profile
                </button>


                <a
                    href="<?= profileEscape(BASE_URL) ?>/admin/dashboard.php"
                    class="btn btn-secondary"
                >
                    Back to Dashboard
                </a>

            </div>

        </form>

    </div>

</main>


</body>

</html>