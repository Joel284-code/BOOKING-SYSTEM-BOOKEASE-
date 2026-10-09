<?php

/*
|--------------------------------------------------------------------------
| BookEase - Payment Failed
|--------------------------------------------------------------------------
| File: payments/failed.php
|
| Purpose:
| - Require customer login
| - Safely receive payment failure information
| - Verify booking belongs to logged-in customer
| - Display booking/payment information
| - Allow customer to retry payment
| - Connect with customer bookings/dashboard
| - Work with the BookEase payments and bookings tables
|--------------------------------------------------------------------------
*/

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| Load Common Files
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
| Helper: Safe Output
|--------------------------------------------------------------------------
*/

function failedEsc($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Require Login
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    empty($_SESSION['user_id'])
) {

    header(
        'Location: ' .
        BASE_URL .
        '/auth/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Logged-In User
|--------------------------------------------------------------------------
*/

$userId = (int) $_SESSION['user_id'];

if ($userId <= 0) {

    header(
        'Location: ' .
        BASE_URL .
        '/auth/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Booking ID
|--------------------------------------------------------------------------
*/

$bookingId = (int) (
    $_GET['booking_id']
    ?? $_GET['id']
    ?? 0
);


/*
|--------------------------------------------------------------------------
| Get Error Message
|--------------------------------------------------------------------------
*/

$message = trim(
    (string) (
        $_GET['message']
        ?? ''
    )
);


if ($message === '') {

    $message =
        'The payment could not be completed. Please check your payment details and try again.';
}


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$booking = null;

$error = '';

$payment = null;


/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

if ($bookingId > 0) {

    try {

        $database = new Database();

        $conn = $database->getConnection();

        if (!$conn instanceof PDO) {

            throw new RuntimeException(
                'Database connection was not created correctly.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Load Booking
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | The booking is restricted to the logged-in customer.
        |
        */

        $bookingStmt = $conn->prepare("
            SELECT

                b.id,
                b.user_id,
                b.service_id,

                b.booking_reference,

                b.booking_date,
                b.booking_time,

                b.customer_name,
                b.customer_email,
                b.customer_phone,

                b.amount,
                b.total_amount,

                b.status,
                b.payment_status,

                b.created_at,
                b.updated_at,

                s.name AS service_name,
                s.duration AS service_duration,

                u.name AS account_name,
                u.email AS account_email,
                u.phone AS account_phone

            FROM bookings b

            INNER JOIN users u
                ON b.user_id = u.id

            INNER JOIN services s
                ON b.service_id = s.id

            WHERE b.id = ?
              AND b.user_id = ?

            LIMIT 1
        ");

        $bookingStmt->execute([
            $bookingId,
            $userId
        ]);

        $booking = $bookingStmt->fetch(
            PDO::FETCH_ASSOC
        );


        /*
        |--------------------------------------------------------------------------
        | Load Latest Payment
        |--------------------------------------------------------------------------
        |
        | Uses the exact database column:
        | payment_method
        |
        */

        if ($booking) {

            $paymentStmt = $conn->prepare("
                SELECT

                    id,
                    booking_id,
                    user_id,
                    amount,
                    payment_method,
                    transaction_reference,
                    transaction_id,
                    phone,
                    status,
                    paid_at,
                    created_at,
                    updated_at

                FROM payments

                WHERE booking_id = ?
                  AND user_id = ?

                ORDER BY id DESC

                LIMIT 1
            ");

            $paymentStmt->execute([
                $bookingId,
                $userId
            ]);

            $payment = $paymentStmt->fetch(
                PDO::FETCH_ASSOC
            );
        }

    } catch (Throwable $e) {

        if (
            defined('DEVELOPMENT_MODE') &&
            DEVELOPMENT_MODE === true
        ) {

            $error =
                'Unable to load payment information: ' .
                $e->getMessage();

        } else {

            $error =
                'Unable to load payment information.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Booking Information
|--------------------------------------------------------------------------
*/

$bookingReference = '';

$serviceName = '';

$bookingDate = '';

$bookingTime = '';

$totalAmount = 0.00;

$bookingStatus = '';

$paymentStatus = '';


if ($booking) {

    $bookingReference =
        !empty($booking['booking_reference'])
            ? $booking['booking_reference']
            : 'BK-' .
              str_pad(
                  (string) $bookingId,
                  6,
                  '0',
                  STR_PAD_LEFT
              );


    $serviceName =
        $booking['service_name']
        ?? 'Service';


    $bookingDate =
        $booking['booking_date']
        ?? '';


    $bookingTime =
        $booking['booking_time']
        ?? '';


    $totalAmount =
        (float) (
            $booking['total_amount']
            ?? $booking['amount']
            ?? 0
        );


    $bookingStatus =
        strtolower(
            trim(
                (string) (
                    $booking['status']
                    ?? ''
                )
            )
        );


    $paymentStatus =
        strtolower(
            trim(
                (string) (
                    $booking['payment_status']
                    ?? 'unpaid'
                )
            )
        );
}


/*
|--------------------------------------------------------------------------
| Currency
|--------------------------------------------------------------------------
*/

$currency =
    defined('CURRENCY')
        ? CURRENCY
        : 'KSh';


/*
|--------------------------------------------------------------------------
| Customer Name
|--------------------------------------------------------------------------
*/

$customerName =
    $booking['customer_name']
    ?? $booking['account_name']
    ?? 'Customer';


/*
|--------------------------------------------------------------------------
| Payment Method
|--------------------------------------------------------------------------
*/

$paymentMethod =
    $payment['payment_method']
    ?? '';


/*
|--------------------------------------------------------------------------
| Transaction Reference
|--------------------------------------------------------------------------
*/

$transactionReference =
    $payment['transaction_reference']
    ?? '';


/*
|--------------------------------------------------------------------------
| Payment ID
|--------------------------------------------------------------------------
*/

$paymentId =
    (int) (
        $payment['id']
        ?? 0
    );


/*
|--------------------------------------------------------------------------
| Determine Retry Availability
|--------------------------------------------------------------------------
*/

$canRetry = false;

if ($booking) {

    if (
        !in_array(
            $bookingStatus,
            [
                'cancelled',
                'rejected',
                'canceled'
            ],
            true
        )
    ) {

        if ($paymentStatus !== 'paid') {

            $canRetry = true;
        }
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
    Payment Failed | <?= failedEsc(APP_NAME) ?>
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

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 20px;
}


/*
|--------------------------------------------------------------------------
| Container
|--------------------------------------------------------------------------
*/

.container {

    width: 100%;

    max-width: 650px;

    margin: 40px auto;
}


/*
|--------------------------------------------------------------------------
| Card
|--------------------------------------------------------------------------
*/

.card {

    background: #ffffff;

    padding: 40px;

    border-radius: 16px;

    text-align: center;

    box-shadow:
        0 8px 30px
        rgba(0, 0, 0, .08);
}


/*
|--------------------------------------------------------------------------
| Logo
|--------------------------------------------------------------------------
*/

.logo {

    font-size: 25px;

    font-weight: 700;

    color: #0f172a;

    margin-bottom: 25px;
}


.logo span {

    color: #2563eb;
}


/*
|--------------------------------------------------------------------------
| Error Icon
|--------------------------------------------------------------------------
*/

.icon {

    width: 78px;

    height: 78px;

    border-radius: 50%;

    background: #fee2e2;

    color: #dc2626;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 42px;

    font-weight: bold;

    margin:
        0 auto 20px;
}


/*
|--------------------------------------------------------------------------
| Heading
|--------------------------------------------------------------------------
*/

h1 {

    margin:
        0 0 12px;

    color: #991b1b;

    font-size: 29px;
}


/*
|--------------------------------------------------------------------------
| Message
|--------------------------------------------------------------------------
*/

.message {

    color: #64748b;

    line-height: 1.6;

    margin:
        0 0 20px;
}


/*
|--------------------------------------------------------------------------
| Error Box
|--------------------------------------------------------------------------
*/

.error {

    background: #fee2e2;

    color: #991b1b;

    padding: 15px;

    border-radius: 9px;

    margin:
        20px 0;

    line-height: 1.5;

    font-size: 14px;

    border:
        1px solid #fecaca;

    word-break: break-word;
}


/*
|--------------------------------------------------------------------------
| Booking Summary
|--------------------------------------------------------------------------
*/

.booking-summary {

    text-align: left;

    background: #f8fafc;

    border:
        1px solid #e2e8f0;

    border-radius: 10px;

    padding: 18px;

    margin:
        25px 0;
}


.summary-title {

    font-size: 16px;

    font-weight: bold;

    color: #0f172a;

    margin-bottom: 10px;
}


.summary-row {

    display: flex;

    justify-content: space-between;

    gap: 15px;

    padding: 10px 0;

    border-bottom:
        1px solid #e2e8f0;
}


.summary-row:last-child {

    border-bottom: none;
}


.summary-label {

    color: #64748b;
}


.summary-value {

    font-weight: 600;

    text-align: right;

    word-break: break-word;
}


/*
|--------------------------------------------------------------------------
| Amount
|--------------------------------------------------------------------------
*/

.amount {

    font-size: 21px;

    color: #2563eb;
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

.status {

    display: inline-block;

    padding:
        5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;

    text-transform: capitalize;
}


.status-unpaid {

    background: #fee2e2;

    color: #991b1b;
}


.status-pending {

    background: #fef3c7;

    color: #92400e;
}


.status-failed {

    background: #fee2e2;

    color: #991b1b;
}


.status-paid {

    background: #dcfce7;

    color: #166534;
}


.status-cancelled {

    background: #fee2e2;

    color: #991b1b;
}


.status-default {

    background: #e2e8f0;

    color: #334155;
}


/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

.actions {

    margin-top: 25px;
}


.btn {

    display: block;

    width: 100%;

    padding: 13px 18px;

    border-radius: 8px;

    text-decoration: none;

    margin-top: 12px;

    font-size: 14px;

    font-weight: 600;

    transition:
        background .2s,
        transform .1s;
}


.btn:hover {

    transform: translateY(-1px);
}


.btn-primary {

    background: #16a34a;

    color: #ffffff;
}


.btn-primary:hover {

    background: #15803d;
}


.btn-secondary {

    background: #2563eb;

    color: #ffffff;
}


.btn-secondary:hover {

    background: #1d4ed8;
}


.btn-light {

    background: #e2e8f0;

    color: #1e293b;
}


.btn-light:hover {

    background: #cbd5e1;
}


.btn-dark {

    background: #0f172a;

    color: #ffffff;
}


.btn-dark:hover {

    background: #1e293b;
}


/*
|--------------------------------------------------------------------------
| Info
|--------------------------------------------------------------------------
*/

.info {

    margin-top: 20px;

    padding: 13px;

    background: #f8fafc;

    border-radius: 8px;

    color: #64748b;

    font-size: 13px;

    line-height: 1.5;
}


/*
|--------------------------------------------------------------------------
| Error Loading Booking
|--------------------------------------------------------------------------
*/

.load-error {

    margin:
        20px 0;

    padding: 14px;

    border-radius: 8px;

    background: #fff7ed;

    color: #9a3412;

    border:
        1px solid #fed7aa;

    font-size: 14px;

    line-height: 1.5;
}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 600px) {

    body {

        padding: 15px;
    }


    .container {

        margin:
            20px auto;
    }


    .card {

        padding:
            30px 20px;
    }


    h1 {

        font-size: 25px;
    }


    .summary-row {

        flex-direction: column;

        gap: 4px;
    }


    .summary-value {

        text-align: left;
    }

}

</style>

</head>


<body>


<div class="container">


    <div class="card">


        <!-- LOGO -->

        <div class="logo">

            Book<span>Ease</span>

        </div>


        <!-- ERROR ICON -->

        <div class="icon">

            ×

        </div>


        <!-- TITLE -->

        <h1>

            Payment Failed

        </h1>


        <!-- DESCRIPTION -->

        <p class="message">

            We were unable to complete your payment.

            Please check your payment details
            and try again.

        </p>


        <!-- ERROR MESSAGE -->

        <div class="error">

            <?= failedEsc($message) ?>

        </div>


        <?php if ($error !== ''): ?>

            <div class="load-error">

                <?= failedEsc($error) ?>

            </div>

        <?php endif; ?>


        <?php if ($booking): ?>


            <!-- BOOKING SUMMARY -->

            <div class="booking-summary">


                <div class="summary-title">

                    Booking Information

                </div>


                <div class="summary-row">

                    <span class="summary-label">

                        Booking Reference

                    </span>

                    <span class="summary-value">

                        <?= failedEsc(
                            $bookingReference
                        ) ?>

                    </span>

                </div>


                <div class="summary-row">

                    <span class="summary-label">

                        Customer

                    </span>

                    <span class="summary-value">

                        <?= failedEsc(
                            $customerName
                        ) ?>

                    </span>

                </div>


                <div class="summary-row">

                    <span class="summary-label">

                        Service

                    </span>

                    <span class="summary-value">

                        <?= failedEsc(
                            $serviceName
                        ) ?>

                    </span>

                </div>


                <div class="summary-row">

                    <span class="summary-label">

                        Booking Date

                    </span>

                    <span class="summary-value">

                        <?= failedEsc(
                            $bookingDate
                        ) ?>

                    </span>

                </div>


                <div class="summary-row">

                    <span class="summary-label">

                        Booking Time

                    </span>

                    <span class="summary-value">

                        <?= failedEsc(
                            $bookingTime
                        ) ?>

                    </span>

                </div>


                <div class="summary-row">

                    <span class="summary-label">

                        Amount

                    </span>

                    <span
                        class="summary-value amount"
                    >

                        <?= failedEsc(
                            $currency
                        ) ?>

                        <?= number_format(
                            $totalAmount,
                            2
                        ) ?>

                    </span>

                </div>


                <div class="summary-row">

                    <span class="summary-label">

                        Booking Status

                    </span>

                    <span class="summary-value">

                        <span
                            class="status status-<?= failedEsc(
                                $bookingStatus ?: 'default'
                            ) ?>"
                        >

                            <?= failedEsc(
                                ucfirst(
                                    $bookingStatus ?: 'Unknown'
                                )
                            ) ?>

                        </span>

                    </span>

                </div>


                <div class="summary-row">

                    <span class="summary-label">

                        Payment Status

                    </span>

                    <span class="summary-value">

                        <span
                            class="status status-<?= failedEsc(
                                $paymentStatus ?: 'default'
                            ) ?>"
                        >

                            <?= failedEsc(
                                ucfirst(
                                    $paymentStatus ?: 'Unknown'
                                )
                            ) ?>

                        </span>

                    </span>

                </div>


                <?php if ($paymentId > 0): ?>

                    <div class="summary-row">

                        <span class="summary-label">

                            Payment ID

                        </span>

                        <span class="summary-value">

                            #<?= $paymentId ?>

                        </span>

                    </div>

                <?php endif; ?>


                <?php if ($paymentMethod !== ''): ?>

                    <div class="summary-row">

                        <span class="summary-label">

                            Payment Method

                        </span>

                        <span class="summary-value">

                            <?= failedEsc(
                                ucfirst(
                                    $paymentMethod
                                )
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <?php if ($transactionReference !== ''): ?>

                    <div class="summary-row">

                        <span class="summary-label">

                            Transaction Reference

                        </span>

                        <span class="summary-value">

                            <?= failedEsc(
                                $transactionReference
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>


            </div>


        <?php endif; ?>


        <!-- ACTIONS -->

        <div class="actions">


            <?php if ($canRetry): ?>

                <a
                    href="<?= failedEsc(BASE_URL) ?>/payments/checkout.php?booking_id=<?= $bookingId ?>"
                    class="btn btn-primary"
                >

                    Try Payment Again

                </a>

            <?php endif; ?>


            <?php if ($bookingId > 0): ?>

                <a
                    href="<?= failedEsc(BASE_URL) ?>/customer/booking-details.php?id=<?= $bookingId ?>"
                    class="btn btn-secondary"
                >

                    View Booking

                </a>

            <?php endif; ?>


            <a
                href="<?= failedEsc(BASE_URL) ?>/customer/my-bookings.php"
                class="btn btn-light"
            >

                My Bookings

            </a>


            <a
                href="<?= failedEsc(BASE_URL) ?>/customer/dashboard.php"
                class="btn btn-dark"
            >

                Customer Dashboard

            </a>


        </div>


        <!-- INFO -->

        <div class="info">

            Your booking remains in your BookEase account.

            If the payment failed, you can return to the
            checkout page and try again.

        </div>


    </div>


</div>


</body>

</html>