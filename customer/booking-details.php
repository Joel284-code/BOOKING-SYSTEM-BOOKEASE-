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
| Require Customer Login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header("Location: ../auth/login.php");

    exit;
}


/*
|--------------------------------------------------------------------------
| Prevent Admin / Staff From Accessing Customer Area
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['role']) &&
    in_array(
        $_SESSION['role'],
        ['admin', 'staff'],
        true
    )
) {

    header("Location: ../admin/dashboard.php");

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

} catch (PDOException $e) {

    die("Database connection failed.");

}


/*
|--------------------------------------------------------------------------
| Current Customer
|--------------------------------------------------------------------------
*/

$userId = (int) (
    $_SESSION['user_id'] ?? 0
);


if ($userId <= 0) {

    header("Location: ../auth/login.php");

    exit;
}


$userName = $_SESSION['name'] ?? 'Customer';


$appName = defined('APP_NAME')
    ? APP_NAME
    : 'BookEase';


$currency = defined('CURRENCY')
    ? CURRENCY
    : 'KSh';


/*
|--------------------------------------------------------------------------
| Booking Statistics
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

            SUM(
                CASE
                    WHEN status = 'pending'
                    THEN 1
                    ELSE 0
                END
            ) AS pending,

            SUM(
                CASE
                    WHEN status = 'confirmed'
                    THEN 1
                    ELSE 0
                END
            ) AS confirmed,

            SUM(
                CASE
                    WHEN status = 'completed'
                    THEN 1
                    ELSE 0
                END
            ) AS completed,

            SUM(
                CASE
                    WHEN status = 'cancelled'
                    THEN 1
                    ELSE 0
                END
            ) AS cancelled

        FROM bookings

        WHERE user_id = ?
    ");


    $stmt->execute([
        $userId
    ]);


    $stats =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if ($stats) {

        $totalBookings =
            (int) (
                $stats['total'] ?? 0
            );


        $pendingBookings =
            (int) (
                $stats['pending'] ?? 0
            );


        $confirmedBookings =
            (int) (
                $stats['confirmed'] ?? 0
            );


        $completedBookings =
            (int) (
                $stats['completed'] ?? 0
            );


        $cancelledBookings =
            (int) (
                $stats['cancelled'] ?? 0
            );
    }


    $stmt->closeCursor();


} catch (PDOException $e) {

    $totalBookings = 0;

    $pendingBookings = 0;

    $confirmedBookings = 0;

    $completedBookings = 0;

    $cancelledBookings = 0;
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


    $stmt->execute([
        $userId
    ]);


    $bookings =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    $stmt->closeCursor();


} catch (PDOException $e) {

    die("Unable to load your bookings.");

}


/*
|--------------------------------------------------------------------------
| Status Helper
|--------------------------------------------------------------------------
*/

function customerBookingStatusClass($status)
{

    $status = strtolower(
        trim(
            (string) $status
        )
    );


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


/*
|--------------------------------------------------------------------------
| Status Label
|--------------------------------------------------------------------------
*/

function customerBookingStatusLabel($status)
{

    $status = strtolower(
        trim(
            (string) $status
        )
    );


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
            return ucfirst(
                $status ?: 'Pending'
            );
    }
}


/*
|--------------------------------------------------------------------------
| Payment Class
|--------------------------------------------------------------------------
*/

function customerPaymentClass($status)
{

    $status = strtolower(
        trim(
            (string) $status
        )
    );


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


/*
|--------------------------------------------------------------------------
| Payment Label
|--------------------------------------------------------------------------
*/

function customerPaymentLabel($status)
{

    $status = strtolower(
        trim(
            (string) $status
        )
    );


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
            return ucfirst(
                $status ?: 'Unpaid'
            );
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

```
Booking History |

<?= htmlspecialchars($appName) ?>
```

</title>

<style>

/* =========================================================
   GLOBAL
========================================================= */

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
        #f1f5f9;

    color:
        #1e293b;

}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    background:
        #ffffff;

    border-bottom:
        1px solid #e2e8f0;

    padding:
        16px 30px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    position:
        sticky;

    top:
        0;

    z-index:
        100;

}


