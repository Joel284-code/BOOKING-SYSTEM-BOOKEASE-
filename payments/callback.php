<?php

/*
|--------------------------------------------------------------------------
| BookEase - Payment Gateway Callback
|--------------------------------------------------------------------------
| File: payments/callback.php
|
| Purpose:
| - Receive payment gateway callback
| - Find payment using transaction_reference
| - Verify payment belongs to the correct booking/customer
| - Update payments table
| - Update bookings.payment_status
| - Create customer notification
| - Prevent duplicate successful processing
| - Return JSON response
|--------------------------------------------------------------------------
*/

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json; charset=UTF-8');


/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function callbackResponse(
    bool $success,
    string $message,
    int $statusCode = 200,
    array $extra = []
): void {

    http_response_code($statusCode);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $extra
        ),
        JSON_UNESCAPED_SLASHES
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
        throw new RuntimeException(
            'Database connection was not created correctly.'
        );
    }

} catch (Throwable $e) {

    $message = 'Unable to connect to the database.';

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {
        $message .= ' ' . $e->getMessage();
    }

    callbackResponse(
        false,
        $message,
        500
    );
}


/*
|--------------------------------------------------------------------------
| GET CALLBACK DATA
|--------------------------------------------------------------------------
|
| Supports:
| - JSON requests
| - normal POST requests
| - simple GET testing
|--------------------------------------------------------------------------
*/

$rawData = file_get_contents('php://input');

$data = [];


/*
|--------------------------------------------------------------------------
| JSON BODY
|--------------------------------------------------------------------------
*/

if (
    $rawData !== false &&
    trim($rawData) !== ''
) {

    $decoded = json_decode(
        $rawData,
        true
    );

    if (is_array($decoded)) {
        $data = $decoded;
    }
}


/*
|--------------------------------------------------------------------------
| NORMAL POST
|--------------------------------------------------------------------------
*/

if (
    empty($data) &&
    !empty($_POST)
) {

    $data = $_POST;
}


/*
|--------------------------------------------------------------------------
| OPTIONAL GET TESTING
|--------------------------------------------------------------------------
*/

if (
    empty($data) &&
    !empty($_GET)
) {

    $data = $_GET;
}


/*
|--------------------------------------------------------------------------
| EXTRACT TRANSACTION REFERENCE
|--------------------------------------------------------------------------
*/

