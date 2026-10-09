<?php

/*
|--------------------------------------------------------------------------
| BookEase - Payment Processing
|--------------------------------------------------------------------------
| File: payments/process.php
|
| Database:
|   users
|   bookings
|   services
|   payments
|   notifications
|
| Relationships:
|
| users.id
|      ↓
| bookings.user_id
|      ↓
| payments.user_id
|
| bookings.id
|      ↓
| payments.booking_id
|
| bookings.payment_status
|      ↕
| payments.status
|
| Supported:
|   mpesa
|   card
|   cash
|
|--------------------------------------------------------------------------
*/

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| LOAD COMMON FILES
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
| REQUIRE LOGIN
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
| ONLY POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: ' .
        BASE_URL .
        '/customer/my-bookings.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
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
| FAILED PAYMENT REDIRECT
|--------------------------------------------------------------------------
*/

function paymentProcessFailed(
    string $message,
    int $bookingId = 0
): void {

    $url =
        BASE_URL .
        '/payments/failed.php?message=' .
        urlencode($message);

    if ($bookingId > 0) {

        $url .=
            '&booking_id=' .
            $bookingId;
    }

    header(
        'Location: ' . $url
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| GET FORM DATA
|--------------------------------------------------------------------------
*/

$bookingId = (int) (
    $_POST['booking_id'] ?? 0
);

$paymentMethod = strtolower(
    trim(
        (string) (
            $_POST['payment_method'] ?? ''
        )
    )
);

$phone = trim(
    (string) (
        $_POST['phone'] ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| VALIDATE BOOKING
|--------------------------------------------------------------------------
*/

if ($bookingId <= 0) {

    paymentProcessFailed(
        'Invalid booking.',
        0
    );
}


/*
|--------------------------------------------------------------------------
| VALIDATE PAYMENT METHOD
|--------------------------------------------------------------------------
*/

$allowedMethods = [
    'mpesa',
    'card',
    'cash'
];


if (
    !in_array(
        $paymentMethod,
        $allowedMethods,
        true
    )
) {

    paymentProcessFailed(
        'Please select a valid payment method.',
        $bookingId
    );
}


/*
|--------------------------------------------------------------------------
| NORMALIZE PHONE
|--------------------------------------------------------------------------
*/

if ($phone !== '') {

    $phone = preg_replace(
        '/[\s\-\(\)]+/',
        '',
        $phone
    );

    /*
    | 0712345678 → 254712345678
    */

    if (
        preg_match(
            '/^07\d{8}$/',
            $phone
        )
    ) {

        $phone =
            '254' .
            substr(
                $phone,
                1
            );
    }

    /*
    | +254712345678 → 254712345678
    */

    if (
        str_starts_with(
            $phone,
            '+254'
        )
    ) {

        $phone =
            substr(
                $phone,
                1
            );
    }

    /*
    | 254712345678 validation
    */

    if (
        !preg_match(
            '/^2547\d{8}$/',
            $phone
        )
    ) {

        if ($paymentMethod === 'mpesa') {

            paymentProcessFailed(
                'Please enter a valid Kenyan M-Pesa phone number.',
                $bookingId
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| M-PESA PHONE REQUIRED
|--------------------------------------------------------------------------
*/

if (
    $paymentMethod === 'mpesa' &&
    $phone === ''
) {

    paymentProcessFailed(
        'M-Pesa phone number is required.',
        $bookingId
    );
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

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        paymentProcessFailed(
            'Database connection error: ' .
            $e->getMessage(),
            $bookingId
        );
    }

    paymentProcessFailed(
        'Unable to connect to the database.',
        $bookingId
    );
}


/*
|--------------------------------------------------------------------------
| LOAD BOOKING
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Uses bookings.user_id, which matches your database.
|
*/

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

            s.name AS service_name,
            s.duration AS service_duration,

            u.name AS account_name,
            u.email AS account_email,
            u.phone AS account_phone

        FROM bookings b

        INNER JOIN users u
            ON u.id = b.user_id

        INNER JOIN services s
            ON s.id = b.service_id

        WHERE b.id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $bookingId
    ]);

    $booking = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $e) {

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        paymentProcessFailed(
            'Unable to load booking: ' .
            $e->getMessage(),
            $bookingId
        );
    }

    paymentProcessFailed(
        'Unable to load booking.',
        $bookingId
    );
}


/*
|--------------------------------------------------------------------------
| BOOKING NOT FOUND
|--------------------------------------------------------------------------
*/

if (!$booking) {

    paymentProcessFailed(
        'Booking not found.',
        $bookingId
    );
}


/*
|--------------------------------------------------------------------------
| AUTHORIZATION
|--------------------------------------------------------------------------
|
| Customer:
|   Only own booking.
|
| Admin:
|   Can process any booking.
|
| Staff:
|   Can process any booking.
|
|--------------------------------------------------------------------------
*/

if (
    $role !== 'admin' &&
    $role !== 'staff'
) {

    if (
        (int) $booking['user_id'] !== $userId
    ) {

        http_response_code(403);

        exit(
            'Unauthorized payment request.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| BOOKING STATUS
|--------------------------------------------------------------------------
*/

$bookingStatus = strtolower(
    trim(
        (string) (
            $booking['status'] ?? ''
        )
    )
);


if (
    in_array(
        $bookingStatus,
        [
            'cancelled',
            'canceled',
            'rejected',
            'no_show'
        ],
        true
    )
) {

    paymentProcessFailed(
        'This booking cannot be paid because it is cancelled, rejected, or unavailable.',
        $bookingId
    );
}


/*
|--------------------------------------------------------------------------
| CURRENT PAYMENT STATUS
|--------------------------------------------------------------------------
*/

$currentPaymentStatus = strtolower(
    trim(
        (string) (
            $booking['payment_status'] ?? 'unpaid'
        )
    )
);


/*
|--------------------------------------------------------------------------
| ALREADY PAID
|--------------------------------------------------------------------------
*/

if ($currentPaymentStatus === 'paid') {

    header(
        'Location: ' .
        BASE_URL .
        '/payments/success.php?booking_id=' .
        $bookingId .
        '&already_paid=1'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| PAYMENT AMOUNT
|--------------------------------------------------------------------------
*/

$amount = (float) (
    $booking['total_amount']
    ?? $booking['amount']
    ?? 0
);


if ($amount <= 0) {

    paymentProcessFailed(
        'This booking has an invalid payment amount.',
        $bookingId
    );
}


/*
|--------------------------------------------------------------------------
| CUSTOMER USER ID
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Always use the booking owner as payments.user_id.
|
*/

$paymentUserId = (int) $booking['user_id'];


/*
|--------------------------------------------------------------------------
| TRANSACTION REFERENCE GENERATOR
|--------------------------------------------------------------------------
*/

function generateBookEasePaymentReference(
    PDO $conn
): string {

    for ($attempt = 1; $attempt <= 10; $attempt++) {

        try {

            $random = strtoupper(
                bin2hex(
                    random_bytes(4)
                )
            );

        } catch (Throwable $e) {

            $random = strtoupper(
                substr(
                    md5(
                        uniqid(
                            '',
                            true
                        )
                    ),
                    0,
                    8
                )
            );
        }

        $reference =
            'PAY-' .
            date('YmdHis') .
            '-' .
            $random;

        $check = $conn->prepare("
            SELECT id
            FROM payments
            WHERE transaction_reference = ?
            LIMIT 1
        ");

        $check->execute([
            $reference
        ]);

        if (!$check->fetch()) {

            return $reference;
        }
    }

    throw new RuntimeException(
        'Unable to generate a unique payment reference.'
    );
}


/*
|--------------------------------------------------------------------------
| START TRANSACTION
|--------------------------------------------------------------------------
*/

try {

    $conn->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | CHECK FOR EXISTING PENDING PAYMENT
    |--------------------------------------------------------------------------
    */

    $existingStmt = $conn->prepare("
        SELECT
            id,
            transaction_reference,
            transaction_id,
            payment_method,
            phone,
            amount,
            status
        FROM payments
        WHERE booking_id = ?
          AND user_id = ?
          AND status = 'pending'
        ORDER BY id DESC
        LIMIT 1
    ");

    $existingStmt->execute([
        $bookingId,
        $paymentUserId
    ]);

    $existingPayment =
        $existingStmt->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | REUSE EXISTING PENDING PAYMENT
    |--------------------------------------------------------------------------
    */

    if ($existingPayment) {

        $paymentId =
            (int) $existingPayment['id'];

        $transactionReference =
            $existingPayment[
                'transaction_reference'
            ];


        $updatePayment = $conn->prepare("
            UPDATE payments
            SET
                amount = ?,
                payment_method = ?,
                phone = ?,
                updated_at = NOW()
            WHERE id = ?
              AND status = 'pending'
        ");

        $updatePayment->execute([
            $amount,
            $paymentMethod,
            $phone !== ''
                ? $phone
                : null,
            $paymentId
        ]);

    } else {

        /*
        |--------------------------------------------------------------------------
        | CREATE NEW PAYMENT
        |--------------------------------------------------------------------------
        */

        $transactionReference =
            generateBookEasePaymentReference(
                $conn
            );


        $insertPayment = $conn->prepare("
            INSERT INTO payments
            (
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
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                NULL,
                ?,
                'pending',
                NULL,
                NOW(),
                NOW()
            )
        ");

        $insertPayment->execute([
            $bookingId,
            $paymentUserId,
            $amount,
            $paymentMethod,
            $transactionReference,
            $phone !== ''
                ? $phone
                : null
        ]);

        $paymentId =
            (int) $conn->lastInsertId();
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE BOOKING PAYMENT STATUS
    |--------------------------------------------------------------------------
    */

    $bookingUpdate = $conn->prepare("
        UPDATE bookings
        SET
            payment_status = 'pending',
            updated_at = NOW()
        WHERE id = ?
    ");

    $bookingUpdate->execute([
        $bookingId
    ]);


    /*
    |--------------------------------------------------------------------------
    | PAYMENT INITIATED NOTIFICATION
    |--------------------------------------------------------------------------
    */

    $currency =
        defined('CURRENCY')
            ? CURRENCY
            : 'KSh';


    $bookingReference =
        !empty(
            $booking['booking_reference']
        )
            ? $booking['booking_reference']
            : '#' . $bookingId;


    $notificationTitle =
        'Payment Initiated';


    $notificationMessage =
        'A payment of ' .
        $currency .
        ' ' .
        number_format(
            $amount,
            2
        ) .
        ' has been initiated for booking ' .
        $bookingReference .
        '. Payment reference: ' .
        $transactionReference .
        '.';


    /*
    |--------------------------------------------------------------------------
    | CHECK EXISTING INITIATED NOTIFICATION
    |--------------------------------------------------------------------------
    */

    $notificationCheck = $conn->prepare("
        SELECT id
        FROM notifications
        WHERE user_id = ?
          AND booking_id = ?
          AND type = 'payment'
          AND title = 'Payment Initiated'
          AND message LIKE ?
        LIMIT 1
    ");

    $notificationCheck->execute([
        $paymentUserId,
        $bookingId,
        '%' . $transactionReference . '%'
    ]);


    $notificationExists =
        $notificationCheck->fetch(
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
                read_at,
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
                NULL,
                NOW()
            )
        ");

        $notificationInsert->execute([
            $paymentUserId,
            $bookingId,
            $notificationTitle,
            $notificationMessage
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT INITIAL PAYMENT
    |--------------------------------------------------------------------------
    */

    $conn->commit();

} catch (Throwable $e) {

    if ($conn->inTransaction()) {

        $conn->rollBack();
    }


    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        paymentProcessFailed(
            'Unable to create payment: ' .
            $e->getMessage(),
            $bookingId
        );
    }

    paymentProcessFailed(
        'Unable to create payment. Please try again.',
        $bookingId
    );
}


/*
|--------------------------------------------------------------------------
| CASH PAYMENT
|--------------------------------------------------------------------------
|
| Cash stays pending.
|
| Admin must later confirm it.
|
|--------------------------------------------------------------------------
*/

if ($paymentMethod === 'cash') {

    header(
        'Location: ' .
        BASE_URL .
        '/payments/success.php' .
        '?booking_id=' .
        $bookingId .
        '&pending=1' .
        '&reference=' .
        urlencode(
            $transactionReference
        )
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| TEST M-PESA / CARD PAYMENT
|--------------------------------------------------------------------------
|
| Since there is no live payment gateway yet:
|
| M-Pesa and Card are simulated as successful.
|
| Later, a real gateway callback can replace this section.
|--------------------------------------------------------------------------
*/

try {

    $conn->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | GENERATE TEST TRANSACTION ID
    |--------------------------------------------------------------------------
    */

    try {

        $testTransactionId =
            'TEST-' .
            strtoupper(
                bin2hex(
                    random_bytes(6)
                )
            );

    } catch (Throwable $e) {

        $testTransactionId =
            'TEST-' .
            strtoupper(
                substr(
                    md5(
                        uniqid(
                            '',
                            true
                        )
                    ),
                    0,
                    12
                )
            );
    }


    /*
    |--------------------------------------------------------------------------
    | MARK PAYMENT AS PAID
    |--------------------------------------------------------------------------
    */

    $paymentUpdate = $conn->prepare("
        UPDATE payments
        SET
            status = 'paid',
            transaction_id = ?,
            paid_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");

    $paymentUpdate->execute([
        $testTransactionId,
        $paymentId
    ]);


    /*
    |--------------------------------------------------------------------------
    | UPDATE BOOKING
    |--------------------------------------------------------------------------
    */

    $bookingUpdate = $conn->prepare("
        UPDATE bookings
        SET
            payment_status = 'paid',
            updated_at = NOW()
        WHERE id = ?
    ");

    $bookingUpdate->execute([
        $bookingId
    ]);


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER SUCCESS NOTIFICATION
    |--------------------------------------------------------------------------
    */

    $successTitle =
        'Payment Successful';


    $successMessage =
        'Your payment of ' .
        $currency .
        ' ' .
        number_format(
            $amount,
            2
        ) .
        ' for booking ' .
        $bookingReference .
        ' has been successfully received. ' .
        'Payment reference: ' .
        $transactionReference .
        '.';


    $successNotification = $conn->prepare("
        INSERT INTO notifications
        (
            user_id,
            booking_id,
            title,
            message,
            type,
            is_read,
            read_at,
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
            NULL,
            NOW()
        )
    ");

    $successNotification->execute([
        $paymentUserId,
        $bookingId,
        $successTitle,
        $successMessage
    ]);


    /*
    |--------------------------------------------------------------------------
    | ADMIN PAYMENT NOTIFICATION
    |--------------------------------------------------------------------------
    |
    | Send notification to every active administrator.
    |
    */

    $adminMessage =
        'Payment received: ' .
        $currency .
        ' ' .
        number_format(
            $amount,
            2
        ) .
        ' for booking ' .
        $bookingReference .
        '. Customer: ' .
        (
            $booking['account_name']
            ?? $booking['customer_name']
            ?? 'Customer'
        ) .
        '. Reference: ' .
        $transactionReference .
        '.';


    $adminUsers = $conn->query("
        SELECT id
        FROM users
        WHERE role = 'admin'
          AND status = 'active'
    ")->fetchAll(
        PDO::FETCH_COLUMN
    );


    if (!empty($adminUsers)) {

        $adminNotification = $conn->prepare("
            INSERT INTO notifications
            (
                user_id,
                booking_id,
                title,
                message,
                type,
                is_read,
                read_at,
                created_at
            )
            VALUES
            (
                ?,
                ?,
                'Payment Received',
                ?,
                'payment',
                0,
                NULL,
                NOW()
            )
        ");


        foreach ($adminUsers as $adminId) {

            $adminNotification->execute([
                (int) $adminId,
                $bookingId,
                $adminMessage
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $conn->commit();

} catch (Throwable $e) {

    if ($conn->inTransaction()) {

        $conn->rollBack();
    }


    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        paymentProcessFailed(
            'Unable to complete payment: ' .
            $e->getMessage(),
            $bookingId
        );
    }

    paymentProcessFailed(
        'Unable to complete the payment. Please try again.',
        $bookingId
    );
}


/*
|--------------------------------------------------------------------------
| PAYMENT SUCCESS
|--------------------------------------------------------------------------
*/

header(
    'Location: ' .
    BASE_URL .
    '/payments/success.php' .
    '?booking_id=' .
    $bookingId .
    '&reference=' .
    urlencode(
        $transactionReference
    )
);

exit;