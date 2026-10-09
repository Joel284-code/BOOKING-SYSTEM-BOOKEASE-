```php
<?php

/*
|--------------------------------------------------------------------------
| BookEase - Payment Checkout
|--------------------------------------------------------------------------
| File: payments/checkout.php
|
| Purpose:
| - Allow logged-in customers to pay for their own bookings
| - Verify booking belongs to logged-in customer
| - Read booking/service/customer information
| - Prevent payment for cancelled/rejected bookings
| - Prevent duplicate payment for already-paid bookings
| - Connect correctly with payments/process.php
| - Use the exact BookEase database structure
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
| Helper: Escape Output
|--------------------------------------------------------------------------
*/

function checkoutEsc($value): string
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

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {

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

    session_unset();

    session_destroy();

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


if ($bookingId <= 0) {

    header(
        'Location: ' .
        BASE_URL .
        '/customer/my-bookings.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

$error = '';

$booking = null;

try {

    $database = new Database();

    $conn = $database->getConnection();

    if (!$conn instanceof PDO) {

        throw new RuntimeException(
            'Database connection was not created correctly.'
        );
    }

} catch (Throwable $e) {

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        $error =
            'Database connection error: ' .
            $e->getMessage();

    } else {

        $error =
            'Unable to connect to the database.';
    }
}


/*
|--------------------------------------------------------------------------
| Load Booking
|--------------------------------------------------------------------------
|
| IMPORTANT:
| The booking is restricted using b.user_id = logged-in user.
| This prevents one customer from paying another customer's booking.
|--------------------------------------------------------------------------
*/

if (
    $error === '' &&
    isset($conn) &&
    $conn instanceof PDO
) {

    try {

        $stmt = $conn->prepare("
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

                b.notes,

                b.created_at,
                b.updated_at,

                s.name AS service_name,
                s.description AS service_description,
                s.price AS service_price,
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

        $stmt->execute([
            $bookingId,
            $userId
        ]);

        $booking = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    } catch (PDOException $e) {

        if (
            defined('DEVELOPMENT_MODE') &&
            DEVELOPMENT_MODE === true
        ) {

            $error =
                'Database error: ' .
                $e->getMessage();

        } else {

            $error =
                'Unable to load the booking.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Display Database Error
|--------------------------------------------------------------------------
*/

if ($error !== '') {

    http_response_code(500);

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
            Payment Error | BookEase
        </title>

        <style>

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                font-family:
                    Arial,
                    Helvetica,
                    sans-serif;
                background: #f1f5f9;
                color: #0f172a;
            }

            .error-card {
                width: 100%;
                max-width: 550px;
                background: #ffffff;
                border-radius: 14px;
                padding: 30px;
                box-shadow:
                    0 10px 30px
                    rgba(0, 0, 0, .08);
                text-align: center;
            }

            .icon {
                width: 60px;
                height: 60px;
                margin: 0 auto 20px;
                border-radius: 50%;
                background: #fee2e2;
                color: #dc2626;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 28px;
                font-weight: bold;
            }

            h1 {
                margin: 0 0 10px;
                font-size: 24px;
            }

            p {
                color: #64748b;
                line-height: 1.6;
            }

            .btn {
                display: inline-block;
                margin-top: 15px;
                padding: 12px 20px;
                background: #2563eb;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
            }

        </style>

    </head>

    <body>

        <div class="error-card">

            <div class="icon">
                !
            </div>

            <h1>
                Payment Error
            </h1>

            <p>
                <?= checkoutEsc($error) ?>
            </p>

            <a
                href="<?= checkoutEsc(BASE_URL) ?>/customer/my-bookings.php"
                class="btn"
            >
                Back to My Bookings
            </a>

        </div>

    </body>

    </html>

    <?php

    exit;
}


/*
|--------------------------------------------------------------------------
| Booking Not Found
|--------------------------------------------------------------------------
*/

if (!$booking) {

    http_response_code(404);

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
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                font-family: Arial, Helvetica, sans-serif;
                background: #f1f5f9;
            }

            .card {
                max-width: 550px;
                width: 100%;
                background: white;
                padding: 35px;
                border-radius: 14px;
                text-align: center;
                box-shadow:
                    0 10px 30px
                    rgba(0, 0, 0, .08);
            }

            h1 {
                margin-top: 0;
                color: #0f172a;
            }

            p {
                color: #64748b;
                line-height: 1.6;
            }

            a {
                display: inline-block;
                margin-top: 15px;
                padding: 12px 20px;
                background: #2563eb;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
            }

        </style>

    </head>

    <body>

        <div class="card">

            <h1>
                Booking Not Found
            </h1>

            <p>
                The booking does not exist or you do not have
                permission to access it.
            </p>

            <a
                href="<?= checkoutEsc(BASE_URL) ?>/customer/my-bookings.php"
            >
                Back to My Bookings
            </a>

        </div>

    </body>

    </html>

    <?php

    exit;
}


/*
|--------------------------------------------------------------------------
| Booking Status
|--------------------------------------------------------------------------
*/

$bookingStatus = strtolower(
    trim(
        (string) (
            $booking['status'] ?? ''
        )
    )
);


/*
|--------------------------------------------------------------------------
| Prevent Payment For Cancelled / Rejected Bookings
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $bookingStatus,
        [
            'cancelled',
            'rejected',
            'canceled'
        ],
        true
    )
) {

    http_response_code(400);

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
            Payment Unavailable | BookEase
        </title>

        <style>

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                font-family: Arial, Helvetica, sans-serif;
                background: #f1f5f9;
            }

            .card {
                max-width: 550px;
                width: 100%;
                background: white;
                padding: 35px;
                border-radius: 14px;
                text-align: center;
                box-shadow:
                    0 10px 30px
                    rgba(0, 0, 0, .08);
            }

            .icon {
                width: 60px;
                height: 60px;
                margin: 0 auto 20px;
                border-radius: 50%;
                background: #fef3c7;
                color: #d97706;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 26px;
                font-weight: bold;
            }

            h1 {
                margin-top: 0;
            }

            p {
                color: #64748b;
                line-height: 1.6;
            }

            a {
                display: inline-block;
                margin-top: 15px;
                padding: 12px 20px;
                background: #2563eb;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: bold;
            }

        </style>

    </head>

    <body>

        <div class="card">

            <div class="icon">
                !
            </div>

            <h1>
                Payment Unavailable
            </h1>

            <p>
                Payment cannot be made because this booking has been
                <?= checkoutEsc(ucfirst($bookingStatus)) ?>.
            </p>

            <a
                href="<?= checkoutEsc(BASE_URL) ?>/customer/my-bookings.php"
            >
                Back to My Bookings
            </a>

        </div>

    </body>

    </html>

    <?php

    exit;
}


/*
|--------------------------------------------------------------------------
| Payment Status
|--------------------------------------------------------------------------
*/

$paymentStatus = strtolower(
    trim(
        (string) (
            $booking['payment_status'] ?? 'unpaid'
        )
    )
);


/*
|--------------------------------------------------------------------------
| Already Paid
|--------------------------------------------------------------------------
*/

if ($paymentStatus === 'paid') {

    header(
        'Location: ' .
        BASE_URL .
        '/payments/success.php?booking_id=' .
        $bookingId
    );

    exit;
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
| Customer Details
|--------------------------------------------------------------------------
*/

$customerName =
    $booking['customer_name']
    ?: $booking['account_name']
    ?: 'Customer';


$customerEmail =
    $booking['customer_email']
    ?: $booking['account_email']
    ?: '';


$customerPhone =
    $booking['customer_phone']
    ?: $booking['account_phone']
    ?: ($_SESSION['phone'] ?? '');


/*
|--------------------------------------------------------------------------
| Payment Amount
|--------------------------------------------------------------------------
*/

$totalAmount = (float) (
    $booking['total_amount']
    ?? $booking['amount']
    ?? 0
);


/*
|--------------------------------------------------------------------------
| Generate Booking Reference Fallback
|--------------------------------------------------------------------------
*/

$bookingReference =
    !empty($booking['booking_reference'])
        ? $booking['booking_reference']
        : 'BK-' . str_pad(
            (string) $bookingId,
            6,
            '0',
            STR_PAD_LEFT
        );

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
    Payment Checkout | <?= checkoutEsc(APP_NAME) ?>
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

    color: #0f172a;
}


/*
|--------------------------------------------------------------------------
| NAVBAR
|--------------------------------------------------------------------------
*/

.navbar {

    background: #0f172a;

    color: white;

    padding: 18px 30px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;
}


.logo {

    font-size: 24px;

    font-weight: bold;
}


.logo span {

    color: #3b82f6;
}


.nav-links {

    display: flex;

    align-items: center;

    gap: 18px;
}


.nav-links a {

    color: #cbd5e1;

    text-decoration: none;

    font-size: 14px;

    font-weight: 600;
}


.nav-links a:hover {

    color: white;
}


/*
|--------------------------------------------------------------------------
| CONTAINER
|--------------------------------------------------------------------------
*/

.container {

    width: 92%;

    max-width: 850px;

    margin: 40px auto;
}


/*
|--------------------------------------------------------------------------
| CARD
|--------------------------------------------------------------------------
*/

.card {

    background: white;

    border-radius: 14px;

    padding: 30px;

    box-shadow:
        0 5px 20px
        rgba(0, 0, 0, .06);
}


h1 {

    margin:
        0 0 8px;

    font-size: 28px;
}


.subtitle {

    color: #64748b;

    margin:
        0 0 25px;
}


/*
|--------------------------------------------------------------------------
| NOTICE
|--------------------------------------------------------------------------
*/

.notice {

    background: #eff6ff;

    color: #1e40af;

    padding: 18px;

    border-radius: 10px;

    margin-bottom: 25px;

    line-height: 1.5;
}


.notice strong {

    display: block;

    margin-top: 5px;

    font-size: 25px;
}


/*
|--------------------------------------------------------------------------
| BOOKING BOX
|--------------------------------------------------------------------------
*/

.booking-box {

    background: #f8fafc;

    padding: 20px;

    border-radius: 10px;

    margin-bottom: 25px;
}


.row {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 20px;

    padding: 13px 0;

    border-bottom:
        1px solid #e2e8f0;
}


.row:last-child {

    border-bottom: none;
}


.row span {

    color: #64748b;
}


.row strong {

    text-align: right;
}


.total {

    font-size: 25px;

    color: #2563eb;
}


/*
|--------------------------------------------------------------------------
| PAYMENT STATUS
|--------------------------------------------------------------------------
*/

.payment-status {

    display: inline-block;

    padding: 6px 12px;

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


/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

.form-group {

    margin-bottom: 20px;
}


label {

    display: block;

    font-weight: bold;

    margin-bottom: 8px;
}


input,
select {

    width: 100%;

    padding: 13px;

    border:
        1px solid #cbd5e1;

    border-radius: 7px;

    font-size: 15px;

    background: white;
}


input:focus,
select:focus {

    outline: none;

    border-color: #2563eb;

    box-shadow:
        0 0 0 3px
        rgba(37, 99, 235, .10);
}


/*
|--------------------------------------------------------------------------
| PAYMENT METHOD HELP
|--------------------------------------------------------------------------
*/

.method-help {

    margin-top: 7px;

    color: #64748b;

    font-size: 13px;

    line-height: 1.5;
}


/*
|--------------------------------------------------------------------------
| BUTTON
|--------------------------------------------------------------------------
*/

.btn {

    width: 100%;

    padding: 14px;

    border: none;

    border-radius: 8px;

    background: #16a34a;

    color: white;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;

    transition:
        background .2s ease,
        transform .1s ease;
}


.btn:hover {

    background: #15803d;
}


.btn:active {

    transform: translateY(1px);
}


/*
|--------------------------------------------------------------------------
| BACK
|--------------------------------------------------------------------------
*/

.back {

    display: inline-block;

    margin-top: 18px;

    color: #2563eb;

    text-decoration: none;

    font-size: 14px;

    font-weight: 600;
}


.back:hover {

    text-decoration: underline;
}


/*
|--------------------------------------------------------------------------
| SECURITY NOTE
|--------------------------------------------------------------------------
*/

.security-note {

    margin-top: 22px;

    padding: 14px;

    background: #f8fafc;

    border-radius: 8px;

    color: #64748b;

    font-size: 13px;

    line-height: 1.5;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 600px) {

    .navbar {

        padding: 16px 20px;

        flex-direction: column;

        align-items: flex-start;
    }


    .nav-links {

        width: 100%;

        justify-content: space-between;
    }


    .container {

        width: 94%;

        margin: 25px auto;
    }


    .card {

        padding: 22px;
    }


    h1 {

        font-size: 24px;
    }


    .row {

        flex-direction: column;

        gap: 5px;
    }


    .row strong {

        text-align: left;
    }


    .total {

        font-size: 22px;
    }

}

</style>

</head>


<body>


<!--
|--------------------------------------------------------------------------
| NAVBAR
|--------------------------------------------------------------------------
-->

<nav class="navbar">

    <div class="logo">

        Book<span>Ease</span>

    </div>


    <div class="nav-links">

        <a
            href="<?= checkoutEsc(BASE_URL) ?>/customer/dashboard.php"
        >
            Dashboard
        </a>


        <a
            href="<?= checkoutEsc(BASE_URL) ?>/customer/my-bookings.php"
        >
            My Bookings
        </a>

    </div>

</nav>


<!--
|--------------------------------------------------------------------------
| MAIN
|--------------------------------------------------------------------------
-->

<div class="container">

    <div class="card">


        <h1>
            Complete Payment
        </h1>


        <p class="subtitle">

            Complete payment for your BookEase booking.

        </p>


        <!-- PAYMENT NOTICE -->

        <div class="notice">

            Payment amount

            <strong>

                <?= checkoutEsc($currency) ?>

                <?= number_format(
                    $totalAmount,
                    2
                ) ?>

            </strong>

        </div>


        <!-- BOOKING INFORMATION -->

        <div class="booking-box">


            <div class="row">

                <span>
                    Booking Reference
                </span>

                <strong>

                    <?= checkoutEsc(
                        $bookingReference
                    ) ?>

                </strong>

            </div>


            <div class="row">

                <span>
                    Customer
                </span>

                <strong>

                    <?= checkoutEsc(
                        $customerName
                    ) ?>

                </strong>

            </div>


            <div class="row">

                <span>
                    Service
                </span>

                <strong>

                    <?= checkoutEsc(
                        $booking['service_name']
                        ?? 'Service'
                    ) ?>

                </strong>

            </div>


            <?php if (!empty($booking['service_duration'])): ?>

                <div class="row">

                    <span>
                        Duration
                    </span>

                    <strong>

                        <?= checkoutEsc(
                            $booking['service_duration']
                        ) ?>

                    </strong>

                </div>

            <?php endif; ?>


            <div class="row">

                <span>
                    Booking Date
                </span>

                <strong>

                    <?= checkoutEsc(
                        $booking['booking_date']
                    ) ?>

                </strong>

            </div>


            <div class="row">

                <span>
                    Booking Time
                </span>

                <strong>

                    <?= checkoutEsc(
                        $booking['booking_time']
                    ) ?>

                </strong>

            </div>


            <div class="row">

                <span>
                    Booking Status
                </span>

                <strong>

                    <?= checkoutEsc(
                        ucfirst($bookingStatus)
                    ) ?>

                </strong>

            </div>


            <div class="row">

                <span>
                    Payment Status
                </span>

                <strong>

                    <span
                        class="payment-status status-<?= checkoutEsc(
                            $paymentStatus
                        ) ?>"
                    >

                        <?= checkoutEsc(
                            ucfirst($paymentStatus)
                        ) ?>

                    </span>

                </strong>

            </div>


            <div class="row">

                <span>
                    Total Amount
                </span>

                <strong class="total">

                    <?= checkoutEsc(
                        $currency
                    ) ?>

                    <?= number_format(
                        $totalAmount,
                        2
                    ) ?>

                </strong>

            </div>


        </div>


        <!-- PAYMENT FORM -->

        <form
            method="POST"
            action="<?= checkoutEsc(BASE_URL) ?>/payments/process.php"
        >


            <input
                type="hidden"
                name="booking_id"
                value="<?= $bookingId ?>"
            >


            <input
                type="hidden"
                name="amount"
                value="<?= checkoutEsc(
                    number_format(
                        $totalAmount,
                        2,
                        '.',
                        ''
                    )
                ) ?>"
            >


            <div class="form-group">

                <label
                    for="payment_method"
                >
                    Payment Method
                </label>


                <select
                    id="payment_method"
                    name="payment_method"
                    required
                >

                    <option value="">
                        Select payment method
                    </option>


                    <option value="mpesa">
                        M-Pesa
                    </option>


                    <option value="card">
                        Card
                    </option>


                    <option value="cash">
                        Cash
                    </option>

                </select>


                <div class="method-help">

                    Select the payment method you want to use.

                </div>

            </div>


            <div class="form-group">

                <label
                    for="phone"
                >
                    Phone Number
                </label>


                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    placeholder="07XXXXXXXX"
                    value="<?= checkoutEsc(
                        $customerPhone
                    ) ?>"
                    autocomplete="tel"
                >


                <div class="method-help">

                    For M-Pesa payments, use the phone number
                    registered with M-Pesa.

                </div>

            </div>


            <button
                type="submit"
                class="btn"
            >

                Pay
                <?= checkoutEsc($currency) ?>

                <?= number_format(
                    $totalAmount,
                    2
                ) ?>

            </button>


        </form>


        <div class="security-note">

            Your payment is linked to booking
            <strong>
                <?= checkoutEsc($bookingReference) ?>
            </strong>
            and your BookEase customer account.

        </div>


        <a
            href="<?= checkoutEsc(BASE_URL) ?>/customer/my-bookings.php"
            class="back"
        >

            ← Back to My Bookings

        </a>


    </div>

</div>


</body>

</html>