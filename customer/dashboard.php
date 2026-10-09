<?php
/*
|--------------------------------------------------------------------------
| BookEase Customer Dashboard
| File: customer/dashboard.php
|--------------------------------------------------------------------------
*/

session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
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
    header("Location: ../admin/dashboard.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

try {

    $db = new Database();
    $conn = $db->getConnection();

} catch (Exception $e) {

    die(
        "Database connection error: " .
        htmlspecialchars($e->getMessage())
    );
}

/*
|--------------------------------------------------------------------------
| User Information
|--------------------------------------------------------------------------
*/

$userId = (int) $_SESSION['user_id'];

$userName = $_SESSION['name'] ?? 'Customer';

$appName = defined('APP_NAME')
    ? APP_NAME
    : 'BookEase';

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalBookings = 0;
$pendingBookings = 0;
$confirmedBookings = 0;
$completedBookings = 0;
$cancelledBookings = 0;

/*
|--------------------------------------------------------------------------
| Booking Statistics
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(
                CASE
                    WHEN status = 'pending' THEN 1
                    ELSE 0
                END
            ), 0) AS pending,

            COALESCE(SUM(
                CASE
                    WHEN status = 'confirmed' THEN 1
                    ELSE 0
                END
            ), 0) AS confirmed,

            COALESCE(SUM(
                CASE
                    WHEN status = 'completed' THEN 1
                    ELSE 0
                END
            ), 0) AS completed,

            COALESCE(SUM(
                CASE
                    WHEN status = 'cancelled' THEN 1
                    ELSE 0
                END
            ), 0) AS cancelled

        FROM bookings

        WHERE user_id = ?
    ");

    $stmt->execute([$userId]);

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($stats) {

        $totalBookings =
            (int) ($stats['total'] ?? 0);

        $pendingBookings =
            (int) ($stats['pending'] ?? 0);

        $confirmedBookings =
            (int) ($stats['confirmed'] ?? 0);

        $completedBookings =
            (int) ($stats['completed'] ?? 0);

        $cancelledBookings =
            (int) ($stats['cancelled'] ?? 0);
    }

    $stmt->closeCursor();

} catch (Exception $e) {

    $totalBookings = 0;
    $pendingBookings = 0;
    $confirmedBookings = 0;
    $completedBookings = 0;
    $cancelledBookings = 0;
}

/*
|--------------------------------------------------------------------------
| Recent Bookings
|--------------------------------------------------------------------------
*/

$bookings = [];

try {

    $stmt = $conn->prepare("
        SELECT
            b.id,
            b.booking_reference,
            b.booking_date,
            b.booking_time,
            b.amount,
            b.status,
            b.payment_status,
            b.created_at,
            s.name AS service_name

        FROM bookings b

        LEFT JOIN services s
            ON b.service_id = s.id

        WHERE b.user_id = ?

        ORDER BY b.created_at DESC

        LIMIT 8
    ");

    $stmt->execute([$userId]);

    $bookings =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt->closeCursor();

} catch (Exception $e) {

    $bookings = [];
}

/*
|--------------------------------------------------------------------------
| Upcoming Booking
|--------------------------------------------------------------------------
*/

$upcomingBooking = null;

try {

    $stmt = $conn->prepare("
        SELECT
            b.id,
            b.booking_reference,
            b.booking_date,
            b.booking_time,
            b.amount,
            b.status,
            b.payment_status,
            s.name AS service_name

        FROM bookings b

        LEFT JOIN services s
            ON b.service_id = s.id

        WHERE b.user_id = ?

        AND b.status IN ('pending', 'confirmed')

        AND (
            b.booking_date > CURDATE()

            OR (
                b.booking_date = CURDATE()
                AND b.booking_time >= CURTIME()
            )
        )

        ORDER BY
            b.booking_date ASC,
            b.booking_time ASC

        LIMIT 1
    ");

    $stmt->execute([$userId]);

    $upcomingBooking =
        $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt->closeCursor();

} catch (Exception $e) {

    $upcomingBooking = null;
}

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$notifications = [];

$unreadNotifications = 0;

try {

    $stmt = $conn->prepare("
        SELECT
            id,
            title,
            message,
            type,
            is_read,
            created_at

        FROM notifications

        WHERE user_id = ?

        ORDER BY created_at DESC

        LIMIT 5
    ");

    $stmt->execute([$userId]);

    $notifications =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt->closeCursor();

} catch (Exception $e) {

    $notifications = [];
}

/*
|--------------------------------------------------------------------------
| Unread Notifications
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total

        FROM notifications

        WHERE user_id = ?

        AND is_read = 0
    ");

    $stmt->execute([$userId]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);

    $unreadNotifications =
        (int) ($row['total'] ?? 0);

    $stmt->closeCursor();

} catch (Exception $e) {

    $unreadNotifications = 0;
}

/*
|--------------------------------------------------------------------------
| Helper Functions
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

function formatDateValue($date)
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return '-';
    }

    return date('d M Y', $timestamp);
}

function formatTimeValue($time)
{
    if (!$time) {
        return '-';
    }

    $timestamp = strtotime($time);

    if (!$timestamp) {
        return '-';
    }

    return date('h:i A', $timestamp);
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
    Dashboard | <?= e($appName) ?>
</title>

<style>

/*
|--------------------------------------------------------------------------
| RESET
|--------------------------------------------------------------------------
*/

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    font-family:
        Inter,
        Arial,
        Helvetica,
        sans-serif;

    background: #f5f7fb;

    color: #172033;
}

/*
|--------------------------------------------------------------------------
| LAYOUT
|--------------------------------------------------------------------------
*/

.layout {

    display: flex;

    min-height: 100vh;
}

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

.sidebar {

    width: 250px;

    background:
        linear-gradient(
            180deg,
            #0f172a 0%,
            #172554 100%
        );

    color: white;

    position: fixed;

    left: 0;
    top: 0;
    bottom: 0;

    padding: 25px 16px;

    z-index: 100;
}

.brand {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 0 12px 30px;
}

.brand-icon {

    width: 40px;
    height: 40px;

    border-radius: 10px;

    background: #2563eb;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: bold;

    font-size: 18px;
}

.brand-name {

    font-size: 21px;

    font-weight: 800;
}

.brand-name span {

    color: #60a5fa;
}

/*
|--------------------------------------------------------------------------
| SIDEBAR MENU
|--------------------------------------------------------------------------
*/

.menu-title {

    font-size: 11px;

    color: #94a3b8;

    text-transform: uppercase;

    letter-spacing: 1px;

    padding: 15px 12px 8px;
}

.menu a {

    display: flex;

    align-items: center;

    gap: 12px;

    text-decoration: none;

    color: #cbd5e1;

    padding: 12px 13px;

    border-radius: 9px;

    margin-bottom: 5px;

    font-size: 14px;

    transition: .2s;
}

.menu a:hover {

    background: rgba(255,255,255,.08);

    color: white;
}

.menu a.active {

    background: #2563eb;

    color: white;

    box-shadow:
        0 5px 15px rgba(37,99,235,.25);
}

.menu-icon {

    width: 20px;

    text-align: center;

    font-size: 16px;
}

/*
|--------------------------------------------------------------------------
| SIDEBAR BOTTOM
|--------------------------------------------------------------------------
*/

.sidebar-bottom {

    position: absolute;

    left: 16px;
    right: 16px;
    bottom: 20px;

    border-top:
        1px solid rgba(255,255,255,.1);

    padding-top: 15px;
}

.logout-link {

    display: flex;

    align-items: center;

    gap: 12px;

    text-decoration: none;

    color: #fca5a5;

    padding: 12px;

    border-radius: 9px;

    font-size: 14px;
}

.logout-link:hover {

    background: rgba(239,68,68,.1);
}

/*
|--------------------------------------------------------------------------
| MAIN CONTENT
|--------------------------------------------------------------------------
*/

.main {

    margin-left: 250px;

    width: calc(100% - 250px);

    min-height: 100vh;
}

/*
|--------------------------------------------------------------------------
| TOPBAR
|--------------------------------------------------------------------------
*/

.topbar {

    height: 72px;

    background: white;

    border-bottom:
        1px solid #e5e7eb;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 32px;

    position: sticky;

    top: 0;

    z-index: 50;
}

.topbar-title {

    font-size: 14px;

    color: #64748b;
}

.profile {

    display: flex;

    align-items: center;

    gap: 12px;
}

.avatar {

    width: 40px;
    height: 40px;

    background: #dbeafe;

    color: #1d4ed8;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: bold;
}

.profile-name {

    font-size: 14px;

    font-weight: 700;
}

.profile-role {

    font-size: 11px;

    color: #64748b;
}

/*
|--------------------------------------------------------------------------
| CONTENT
|--------------------------------------------------------------------------
*/

.content {

    padding: 30px;
}

/*
|--------------------------------------------------------------------------
| WELCOME
|--------------------------------------------------------------------------
*/

.welcome-section {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 28px;

    gap: 20px;
}

.welcome-section h1 {

    font-size: 28px;

    margin-bottom: 7px;
}

.welcome-section p {

    color: #64748b;

    font-size: 14px;
}

.primary-btn {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    background: #2563eb;

    color: white;

    text-decoration: none;

    padding: 12px 18px;

    border-radius: 9px;

    font-size: 14px;

    font-weight: 700;

    box-shadow:
        0 5px 15px rgba(37,99,235,.18);
}

.primary-btn:hover {

    background: #1d4ed8;
}

/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}

.stat-card {

    background: white;

    border-radius: 14px;

    padding: 20px;

    border:
        1px solid #e8edf4;

    box-shadow:
        0 5px 20px rgba(15,23,42,.04);

    position: relative;

    overflow: hidden;
}

.stat-top {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 16px;
}

.stat-label {

    color: #64748b;

    font-size: 13px;

    font-weight: 600;
}

.stat-icon {

    width: 40px;
    height: 40px;

    border-radius: 10px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 18px;
}

.stat-number {

    font-size: 29px;

    font-weight: 800;

    margin-bottom: 5px;
}

.stat-text {

    color: #94a3b8;

    font-size: 12px;
}

.icon-blue {

    background: #dbeafe;
    color: #2563eb;
}

.icon-orange {

    background: #fef3c7;
    color: #d97706;
}

.icon-green {

    background: #dcfce7;
    color: #16a34a;
}

.icon-purple {

    background: #ede9fe;
    color: #7c3aed;
}

/*
|--------------------------------------------------------------------------
| STATUS BAR
|--------------------------------------------------------------------------
*/

.status-overview {

    background: white;

    border:
        1px solid #e8edf4;

    border-radius: 14px;

    padding: 20px;

    margin-bottom: 25px;

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;
}

.status-item {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 10px;
}

.status-dot {

    width: 11px;
    height: 11px;

    border-radius: 50%;
}

.dot-pending {
    background: #f59e0b;
}

.dot-confirmed {
    background: #22c55e;
}

.dot-completed {
    background: #6366f1;
}

.dot-cancelled {
    background: #ef4444;
}

.status-item strong {

    display: block;

    font-size: 18px;
}

.status-item span {

    color: #64748b;

    font-size: 12px;
}

/*
|--------------------------------------------------------------------------
| TWO COLUMN
|--------------------------------------------------------------------------
*/

.dashboard-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 2fr)
        minmax(300px, 1fr);

    gap: 25px;
}