.logo {

    color:
        #2563eb;

    font-size:
        24px;

    font-weight:
        800;

    letter-spacing:
        -.5px;

}


.nav-right {

    display:
        flex;

    align-items:
        center;

    gap:
        12px;

}


.user-name {

    color:
        #475569;

    font-size:
        14px;

    font-weight:
        600;

}


.nav-link {

    text-decoration:
        none;

    color:
        #475569;

    background:
        #f8fafc;

    border:
        1px solid #e2e8f0;

    padding:
        9px 14px;

    border-radius:
        8px;

    font-size:
        13px;

    font-weight:
        600;

    transition:
        .2s;

}


.nav-link:hover {

    background:
        #eff6ff;

    color:
        #2563eb;

    border-color:
        #bfdbfe;

}


.logout {

    background:
        #ef4444;

    color:
        white;

    border:
        1px solid #ef4444;

}


.logout:hover {

    background:
        #dc2626;

    color:
        white;

}


/* =========================================================
   CONTAINER
========================================================= */

.container {

    max-width:
        1200px;

    margin:
        0 auto;

    padding:
        30px 20px;

}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        20px;

    margin-bottom:
        25px;

}


.page-header h1 {

    margin:
        0 0 6px;

    font-size:
        29px;

    color:
        #0f172a;

    letter-spacing:
        -.5px;

}


.page-header p {

    margin:
        0;

    color:
        #64748b;

    font-size:
        14px;

}


/* =========================================================
   NEW BOOKING BUTTON
========================================================= */

.new-booking {

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        6px;

    background:
        #2563eb;

    color:
        white;

    text-decoration:
        none;

    padding:
        11px 18px;

    border-radius:
        8px;

    font-weight:
        700;

    font-size:
        14px;

    white-space:
        nowrap;

    box-shadow:
        0 4px 10px
        rgba(37, 99, 235, .20);

    transition:
        .2s;

}


.new-booking:hover {

    background:
        #1d4ed8;

    transform:
        translateY(-1px);

}


/* =========================================================
   STATISTICS
========================================================= */

.stats {

    display:
        grid;

    grid-template-columns:
        repeat(5, 1fr);

    gap:
        15px;

    margin-bottom:
        25px;

}


.stat-card {

    background:
        #ffffff;

    border:
        1px solid #e2e8f0;

    border-radius:
        12px;

    padding:
        18px;

    box-shadow:
        0 4px 15px
        rgba(15, 23, 42, .04);

    transition:
        .2s;

}


.stat-card:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 8px 22px
        rgba(15, 23, 42, .08);

}


.stat-label {

    color:
        #64748b;

    font-size:
        13px;

    font-weight:
        600;

    margin-bottom:
        8px;

}


.stat-number {

    font-size:
        28px;

    font-weight:
        800;

    color:
        #0f172a;

}


.stat-total {

    border-top:
        4px solid #2563eb;

}


.stat-pending {

    border-top:
        4px solid #f59e0b;

}


.stat-confirmed {

    border-top:
        4px solid #16a34a;

}


.stat-completed {

    border-top:
        4px solid #7c3aed;

}


.stat-cancelled {

    border-top:
        4px solid #ef4444;

}


/* =========================================================
   STATUS SUMMARY
========================================================= */

.status-summary {

    display:
        grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap:
        15px;

    margin-bottom:
        25px;

}


.status-box {

    background:
        #ffffff;

    border-radius:
        10px;

    padding:
        17px;

    border-left:
        4px solid;

    box-shadow:
        0 4px 15px
        rgba(15, 23, 42, .04);

}


.status-box.pending {

    border-color:
        #f59e0b;

}


