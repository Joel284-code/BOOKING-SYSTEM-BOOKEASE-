<?php

/*
|--------------------------------------------------------------------------
| BookEase - Customer Notifications
| File: /customer/notifications.php
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

date_default_timezone_set('Africa/Nairobi');

/*
|--------------------------------------------------------------------------
| Require Customer Login
|--------------------------------------------------------------------------
*/

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Prevent Admin / Staff Access
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['role']) &&
    in_array($_SESSION['role'], ['admin', 'staff'], true)
) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

try {

    $db = new Database();
    $conn = $db->getConnection();

    if (!($conn instanceof PDO)) {
        throw new Exception('Invalid database connection.');
    }

} catch (Throwable $e) {

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {
        die(
            'Database connection error: ' .
            htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            )
        );
    }

    die('Unable to connect to the database.');
}

/*
|--------------------------------------------------------------------------
| Current Customer
|--------------------------------------------------------------------------
*/

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$notifications = [];
$error = '';

try {

    $stmt = $conn->prepare("
        SELECT
            b.id,
            b.booking_reference,
            b.booking_date,
            b.booking_time,
            b.status,
            b.created_at,
            s.name AS service_name

        FROM bookings b

        LEFT JOIN services s
            ON b.service_id = s.id

        WHERE b.user_id = ?

        ORDER BY
            b.created_at DESC,
            b.id DESC

        LIMIT 20
    ");

    $stmt->execute([$userId]);

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bookings as $booking) {

        $status = strtolower(
            trim(
                $booking['status'] ?? 'pending'
            )
        );

        $serviceName =
            $booking['service_name']
            ?? 'your selected service';

        switch ($status) {

            case 'confirmed':

                $title = 'Booking Confirmed';
                $message =
                    'Your booking for ' .
                    $serviceName .
                    ' has been confirmed.';

                $notificationClass = 'confirmed';
                $icon = '✓';

                break;

            case 'completed':

                $title = 'Booking Completed';
                $message =
                    'Your booking for ' .
                    $serviceName .
                    ' has been completed successfully.';

                $notificationClass = 'completed';
                $icon = '✓';

                break;

            case 'cancelled':

                $title = 'Booking Cancelled';
                $message =
                    'Your booking for ' .
                    $serviceName .
                    ' has been cancelled.';

                $notificationClass = 'cancelled';
                $icon = '×';

                break;

            case 'pending':

                $title = 'Booking Received';
                $message =
                    'Your booking for ' .
                    $serviceName .
                    ' is waiting for confirmation.';

                $notificationClass = 'pending';
                $icon = '⏳';

                break;

            default:

                $title = 'Booking Update';
                $message =
                    'There is an update regarding your booking for ' .
                    $serviceName .
                    '.';

                $notificationClass = 'pending';
                $icon = 'i';

                break;
        }

        $notifications[] = [

            'id' =>
                (int)$booking['id'],

            'reference' =>
                $booking['booking_reference']
                ?? ('BK-' . $booking['id']),

            'title' =>
                $title,

            'message' =>
                $message,

            'status' =>
                $status,

            'class' =>
                $notificationClass,

            'icon' =>
                $icon,

            'created_at' =>
                $booking['created_at'],

            'booking_date' =>
                $booking['booking_date'],

            'booking_time' =>
                $booking['booking_time'],

            'service' =>
                $serviceName
        ];
    }

} catch (Throwable $e) {

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {
        $error =
            'Database error: ' .
            $e->getMessage();
    } else {
        $error =
            'Unable to load your notifications.';
    }
}

/*
|--------------------------------------------------------------------------
| Format Date
|--------------------------------------------------------------------------
*/

function formatNotificationDate($date)
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return '-';
    }

    return date(
        'd M Y, h:i A',
        $timestamp
    );
}

/*
|--------------------------------------------------------------------------
| Format Appointment Date
|--------------------------------------------------------------------------
*/

function formatAppointmentDate($date)
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return '-';
    }

    return date(
        'D, d M Y',
        $timestamp
    );
}

/*
|--------------------------------------------------------------------------
| Format Appointment Time
|--------------------------------------------------------------------------
*/