/*
|--------------------------------------------------------------------------
| PANEL
|--------------------------------------------------------------------------
*/

.panel {

    background: white;

    border:
        1px solid #e8edf4;

    border-radius: 14px;

    box-shadow:
        0 5px 20px rgba(15,23,42,.04);

    overflow: hidden;
}

.panel-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 20px 22px;

    border-bottom:
        1px solid #eef2f7;
}

.panel-title {

    font-size: 17px;

    font-weight: 800;
}

.panel-link {

    color: #2563eb;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;
}

.panel-body {

    padding: 22px;
}

/*
|--------------------------------------------------------------------------
| UPCOMING BOOKING
|--------------------------------------------------------------------------
*/

.upcoming {

    background:
        linear-gradient(
            135deg,
            #eff6ff,
            #ffffff
        );

    border:
        1px solid #bfdbfe;

    border-radius: 12px;

    padding: 20px;

    margin-bottom: 20px;
}

.upcoming-label {

    color: #2563eb;

    font-size: 11px;

    text-transform: uppercase;

    font-weight: 800;

    letter-spacing: .8px;

    margin-bottom: 10px;
}

.upcoming-service {

    font-size: 20px;

    font-weight: 800;

    margin-bottom: 15px;
}

.upcoming-info {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 12px;
}

