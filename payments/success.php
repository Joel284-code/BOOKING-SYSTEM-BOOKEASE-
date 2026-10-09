<?php

/*
|--------------------------------------------------------------------------
| BookEase - Payment Success / Payment Status
|--------------------------------------------------------------------------
| File: payments/success.php
|
| This page communicates with:
|
| Customer:
|   - customer/dashboard.php
|   - customer/my-bookings.php
|   - customer/booking-details.php
|
| Admin:
|   - admin/dashboard.php
|   - admin/payments.php
|   - admin/booking-view.php
|
| Database:
|   payments
|   bookings
|   users
|   services
|   notifications
|
| IMPORTANT:
| This page DOES NOT automatically mark a payment as paid.
| The payment status must come from payments.status.
|
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
| Require Login
|--------------------------------------------------------------------------
*/

if (!isLoggedIn()) {

    header(
        'Location: ' .
        BASE_URL .
        '/auth/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

$userId = (int) (
    $_SESSION['user_id'] ?? 0
);

$role = strtolower(
    trim(
        (string) (
            $_SESSION['role'] ?? 'customer'
        )
    )
);


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
| Helper: Escape
|--------------------------------------------------------------------------
*/

if (!function_exists('paymentSuccessEsc')) {

    function paymentSuccessEsc($value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Helper: Error Page
|--------------------------------------------------------------------------
*/

function paymentSuccessError(
    string $message,
    int $statusCode = 400
): void {

    http_response_code($statusCode);

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Payment Information</title>';

    echo '<style>
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #1e293b;
        }

        .box {
            width: 100%;
            max-width: 550px;
            background: white;
            padding: 35px;
            border-radius: 14px;
            text-align: center;
            box-shadow: 0 5px 25px rgba(0,0,0,.07);
        }

        h1 {
            color: #dc2626;
            margin-bottom: 15px;
        }

        p {
            color: #64748b;
            line-height: 1.6;
        }

        a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 20px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }
    </style>';

    echo '</head>';
    echo '<body>';

    echo '<div class="box">';

    echo '<h1>Payment Information</h1>';

    echo '<p>';
    echo paymentSuccessEsc($message);
    echo '</p>';

    echo '<a href="' .
        paymentSuccessEsc(
            BASE_URL . '/customer/my-bookings.php'
        ) .
        '">Back to My Bookings</a>';

    echo '</div>';

    echo '</body>';
    echo '</html>';

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
| Get Optional Transaction Reference
|--------------------------------------------------------------------------
*/

$requestedReference = trim(
    (string) (
        $_GET['reference']
        ?? $_GET['transaction_reference']
        ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| Pending Flag
|--------------------------------------------------------------------------
|
| This is only a display hint.
| The database remains the source of truth.
|
|--------------------------------------------------------------------------
*/

$pendingHint =
    isset($_GET['pending']) &&
    $_GET['pending'] === '1';


/*
|--------------------------------------------------------------------------
| Validate Booking ID
|--------------------------------------------------------------------------
*/

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

        paymentSuccessError(
            'Database connection error: ' .
            $e->getMessage(),
            500
        );
    }

    paymentSuccessError(
        'Unable to connect to the database.',
        500
    );
}


/*
|--------------------------------------------------------------------------
| Load Booking
|--------------------------------------------------------------------------
|
| We first verify that the booking exists.
|
| Admin/staff:
|   Can view any booking.
|
| Customer:
|   Can only view their own booking.
|
|--------------------------------------------------------------------------
*/

try {

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

            s.name AS service_name,
            s.duration,

            u.name AS account_name,
            u.email AS account_email,
            u.phone AS account_phone

        FROM bookings b

        LEFT JOIN services s
            ON b.service_id = s.id

        LEFT JOIN users u
            ON b.user_id = u.id

        WHERE b.id = ?

        LIMIT 1
    ");

    $bookingStmt->execute([
        $bookingId
    ]);

    $booking = $bookingStmt->fetch(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $e) {

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        paymentSuccessError(
            'Unable to load booking: ' .
            $e->getMessage(),
            500
        );
    }

    paymentSuccessError(
        'Unable to load booking information.',
        500
    );
}


/*
|--------------------------------------------------------------------------
| Booking Not Found
|--------------------------------------------------------------------------
*/

if (!$booking) {

    paymentSuccessError(
        'Booking not found.',
        404
    );
}


/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
|
| Admin/staff can access any payment.
|
| Customers can only access their own booking.
|
|--------------------------------------------------------------------------
*/

$isAdmin =
    in_array(
        $role,
        [
            'admin',
            'staff'
        ],
        true
    );


if (
    !$isAdmin &&
    (int) $booking['user_id'] !== $userId
) {

    paymentSuccessError(
        'You are not authorized to view this payment.',
        403
    );
}


/*
|--------------------------------------------------------------------------
| Get Latest Payment
|--------------------------------------------------------------------------
*/

try {

    /*
    |----------------------------------------------------------------------
    | Prefer requested transaction reference
    |----------------------------------------------------------------------
    */

    if ($requestedReference !== '') {

        $paymentStmt = $conn->prepare("
            SELECT

                p.id,
                p.booking_id,
                p.user_id,

                p.amount,
                p.payment_method,

                p.transaction_reference,
                p.transaction_id,

                p.phone,
                p.status,

                p.created_at,
                p.paid_at,
                p.updated_at

            FROM payments p

            WHERE p.booking_id = ?

              AND p.transaction_reference = ?

            ORDER BY p.id DESC

            LIMIT 1
        ");

        $paymentStmt->execute([
            $bookingId,
            $requestedReference
        ]);

    } else {

        $paymentStmt = $conn->prepare("
            SELECT

                p.id,
                p.booking_id,
                p.user_id,

                p.amount,
                p.payment_method,

                p.transaction_reference,
                p.transaction_id,

                p.phone,
                p.status,

                p.created_at,
                p.paid_at,
                p.updated_at

            FROM payments p

            WHERE p.booking_id = ?

            ORDER BY p.id DESC

            LIMIT 1
        ");

        $paymentStmt->execute([
            $bookingId
        ]);
    }

    $payment = $paymentStmt->fetch(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $e) {

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        paymentSuccessError(
            'Unable to load payment: ' .
            $e->getMessage(),
            500
        );
    }

    paymentSuccessError(
        'Unable to load payment information.',
        500
    );
}


/*
|--------------------------------------------------------------------------
| Payment Not Found
|--------------------------------------------------------------------------
*/

if (!$payment) {

    paymentSuccessError(
        'No payment record was found for this booking.',
        404
    );
}


/*
|--------------------------------------------------------------------------
| Verify Payment Belongs To Booking Customer
|--------------------------------------------------------------------------
*/

if (
    !$isAdmin &&
    (int) $payment['user_id'] !== $userId
) {

    paymentSuccessError(
        'You are not authorized to view this payment.',
        403
    );
}


/*
|--------------------------------------------------------------------------
| Normalize Payment Status
|--------------------------------------------------------------------------
*/

$paymentStatus = strtolower(
    trim(
        (string) (
            $payment['status'] ?? 'pending'
        )
    )
);


/*
|--------------------------------------------------------------------------
| Normalize Booking Payment Status
|--------------------------------------------------------------------------
*/

$bookingPaymentStatus = strtolower(
    trim(
        (string) (
            $booking['payment_status']
            ?? 'unpaid'
        )
    )
);


/*
|--------------------------------------------------------------------------
| Determine Status
|--------------------------------------------------------------------------
*/

$successfulStatuses = [
    'paid',
    'success',
    'successful',
    'completed',
    'complete'
];

$pendingStatuses = [
    'pending',
    'processing',
    'initiated',
    'awaiting',
    'unpaid'
];

$failedStatuses = [
    'failed',
    'failure',
    'cancelled',
    'canceled',
    'declined',
    'rejected'
];


$isPaid = in_array(
    $paymentStatus,
    $successfulStatuses,
    true
);


$isFailed = in_array(
    $paymentStatus,
    $failedStatuses,
    true
);


$isPending = in_array(
    $paymentStatus,
    $pendingStatuses,
    true
);


/*
|--------------------------------------------------------------------------
| Synchronize Booking Payment Status
|--------------------------------------------------------------------------
|
| This keeps:
|
| payments.status
|        ↕
| bookings.payment_status
|
| synchronized.
|
| IMPORTANT:
| We NEVER convert pending into paid here.
|
|--------------------------------------------------------------------------
*/

try {

    if ($isPaid && $bookingPaymentStatus !== 'paid') {

        $sync = $conn->prepare("
            UPDATE bookings
            SET
                payment_status = 'paid',
                updated_at = NOW()
            WHERE id = ?
        ");

        $sync->execute([
            $bookingId
        ]);

        $booking['payment_status'] = 'paid';
    }


    if (
        $isFailed &&
        $bookingPaymentStatus !== 'failed'
    ) {

        $sync = $conn->prepare("
            UPDATE bookings
            SET
                payment_status = 'failed',
                updated_at = NOW()
            WHERE id = ?
        ");

        $sync->execute([
            $bookingId
        ]);

        $booking['payment_status'] = 'failed';
    }


    if (
        $isPending &&
        $bookingPaymentStatus !== 'pending'
    ) {

        $sync = $conn->prepare("
            UPDATE bookings
            SET
                payment_status = 'pending',
                updated_at = NOW()
            WHERE id = ?
        ");

        $sync->execute([
            $bookingId
        ]);

        $booking['payment_status'] = 'pending';
    }

} catch (Throwable $e) {

    /*
     * Do not destroy the payment success page
     * if only the synchronization query fails.
     */

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        error_log(
            'BookEase payment status sync error: ' .
            $e->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| Customer Notification
|--------------------------------------------------------------------------
|
| Ensure a payment status notification exists.
|
|--------------------------------------------------------------------------
*/

try {

    $notificationUserId =
        (int) $booking['user_id'];

    $bookingReference =
        $booking['booking_reference']
        ?: '#' . $bookingId;

    $currency =
        defined('CURRENCY')
            ? CURRENCY
            : 'KSh';

    $paymentAmount =
        (float) (
            $payment['amount'] ?? 0
        );


    if ($isPaid) {

        $notificationTitle =
            'Payment Successful';

        $notificationMessage =
            'Your payment of ' .
            $currency .
            ' ' .
            number_format(
                $paymentAmount,
                2
            ) .
            ' for booking ' .
            $bookingReference .
            ' has been successfully received. ' .
            'Payment reference: ' .
            (
                $payment['transaction_reference']
                ?: 'N/A'
            ) .
            '.';

    } elseif ($isFailed) {

        $notificationTitle =
            'Payment Failed';

        $notificationMessage =
            'Your payment for booking ' .
            $bookingReference .
            ' was not successful. ' .
            'Please try again or contact the administrator.';

    } else {

        $notificationTitle =
            'Payment Pending';

        $notificationMessage =
            'Your payment of ' .
            $currency .
            ' ' .
            number_format(
                $paymentAmount,
                2
            ) .
            ' for booking ' .
            $bookingReference .
            ' is awaiting confirmation.';
    }


    /*
    |----------------------------------------------------------------------
    | Check Existing Notification
    |----------------------------------------------------------------------
    */

    $notificationCheck = $conn->prepare("
        SELECT id

        FROM notifications

        WHERE user_id = ?
          AND booking_id = ?
          AND type = 'payment'
          AND title = ?

        ORDER BY id DESC

        LIMIT 1
    ");

    $notificationCheck->execute([
        $notificationUserId,
        $bookingId,
        $notificationTitle
    ]);

    $notificationExists =
        $notificationCheck->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |----------------------------------------------------------------------
    | Insert Notification
    |----------------------------------------------------------------------
    */

    if (!$notificationExists) {

        $notificationInsert = $conn->prepare("
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
                'payment',
                0,
                NOW()
            )
        ");

        $notificationInsert->execute([
            $notificationUserId,
            $bookingId,
            $notificationTitle,
            $notificationMessage
        ]);
    }

} catch (Throwable $e) {

    /*
     * Notification failure should not prevent
     * the customer from seeing payment details.
     */

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        error_log(
            'BookEase notification error: ' .
            $e->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| Payment Method Label
|--------------------------------------------------------------------------
*/

$paymentMethod =
    strtolower(
        trim(
            (string) (
                $payment['payment_method']
                ?? ''
            )
        )
    );


$paymentMethodLabels = [

    'mpesa' => 'M-Pesa',

    'card' => 'Card',

    'cash' => 'Cash'

];


$paymentMethodLabel =
    $paymentMethodLabels[
        $paymentMethod
    ]
    ?? (
        $paymentMethod !== ''
            ? ucfirst($paymentMethod)
            : 'N/A'
    );


/*
|--------------------------------------------------------------------------
| Status Label
|--------------------------------------------------------------------------
*/

if ($isPaid) {

    $statusLabel =
        'Paid';

    $statusClass =
        'success';

} elseif ($isFailed) {

    $statusLabel =
        'Failed';

    $statusClass =
        'failed';

} else {

    $statusLabel =
        'Pending';

    $statusClass =
        'pending';
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
| Transaction Reference
|--------------------------------------------------------------------------
*/

$transactionReference =
    $payment['transaction_reference']
    ?? 'N/A';


/*
|--------------------------------------------------------------------------
| Transaction ID
|--------------------------------------------------------------------------
*/

$transactionId =
    $payment['transaction_id']
    ?? '';


/*
|--------------------------------------------------------------------------
| Booking Reference
|--------------------------------------------------------------------------
*/

$bookingReference =
    $booking['booking_reference']
    ?: '#' . $bookingId;

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

<?= $statusLabel ?>

|

<?= paymentSuccessEsc(APP_NAME) ?>

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


.container {

    width: 100%;

    max-width: 700px;

    margin: 30px auto;
}


.card {

    background: white;

    padding: 40px;

    border-radius: 16px;

    box-shadow:
        0 8px 30px
        rgba(0,0,0,.07);

    text-align: center;
}


.logo {

    font-size: 25px;

    font-weight: 700;

    margin-bottom: 25px;

    color: #0f172a;
}


.logo span {

    color: #2563eb;
}


.icon {

    width: 78px;

    height: 78px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    margin: 0 auto 20px;

    font-size: 40px;

    font-weight: bold;
}


.icon.success {

    background: #dcfce7;

    color: #16a34a;
}


.icon.pending {

    background: #fef3c7;

    color: #d97706;
}


.icon.failed {

    background: #fee2e2;

    color: #dc2626;
}


h1 {

    margin: 0 0 12px;

    font-size: 29px;
}


h1.success {

    color: #166534;
}


h1.pending {

    color: #92400e;
}


h1.failed {

    color: #991b1b;
}


.message {

    color: #64748b;

    line-height: 1.6;

    margin-bottom: 25px;
}


.details {

    text-align: left;

    margin-top: 25px;

    border-top:
        1px solid #e2e8f0;
}


.row {

    display: flex;

    justify-content: space-between;

    gap: 20px;

    padding: 14px 0;

    border-bottom:
        1px solid #e2e8f0;
}


.label {

    color: #64748b;

    font-size: 14px;
}


.value {

    font-weight: 600;

    text-align: right;

    word-break: break-word;
}


.amount {

    color: #2563eb;

    font-size: 19px;
}


.status {

    padding: 6px 12px;

    border-radius: 20px;

    font-size: 13px;
}


.status.success {

    background: #dcfce7;

    color: #166534;
}


.status.pending {

    background: #fef3c7;

    color: #92400e;
}


.status.failed {

    background: #fee2e2;

    color: #991b1b;
}


.notice {

    margin-top: 20px;

    padding: 15px;

    border-radius: 9px;

    line-height: 1.5;

    font-size: 14px;
}


.notice.pending {

    background: #fef3c7;

    color: #92400e;
}


.notice.failed {

    background: #fee2e2;

    color: #991b1b;
}


.notice.success {

    background: #dcfce7;

    color: #166534;
}


.actions {

    margin-top: 28px;
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


.btn-secondary {

    background: #e2e8f0;

    color: #1e293b;
}


.btn-secondary:hover {

    background: #cbd5e1;
}


.btn-admin {

    background: #0f172a;

    color: white;
}


.btn-admin:hover {

    background: #1e293b;
}


@media (max-width: 600px) {

    body {
        padding: 12px;
    }

    .card {
        padding: 28px 20px;
    }

    h1 {
        font-size: 24px;
    }

    .row {
        flex-direction: column;
        gap: 5px;
    }

    .value {
        text-align: left;
    }

}

</style>

</head>


<body>


<div class="container">

<div class="card">


<div class="logo">

Book<span>Ease</span>

</div>


<div class="icon <?= paymentSuccessEsc($statusClass) ?>">

<?php if ($isPaid): ?>

✓

<?php elseif ($isFailed): ?>

×

<?php else: ?>

!

<?php endif; ?>

</div>


<?php if ($isPaid): ?>

<h1 class="success">

Payment Successful

</h1>

<p class="message">

Your payment has been successfully recorded.
Your booking payment status has been updated.

</p>


<?php elseif ($isFailed): ?>

<h1 class="failed">

Payment Failed

</h1>

<p class="message">

The payment for this booking was not successful.
Please try again or contact the administrator.

</p>


<?php else: ?>

<h1 class="pending">

Payment Pending

</h1>

<p class="message">

Your payment has been recorded and is awaiting
confirmation by the administrator.

</p>

<?php endif; ?>


<div class="details">


<div class="row">

<span class="label">

Booking Reference

</span>

<span class="value">

<?= paymentSuccessEsc(
    $bookingReference
) ?>

</span>

</div>


<div class="row">

<span class="label">

Service

</span>

<span class="value">

<?= paymentSuccessEsc(
    $booking['service_name']
    ?? 'Service'
) ?>

</span>

</div>


<div class="row">

<span class="label">

Booking Date

</span>

<span class="value">

<?= paymentSuccessEsc(
    $booking['booking_date']
    ?? ''
) ?>

</span>

</div>


<div class="row">

<span class="label">

Booking Time

</span>

<span class="value">

<?= paymentSuccessEsc(
    $booking['booking_time']
    ?? ''
) ?>

</span>

</div>


<div class="row">

<span class="label">

Amount

</span>

<span class="value amount">

<?= paymentSuccessEsc(
    $currency
) ?>

<?= number_format(
    (float) (
        $payment['amount']
        ?? 0
    ),
    2
) ?>

</span>

</div>


<div class="row">

<span class="label">

Payment Method

</span>

<span class="value">

<?= paymentSuccessEsc(
    $paymentMethodLabel
) ?>

</span>

</div>


<div class="row">

<span class="label">

Payment Reference

</span>

<span class="value">

<?= paymentSuccessEsc(
    $transactionReference
) ?>

</span>

</div>


<?php if ($transactionId !== ''): ?>

<div class="row">

<span class="label">

Transaction ID

</span>

<span class="value">

<?= paymentSuccessEsc(
    $transactionId
) ?>

</span>

</div>

<?php endif; ?>


<div class="row">

<span class="label">

Payment Status

</span>

<span class="value">

<span class="status <?= paymentSuccessEsc(
    $statusClass
) ?>">

<?= paymentSuccessEsc(
    $statusLabel
) ?>

</span>

</span>

</div>


<div class="row">

<span class="label">

Booking Status

</span>

<span class="value">

<?= paymentSuccessEsc(
    ucfirst(
        strtolower(
            (string) (
                $booking['status']
                ?? 'pending'
            )
        )
    )
) ?>

</span>

</div>


</div>


<?php if ($isPending): ?>

<div class="notice pending">

<strong>Payment awaiting confirmation.</strong><br>

The administrator can review and confirm this
payment from the admin payment/booking section.

</div>

<?php elseif ($isFailed): ?>

<div class="notice failed">

<strong>Payment was not completed.</strong><br>

You can return to checkout and try the payment again.

</div>

<?php else: ?>

<div class="notice success">

<strong>Payment confirmed.</strong><br>

Your payment has been recorded successfully and
the booking payment status is now marked as paid.

</div>

<?php endif; ?>


<div class="actions">


<?php if ($isFailed): ?>

<a
    href="<?= paymentSuccessEsc(
        BASE_URL .
        '/payments/checkout.php?booking_id=' .
        $bookingId
    ) ?>"
    class="btn btn-primary"
>

Try Payment Again

</a>

<?php endif; ?>


<a
    href="<?= paymentSuccessEsc(
        BASE_URL .
        '/customer/booking-details.php?id=' .
        $bookingId
    ) ?>"
    class="btn btn-primary"
>

View Booking

</a>


<a
    href="<?= paymentSuccessEsc(
        BASE_URL .
        '/customer/my-bookings.php'
    ) ?>"
    class="btn btn-secondary"
>

My Bookings

</a>


<a
    href="<?= paymentSuccessEsc(
        BASE_URL .
        '/customer/dashboard.php'
    ) ?>"
    class="btn btn-success"
>

Customer Dashboard

</a>


<?php if ($isAdmin): ?>

<a
    href="<?= paymentSuccessEsc(
        BASE_URL .
        '/admin/dashboard.php'
    ) ?>"
    class="btn btn-admin"
>

Admin Dashboard

</a>

<?php endif; ?>


</div>


</div>

</div>


</body>

</html>