function formatAppointmentTime($time)
{
    if (empty($time)) {
        return '-';
    }

    $timestamp = strtotime($time);

    if ($timestamp === false) {
        return $time;
    }

    return date(
        'h:i A',
        $timestamp
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
    Notifications |
    <?= htmlspecialchars(
        defined('APP_NAME')
            ? APP_NAME
            : 'BookEase',
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</title>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {

    margin: 0;

    font-family:
        Inter,
        "Segoe UI",
        Arial,
        Helvetica,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #f8fafc 0%,
            #eef4ff 100%
        );

    color: #0f172a;

    min-height: 100vh;
}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    height: 72px;

    background:
        rgba(255,255,255,.94);

    backdrop-filter:
        blur(12px);

    -webkit-backdrop-filter:
        blur(12px);

    border-bottom:
        1px solid #e2e8f0;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 5%;

    position: sticky;

    top: 0;

    z-index: 1000;

    box-shadow:
        0 4px 20px
        rgba(15,23,42,.04);
}


/* =========================================================
   LOGO
========================================================= */

.logo {

    display: flex;

    align-items: center;

    gap: 10px;

    text-decoration: none;

    color: #0f172a;

    font-size: 22px;

    font-weight: 800;

    letter-spacing: -.5px;
}

.logo-icon {

    width: 38px;

    height: 38px;

    border-radius: 11px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: white;

    font-size: 18px;

    box-shadow:
        0 6px 15px
        rgba(37,99,235,.25);
}

.logo span {
    color: #2563eb;
}


/* =========================================================
   NAVIGATION
========================================================= */

.nav-right {

    display: flex;

    align-items: center;

    gap: 10px;
}

.nav-link {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    text-decoration: none;

    color: #475569;

    padding:
        9px 12px;

    border-radius: 9px;

    font-size: 13px;

    font-weight: 600;

    transition:
        .2s ease;
}

.nav-link:hover {

    background: #eff6ff;

    color: #2563eb;
}

.nav-link.active {

    background: #eff6ff;

    color: #2563eb;
}

.logout {

    background: #fee2e2;

    color: #dc2626 !important;
}

.logout:hover {

    background: #fecaca;

    color: #b91c1c !important;
}


/* =========================================================
   USER PROFILE
========================================================= */

.user-profile {

    display: flex;

    align-items: center;

    gap: 9px;

    margin-left: 8px;

    padding-left: 15px;

    border-left:
        1px solid #e2e8f0;
}

.avatar {

    width: 36px;

    height: 36px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #dbeafe,
            #e0e7ff
        );

    color: #2563eb;

    font-size: 14px;

    font-weight: 800;
}

.user-name {

    font-size: 13px;

    font-weight: 700;

    color: #334155;
}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.container {

    width: 92%;

    max-width: 1050px;

    margin:
        38px auto 70px;
}


/* =========================================================
   TOP BAR
========================================================= */

.top-bar {

    display: flex;

    justify-content: space-between;

    align-items: flex-end;

    gap: 20px;

    margin-bottom: 28px;
}

.heading-area {

    display: flex;

    align-items: flex-start;

    gap: 15px;
}

.heading-icon {

    width: 50px;

    height: 50px;

    border-radius: 14px;

    background:
        linear-gradient(
            135deg,
            #dbeafe,
            #e0e7ff
        );

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 23px;

    color: #2563eb;

    flex-shrink: 0;
}

.page-header h1 {

    margin:
        0 0 6px;

    font-size: 30px;

    letter-spacing: -.7px;

    color: #0f172a;
}

.page-header p {

    margin: 0;

    color: #64748b;

    font-size: 14px;

    line-height: 1.6;
}


/* =========================================================
   BACK BUTTON
========================================================= */

.back-button {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        10px 15px;

    background: white;

    border:
        1px solid #e2e8f0;

    color: #475569;

    text-decoration: none;

    border-radius: 9px;

    font-size: 13px;

    font-weight: 700;

    transition:
        .2s ease;

    box-shadow:
        0 3px 12px
        rgba(15,23,42,.04);
}

.back-button:hover {

    border-color: #bfdbfe;

    background: #eff6ff;

    color: #2563eb;

    transform:
        translateY(-1px);
}


/* =========================================================
   SUMMARY
========================================================= */

.summary {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 25px;
}

.summary-card {

    background: white;

    border:
        1px solid #e2e8f0;

    border-radius: 14px;

    padding: 18px;

    display: flex;

    align-items: center;

    gap: 13px;

    box-shadow:
        0 5px 20px
        rgba(15,23,42,.04);
}

