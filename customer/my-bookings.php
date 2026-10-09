<?php
/*
|--------------------------------------------------------------------------
| BookEase - Customer Booking History
| File: customer/my-bookings.php
|--------------------------------------------------------------------------
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

/*
|--------------------------------------------------------------------------
| Prevent Admin / Staff
|--------------------------------------------------------------------------
*/

$role = $_SESSION['role'] ?? 'customer';

if (in_array($role, ['admin', 'staff'], true)) {
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

    if (!$conn instanceof PDO) {
        die("Invalid database connection.");
    }

} catch (Exception $e) {
    die("Database connection failed.");
}

/*
|--------------------------------------------------------------------------
| Customer
|--------------------------------------------------------------------------
*/

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    header("Location: ../auth/login.php");
    exit;
}

$userName = $_SESSION['name'] ?? 'Customer';

$appName = defined('APP_NAME') ? APP_NAME : 'BookEase';

$currency = defined('CURRENCY') ? CURRENCY : 'KSh';

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

try {

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN LOWER(status) = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN LOWER(status) = 'confirmed' THEN 1 ELSE 0 END) AS confirmed,
            SUM(CASE WHEN LOWER(status) = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN LOWER(status) = 'cancelled' THEN 1 ELSE 0 END) AS cancelled
        FROM bookings
        WHERE user_id = ?
    ");

    $stmt->execute([$userId]);

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($stats) {
        $totalBookings = (int) ($stats['total'] ?? 0);
        $pendingBookings = (int) ($stats['pending'] ?? 0);
        $confirmedBookings = (int) ($stats['confirmed'] ?? 0);
        $completedBookings = (int) ($stats['completed'] ?? 0);
        $cancelledBookings = (int) ($stats['cancelled'] ?? 0);
    }

    $stmt->closeCursor();

} catch (Exception $e) {
    // Keep dashboard working even if statistics fail.
}

/*
|--------------------------------------------------------------------------
| Get Customer Bookings
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
            b.notes,
            b.created_at,
            s.name AS service_name,
            s.duration AS service_duration
        FROM bookings b
        LEFT JOIN services s
            ON b.service_id = s.id
        WHERE b.user_id = ?
        ORDER BY
            b.booking_date DESC,
            b.booking_time DESC,
            b.id DESC
    ");

    $stmt->execute([$userId]);

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt->closeCursor();

} catch (Exception $e) {

    $bookings = [];
}

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function customerBookingStatusClass($status)
{
    $status = strtolower(trim((string) $status));

    switch ($status) {
        case 'confirmed':
            return 'confirmed';

        case 'completed':
            return 'completed';

        case 'cancelled':
            return 'cancelled';

        case 'pending':
            return 'pending';

        default:
            return 'pending';
    }
}


function customerBookingStatusLabel($status)
{
    $status = strtolower(trim((string) $status));

    switch ($status) {
        case 'confirmed':
            return 'Confirmed';

        case 'completed':
            return 'Completed';

        case 'cancelled':
            return 'Cancelled';

        case 'pending':
            return 'Pending';

        default:
            return ucfirst($status ?: 'Pending');
    }
}


function customerPaymentClass($status)
{
    $status = strtolower(trim((string) $status));

    switch ($status) {
        case 'paid':
            return 'paid';

        case 'failed':
            return 'failed';

        case 'refunded':
            return 'refunded';

        default:
            return 'unpaid';
    }
}


function customerPaymentLabel($status)
{
    $status = strtolower(trim((string) $status));

    switch ($status) {
        case 'paid':
            return 'Paid';

        case 'failed':
            return 'Failed';

        case 'refunded':
            return 'Refunded';

        case 'unpaid':
            return 'Unpaid';

        default:
            return ucfirst($status ?: 'Unpaid');
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
    My Bookings | <?= htmlspecialchars($appName) ?>
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

/* =========================================================
   NAVBAR
========================================================= */

.navbar {
    background: #0f172a;
    color: white;
    padding: 16px 30px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 20px;
}

.logo {
    font-size: 24px;
    font-weight: 800;
    color: white;
}

.logo span {
    color: #60a5fa;
}

.nav-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-name {
    font-size: 14px;
    color: #e2e8f0;
    font-weight: 600;
}

.nav-btn {
    text-decoration: none;
    color: white;

    padding: 9px 14px;

    border-radius: 7px;

    font-size: 13px;

    background: #1e293b;

    border: 1px solid #334155;

    transition: .2s;
}

.nav-btn:hover {
    background: #334155;
}

