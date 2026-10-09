<?php

/*
|--------------------------------------------------------------------------
| BookEase - Admin Payments
|--------------------------------------------------------------------------
| File: admin/payments.php
|
| Connected tables:
| users
| bookings
| services
| payments
| notifications
|
| Compatible with the BookEase database supplied by the user.
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
| Authentication
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
| Get Current User
|--------------------------------------------------------------------------
*/

$currentUserId = (int) $_SESSION['user_id'];

$role = strtolower(
    trim(
        (string) (
            $_SESSION['role'] ?? ''
        )
    )
);


/*
|--------------------------------------------------------------------------
| Recover Missing Role
|--------------------------------------------------------------------------
*/

if ($role === '') {

    try {

        $database = new Database();

        $conn = $database->getConnection();

        $roleStmt = $conn->prepare("
            SELECT
                role,
                status
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $roleStmt->execute([
            $currentUserId
        ]);

        $currentUser = $roleStmt->fetch(
            PDO::FETCH_ASSOC
        );

        if ($currentUser) {

            $role = strtolower(
                trim(
                    (string) (
                        $currentUser['role'] ?? ''
                    )
                )
            );

            $_SESSION['role'] = $role;
        }

    } catch (Throwable $e) {

        header(
            'Location: ' .
            BASE_URL .
            '/auth/login.php'
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Admin / Staff Access
|--------------------------------------------------------------------------
*/

if (
    $role !== 'admin' &&
    $role !== 'staff'
) {

    if ($role === 'customer') {

        header(
            'Location: ' .
            BASE_URL .
            '/customer/dashboard.php'
        );

    } else {

        header(
            'Location: ' .
            BASE_URL .
            '/auth/login.php'
        );
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

$error = '';

$message = '';

$payments = [];

try {

    $database = new Database();

    $conn = $database->getConnection();

    if (!$conn instanceof PDO) {

        throw new RuntimeException(
            'Database connection was not created correctly.'
        );
    }

} catch (Throwable $e) {

    $error =
        'Unable to connect to the database. Please check your database configuration.';
}


/*
|--------------------------------------------------------------------------
| Safe Output
|--------------------------------------------------------------------------
*/

function paymentEsc($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Payment Status Class
|--------------------------------------------------------------------------
*/

function paymentStatusClass(string $status): string
{
    $status = strtolower(
        trim($status)
    );

    $allowed = [
        'pending',
        'paid',
        'failed',
        'refunded'
    ];

    return in_array(
        $status,
        $allowed,
        true
    )
        ? $status
        : 'default';
}


/*
|--------------------------------------------------------------------------
| Payment Method Label
|--------------------------------------------------------------------------
*/

function paymentMethodLabel(string $method): string
{
    $method = strtolower(
        trim($method)
    );

    switch ($method) {

        case 'mpesa':
            return 'M-Pesa';

        case 'card':
            return 'Card';

        case 'cash':
            return 'Cash';

        default:
            return ucfirst(
                $method ?: 'Unknown'
            );
    }
}


/*
|--------------------------------------------------------------------------
| Format Date
|--------------------------------------------------------------------------
*/

function paymentFormatDate(
    ?string $date,
    string $format = 'd M Y H:i'
): string {

    if (
        empty($date) ||
        strtotime($date) === false
    ) {

        return '-';
    }

    return date(
        $format,
        strtotime($date)
    );
}


/*
|--------------------------------------------------------------------------
| UPDATE PAYMENT STATUS
|--------------------------------------------------------------------------
|
| Example:
|
| payments.php?action=status&id=5&status=paid
|
|--------------------------------------------------------------------------
*/

if (
    $error === '' &&
    isset($_GET['action']) &&
    $_GET['action'] === 'status' &&
    isset($_GET['id']) &&
    isset($_GET['status'])
) {

    $paymentId =
        (int) $_GET['id'];

    $newStatus =
        strtolower(
            trim(
                (string) $_GET['status']
            )
        );


    $allowedPaymentStatuses = [
        'pending',
        'paid',
        'failed',
        'refunded'
    ];


    if ($paymentId <= 0) {

        $error =
            'Invalid payment ID.';

    } elseif (
        !in_array(
            $newStatus,
            $allowedPaymentStatuses,
            true
        )
    ) {

        $error =
            'Invalid payment status.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Get Payment
            |--------------------------------------------------------------------------
            */

            $paymentCheck =
                $conn->prepare("
                    SELECT
                        id,
                        booking_id,
                        user_id,
                        amount,
                        status
                    FROM payments
                    WHERE id = ?
                    LIMIT 1
                ");

            $paymentCheck->execute([
                $paymentId
            ]);

            $existingPayment =
                $paymentCheck->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$existingPayment) {

                $error =
                    'Payment transaction was not found.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Start Transaction
                |--------------------------------------------------------------------------
                */

                $conn->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | Update Payment
                |--------------------------------------------------------------------------
                */

                if ($newStatus === 'paid') {

                    $updatePayment =
                        $conn->prepare("
                            UPDATE payments
                            SET
                                status = 'paid',
                                paid_at = COALESCE(
                                    paid_at,
                                    NOW()
                                ),
                                updated_at = NOW()
                            WHERE id = ?
                        ");

                    $updatePayment->execute([
                        $paymentId
                    ]);

                } else {

                    $updatePayment =
                        $conn->prepare("
                            UPDATE payments
                            SET
                                status = ?,
                                updated_at = NOW()
                            WHERE id = ?
                        ");

                    $updatePayment->execute([
                        $newStatus,
                        $paymentId
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Update Related Booking Payment Status
                |--------------------------------------------------------------------------
                */

                $bookingId =
                    (int) (
                        $existingPayment['booking_id']
                        ?? 0
                    );


                $bookingPaymentStatus =
                    'unpaid';


                switch ($newStatus) {

                    case 'paid':

                        $bookingPaymentStatus =
                            'paid';

                        break;


                    case 'pending':

                        $bookingPaymentStatus =
                            'pending';

                        break;


                    case 'failed':

                        $bookingPaymentStatus =
                            'failed';

                        break;


                    case 'refunded':

                        $bookingPaymentStatus =
                            'refunded';

                        break;
                }


                if ($bookingId > 0) {

                    $updateBooking =
                        $conn->prepare("
                            UPDATE bookings
                            SET
                                payment_status = ?,
                                updated_at = NOW()
                            WHERE id = ?
                        ");

                    $updateBooking->execute([
                        $bookingPaymentStatus,
                        $bookingId
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Customer Notification
                |--------------------------------------------------------------------------
                */

                $customerId =
                    (int) (
                        $existingPayment['user_id']
                        ?? 0
                    );


                if (
                    $customerId > 0 &&
                    $bookingId > 0
                ) {

                    $notificationTitle =
                        'Payment Status Updated';


                    switch ($newStatus) {

                        case 'paid':

                            $notificationMessage =
                                'Your payment for booking #' .
                                $bookingId .
                                ' has been marked as paid.';

                            break;


                        case 'pending':

                            $notificationMessage =
                                'Your payment for booking #' .
                                $bookingId .
                                ' is currently pending.';

                            break;


                        case 'failed':

                            $notificationMessage =
                                'Your payment for booking #' .
                                $bookingId .
                                ' has failed. Please check your payment details.';

                            break;


                        case 'refunded':

                            $notificationMessage =
                                'Your payment for booking #' .
                                $bookingId .
                                ' has been marked as refunded.';

                            break;


                        default:

                            $notificationMessage =
                                'The payment status for booking #' .
                                $bookingId .
                                ' has been updated.';
                    }


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
                                    'payment',
                                    0,
                                    NOW()
                                )
                            ");

                        $notificationStmt->execute([
                            $customerId,
                            $bookingId,
                            $notificationTitle,
                            $notificationMessage
                        ]);

                    } catch (Throwable $notificationError) {

                        /*
                        |----------------------------------------------------------------------
                        | Notification failure must not cancel payment update.
                        |----------------------------------------------------------------------
                        */

                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Commit
                |--------------------------------------------------------------------------
                */

                $conn->commit();


                /*
                |--------------------------------------------------------------------------
                | Redirect
                |--------------------------------------------------------------------------
                */

                header(
                    'Location: ' .
                    BASE_URL .
                    '/admin/payments.php?updated=1'
                );

                exit;
            }

        } catch (Throwable $e) {

            if (
                isset($conn) &&
                $conn instanceof PDO &&
                $conn->inTransaction()
            ) {

                $conn->rollBack();
            }


            $error =
                'Unable to update payment status. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| Success Message
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['updated']) &&
    $_GET['updated'] === '1'
) {

    $message =
        'Payment status updated successfully.';
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalPayments = 0;

$paidPayments = 0;

$pendingPayments = 0;

$failedPayments = 0;

$refundedPayments = 0;

$totalRevenue = 0.00;

$pendingAmount = 0.00;


/*
|--------------------------------------------------------------------------
| Search / Filter
|--------------------------------------------------------------------------
*/

$search =
    trim(
        (string) (
            $_GET['search'] ?? ''
        )
    );


$statusFilter =
    strtolower(
        trim(
            (string) (
                $_GET['status'] ?? ''
            )
        )
    );


$allowedFilters = [
    '',
    'pending',
    'paid',
    'failed',
    'refunded'
];


if (
    !in_array(
        $statusFilter,
        $allowedFilters,
        true
    )
) {

    $statusFilter = '';
}


/*
|--------------------------------------------------------------------------
| Load Payments
|--------------------------------------------------------------------------
*/

if (
    $error === '' &&
    isset($conn) &&
    $conn instanceof PDO
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | Build Query
        |--------------------------------------------------------------------------
        */

        $sql = "
            SELECT

                p.id,
                p.booking_id,
                p.user_id,
                p.amount,
                p.payment_method,
                p.transaction_reference,
                p.transaction_id,
                p.phone AS payment_phone,
                p.status AS payment_status,
                p.paid_at,
                p.created_at,
                p.updated_at,

                b.booking_reference,
                b.booking_date,
                b.booking_time,
                b.status AS booking_status,
                b.payment_status AS booking_payment_status,

                u.name AS customer,
                u.email AS customer_email,
                u.phone AS customer_phone,

                s.name AS service,
                s.duration AS service_duration

            FROM payments p

            INNER JOIN bookings b
                ON p.booking_id = b.id

            INNER JOIN users u
                ON p.user_id = u.id

            INNER JOIN services s
                ON b.service_id = s.id

            WHERE 1 = 1
        ";


        $params = [];


        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($search !== '') {

            $sql .= "
                AND (
                    u.name LIKE :search
                    OR u.email LIKE :search
                    OR u.phone LIKE :search
                    OR p.transaction_reference LIKE :search
                    OR p.transaction_id LIKE :search
                    OR b.booking_reference LIKE :search
                    OR s.name LIKE :search
                )
            ";

            $params[':search'] =
                '%' . $search . '%';
        }


        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */

        if ($statusFilter !== '') {

            $sql .= "
                AND p.status = :status
            ";

            $params[':status'] =
                $statusFilter;
        }


        /*
        |--------------------------------------------------------------------------
        | Order
        |--------------------------------------------------------------------------
        */

        $sql .= "
            ORDER BY p.id DESC
        ";


        /*
        |--------------------------------------------------------------------------
        | Execute
        |--------------------------------------------------------------------------
        */

        $stmt =
            $conn->prepare($sql);

        $stmt->execute(
            $params
        );


        $payments =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | Calculate Statistics
        |--------------------------------------------------------------------------
        */

        foreach ($payments as $payment) {

            $paymentStatus =
                strtolower(
                    trim(
                        (string) (
                            $payment['payment_status']
                            ?? 'pending'
                        )
                    )
                );


            $amount =
                (float) (
                    $payment['amount']
                    ?? 0
                );


            $totalPayments++;


            switch ($paymentStatus) {

                case 'paid':

                    $paidPayments++;

                    $totalRevenue +=
                        $amount;

                    break;


                case 'pending':

                    $pendingPayments++;

                    $pendingAmount +=
                        $amount;

                    break;


                case 'failed':

                    $failedPayments++;

                    break;


                case 'refunded':

                    $refundedPayments++;

                    break;
            }
        }


    } catch (PDOException $e) {

        $error =
            'Unable to load payment transactions. Please check your BookEase database connection.';

    } catch (Throwable $e) {

        $error =
            'Unable to load payment transactions.';
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
    Payments | BookEase
</title>


<style>

* {
    box-sizing: border-box;
}


:root {

    --primary: #2563eb;
    --primary-dark: #1d4ed8;

    --success: #16a34a;
    --warning: #d97706;
    --danger: #dc2626;

    --purple: #7c3aed;

    --dark: #0f172a;
    --text: #334155;
    --muted: #64748b;

    --border: #e2e8f0;

    --background: #f8fafc;

    --white: #ffffff;
}


body {

    margin: 0;

    font-family:
        Inter,
        Arial,
        Helvetica,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #f8fafc 0%,
            #eef2ff 100%
        );

    color: var(--text);
}


.content {

    width: 100%;

    max-width: 1450px;

    margin: 0 auto;

    padding: 30px 24px 50px;
}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

.page-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 25px;
}


.page-title {

    display: flex;

    align-items: center;

    gap: 15px;
}


.page-icon {

    width: 54px;

    height: 54px;

    border-radius: 16px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    color: white;

    font-size: 25px;

    box-shadow:
        0 10px 25px
        rgba(37, 99, 235, .20);
}


.page-title h1 {

    margin: 0;

    color: var(--dark);

    font-size: 30px;

    font-weight: 800;
}


.page-title p {

    margin: 6px 0 0;

    color: var(--muted);

    font-size: 14px;
}


.back-btn {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 11px 16px;

    background: white;

    color: #334155;

    border: 1px solid var(--border);

    border-radius: 10px;

    text-decoration: none;

    font-weight: 700;

    transition: .2s;
}


.back-btn:hover {

    background: #f8fafc;

    transform: translateY(-1px);
}


/*
|--------------------------------------------------------------------------
| Alerts
|--------------------------------------------------------------------------
*/

.alert {

    padding: 14px 17px;

    border-radius: 12px;

    margin-bottom: 20px;

    font-size: 14px;

    font-weight: 600;
}


.alert-success {

    background: #ecfdf5;

    color: #166534;

    border: 1px solid #bbf7d0;
}


.alert-error {

    background: #fef2f2;

    color: #991b1b;

    border: 1px solid #fecaca;
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 18px;

    margin-bottom: 22px;
}


.stat-card {

    position: relative;

    background: white;

    border:
        1px solid rgba(226, 232, 240, .8);

    border-radius: 18px;

    padding: 21px;

    box-shadow:
        0 10px 30px
        rgba(15, 23, 42, .06);

    overflow: hidden;

    transition: .2s;
}


.stat-card:hover {

    transform: translateY(-2px);

    box-shadow:
        0 15px 35px
        rgba(15, 23, 42, .09);
}


.stat-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;
}


.stat-card h4 {

    margin: 0;

    color: var(--muted);

    font-size: 13px;

    font-weight: 700;
}


.stat-icon {

    width: 42px;

    height: 42px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 12px;

    font-size: 19px;
}


.icon-blue {

    background: #eff6ff;

    color: #2563eb;
}


.icon-green {

    background: #ecfdf5;

    color: #16a34a;
}


.icon-orange {

    background: #fff7ed;

    color: #ea580c;
}


.icon-purple {

    background: #f5f3ff;

    color: #7c3aed;
}


.stat-number {

    margin-top: 16px;

    font-size: 27px;

    font-weight: 800;

    color: var(--dark);
}


.stat-small {

    margin-top: 5px;

    font-size: 12px;

    color: var(--muted);
}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

.filter-card {

    background: white;

    border:
        1px solid rgba(226, 232, 240, .8);

    border-radius: 16px;

    padding: 17px;

    margin-bottom: 22px;

    box-shadow:
        0 8px 25px
        rgba(15, 23, 42, .05);
}


.filter-form {

    display: grid;

    grid-template-columns:
        1fr
        190px
        auto
        auto;

    gap: 10px;

    align-items: center;
}


.search-box {

    position: relative;
}


.search-box input {

    width: 100%;

    padding:
        12px 14px;

    border:
        1px solid #cbd5e1;

    border-radius: 10px;

    outline: none;

    font-size: 14px;

    transition: .2s;
}


.search-box input:focus {

    border-color: var(--primary);

    box-shadow:
        0 0 0 3px
        rgba(37, 99, 235, .10);
}


.filter-select {

    width: 100%;

    padding:
        12px 13px;

    border:
        1px solid #cbd5e1;

    border-radius: 10px;

    background: white;

    color: #334155;

    font-size: 14px;

    outline: none;
}


.filter-btn {

    border: none;

    background: var(--primary);

    color: white;

    padding:
        12px 18px;

    border-radius: 10px;

    font-weight: 700;

    cursor: pointer;

    transition: .2s;
}


.filter-btn:hover {

    background: var(--primary-dark);
}


.clear-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding:
        11px 17px;

    border:
        1px solid #cbd5e1;

    border-radius: 10px;

    color: #475569;

    background: white;

    text-decoration: none;

    font-size: 14px;

    font-weight: 700;
}


.clear-btn:hover {

    background: #f8fafc;
}


/*
|--------------------------------------------------------------------------
| Table Card
|--------------------------------------------------------------------------
*/

.table-container {

    background: white;

    border:
        1px solid rgba(226, 232, 240, .8);

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 10px 35px
        rgba(15, 23, 42, .06);
}


.table-header {

    padding:
        20px 22px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    border-bottom:
        1px solid var(--border);
}


.table-header h3 {

    margin: 0;

    font-size: 19px;

    color: var(--dark);
}


.table-header p {

    margin: 5px 0 0;

    font-size: 13px;

    color: var(--muted);
}


.result-count {

    background: #eff6ff;

    color: #1d4ed8;

    padding: 7px 11px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 700;
}


.table-wrapper {

    width: 100%;

    overflow-x: auto;
}


table {

    width: 100%;

    min-width: 1250px;

    border-collapse: collapse;
}


thead {

    background:
        #f8fafc;
}


th {

    padding:
        13px 15px;

    text-align: left;

    color: #64748b;

    font-size: 11px;

    font-weight: 800;

    text-transform: uppercase;

    letter-spacing: .04em;

    border-bottom:
        1px solid var(--border);

    white-space: nowrap;
}


td {

    padding:
        16px 15px;

    border-bottom:
        1px solid #f1f5f9;

    vertical-align: middle;

    font-size: 13px;
}


tbody tr {

    transition: .15s;
}


tbody tr:hover {

    background: #f8fbff;
}


/*
|--------------------------------------------------------------------------
| Customer
|--------------------------------------------------------------------------
*/

.customer {

    display: flex;

    align-items: center;

    gap: 11px;

    min-width: 190px;
}


.avatar {

    width: 40px;

    height: 40px;

    flex: 0 0 40px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 12px;

    background:
        linear-gradient(
            135deg,
            #dbeafe,
            #e0e7ff
        );

    color: #3730a3;

    font-weight: 800;

    font-size: 14px;
}


.customer-name {

    font-weight: 800;

    color: #0f172a;

    margin-bottom: 3px;
}


.customer-email {

    color: #64748b;

    font-size: 11px;
}


/*
|--------------------------------------------------------------------------
| Booking
|--------------------------------------------------------------------------
*/

.booking-ref {

    color: #1d4ed8;

    font-weight: 800;

    text-decoration: none;
}


.booking-ref:hover {

    text-decoration: underline;
}


.service-name {

    color: #334155;

    font-weight: 700;
}


.booking-date {

    color: #0f172a;

    font-weight: 700;
}


.booking-time {

    color: #64748b;

    font-size: 11px;

    margin-top: 3px;
}


/*
|--------------------------------------------------------------------------
| Amount
|--------------------------------------------------------------------------
*/

.amount {

    font-size: 14px;

    font-weight: 800;

    color: #0f172a;
}


/*
|--------------------------------------------------------------------------
| Payment Method
|--------------------------------------------------------------------------
*/

.method {

    display: inline-flex;

    align-items: center;

    padding:
        6px 10px;

    background: #f8fafc;

    border:
        1px solid #e2e8f0;

    border-radius: 8px;

    font-size: 11px;

    font-weight: 800;

    color: #475569;
}


/*
|--------------------------------------------------------------------------
| Reference
|--------------------------------------------------------------------------
*/

.reference {

    font-family:
        Consolas,
        monospace;

    font-size: 11px;

    color: #475569;

    max-width: 170px;

    word-break: break-all;
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

.status {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding:
        6px 10px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 800;

    text-transform: capitalize;

    white-space: nowrap;
}


.status::before {

    content: '';

    width: 6px;

    height: 6px;

    border-radius: 50%;

    background: currentColor;
}


.status-paid {

    background: #dcfce7;

    color: #166534;
}


.status-pending {

    background: #fef3c7;

    color: #92400e;
}


.status-failed {

    background: #fee2e2;

    color: #991b1b;
}


.status-refunded {

    background: #ede9fe;

    color: #6d28d9;
}


.status-default {

    background: #e2e8f0;

    color: #475569;
}


/*
|--------------------------------------------------------------------------
| Action Buttons
|--------------------------------------------------------------------------
*/

.actions {

    display: flex;

    align-items: center;

    gap: 6px;

    flex-wrap: wrap;
}


.action-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding:
        7px 9px;

    border-radius: 8px;

    text-decoration: none;

    font-size: 11px;

    font-weight: 800;

    white-space: nowrap;
}


.action-view {

    background: #eff6ff;

    color: #1d4ed8;
}


.action-paid {

    background: #dcfce7;

    color: #166534;
}


.action-pending {

    background: #fef3c7;

    color: #92400e;
}


.action-failed {

    background: #fee2e2;

    color: #991b1b;
}


.action-refunded {

    background: #ede9fe;

    color: #6d28d9;
}


.action-btn:hover {

    filter: brightness(.96);

    transform: translateY(-1px);
}


/*
|--------------------------------------------------------------------------
| Empty
|--------------------------------------------------------------------------
*/

.empty {

    text-align: center;

    padding: 65px 30px;

    color: #64748b;
}


.empty-icon {

    width: 60px;

    height: 60px;

    margin:
        0 auto 15px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 18px;

    background: #f1f5f9;

    font-size: 25px;
}


.empty h3 {

    margin: 0 0 5px;

    color: #334155;

    font-size: 17px;
}


.empty p {

    margin: 0;

    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 1100px) {

    .stats {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }


    .filter-form {

        grid-template-columns:
            1fr 1fr;
    }

}


@media (max-width: 700px) {

    .content {

        padding:
            20px 14px 40px;
    }


    .page-header {

        flex-direction: column;

        align-items: flex-start;
    }


    .page-title h1 {

        font-size: 25px;
    }


    .stats {

        grid-template-columns: 1fr;
    }


    .filter-form {

        grid-template-columns: 1fr;
    }


    .table-header {

        align-items: flex-start;

        flex-direction: column;
    }

}

</style>

</head>


<body>


<div class="content">


    <!-- =========================================================
         PAGE HEADER
         ========================================================= -->

    <div class="page-header">

        <div class="page-title">

            <div class="page-icon">
                💳
            </div>

            <div>

                <h1>
                    Payments
                </h1>

                <p>
                    Monitor customer payments and booking transactions.
                </p>

            </div>

        </div>


        <a
            href="<?= paymentEsc(
                BASE_URL . '/admin/dashboard.php'
            ) ?>"
            class="back-btn"
        >
            ← Dashboard
        </a>

    </div>


    <!-- =========================================================
         ALERTS
         ========================================================= -->

    <?php if ($message !== ''): ?>

        <div class="alert alert-success">

            ✓
            <?= paymentEsc($message) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="alert alert-error">

            ⚠
            <?= paymentEsc($error) ?>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         STATISTICS
         ========================================================= -->

    <div class="stats">


        <!-- TOTAL -->

        <div class="stat-card">

            <div class="stat-top">

                <h4>
                    Total Transactions
                </h4>

                <div class="stat-icon icon-blue">
                    💳
                </div>

            </div>

            <div class="stat-number">

                <?= number_format(
                    $totalPayments
                ) ?>

            </div>

            <div class="stat-small">
                Matching transactions
            </div>

        </div>


        <!-- PAID -->

        <div class="stat-card">

            <div class="stat-top">

                <h4>
                    Successful Payments
                </h4>

                <div class="stat-icon icon-green">
                    ✓
                </div>

            </div>

            <div
                class="stat-number"
                style="color:#16a34a;"
            >

                <?= number_format(
                    $paidPayments
                ) ?>

            </div>

            <div class="stat-small">
                Successfully paid
            </div>

        </div>


        <!-- PENDING -->

        <div class="stat-card">

            <div class="stat-top">

                <h4>
                    Pending Payments
                </h4>

                <div class="stat-icon icon-orange">
                    ⏳
                </div>

            </div>

            <div
                class="stat-number"
                style="color:#d97706;"
            >

                <?= number_format(
                    $pendingPayments
                ) ?>

            </div>

            <div class="stat-small">

                KSh
                <?= number_format(
                    $pendingAmount,
                    2
                ) ?>

                pending

            </div>

        </div>


        <!-- REVENUE -->

        <div class="stat-card">

            <div class="stat-top">

                <h4>
                    Paid Revenue
                </h4>

                <div class="stat-icon icon-purple">
                    KSh
                </div>

            </div>

            <div
                class="stat-number"
                style="color:#7c3aed;"
            >

                KSh
                <?= number_format(
                    $totalRevenue,
                    2
                ) ?>

            </div>

            <div class="stat-small">
                Total successful payments
            </div>

        </div>

    </div>


    <!-- =========================================================
         FILTERS
         ========================================================= -->

    <div class="filter-card">

        <form
            method="GET"
            action=""
            class="filter-form"
        >

            <div class="search-box">

                <input
                    type="text"
                    name="search"
                    value="<?= paymentEsc($search) ?>"
                    placeholder="Search customer, booking, transaction or service..."
                >

            </div>


            <select
                name="status"
                class="filter-select"
            >

                <option value="">
                    All Payment Statuses
                </option>

                <option
                    value="pending"
                    <?= $statusFilter === 'pending'
                        ? 'selected'
                        : '' ?>
                >
                    Pending
                </option>

                <option
                    value="paid"
                    <?= $statusFilter === 'paid'
                        ? 'selected'
                        : '' ?>
                >
                    Paid
                </option>

                <option
                    value="failed"
                    <?= $statusFilter === 'failed'
                        ? 'selected'
                        : '' ?>
                >
                    Failed
                </option>

                <option
                    value="refunded"
                    <?= $statusFilter === 'refunded'
                        ? 'selected'
                        : '' ?>
                >
                    Refunded
                </option>

            </select>


            <button
                type="submit"
                class="filter-btn"
            >
                Search
            </button>


            <a
                href="<?= paymentEsc(
                    BASE_URL . '/admin/payments.php'
                ) ?>"
                class="clear-btn"
            >
                Clear
            </a>

        </form>

    </div>


    <!-- =========================================================
         PAYMENT TABLE
         ========================================================= -->

    <div class="table-container">


        <div class="table-header">

            <div>

                <h3>
                    Payment Transactions
                </h3>

                <p>
                    Payments are connected directly to customers,
                    bookings and services.
                </p>

            </div>


            <div class="result-count">

                <?= number_format(
                    count($payments)
                ) ?>

                Records

            </div>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Payment
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Booking
                        </th>

                        <th>
                            Service
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Method
                        </th>

                        <th>
                            Reference
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Date
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (!empty($payments)): ?>


                    <?php foreach ($payments as $payment): ?>


                        <?php

                        $paymentId =
                            (int) (
                                $payment['id']
                                ?? 0
                            );


                        $bookingId =
                            (int) (
                                $payment['booking_id']
                                ?? 0
                            );


                        $customer =
                            $payment['customer']
                            ?? 'Unknown Customer';


                        $customerEmail =
                            $payment['customer_email']
                            ?? '';


                        $amount =
                            (float) (
                                $payment['amount']
                                ?? 0
                            );


                        $method =
                            $payment['payment_method']
                            ?? '';


                        $reference =
                            $payment[
                                'transaction_reference'
                            ]
                            ?? '';


                        $transactionId =
                            $payment[
                                'transaction_id'
                            ]
                            ?? '';


                        $status =
                            strtolower(
                                trim(
                                    (string) (
                                        $payment[
                                            'payment_status'
                                        ]
                                        ?? 'pending'
                                    )
                                )
                            );


                        $statusClass =
                            paymentStatusClass(
                                $status
                            );


                        $bookingReference =
                            $payment[
                                'booking_reference'
                            ]
                            ?? '';


                        $service =
                            $payment['service']
                            ?? 'Unknown Service';


                        $bookingDate =
                            $payment[
                                'booking_date'
                            ]
                            ?? '';


                        $bookingTime =
                            $payment[
                                'booking_time'
                            ]
                            ?? '';


                        $createdAt =
                            $payment['created_at']
                            ?? '';


                        $paidAt =
                            $payment['paid_at']
                            ?? '';


                        /*
                        |--------------------------------------------------------------------------
                        | Customer Avatar
                        |--------------------------------------------------------------------------
                        */

                        $customerInitial =
                            strtoupper(
                                substr(
                                    trim(
                                        $customer
                                    ),
                                    0,
                                    1
                                )
                            );

                        ?>


                        <tr>


                            <!-- PAYMENT -->

                            <td>

                                <strong>
                                    #<?= $paymentId ?>
                                </strong>

                                <?php if (!empty($transactionId)): ?>

                                    <div
                                        style="
                                            margin-top:4px;
                                            color:#64748b;
                                            font-size:10px;
                                        "
                                    >

                                        TX:
                                        <?= paymentEsc(
                                            $transactionId
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- CUSTOMER -->

                            <td>

                                <div class="customer">

                                    <div class="avatar">

                                        <?= paymentEsc(
                                            $customerInitial
                                        ) ?>

                                    </div>

                                    <div>

                                        <div class="customer-name">

                                            <?= paymentEsc(
                                                $customer
                                            ) ?>

                                        </div>

                                        <div class="customer-email">

                                            <?= paymentEsc(
                                                $customerEmail
                                            ) ?>

                                        </div>

                                    </div>

                                </div>

                            </td>


                            <!-- BOOKING -->

                            <td>

                                <?php if ($bookingId > 0): ?>

                                    <a
                                        href="<?= paymentEsc(
                                            BASE_URL .
                                            '/admin/booking-view.php?id=' .
                                            $bookingId
                                        ) ?>"
                                        class="booking-ref"
                                    >

                                        <?= paymentEsc(
                                            $bookingReference
                                            ?: '#' . $bookingId
                                        ) ?>

                                    </a>


                                    <div class="booking-date">

                                        <?= paymentEsc(
                                            paymentFormatDate(
                                                $bookingDate,
                                                'd M Y'
                                            )
                                        ) ?>

                                    </div>


                                    <div class="booking-time">

                                        <?= paymentEsc(
                                            paymentFormatDate(
                                                $bookingDate .
                                                ' ' .
                                                $bookingTime,
                                                'H:i'
                                            )
                                        ) ?>

                                    </div>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <!-- SERVICE -->

                            <td>

                                <span class="service-name">

                                    <?= paymentEsc(
                                        $service
                                    ) ?>

                                </span>

                            </td>


                            <!-- AMOUNT -->

                            <td>

                                <span class="amount">

                                    KSh
                                    <?= number_format(
                                        $amount,
                                        2
                                    ) ?>

                                </span>

                            </td>


                            <!-- METHOD -->

                            <td>

                                <span class="method">

                                    <?= paymentEsc(
                                        paymentMethodLabel(
                                            $method
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- REFERENCE -->

                            <td>

                                <div class="reference">

                                    <?= paymentEsc(
                                        $reference
                                        ?: '-'
                                    ) ?>

                                </div>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status status-<?= paymentEsc(
                                        $statusClass
                                    ) ?>"
                                >

                                    <?= paymentEsc(
                                        ucfirst(
                                            $status
                                        )
                                    ) ?>

                                </span>


                                <?php if ($status === 'paid' && !empty($paidAt)): ?>

                                    <div
                                        style="
                                            margin-top:5px;
                                            font-size:10px;
                                            color:#64748b;
                                        "
                                    >

                                        Paid:
                                        <?= paymentEsc(
                                            paymentFormatDate(
                                                $paidAt
                                            )
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <!-- CREATED -->

                            <td>

                                <?= paymentEsc(
                                    paymentFormatDate(
                                        $createdAt
                                    )
                                ) ?>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="actions">


                                    <!-- VIEW BOOKING -->

                                    <?php if ($bookingId > 0): ?>

                                        <a
                                            href="<?= paymentEsc(
                                                BASE_URL .
                                                '/admin/booking-view.php?id=' .
                                                $bookingId
                                            ) ?>"
                                            class="action-btn action-view"
                                        >
                                            View
                                        </a>

                                    <?php endif; ?>


                                    <!-- MARK PAID -->

                                    <?php if ($status !== 'paid'): ?>

                                        <a
                                            href="<?= paymentEsc(
                                                BASE_URL .
                                                '/admin/payments.php?action=status&id=' .
                                                $paymentId .
                                                '&status=paid'
                                            ) ?>"
                                            class="action-btn action-paid"
                                            onclick="
                                                return confirm(
                                                    'Mark this payment as PAID?'
                                                );
                                            "
                                        >
                                            Paid
                                        </a>

                                    <?php endif; ?>


                                    <!-- MARK PENDING -->

                                    <?php if ($status !== 'pending'): ?>

                                        <a
                                            href="<?= paymentEsc(
                                                BASE_URL .
                                                '/admin/payments.php?action=status&id=' .
                                                $paymentId .
                                                '&status=pending'
                                            ) ?>"
                                            class="action-btn action-pending"
                                            onclick="
                                                return confirm(
                                                    'Change this payment to PENDING?'
                                                );
                                            "
                                        >
                                            Pending
                                        </a>

                                    <?php endif; ?>


                                    <!-- MARK FAILED -->

                                    <?php if ($status !== 'failed'): ?>

                                        <a
                                            href="<?= paymentEsc(
                                                BASE_URL .
                                                '/admin/payments.php?action=status&id=' .
                                                $paymentId .
                                                '&status=failed'
                                            ) ?>"
                                            class="action-btn action-failed"
                                            onclick="
                                                return confirm(
                                                    'Mark this payment as FAILED?'
                                                );
                                            "
                                        >
                                            Failed
                                        </a>

                                    <?php endif; ?>


                                    <!-- REFUND -->

                                    <?php if ($status !== 'refunded'): ?>

                                        <a
                                            href="<?= paymentEsc(
                                                BASE_URL .
                                                '/admin/payments.php?action=status&id=' .
                                                $paymentId .
                                                '&status=refunded'
                                            ) ?>"
                                            class="action-btn action-refunded"
                                            onclick="
                                                return confirm(
                                                    'Mark this payment as REFUNDED?'
                                                );
                                            "
                                        >
                                            Refund
                                        </a>

                                    <?php endif; ?>


                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="10"
                            class="empty"
                        >

                            <div class="empty-icon">
                                💳
                            </div>

                            <h3>
                                No payment transactions
                            </h3>

                            <p>

                                <?php if ($search !== '' || $statusFilter !== ''): ?>

                                    No payments match your current search or filter.

                                <?php else: ?>

                                    Customer payments will appear here once transactions are created.

                                <?php endif; ?>

                            </p>

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


</div>


</body>

</html>