.summary-icon {

    width: 42px;

    height: 42px;

    border-radius: 11px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #eff6ff;

    color: #2563eb;

    font-size: 18px;

    font-weight: 800;
}

.summary-number {

    display: block;

    font-size: 20px;

    font-weight: 800;

    color: #0f172a;
}

.summary-label {

    display: block;

    margin-top: 2px;

    color: #64748b;

    font-size: 12px;

    font-weight: 600;
}


/* =========================================================
   ERROR
========================================================= */

.error {

    background:
        linear-gradient(
            135deg,
            #fff1f2,
            #fee2e2
        );

    border:
        1px solid #fecdd3;

    color: #9f1239;

    padding:
        15px 18px;

    border-radius: 12px;

    margin-bottom: 20px;

    font-size: 14px;
}


/* =========================================================
   NOTIFICATION LIST
========================================================= */

.notification-list {

    display: flex;

    flex-direction: column;

    gap: 14px;
}


/* =========================================================
   NOTIFICATION CARD
========================================================= */

.notification {

    position: relative;

    background: white;

    border:
        1px solid #e2e8f0;

    border-radius: 16px;

    padding: 20px;

    display: flex;

    gap: 17px;

    box-shadow:
        0 5px 22px
        rgba(15,23,42,.045);

    transition:
        transform .2s ease,
        box-shadow .2s ease,
        border-color .2s ease;
}

.notification:hover {

    transform:
        translateY(-2px);

    border-color: #cbd5e1;

    box-shadow:
        0 10px 30px
        rgba(15,23,42,.08);
}


/* =========================================================
   NOTIFICATION ICON
========================================================= */

.notification-icon {

    width: 48px;

    height: 48px;

    border-radius: 14px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 20px;

    font-weight: 800;

    flex-shrink: 0;
}

.notification.confirmed
.notification-icon {

    background: #dcfce7;

    color: #15803d;
}

.notification.completed
.notification-icon {

    background: #ede9fe;

    color: #7c3aed;
}

.notification.cancelled
.notification-icon {

    background: #fee2e2;

    color: #dc2626;
}

.notification.pending
.notification-icon {

    background: #fef3c7;

    color: #d97706;
}


/* =========================================================
   NOTIFICATION CONTENT
========================================================= */

.notification-content {

    flex: 1;

    min-width: 0;
}

.notification-top {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 15px;

    margin-bottom: 7px;
}

.notification-title {

    margin: 0;

    font-size: 16px;

    font-weight: 800;

    color: #0f172a;
}

.notification-message {

    margin:
        0 0 13px;

    color: #64748b;

    font-size: 13px;

    line-height: 1.6;
}


/* =========================================================
   STATUS BADGE
========================================================= */

.status {

    display: inline-flex;

    align-items: center;

    padding:
        5px 10px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 800;

    text-transform: uppercase;

    letter-spacing: .4px;

    white-space: nowrap;
}

.status.confirmed {

    background: #dcfce7;

    color: #166534;
}

.status.completed {

    background: #ede9fe;

    color: #6d28d9;
}

.status.cancelled {

    background: #fee2e2;

    color: #991b1b;
}

.status.pending {

    background: #fef3c7;

    color: #92400e;
}


/* =========================================================
   META INFORMATION
========================================================= */

.notification-meta {

    display: flex;

    flex-wrap: wrap;

    gap: 8px 16px;

    margin-bottom: 15px;

    color: #94a3b8;

    font-size: 11px;

    font-weight: 600;
}

.meta-item {

    display: inline-flex;

    align-items: center;

    gap: 5px;
}


/* =========================================================
   VIEW BUTTON
========================================================= */

.view-button {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        9px 14px;

    border-radius: 8px;

    background: #eff6ff;

    color: #2563eb;

    text-decoration: none;

    font-size: 12px;

    font-weight: 800;

    transition:
        .2s ease;
}

