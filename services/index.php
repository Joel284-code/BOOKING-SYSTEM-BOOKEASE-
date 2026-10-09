<?php
/**
 * BookEase - Modern Services Page
 * File: /booking-system/services/index.php
 */

session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';


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
$role   = $_SESSION['role'] ?? '';


/*
|--------------------------------------------------------------------------
| Allowed Roles
|--------------------------------------------------------------------------
*/

if (!in_array($role, ['admin', 'staff', 'customer'], true)) {
    header("Location: ../auth/login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

$services = [];

$databaseError = false;

$databaseErrorMessage = '';

try {

    $db = new Database();

    $conn = $db->getConnection();

    if (!($conn instanceof PDO)) {
        throw new Exception(
            'Database connection was not created correctly.'
        );
    }

} catch (Throwable $e) {

    $databaseError = true;

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {
        $databaseErrorMessage = $e->getMessage();
    } else {
        $databaseErrorMessage =
            'Unable to connect to the database.';
    }
}


/*
|--------------------------------------------------------------------------
| Success Message
|--------------------------------------------------------------------------
*/

$success = $_GET['success'] ?? '';

$allowedSuccessMessages = [
    'created',
    'updated',
    'deleted'
];

if (!in_array($success, $allowedSuccessMessages, true)) {
    $success = '';
}


/*
|--------------------------------------------------------------------------
| Load Services
|--------------------------------------------------------------------------
*/

if (!$databaseError) {

    try {

        $sql = "
            SELECT
                s.id,
                s.name,
                s.description,
                s.price,
                s.duration,
                s.status,
                s.created_at,
                s.updated_at,

                COUNT(b.id) AS total_bookings

            FROM services s

            LEFT JOIN bookings b
                ON s.id = b.service_id

            GROUP BY
                s.id,
                s.name,
                s.description,
                s.price,
                s.duration,
                s.status,
                s.created_at,
                s.updated_at

            ORDER BY
                s.created_at DESC
        ";

        $stmt = $conn->prepare($sql);

        $stmt->execute();

        $services =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {

        $databaseError = true;

        if (
            defined('DEVELOPMENT_MODE') &&
            DEVELOPMENT_MODE === true
        ) {
            $databaseErrorMessage =
                $e->getMessage();
        } else {
            $databaseErrorMessage =
                'Unable to load services.';
        }

        $services = [];
    }
}


/*
|--------------------------------------------------------------------------
| Dashboard Link
|--------------------------------------------------------------------------
*/

if (
    $role === 'admin' ||
    $role === 'staff'
) {

    $dashboardLink =
        "../admin/dashboard.php";

} else {

    $dashboardLink =
        "../customer/dashboard.php";
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
| Statistics
|--------------------------------------------------------------------------
*/

$totalServices = count($services);

$activeServices = 0;

$inactiveServices = 0;

$totalBookings = 0;

$totalRevenue = 0;


foreach ($services as $service) {

    if (($service['status'] ?? '') === 'active') {
        $activeServices++;
    } else {
        $inactiveServices++;
    }

    $totalBookings +=
        (int)($service['total_bookings'] ?? 0);

    $totalRevenue +=
        (float)($service['price'] ?? 0)
        *
        (int)($service['total_bookings'] ?? 0);
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
    Services | BookEase
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
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Arial,
        sans-serif;

    background:
        #f6f8fc;

    color: #0f172a;
}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    height: 72px;

    background:
        rgba(255,255,255,.96);

    backdrop-filter:
        blur(12px);

    border-bottom:
        1px solid #e5e7eb;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 34px;

    position: sticky;

    top: 0;

    z-index: 1000;
}


.logo {

    display: flex;

    align-items: center;

    gap: 9px;

    font-size: 23px;

    font-weight: 800;

    color: #2563eb;

    text-decoration: none;
}


.logo-icon {

    width: 38px;

    height: 38px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 11px;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: #fff;

    font-size: 18px;

    box-shadow:
        0 5px 14px
        rgba(37,99,235,.25);
}


.logo span {
    color: #0f172a;
}


.navbar-right {

    display: flex;

    align-items: center;

    gap: 7px;
}


.nav-link {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding:
        9px 13px;

    border-radius: 9px;

    color: #475569;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;

    transition:
        .2s ease;
}


.nav-link:hover {

    background: #eff6ff;

    color: #2563eb;
}


.logout {

    background: #fff1f2;

    color: #dc2626;
}


.logout:hover {

    background: #fee2e2;

    color: #b91c1c;
}


/* =========================================================
   MAIN
========================================================= */

.container {

    width: 92%;

    max-width: 1320px;

    margin:
        34px auto 70px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 28px;
}


.page-title {

    display: flex;

    align-items: flex-start;

    gap: 15px;
}


.page-icon {

    width: 52px;

    height: 52px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    background:
        linear-gradient(
            135deg,
            #dbeafe,
            #ede9fe
        );

    color: #2563eb;

    font-size: 23px;
}


.page-header h1 {

    margin: 0 0 5px;

    font-size: 30px;

    letter-spacing: -.5px;
}


.page-header p {

    margin: 0;

    color: #64748b;

    font-size: 14px;
}


/* =========================================================
   BUTTON
========================================================= */

.btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    padding:
        11px 17px;

    border-radius: 10px;

    border: none;

    text-decoration: none;

    cursor: pointer;

    font-size: 13px;

    font-weight: 700;

    transition:
        .2s ease;
}


.btn:hover {

    transform:
        translateY(-1px);
}


.btn-primary {

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: white;

    box-shadow:
        0 7px 18px
        rgba(37,99,235,.20);
}


.btn-primary:hover {

    box-shadow:
        0 9px 22px
        rgba(37,99,235,.28);
}


/* =========================================================
   STAT CARDS
========================================================= */

.stats-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 16px;

    margin-bottom: 25px;
}


.stat-card {

    background: #fff;

    border:
        1px solid #e8edf4;

    border-radius: 15px;

    padding: 20px;

    display: flex;

    align-items: center;

    gap: 14px;

    box-shadow:
        0 5px 20px
        rgba(15,23,42,.035);
}


.stat-icon {

    width: 45px;

    height: 45px;

    border-radius: 12px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 19px;
}


.stat-blue {

    background: #eff6ff;

    color: #2563eb;
}


.stat-green {

    background: #ecfdf5;

    color: #059669;
}


.stat-red {

    background: #fff1f2;

    color: #e11d48;
}


.stat-purple {

    background: #f5f3ff;

    color: #7c3aed;
}


.stat-content span {

    display: block;

    color: #64748b;

    font-size: 12px;

    margin-bottom: 4px;
}


.stat-content strong {

    font-size: 22px;

    color: #0f172a;
}


/* =========================================================
   ALERTS
========================================================= */

.alert {

    display: flex;

    align-items: flex-start;

    gap: 12px;

    padding:
        14px 17px;

    border-radius: 11px;

    margin-bottom: 20px;

    font-size: 14px;

    line-height: 1.5;
}


.alert-success {

    background: #ecfdf5;

    color: #166534;

    border:
        1px solid #bbf7d0;
}


.alert-error {

    background: #fff1f2;

    color: #991b1b;

    border:
        1px solid #fecdd3;
}


.alert-icon {

    font-size: 17px;

    flex-shrink: 0;
}


.error-details {

    margin-top: 10px;

    padding: 12px;

    background: white;

    border:
        1px solid #fecaca;

    border-radius: 8px;

    font-family:
        Consolas,
        monospace;

    font-size: 12px;

    overflow-x: auto;
}


/* =========================================================
   SERVICES CARD
========================================================= */

.services-card {

    background: #fff;

    border:
        1px solid #e8edf4;

    border-radius: 17px;

    overflow: hidden;

    box-shadow:
        0 8px 30px
        rgba(15,23,42,.045);
}


/* =========================================================
   CARD HEADER
========================================================= */

.card-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    padding:
        20px 22px;

    border-bottom:
        1px solid #edf1f5;
}