.info-box {

    background: white;

    border-radius: 9px;

    padding: 12px;

    border:
        1px solid #e2e8f0;
}

.info-label {

    color: #94a3b8;

    font-size: 10px;

    text-transform: uppercase;

    margin-bottom: 4px;
}

.info-value {

    font-size: 13px;

    font-weight: 700;
}

/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table-wrapper {

    overflow-x: auto;
}

table {

    width: 100%;

    border-collapse: collapse;
}

th,
td {

    padding: 14px 18px;

    text-align: left;

    border-bottom:
        1px solid #eef2f7;

    font-size: 13px;

    white-space: nowrap;
}

th {

    color: #64748b;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .4px;

    background: #f8fafc;
}

td {

    color: #334155;
}

.reference {

    font-weight: 800;

    color: #2563eb;
}

/*
|--------------------------------------------------------------------------
| STATUS BADGES
|--------------------------------------------------------------------------
*/

.badge {

    display: inline-flex;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 700;
}

.badge.pending {

    background: #fef3c7;

    color: #92400e;
}

.badge.confirmed {

    background: #dcfce7;

    color: #166534;
}

.badge.completed {

    background: #e0e7ff;

    color: #3730a3;
}

.badge.cancelled {

    background: #fee2e2;

    color: #991b1b;
}