$transactionReference = trim(
    (string) (
        $data['transaction_reference']
        ?? $data['transactionReference']
        ?? $data['reference']
        ?? $data['Reference']
        ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| EXTRACT TRANSACTION ID
|--------------------------------------------------------------------------
*/

$transactionId = trim(
    (string) (
        $data['transaction_id']
        ?? $data['transactionId']
        ?? $data['transactionID']
        ?? $data['receipt']
        ?? $data['receipt_number']
        ?? $data['mpesa_receipt']
        ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| EXTRACT PAYMENT STATUS
|--------------------------------------------------------------------------
*/

$status = strtolower(
    trim(
        (string) (
            $data['status']
            ?? $data['payment_status']
            ?? $data['paymentStatus']
            ?? ''
        )
    )
);


/*
|--------------------------------------------------------------------------
| EXTRACT AMOUNT
|--------------------------------------------------------------------------
*/

$amount = (float) (
    $data['amount']
    ?? $data['Amount']
    ?? $data['paid_amount']
    ?? $data['paidAmount']
    ?? 0
);


/*
|--------------------------------------------------------------------------
| EXTRACT USER ID IF PROVIDED
|--------------------------------------------------------------------------
*/

$callbackUserId = (int) (
    $data['user_id']
    ?? $data['userId']
    ?? 0
);


/*
|--------------------------------------------------------------------------
| VALIDATE TRANSACTION REFERENCE
|--------------------------------------------------------------------------
*/

if ($transactionReference === '') {

    callbackResponse(
        false,
        'Transaction reference is required.',
        400
    );
}


/*
|--------------------------------------------------------------------------
| VALIDATE STATUS
|--------------------------------------------------------------------------
*/

if ($status === '') {

    callbackResponse(
        false,
        'Payment status is required.',
        400
    );
}


/*
|--------------------------------------------------------------------------
| SUCCESS STATUSES
|--------------------------------------------------------------------------
*/

$successfulStatuses = [
    'success',
    'successful',
    'completed',
    'complete',
    'paid',
    'payment_success',
    'successfully_paid'
];


/*
|--------------------------------------------------------------------------
| FAILED STATUSES
|--------------------------------------------------------------------------
*/

$failedStatuses = [
    'failed',
    'failure',
    'cancelled',
    'canceled',
    'declined',
    'rejected',
    'payment_failed'
];


/*
|--------------------------------------------------------------------------
| REFUNDED STATUSES
|--------------------------------------------------------------------------
*/

$refundedStatuses = [
    'refunded',
    'refund',
    'reversed',
    'reversal'
];


/*
|--------------------------------------------------------------------------
| NORMALIZE STATUS
|--------------------------------------------------------------------------
*/

if (in_array($status, $successfulStatuses, true)) {

    $normalizedStatus = 'paid';

} elseif (in_array($status, $failedStatuses, true)) {

    $normalizedStatus = 'failed';

} elseif (in_array($status, $refundedStatuses, true)) {

    $normalizedStatus = 'refunded';

} elseif ($status === 'pending') {

    $normalizedStatus = 'pending';

} else {

    callbackResponse(
        false,
        'Unknown payment status.',
        400,
        [
            'received_status' => $status
        ]
    );
}


/*
|--------------------------------------------------------------------------
| FIND PAYMENT
|--------------------------------------------------------------------------
|
| IMPORTANT:
| payments table uses:
| - payment_method
| - transaction_reference
| - transaction_id
| - user_id
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->prepare("
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
            p.paid_at,
            p.created_at,

            b.user_id AS booking_user_id,
            b.booking_reference,
            b.status AS booking_status,
            b.payment_status,
            b.total_amount,
            b.amount AS booking_amount,
            b.service_id,

            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone,

            s.name AS service_name

        FROM payments p

        LEFT JOIN bookings b
            ON p.booking_id = b.id

        LEFT JOIN users u
            ON p.user_id = u.id

        LEFT JOIN services s
            ON b.service_id = s.id

        WHERE p.transaction_reference = ?

        LIMIT 1
    ");

    $stmt->execute([
        $transactionReference
    ]);

    $payment = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $e) {

    $message =
        'Unable to find the payment record.';

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        $message .=
            ' ' .
            $e->getMessage();
    }

    callbackResponse(
        false,
        $message,
        500
    );
}


/*
|--------------------------------------------------------------------------
| PAYMENT NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$payment) {

    callbackResponse(
        false,
        'Payment not found.',
        404,
        [
            'transaction_reference' =>
                $transactionReference
        ]
    );
}


/*
|--------------------------------------------------------------------------
| VERIFY PAYMENT USER / BOOKING USER
|--------------------------------------------------------------------------
*/

$paymentUserId = (int) (
    $payment['user_id'] ?? 0
);

$bookingUserId = (int) (
    $payment['booking_user_id'] ?? 0
);


/*
|--------------------------------------------------------------------------
| SECURITY CHECK
|--------------------------------------------------------------------------
*/

if (
    $paymentUserId > 0 &&
    $bookingUserId > 0 &&
    $paymentUserId !== $bookingUserId
) {

    callbackResponse(
        false,
        'Payment customer does not match booking customer.',
        409
    );
}


/*
|--------------------------------------------------------------------------
| VERIFY CALLBACK USER ID
|--------------------------------------------------------------------------
*/

if (
    $callbackUserId > 0 &&
    $paymentUserId > 0 &&
    $callbackUserId !== $paymentUserId
) {

    callbackResponse(
        false,
        'Payment customer verification failed.',
        403
    );
}


/*
|--------------------------------------------------------------------------
| VERIFY PAYMENT AMOUNT
|--------------------------------------------------------------------------
*/

if ($amount > 0) {

    $storedAmount = (float) (
        $payment['amount'] ?? 0
    );

    if (
        abs(
            $amount -
            $storedAmount
        ) > 0.01
    ) {

        callbackResponse(
            false,
            'Payment amount mismatch.',
            400,
            [
                'expected_amount' =>
                    $storedAmount,

                'received_amount' =>
                    $amount
            ]
        );
    }
}


/*
|--------------------------------------------------------------------------
| PAYMENT ALREADY PAID
|--------------------------------------------------------------------------
|
| Prevent duplicate callback processing.
|--------------------------------------------------------------------------
*/

if (
    $payment['status'] === 'paid' &&
    $normalizedStatus === 'paid'
) {

    callbackResponse(
        true,
        'Payment was already confirmed.',
        200,
        [
            'payment_id' =>
                (int) $payment['id'],

            'booking_id' =>
                (int) $payment['booking_id'],

            'user_id' =>
                (int) $payment['user_id'],

            'transaction_reference' =>
                $payment['transaction_reference'],

            'status' =>
                'paid'
        ]
    );
}


/*
|--------------------------------------------------------------------------
| START DATABASE TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $conn->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | UPDATE PAYMENT
    |--------------------------------------------------------------------------
    */

    if ($normalizedStatus === 'paid') {

        $paymentUpdate = $conn->prepare("
            UPDATE payments

            SET
                status = 'paid',

                transaction_id =
                    CASE
                        WHEN ? <> ''
                        THEN ?
                        ELSE transaction_id
                    END,

                paid_at =
                    COALESCE(
                        paid_at,
                        NOW()
                    ),

                updated_at = NOW()

            WHERE id = ?
        ");

        $paymentUpdate->execute([
            $transactionId,
            $transactionId,
            (int) $payment['id']
        ]);

    } elseif ($normalizedStatus === 'failed') {

        $paymentUpdate = $conn->prepare("
            UPDATE payments

            SET
                status = 'failed',

                transaction_id =
                    CASE
                        WHEN ? <> ''
                        THEN ?
                        ELSE transaction_id
                    END,

                updated_at = NOW()

            WHERE id = ?
        ");

        $paymentUpdate->execute([
            $transactionId,
            $transactionId,
            (int) $payment['id']
        ]);

    } elseif ($normalizedStatus === 'refunded') {

        $paymentUpdate = $conn->prepare("
            UPDATE payments

            SET
                status = 'refunded',

                transaction_id =
                    CASE
                        WHEN ? <> ''
                        THEN ?
                        ELSE transaction_id
                    END,

                updated_at = NOW()

            WHERE id = ?
        ");

        $paymentUpdate->execute([
            $transactionId,
            $transactionId,
            (int) $payment['id']
        ]);

    } else {

        $paymentUpdate = $conn->prepare("
            UPDATE payments

            SET
                status = 'pending',

                transaction_id =
                    CASE
                        WHEN ? <> ''
                        THEN ?
                        ELSE transaction_id
                    END,

                updated_at = NOW()

            WHERE id = ?
        ");

        $paymentUpdate->execute([
            $transactionId,
            $transactionId,
            (int) $payment['id']
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE BOOKING PAYMENT STATUS
    |--------------------------------------------------------------------------
    */

    $bookingPaymentStatus = $normalizedStatus;


    /*
    |----------------------------------------------------------------------
    | Refund is also represented as refunded
    |----------------------------------------------------------------------
    */

    $bookingUpdate = $conn->prepare("
        UPDATE bookings

        SET
            payment_status = ?,
            updated_at = NOW()

        WHERE id = ?
    ");

    $bookingUpdate->execute([
        $bookingPaymentStatus,
        (int) $payment['booking_id']
    ]);


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER NOTIFICATION
    |--------------------------------------------------------------------------
    */

    $customerId = $paymentUserId;

    if (
        $customerId <= 0 &&
        $bookingUserId > 0
    ) {

        $customerId = $bookingUserId;
    }


    $bookingReference =
        $payment['booking_reference']
        ?: (
            'BOOKING-' .
            (int) $payment['booking_id']
        );


    $serviceName =
        $payment['service_name']
        ?: 'your booking';


    /*
    |--------------------------------------------------------------------------
    | Notification Content
    |--------------------------------------------------------------------------
    */

    if ($normalizedStatus === 'paid') {

        $notificationTitle =
            'Payment Confirmed';

        $notificationMessage =
            'Your payment of KSh ' .
            number_format(
                (float) $payment['amount'],
                2
            ) .
            ' for ' .
            $serviceName .
            ' (' .
            $bookingReference .
            ') has been successfully confirmed.';

        $notificationType =
            'payment';

    } elseif ($normalizedStatus === 'failed') {

        $notificationTitle =
            'Payment Failed';

        $notificationMessage =
            'Your payment of KSh ' .
            number_format(
                (float) $payment['amount'],
                2
            ) .
            ' for ' .
            $serviceName .
            ' (' .
            $bookingReference .
            ') could not be completed. Please try again.';

        $notificationType =
            'payment';

    } elseif ($normalizedStatus === 'refunded') {

        $notificationTitle =
            'Payment Refunded';

        $notificationMessage =
            'Your payment of KSh ' .
            number_format(
                (float) $payment['amount'],
                2
            ) .
            ' for ' .
            $serviceName .
            ' (' .
            $bookingReference .
            ') has been marked as refunded.';

        $notificationType =
            'payment';

    } else {

        $notificationTitle =
            'Payment Pending';

        $notificationMessage =
            'Your payment of KSh ' .
            number_format(
                (float) $payment['amount'],
                2
            ) .
            ' for ' .
            $serviceName .
            ' (' .
            $bookingReference .
            ') is currently pending.';

        $notificationType =
            'payment';
    }


    /*
    |--------------------------------------------------------------------------
    | Create Notification
    |--------------------------------------------------------------------------
    */

    if ($customerId > 0) {

        /*
        |----------------------------------------------------------------------
        | Prevent duplicate notification for the same payment state
        |----------------------------------------------------------------------
        */

        $existingNotification = $conn->prepare("
            SELECT id

            FROM notifications

            WHERE user_id = ?

            AND booking_id = ?

            AND type = 'payment'

            AND title = ?

            ORDER BY id DESC

            LIMIT 1
        ");

        $existingNotification->execute([
            $customerId,
            (int) $payment['booking_id'],
            $notificationTitle
        ]);

        $notificationExists =
            $existingNotification->fetch(
                PDO::FETCH_ASSOC
            );


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
                    ?,
                    0,
                    NOW()
                )
            ");

            $notificationInsert->execute([
                $customerId,
                (int) $payment['booking_id'],
                $notificationTitle,
                $notificationMessage,
                $notificationType
            ]);
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
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    callbackResponse(
        true,
        'Payment status updated successfully.',
        200,
        [
            'payment_id' =>
                (int) $payment['id'],

            'booking_id' =>
                (int) $payment['booking_id'],

            'user_id' =>
                $customerId,

            'booking_reference' =>
                $bookingReference,

            'amount' =>
                (float) $payment['amount'],

            'payment_method' =>
                $payment['payment_method'],

            'transaction_reference' =>
                $payment['transaction_reference'],

            'transaction_id' =>
                $transactionId !== ''
                    ? $transactionId
                    : $payment['transaction_id'],

            'status' =>
                $normalizedStatus,

            'booking_payment_status' =>
                $normalizedStatus
        ]
    );


} catch (Throwable $e) {


    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    if (
        $conn->inTransaction()
    ) {

        $conn->rollBack();
    }


    $message =
        'Unable to update payment.';

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        $message .=
            ' ' .
            $e->getMessage();
    }


    callbackResponse(
        false,
        $message,
        500
    );
}