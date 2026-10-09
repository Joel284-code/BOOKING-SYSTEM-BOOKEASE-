<?php

/*
|--------------------------------------------------------------------------
| BookEase - Admin Reports
|--------------------------------------------------------------------------
| File:
| C:\xampp\htdocs\booking-system\admin\reports.php
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

if (empty($_SESSION['user_id'])) {

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

    if (!$conn instanceof PDO) {
        throw new RuntimeException(
            'Invalid database connection.'
        );
    }

} catch (Throwable $e) {

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
| Default Values
|--------------------------------------------------------------------------
*/

$totalBookings   = 0;
$pending         = 0;
$confirmed       = 0;
$completed       = 0;
$cancelled       = 0;

$totalCustomers  = 0;
$totalServices   = 0;

$totalPayments   = 0;
$paidPayments    = 0;
$pendingPayments = 0;
$failedPayments  = 0;

$revenue         = 0;

$recentBookings  = [];

$errorMessages = [];


/*
|--------------------------------------------------------------------------
| TOTAL BOOKINGS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->query("
        SELECT COUNT(*)
        FROM bookings
    ");

    $totalBookings = (int) $stmt->fetchColumn();

} catch (Throwable $e) {

    $errorMessages[] =
        'Unable to load booking statistics.';
}


/*
|--------------------------------------------------------------------------
| BOOKING STATUS COUNTS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->query("
        SELECT
            LOWER(TRIM(status)) AS booking_status,
            COUNT(*) AS total
        FROM bookings
        GROUP BY LOWER(TRIM(status))
    ");

    $statusRows =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    foreach ($statusRows as $row) {

        $bookingStatus =
            strtolower(
                trim(
                    $row['booking_status'] ?? ''
                )
            );

        $count =
            (int) (
                $row['total'] ?? 0
            );


        switch ($bookingStatus) {

            case 'pending':
                $pending = $count;
                break;

            case 'confirmed':
                $confirmed = $count;
                break;

            case 'completed':
                $completed = $count;
                break;

            case 'cancelled':
                $cancelled = $count;
                break;
        }
    }

} catch (Throwable $e) {

    $errorMessages[] =
        'Unable to load booking status statistics.';
}


/*
|--------------------------------------------------------------------------
| TOTAL CUSTOMERS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->query("
        SELECT COUNT(*)
        FROM users
        WHERE role = 'customer'
    ");

    $totalCustomers =
        (int) $stmt->fetchColumn();

} catch (Throwable $e) {

    $errorMessages[] =
        'Unable to load customer statistics.';
}


/*
|--------------------------------------------------------------------------
| TOTAL SERVICES
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->query("
        SELECT COUNT(*)
        FROM services
    ");

    $totalServices =
        (int) $stmt->fetchColumn();

} catch (Throwable $e) {

    $errorMessages[] =
        'Unable to load service statistics.';
}


/*
|--------------------------------------------------------------------------
| PAYMENT STATISTICS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->query("
        SELECT
            COUNT(*) AS total_payments,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(TRIM(status)) = 'paid'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS paid_payments,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(TRIM(status)) = 'pending'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS pending_payments,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(TRIM(status)) = 'failed'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS failed_payments,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(TRIM(status)) = 'paid'
                        THEN amount
                        ELSE 0
                    END
                ),
                0
            ) AS revenue

        FROM payments
    ");

    $paymentStats =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if ($paymentStats) {

        $totalPayments =
            (int) (
                $paymentStats['total_payments']
                ?? 0
            );

        $paidPayments =
            (int) (
                $paymentStats['paid_payments']
                ?? 0
            );

        $pendingPayments =
            (int) (
                $paymentStats['pending_payments']
                ?? 0
            );

        $failedPayments =
            (int) (
                $paymentStats['failed_payments']
                ?? 0
            );

        $revenue =
            (float) (
                $paymentStats['revenue']
                ?? 0
            );
    }

} catch (Throwable $e) {

    $errorMessages[] =
        'Unable to load payment statistics.';
}


/*
|--------------------------------------------------------------------------
| RECENT BOOKINGS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->query("
        SELECT

            b.id,
            b.booking_date,
            b.booking_time,
            b.status,

            u.name AS customer,

            s.name AS service

        FROM bookings b

        LEFT JOIN users u
            ON b.user_id = u.id

        LEFT JOIN services s
            ON b.service_id = s.id

        ORDER BY b.id DESC

        LIMIT 10
    ");

    $recentBookings =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $errorMessages[] =
        'Unable to load recent bookings.';
}


/*
|--------------------------------------------------------------------------
| Current Page
|--------------------------------------------------------------------------
*/

