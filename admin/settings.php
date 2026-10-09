<?php

/*
|--------------------------------------------------------------------------
| BookEase - Admin Settings
|--------------------------------------------------------------------------
| Admin settings page
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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Check Login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header(
        'Location: ' .
        BASE_URL .
        '/auth/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Check Admin / Staff Access
|--------------------------------------------------------------------------
*/

$role = strtolower(
    trim(
        $_SESSION['role'] ?? ''
    )
);

if (!in_array($role, ['admin', 'staff'], true)) {

    header(
        'Location: ' .
        BASE_URL .
        '/customer/dashboard.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

try {

    $database = new Database();

    $conn = $database->getConnection();

} catch (PDOException $e) {

    die(
        'Database connection error: ' .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
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
| Helper: Escape Output
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Get Setting
|--------------------------------------------------------------------------
*/

function getSetting(
    PDO $conn,
    string $key,
    string $default = ''
): string {

    try {

        $stmt = $conn->prepare("
            SELECT setting_value
            FROM settings
            WHERE setting_key = ?
            LIMIT 1
        ");

        $stmt->execute([
            $key
        ]);

        $value = $stmt->fetchColumn();

        return $value === false
            ? $default
            : (string) $value;

    } catch (PDOException $e) {

        return $default;
    }
}


/*
|--------------------------------------------------------------------------
| Save / Update Setting
|--------------------------------------------------------------------------
*/

function saveSetting(
    PDO $conn,
    string $key,
    string $value
): void {

    /*
    | Check whether setting already exists
    */

    $check = $conn->prepare("
        SELECT id
        FROM settings
        WHERE setting_key = ?
        LIMIT 1
    ");

    $check->execute([
        $key
    ]);

    $existingId = $check->fetchColumn();


    /*
    | Update existing setting
    */

    if ($existingId !== false) {

        $stmt = $conn->prepare("
            UPDATE settings
            SET setting_value = ?
            WHERE setting_key = ?
        ");

        $stmt->execute([
            $value,
            $key
        ]);

        return;
    }


    /*
    | Insert new setting
    */

    $stmt = $conn->prepare("
        INSERT INTO settings
        (
            setting_key,
            setting_value
        )
        VALUES
        (
            ?,
            ?
        )
    ");

    $stmt->execute([
        $key,
        $value
    ]);
}


/*
|--------------------------------------------------------------------------
| Save Settings
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $siteName = trim(
        $_POST['site_name'] ?? ''
    );

    $siteEmail = trim(
        $_POST['site_email'] ?? ''
    );

    $sitePhone = trim(
        $_POST['site_phone'] ?? ''
    );

    $currency = strtoupper(
        trim(
            $_POST['currency'] ?? 'KES'
        )
    );

    $bookingStatus = strtolower(
        trim(
            $_POST['booking_status'] ?? 'open'
        )
    );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    $allowedCurrencies = [
        'KES',
        'USD',
        'EUR'
    ];

    $allowedBookingStatuses = [
        'open',
        'closed'
    ];


    if ($siteName === '') {

        $error =
            'Business / Website name is required.';

    } elseif (
        $siteEmail !== '' &&
        !filter_var(
            $siteEmail,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            'Please enter a valid email address.';

    } elseif (
        !in_array(
            $currency,
            $allowedCurrencies,
            true
        )
    ) {

        $error =
            'Invalid currency selected.';

    } elseif (
        !in_array(
            $bookingStatus,
            $allowedBookingStatuses,
            true
        )
    ) {

        $error =
            'Invalid booking system status.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Begin Transaction
            |--------------------------------------------------------------------------
            */

            $conn->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Settings
            |--------------------------------------------------------------------------
            */

            $settings = [

                'site_name' =>
                    $siteName,

                'site_email' =>
                    $siteEmail,

                'site_phone' =>
                    $sitePhone,

                'currency' =>
                    $currency,

                'booking_status' =>
                    $bookingStatus
            ];


            /*
            |--------------------------------------------------------------------------
            | Save Every Setting
            |--------------------------------------------------------------------------
            */

            foreach (
                $settings as $key => $value
            ) {

                saveSetting(
                    $conn,
                    $key,
                    $value
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $conn->commit();


            /*
            |--------------------------------------------------------------------------
            | Update Session
            |--------------------------------------------------------------------------
            */

            $_SESSION['site_name'] =
                $siteName;

            $_SESSION['currency'] =
                $currency;


            $message =
                'Settings saved successfully.';


        } catch (PDOException $e) {

            if (
                $conn->inTransaction()
            ) {

                $conn->rollBack();
            }

            $error =
                'Unable to save settings. Please check that the settings table exists.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Current Settings
|--------------------------------------------------------------------------
*/

$siteName = getSetting(
    $conn,
    'site_name',
    'BookEase'
);

$siteEmail = getSetting(
    $conn,
    'site_email',
    ''
);

$sitePhone = getSetting(
    $conn,
    'site_phone',
    ''
);

$currency = getSetting(
    $conn,
    'currency',
    'KES'
);

$bookingStatus = getSetting(
    $conn,
    'booking_status',
    'open'
);


/*
|--------------------------------------------------------------------------
| User Information
|--------------------------------------------------------------------------
*/

$adminName =
    $_SESSION['name']
    ?? $_SESSION['username']
    ?? 'Administrator';

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
    Settings | <?= e($siteName) ?>
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
| Layout
|--------------------------------------------------------------------------
*/

.admin-layout {

    display: flex;

    min-height: 100vh;
}


/*
|--------------------------------------------------------------------------
| Sidebar
|--------------------------------------------------------------------------
*/

.sidebar {

    width: 250px;

    background: #0f172a;

    color: white;

    position: fixed;

    left: 0;

    top: 0;

    bottom: 0;

    overflow-y: auto;

    z-index: 1000;
}

.sidebar-header {

    padding: 22px 20px;

    border-bottom:
        1px solid
        rgba(255,255,255,.10);
}

.sidebar-header h2 {

    margin: 0;

    font-size: 21px;
}

.sidebar-header p {

    margin: 5px 0 0;

    color: #94a3b8;

    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

.sidebar-nav {

    padding: 15px 10px;
}

.sidebar-nav a {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 12px 14px;

    margin-bottom: 4px;

    color: #cbd5e1;

    text-decoration: none;

    border-radius: 8px;

    font-size: 14px;

    transition: .2s;
}

.sidebar-nav a:hover {

    background: #1e293b;

    color: white;
}

.sidebar-nav a.active {

    background: #2563eb;

    color: white;
}

.nav-icon {

    width: 22px;

    text-align: center;

    font-size: 16px;
}


/*
|--------------------------------------------------------------------------
| Main
|--------------------------------------------------------------------------
*/

.main {

    margin-left: 250px;

    width: calc(100% - 250px);

    min-height: 100vh;
}


/*
|--------------------------------------------------------------------------
| Topbar
|--------------------------------------------------------------------------
*/

.topbar {

    height: 70px;

    background: white;

    border-bottom:
        1px solid #e2e8f0;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;
}

.topbar-title {

    font-size: 20px;

    font-weight: 700;
}

.topbar-user {

    color: #64748b;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| Content
|--------------------------------------------------------------------------
*/

.content {

    max-width: 1050px;

    margin: 0 auto;

    padding: 30px;
}


/*
|--------------------------------------------------------------------------
| Page Title
|--------------------------------------------------------------------------
*/

.page-title {

    margin-bottom: 25px;
}

.page-title h1 {

    margin: 0;

    font-size: 28px;
}

.page-title p {

    margin-top: 7px;

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


/*
|--------------------------------------------------------------------------
| Form Card
|--------------------------------------------------------------------------
*/

.form-card {

    background: white;

    padding: 30px;

    border-radius: 12px;

    border:
        1px solid #e2e8f0;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,.05);
}

.section-title {

    margin-top: 0;

    margin-bottom: 25px;

    font-size: 20px;
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

    border:
        1px solid #cbd5e1;

    border-radius: 8px;

    background: white;

    color: #1e293b;

    font-size: 15px;

    outline: none;
}

.form-control:focus {

    border-color: #2563eb;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.10);
}


/*
|--------------------------------------------------------------------------
| Button
|--------------------------------------------------------------------------
*/

.btn {

    display: inline-block;

    border: none;

    border-radius: 8px;

    padding: 13px 20px;

    background: #2563eb;

    color: white;

    font-size: 15px;

    font-weight: 600;

    cursor: pointer;

    text-decoration: none;
}

.btn:hover {

    background: #1d4ed8;
}


/*
|--------------------------------------------------------------------------
| Footer
|--------------------------------------------------------------------------
*/

.footer {

    padding: 25px 30px;

    color: #64748b;

    text-align: center;

    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 800px) {

    .sidebar {

        width: 220px;
    }

    .main {

        margin-left: 220px;

        width: calc(100% - 220px);
    }

    .topbar {

        padding: 0 20px;
    }

    .content {

        padding: 20px;
    }

}

@media (max-width: 600px) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;

        max-height: none;
    }

    .admin-layout {

        display: block;
    }

    .main {

        margin-left: 0;

        width: 100%;
    }

    .topbar {

        height: auto;

        padding: 15px;

        gap: 10px;

        flex-direction: column;

        align-items: flex-start;
    }

    .content {

        padding: 15px;
    }

    .form-card {

        padding: 20px;
    }

    .page-title h1 {

        font-size: 24px;
    }

}

</style>

</head>


<body>


<div class="admin-layout">


    <!-- ================================================================
         SIDEBAR
    ================================================================= -->


    <aside class="sidebar">


        <div class="sidebar-header">

            <h2>
                <?= e($siteName) ?>
            </h2>

            <p>
                Admin Panel
            </p>

        </div>


        <nav class="sidebar-nav">


            <a
                href="<?= BASE_URL ?>/admin/dashboard.php"
            >

                <span class="nav-icon">
                    🏠
                </span>

                Dashboard

            </a>


            <a
                href="<?= BASE_URL ?>/admin/bookings.php"
            >

                <span class="nav-icon">
                    📅
                </span>

                Bookings

            </a>


            <a
                href="<?= BASE_URL ?>/admin/customers.php"
            >

                <span class="nav-icon">
                    👥
                </span>

                Customers

            </a>


            <a
                href="<?= BASE_URL ?>/admin/services.php"
            >

                <span class="nav-icon">
                    🛎️
                </span>

                Services

            </a>


            <a
                href="<?= BASE_URL ?>/admin/payments.php"
            >

                <span class="nav-icon">
                    💳
                </span>

                Payments

            </a>


            <a
                href="<?= BASE_URL ?>/admin/reports.php"
            >

                <span class="nav-icon">
                    📊
                </span>

                Reports

            </a>


            <a
                href="<?= BASE_URL ?>/admin/profile.php"
            >

                <span class="nav-icon">
                    👤
                </span>

                My Profile

            </a>


            <a
                href="<?= BASE_URL ?>/admin/settings.php"
                class="active"
            >

                <span class="nav-icon">
                    ⚙️
                </span>

                Settings

            </a>


            <a
                href="<?= BASE_URL ?>/logout.php"
            >

                <span class="nav-icon">
                    🚪
                </span>

                Logout

            </a>


        </nav>

    </aside>


    <!-- ================================================================
         MAIN
    ================================================================= -->


    <main class="main">


        <!-- TOPBAR -->

        <header class="topbar">

            <div class="topbar-title">

                Settings

            </div>


            <div class="topbar-user">

                Welcome,
                <strong>
                    <?= e($adminName) ?>
                </strong>

                (<?= e(ucfirst($role)) ?>)

            </div>

        </header>


        <!-- CONTENT -->

        <div class="content">


            <!-- PAGE TITLE -->

            <div class="page-title">

                <h1>
                    Settings
                </h1>

                <p>
                    Configure your BookEase booking system.
                </p>

            </div>


            <!-- SUCCESS -->

            <?php if ($message !== ''): ?>

                <div class="alert alert-success">

                    <?= e($message) ?>

                </div>

            <?php endif; ?>


            <!-- ERROR -->

            <?php if ($error !== ''): ?>

                <div class="alert alert-error">

                    <?= e($error) ?>

                </div>

            <?php endif; ?>


            <!-- SETTINGS -->

            <div class="form-card">


                <h2 class="section-title">

                    General System Settings

                </h2>


                <form
                    method="POST"
                    action="<?= e($_SERVER['PHP_SELF']) ?>"
                >


                    <!-- SITE NAME -->

                    <div class="form-group">

                        <label
                            for="site_name"
                        >
                            Business / Website Name
                        </label>

                        <input
                            type="text"
                            id="site_name"
                            name="site_name"
                            class="form-control"
                            value="<?= e($siteName) ?>"
                            placeholder="BookEase"
                            required
                        >

                    </div>


                    <!-- EMAIL -->

                    <div class="form-group">

                        <label
                            for="site_email"
                        >
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="site_email"
                            name="site_email"
                            class="form-control"
                            value="<?= e($siteEmail) ?>"
                            placeholder="example@gmail.com"
                        >

                    </div>


                    <!-- PHONE -->

                    <div class="form-group">

                        <label
                            for="site_phone"
                        >
                            Phone Number
                        </label>

                        <input
                            type="text"
                            id="site_phone"
                            name="site_phone"
                            class="form-control"
                            value="<?= e($sitePhone) ?>"
                            placeholder="0712345678"
                        >

                    </div>


                    <!-- CURRENCY -->

                    <div class="form-group">

                        <label
                            for="currency"
                        >
                            Currency
                        </label>

                        <select
                            id="currency"
                            name="currency"
                            class="form-control"
                        >

                            <option
                                value="KES"
                                <?= $currency === 'KES'
                                    ? 'selected'
                                    : '' ?>
                            >
                                KES - Kenyan Shilling
                            </option>

                            <option
                                value="USD"
                                <?= $currency === 'USD'
                                    ? 'selected'
                                    : '' ?>
                            >
                                USD - US Dollar
                            </option>

                            <option
                                value="EUR"
                                <?= $currency === 'EUR'
                                    ? 'selected'
                                    : '' ?>
                            >
                                EUR - Euro
                            </option>

                        </select>

                    </div>


                    <!-- BOOKING STATUS -->

                    <div class="form-group">

                        <label
                            for="booking_status"
                        >
                            Booking System Status
                        </label>

                        <select
                            id="booking_status"
                            name="booking_status"
                            class="form-control"
                        >

                            <option
                                value="open"
                                <?= $bookingStatus === 'open'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Open - Customers can make bookings
                            </option>

                            <option
                                value="closed"
                                <?= $bookingStatus === 'closed'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Closed - New bookings disabled
                            </option>

                        </select>

                    </div>


                    <!-- SAVE -->

                    <button
                        type="submit"
                        class="btn"
                    >

                        Save Settings

                    </button>


                </form>


            </div>


        </div>


        <!-- FOOTER -->

        <footer class="footer">

            &copy;
            <?= date('Y') ?>
            <?= e($siteName) ?>.
            All rights reserved.

        </footer>


    </main>


</div>


</body>

</html>