.view-button:hover {

    background: #2563eb;

    color: white;

    transform:
        translateY(-1px);
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty {

    background: white;

    border:
        1px solid #e2e8f0;

    border-radius: 18px;

    padding:
        75px 25px;

    text-align: center;

    box-shadow:
        0 7px 25px
        rgba(15,23,42,.05);
}

.empty-icon {

    width: 70px;

    height: 70px;

    margin:
        0 auto 18px;

    border-radius: 20px;

    background:
        linear-gradient(
            135deg,
            #dbeafe,
            #e0e7ff
        );

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 30px;
}

.empty h2 {

    margin:
        0 0 8px;

    font-size: 21px;

    color: #1e293b;
}

.empty p {

    margin:
        0 auto 22px;

    max-width: 430px;

    color: #64748b;

    font-size: 14px;

    line-height: 1.6;
}

.book-button {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding:
        11px 18px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: white;

    text-decoration: none;

    border-radius: 9px;

    font-size: 13px;

    font-weight: 800;

    box-shadow:
        0 7px 18px
        rgba(37,99,235,.2);

    transition:
        .2s ease;
}

.book-button:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 10px 22px
        rgba(37,99,235,.28);
}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    text-align: center;

    color: #94a3b8;

    font-size: 11px;

    margin-top: 35px;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 800px) {

    .navbar {

        height: auto;

        min-height: 68px;

        padding:
            13px 4%;

        gap: 12px;
    }

    .nav-right {

        gap: 5px;
    }

    .nav-link {

        display: none;
    }

    .user-name {

        display: none;
    }

    .user-profile {

        border-left: none;

        margin-left: 0;

        padding-left: 0;
    }

    .top-bar {

        align-items: flex-start;

        flex-direction: column;
    }

    .back-button {

        width: 100%;

        justify-content: center;
    }

    .summary {

        grid-template-columns: 1fr;

    }

}


@media (max-width: 600px) {

    .container {

        width: 94%;

        margin:
            25px auto 50px;
    }

    .page-header h1 {

        font-size: 25px;
    }

    .heading-icon {

        width: 44px;

        height: 44px;

        font-size: 19px;
    }

    .notification {

        padding: 17px;

        gap: 12px;
    }

    .notification-icon {

        width: 40px;

        height: 40px;

        border-radius: 11px;

        font-size: 17px;
    }

    .notification-top {

        flex-direction: column;

        gap: 8px;
    }

    .notification-meta {

        flex-direction: column;

        gap: 6px;
    }

}


@media (max-width: 420px) {

    .logo {

        font-size: 19px;
    }

    .logo-icon {

        width: 34px;

        height: 34px;
    }

    .logout {

        padding:
            8px 11px;

        font-size: 11px;
    }

}

</style>

</head>

<body>

<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar">

```
<a
    href="<?= BASE_URL ?>/customer/dashboard.php"
    class="logo"
>

    <span class="logo-icon">
        B
    </span>

    Book<span>Ease</span>

</a>


<div class="nav-right">


    <a
        href="<?= BASE_URL ?>/customer/dashboard.php"
        class="nav-link"
    >
        Dashboard
    </a>


    <a
        href="<?= BASE_URL ?>/customer/book.php"
        class="nav-link"
    >
        Book Service
    </a>


    <a
        href="<?= BASE_URL ?>/customer/my-bookings.php"
        class="nav-link"
    >
        My Bookings
    </a>


    <a
        href="<?= BASE_URL ?>/customer/notifications.php"
        class="nav-link active"
    >
        Notifications
    </a>


    <div class="user-profile">

        <div class="avatar">

            <?= strtoupper(
                substr(
                    $_SESSION['name']
                    ?? 'C',
                    0,
                    1
                )
            ) ?>

        </div>


        <span class="user-name">

            <?= htmlspecialchars(
                $_SESSION['name']
                ?? 'Customer',
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </span>

    </div>


    <a
        href="<?= BASE_URL ?>/logout.php"
        class="nav-link logout"
    >
        Logout
    </a>


</div>
```

</nav>

<!-- =====================================================
     MAIN
===================================================== -->

<main class="container">

