<?php

/*
|--------------------------------------------------------------------------
| BookEase - Admin Customers
| File: admin/customers.php
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


$adminRole = $_SESSION['role'] ?? '';

if (!in_array($adminRole, ['admin', 'staff'], true)) {

    header(
        'Location: ' . BASE_URL . '/customer/dashboard.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

try {

    $database = new Database();

    $conn = $database->getConnection();

    if (!$conn instanceof PDO) {
        throw new Exception('Database connection was not created correctly.');
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
| HELPERS
|--------------------------------------------------------------------------
*/

function customerSafe($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function customerStatusClass($status)
{
    $status = strtolower(trim((string) $status));

    $allowed = [
        'active',
        'inactive',
        'suspended'
    ];

    return in_array($status, $allowed, true)
        ? $status
        : 'inactive';
}


function customerDate($date)
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return '-';
    }

    return date('d M Y', $timestamp);
}


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

$message = '';
$error = '';


/*
|--------------------------------------------------------------------------
| HANDLE CUSTOMER ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = trim($_POST['action'] ?? '');

    $customerId = (int) ($_POST['customer_id'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | Validate Customer ID
    |--------------------------------------------------------------------------
    */

    if ($customerId <= 0) {

        $error = 'Invalid customer account.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Make sure selected user is actually a customer
            |--------------------------------------------------------------------------
            */

            $customerCheck = $conn->prepare("
                SELECT
                    id,
                    name,
                    email,
                    phone,
                    status,
                    role
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $customerCheck->execute([
                $customerId
            ]);

            $existingCustomer =
                $customerCheck->fetch(PDO::FETCH_ASSOC);


            if (!$existingCustomer) {

                $error = 'Customer account was not found.';

            } elseif (
                strtolower(
                    trim(
                        $existingCustomer['role'] ?? ''
                    )
                ) !== 'customer'
            ) {

                $error =
                    'This account is not a customer account.';

            } else {


                /*
                |--------------------------------------------------------------------------
                | UPDATE CUSTOMER PROFILE
                |--------------------------------------------------------------------------
                */

                if ($action === 'update_customer') {

                    $name =
                        trim(
                            $_POST['name'] ?? ''
                        );

                    $email =
                        trim(
                            $_POST['email'] ?? ''
                        );

                    $phone =
                        trim(
                            $_POST['phone'] ?? ''
                        );

                    $status =
                        strtolower(
                            trim(
                                $_POST['status'] ?? 'active'
                            )
                        );


                    if ($name === '') {

                        throw new Exception(
                            'Customer name is required.'
                        );
                    }


                    if (
                        $email === '' ||
                        !filter_var(
                            $email,
                            FILTER_VALIDATE_EMAIL
                        )
                    ) {

                        throw new Exception(
                            'Please enter a valid email address.'
                        );
                    }


                    if (
                        !in_array(
                            $status,
                            [
                                'active',
                                'inactive',
                                'suspended'
                            ],
                            true
                        )
                    ) {

                        $status = 'active';
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Check duplicate email
                    |--------------------------------------------------------------------------
                    */

                    $emailCheck = $conn->prepare("
                        SELECT id
                        FROM users
                        WHERE email = ?
                        AND id != ?
                        LIMIT 1
                    ");

                    $emailCheck->execute([
                        $email,
                        $customerId
                    ]);


                    if ($emailCheck->fetch()) {

                        throw new Exception(
                            'Another account is already using this email address.'
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Update customer
                    |--------------------------------------------------------------------------
                    */

                    $updateCustomer = $conn->prepare("
                        UPDATE users
                        SET
                            name = ?,
                            email = ?,
                            phone = ?,
                            status = ?
                        WHERE id = ?
                        AND role = 'customer'
                    ");


                    $updateCustomer->execute([
                        $name,
                        $email,
                        $phone,
                        $status,
                        $customerId
                    ]);


                    $message =
                        'Customer account updated successfully.';
                }


                /*
                |--------------------------------------------------------------------------
                | CHANGE PASSWORD
                |--------------------------------------------------------------------------
                */

                elseif ($action === 'change_password') {

                    $password =
                        $_POST['password'] ?? '';

                    $confirmPassword =
                        $_POST['confirm_password'] ?? '';


                    if (strlen($password) < 6) {

                        throw new Exception(
                            'Password must contain at least 6 characters.'
                        );
                    }


                    if ($password !== $confirmPassword) {

                        throw new Exception(
                            'Passwords do not match.'
                        );
                    }


                    $hashedPassword =
                        password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );


                    $passwordStmt = $conn->prepare("
                        UPDATE users
                        SET password = ?
                        WHERE id = ?
                        AND role = 'customer'
                    ");


                    $passwordStmt->execute([
                        $hashedPassword,
                        $customerId
                    ]);


                    $message =
                        'Customer password changed successfully.';
                }


                /*
                |--------------------------------------------------------------------------
                | ACTIVATE
                |--------------------------------------------------------------------------
                */

                elseif ($action === 'activate') {

                    $stmt = $conn->prepare("
                        UPDATE users
                        SET status = 'active'
                        WHERE id = ?
                        AND role = 'customer'
                    ");

                    $stmt->execute([
                        $customerId
                    ]);


                    $message =
                        'Customer account activated successfully.';
                }


                /*
                |--------------------------------------------------------------------------
                | DEACTIVATE
                |--------------------------------------------------------------------------
                */

                elseif ($action === 'deactivate') {

                    $stmt = $conn->prepare("
                        UPDATE users
                        SET status = 'inactive'
                        WHERE id = ?
                        AND role = 'customer'
                    ");

                    $stmt->execute([
                        $customerId
                    ]);


                    $message =
                        'Customer account deactivated successfully.';
                }


                /*
                |--------------------------------------------------------------------------
                | SUSPEND
                |--------------------------------------------------------------------------
                */

                elseif ($action === 'suspend') {

                    $stmt = $conn->prepare("
                        UPDATE users
                        SET status = 'suspended'
                        WHERE id = ?
                        AND role = 'customer'
                    ");

                    $stmt->execute([
                        $customerId
                    ]);


                    $message =
                        'Customer account suspended successfully.';
                }


                /*
                |--------------------------------------------------------------------------
                | DELETE CUSTOMER
                |--------------------------------------------------------------------------
                */

                elseif ($action === 'delete_customer') {

                    /*
                    |--------------------------------------------------------------------------
                    | Count customer's bookings
                    |--------------------------------------------------------------------------
                    */

                    $bookingCheck = $conn->prepare("
                        SELECT COUNT(*)
                        FROM bookings
                        WHERE user_id = ?
                    ");

                    $bookingCheck->execute([
                        $customerId
                    ]);

                    $customerBookingCount =
                        (int) $bookingCheck->fetchColumn();


                    /*
                    |--------------------------------------------------------------------------
                    | Transaction
                    |--------------------------------------------------------------------------
                    */

                    $conn->beginTransaction();


                    /*
                    | Delete notifications first
                    */

                    try {

                        $stmt = $conn->prepare("
                            DELETE FROM notifications
                            WHERE user_id = ?
                        ");

                        $stmt->execute([
                            $customerId
                        ]);

                    } catch (Throwable $ignore) {
                        /*
                        | Notifications table may have
                        | different structure.
                        */
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Delete payments connected to bookings
                    |--------------------------------------------------------------------------
                    */

                    if ($customerBookingCount > 0) {

                        try {

                            $paymentDelete = $conn->prepare("
                                DELETE p
                                FROM payments p
                                INNER JOIN bookings b
                                    ON p.booking_id = b.id
                                WHERE b.user_id = ?
                            ");

                            $paymentDelete->execute([
                                $customerId
                            ]);

                        } catch (Throwable $ignore) {
                            /*
                            | Continue if payment relationship
                            | does not exist.
                            */
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Delete customer's bookings
                        |--------------------------------------------------------------------------
                        */

                        $bookingDelete = $conn->prepare("
                            DELETE FROM bookings
                            WHERE user_id = ?
                        ");

                        $bookingDelete->execute([
                            $customerId
                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Delete customer
                    |--------------------------------------------------------------------------
                    */

                    $deleteStmt = $conn->prepare("
                        DELETE FROM users
                        WHERE id = ?
                        AND role = 'customer'
                    ");

                    $deleteStmt->execute([
                        $customerId
                    ]);


                    $conn->commit();


                    $message =
                        'Customer account and associated booking records were deleted successfully.';
                }

            }

        } catch (Throwable $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $error =
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET CUSTOMERS
|--------------------------------------------------------------------------
*/

$customers = [];

try {

    $stmt = $conn->prepare("
        SELECT

            u.id,
            u.name,
            u.email,
            u.phone,
            u.status,
            u.created_at,

            COUNT(DISTINCT b.id) AS booking_count,

            COALESCE(
                SUM(
                    CASE
                        WHEN b.status = 'pending'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS pending_bookings,

            COALESCE(
                SUM(
                    CASE
                        WHEN b.status = 'confirmed'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS confirmed_bookings,

            COALESCE(
                SUM(
                    CASE
                        WHEN b.status = 'completed'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS completed_bookings,

            COALESCE(
                SUM(
                    CASE
                        WHEN b.status = 'cancelled'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS cancelled_bookings,

            COALESCE(
                SUM(
                    CASE
                        WHEN b.status != 'cancelled'
                        THEN COALESCE(
                            b.total_amount,
                            b.amount,
                            0
                        )
                        ELSE 0
                    END
                ),
                0
            ) AS total_booking_amount,

            MAX(b.booking_date) AS last_booking

        FROM users u

        LEFT JOIN bookings b
            ON b.user_id = u.id

        WHERE u.role = 'customer'

        GROUP BY
            u.id,
            u.name,
            u.email,
            u.phone,
            u.status,
            u.created_at

        ORDER BY u.id DESC
    ");


    $stmt->execute();

    $customers =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $error =
        'Unable to load customers: ' .
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalCustomers = count($customers);

$activeCustomers = 0;
$inactiveCustomers = 0;
$suspendedCustomers = 0;

$totalBookings = 0;
$totalRevenue = 0;

$totalPending = 0;
$totalConfirmed = 0;
$totalCompleted = 0;
$totalCancelled = 0;


foreach ($customers as $customer) {

    $customerStatus =
        strtolower(
            trim(
                $customer['status'] ?? 'active'
            )
        );


    if ($customerStatus === 'active') {

        $activeCustomers++;

    } elseif ($customerStatus === 'suspended') {

        $suspendedCustomers++;

    } else {

        $inactiveCustomers++;
    }


    $totalBookings +=
        (int) (
            $customer['booking_count'] ?? 0
        );


    $totalRevenue +=
        (float) (
            $customer['total_booking_amount'] ?? 0
        );


    $totalPending +=
        (int) (
            $customer['pending_bookings'] ?? 0
        );


    $totalConfirmed +=
        (int) (
            $customer['confirmed_bookings'] ?? 0
        );


    $totalCompleted +=
        (int) (
            $customer['completed_bookings'] ?? 0
        );


    $totalCancelled +=
        (int) (
            $customer['cancelled_bookings'] ?? 0
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
    Customers | BookEase Admin
</title>


<style>

/* ==========================================================================
   RESET
   ========================================================================== */

* {
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    margin: 0;
    font-family:
        Inter,
        ui-sans-serif,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;
    background: #f5f7fb;
    color: #172033;
}


/* ==========================================================================
   LAYOUT
   ========================================================================== */

.content {
    width: 100%;
    max-width: 1450px;
    margin: 0 auto;
    padding: 32px;
}


/* ==========================================================================
   HEADER
   ========================================================================== */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 28px;
}

.page-title h1 {
    margin: 0;
    font-size: 32px;
    font-weight: 800;
    letter-spacing: -0.8px;
    color: #111827;
}

.page-title p {
    margin: 7px 0 0;
    color: #64748b;
    font-size: 14px;
}

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.header-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 16px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    transition: .2s ease;
}

.header-btn.primary {
    background: #2563eb;
    color: white;
    box-shadow: 0 5px 15px rgba(37, 99, 235, .18);
}

.header-btn.primary:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}

.header-btn.light {
    background: white;
    color: #334155;
    border: 1px solid #e2e8f0;
}

.header-btn.light:hover {
    background: #f8fafc;
}


/* ==========================================================================
   ALERTS
   ========================================================================== */

.alert {
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 22px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}


/* ==========================================================================
   STATISTICS
   ========================================================================== */

.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 22px;
}

.stat-card {
    position: relative;
    overflow: hidden;
    background: white;
    border: 1px solid #edf0f5;
    border-radius: 16px;
    padding: 22px;
    box-shadow: 0 8px 30px rgba(15, 23, 42, .045);
}

.stat-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-icon {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 20px;
    background: #eff6ff;
}

.stat-card:nth-child(2) .stat-icon {
    background: #ecfdf5;
}

.stat-card:nth-child(3) .stat-icon {
    background: #fff7ed;
}

.stat-card:nth-child(4) .stat-icon {
    background: #f5f3ff;
}

.stat-label {
    margin-top: 17px;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}

.stat-value {
    margin-top: 5px;
    font-size: 28px;
    font-weight: 800;
    letter-spacing: -.5px;
    color: #111827;
}

.stat-sub {
    margin-top: 6px;
    font-size: 12px;
    color: #94a3b8;
}


/* ==========================================================================
   SUMMARY
   ========================================================================== */

.summary-card {
    background: white;
    border: 1px solid #edf0f5;
    border-radius: 16px;
    padding: 22px;
    margin-bottom: 22px;
    box-shadow: 0 8px 30px rgba(15, 23, 42, .045);
}

.summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.summary-header h3 {
    margin: 0;
    font-size: 17px;
}

.summary-header span {
    color: #94a3b8;
    font-size: 12px;
}

.summary-items {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.summary-item {
    padding: 15px;
    background: #f8fafc;
    border-radius: 11px;
    border: 1px solid #eef2f7;
}

.summary-item span {
    display: block;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 5px;
}

.summary-item strong {
    font-size: 22px;
}


/* ==========================================================================
   CUSTOMER PANEL
   ========================================================================== */

.customer-panel {
    background: white;
    border: 1px solid #edf0f5;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(15, 23, 42, .045);
}

.panel-header {
    padding: 20px 22px;
    border-bottom: 1px solid #eef2f7;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.panel-title h3 {
    margin: 0;
    font-size: 18px;
}

.panel-title p {
    margin: 5px 0 0;
    color: #94a3b8;
    font-size: 12px;
}

.tools {
    display: flex;
    gap: 9px;
    flex-wrap: wrap;
}

.search-box {
    position: relative;
}

.search-box input {
    width: 250px;
    height: 40px;
    padding: 0 13px 0 38px;
    border: 1px solid #dbe2ea;
    border-radius: 9px;
    outline: none;
    font-size: 13px;
    background: #f8fafc;
}

.search-box input:focus {
    border-color: #2563eb;
    background: white;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .08);
}

.search-icon {
    position: absolute;
    left: 13px;
    top: 10px;
    color: #94a3b8;
}

.filter-select {
    height: 40px;
    padding: 0 12px;
    border: 1px solid #dbe2ea;
    border-radius: 9px;
    background: white;
    color: #334155;
    outline: none;
}


/* ==========================================================================
   TABLE
   ========================================================================== */

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 1200px;
    border-collapse: collapse;
}

thead {
    background: #fafbfc;
}

th {
    text-align: left;
    padding: 13px 17px;
    color: #64748b;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .5px;
    border-bottom: 1px solid #eef2f7;
}

td {
    padding: 16px 17px;
    border-bottom: 1px solid #f0f2f5;
    vertical-align: middle;
}

tbody tr {
    transition: .15s ease;
}

tbody tr:hover {
    background: #fafcff;
}


/* ==========================================================================
   CUSTOMER
   ========================================================================== */

.customer-cell {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 220px;
}

.avatar {
    width: 40px;
    height: 40px;
    border-radius: 11px;
    background: linear-gradient(
        135deg,
        #2563eb,
        #7c3aed
    );
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 800;
    flex-shrink: 0;
}

.customer-name {
    font-size: 13px;
    font-weight: 750;
    color: #172033;
}

.customer-email {
    margin-top: 3px;
    font-size: 11px;
    color: #94a3b8;
}


/* ==========================================================================
   STATUS
   ========================================================================== */

.status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 750;
}

.status::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.status-active {
    background: #ecfdf5;
    color: #047857;
}

.status-active::before {
    background: #10b981;
}

.status-inactive {
    background: #f1f5f9;
    color: #475569;
}

.status-inactive::before {
    background: #94a3b8;
}

.status-suspended {
    background: #fff7ed;
    color: #c2410c;
}

.status-suspended::before {
    background: #f97316;
}


/* ==========================================================================
   BOOKING COUNT
   ========================================================================== */

.booking-count {
    display: inline-flex;
    min-width: 32px;
    justify-content: center;
    padding: 6px 9px;
    border-radius: 8px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 800;
}


/* ==========================================================================
   BOOKING MINI STATUS
   ========================================================================== */

.booking-mini {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    max-width: 180px;
}

.mini {
    padding: 4px 7px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 800;
}

.mini-pending {
    background: #fef3c7;
    color: #92400e;
}

.mini-confirmed {
    background: #dbeafe;
    color: #1d4ed8;
}

.mini-completed {
    background: #dcfce7;
    color: #166534;
}

.mini-cancelled {
    background: #fee2e2;
    color: #991b1b;
}


/* ==========================================================================
   AMOUNT
   ========================================================================== */

.amount {
    font-weight: 800;
    color: #111827;
    white-space: nowrap;
}


/* ==========================================================================
   ACTIONS
   ========================================================================== */

.actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.action-btn {
    border: 0;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 10px;
    border-radius: 7px;
    font-size: 11px;
    font-weight: 750;
    transition: .15s ease;
}

.action-btn:hover {
    transform: translateY(-1px);
}

.action-view {
    background: #eff6ff;
    color: #1d4ed8;
}

.action-edit {
    background: #f3e8ff;
    color: #7e22ce;
}

.action-password {
    background: #fff7ed;
    color: #c2410c;
}

.action-delete {
    background: #fef2f2;
    color: #b91c1c;
}


/* ==========================================================================
   EMPTY
   ========================================================================== */

.empty {
    padding: 60px 30px;
    text-align: center;
    color: #94a3b8;
}

.empty-icon {
    font-size: 38px;
    margin-bottom: 10px;
}

.empty h3 {
    margin: 0 0 5px;
    color: #475569;
}

.empty p {
    margin: 0;
    font-size: 13px;
}


/* ==========================================================================
   MODALS
   ========================================================================== */

.modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(15, 23, 42, .55);
    backdrop-filter: blur(5px);
    padding: 20px;
    overflow-y: auto;
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-card {
    width: 100%;
    max-width: 500px;
    background: white;
    border-radius: 18px;
    box-shadow: 0 25px 80px rgba(0,0,0,.2);
    overflow: hidden;
    animation: modalIn .2s ease;
}

@keyframes modalIn {
    from {
        opacity: 0;
        transform: translateY(10px) scale(.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    padding: 20px 22px;
    border-bottom: 1px solid #eef2f7;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.close-btn {
    border: 0;
    background: #f1f5f9;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 17px;
    color: #475569;
}

.modal-body {
    padding: 22px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 750;
    color: #334155;
}

.form-control {
    width: 100%;
    height: 43px;
    padding: 0 12px;
    border: 1px solid #dbe2ea;
    border-radius: 9px;
    outline: none;
    font-size: 13px;
    color: #172033;
}

textarea.form-control {
    height: 90px;
    padding-top: 11px;
    resize: vertical;
}

.form-control:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,.08);
}

.modal-footer {
    padding: 16px 22px;
    border-top: 1px solid #eef2f7;
    display: flex;
    justify-content: flex-end;
    gap: 9px;
}

.btn-modal {
    border: 0;
    padding: 10px 16px;
    border-radius: 9px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 750;
}

.btn-cancel {
    background: #f1f5f9;
    color: #475569;
}

.btn-save {
    background: #2563eb;
    color: white;
}

.btn-save:hover {
    background: #1d4ed8;
}

.btn-danger {
    background: #dc2626;
    color: white;
}


/* ==========================================================================
   RESPONSIVE
   ========================================================================== */

@media (max-width: 1100px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .summary-items {
        grid-template-columns: repeat(2, 1fr);
    }

    .panel-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .tools {
        width: 100%;
    }

    .search-box {
        flex: 1;
    }

    .search-box input {
        width: 100%;
    }
}

@media (max-width: 700px) {

    .content {
        padding: 20px 14px;
    }

    .page-header {
        flex-direction: column;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .summary-items {
        grid-template-columns: 1fr;
    }

    .header-actions {
        width: 100%;
    }

    .header-btn {
        flex: 1;
        justify-content: center;
    }

    .tools {
        flex-direction: column;
    }

    .filter-select {
        width: 100%;
    }

    .page-title h1 {
        font-size: 26px;
    }
}

</style>

</head>


<body>


<div class="content">


    <!-- ================================================================
         HEADER
         ================================================================ -->

    <div class="page-header">

        <div class="page-title">

            <h1>
                Customers
            </h1>

            <p>
                Manage customer accounts, credentials, bookings and activity.
            </p>

        </div>


        <div class="header-actions">

            <a
                href="<?= customerSafe(
                    BASE_URL . '/admin/dashboard.php'
                ) ?>"
                class="header-btn light"
            >
                ← Dashboard
            </a>

            <a
                href="<?= customerSafe(
                    BASE_URL . '/admin/bookings.php'
                ) ?>"
                class="header-btn primary"
            >
                📅 View Bookings
            </a>

        </div>

    </div>


    <!-- ================================================================
         ALERTS
         ================================================================ -->

    <?php if ($message !== ''): ?>

        <div class="alert alert-success">

            ✓

            <?= customerSafe($message) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="alert alert-error">

            ⚠

            <?= customerSafe($error) ?>

        </div>

    <?php endif; ?>


    <!-- ================================================================
         STATISTICS
         ================================================================ -->

    <div class="stats">


        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-icon">
                    👥
                </div>

            </div>

            <div class="stat-label">
                Total Customers
            </div>

            <div class="stat-value">
                <?= number_format($totalCustomers) ?>
            </div>

            <div class="stat-sub">
                Registered customer accounts
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-icon">
                    ✓
                </div>

            </div>

            <div class="stat-label">
                Active Customers
            </div>

            <div class="stat-value">
                <?= number_format($activeCustomers) ?>
            </div>

            <div class="stat-sub">
                Currently active accounts
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-icon">
                    📅
                </div>

            </div>

            <div class="stat-label">
                Total Bookings
            </div>

            <div class="stat-value">
                <?= number_format($totalBookings) ?>
            </div>

            <div class="stat-sub">
                Customer booking activity
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <div class="stat-icon">
                    💰
                </div>

            </div>

            <div class="stat-label">
                Booking Revenue
            </div>

            <div class="stat-value">

                KSh
                <?= number_format(
                    $totalRevenue,
                    2
                ) ?>

            </div>

            <div class="stat-sub">
                Excluding cancelled bookings
            </div>

        </div>


    </div>


    <!-- ================================================================
         BOOKING SUMMARY
         ================================================================ -->

    <div class="summary-card">

        <div class="summary-header">

            <h3>
                Booking Overview
            </h3>

            <span>
                All customer accounts
            </span>

        </div>


        <div class="summary-items">


            <div class="summary-item">

                <span>
                    Pending
                </span>

                <strong style="color:#d97706;">

                    <?= number_format(
                        $totalPending
                    ) ?>

                </strong>

            </div>


            <div class="summary-item">

                <span>
                    Confirmed
                </span>

                <strong style="color:#2563eb;">

                    <?= number_format(
                        $totalConfirmed
                    ) ?>

                </strong>

            </div>


            <div class="summary-item">

                <span>
                    Completed
                </span>

                <strong style="color:#16a34a;">

                    <?= number_format(
                        $totalCompleted
                    ) ?>

                </strong>

            </div>


            <div class="summary-item">

                <span>
                    Cancelled
                </span>

                <strong style="color:#dc2626;">

                    <?= number_format(
                        $totalCancelled
                    ) ?>

                </strong>

            </div>


        </div>

    </div>


    <!-- ================================================================
         CUSTOMER PANEL
         ================================================================ -->

    <div class="customer-panel">


        <div class="panel-header">

            <div class="panel-title">

                <h3>
                    Customer Accounts
                </h3>

                <p>
                    Manage customers and their BookEase accounts.
                </p>

            </div>


            <div class="tools">


                <div class="search-box">

                    <span class="search-icon">
                        🔎
                    </span>

                    <input
                        type="text"
                        id="customerSearch"
                        placeholder="Search name, email or phone..."
                    >

                </div>


                <select
                    id="statusFilter"
                    class="filter-select"
                >

                    <option value="all">
                        All Status
                    </option>

                    <option value="active">
                        Active
                    </option>

                    <option value="inactive">
                        Inactive
                    </option>

                    <option value="suspended">
                        Suspended
                    </option>

                </select>


            </div>

        </div>


        <div class="table-wrapper">


            <table id="customersTable">


                <thead>

                    <tr>

                        <th>
                            Customer
                        </th>

                        <th>
                            Phone
                        </th>

                        <th>
                            Account
                        </th>

                        <th>
                            Bookings
                        </th>

                        <th>
                            Booking Status
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Last Booking
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (!empty($customers)): ?>


                    <?php foreach ($customers as $customer): ?>

                        <?php

                        $customerId =
                            (int) (
                                $customer['id'] ?? 0
                            );


                        $customerName =
                            $customer['name']
                            ?? 'Unknown Customer';


                        $customerEmail =
                            $customer['email']
                            ?? '';


                        $customerPhone =
                            $customer['phone']
                            ?? '';


                        $customerStatus =
                            strtolower(
                                trim(
                                    $customer['status']
                                    ?? 'active'
                                )
                            );


                        $statusClass =
                            customerStatusClass(
                                $customerStatus
                            );


                        $bookingCount =
                            (int) (
                                $customer['booking_count']
                                ?? 0
                            );


                        $pending =
                            (int) (
                                $customer['pending_bookings']
                                ?? 0
                            );


                        $confirmed =
                            (int) (
                                $customer['confirmed_bookings']
                                ?? 0
                            );


                        $completed =
                            (int) (
                                $customer['completed_bookings']
                                ?? 0
                            );


                        $cancelled =
                            (int) (
                                $customer['cancelled_bookings']
                                ?? 0
                            );


                        $totalAmount =
                            (float) (
                                $customer['total_booking_amount']
                                ?? 0
                            );


                        $lastBooking =
                            $customer['last_booking']
                            ?? '';


                        $initial =
                            strtoupper(
                                substr(
                                    trim($customerName),
                                    0,
                                    1
                                )
                            );

                        ?>


                        <tr
                            data-name="<?= customerSafe(
                                strtolower($customerName)
                            ) ?>"
                            data-email="<?= customerSafe(
                                strtolower($customerEmail)
                            ) ?>"
                            data-phone="<?= customerSafe(
                                strtolower($customerPhone)
                            ) ?>"
                            data-status="<?= customerSafe(
                                $customerStatus
                            ) ?>"
                        >


                            <!-- CUSTOMER -->

                            <td>

                                <div class="customer-cell">

                                    <div class="avatar">

                                        <?= customerSafe(
                                            $initial ?: '?'
                                        ) ?>

                                    </div>


                                    <div>

                                        <div class="customer-name">

                                            <?= customerSafe(
                                                $customerName
                                            ) ?>

                                        </div>

                                        <div class="customer-email">

                                            <?= customerSafe(
                                                $customerEmail
                                            ) ?>

                                        </div>

                                    </div>

                                </div>

                            </td>


                            <!-- PHONE -->

                            <td>

                                <?= customerSafe(
                                    $customerPhone ?: '-'
                                ) ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status status-<?= customerSafe(
                                        $statusClass
                                    ) ?>"
                                >

                                    <?= customerSafe(
                                        ucfirst(
                                            $customerStatus
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <!-- BOOKINGS -->

                            <td>

                                <span class="booking-count">

                                    <?= number_format(
                                        $bookingCount
                                    ) ?>

                                </span>

                            </td>


                            <!-- BOOKING STATUS -->

                            <td>

                                <div class="booking-mini">


                                    <?php if ($pending > 0): ?>

                                        <span class="mini mini-pending">
                                            P <?= $pending ?>
                                        </span>

                                    <?php endif; ?>


                                    <?php if ($confirmed > 0): ?>

                                        <span class="mini mini-confirmed">
                                            C <?= $confirmed ?>
                                        </span>

                                    <?php endif; ?>


                                    <?php if ($completed > 0): ?>

                                        <span class="mini mini-completed">
                                            D <?= $completed ?>
                                        </span>

                                    <?php endif; ?>


                                    <?php if ($cancelled > 0): ?>

                                        <span class="mini mini-cancelled">
                                            X <?= $cancelled ?>
                                        </span>

                                    <?php endif; ?>


                                    <?php if ($bookingCount === 0): ?>

                                        <span
                                            style="
                                                color:#94a3b8;
                                                font-size:11px;
                                            "
                                        >
                                            No bookings
                                        </span>

                                    <?php endif; ?>


                                </div>

                            </td>


                            <!-- AMOUNT -->

                            <td>

                                <span class="amount">

                                    KSh
                                    <?= number_format(
                                        $totalAmount,
                                        2
                                    ) ?>

                                </span>

                            </td>


                            <!-- LAST BOOKING -->

                            <td>

                                <?= customerSafe(
                                    customerDate(
                                        $lastBooking
                                    )
                                ) ?>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="actions">


                                    <!-- VIEW BOOKINGS -->

                                    <a
                                        href="<?= customerSafe(
                                            BASE_URL .
                                            '/admin/bookings.php?user_id=' .
                                            $customerId
                                        ) ?>"
                                        class="action-btn action-view"
                                    >
                                        View
                                    </a>


                                    <!-- EDIT -->

                                    <button
                                        type="button"
                                        class="action-btn action-edit"
                                        onclick="openEditModal(
                                            <?= $customerId ?>,
                                            <?= htmlspecialchars(
                                                json_encode(
                                                    $customerName
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>,
                                            <?= htmlspecialchars(
                                                json_encode(
                                                    $customerEmail
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>,
                                            <?= htmlspecialchars(
                                                json_encode(
                                                    $customerPhone
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>,
                                            <?= htmlspecialchars(
                                                json_encode(
                                                    $customerStatus
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        )"
                                    >
                                        Edit
                                    </button>


                                    <!-- PASSWORD -->

                                    <button
                                        type="button"
                                        class="action-btn action-password"
                                        onclick="openPasswordModal(
                                            <?= $customerId ?>,
                                            <?= htmlspecialchars(
                                                json_encode(
                                                    $customerName
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        )"
                                    >
                                        Password
                                    </button>


                                    <!-- DELETE -->

                                    <button
                                        type="button"
                                        class="action-btn action-delete"
                                        onclick="openDeleteModal(
                                            <?= $customerId ?>,
                                            <?= htmlspecialchars(
                                                json_encode(
                                                    $customerName
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        )"
                                    >
                                        Delete
                                    </button>


                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="8"
                            class="empty"
                        >

                            <div class="empty-icon">
                                👥
                            </div>

                            <h3>
                                No customers found
                            </h3>

                            <p>
                                Customer accounts will appear here after registration.
                            </p>

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


</div>


<!-- ======================================================================
     EDIT CUSTOMER MODAL
     ====================================================================== -->

<div
    class="modal"
    id="editModal"
>

    <div class="modal-card">


        <div class="modal-header">

            <h3>
                Edit Customer
            </h3>

            <button
                type="button"
                class="close-btn"
                onclick="closeModal('editModal')"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
        >

            <div class="modal-body">


                <input
                    type="hidden"
                    name="action"
                    value="update_customer"
                >


                <input
                    type="hidden"
                    name="customer_id"
                    id="edit_customer_id"
                >


                <div class="form-group">

                    <label>
                        Full Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        id="edit_name"
                        class="form-control"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Email Address
                    </label>

                    <input
                        type="email"
                        name="email"
                        id="edit_email"
                        class="form-control"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Phone Number
                    </label>

                    <input
                        type="text"
                        name="phone"
                        id="edit_phone"
                        class="form-control"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Account Status
                    </label>

                    <select
                        name="status"
                        id="edit_status"
                        class="form-control"
                    >

                        <option value="active">
                            Active
                        </option>

                        <option value="inactive">
                            Inactive
                        </option>

                        <option value="suspended">
                            Suspended
                        </option>

                    </select>

                </div>


            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn-modal btn-cancel"
                    onclick="closeModal('editModal')"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn-modal btn-save"
                >
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>


<!-- ======================================================================
     PASSWORD MODAL
     ====================================================================== -->

<div
    class="modal"
    id="passwordModal"
>

    <div class="modal-card">


        <div class="modal-header">

            <h3>
                Change Customer Password
            </h3>

            <button
                type="button"
                class="close-btn"
                onclick="closeModal('passwordModal')"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
        >

            <div class="modal-body">


                <input
                    type="hidden"
                    name="action"
                    value="change_password"
                >


                <input
                    type="hidden"
                    name="customer_id"
                    id="password_customer_id"
                >


                <div
                    style="
                        margin-bottom:18px;
                        padding:12px;
                        background:#fff7ed;
                        color:#9a3412;
                        border-radius:9px;
                        font-size:12px;
                    "
                >

                    Changing the password for:

                    <strong id="password_customer_name">
                    </strong>

                </div>


                <div class="form-group">

                    <label>
                        New Password
                    </label>

                    <input
                        type="password"
                        name="password"
                        class="form-control"
                        minlength="6"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Confirm New Password
                    </label>

                    <input
                        type="password"
                        name="confirm_password"
                        class="form-control"
                        minlength="6"
                        required
                    >

                </div>


            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn-modal btn-cancel"
                    onclick="closeModal('passwordModal')"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn-modal btn-save"
                >
                    Change Password
                </button>

            </div>

        </form>

    </div>

</div>


<!-- ======================================================================
     DELETE MODAL
     ====================================================================== -->

<div
    class="modal"
    id="deleteModal"
>

    <div class="modal-card">


        <div class="modal-header">

            <h3>
                Delete Customer
            </h3>

            <button
                type="button"
                class="close-btn"
                onclick="closeModal('deleteModal')"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
        >

            <div class="modal-body">


                <input
                    type="hidden"
                    name="action"
                    value="delete_customer"
                >


                <input
                    type="hidden"
                    name="customer_id"
                    id="delete_customer_id"
                >


                <div
                    style="
                        text-align:center;
                        padding:10px 5px;
                    "
                >

                    <div
                        style="
                            width:60px;
                            height:60px;
                            margin:0 auto 15px;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            border-radius:50%;
                            background:#fee2e2;
                            color:#dc2626;
                            font-size:26px;
                        "
                    >
                        !
                    </div>


                    <h3
                        style="
                            margin:0 0 8px;
                            color:#111827;
                        "
                    >
                        Delete this customer?
                    </h3>


                    <p
                        style="
                            margin:0;
                            color:#64748b;
                            font-size:13px;
                            line-height:1.6;
                        "
                    >

                        You are about to permanently delete

                        <strong id="delete_customer_name">
                        </strong>.

                        The customer's account and associated
                        booking records may also be deleted.

                        <br><br>

                        <strong
                            style="color:#dc2626;"
                        >
                            This action cannot be undone.
                        </strong>

                    </p>

                </div>


            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn-modal btn-cancel"
                    onclick="closeModal('deleteModal')"
                >
                    Keep Customer
                </button>

                <button
                    type="submit"
                    class="btn-modal btn-danger"
                >
                    Yes, Delete Customer
                </button>

            </div>

        </form>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

const searchInput =
    document.getElementById(
        'customerSearch'
    );

const statusFilter =
    document.getElementById(
        'statusFilter'
    );

const customerRows =
    document.querySelectorAll(
        '#customersTable tbody tr[data-name]'
    );


function filterCustomers() {

    const search =
        searchInput.value
            .toLowerCase()
            .trim();

    const status =
        statusFilter.value;


    customerRows.forEach(function(row) {

        const name =
            row.dataset.name || '';

        const email =
            row.dataset.email || '';

        const phone =
            row.dataset.phone || '';

        const rowStatus =
            row.dataset.status || '';


        const matchesSearch =
            name.includes(search) ||
            email.includes(search) ||
            phone.includes(search);


        const matchesStatus =
            status === 'all' ||
            rowStatus === status;


        row.style.display =
            matchesSearch && matchesStatus
                ? ''
                : 'none';

    });
}


searchInput.addEventListener(
    'input',
    filterCustomers
);


statusFilter.addEventListener(
    'change',
    filterCustomers
);


/*
|--------------------------------------------------------------------------
| MODALS
|--------------------------------------------------------------------------
*/

function openModal(id) {

    const modal =
        document.getElementById(id);

    if (modal) {

        modal.classList.add('show');

        document.body.style.overflow =
            'hidden';
    }
}


function closeModal(id) {

    const modal =
        document.getElementById(id);

    if (modal) {

        modal.classList.remove('show');

        document.body.style.overflow =
            '';
    }
}


/*
|--------------------------------------------------------------------------
| EDIT MODAL
|--------------------------------------------------------------------------
*/

function openEditModal(
    id,
    name,
    email,
    phone,
    status
) {

    document.getElementById(
        'edit_customer_id'
    ).value = id;


    document.getElementById(
        'edit_name'
    ).value = name;


    document.getElementById(
        'edit_email'
    ).value = email;


    document.getElementById(
        'edit_phone'
    ).value = phone;


    document.getElementById(
        'edit_status'
    ).value = status;


    openModal('editModal');
}


/*
|--------------------------------------------------------------------------
| PASSWORD MODAL
|--------------------------------------------------------------------------
*/

function openPasswordModal(
    id,
    name
) {

    document.getElementById(
        'password_customer_id'
    ).value = id;


    document.getElementById(
        'password_customer_name'
    ).textContent = name;


    openModal('passwordModal');
}


/*
|--------------------------------------------------------------------------
| DELETE MODAL
|--------------------------------------------------------------------------
*/

function openDeleteModal(
    id,
    name
) {

    document.getElementById(
        'delete_customer_id'
    ).value = id;


    document.getElementById(
        'delete_customer_name'
    ).textContent = name;


    openModal('deleteModal');
}


/*
|--------------------------------------------------------------------------
| CLOSE MODAL WHEN CLICKING OUTSIDE
|--------------------------------------------------------------------------
*/

document.querySelectorAll('.modal').forEach(
    function(modal) {

        modal.addEventListener(
            'click',
            function(event) {

                if (
                    event.target === modal
                ) {

                    closeModal(
                        modal.id
                    );
                }

            }
        );

    }
);


/*
|--------------------------------------------------------------------------
| ESC KEY
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'Escape') {

            document.querySelectorAll(
                '.modal.show'
            ).forEach(
                function(modal) {

                    closeModal(
                        modal.id
                    );

                }
            );

        }

    }
);

</script>


</body>

</html>