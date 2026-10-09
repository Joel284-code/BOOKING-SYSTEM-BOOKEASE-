<?php

/*
|--------------------------------------------------------------------------
| BookEase - Customer Header
|--------------------------------------------------------------------------
| Shared header/sidebar for all customer pages.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

/*
|--------------------------------------------------------------------------
| Require Customer Login
|--------------------------------------------------------------------------
*/

requireCustomer();

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

try {

    $db = new Database();

    $conn = $db->getConnection();

} catch (PDOException $e) {

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {
        die(
            'Database connection error: ' .
            htmlspecialchars($e->getMessage())
        );
    }

    die('Unable to connect to the database.');
}

/*
|--------------------------------------------------------------------------
| Current Page
|--------------------------------------------------------------------------
*/

$currentPage = basename($_SERVER['PHP_SELF']);

/*
|--------------------------------------------------------------------------
| Customer Name
|--------------------------------------------------------------------------
*/

$customerName = $_SESSION['name'] ?? 'Customer';

/*
|--------------------------------------------------------------------------
| Customer Email
|--------------------------------------------------------------------------
*/

$customerEmail = $_SESSION['email'] ?? '';

/*
|--------------------------------------------------------------------------
| Customer Initial
|--------------------------------------------------------------------------
*/

$customerInitial = strtoupper(
    substr(
        trim($customerName),
        0,
        1
    )
);

/*
|--------------------------------------------------------------------------
| Active Navigation Helper
|--------------------------------------------------------------------------
*/