.logout {
    background: #ef4444;
    border-color: #ef4444;
}

.logout:hover {
    background: #dc2626;
}

/* =========================================================
   CONTAINER
========================================================= */

.container {
    width: 94%;
    max-width: 1300px;

    margin: auto;

    padding: 32px 0 50px;
}

/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 25px;
}

.page-title h1 {
    margin: 0 0 7px;

    font-size: 30px;

    color: #0f172a;
}

.page-title p {
    margin: 0;

    color: #64748b;

    font-size: 14px;
}

/* =========================================================
   BUTTONS
========================================================= */

.btn {
    display: inline-block;

    text-decoration: none;

    border: none;

    cursor: pointer;

    padding: 11px 17px;

    border-radius: 8px;

    font-size: 14px;

    font-weight: 600;

    transition: .2s;
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

/* =========================================================
   STATISTICS
========================================================= */

.stats {
    display: grid;

    grid-template-columns: repeat(5, 1fr);

    gap: 16px;

    margin-bottom: 25px;
}

.stat-card {
    position: relative;

    background: white;

    border-radius: 12px;

    padding: 20px;

    border: 1px solid #e2e8f0;

    box-shadow: 0 5px 20px rgba(0,0,0,.05);

    overflow: hidden;
}

.stat-card::before {
    content: "";

    position: absolute;

    left: 0;
    top: 0;
    bottom: 0;

    width: 4px;
}

.stat-card.total::before {
    background: #2563eb;
}

.stat-card.pending::before {
    background: #f59e0b;
}

.stat-card.confirmed::before {
    background: #16a34a;
}

.stat-card.completed::before {
    background: #7c3aed;
}

.stat-card.cancelled::before {
    background: #ef4444;
}

.stat-label {
    color: #64748b;

    font-size: 13px;

    margin-bottom: 8px;
}

.stat-number {
    font-size: 29px;

    font-weight: 800;

    color: #0f172a;
}

/* =========================================================
   INFORMATION BANNER
========================================================= */

.info-banner {
    background: linear-gradient(
        135deg,
        #eff6ff,
        #f8fafc
    );

    border: 1px solid #bfdbfe;

    border-radius: 12px;

    padding: 18px 20px;

    margin-bottom: 25px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;
}

.info-title {
    font-weight: 700;

    color: #1e3a8a;

    margin-bottom: 5px;
}

.info-text {
    color: #64748b;

    font-size: 13px;

    line-height: 1.5;
}

/* =========================================================
   MAIN CARD
========================================================= */

.card {
    background: white;

    border-radius: 14px;

    border: 1px solid #e2e8f0;

    box-shadow: 0 5px 25px rgba(0,0,0,.06);

    overflow: hidden;
}

.card-header {
    padding: 20px 22px;

    border-bottom: 1px solid #e2e8f0;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;
}

.card-header h2 {
    margin: 0;

    font-size: 19px;

    color: #0f172a;
}

.booking-count {
    background: #eff6ff;

    color: #2563eb;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 700;
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

    min-width: 1050px;

    border-collapse: collapse;
}

th {
    background: #f8fafc;

    color: #64748b;

    font-size: 12px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .3px;

    text-align: left;

    padding: 15px 18px;

    border-bottom: 1px solid #e2e8f0;
}

td {
    padding: 16px 18px;

    border-bottom: 1px solid #e2e8f0;

    font-size: 14px;

    vertical-align: middle;
}

tbody tr {
    transition: .15s;
}

tbody tr:hover {
    background: #f8fafc;
}

tbody tr:last-child td {
    border-bottom: none;
}

/* =========================================================
   BOOKING
========================================================= */

.booking-reference {
    color: #2563eb;

    font-weight: 800;

    font-size: 13px;
}

.booking-id {
    display: block;

    color: #94a3b8;

    font-size: 11px;

    margin-top: 4px;
}

/* =========================================================
   SERVICE
========================================================= */

.service-name {
    font-weight: 700;

    color: #0f172a;

    margin-bottom: 4px;
}

.service-duration {
    color: #94a3b8;

    font-size: 12px;
}

/* =========================================================
   DATE
========================================================= */

.date-main {
    font-weight: 600;

    color: #334155;

    white-space: nowrap;
}

.time-main {
    color: #64748b;

    margin-top: 4px;

    font-size: 12px;

    white-space: nowrap;
}

/* =========================================================
   PRICE
========================================================= */

.price {
    font-weight: 700;

    color: #0f172a;

    white-space: nowrap;
}

/* =========================================================
   STATUS
========================================================= */

.status {
    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 700;

    white-space: nowrap;
}

.status::before {
    content: "";

    width: 6px;

    height: 6px;

    border-radius: 50%;

    background: currentColor;
}

.status.pending {
    background: #fef3c7;

    color: #92400e;
}

.status.confirmed {
    background: #dcfce7;

    color: #166534;
}

.status.completed {
    background: #dbeafe;

    color: #1e40af;
}

.status.cancelled {
    background: #fee2e2;

    color: #991b1b;
}

/* =========================================================
   PAYMENT
========================================================= */

.payment {
    display: inline-block;

    padding: 5px 9px;

    border-radius: 15px;

    font-size: 11px;

    font-weight: 700;

    white-space: nowrap;
}

.payment.paid {
    background: #dcfce7;

    color: #166534;
}

.payment.unpaid {
    background: #fef3c7;

    color: #92400e;
}

.payment.failed {
    background: #fee2e2;

    color: #991b1b;
}

.payment.refunded {
    background: #ede9fe;

    color: #6d28d9;
}

/* =========================================================
   VIEW BUTTON
========================================================= */

.view-button {
    display: inline-block;

    background: #eff6ff;

    color: #2563eb;

    text-decoration: none;

    padding: 8px 12px;

    border-radius: 7px;

    font-size: 12px;

    font-weight: 700;

    white-space: nowrap;

    transition: .2s;
}

.view-button:hover {
    background: #dbeafe;
}

/* =========================================================
   EMPTY STATE
========================================================= */

.empty {
    text-align: center;

    padding: 70px 20px;
}

.empty-icon {
    font-size: 48px;

    margin-bottom: 12px;
}

.empty h2 {
    margin: 0 0 8px;

    color: #334155;
}

.empty p {
    color: #64748b;

    margin: 0 0 20px;
}

/* =========================================================
   FOOTER
========================================================= */

.footer {
    text-align: center;

    color: #94a3b8;

    font-size: 12px;

    padding: 25px 0 0;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .stats {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 750px) {

    .navbar {
        padding: 14px 18px;
    }

    .user-name {
        display: none;
    }

    .container {
        width: 94%;

        padding-top: 22px;
    }

    .page-header {
        align-items: flex-start;

        flex-direction: column;
    }

    .page-header .btn {
        width: 100%;

        text-align: center;
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .info-banner {
        align-items: flex-start;

        flex-direction: column;
    }

    .info-banner .btn {
        width: 100%;

        text-align: center;
    }
}

@media (max-width: 450px) {

    .stats {
        grid-template-columns: 1fr;
    }

    .page-title h1 {
        font-size: 25px;
    }

    .nav-right {
        gap: 6px;
    }

    .nav-btn {
        padding: 8px 10px;

        font-size: 12px;
    }
}

</style>

</head>

<body>

<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar">

    <div class="logo">
        Book<span>Ease</span>
    </div>

    <div class="nav-right">

        <span class="user-name">
            Welcome,
            <?= htmlspecialchars($userName) ?>
        </span>

        <a
            href="../customer/dashboard.php"
            class="nav-btn"
        >
            Dashboard
        </a>

        <a
            href="../auth/logout.php"
            class="nav-btn logout"
        >
            Logout
        </a>

    </div>

</nav>


<!-- =========================================================
     CONTENT
========================================================= -->

<div class="container">

    <!-- PAGE HEADER -->

    <div class="page-header">

        <div class="page-title">

            <h1>
                My Bookings
            </h1>

            <p>
                Track all your BookEase appointments and booking status.
            </p>

        </div>

        <a
            href="../customer/book.php"
            class="btn btn-primary"
        >
            + New Booking
        </a>

    </div>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <div class="stats">

        <div class="stat-card total">

            <div class="stat-label">
                Total Bookings
            </div>

            <div class="stat-number">
                <?= $totalBookings ?>
            </div>

        </div>


        <div class="stat-card pending">

            <div class="stat-label">
                Pending
            </div>

            <div class="stat-number">
                <?= $pendingBookings ?>
            </div>

        </div>


        <div class="stat-card confirmed">

            <div class="stat-label">
                Confirmed
            </div>

            <div class="stat-number">
                <?= $confirmedBookings ?>
            </div>

        </div>


        <div class="stat-card completed">

            <div class="stat-label">
                Completed
            </div>

            <div class="stat-number">
                <?= $completedBookings ?>
            </div>

        </div>


        <div class="stat-card cancelled">

            <div class="stat-label">
                Cancelled
            </div>

            <div class="stat-number">
                <?= $cancelledBookings ?>
            </div>

        </div>

    </div>


    <!-- =====================================================
         INFORMATION
    ====================================================== -->

    <div class="info-banner">

        <div>

            <div class="info-title">
                Booking Status
            </div>

            <div class="info-text">
                Pending bookings are waiting for confirmation.
                Confirmed bookings have been accepted, while completed
                bookings have already been served.
            </div>

        </div>

        <a
            href="../customer/book.php"
            class="btn btn-primary"
        >
            Book a Service
        </a>

    </div>


    <!-- =====================================================
         BOOKING TABLE
    ====================================================== -->

    <div class="card">

        <div class="card-header">

            <h2>
                Booking History
            </h2>

            <span class="booking-count">

                <?= $totalBookings ?>

                <?= $totalBookings === 1
                    ? 'Booking'
                    : 'Bookings'
                ?>

            </span>

        </div>


        <?php if (!empty($bookings)): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Booking
                            </th>

                            <th>
                                Service
                            </th>

                            <th>
                                Date & Time
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Payment
                            </th>

                            <th>
                                Status
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
                                $booking['status'] ?? 'pending'
                            )
                        );

                        $paymentStatus = strtolower(
                            trim(
                                $booking['payment_status'] ?? 'unpaid'
                            )
                        );

                        $bookingReference =
                            !empty($booking['booking_reference'])
                                ? $booking['booking_reference']
                                : 'BK-' . $booking['id'];

                        $bookingDate =
                            $booking['booking_date'] ?? '';

                        $bookingTime =
                            $booking['booking_time'] ?? '';

                        ?>

                        <tr>

                            <!-- BOOKING -->

                            <td>

                                <div class="booking-reference">

                                    <?= htmlspecialchars(
                                        $bookingReference
                                    ) ?>

                                </div>

                                <span class="booking-id">

                                    Booking ID:
                                    #<?= (int) $booking['id'] ?>

                                </span>

                            </td>


                            <!-- SERVICE -->

                            <td>

                                <div class="service-name">

                                    <?= htmlspecialchars(
                                        $booking['service_name']
                                        ?? 'Service'
                                    ) ?>

                                </div>

                                <?php if (
                                    !empty(
                                        $booking['service_duration']
                                    )
                                ): ?>

                                    <div class="service-duration">

                                        <?= (int)
                                            $booking['service_duration']
                                        ?>
                                        minutes

                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- DATE / TIME -->

                            <td>

                                <div class="date-main">

                                    <?php if ($bookingDate): ?>

                                        <?= htmlspecialchars(
                                            date(
                                                'd M Y',
                                                strtotime($bookingDate)
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </div>

                                <div class="time-main">

                                    <?php if ($bookingTime): ?>

                                        <?= htmlspecialchars(
                                            date(
                                                'h:i A',
                                                strtotime($bookingTime)
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        -

                                    <?php endif; ?>

                                </div>

                            </td>


                            <!-- AMOUNT -->

                            <td>

                                <div class="price">

                                    <?= htmlspecialchars($currency) ?>

                                    <?= number_format(
                                        (float) (
                                            $booking['amount'] ?? 0
                                        ),
                                        2
                                    ) ?>

                                </div>

                            </td>


                            <!-- PAYMENT -->

                            <td>

                                <span
                                    class="payment <?= htmlspecialchars(
                                        customerPaymentClass(
                                            $paymentStatus
                                        )
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        customerPaymentLabel(
                                            $paymentStatus
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status <?= htmlspecialchars(
                                        customerBookingStatusClass(
                                            $status
                                        )
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        customerBookingStatusLabel(
                                            $status
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- ACTION -->

                            <td>

                                <a
                                    href="booking-details.php?id=<?= (int) $booking['id'] ?>"
                                    class="view-button"
                                >
                                    View Details
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
                    📅
                </div>

                <h2>
                    No Bookings Yet
                </h2>

                <p>
                    You haven't made any bookings yet.
                    Start by selecting a service.
                </p>

                <a
                    href="../customer/book.php"
                    class="btn btn-primary"
                >
                    + Make Your First Booking
                </a>

            </div>

        <?php endif; ?>

    </div>


    <!-- FOOTER -->

    <div class="footer">

        <?= htmlspecialchars($appName) ?>

        &copy;

        <?= date('Y') ?>

        — Booking Management System

    </div>

</div>

</body>

</html>