.card-header h2 {

    margin: 0;

    font-size: 16px;
}


.card-header p {

    margin: 4px 0 0;

    color: #94a3b8;

    font-size: 12px;
}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    width: 100%;

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 950px;
}


thead th {

    padding:
        13px 18px;

    background:
        #f8fafc;

    border-bottom:
        1px solid #e5e7eb;

    color: #64748b;

    text-align: left;

    font-size: 11px;

    font-weight: 800;

    text-transform: uppercase;

    letter-spacing: .5px;
}


tbody td {

    padding:
        17px 18px;

    border-bottom:
        1px solid #f1f5f9;

    vertical-align: middle;

    font-size: 13px;
}


tbody tr {

    transition:
        background .18s ease;
}


tbody tr:hover {

    background:
        #fafcff;
}


tbody tr:last-child td {

    border-bottom: none;
}


/* =========================================================
   NUMBER
========================================================= */

.number {

    color: #94a3b8;

    font-size: 12px;

    font-weight: 700;
}


/* =========================================================
   SERVICE NAME
========================================================= */

.service-cell {

    display: flex;

    align-items: center;

    gap: 12px;

    min-width: 190px;
}


.service-icon {

    width: 40px;

    height: 40px;

    flex-shrink: 0;

    border-radius: 11px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #eff6ff,
            #eef2ff
        );

    color: #2563eb;

    font-size: 16px;
}