/*
|--------------------------------------------------------------------------
| PAYMENT
|--------------------------------------------------------------------------
*/

.payment-paid {

    color: #16a34a;

    font-weight: 700;
}

.payment-unpaid {

    color: #dc2626;

    font-weight: 700;
}

/*
|--------------------------------------------------------------------------
| VIEW BUTTON
|--------------------------------------------------------------------------
*/

.view-btn {

    display: inline-block;

    padding: 6px 10px;

    background: #eff6ff;

    color: #2563eb;

    border-radius: 6px;

    text-decoration: none;

    font-size: 11px;

    font-weight: 700;
}

.view-btn:hover {

    background: #dbeafe;
}

/*
|--------------------------------------------------------------------------
| NOTIFICATIONS
|--------------------------------------------------------------------------
*/

.notification {

    padding: 15px 0;

    border-bottom:
        1px solid #eef2f7;
}

.notification:last-child {

    border-bottom: none;
}

.notification.unread {

    padding-left: 12px;

    border-left:
        3px solid #2563eb;
}

.notification-title {

    font-size: 13px;

    font-weight: 700;

    margin-bottom: 5px;
}

.notification-message {

    color: #64748b;

    font-size: 12px;

    line-height: 1.5;
}

.notification-date {

    color: #94a3b8;

    font-size: 10px;

    margin-top: 7px;
}

/*
|--------------------------------------------------------------------------
| QUICK LINKS
|--------------------------------------------------------------------------
*/

.quick-links {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 10px;

    margin-top: 20px;
}