.status-box.confirmed {

    border-color:
        #16a34a;

}


.status-box.completed {

    border-color:
        #7c3aed;

}


.status-box.cancelled {

    border-color:
        #ef4444;

}


.status-box h3 {

    margin:
        0 0 7px;

    color:
        #64748b;

    font-size:
        13px;

    font-weight:
        600;

}


.status-box p {

    margin:
        0;

    font-size:
        24px;

    font-weight:
        800;

    color:
        #0f172a;

}


/* =========================================================
   CARD
========================================================= */

.card {

    background:
        #ffffff;

    border-radius:
        12px;

    box-shadow:
        0 5px 20px
        rgba(0, 0, 0, .05);

    border:
        1px solid #e2e8f0;

    overflow:
        hidden;

}


/* =========================================================
   CARD HEADER
========================================================= */

.card-header {

    padding:
        20px 22px;

    border-bottom:
        1px solid #e2e8f0;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

}


.card-header h2 {

    margin:
        0;

    font-size:
        19px;

    color:
        #0f172a;

}


.booking-count {

    background:
        #eff6ff;

    color:
        #2563eb;

    padding:
        5px 10px;

    border-radius:
        20px;

    font-size:
        12px;

    font-weight:
        700;

}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    width:
        100%;

    overflow-x:
        auto;

}


table {

    width:
        100%;

    border-collapse:
        collapse;

    min-width:
        850px;

}


th {

    background:
        #f8fafc;

    color:
        #64748b;

    font-size:
        12px;

    font-weight:
        700;

    text-align:
        left;

    padding:
        15px 18px;

    border-bottom:
        1px solid #e2e8f0;

    text-transform:
        uppercase;

    letter-spacing:
        .3px;

}


td {

    padding:
        16px 18px;

    border-bottom:
        1px solid #e2e8f0;

    font-size:
        14px;

    vertical-align:
        middle;

}


tbody tr {

    transition:
        .15s;

}


tbody tr:hover {

    background:
        #f8fafc;

}


tbody tr:last-child td {

    border-bottom:
        none;

}


/* =========================================================
   BOOKING REFERENCE
========================================================= */

.booking-number {

    font-weight:
        800;

    color:
        #2563eb;

    font-size:
        13px;

}


/* =========================================================
   SERVICE
========================================================= */

.service-name {

    font-weight:
        700;

    color:
        #0f172a;

}


/* =========================================================
   DATE
========================================================= */

.date {

    color:
        #334155;

    white-space:
        nowrap;

    font-weight:
        600;

}


/* =========================================================
   TIME
========================================================= */

.time {

    color:
        #64748b;

    white-space:
        nowrap;

}


/* =========================================================
   PRICE
========================================================= */

.price {

    font-weight:
        700;

    color:
        #0f172a;

    white-space:
        nowrap;

}


/* =========================================================
   PAYMENT
========================================================= */

.payment {

    font-size:
        11px;

    font-weight:
        700;

    padding:
        5px 9px;

    border-radius:
        20px;

    display:
        inline-block;

}


.payment.unpaid {

    background:
        #fef3c7;

    color:
        #92400e;

}


.payment.paid {

    background:
        #dcfce7;

    color:
        #166534;

}


.payment.failed {

    background:
        #fee2e2;

    color:
        #991b1b;

}


.payment.refunded {

    background:
        #ede9fe;

    color:
        #6d28d9;

}


/* =========================================================
   STATUS
========================================================= */

.status {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        6px;

    padding:
        6px 10px;

    border-radius:
        20px;

    font-size:
        11px;

    font-weight:
        700;

    white-space:
        nowrap;

}


.status::before {

    content:
        "";

    width:
        6px;

    height:
        6px;

    border-radius:
        50%;

    background:
        currentColor;

}


.status.pending {

    background:
        #fef3c7;

    color:
        #92400e;

}


.status.confirmed {

    background:
        #dcfce7;

    color:
        #166534;

}


