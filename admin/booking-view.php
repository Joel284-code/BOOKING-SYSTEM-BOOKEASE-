<?php

/*
|--------------------------------------------------------------------------
| BookEase - Admin Booking View
| File: admin/booking-view.php
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';


/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {

    header(
        'Location: ' . BASE_URL . '/auth/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| ROLE CHECK
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
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

try {

    $database = new Database();

    $conn = $database->getConnection();

    if (!$conn instanceof PDO) {

        throw new Exception(
            'Database connection was not created correctly.'
        );
    }

} catch (Throwable $e) {

    die(
        '<div style="
            font-family:Arial;
            padding:30px;
            color:#991b1b;
            background:#fee2e2;
            margin:30px;
            border-radius:10px;
        ">
            <strong>Database connection error:</strong><br><br>' .
            htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            ) .
        '</div>'
    );
}


/*
|--------------------------------------------------------------------------
| BOOKING ID
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


if ($id <= 0) {

    header(
        'Location: ' .
        BASE_URL .
        '/admin/bookings.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$message = '';
$error = '';

$booking = null;


/*
|--------------------------------------------------------------------------
| CHECK WHETHER NOTIFICATIONS TABLE EXISTS
|--------------------------------------------------------------------------
*/

$notificationsTableExists = false;

try {

    $tableCheck = $conn->query("
        SHOW TABLES LIKE 'notifications'
    ");

    $notificationsTableExists =
        $tableCheck->rowCount() > 0;

} catch (Throwable $e) {

    $notificationsTableExists = false;
}


/*
|--------------------------------------------------------------------------
| UPDATE BOOKING STATUS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $newStatus = strtolower(
        trim(
            $_POST['status'] ?? ''
        )
    );


    $allowedStatuses = [
        'pending',
        'confirmed',
        'completed',
        'cancelled'
    ];


    if (!in_array(
        $newStatus,
        $allowedStatuses,
        true
    )) {

        $error =
            'Invalid booking status selected.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Get Existing Booking
            |--------------------------------------------------------------------------
            */

            $checkStmt = $conn->prepare("
                SELECT
                    id,
                    user_id,
                    booking_reference,
                    status,
                    payment_status
                FROM bookings
                WHERE id = ?
                LIMIT 1
            ");

            $checkStmt->execute([
                $id
            ]);

            $existingBooking =
                $checkStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$existingBooking) {

                $error =
                    'The booking could not be found.';

            } else {

                $oldStatus = strtolower(
                    trim(
                        $existingBooking['status']
                        ?? ''
                    )
                );


                /*
                |--------------------------------------------------------------------------
                | START TRANSACTION
                |--------------------------------------------------------------------------
                */

                $conn->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | UPDATE STATUS
                |--------------------------------------------------------------------------
                */

                if ($newStatus === 'confirmed') {

                    $updateSql = "
                        UPDATE bookings
                        SET
                            status = ?,
                            confirmed_at = NOW(),
                            cancelled_at = NULL
                        WHERE id = ?
                    ";

                } elseif ($newStatus === 'completed') {

                    $updateSql = "
                        UPDATE bookings
                        SET
                            status = ?,
                            completed_at = NOW()
                        WHERE id = ?
                    ";

                } elseif ($newStatus === 'cancelled') {

                    $updateSql = "
                        UPDATE bookings
                        SET
                            status = ?,
                            cancelled_at = NOW()
                        WHERE id = ?
                    ";

                } else {

                    $updateSql = "
                        UPDATE bookings
                        SET
                            status = ?,
                            cancelled_at =
                                CASE
                                    WHEN ? = 'pending'
                                    THEN NULL
                                    ELSE cancelled_at
                                END
                        WHERE id = ?
                    ";
                }


                if ($newStatus === 'confirmed') {

                    $updateStmt =
                        $conn->prepare(
                            $updateSql
                        );

                    $updateStmt->execute([
                        $newStatus,
                        $id
                    ]);

                } elseif ($newStatus === 'completed') {

                    $updateStmt =
                        $conn->prepare(
                            $updateSql
                        );

                    $updateStmt->execute([
                        $newStatus,
                        $id
                    ]);

                } elseif ($newStatus === 'cancelled') {

                    $updateStmt =
                        $conn->prepare(
                            $updateSql
                        );

                    $updateStmt->execute([
                        $newStatus,
                        $id
                    ]);

                } else {

                    $updateStmt =
                        $conn->prepare(
                            $updateSql
                        );

                    $updateStmt->execute([
                        $newStatus,
                        $newStatus,
                        $id
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | CUSTOMER ID
                |--------------------------------------------------------------------------
                */

                $customerId =
                    (int) (
                        $existingBooking['user_id']
                        ?? 0
                    );


                /*
                |--------------------------------------------------------------------------
                | NOTIFICATION MESSAGE
                |--------------------------------------------------------------------------
                */

                switch ($newStatus) {

                    case 'confirmed':

                        $notificationTitle =
                            'Booking Confirmed';

                        $notificationMessage =
                            'Your booking ' .
                            (
                                $existingBooking[
                                    'booking_reference'
                                ]
                                ?? '#' . $id
                            ) .
                            ' has been confirmed.';

                        break;


                    case 'completed':

                        $notificationTitle =
                            'Booking Completed';

                        $notificationMessage =
                            'Your booking ' .
                            (
                                $existingBooking[
                                    'booking_reference'
                                ]
                                ?? '#' . $id
                            ) .
                            ' has been marked as completed.';

                        break;


                    case 'cancelled':

                        $notificationTitle =
                            'Booking Cancelled';

                        $notificationMessage =
                            'Your booking ' .
                            (
                                $existingBooking[
                                    'booking_reference'
                                ]
                                ?? '#' . $id
                            ) .
                            ' has been cancelled by the administrator.';

                        break;


                    case 'pending':

                        $notificationTitle =
                            'Booking Pending';

                        $notificationMessage =
                            'Your booking ' .
                            (
                                $existingBooking[
                                    'booking_reference'
                                ]
                                ?? '#' . $id
                            ) .
                            ' is currently pending confirmation.';

                        break;


                    default:

                        $notificationTitle =
                            'Booking Updated';

                        $notificationMessage =
                            'Your booking status has been updated.';
                }


                /*
                |--------------------------------------------------------------------------
                | CREATE CUSTOMER NOTIFICATION
                |--------------------------------------------------------------------------
                */

                if (
                    $customerId > 0 &&
                    $notificationsTableExists
                ) {

                    /*
                    | Try the full notification structure first.
                    */

                    try {

                        $notificationStmt =
                            $conn->prepare("
                                INSERT INTO notifications
                                (
                                    user_id,
                                    booking_id,
                                    title,
                                    message,
                                    type,
                                    is_read,
                                    created_at
                                )
                                VALUES
                                (
                                    ?,
                                    ?,
                                    ?,
                                    ?,
                                    ?,
                                    0,
                                    NOW()
                                )
                            ");

                        $notificationStmt->execute([
                            $customerId,
                            $id,
                            $notificationTitle,
                            $notificationMessage,
                            'booking'
                        ]);

                    } catch (PDOException $notificationError) {

                        /*
                        |--------------------------------------------------------------------------
                        | Fallback for databases without some notification columns
                        |--------------------------------------------------------------------------
                        */

                        try {

                            $fallbackStmt =
                                $conn->prepare("
                                    INSERT INTO notifications
                                    (
                                        user_id,
                                        title,
                                        message,
                                        is_read
                                    )
                                    VALUES
                                    (
                                        ?,
                                        ?,
                                        ?,
                                        0
                                    )
                                ");

                            $fallbackStmt->execute([
                                $customerId,
                                $notificationTitle,
                                $notificationMessage
                            ]);

                        } catch (PDOException $fallbackError) {

                            /*
                            | Do not stop the booking update just because
                            | notification insertion failed.
                            */

                        }
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | COMMIT
                |--------------------------------------------------------------------------
                */

                $conn->commit();


                /*
                |--------------------------------------------------------------------------
                | SUCCESS MESSAGE
                |--------------------------------------------------------------------------
                */

                if ($oldStatus === $newStatus) {

                    $message =
                        'Booking is already ' .
                        ucfirst($newStatus) .
                        '.';

                } else {

                    $message =
                        'Booking status updated to ' .
                        ucfirst($newStatus) .
                        ' successfully.';
                }
            }

        } catch (Throwable $e) {

            if (
                $conn instanceof PDO &&
                $conn->inTransaction()
            ) {

                $conn->rollBack();
            }


            $error =
                'Unable to update booking. ' .
                htmlspecialchars(
                    $e->getMessage(),
                    ENT_QUOTES,
                    'UTF-8'
                );
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOAD BOOKING
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->prepare("
        SELECT

            b.id,
            b.user_id,
            b.service_id,
            b.staff_id,

            b.booking_reference,

            b.booking_date,
            b.booking_time,

            b.customer_name,
            b.customer_email,
            b.customer_phone,

            b.notes,

            b.amount,
            b.total_amount,

            b.status,
            b.payment_status,

            b.cancellation_reason,

            b.cancelled_at,
            b.confirmed_at,
            b.completed_at,

            b.created_at,
            b.updated_at,

            u.name AS account_name,
            u.email AS account_email,
            u.phone AS account_phone,

            s.name AS service_name,
            s.price AS service_price,
            s.duration AS service_duration

        FROM bookings b

        LEFT JOIN users u
            ON b.user_id = u.id

        LEFT JOIN services s
            ON b.service_id = s.id

        WHERE b.id = ?

        LIMIT 1
    ");


    $stmt->execute([
        $id
    ]);


    $booking =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $e) {

    die(
        'Unable to load booking: ' .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


/*
|--------------------------------------------------------------------------
| BOOKING NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$booking) {

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
            Booking Not Found | BookEase
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
            }

            .box {
                max-width: 550px;
                margin: 100px auto;
                background: white;
                padding: 40px;
                border-radius: 18px;
                text-align: center;
                box-shadow:
                    0 15px 40px
                    rgba(15,23,42,.08);
            }

            .icon {
                width: 70px;
                height: 70px;
                margin: 0 auto 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #fee2e2;
                color: #dc2626;
                border-radius: 50%;
                font-size: 30px;
            }

            h2 {
                color: #0f172a;
            }

            p {
                color: #64748b;
            }

            .btn {
                display: inline-block;
                margin-top: 15px;
                padding: 12px 20px;
                background: #2563eb;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
            }

        </style>

    </head>

    <body>

        <div class="box">

            <div class="icon">
                !
            </div>

            <h2>
                Booking Not Found
            </h2>

            <p>
                The booking you are looking for does not exist.
            </p>

            <a
                href="<?= htmlspecialchars(
                    BASE_URL .
                    '/admin/bookings.php',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="btn"
            >
                Back to Bookings
            </a>

        </div>

    </body>

    </html>

    <?php

    exit;
}


/*
|--------------------------------------------------------------------------
| VALUES
|--------------------------------------------------------------------------
*/

$customerName =
    trim(
        $booking['customer_name']
        ?? ''
    );

if ($customerName === '') {

    $customerName =
        $booking['account_name']
        ?? 'Unknown Customer';
}


$customerEmail =
    trim(
        $booking['customer_email']
        ?? ''
    );

if ($customerEmail === '') {

    $customerEmail =
        $booking['account_email']
        ?? '';
}


$customerPhone =
    trim(
        $booking['customer_phone']
        ?? ''
    );

if ($customerPhone === '') {

    $customerPhone =
        $booking['account_phone']
        ?? '';
}


$serviceName =
    $booking['service_name']
    ?? 'Unknown Service';


$servicePrice =
    $booking['service_price']
    ?? $booking['amount']
    ?? $booking['total_amount']
    ?? 0;


$duration =
    $booking['service_duration']
    ?? '';


$amount =
    $booking['amount']
    ?? 0;


$totalAmount =
    $booking['total_amount']
    ?? $amount;


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


$bookingDate =
    $booking['booking_date']
    ?? '';


$bookingTime =
    $booking['booking_time']
    ?? '';


$notes =
    $booking['notes']
    ?? '';


$reference =
    $booking['booking_reference']
    ?? '';


/*
|--------------------------------------------------------------------------
| STATUS CLASS
|--------------------------------------------------------------------------
*/

$validStatuses = [
    'pending',
    'confirmed',
    'completed',
    'cancelled'
];

$statusClass =
    in_array(
        $status,
        $validStatuses,
        true
    )
    ? $status
    : 'pending';


/*
|--------------------------------------------------------------------------
| PAYMENT CLASS
|--------------------------------------------------------------------------
*/

$validPaymentStatuses = [
    'unpaid',
    'pending',
    'paid',
    'failed',
    'refunded'
];

$paymentClass =
    in_array(
        $paymentStatus,
        $validPaymentStatuses,
        true
    )
    ? $paymentStatus
    : 'unpaid';


/*
|--------------------------------------------------------------------------
| SAFE OUTPUT
|--------------------------------------------------------------------------
*/

function bookingSafe($value)
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
    Booking <?= bookingSafe($reference ?: '#' . $id) ?>
    | BookEase
</title>


<style>

/*
|--------------------------------------------------------------------------
| RESET
|--------------------------------------------------------------------------
*/

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Inter,
        Arial,
        Helvetica,
        sans-serif;

    background:
        #f5f7fb;

    color:
        #0f172a;
}

a {
    text-decoration: none;
}


/*
|--------------------------------------------------------------------------
| CONTENT
|--------------------------------------------------------------------------
*/

.content {

    max-width: 1250px;

    margin: 0 auto;

    padding: 30px 22px 50px;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 25px;
}

.header-left {

    display: flex;

    align-items: center;

    gap: 15px;
}

.header-icon {

    width: 55px;

    height: 55px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 15px;

    background:
        #dbeafe;

    color:
        #2563eb;

    font-size: 25px;
}

.page-header h1 {

    margin: 0;

    font-size: 28px;

    font-weight: 750;
}

.page-header p {

    margin: 5px 0 0;

    color:
        #64748b;

    font-size: 14px;
}

.back-btn {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 11px 16px;

    background: white;

    color: #334155;

    border:
        1px solid #e2e8f0;

    border-radius: 9px;

    font-weight: 600;

    transition: .2s;
}

.back-btn:hover {

    background: #f8fafc;

    transform: translateY(-1px);
}


/*
|--------------------------------------------------------------------------
| ALERTS
|--------------------------------------------------------------------------
*/

.alert {

    padding: 15px 18px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 14px;

    font-weight: 500;
}

.alert-success {

    background:
        #ecfdf5;

    color:
        #047857;

    border:
        1px solid #a7f3d0;
}

.alert-error {

    background:
        #fef2f2;

    color:
        #b91c1c;

    border:
        1px solid #fecaca;
}


/*
|--------------------------------------------------------------------------
| MAIN GRID
|--------------------------------------------------------------------------
*/

.grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 20px;
}


/*
|--------------------------------------------------------------------------
| CARD
|--------------------------------------------------------------------------
*/

.card {

    background: white;

    border:
        1px solid #e5e7eb;

    border-radius: 16px;

    padding: 24px;

    box-shadow:
        0 5px 25px
        rgba(15,23,42,.045);
}

.card h2 {

    margin: 0 0 22px;

    font-size: 18px;

    color:
        #0f172a;
}


/*
|--------------------------------------------------------------------------
| DETAIL
|--------------------------------------------------------------------------
*/

.detail {

    margin-bottom: 19px;
}

.detail:last-child {

    margin-bottom: 0;
}

.detail strong {

    display: block;

    margin-bottom: 6px;

    color:
        #94a3b8;

    font-size: 11px;

    text-transform:
        uppercase;

    letter-spacing:
        .6px;
}

.value {

    color:
        #1e293b;

    font-size: 15px;

    line-height: 1.5;
}


/*
|--------------------------------------------------------------------------
| CUSTOMER
|--------------------------------------------------------------------------
*/

.customer-profile {

    display: flex;

    align-items: center;

    gap: 15px;

    margin-bottom: 22px;

    padding-bottom: 20px;

    border-bottom:
        1px solid #e2e8f0;
}

.avatar {

    width: 55px;

    height: 55px;

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

    font-size: 20px;

    font-weight: 700;
}

.customer-profile h3 {

    margin: 0;

    font-size: 17px;
}

.customer-profile p {

    margin: 4px 0 0;

    color: #64748b;

    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| REFERENCE
|--------------------------------------------------------------------------
*/

.reference {

    display: inline-block;

    padding: 7px 11px;

    background:
        #eff6ff;

    color:
        #1d4ed8;

    border-radius: 7px;

    font-weight: 700;

    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

.status {

    display: inline-flex;

    align-items: center;

    padding: 7px 12px;

    border-radius: 30px;

    font-size: 12px;

    font-weight: 700;

    text-transform:
        capitalize;
}

.status-pending {

    background:
        #fef3c7;

    color:
        #92400e;
}

.status-confirmed {

    background:
        #dbeafe;

    color:
        #1d4ed8;
}

.status-completed {

    background:
        #dcfce7;

    color:
        #166534;
}

.status-cancelled {

    background:
        #fee2e2;

    color:
        #991b1b;
}


/*
|--------------------------------------------------------------------------
| PAYMENT
|--------------------------------------------------------------------------
*/

.payment-unpaid {

    background:
        #fef3c7;

    color:
        #92400e;
}

.payment-pending {

    background:
        #dbeafe;

    color:
        #1d4ed8;
}

.payment-paid {

    background:
        #dcfce7;

    color:
        #166534;
}

.payment-failed {

    background:
        #fee2e2;

    color:
        #991b1b;
}

.payment-refunded {

    background:
        #ede9fe;

    color:
        #6d28d9;
}


/*
|--------------------------------------------------------------------------
| NOTES
|--------------------------------------------------------------------------
*/

.notes {

    padding: 16px;

    background:
        #f8fafc;

    border:
        1px solid #e2e8f0;

    border-radius: 10px;

    color:
        #475569;

    line-height:
        1.7;

    min-height:
        90px;
}


/*
|--------------------------------------------------------------------------
| ACTION CARD
|--------------------------------------------------------------------------
*/

.actions-card {

    margin-top: 20px;
}

.current-status {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    padding: 15px;

    margin-bottom: 20px;

    background:
        #f8fafc;

    border-radius: 10px;
}

.current-status span {

    color:
        #64748b;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.actions {

    display: flex;

    flex-wrap: wrap;

    gap: 10px;
}

.action-form {

    margin: 0;
}

.btn {

    border: none;

    padding: 11px 17px;

    border-radius: 9px;

    color: white;

    font-size: 13px;

    font-weight: 700;

    cursor: pointer;

    transition: .2s;
}

.btn:hover {

    transform:
        translateY(-1px);

    filter:
        brightness(.96);
}

.btn-pending {

    background:
        #f59e0b;
}

.btn-confirm {

    background:
        #16a34a;
}

.btn-complete {

    background:
        #2563eb;
}

.btn-cancel {

    background:
        #dc2626;
}


/*
|--------------------------------------------------------------------------
| INFO
|--------------------------------------------------------------------------
*/

.info-box {

    margin-top: 20px;

    padding: 15px;

    border-radius: 10px;

    background:
        #eff6ff;

    border:
        1px solid #bfdbfe;

    color:
        #475569;

    font-size: 13px;

    line-height: 1.6;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 850px) {

    .grid {

        grid-template-columns: 1fr;
    }

}

@media (max-width: 600px) {

    .content {

        padding:
            20px 14px 40px;
    }

    .page-header {

        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .page-header h1 {

        font-size:
            23px;
    }

    .card {

        padding:
            19px;
    }

    .current-status {

        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .actions {

        flex-direction:
            column;
    }

    .btn {

        width:
            100%;
    }

}

</style>

</head>


<body>


<div class="content">


    <!-- HEADER -->

    <div class="page-header">

        <div class="header-left">

            <div class="header-icon">
                📅
            </div>

            <div>

                <h1>
                    Booking Details
                </h1>

                <p>
                    Manage customer booking and status
                </p>

            </div>

        </div>


        <a
            href="<?= bookingSafe(
                BASE_URL .
                '/admin/bookings.php'
            ) ?>"
            class="back-btn"
        >
            ← Back to Bookings
        </a>

    </div>


    <!-- SUCCESS -->

    <?php if ($message !== ''): ?>

        <div class="alert alert-success">

            ✓

            <?= bookingSafe(
                $message
            ) ?>

        </div>

    <?php endif; ?>


    <!-- ERROR -->

    <?php if ($error !== ''): ?>

        <div class="alert alert-error">

            ⚠

            <?= bookingSafe(
                $error
            ) ?>

        </div>

    <?php endif; ?>


    <!-- MAIN GRID -->

    <div class="grid">


        <!-- CUSTOMER -->

        <div class="card">

            <h2>
                Customer Information
            </h2>


            <div class="customer-profile">

                <div class="avatar">

                    <?= bookingSafe(
                        strtoupper(
                            substr(
                                $customerName,
                                0,
                                1
                            )
                        )
                    ) ?>

                </div>


                <div>

                    <h3>
                        <?= bookingSafe(
                            $customerName
                        ) ?>
                    </h3>

                    <p>
                        Customer ID:
                        #<?= (int) (
                            $booking['user_id']
                            ?? 0
                        ) ?>
                    </p>

                </div>

            </div>


            <div class="detail">

                <strong>
                    Email
                </strong>

                <span class="value">

                    <?= bookingSafe(
                        $customerEmail
                        ?: 'Not provided'
                    ) ?>

                </span>

            </div>


            <div class="detail">

                <strong>
                    Phone
                </strong>

                <span class="value">

                    <?= bookingSafe(
                        $customerPhone
                        ?: 'Not provided'
                    ) ?>

                </span>

            </div>

        </div>


        <!-- BOOKING -->

        <div class="card">

            <h2>
                Booking Information
            </h2>


            <div class="detail">

                <strong>
                    Booking Reference
                </strong>

                <span class="reference">

                    <?= bookingSafe(
                        $reference
                        ?: 'BK-' . $id
                    ) ?>

                </span>

            </div>


            <div class="detail">

                <strong>
                    Service
                </strong>

                <span class="value">

                    <?= bookingSafe(
                        $serviceName
                    ) ?>

                </span>

            </div>


            <div class="detail">

                <strong>
                    Service Price
                </strong>

                <span class="value">

                    KSh
                    <?= number_format(
                        (float)
                        $servicePrice,
                        2
                    ) ?>

                </span>

            </div>


            <div class="detail">

                <strong>
                    Duration
                </strong>

                <span class="value">

                    <?= bookingSafe(
                        $duration
                            ? $duration . ' minutes'
                            : 'Not specified'
                    ) ?>

                </span>

            </div>

        </div>


        <!-- APPOINTMENT -->

        <div class="card">

            <h2>
                Appointment
            </h2>


            <div class="detail">

                <strong>
                    Date
                </strong>

                <span class="value">

                    <?= bookingSafe(
                        $bookingDate
                        ?: 'Not specified'
                    ) ?>

                </span>

            </div>


            <div class="detail">

                <strong>
                    Time
                </strong>

                <span class="value">

                    <?= bookingSafe(
                        $bookingTime
                        ?: 'Not specified'
                    ) ?>

                </span>

            </div>


            <div class="detail">

                <strong>
                    Booking Status
                </strong>

                <span
                    class="status status-<?= bookingSafe(
                        $statusClass
                    ) ?>"
                >

                    <?= bookingSafe(
                        ucfirst(
                            $statusClass
                        )
                    ) ?>

                </span>

            </div>

        </div>


        <!-- PAYMENT -->

        <div class="card">

            <h2>
                Payment Information
            </h2>


            <div class="detail">

                <strong>
                    Amount
                </strong>

                <span class="value">

                    KSh
                    <?= number_format(
                        (float)
                        $amount,
                        2
                    ) ?>

                </span>

            </div>


            <div class="detail">

                <strong>
                    Total Amount
                </strong>

                <span class="value">

                    KSh
                    <?= number_format(
                        (float)
                        $totalAmount,
                        2
                    ) ?>

                </span>

            </div>


            <div class="detail">

                <strong>
                    Payment Status
                </strong>

                <span
                    class="status payment-<?= bookingSafe(
                        $paymentClass
                    ) ?>"
                >

                    <?= bookingSafe(
                        ucfirst(
                            $paymentClass
                        )
                    ) ?>

                </span>

            </div>

        </div>


        <!-- CUSTOMER NOTES -->

        <div class="card">

            <h2>
                Customer Notes
            </h2>


            <div class="notes">

                <?php if (
                    trim($notes) !== ''
                ): ?>

                    <?= nl2br(
                        bookingSafe(
                            $notes
                        )
                    ) ?>

                <?php else: ?>

                    No notes were provided
                    by the customer.

                <?php endif; ?>

            </div>

        </div>


        <!-- BOOKING RECORD -->

        <div class="card">

            <h2>
                Booking Record
            </h2>


            <div class="detail">

                <strong>
                    Created
                </strong>

                <span class="value">

                    <?= bookingSafe(
                        $booking['created_at']
                        ?? '-'
                    ) ?>

                </span>

            </div>


            <div class="detail">

                <strong>
                    Confirmed
                </strong>

                <span class="value">

                    <?= bookingSafe(
                        $booking['confirmed_at']
                        ?? '-'
                    ) ?>

                </span>

            </div>


            <div class="detail">

                <strong>
                    Completed
                </strong>

                <span class="value">

                    <?= bookingSafe(
                        $booking['completed_at']
                        ?? '-'
                    ) ?>

                </span>

            </div>


            <div class="detail">

                <strong>
                    Cancelled
                </strong>

                <span class="value">

                    <?= bookingSafe(
                        $booking['cancelled_at']
                        ?? '-'
                    ) ?>

                </span>

            </div>

        </div>


    </div>


    <!-- STATUS MANAGEMENT -->

    <div class="card actions-card">

        <h2>
            Manage Booking
        </h2>


        <div class="current-status">

            <span>
                Current booking status
            </span>


            <span
                class="status status-<?= bookingSafe(
                    $statusClass
                ) ?>"
            >

                <?= bookingSafe(
                    ucfirst(
                        $statusClass
                    )
                ) ?>

            </span>

        </div>


        <div class="actions">


            <!-- PENDING -->

            <?php if (
                $statusClass !== 'pending'
            ): ?>

                <form
                    method="POST"
                    class="action-form"
                >

                    <input
                        type="hidden"
                        name="status"
                        value="pending"
                    >

                    <button
                        type="submit"
                        class="btn btn-pending"
                    >
                        Set Pending
                    </button>

                </form>

            <?php endif; ?>


            <!-- CONFIRMED -->

            <?php if (
                $statusClass !== 'confirmed'
            ): ?>

                <form
                    method="POST"
                    class="action-form"
                >

                    <input
                        type="hidden"
                        name="status"
                        value="confirmed"
                    >

                    <button
                        type="submit"
                        class="btn btn-confirm"
                    >
                        ✓ Confirm Booking
                    </button>

                </form>

            <?php endif; ?>


            <!-- COMPLETED -->

            <?php if (
                $statusClass !== 'completed'
            ): ?>

                <form
                    method="POST"
                    class="action-form"
                >

                    <input
                        type="hidden"
                        name="status"
                        value="completed"
                    >

                    <button
                        type="submit"
                        class="btn btn-complete"
                    >
                        ✓ Mark Completed
                    </button>

                </form>

            <?php endif; ?>


            <!-- CANCELLED -->

            <?php if (
                $statusClass !== 'cancelled'
            ): ?>

                <form
                    method="POST"
                    class="action-form"
                >

                    <input
                        type="hidden"
                        name="status"
                        value="cancelled"
                    >

                    <button
                        type="submit"
                        class="btn btn-cancel"
                        onclick="
                            return confirm(
                                'Are you sure you want to cancel this booking?'
                            );
                        "
                    >
                        ✕ Cancel Booking
                    </button>

                </form>

            <?php endif; ?>


        </div>


        <div class="info-box">

            <strong>
                Customer communication:
            </strong>

            When the administrator changes the
            booking status, BookEase creates a
            notification for the customer using
            the booking's
            <strong>user_id</strong>.

            The customer's notification page can
            then display the update.

        </div>

    </div>


</div>


</body>

</html>