.service-name {

    font-weight: 750;

    color: #0f172a;

    margin-bottom: 3px;
}


.service-id {

    color: #94a3b8;

    font-size: 11px;
}


/* =========================================================
   DESCRIPTION
========================================================= */

.description {

    color: #64748b;

    max-width: 260px;

    line-height: 1.5;

    font-size: 12px;
}


/* =========================================================
   PRICE
========================================================= */

.price {

    font-weight: 800;

    color: #0f172a;

    white-space: nowrap;
}


.price small {

    font-size: 10px;

    color: #94a3b8;

    font-weight: 600;
}


/* =========================================================
   DURATION
========================================================= */

.duration {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    color: #475569;

    white-space: nowrap;
}


/* =========================================================
   BOOKING COUNT
========================================================= */

.booking-count {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-width: 34px;

    padding:
        5px 9px;

    background: #f1f5f9;

    border-radius: 8px;

    color: #334155;

    font-weight: 700;

    font-size: 12px;
}


/* =========================================================
   BADGES
========================================================= */

.badge {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding:
        6px 10px;

    border-radius: 999px;

    font-size: 11px;

    font-weight: 750;
}


.badge-dot {

    width: 6px;

    height: 6px;

    border-radius: 50%;

    display: inline-block;
}


.badge-active {

    background: #ecfdf5;

    color: #047857;
}


.badge-active .badge-dot {

    background: #10b981;
}


.badge-inactive {

    background: #fff1f2;

    color: #be123c;
}


.badge-inactive .badge-dot {

    background: #f43f5e;
}


/* =========================================================
   ACTIONS
========================================================= */

.actions {

    display: flex;

    align-items: center;

    gap: 6px;

    flex-wrap: wrap;
}


.action-btn {

    width: 34px;

    height: 34px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border-radius: 8px;

    text-decoration: none;

    font-size: 14px;

    transition:
        .2s ease;
}


.action-btn:hover {

    transform:
        translateY(-1px);
}


.action-view {

    background: #eff6ff;

    color: #2563eb;
}


.action-view:hover {

    background: #dbeafe;
}


.action-edit {

    background: #fffbeb;

    color: #d97706;
}


.action-edit:hover {

    background: #fef3c7;
}


.action-delete {

    background: #fff1f2;

    color: #e11d48;
}


.action-delete:hover {

    background: #ffe4e6;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty {

    padding:
        75px 25px;

    text-align: center;
}


.empty-icon {

    width: 65px;

    height: 65px;

    margin:
        0 auto 17px;

    border-radius: 18px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #eff6ff;

    color: #2563eb;

    font-size: 27px;
}


.empty h3 {

    margin:
        0 0 7px;

    font-size: 18px;
}


.empty p {

    margin:
        0 auto 20px;

    color: #64748b;

    font-size: 13px;

    max-width: 400px;

    line-height: 1.6;
}


/* =========================================================
   FOOTER
========================================================= */

.page-footer {

    margin-top: 22px;

    text-align: center;

    color: #94a3b8;

    font-size: 11px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {

    .stats-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }
}


@media (max-width: 750px) {

    .navbar {

        height: auto;

        padding:
            15px 18px;

        flex-direction: column;

        align-items: stretch;

        gap: 13px;
    }


    .navbar-right {

        width: 100%;

        overflow-x: auto;

        padding-bottom: 2px;
    }


    .nav-link {

        white-space: nowrap;
    }


    .container {

        width: 94%;

        margin-top: 25px;
    }


    .page-header {

        align-items: flex-start;

        flex-direction: column;
    }


    .page-header .btn {

        width: 100%;
    }


    .stats-grid {

        grid-template-columns:
            1fr 1fr;
    }
}


@media (max-width: 500px) {

    .stats-grid {

        grid-template-columns: 1fr;
    }


    .page-title {

        gap: 10px;
    }


    .page-icon {

        width: 45px;

        height: 45px;
    }


    .page-header h1 {

        font-size: 25px;
    }


    .card-header {

        padding:
            17px;
    }


    .container {

        width: 92%;
    }
}

</style>

</head>


<body>


<!-- =====================================================
     NAVIGATION
===================================================== -->