.quick-link {

    text-decoration: none;

    background: #f8fafc;

    border:
        1px solid #e2e8f0;

    color: #334155;

    padding: 13px;

    border-radius: 9px;

    font-size: 12px;

    font-weight: 700;

    text-align: center;
}

.quick-link:hover {

    background: #eff6ff;

    color: #2563eb;

    border-color: #bfdbfe;
}

/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {

    padding: 35px 20px;

    text-align: center;

    color: #64748b;
}

.empty-icon {

    font-size: 35px;

    margin-bottom: 10px;
}

.empty p {

    font-size: 13px;

    margin-bottom: 15px;
}

/*
|--------------------------------------------------------------------------
| MOBILE MENU
|--------------------------------------------------------------------------
*/

.mobile-menu {

    display: none;

    border: none;

    background: transparent;

    font-size: 25px;

    cursor: pointer;

    color: #334155;
}

/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 1150px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .dashboard-grid {

        grid-template-columns: 1fr;
    }

}

@media (max-width: 850px) {

    .sidebar {

        width: 220px;
    }

    .main {

        margin-left: 220px;

        width: calc(100% - 220px);
    }

    .status-overview {

        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media (max-width: 700px) {

    .sidebar {

        transform:
            translateX(-100%);

        transition: .3s;
    }

    .sidebar.show {

        transform:
            translateX(0);
    }

    .main {

        margin-left: 0;

        width: 100%;
    }

    .mobile-menu {

        display: block;
    }

    .topbar {

        padding: 0 18px;
    }

    .content {

        padding: 20px 15px;
    }

    .welcome-section {

        align-items: flex-start;

        flex-direction: column;
    }

    .stats {

        grid-template-columns: 1fr;
    }

    .status-overview {

        grid-template-columns: 1fr;
    }

    .upcoming-info {

        grid-template-columns: 1fr;
    }

}

@media (max-width: 450px) {

    .profile-name,
    .profile-role {

        display: none;
    }

}

</style>

</head>

<body>

<div class="layout">

<!-- ================================================================
     SIDEBAR
================================================================ -->

<aside class="sidebar" id="sidebar">

    <div class="brand">

        <div class="brand-icon">
            B
        </div>

        <div class="brand-name">
            Book<span>Ease</span>
        </div>

    </div>


    <div class="menu-title">
        Main Menu
    </div>


    <nav class="menu">

        <a
            href="dashboard.php"
            class="active"
        >
            <span class="menu-icon">⌂</span>
            Dashboard
        </a>


        <a href="book.php">

            <span class="menu-icon">＋</span>

            New Booking

        </a>


        <a href="my-bookings.php">

            <span class="menu-icon">▣</span>

            My Bookings

        </a>


        <a href="../services/index.php">

            <span class="menu-icon">◆</span>

            Services

        </a>


        <a href="notifications.php">

            <span class="menu-icon">●</span>

            Notifications

            <?php if ($unreadNotifications > 0): ?>

                <span
                    style="
                        margin-left:auto;
                        background:#ef4444;
                        color:white;
                        border-radius:20px;
                        padding:2px 7px;
                        font-size:10px;
                    "
                >
                    <?= $unreadNotifications ?>
                </span>

            <?php endif; ?>

        </a>


        <a href="profile.php">

            <span class="menu-icon">♙</span>

            My Profile

        </a>

    </nav>


    <div class="sidebar-bottom">

        <a
            href="../auth/logout.php"
            class="logout-link"
        >

            <span class="menu-icon">↪</span>

            Logout

        </a>

    </div>

</aside>


<!-- ================================================================
     MAIN
================================================================ -->

<main class="main">


<!-- TOPBAR -->

<header class="topbar">

    <button
        class="mobile-menu"
        onclick="toggleSidebar()"
        type="button"
    >
        ☰
    </button>


    <div class="topbar-title">

        Customer Portal

    </div>


    <div class="profile">

        <div class="avatar">

            <?= e(
                strtoupper(
                    substr(
                        trim($userName),
                        0,
                        1
                    )
                )
            ) ?>

        </div>


        <div>

            <div class="profile-name">

                <?= e($userName) ?>

            </div>

            <div class="profile-role">

                Customer Account

            </div>

        </div>

    </div>

</header>


<!-- CONTENT -->

<section class="content">


<!-- ================================================================
     WELCOME
================================================================ -->

<div class="welcome-section">

    <div>

        <h1>
            Welcome back, <?= e($userName) ?> 👋
        </h1>

        <p>
            Here's an overview of your bookings and account activity.
        </p>

    </div>


    <a
        href="book.php"
        class="primary-btn"
    >
        + New Booking
    </a>

</div>


<!-- ================================================================
     STATISTICS
================================================================ -->

<div class="stats">


    <!-- TOTAL -->

    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-label">
                Total Bookings
            </div>

            <div class="stat-icon icon-blue">
                ▣
            </div>

        </div>

        <div class="stat-number">
            <?= $totalBookings ?>
        </div>

        <div class="stat-text">
            All your bookings
        </div>

    </div>


    <!-- PENDING -->

    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-label">
                Pending
            </div>

            <div class="stat-icon icon-orange">
                ◷
            </div>

        </div>

        <div class="stat-number">
            <?= $pendingBookings ?>
        </div>

        <div class="stat-text">
            Waiting for confirmation
        </div>

    </div>


    <!-- CONFIRMED -->

    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-label">
                Confirmed
            </div>

            <div class="stat-icon icon-green">
                ✓
            </div>

        </div>

        <div class="stat-number">
            <?= $confirmedBookings ?>
        </div>

        <div class="stat-text">
            Upcoming confirmed bookings
        </div>

    </div>


    <!-- COMPLETED -->

    <div class="stat-card">

        <div class="stat-top">

            <div class="stat-label">
                Completed
            </div>

            <div class="stat-icon icon-purple">
                ★
            </div>

        </div>

        <div class="stat-number">
            <?= $completedBookings ?>
        </div>

        <div class="stat-text">
            Successfully completed
        </div>

    </div>

</div>


<!-- ================================================================
     STATUS OVERVIEW
================================================================ -->

<div class="status-overview">


    <div class="status-item">

        <span class="status-dot dot-pending"></span>

        <div>

            <strong>
                <?= $pendingBookings ?>
            </strong>

            <span>
                Pending
            </span>

        </div>

    </div>


    <div class="status-item">

        <span class="status-dot dot-confirmed"></span>

        <div>

            <strong>
                <?= $confirmedBookings ?>
            </strong>

            <span>
                Confirmed
            </span>

        </div>

    </div>


    <div class="status-item">

        <span class="status-dot dot-completed"></span>

        <div>

            <strong>
                <?= $completedBookings ?>
            </strong>

            <span>
                Completed
            </span>

        </div>

    </div>


    <div class="status-item">

        <span class="status-dot dot-cancelled"></span>

        <div>

            <strong>
                <?= $cancelledBookings ?>
            </strong>

            <span>
                Cancelled
            </span>

        </div>

    </div>

</div>


<!-- ================================================================
     DASHBOARD GRID
================================================================ -->

<div class="dashboard-grid">


<!-- ================================================================
     LEFT COLUMN
================================================================ -->

<div>


<!-- UPCOMING BOOKING -->

<div class="panel">

    <div class="panel-header">

        <div class="panel-title">
            Upcoming Booking
        </div>

        <a
            href="my-bookings.php"
            class="panel-link"
        >
            View All
        </a>

    </div>


    <div class="panel-body">

        <?php if ($upcomingBooking): ?>

            <div class="upcoming">

                <div class="upcoming-label">

                    Next Appointment

                </div>


                <div class="upcoming-service">

                    <?= e(
                        $upcomingBooking['service_name']
                        ?? 'Service'
                    ) ?>

                </div>


                <div class="upcoming-info">


                    <div class="info-box">

                        <div class="info-label">
                            Date
                        </div>

                        <div class="info-value">

                            <?= formatDateValue(
                                $upcomingBooking['booking_date']
                            ) ?>

                        </div>

                    </div>


                    <div class="info-box">

                        <div class="info-label">
                            Time
                        </div>

                        <div class="info-value">

                            <?= formatTimeValue(
                                $upcomingBooking['booking_time']
                            ) ?>

                        </div>

                    </div>


                    <div class="info-box">

                        <div class="info-label">
                            Status
                        </div>

                        <div class="info-value">

                            <span
                                class="badge <?= e(
                                    strtolower(
                                        $upcomingBooking['status']
                                    )
                                ) ?>"
                            >

                                <?= e(
                                    ucfirst(
                                        $upcomingBooking['status']
                                    )
                                ) ?>

                            </span>

                        </div>

                    </div>

                </div>


                <div
                    style="
                        margin-top:15px;
                        font-size:11px;
                        color:#64748b;
                    "
                >

                    Reference:
                    <strong>
                        <?= e(
                            $upcomingBooking['booking_reference']
                            ?? ('BK-' . $upcomingBooking['id'])
                        ) ?>
                    </strong>

                </div>

            </div>

        <?php else: ?>

            <div class="empty">

                <div class="empty-icon">
                    📅
                </div>

                <p>
                    You don't have any upcoming bookings.
                </p>

                <a
                    href="book.php"
                    class="primary-btn"
                >
                    Make a Booking
                </a>

            </div>

        <?php endif; ?>

    </div>

</div>


<!-- RECENT BOOKINGS -->

<div
    class="panel"
    style="margin-top:25px;"
>

    <div class="panel-header">

        <div class="panel-title">
            Recent Bookings
        </div>

        <a
            href="my-bookings.php"
            class="panel-link"
        >
            View All
        </a>

    </div>


    <?php if (!empty($bookings)): ?>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Reference
                        </th>

                        <th>
                            Service
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Payment
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($bookings as $booking): ?>

                    <?php

                    $status = strtolower(
                        trim(
                            $booking['status']
                            ?? 'pending'
                        )
                    );

                    $paymentStatus = strtolower(
                        trim(
                            $booking['payment_status']
                            ?? 'unpaid'
                        )
                    );

                    ?>

                    <tr>


                        <td>

                            <span class="reference">

                                <?= e(
                                    $booking[
                                        'booking_reference'
                                    ]
                                    ??
                                    ('BK-' . $booking['id'])
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= e(
                                $booking[
                                    'service_name'
                                ]
                                ??
                                'Service'
                            ) ?>

                        </td>


                        <td>

                            <?= formatDateValue(
                                $booking['booking_date']
                            ) ?>

                        </td>


                        <td>

                            <span
                                class="badge <?= e($status) ?>"
                            >

                                <?= e(
                                    ucfirst($status)
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?php if (
                                $paymentStatus === 'paid'
                            ): ?>

                                <span class="payment-paid">
                                    Paid
                                </span>

                            <?php else: ?>

                                <span class="payment-unpaid">
                                    Unpaid
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <a
                                href="booking-details.php?id=<?= (int)$booking['id'] ?>"
                                class="view-btn"
                            >
                                View
                            </a>

                        </td>


                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php else: ?>

        <div class="empty">

            <div class="empty-icon">
                📋
            </div>

            <p>
                You have not made any bookings yet.
            </p>

            <a
                href="book.php"
                class="primary-btn"
            >
                Make Your First Booking
            </a>

        </div>

    <?php endif; ?>

</div>

</div>


<!-- ================================================================
     RIGHT COLUMN
================================================================ -->

<div>


<!-- NOTIFICATIONS -->

<div class="panel">

    <div class="panel-header">

        <div class="panel-title">

            Notifications

            <?php if ($unreadNotifications > 0): ?>

                <span
                    style="
                        display:inline-flex;
                        align-items:center;
                        justify-content:center;
                        min-width:20px;
                        height:20px;
                        padding:0 6px;
                        background:#ef4444;
                        color:white;
                        border-radius:20px;
                        font-size:10px;
                        margin-left:5px;
                    "
                >
                    <?= $unreadNotifications ?>
                </span>

            <?php endif; ?>

        </div>


        <a
            href="notifications.php"
            class="panel-link"
        >
            View All
        </a>

    </div>


    <div class="panel-body">

        <?php if (!empty($notifications)): ?>

            <?php foreach ($notifications as $notification): ?>

                <div
                    class="notification
                    <?= (
                        (int)(
                            $notification['is_read']
                            ?? 0
                        ) === 1
                    )
                        ? ''
                        : 'unread'
                    ?>"
                >

                    <div class="notification-title">

                        <?= e(
                            $notification['title']
                            ?? ''
                        ) ?>

                    </div>


                    <div class="notification-message">

                        <?= e(
                            $notification['message']
                            ?? ''
                        ) ?>

                    </div>


                    <div class="notification-date">

                        <?= !empty(
                            $notification['created_at']
                        )
                            ? date(
                                'd M Y, h:i A',
                                strtotime(
                                    $notification[
                                        'created_at'
                                    ]
                                )
                            )
                            : ''
                        ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty">

                <div class="empty-icon">
                    🔔
                </div>

                <p>
                    No notifications yet.
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>


<!-- QUICK ACTIONS -->

<div
    class="panel"
    style="margin-top:25px;"
>

    <div class="panel-header">

        <div class="panel-title">
            Quick Actions
        </div>

    </div>


    <div class="panel-body">

        <a
            href="book.php"
            class="primary-btn"
            style="
                width:100%;
                justify-content:center;
            "
        >
            + Make New Booking
        </a>


        <div class="quick-links">

            <a
                href="my-bookings.php"
                class="quick-link"
            >
                My Bookings
            </a>


            <a
                href="../services/index.php"
                class="quick-link"
            >
                Services
            </a>


            <a
                href="profile.php"
                class="quick-link"
            >
                My Profile
            </a>


            <a
                href="notifications.php"
                class="quick-link"
            >
                Notifications
            </a>

        </div>

    </div>

</div>


<!-- BOOKING SUMMARY -->

<div
    class="panel"
    style="margin-top:25px;"
>

    <div class="panel-header">

        <div class="panel-title">
            Booking Summary
        </div>

    </div>


    <div class="panel-body">

        <div
            style="
                display:flex;
                justify-content:space-between;
                padding:10px 0;
                border-bottom:1px solid #eef2f7;
                font-size:13px;
            "
        >

            <span style="color:#64748b;">
                Total
            </span>

            <strong>
                <?= $totalBookings ?>
            </strong>

        </div>


        <div
            style="
                display:flex;
                justify-content:space-between;
                padding:10px 0;
                border-bottom:1px solid #eef2f7;
                font-size:13px;
            "
        >

            <span style="color:#64748b;">
                Pending
            </span>

            <strong style="color:#d97706;">
                <?= $pendingBookings ?>
            </strong>

        </div>


        <div
            style="
                display:flex;
                justify-content:space-between;
                padding:10px 0;
                border-bottom:1px solid #eef2f7;
                font-size:13px;
            "
        >

            <span style="color:#64748b;">
                Confirmed
            </span>

            <strong style="color:#16a34a;">
                <?= $confirmedBookings ?>
            </strong>

        </div>


        <div
            style="
                display:flex;
                justify-content:space-between;
                padding:10px 0;
                font-size:13px;
            "
        >

            <span style="color:#64748b;">
                Completed
            </span>

            <strong style="color:#6366f1;">
                <?= $completedBookings ?>
            </strong>

        </div>

    </div>

</div>


</div>

</div>

</section>

</main>

</div>


<script>

function toggleSidebar()
{
    const sidebar =
        document.getElementById('sidebar');

    sidebar.classList.toggle('show');
}

</script>

</body>

</html>