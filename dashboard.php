<?php

/*
|--------------------------------------------------------------------------
| BookEase - Customer Dashboard
|--------------------------------------------------------------------------
| File:
| /booking-system/dashboard.php
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';


/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Require Login
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| User Information
|--------------------------------------------------------------------------
*/

$userId = (int) ($_SESSION['user_id'] ?? 0);

$name = $_SESSION['name'] ?? 'Customer';

$email = $_SESSION['email'] ?? '';

$phone = $_SESSION['phone'] ?? '';

$role = strtolower(
    trim(
        $_SESSION['role'] ?? 'customer'
    )
);


if ($userId <= 0) {

    session_unset();
    session_destroy();

    header(
        'Location: ' .
        BASE_URL .
        '/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

$conn = null;

$databaseError = '';

try {

    $database = new Database();

    $conn = $database->getConnection();

    /*
    |--------------------------------------------------------------------------
    | Verify PDO Connection
    |--------------------------------------------------------------------------
    */

    if (!($conn instanceof PDO)) {

        throw new RuntimeException(
            'Database connection was not created correctly.'
        );
    }

} catch (Throwable $e) {

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        $databaseError =
            'Database connection error: ' .
            $e->getMessage();

    } else {

        $databaseError =
            'Unable to connect to the database. Please try again later.';
    }
}


/*
|--------------------------------------------------------------------------
| Default Statistics
|--------------------------------------------------------------------------
*/

$totalBookings = 0;

$pendingBookings = 0;

$confirmedBookings = 0;

$cancelledBookings = 0;

$completedBookings = 0;

$totalSpent = 0;

$upcomingBookings = [];


/*
|--------------------------------------------------------------------------
| Load Dashboard Data
|--------------------------------------------------------------------------
*/

if ($conn instanceof PDO) {

    /*
    |--------------------------------------------------------------------------
    | Booking Statistics
    |--------------------------------------------------------------------------
    */

    try {

        $stmt = $conn->prepare("
            SELECT

                COUNT(*) AS total_bookings,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'pending'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS pending_bookings,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'confirmed'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS confirmed_bookings,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'cancelled'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS cancelled_bookings,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'completed'
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS completed_bookings,

                COALESCE(
                    SUM(
                        CASE
                            WHEN status <> 'cancelled'
                            THEN COALESCE(total_amount, 0)
                            ELSE 0
                        END
                    ),
                    0
                ) AS total_spent

            FROM bookings

            WHERE customer_id = ?
        ");

        $stmt->execute([
            $userId
        ]);

        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($stats) {

            $totalBookings =
                (int) ($stats['total_bookings'] ?? 0);

            $pendingBookings =
                (int) ($stats['pending_bookings'] ?? 0);

            $confirmedBookings =
                (int) ($stats['confirmed_bookings'] ?? 0);

            $cancelledBookings =
                (int) ($stats['cancelled_bookings'] ?? 0);

            $completedBookings =
                (int) ($stats['completed_bookings'] ?? 0);

            $totalSpent =
                (float) ($stats['total_spent'] ?? 0);
        }

    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | Ignore statistics errors
        |--------------------------------------------------------------------------
        */

        if (
            defined('DEVELOPMENT_MODE') &&
            DEVELOPMENT_MODE === true
        ) {

            $databaseError =
                'Dashboard statistics error: ' .
                $e->getMessage();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Upcoming Bookings
    |--------------------------------------------------------------------------
    */

    try {

        $stmt = $conn->prepare("
            SELECT

                b.id,

                b.booking_reference,

                b.booking_date,

                b.booking_time,

                b.status,

                b.total_amount,

                s.name AS service_name

            FROM bookings b

            LEFT JOIN services s
                ON s.id = b.service_id

            WHERE b.customer_id = ?

            AND b.status IN (
                'pending',
                'confirmed'
            )

            AND (
                b.booking_date > CURDATE()

                OR (

                    b.booking_date = CURDATE()

                    AND (
                        b.booking_time IS NULL
                        OR b.booking_time >= CURTIME()
                    )

                )
            )

            ORDER BY

                b.booking_date ASC,

                b.booking_time ASC

            LIMIT 5
        ");

        $stmt->execute([
            $userId
        ]);

        $upcomingBookings =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {

        $upcomingBookings = [];

        if (
            defined('DEVELOPMENT_MODE') &&
            DEVELOPMENT_MODE === true
        ) {

            $databaseError =
                'Upcoming bookings error: ' .
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Helper
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
| Avatar Initial
|--------------------------------------------------------------------------
*/

$avatarLetter = strtoupper(
    substr(
        trim($name ?: 'C'),
        0,
        1
    )
);


/*
|--------------------------------------------------------------------------
| Currency
|--------------------------------------------------------------------------
*/

$currency =
    defined('CURRENCY')
        ? CURRENCY
        : 'KSh';

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
    Dashboard |
    <?= e(
        defined('APP_NAME')
            ? APP_NAME
            : 'BookEase'
    ) ?>
</title>

<style>

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
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

    color: #0f172a;
}


/* NAVBAR */

.navbar {

    min-height: 72px;

    background: rgba(255,255,255,.96);

    border-bottom:
        1px solid #e2e8f0;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 12px 5%;

    position: sticky;

    top: 0;

    z-index: 1000;
}

.logo {

    color: #2563eb;

    font-size: 25px;

    font-weight: 800;

    text-decoration: none;
}

.logo span {
    color: #0f172a;
}

.nav-links {

    display: flex;

    gap: 7px;

    align-items: center;
}

.nav-links a {

    color: #475569;

    text-decoration: none;

    padding: 9px 12px;

    border-radius: 8px;

    font-size: 14px;

    font-weight: 600;
}

.nav-links a:hover,
.nav-links a.active {

    background: #eff6ff;

    color: #2563eb;
}

.user-area {

    display: flex;

    align-items: center;

    gap: 12px;
}

.user-info {
    text-align: right;
}

.user-name {

    display: block;

    font-weight: 700;

    font-size: 14px;
}

.user-email {

    display: block;

    color: #64748b;

    font-size: 12px;
}

.avatar {

    width: 42px;

    height: 42px;

    border-radius: 50%;

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

    font-weight: 800;
}

.logout {

    background: #fee2e2;

    color: #dc2626;

    text-decoration: none;

    padding: 9px 13px;

    border-radius: 8px;

    font-size: 13px;

    font-weight: 700;
}

.logout:hover {
    background: #fecaca;
}


/* MAIN */

.container {

    width: 92%;

    max-width: 1250px;

    margin: 35px auto 60px;
}


/* DATABASE ERROR */

.database-error {

    background: #fee2e2;

    border:
        1px solid #fecaca;

    color: #991b1b;

    padding: 15px 18px;

    border-radius: 10px;

    margin-bottom: 22px;

    line-height: 1.6;

    font-size: 14px;
}

.database-error strong {
    display: block;
    margin-bottom: 5px;
}


/* HERO */

.hero {

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5,
            #7c3aed
        );

    color: white;

    padding: 40px;

    border-radius: 20px;

    margin-bottom: 25px;

    box-shadow:
        0 18px 45px
        rgba(37,99,235,.22);
}

.hero h1 {

    margin: 0 0 10px;

    font-size: 34px;
}

.hero p {

    margin: 0;

    color: rgba(255,255,255,.9);

    line-height: 1.7;
}

.hero-actions {

    margin-top: 23px;

    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}

.hero-btn {

    display: inline-block;

    text-decoration: none;

    padding: 12px 18px;

    border-radius: 9px;

    font-weight: 700;

    font-size: 14px;
}

.hero-btn-primary {

    background: white;

    color: #2563eb;
}

.hero-btn-secondary {

    background:
        rgba(255,255,255,.15);

    color: white;

    border:
        1px solid rgba(255,255,255,.3);
}


/* STATISTICS */

.stats-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 17px;

    margin-bottom: 25px;
}

.stat-card {

    background: white;

    border:
        1px solid #e2e8f0;

    border-radius: 15px;

    padding: 22px;

    box-shadow:
        0 7px 25px
        rgba(15,23,42,.05);
}

.stat-label {

    color: #64748b;

    font-size: 13px;

    font-weight: 600;
}

.stat-value {

    font-size: 27px;

    font-weight: 800;

    margin-top: 12px;
}

.stat-small {

    color: #94a3b8;

    font-size: 12px;

    margin-top: 5px;
}


/* CONTENT */

.content-grid {

    display: grid;

    grid-template-columns:
        1.7fr 1fr;

    gap: 20px;
}

.panel {

    background: white;

    border:
        1px solid #e2e8f0;

    border-radius: 15px;

    box-shadow:
        0 7px 25px
        rgba(15,23,42,.05);

    overflow: hidden;
}

.panel-header {

    padding: 21px 23px;

    border-bottom:
        1px solid #eef2f7;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.panel-header h2 {

    margin: 0;

    font-size: 18px;
}

.panel-header a {

    color: #2563eb;

    text-decoration: none;

    font-size: 13px;

    font-weight: 700;
}

.panel-body {
    padding: 22px;
}


/* BOOKINGS */

.booking-item {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    padding: 16px 0;

    border-bottom:
        1px solid #f1f5f9;
}

.booking-item:last-child {
    border-bottom: none;
}

.booking-main {

    display: flex;

    align-items: center;

    gap: 13px;

    min-width: 0;
}

.booking-icon {

    width: 42px;

    height: 42px;

    border-radius: 10px;

    background: #eff6ff;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;
}

.booking-title {

    font-weight: 700;

    font-size: 14px;

    margin-bottom: 4px;
}

.booking-meta {

    color: #64748b;

    font-size: 12px;
}

.booking-right {
    text-align: right;
}

.amount {

    font-weight: 800;

    margin-bottom: 5px;
}

.status {

    display: inline-block;

    padding: 4px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 700;

    text-transform: capitalize;
}

.status-pending {

    background: #fef3c7;

    color: #92400e;
}

.status-confirmed {

    background: #dcfce7;

    color: #166534;
}

.status-cancelled {

    background: #fee2e2;

    color: #991b1b;
}

.status-completed {

    background: #dbeafe;

    color: #1d4ed8;
}


/* EMPTY */

.empty {

    text-align: center;

    padding: 35px 15px;

    color: #64748b;
}

.empty h3 {

    color: #334155;

    margin-bottom: 7px;
}

.empty p {
    font-size: 14px;
}


/* ACTIONS */

.action-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 12px;
}

.action {

    display: block;

    padding: 15px;

    border-radius: 11px;

    background: #f8fafc;

    color: #0f172a;

    text-decoration: none;

    border:
        1px solid #eef2f7;
}

.action:hover {

    background: #eff6ff;

    border-color: #bfdbfe;
}

.action strong {

    display: block;

    font-size: 13px;
}

.action span {

    display: block;

    color: #64748b;

    font-size: 11px;

    margin-top: 3px;
}


/* PROFILE */

.profile-box {

    margin-top: 20px;

    padding: 18px;

    border-radius: 12px;

    background:
        linear-gradient(
            135deg,
            #f8fafc,
            #eff6ff
        );

    border:
        1px solid #dbeafe;
}

.profile-title {

    font-weight: 800;

    margin-bottom: 12px;
}

.profile-row {

    display: flex;

    justify-content: space-between;

    gap: 10px;

    padding: 8px 0;

    font-size: 13px;

    border-bottom:
        1px solid rgba(148,163,184,.15);
}

.profile-row:last-child {
    border-bottom: none;
}

.profile-label {
    color: #64748b;
}

.profile-value {

    font-weight: 600;

    text-align: right;

    word-break: break-word;
}


/* RESPONSIVE */

@media (max-width: 1000px) {

    .stats-grid {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 750px) {

    .nav-links {
        display: none;
    }

    .user-info {
        display: none;
    }

    .container {
        width: 94%;
    }

    .hero {
        padding: 30px 25px;
    }

    .hero h1 {
        font-size: 27px;
    }
}

@media (max-width: 550px) {

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .action-grid {
        grid-template-columns: 1fr;
    }

    .booking-item {
        align-items: flex-start;
    }
}

</style>

</head>

<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar">

    <a
        href="<?= BASE_URL ?>/dashboard.php"
        class="logo"
    >
        Book<span>Ease</span>
    </a>


    <div class="nav-links">

        <a
            href="<?= BASE_URL ?>/dashboard.php"
            class="active"
        >
            Dashboard
        </a>

        <a
            href="<?= BASE_URL ?>/services/index.php"
        >
            Services
        </a>

        <a
            href="<?= BASE_URL ?>/customer/my-bookings.php"
        >
            My Bookings
        </a>

        <a
            href="<?= BASE_URL ?>/customer/profile.php"
        >
            Profile
        </a>

    </div>


    <div class="user-area">

        <div class="user-info">

            <span class="user-name">
                <?= e($name) ?>
            </span>

            <span class="user-email">
                <?= e($email) ?>
            </span>

        </div>


        <div class="avatar">
            <?= e($avatarLetter) ?>
        </div>


        <a
            href="<?= BASE_URL ?>/logout.php"
            class="logout"
        >
            Logout
        </a>

    </div>

</nav>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="container">


<?php if ($databaseError !== ''): ?>

    <div class="database-error">

        <strong>
            Database connection error
        </strong>

        <?= e($databaseError) ?>

    </div>

<?php endif; ?>


<!-- HERO -->

<section class="hero">

    <h1>
        Welcome back, <?= e($name) ?> 👋
    </h1>

    <p>
        Manage your bookings, explore available services,
        and keep your appointments organized from one place.
    </p>

    <div class="hero-actions">

        <a
            href="<?= BASE_URL ?>/services/index.php"
            class="hero-btn hero-btn-primary"
        >
            📅 Browse Services
        </a>

        <a
            href="<?= BASE_URL ?>/customer/my-bookings.php"
            class="hero-btn hero-btn-secondary"
        >
            View My Bookings
        </a>

    </div>

</section>


<!-- STATISTICS -->

<section class="stats-grid">

    <div class="stat-card">

        <div class="stat-label">
            Total Bookings
        </div>

        <div class="stat-value">
            <?= number_format($totalBookings) ?>
        </div>

        <div class="stat-small">
            All your bookings
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">
            Pending
        </div>

        <div class="stat-value">
            <?= number_format($pendingBookings) ?>
        </div>

        <div class="stat-small">
            Awaiting confirmation
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">
            Confirmed
        </div>

        <div class="stat-value">
            <?= number_format($confirmedBookings) ?>
        </div>

        <div class="stat-small">
            Confirmed bookings
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">
            Total Spent
        </div>

        <div class="stat-value">

            <?= e($currency) ?>

            <?= number_format(
                $totalSpent,
                2
            ) ?>

        </div>

        <div class="stat-small">
            Non-cancelled bookings
        </div>

    </div>

</section>


<!-- CONTENT -->

<section class="content-grid">


<!-- UPCOMING -->

<div class="panel">

    <div class="panel-header">

        <h2>
            Upcoming Bookings
        </h2>

        <a
            href="<?= BASE_URL ?>/customer/my-bookings.php"
        >
            View all →
        </a>

    </div>


    <div class="panel-body">


    <?php if (empty($upcomingBookings)): ?>

        <div class="empty">

            <h3>
                No upcoming bookings
            </h3>

            <p>
                You currently don't have any upcoming appointments.
            </p>

            <a
                href="<?= BASE_URL ?>/services/index.php"
                class="hero-btn"
                style="
                    background:#2563eb;
                    color:white;
                "
            >
                Book a Service
            </a>

        </div>

    <?php else: ?>


        <?php foreach ($upcomingBookings as $booking): ?>

            <?php

            $status =
                strtolower(
                    trim(
                        $booking['status'] ?? 'pending'
                    )
                );

            $bookingDate =
                !empty($booking['booking_date'])
                    ? date(
                        'd M Y',
                        strtotime(
                            $booking['booking_date']
                        )
                    )
                    : '-';

            $bookingTime = '';

            if (!empty($booking['booking_time'])) {

                $bookingTime =
                    date(
                        'h:i A',
                        strtotime(
                            $booking['booking_time']
                        )
                    );
            }

            ?>

            <div class="booking-item">

                <div class="booking-main">

                    <div class="booking-icon">
                        📌
                    </div>

                    <div>

                        <div class="booking-title">

                            <?= e(
                                $booking['service_name']
                                ?? 'Service'
                            ) ?>

                        </div>

                        <div class="booking-meta">

                            <?= e(
                                $booking['booking_reference']
                                ?? ''
                            ) ?>

                            ·

                            <?= e($bookingDate) ?>

                            <?php if ($bookingTime !== ''): ?>

                                ·

                                <?= e($bookingTime) ?>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <div class="booking-right">

                    <div class="amount">

                        <?= e($currency) ?>

                        <?= number_format(
                            (float)(
                                $booking['total_amount']
                                ?? 0
                            ),
                            2
                        ) ?>

                    </div>


                    <span
                        class="status status-<?= e($status) ?>"
                    >
                        <?= e(
                            ucfirst($status)
                        ) ?>
                    </span>

                </div>

            </div>

        <?php endforeach; ?>


    <?php endif; ?>

    </div>

</div>


<!-- RIGHT SIDE -->

<div>


<div class="panel">

    <div class="panel-header">

        <h2>
            Quick Actions
        </h2>

    </div>


    <div class="panel-body">

        <div class="action-grid">

            <a
                href="<?= BASE_URL ?>/services/index.php"
                class="action"
            >
                <strong>
                    🔎 Find Services
                </strong>

                <span>
                    Browse available services
                </span>
            </a>


            <a
                href="<?= BASE_URL ?>/customer/my-bookings.php"
                class="action"
            >
                <strong>
                    📋 My Bookings
                </strong>

                <span>
                    Manage your bookings
                </span>
            </a>


            <a
                href="<?= BASE_URL ?>/customer/profile.php"
                class="action"
            >
                <strong>
                    👤 My Profile
                </strong>

                <span>
                    Manage your account
                </span>
            </a>


            <a
                href="<?= BASE_URL ?>/services/index.php"
                class="action"
            >
                <strong>
                    ⭐ Explore
                </strong>

                <span>
                    Explore services
                </span>
            </a>

        </div>

    </div>

</div>


<!-- PROFILE -->

<div class="profile-box">

    <div class="profile-title">
        Account Information
    </div>


    <div class="profile-row">

        <span class="profile-label">
            Name
        </span>

        <span class="profile-value">
            <?= e($name) ?>
        </span>

    </div>


    <div class="profile-row">

        <span class="profile-label">
            Email
        </span>

        <span class="profile-value">
            <?= e($email ?: '-') ?>
        </span>

    </div>


    <div class="profile-row">

        <span class="profile-label">
            Phone
        </span>

        <span class="profile-value">
            <?= e($phone ?: '-') ?>
        </span>

    </div>


    <div class="profile-row">

        <span class="profile-label">
            Completed
        </span>

        <span class="profile-value">
            <?= number_format(
                $completedBookings
            ) ?>
        </span>

    </div>


    <div class="profile-row">

        <span class="profile-label">
            Cancelled
        </span>

        <span class="profile-value">
            <?= number_format(
                $cancelledBookings
            ) ?>
        </span>

    </div>


    <div style="margin-top:15px;">

        <a
            href="<?= BASE_URL ?>/customer/profile.php"
            class="hero-btn"
            style="
                display:block;
                text-align:center;
                background:#2563eb;
                color:white;
            "
        >
            Manage Profile
        </a>

    </div>

</div>


</div>


</section>


</main>

</body>

</html>