<nav class="navbar">


    <a
        href="<?= e($dashboardLink) ?>"
        class="logo"
    >

        <span class="logo-icon">
            ✦
        </span>

        Book<span>Ease</span>

    </a>


    <div class="navbar-right">


        <a
            href="<?= e($dashboardLink) ?>"
            class="nav-link"
        >
            🏠 Dashboard
        </a>


        <?php if ($role === 'customer'): ?>

            <a
                href="../customer/book.php"
                class="nav-link"
            >
                📅 Book Service
            </a>

        <?php endif; ?>


        <?php if ($role === 'admin'): ?>

            <a
                href="create.php"
                class="nav-link"
            >
                ＋ Add Service
            </a>

        <?php endif; ?>


        <a
            href="../logout.php"
            class="nav-link logout"
        >
            ↪ Logout
        </a>


    </div>

</nav>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="container">


    <!-- =================================================
         PAGE HEADER
    ================================================== -->

    <div class="page-header">


        <div class="page-title">


            <div class="page-icon">
                ⚙
            </div>


            <div>

                <h1>
                    Services
                </h1>

                <p>
                    Manage and view services available through BookEase.
                </p>

            </div>


        </div>


        <?php if ($role === 'admin'): ?>

            <a
                href="create.php"
                class="btn btn-primary"
            >
                ＋ Add New Service
            </a>

        <?php endif; ?>


    </div>


    <!-- =================================================
         SUCCESS MESSAGES
    ================================================== -->

    <?php if ($success === 'created'): ?>

        <div class="alert alert-success">

            <span class="alert-icon">
                ✓
            </span>

            <div>
                <strong>
                    Service created successfully.
                </strong>

                <br>

                The new service is now available in your services list.
            </div>

        </div>

    <?php elseif ($success === 'updated'): ?>

        <div class="alert alert-success">

            <span class="alert-icon">
                ✓
            </span>

            <div>
                <strong>
                    Service updated successfully.
                </strong>

                <br>

                The service information has been updated.
            </div>

        </div>

    <?php elseif ($success === 'deleted'): ?>

        <div class="alert alert-success">

            <span class="alert-icon">
                ✓
            </span>

            <div>
                <strong>
                    Service deactivated successfully.
                </strong>

                <br>

                Customers can no longer book this service.
            </div>

        </div>

    <?php endif; ?>


    <!-- =================================================
         DATABASE ERROR
    ================================================== -->

    <?php if ($databaseError): ?>

        <div class="alert alert-error">

            <span class="alert-icon">
                ⚠
            </span>

            <div>

                <strong>
                    Unable to load services.
                </strong>

                <br>

                Please check your database configuration
                and services table.

                <?php if (
                    defined('DEVELOPMENT_MODE') &&
                    DEVELOPMENT_MODE === true &&
                    !empty($databaseErrorMessage)
                ): ?>

                    <div class="error-details">

                        <?= e(
                            $databaseErrorMessage
                        ) ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>


    <?php if (!$databaseError): ?>


        <!-- =================================================
             STATISTICS
        ================================================== -->

        <div class="stats-grid">


            <div class="stat-card">

                <div class="stat-icon stat-blue">
                    ◈
                </div>

                <div class="stat-content">

                    <span>
                        Total Services
                    </span>

                    <strong>
                        <?= number_format($totalServices) ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon stat-green">
                    ✓
                </div>

                <div class="stat-content">

                    <span>
                        Active Services
                    </span>

                    <strong>
                        <?= number_format($activeServices) ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon stat-red">
                    ○
                </div>

                <div class="stat-content">

                    <span>
                        Inactive Services
                    </span>

                    <strong>
                        <?= number_format($inactiveServices) ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon stat-purple">
                    ◷
                </div>

                <div class="stat-content">

                    <span>
                        Total Bookings
                    </span>

                    <strong>
                        <?= number_format($totalBookings) ?>
                    </strong>

                </div>

            </div>


        </div>


        <!-- =================================================
             SERVICES CARD
        ================================================== -->

        <section class="services-card">


            <div class="card-header">

                <div>

                    <h2>
                        Available Services
                    </h2>

                    <p>
                        <?= number_format($totalServices) ?>
                        service(s) registered in the system
                    </p>

                </div>


                <?php if ($role === 'admin'): ?>

                    <a
                        href="create.php"
                        class="btn btn-primary"
                    >
                        ＋ Add
                    </a>

                <?php endif; ?>

            </div>


            <?php if (!empty($services)): ?>


                <div class="table-wrapper">

                    <table>


                        <thead>

                            <tr>

                                <th>
                                    #
                                </th>

                                <th>
                                    Service
                                </th>

                                <th>
                                    Description
                                </th>

                                <th>
                                    Price
                                </th>

                                <th>
                                    Duration
                                </th>

                                <th>
                                    Bookings
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $services as $number => $service
                        ): ?>


                            <tr>


                                <!-- NUMBER -->

                                <td>

                                    <span class="number">
                                        <?= $number + 1 ?>
                                    </span>

                                </td>


                                <!-- SERVICE -->

                                <td>

                                    <div class="service-cell">


                                        <div class="service-icon">
                                            ✦
                                        </div>


                                        <div>

                                            <div class="service-name">

                                                <?= e(
                                                    $service['name']
                                                    ?? 'Unnamed Service'
                                                ) ?>

                                            </div>


                                            <div class="service-id">

                                                Service #
                                                <?= (int)$service['id'] ?>

                                            </div>

                                        </div>


                                    </div>

                                </td>


                                <!-- DESCRIPTION -->

                                <td>

                                    <div class="description">

                                        <?php

                                        $description =
                                            $service['description']
                                            ?? '';

                                        if (
                                            strlen($description) > 90
                                        ) {

                                            $description =
                                                substr(
                                                    $description,
                                                    0,
                                                    90
                                                ) . '...';
                                        }

                                        ?>

                                        <?= e(
                                            $description !== ''
                                                ? $description
                                                : 'No description provided.'
                                        ) ?>

                                    </div>

                                </td>


                                <!-- PRICE -->

                                <td>

                                    <div class="price">

                                        KSh
                                        <?= number_format(
                                            (float)(
                                                $service['price']
                                                ?? 0
                                            ),
                                            2
                                        ) ?>

                                    </div>

                                </td>


                                <!-- DURATION -->

                                <td>

                                    <span class="duration">

                                        ◷

                                        <?= e(
                                            $service['duration']
                                            ?? '-'
                                        ) ?>

                                    </span>

                                </td>


                                <!-- BOOKINGS -->

                                <td>

                                    <span class="booking-count">

                                        <?= number_format(
                                            (int)(
                                                $service['total_bookings']
                                                ?? 0
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- STATUS -->

                                <td>


                                    <?php if (
                                        ($service['status'] ?? '')
                                        === 'active'
                                    ): ?>


                                        <span
                                            class="badge badge-active"
                                        >

                                            <span class="badge-dot"></span>

                                            Active

                                        </span>


                                    <?php else: ?>


                                        <span
                                            class="badge badge-inactive"
                                        >

                                            <span class="badge-dot"></span>

                                            Inactive

                                        </span>


                                    <?php endif; ?>


                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="actions">


                                        <!-- VIEW -->

                                        <a
                                            href="view.php?id=<?= (int)$service['id'] ?>"
                                            class="action-btn action-view"
                                            title="View Service"
                                        >
                                            👁
                                        </a>


                                        <?php if (
                                            $role === 'admin'
                                        ): ?>


                                            <!-- EDIT -->

                                            <a
                                                href="edit.php?id=<?= (int)$service['id'] ?>"
                                                class="action-btn action-edit"
                                                title="Edit Service"
                                            >
                                                ✎
                                            </a>


                                            <?php if (
                                                ($service['status'] ?? '')
                                                === 'active'
                                            ): ?>


                                                <!-- DELETE -->

                                                <a
                                                    href="delete.php?id=<?= (int)$service['id'] ?>"
                                                    class="action-btn action-delete"
                                                    title="Deactivate Service"
                                                    onclick="return confirm('Are you sure you want to deactivate this service?');"
                                                >
                                                    🗑
                                                </a>


                                            <?php endif; ?>


                                        <?php endif; ?>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>


                    </table>

                </div>


            <?php else: ?>


                <!-- =================================================
                     EMPTY STATE
                ================================================== -->

                <div class="empty">


                    <div class="empty-icon">
                        ✦
                    </div>


                    <h3>
                        No Services Yet
                    </h3>


                    <p>
                        There are currently no services in BookEase.
                        Add a service to allow customers to book it.
                    </p>


                    <?php if (
                        $role === 'admin'
                    ): ?>

                        <a
                            href="create.php"
                            class="btn btn-primary"
                        >
                            ＋ Add Your First Service
                        </a>

                    <?php endif; ?>


                </div>


            <?php endif; ?>


        </section>


        <!-- FOOTER -->

        <div class="page-footer">

            BookEase Booking Management System

            &nbsp;•&nbsp;

            Services

        </div>


    <?php endif; ?>


</main>


</body>

</html>