.status.completed {

    background:
        #dbeafe;

    color:
        #1e40af;

}


.status.cancelled {

    background:
        #fee2e2;

    color:
        #991b1b;

}


/* =========================================================
   VIEW BUTTON
========================================================= */

.view-button {

    display:
        inline-block;

    color:
        #2563eb;

    background:
        #eff6ff;

    text-decoration:
        none;

    padding:
        7px 11px;

    border-radius:
        6px;

    font-size:
        12px;

    font-weight:
        700;

    transition:
        .2s;

}


.view-button:hover {

    background:
        #dbeafe;

}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    text-align:
        center;

    padding:
        70px 20px;

    color:
        #64748b;

}


.empty-icon {

    font-size:
        45px;

    margin-bottom:
        15px;

}


.empty h2 {

    margin:
        0 0 8px;

    color:
        #334155;

    font-size:
        20px;

}


.empty p {

    margin:
        0 0 20px;

}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    text-align:
        center;

    color:
        #94a3b8;

    font-size:
        12px;

    padding:
        25px 0 0;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1050px) {

    .stats {

        grid-template-columns:
            repeat(3, 1fr);

    }

    .status-summary {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (max-width: 750px) {

    .navbar {

        padding:
            15px;

    }

    .user-name {

        display:
            none;

    }

    .container {

        padding:
            22px 15px;

    }

    .page-header {

        align-items:
            flex-start;

        flex-direction:
            column;

    }

    .new-booking {

        width:
            100%;

    }

    .stats {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (max-width: 500px) {

    .stats {

        grid-template-columns:
            1fr;

    }

    .status-summary {

        grid-template-columns:
            1fr;

    }

    .page-header h1 {

        font-size:
            25px;

    }

    .nav-link {

        display:
            none;

    }

}

</style>

</head>

<body>

<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar">

```
<div class="logo">

    <?= htmlspecialchars($appName) ?>

</div>


<div class="nav-right">


    <span class="user-name">

        Welcome,
        <?= htmlspecialchars($userName) ?>

    </span>


    <a
        href="../customer/dashboard.php"
        class="nav-link"
    >
        Dashboard
    </a>


    <a
        href="../auth/logout.php"
        class="nav-link logout"
    >
        Logout
    </a>


</div>
```

</nav>

<!-- =========================================================
     CONTENT
========================================================= -->

<div class="container">

```
<!-- PAGE HEADER -->

<div class="page-header">


    <div>


        <h1>

            Booking History

        </h1>


        <p>

            View and manage all your bookings.

        </p>


    </div>


    <a
        href="../customer/book.php"
        class="new-booking"
    >

        + New Booking

    </a>


</div>


<!-- =====================================================
     STATISTICS
====================================================== -->

<div class="stats">


    <div class="stat-card stat-total">

        <div class="stat-label">

            Total Bookings

        </div>

        <div class="stat-number">

            <?= $totalBookings ?>

        </div>

    </div>


    <div class="stat-card stat-pending">

        <div class="stat-label">

            Pending

        </div>

        <div class="stat-number">

            <?= $pendingBookings ?>

        </div>

    </div>


    <div class="stat-card stat-confirmed">

        <div class="stat-label">

            Confirmed

        </div>

        <div class="stat-number">

            <?= $confirmedBookings ?>

        </div>

    </div>


    <div class="stat-card stat-completed">

        <div class="stat-label">

            Completed

        </div>

        <div class="stat-number">

            <?= $completedBookings ?>

        </div>

    </div>


    <div class="stat-card stat-cancelled">

        <div class="stat-label">

            Cancelled

        </div>

        <div class="stat-number">

            <?= $cancelledBookings ?>

        </div>

    </div>


</div>


<!-- =====================================================
     STATUS SUMMARY
====================================================== -->

<div class="status-summary">


    <div class="status-box pending">

        <h3>

            Pending Bookings

        </h3>

        <p>

            <?= $pendingBookings ?>

        </p>

    </div>


    <div class="status-box confirmed">

        <h3>

            Confirmed Bookings

        </h3>

        <p>

            <?= $confirmedBookings ?>

        </p>

    </div>


    <div class="status-box completed">

        <h3>

            Completed Bookings

        </h3>

        <p>

            <?= $completedBookings ?>

        </p>

    </div>


    <div class="status-box cancelled">

        <h3>

            Cancelled Bookings

        </h3>

        <p>

            <?= $cancelledBookings ?>

        </p>

    </div>


</div>


<!-- =====================================================
     BOOKINGS
====================================================== -->

<div class="card">


    <div class="card-header">


        <h2>

            My Bookings

        </h2>


        <span class="booking-count">

            <?= $totalBookings ?>

            <?= $totalBookings == 1
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

                            Booking #

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

                            Price

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


                <?php foreach (
                    $bookings
                    as $booking
                ): ?>


                    <?php

                    $status =
                        strtolower(
                            trim(
                                $booking['status']
                                ?? 'pending'
                            )
                        );


                    $paymentStatus =
                        strtolower(
                            trim(
                                $booking['payment_status']
                                ?? 'unpaid'
                            )
                        );


                    $bookingReference =
                        !empty(
                            $booking[
                                'booking_reference'
                            ]
                        )
                        ? $booking[
                            'booking_reference'
                        ]
                        : '#' .
                          $booking['id'];

                    ?>


                    <tr>


                        <!-- BOOKING NUMBER -->

                        <td>


                            <span
                                class="booking-number"
                            >

                                <?= htmlspecialchars(
                                    $bookingReference
                                ) ?>

                            </span>


                        </td>


                        <!-- SERVICE -->

                        <td>


                            <span
                                class="service-name"
                            >

                                <?= htmlspecialchars(
                                    $booking[
                                        'service_name'
                                    ]
                                    ?? 'Service'
                                ) ?>

                            </span>


                        </td>


                        <!-- DATE -->

                        <td>


                            <span
                                class="date"
                            >


                                <?php

                                $date =
                                    $booking[
                                        'booking_date'
                                    ]
                                    ?? '';


                                if ($date) {

                                    echo htmlspecialchars(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $date
                                            )
                                        )
                                    );

                                } else {

                                    echo '-';

                                }

                                ?>


                            </span>


                        </td>


                        <!-- TIME -->

                        <td>


                            <span
                                class="time"
                            >


                                <?php

                                $time =
                                    $booking[
                                        'booking_time'
                                    ]
                                    ?? '';


                                if ($time) {

                                    echo htmlspecialchars(
                                        date(
                                            'h:i A',
                                            strtotime(
                                                $time
                                            )
                                        )
                                    );

                                } else {

                                    echo '-';

                                }

                                ?>


                            </span>


                        </td>


                        <!-- PRICE -->

                        <td>


                            <span
                                class="price"
                            >


                                <?= htmlspecialchars(
                                    $currency
                                ) ?>


                                <?= number_format(
                                    (float) (
                                        $booking[
                                            'amount'
                                        ]
                                        ?? 0
                                    ),
                                    2
                                ) ?>


                            </span>


                        </td>


                        <!-- PAYMENT -->

                        <td>


                            <span
                                class="payment
                                <?= htmlspecialchars(
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
                                class="status
                                <?= htmlspecialchars(
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

                                View

                            </a>


                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>


            </table>


        </div>


    <?php else: ?>


        <!-- EMPTY STATE -->

        <div class="empty">


            <div class="empty-icon">

                📅

            </div>


            <h2>

                No Bookings Yet

            </h2>


            <p>

                You haven't made any bookings yet.

            </p>


            <a
                href="../customer/book.php"
                class="new-booking"
            >

                Make Your First Booking

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
```

</div>

</body>

</html>