function customerActive(string $page): string
{
    global $currentPage;

    return $currentPage === $page
        ? 'active'
        : '';
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
        <?= htmlspecialchars(APP_NAME) ?> - Customer
    </title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f1f5f9;

            color: #1e293b;

            min-height: 100vh;
        }

        /* =========================================================
           SIDEBAR
        ========================================================= */

        .sidebar {
            position: fixed;

            left: 0;
            top: 0;

            width: 250px;
            height: 100vh;

            background: #0f172a;

            color: white;

            overflow-y: auto;

            z-index: 1000;
        }

        .logo {
            height: 70px;

            display: flex;

            align-items: center;

            padding: 0 25px;

            font-size: 25px;

            font-weight: bold;

            border-bottom:
                1px solid #1e293b;
        }

        .logo span {
            color: #3b82f6;
        }

        .menu {
            padding: 20px 12px;
        }

        .menu-title {
            font-size: 11px;

            color: #94a3b8;

            text-transform: uppercase;

            margin:
                15px 12px 8px;
        }

        .menu a {
            display: flex;

            align-items: center;

            gap: 12px;

            padding: 12px;

            margin-bottom: 4px;

            color: #cbd5e1;

            text-decoration: none;

            border-radius: 7px;

            transition:
                background .2s ease,
                color .2s ease;
        }

        .menu a:hover,
        .menu a.active {
            background: #2563eb;

            color: white;
        }

        .icon {
            width: 25px;

            min-width: 25px;

            text-align: center;

            font-size: 17px;
        }

        /* =========================================================
           MAIN
        ========================================================= */

        .main {
            margin-left: 250px;

            min-height: 100vh;
        }

        /* =========================================================
           TOPBAR
        ========================================================= */

        .topbar {
            height: 70px;

            background: white;

            border-bottom:
                1px solid #e2e8f0;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 30px;

            position: sticky;

            top: 0;

            z-index: 900;
        }

        .topbar h3 {
            font-size: 20px;

            color: #1e293b;
        }

        .user-area {
            display: flex;

            align-items: center;

            gap: 12px;
        }

        .avatar {
            width: 40px;

            height: 40px;

            border-radius: 50%;

            background: #2563eb;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: bold;

            text-transform: uppercase;
        }

        .user-name {
            color: #334155;
        }

        .logout {
            color: #ef4444;

            text-decoration: none;

            font-weight: 600;

            padding: 8px 10px;

            border-radius: 6px;
        }

        .logout:hover {
            background: #fee2e2;
        }

        /* =========================================================
           CONTENT
        ========================================================= */

        .content {
            padding: 30px;
        }

        .page-title {
            margin-bottom: 25px;
        }

        .page-title h1 {
            font-size: 28px;

            margin-bottom: 7px;
        }

        .page-title p {
            color: #64748b;
        }

        /* =========================================================
           CARDS
        ========================================================= */

        .cards {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

            margin-bottom: 30px;
        }

        .card {
            background: white;

            padding: 24px;

            border-radius: 10px;

            border:
                1px solid #e2e8f0;

            box-shadow:
                0 3px 12px
                rgba(15, 23, 42, .04);
        }

        .card-icon {
            font-size: 30px;

            margin-bottom: 15px;
        }

        .card h3 {
            font-size: 28px;

            margin-bottom: 6px;
        }

        .card p {
            color: #64748b;
        }

        /* =========================================================
           TABLE
        ========================================================= */

        .table-container {
            background: white;

            border-radius: 10px;

            border:
                1px solid #e2e8f0;

            overflow-x: auto;
        }

        .table-header {
            padding: 20px;

            border-bottom:
                1px solid #e2e8f0;

            display: flex;

            align-items: center;

            justify-content: space-between;
        }

        table {
            width: 100%;

            border-collapse: collapse;
        }

        th,
        td {
            padding:
                14px 18px;

            text-align: left;

            border-bottom:
                1px solid #e2e8f0;
        }

        th {
            background: #f8fafc;

            font-size: 13px;

            color: #475569;
        }

        td {
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* =========================================================
           BUTTONS
        ========================================================= */

        .btn {
            display: inline-block;

            padding:
                10px 15px;

            border-radius: 7px;

            text-decoration: none;

            border: none;

            cursor: pointer;

            font-size: 14px;

            transition: .2s ease;
        }

        .btn-primary {
            background: #2563eb;

            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-success {
            background: #16a34a;

            color: white;
        }

        .btn-success:hover {
            background: #15803d;
        }

        .btn-danger {
            background: #dc2626;

            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-secondary {
            background: #64748b;

            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
        }

        .btn-outline {
            border:
                1px solid #2563eb;

            color: #2563eb;

            background: white;
        }

        .btn-outline:hover {
            background: #eff6ff;
        }

        /* =========================================================
           STATUS
        ========================================================= */

        .status {
            display: inline-block;

            padding:
                5px 10px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;
        }

        .status-pending {
            background: #fef3c7;

            color: #92400e;
        }

        .status-confirmed {
            background: #dbeafe;

            color: #1d4ed8;
        }

        .status-completed {
            background: #dcfce7;

            color: #166534;
        }

        .status-cancelled {
            background: #fee2e2;

            color: #991b1b;
        }

        .status-rejected {
            background: #fee2e2;

            color: #991b1b;
        }

        .status-no_show {
            background: #f1f5f9;

            color: #475569;
        }

        /* =========================================================
           FORMS
        ========================================================= */

        .form-card {
            background: white;

            padding: 30px;

            border-radius: 10px;

            border:
                1px solid #e2e8f0;

            max-width: 800px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;

            margin-bottom: 7px;

            font-weight: 600;
        }

        .form-control {
            width: 100%;

            padding: 13px;

            border:
                1px solid #cbd5e1;

            border-radius: 7px;

            outline: none;

            font-size: 15px;

            background: white;
        }

        .form-control:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, .1);
        }

        textarea.form-control {
            min-height: 120px;

            resize: vertical;
        }

        /* =========================================================
           ALERT
        ========================================================= */

        .alert {
            padding:
                14px 17px;

            border-radius: 8px;

            margin-bottom: 20px;
        }

        .alert-success {
            background: #dcfce7;

            color: #166534;
        }

        .alert-error {
            background: #fee2e2;

            color: #991b1b;
        }

        .alert-warning {
            background: #fef3c7;

            color: #92400e;
        }

        /* =========================================================
           EMPTY
        ========================================================= */

        .empty {
            text-align: center;

            padding:
                50px 20px;

            color: #64748b;
        }

        .empty-icon {
            font-size: 45px;

            margin-bottom: 15px;
        }

        /* =========================================================
           SERVICE GRID
        ========================================================= */

        .service-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;
        }

        .service-card {
            background: white;

            padding: 25px;

            border-radius: 10px;

            border:
                1px solid #e2e8f0;

            box-shadow:
                0 3px 12px
                rgba(15, 23, 42, .04);
        }

        .service-card h3 {
            margin-bottom: 10px;
        }

        .service-card p {
            color: #64748b;

            line-height: 1.6;

            margin-bottom: 15px;
        }

        .service-price {
            font-size: 22px;

            font-weight: bold;

            color: #2563eb;

            margin-bottom: 15px;
        }

        /* =========================================================
           DETAIL
        ========================================================= */

        .detail-card {
            background: white;

            border-radius: 10px;

            border:
                1px solid #e2e8f0;

            padding: 30px;

            max-width: 850px;
        }

        .detail-row {
            display: flex;

            justify-content: space-between;

            gap: 20px;

            padding: 15px 0;

            border-bottom:
                1px solid #e2e8f0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #64748b;

            font-weight: 600;
        }

        .detail-value {
            text-align: right;
        }

        /* =========================================================
           NOTIFICATIONS
        ========================================================= */

        .notification {
            background: white;

            padding: 20px;

            border-radius: 10px;

            border:
                1px solid #e2e8f0;

            margin-bottom: 12px;
        }

        .notification strong {
            display: block;

            margin-bottom: 7px;
        }

        .notification p {
            color: #64748b;

            line-height: 1.5;
        }

        .notification small {
            display: block;

            margin-top: 8px;

            color: #94a3b8;
        }

        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 1000px) {

            .cards {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .service-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

        }

        @media (max-width: 700px) {

            .sidebar {
                width: 70px;
            }

            .logo {
                font-size: 0;

                justify-content: center;

                padding: 0;
            }

            .logo span {
                font-size: 20px;
            }

            .menu a {
                justify-content: center;
            }

            .menu a span:not(.icon) {
                display: none;
            }

            .menu-title {
                display: none;
            }

            .main {
                margin-left: 70px;
            }

            .content {
                padding: 20px;
            }

            .topbar {
                padding:
                    0 15px;
            }

            .topbar h3 {
                font-size: 16px;
            }

            .cards,
            .service-grid {
                grid-template-columns: 1fr;
            }

            .user-name {
                display: none;
            }

            .topbar .logout {
                display: none;
            }

            .detail-row {
                flex-direction: column;
            }

            .detail-value {
                text-align: left;
            }

        }

        @media (max-width: 450px) {

            .main {
                margin-left: 60px;
            }

            .sidebar {
                width: 60px;
            }

            .content {
                padding: 15px;
            }

            .topbar {
                height: 60px;
            }

            .page-title h1 {
                font-size: 23px;
            }

        }

    </style>

</head>

<body>

<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">

    <div class="logo">
        Book<span>Ease</span>
    </div>

    <nav class="menu">

        <div class="menu-title">
            Main
        </div>

        <a
            href="<?= BASE_URL ?>/customer/dashboard.php"
            class="<?= customerActive('dashboard.php') ?>"
        >
            <span class="icon">📊</span>
            <span>Dashboard</span>
        </a>

        <a
            href="<?= BASE_URL ?>/customer/book.php"
            class="<?= customerActive('book.php') ?>"
        >
            <span class="icon">➕</span>
            <span>Book Service</span>
        </a>

        <a
            href="<?= BASE_URL ?>/customer/my-bookings.php"
            class="<?= customerActive('my-bookings.php') ?>"
        >
            <span class="icon">📅</span>
            <span>My Bookings</span>
        </a>

        <div class="menu-title">
            Account
        </div>

        <a
            href="<?= BASE_URL ?>/customer/notifications.php"
            class="<?= customerActive('notifications.php') ?>"
        >
            <span class="icon">🔔</span>
            <span>Notifications</span>
        </a>

        <a
            href="<?= BASE_URL ?>/customer/profile.php"
            class="<?= customerActive('profile.php') ?>"
        >
            <span class="icon">👤</span>
            <span>My Profile</span>
        </a>

        <a
            href="<?= BASE_URL ?>/index.php"
        >
            <span class="icon">🏠</span>
            <span>Website</span>
        </a>

        <a
            href="<?= BASE_URL ?>/logout.php"
        >
            <span class="icon">🚪</span>
            <span>Logout</span>
        </a>

    </nav>

</aside>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <!-- =====================================================
         TOPBAR
    ====================================================== -->

    <header class="topbar">

        <h3>
            Customer Portal
        </h3>

        <div class="user-area">

            <div class="avatar">

                <?= htmlspecialchars(
                    $customerInitial
                ) ?>

            </div>

            <strong class="user-name">

                <?= htmlspecialchars(
                    $customerName
                ) ?>

            </strong>

            <a
                href="<?= BASE_URL ?>/logout.php"
                class="logout"
            >
                Logout
            </a>

        </div>

    </header>