```
<!-- =================================================
     HEADER
================================================== -->

<div class="top-bar">


    <div class="heading-area">


        <div class="heading-icon">
            🔔
        </div>


        <div class="page-header">

            <h1>
                Notifications
            </h1>

            <p>
                Stay updated with the latest activity
                on your bookings.
            </p>

        </div>


    </div>


    <a
        href="<?= BASE_URL ?>/customer/dashboard.php"
        class="back-button"
    >

        ← Back to Dashboard

    </a>


</div>


<!-- =================================================
     SUMMARY
================================================== -->

<?php

$totalNotifications =
    count($notifications);

$pendingCount = 0;

$confirmedCount = 0;

foreach ($notifications as $item) {

    if (
        $item['status'] === 'pending'
    ) {
        $pendingCount++;
    }

    if (
        $item['status'] === 'confirmed'
    ) {
        $confirmedCount++;
    }
}

?>


<?php if (!$error): ?>

    <div class="summary">


        <div class="summary-card">

            <div class="summary-icon">
                🔔
            </div>

            <div>

                <span class="summary-number">
                    <?= $totalNotifications ?>
                </span>

                <span class="summary-label">
                    Total Updates
                </span>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">
                ⏳
            </div>

            <div>

                <span class="summary-number">
                    <?= $pendingCount ?>
                </span>

                <span class="summary-label">
                    Pending Bookings
                </span>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon">
                ✓
            </div>

            <div>

                <span class="summary-number">
                    <?= $confirmedCount ?>
                </span>

                <span class="summary-label">
                    Confirmed Bookings
                </span>

            </div>

        </div>


    </div>

<?php endif; ?>


<!-- =================================================
     ERROR
================================================== -->

<?php if (!empty($error)): ?>

    <div class="error">

        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

<?php endif; ?>


<!-- =================================================
     NOTIFICATIONS
================================================== -->

<?php if (!empty($notifications)): ?>


    <div class="notification-list">


        <?php foreach (
            $notifications
            as $notification
        ): ?>


            <article
                class="notification
                <?= htmlspecialchars(
                    $notification['class'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >


                <!-- ICON -->

                <div class="notification-icon">

                    <?= htmlspecialchars(
                        $notification['icon'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


                <!-- CONTENT -->

                <div class="notification-content">


                    <div class="notification-top">


                        <h2 class="notification-title">

                            <?= htmlspecialchars(
                                $notification['title'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </h2>


                        <span
                            class="status
                            <?= htmlspecialchars(
                                $notification['class'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                            <?= htmlspecialchars(
                                ucfirst(
                                    $notification['status']
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>


                    </div>


                    <p class="notification-message">

                        <?= htmlspecialchars(
                            $notification['message'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </p>


                    <!-- META -->

                    <div class="notification-meta">


                        <span class="meta-item">

                            📋

                            <?= htmlspecialchars(
                                $notification['reference'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>


                        <span class="meta-item">

                            🛎️

                            <?= htmlspecialchars(
                                $notification['service'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>


                        <span class="meta-item">

                            📅

                            <?= htmlspecialchars(
                                formatAppointmentDate(
                                    $notification['booking_date']
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>


                        <?php if (
                            !empty(
                                $notification['booking_time']
                            )
                        ): ?>

                            <span class="meta-item">

                                🕐

                                <?= htmlspecialchars(
                                    formatAppointmentTime(
                                        $notification['booking_time']
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        <?php endif; ?>


                        <span class="meta-item">

                            🕒

                            <?= htmlspecialchars(
                                formatNotificationDate(
                                    $notification['created_at']
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>


                    </div>


                    <!-- VIEW BUTTON -->

                    <a
                        href="<?= BASE_URL ?>/customer/booking-details.php?id=<?= (int)$notification['id'] ?>"
                        class="view-button"
                    >

                        View Booking

                        <span>
                            →
                        </span>

                    </a>


                </div>


            </article>


        <?php endforeach; ?>


    </div>


<?php else: ?>


    <!-- =================================================
         EMPTY STATE
    ================================================== -->

    <div class="empty">


        <div class="empty-icon">
            🔔
        </div>


        <h2>
            No Notifications Yet
        </h2>


        <p>
            You don't have any booking updates yet.
            Once you make a booking, its status and
            updates will appear here.
        </p>


        <a
            href="<?= BASE_URL ?>/customer/book.php"
            class="book-button"
        >

            + Make a Booking

        </a>


    </div>


<?php endif; ?>


<div class="footer">

    <?= htmlspecialchars(
        defined('APP_NAME')
            ? APP_NAME
            : 'BookEase',
        ENT_QUOTES,
        'UTF-8'
    ) ?>

    &copy;

    <?= date('Y') ?>

    · Booking Management System

</div>
```

</main>

</body>

</html>