$currentPage = 'reports';


/*
|--------------------------------------------------------------------------
| Safe HTML Helper
|--------------------------------------------------------------------------
*/

function report_h($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Status Class
|--------------------------------------------------------------------------
*/

function report_status_class($status)
{
    $status =
        strtolower(
            trim(
                (string) $status
            )
        );

    $allowed = [
        'pending',
        'confirmed',
        'completed',
        'cancelled'
    ];

    return in_array(
        $status,
        $allowed,
        true
    )
        ? $status
        : 'default';
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
    Reports | BookEase
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

.admin-nav {

    background: #0f172a;

    color: white;

    padding: 0 25px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    min-height: 65px;

    gap: 20px;

    position: sticky;

    top: 0;

    z-index: 1000;

}

.brand {

    color: white;

    text-decoration: none;

    font-size: 21px;

    font-weight: 700;

    white-space: nowrap;

}

.brand span {

    color: #60a5fa;

}

.nav-links {

    display: flex;

    align-items: center;

    gap: 5px;

    flex-wrap: wrap;

}

.nav-links a {

    color: #cbd5e1;

    text-decoration: none;

    padding: 9px 12px;

    border-radius: 7px;

    font-size: 14px;

    transition:
        background .2s ease,
        color .2s ease;

}

.nav-links a:hover {

    background: #1e293b;

    color: white;

}

.nav-links a.active {

    background: #2563eb;

    color: white;

}

.logout-link {

    color: #fecaca !important;

}


/*
|--------------------------------------------------------------------------
| Main Content
|--------------------------------------------------------------------------
*/

.content {

    max-width: 1250px;

    margin: 0 auto;

    padding: 30px;
}


/*
|--------------------------------------------------------------------------
| Page Header
|--------------------------------------------------------------------------
*/

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 25px;

}

.page-title h1 {

    margin: 0;

    font-size: 30px;

    color: #0f172a;

}

.page-title p {

    margin: 7px 0 0;

    color: #64748b;

}

.header-actions {

    display: flex;

    gap: 10px;

}

.header-btn {

    display: inline-block;

    padding: 10px 15px;

    border-radius: 7px;

    text-decoration: none;

    font-size: 14px;

    font-weight: 600;

    background: white;

    color: #334155;

    border: 1px solid #cbd5e1;

}

.header-btn:hover {

    background: #f8fafc;

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

.alert-error {

    background: #fee2e2;

    border: 1px solid #fecaca;

    color: #991b1b;

}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

.cards {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 18px;

    margin-bottom: 25px;

}

.card {

    background: white;

    padding: 22px;

    border-radius: 12px;

    border: 1px solid #e2e8f0;

    box-shadow:
        0 5px 20px
        rgba(0, 0, 0, .05);

}

.card-icon {

    font-size: 27px;

    margin-bottom: 10px;

}

.card h3 {

    margin: 0 0 6px;

    font-size: 25px;

    color: #0f172a;

}

.card p {

    margin: 0;

    color: #64748b;

    font-size: 14px;

}


/*
|--------------------------------------------------------------------------
| Status Summary
|--------------------------------------------------------------------------
*/

.status-grid {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 18px;

    margin-bottom: 25px;

}

.status-card {

    background: white;

    padding: 20px;

    border-radius: 12px;

    border: 1px solid #e2e8f0;

}

.status-card h4 {

    margin: 0 0 8px;

    color: #64748b;

    font-size: 14px;

}

.status-number {

    font-size: 25px;

    font-weight: 700;

}

.status-pending {

    color: #d97706;

}

.status-confirmed {

    color: #2563eb;

}

.status-completed {

    color: #16a34a;

}

.status-cancelled {

    color: #dc2626;

}


/*
|--------------------------------------------------------------------------
| Payment Summary
|--------------------------------------------------------------------------
*/

.payment-grid {

    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 18px;

    margin-bottom: 25px;

}

.payment-card {

    background: white;

    border-radius: 12px;

    padding: 22px;

    border: 1px solid #e2e8f0;

}

.payment-card h4 {

    margin: 0 0 8px;

    color: #64748b;

}

.payment-value {

    font-size: 24px;

    font-weight: 700;

    color: #0f172a;

}


/*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
*/

.table-container {

    background: white;

    border-radius: 12px;

    overflow: hidden;

    box-shadow:
        0 5px 20px
        rgba(0, 0, 0, .05);

    border: 1px solid #e2e8f0;

    margin-bottom: 25px;

}

.table-header {

    padding: 20px 22px;

    border-bottom:
        1px solid #e2e8f0;

    display: flex;

    justify-content: space-between;

    align-items: center;

}

.table-header h3 {

    margin: 0;

    font-size: 19px;

}

.table-header a {

    color: #2563eb;

    text-decoration: none;

    font-size: 14px;

    font-weight: 600;

}

.table-wrapper {

    overflow-x: auto;

}

table {

    width: 100%;

    min-width: 850px;

    border-collapse: collapse;

}

thead {

    background: #f8fafc;

}

th {

    text-align: left;

    padding: 14px 16px;

    color: #475569;

    font-size: 12px;

    text-transform: uppercase;

    border-bottom:
        1px solid #e2e8f0;

}

td {

    padding: 15px 16px;

    border-bottom:
        1px solid #e2e8f0;

    vertical-align: middle;

}

tbody tr:hover {

    background: #f8fafc;

}


/*
|--------------------------------------------------------------------------
| Booking Status
|--------------------------------------------------------------------------
*/

.badge {

    display: inline-block;

    padding: 6px 11px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 700;

}

.badge-pending {

    background: #fef3c7;

    color: #92400e;

}

.badge-confirmed {

    background: #dbeafe;

    color: #1d4ed8;

}

.badge-completed {

    background: #dcfce7;

    color: #166534;

}

.badge-cancelled {

    background: #fee2e2;

    color: #991b1b;

}

.badge-default {

    background: #e2e8f0;

    color: #334155;

}


/*
|--------------------------------------------------------------------------
| View Button
|--------------------------------------------------------------------------
*/

.view-btn {

    display: inline-block;

    padding: 7px 12px;

    background: #2563eb;

    color: white;

    text-decoration: none;

    border-radius: 6px;

    font-size: 12px;

    font-weight: 600;

}

.view-btn:hover {

    background: #1d4ed8;

}


/*
|--------------------------------------------------------------------------
| Empty
|--------------------------------------------------------------------------
*/

.empty {

    text-align: center;

    padding: 40px;

    color: #64748b;

}


/*
|--------------------------------------------------------------------------
| Footer
|--------------------------------------------------------------------------
*/

.footer {

    text-align: center;

    padding: 20px;

    color: #64748b;

    font-size: 13px;

}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 1050px) {

    .nav-links a {

        padding:
            8px 9px;

    }

    .cards {

        grid-template-columns:
            repeat(2, 1fr);

    }

    .status-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

}

@media (max-width: 750px) {

    .admin-nav {

        flex-direction: column;

        align-items: flex-start;

        padding: 15px;

    }

    .nav-links {

        width: 100%;

        overflow-x: auto;

        flex-wrap: nowrap;

        padding-bottom: 3px;

    }

    .content {

        padding: 15px;

    }

    .page-header {

        flex-direction: column;

        align-items: flex-start;

    }

    .cards,

    .status-grid,

    .payment-grid {

        grid-template-columns: 1fr;

    }

}

</style>

</head>


<body>


<!--
|--------------------------------------------------------------------------
| ADMIN NAVIGATION
|--------------------------------------------------------------------------
-->

<nav class="admin-nav">


    <a
        href="<?= report_h(BASE_URL) ?>/admin/dashboard.php"
        class="brand"
    >
        Book<span>Ease</span>
    </a>


    <div class="nav-links">


        <a
            href="<?= report_h(BASE_URL) ?>/admin/dashboard.php"
        >
            Dashboard
        </a>


        <a
            href="<?= report_h(BASE_URL) ?>/admin/bookings.php"
        >
            Bookings
        </a>


        <a
            href="<?= report_h(BASE_URL) ?>/admin/customers.php"
        >
            Customers
        </a>


        <a
            href="<?= report_h(BASE_URL) ?>/admin/services.php"
        >
            Services
        </a>


        <a
            href="<?= report_h(BASE_URL) ?>/admin/payments.php"
        >
            Payments
        </a>


        <a
            href="<?= report_h(BASE_URL) ?>/admin/reports.php"
            class="active"
        >
            Reports
        </a>


        <a
            href="<?= report_h(BASE_URL) ?>/admin/profile.php"
        >
            Profile
        </a>


        <a
            href="<?= report_h(BASE_URL) ?>/logout.php"
            class="logout-link"
        >
            Logout
        </a>


    </div>

</nav>


<!--
|--------------------------------------------------------------------------
| CONTENT
|--------------------------------------------------------------------------
-->

<div class="content">


    <!-- PAGE HEADER -->

    <div class="page-header">

        <div class="page-title">

            <h1>
                Reports
            </h1>

            <p>
                Booking, customer, service and payment overview.
            </p>

        </div>


        <div class="header-actions">

            <a
                href="<?= report_h(BASE_URL) ?>/admin/dashboard.php"
                class="header-btn"
            >
                ← Dashboard
            </a>

        </div>

    </div>


    <!-- ERRORS -->

    <?php if (!empty($errorMessages)): ?>

        <div class="alert alert-error">

            <?php foreach ($errorMessages as $errorMessage): ?>

                <div>
                    <?= report_h($errorMessage) ?>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <!--
    |--------------------------------------------------------------------------
    | MAIN STATISTICS
    |--------------------------------------------------------------------------
    -->

    <div class="cards">


        <!-- BOOKINGS -->

        <div class="card">

            <div class="card-icon">
                📅
            </div>

            <h3>
                <?= number_format($totalBookings) ?>
            </h3>

            <p>
                Total Bookings
            </p>

        </div>


        <!-- CUSTOMERS -->

        <div class="card">

            <div class="card-icon">
                👥
            </div>

            <h3>
                <?= number_format($totalCustomers) ?>
            </h3>

            <p>
                Customers
            </p>

        </div>


        <!-- SERVICES -->

        <div class="card">

            <div class="card-icon">
                🛎️
            </div>

            <h3>
                <?= number_format($totalServices) ?>
            </h3>

            <p>
                Services
            </p>

        </div>


        <!-- REVENUE -->

        <div class="card">

            <div class="card-icon">
                💰
            </div>

            <h3>
                KSh <?= number_format(
                    $revenue,
                    2
                ) ?>
            </h3>

            <p>
                Paid Revenue
            </p>

        </div>


    </div>


    <!--
    |--------------------------------------------------------------------------
    | BOOKING STATUS
    |--------------------------------------------------------------------------
    -->

    <div class="status-grid">


        <div class="status-card">

            <h4>
                Pending Bookings
            </h4>

            <div class="status-number status-pending">

                <?= number_format($pending) ?>

            </div>

        </div>


        <div class="status-card">

            <h4>
                Confirmed Bookings
            </h4>

            <div class="status-number status-confirmed">

                <?= number_format($confirmed) ?>

            </div>

        </div>


        <div class="status-card">

            <h4>
                Completed Bookings
            </h4>

            <div class="status-number status-completed">

                <?= number_format($completed) ?>

            </div>

        </div>


        <div class="status-card">

            <h4>
                Cancelled Bookings
            </h4>

            <div class="status-number status-cancelled">

                <?= number_format($cancelled) ?>

            </div>

        </div>


    </div>


    <!--
    |--------------------------------------------------------------------------
    | PAYMENT SUMMARY
    |--------------------------------------------------------------------------
    -->

    <div class="payment-grid">


        <div class="payment-card">

            <h4>
                Total Transactions
            </h4>

            <div class="payment-value">

                <?= number_format(
                    $totalPayments
                ) ?>

            </div>

        </div>


        <div class="payment-card">

            <h4>
                Paid Transactions
            </h4>

            <div
                class="payment-value"
                style="color:#16a34a;"
            >

                <?= number_format(
                    $paidPayments
                ) ?>

            </div>

        </div>


        <div class="payment-card">

            <h4>
                Pending Transactions
            </h4>

            <div
                class="payment-value"
                style="color:#d97706;"
            >

                <?= number_format(
                    $pendingPayments
                ) ?>

            </div>

        </div>


    </div>


    <!--
    |--------------------------------------------------------------------------
    | BOOKING STATUS REPORT
    |--------------------------------------------------------------------------
    -->

    <div class="table-container">


        <div class="table-header">

            <h3>
                Booking Status Report
            </h3>


            <a
                href="<?= report_h(BASE_URL) ?>/admin/bookings.php"
            >
                Manage Bookings →
            </a>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Status
                        </th>

                        <th>
                            Number of Bookings
                        </th>

                        <th>
                            Percentage
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php

                    $statusReport = [
                        [
                            'name' => 'Pending',
                            'value' => $pending,
                            'class' => 'pending'
                        ],
                        [
                            'name' => 'Confirmed',
                            'value' => $confirmed,
                            'class' => 'confirmed'
                        ],
                        [
                            'name' => 'Completed',
                            'value' => $completed,
                            'class' => 'completed'
                        ],
                        [
                            'name' => 'Cancelled',
                            'value' => $cancelled,
                            'class' => 'cancelled'
                        ]
                    ];

                    ?>


                    <?php foreach (
                        $statusReport
                        as $statusRow
                    ): ?>


                        <?php

                        $percentage =
                            $totalBookings > 0
                            ? (
                                $statusRow['value']
                                / $totalBookings
                            ) * 100
                            : 0;

                        ?>


                        <tr>

                            <td>

                                <span
                                    class="badge badge-<?= report_h(
                                        $statusRow['class']
                                    ) ?>"
                                >

                                    <?= report_h(
                                        $statusRow['name']
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <strong>
                                    <?= number_format(
                                        $statusRow['value']
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <?= number_format(
                                    $percentage,
                                    1
                                ) ?>%

                            </td>

                        </tr>


                    <?php endforeach; ?>


                </tbody>

            </table>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | RECENT BOOKINGS
    |--------------------------------------------------------------------------
    -->

    <div class="table-container">


        <div class="table-header">

            <h3>
                Recent Bookings
            </h3>


            <a
                href="<?= report_h(BASE_URL) ?>/admin/bookings.php"
            >
                View All →
            </a>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Service
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Time
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


                <?php if (!empty($recentBookings)): ?>


                    <?php foreach (
                        $recentBookings
                        as $booking
                    ): ?>


                        <?php

                        $bookingId =
                            (int) (
                                $booking['id']
                                ?? 0
                            );

                        $customer =
                            $booking['customer']
                            ?? 'Unknown Customer';

                        $service =
                            $booking['service']
                            ?? 'Unknown Service';

                        $bookingDate =
                            $booking['booking_date']
                            ?? '-';

                        $bookingTime =
                            $booking['booking_time']
                            ?? '-';

                        $bookingStatus =
                            strtolower(
                                trim(
                                    $booking['status']
                                    ?? 'pending'
                                )
                            );

                        $statusClass =
                            report_status_class(
                                $bookingStatus
                            );

                        ?>


                        <tr>


                            <td>

                                <strong>
                                    #<?= $bookingId ?>
                                </strong>

                            </td>


                            <td>

                                <?= report_h(
                                    $customer
                                ) ?>

                            </td>


                            <td>

                                <?= report_h(
                                    $service
                                ) ?>

                            </td>


                            <td>

                                <?= report_h(
                                    $bookingDate
                                ) ?>

                            </td>


                            <td>

                                <?= report_h(
                                    $bookingTime
                                ) ?>

                            </td>


                            <td>

                                <span
                                    class="badge badge-<?= report_h(
                                        $statusClass
                                    ) ?>"
                                >

                                    <?= ucfirst(
                                        report_h(
                                            $bookingStatus
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?php if ($bookingId > 0): ?>

                                    <a
                                        href="<?= report_h(BASE_URL) ?>/admin/booking-view.php?id=<?= $bookingId ?>"
                                        class="view-btn"
                                    >
                                        View
                                    </a>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="7"
                            class="empty"
                        >

                            No bookings found.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


</div>


<!-- FOOTER -->

<div class="footer">

    BookEase Admin Panel &copy;
    <?= date('Y') ?>

</div>


</body>

</html>