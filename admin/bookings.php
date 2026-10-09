<?php

/*
|--------------------------------------------------------------------------
| BookEase - Admin Bookings
| File: admin/bookings.php
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['admin', 'staff'], true)) {
    header('Location: ' . BASE_URL . '/customer/dashboard.php');
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
        throw new Exception('Database connection failed.');
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

function bookingSafe($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function bookingStatusClass($status)
{
    $status = strtolower(trim((string) $status));

    $allowed = [
        'pending',
        'confirmed',
        'completed',
        'cancelled'
    ];

    return in_array($status, $allowed, true)
        ? $status
        : 'default';
}

function bookingStatusLabel($status)
{
    return ucfirst(
        str_replace(
            '_',
            ' ',
            strtolower(trim((string) $status))
        )
    );
}

/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$message = '';
$error = '';

$search = trim($_GET['search'] ?? '');
$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));

$allowedFilters = [
    'all',
    'pending',
    'confirmed',
    'completed',
    'cancelled'
];

if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'all';
}

/*
|--------------------------------------------------------------------------
| UPDATE BOOKING STATUS
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['action']) &&
    isset($_GET['id'])
) {

    $action = trim($_GET['action']);
    $bookingId = (int) $_GET['id'];

    $allowedStatuses = [
        'pending',
        'confirmed',
        'completed',
        'cancelled'
    ];

    if (
        $bookingId <= 0 ||
        !in_array($action, $allowedStatuses, true)
    ) {

        $error = 'Invalid booking request.';

    } else {

        try {

            /*
            |--------------------------------------------------------------
            | Get existing booking
            |--------------------------------------------------------------
            */

            $checkStmt = $conn->prepare("
                SELECT
                    id,
                    user_id,
                    booking_reference,
                    status
                FROM bookings
                WHERE id = ?
                LIMIT 1
            ");

            $checkStmt->execute([
                $bookingId
            ]);

            $existingBooking =
                $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingBooking) {

                $error = 'Booking not found.';

            } else {

                $userId =
                    (int) ($existingBooking['user_id'] ?? 0);

                /*
                |----------------------------------------------------------
                | Update booking
                |----------------------------------------------------------
                */

                if ($action === 'confirmed') {

                    $updateSql = "
                        UPDATE bookings
                        SET
                            status = ?,
                            confirmed_at = NOW(),
                            cancelled_at = NULL
                        WHERE id = ?
                    ";

                } elseif ($action === 'completed') {

                    $updateSql = "
                        UPDATE bookings
                        SET
                            status = ?,
                            completed_at = NOW()
                        WHERE id = ?
                    ";

                } elseif ($action === 'cancelled') {

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
                            status = ?
                        WHERE id = ?
                    ";
                }

                $updateStmt =
                    $conn->prepare($updateSql);

                $updateStmt->execute([
                    $action,
                    $bookingId
                ]);

                /*
                |----------------------------------------------------------
                | Customer notification
                |----------------------------------------------------------
                */

                if ($userId > 0) {

                    switch ($action) {

                        case 'pending':

                            $notificationTitle =
                                'Booking Pending';

                            $notificationMessage =
                                'Your booking is currently pending confirmation.';

                            break;

                        case 'confirmed':

                            $notificationTitle =
                                'Booking Confirmed';

                            $notificationMessage =
                                'Your booking has been confirmed by BookEase.';

                            break;

                        case 'completed':

                            $notificationTitle =
                                'Booking Completed';

                            $notificationMessage =
                                'Your BookEase booking has been marked as completed.';

                            break;

                        case 'cancelled':

                            $notificationTitle =
                                'Booking Cancelled';

                            $notificationMessage =
                                'Your BookEase booking has been cancelled by the administrator.';

                            break;

                        default:

                            $notificationTitle =
                                'Booking Updated';

                            $notificationMessage =
                                'Your booking status has been updated.';
                    }

                    /*
                    |------------------------------------------------------
                    | Insert notification
                    |------------------------------------------------------
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
                                    'booking',
                                    0,
                                    NOW()
                                )
                            ");

                        $notificationStmt->execute([
                            $userId,
                            $bookingId,
                            $notificationTitle,
                            $notificationMessage
                        ]);

                    } catch (PDOException $notificationError) {

                        /*
                        |--------------------------------------------------
                        | Fallback for databases where booking_id
                        | is not available in notifications
                        |--------------------------------------------------
                        */

                        try {

                            $notificationStmt =
                                $conn->prepare("
                                    INSERT INTO notifications
                                    (
                                        user_id,
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
                                        'booking',
                                        0,
                                        NOW()
                                    )
                                ");

                            $notificationStmt->execute([
                                $userId,
                                $notificationTitle,
                                $notificationMessage
                            ]);

                        } catch (PDOException $ignored) {
                            // Booking status remains updated.
                        }
                    }
                }

                /*
                |----------------------------------------------------------
                | Redirect
                |----------------------------------------------------------
                */

                header(
                    'Location: ' .
                    BASE_URL .
                    '/admin/bookings.php?updated=1'
                );

                exit;
            }

        } catch (Throwable $e) {

            $error =
                'Unable to update booking: ' .
                $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/

if (isset($_GET['updated'])) {
    $message = 'Booking status updated successfully.';
}

/*
|--------------------------------------------------------------------------
| BOOKING STATISTICS
|--------------------------------------------------------------------------
*/

$totalBookings = 0;
$pendingBookings = 0;
$confirmedBookings = 0;
$completedBookings = 0;
$cancelledBookings = 0;

try {

    $statsStmt = $conn->query("
        SELECT
            COUNT(*) AS total_bookings,

            SUM(
                CASE
                    WHEN status = 'pending'
                    THEN 1
                    ELSE 0
                END
            ) AS pending_bookings,

            SUM(
                CASE
                    WHEN status = 'confirmed'
                    THEN 1
                    ELSE 0
                END
            ) AS confirmed_bookings,

            SUM(
                CASE
                    WHEN status = 'completed'
                    THEN 1
                    ELSE 0
                END
            ) AS completed_bookings,

            SUM(
                CASE
                    WHEN status = 'cancelled'
                    THEN 1
                    ELSE 0
                END
            ) AS cancelled_bookings

        FROM bookings
    ");

    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    $totalBookings =
        (int) ($stats['total_bookings'] ?? 0);

    $pendingBookings =
        (int) ($stats['pending_bookings'] ?? 0);

    $confirmedBookings =
        (int) ($stats['confirmed_bookings'] ?? 0);

    $completedBookings =
        (int) ($stats['completed_bookings'] ?? 0);

    $cancelledBookings =
        (int) ($stats['cancelled_bookings'] ?? 0);

} catch (Throwable $e) {

    $error =
        'Unable to load booking statistics.';
}

/*
|--------------------------------------------------------------------------
| LOAD BOOKINGS
|--------------------------------------------------------------------------
*/

$bookings = [];

try {

    $sql = "
        SELECT

            b.id,
            b.user_id,
            b.service_id,

            b.booking_reference,
            b.booking_date,
            b.booking_time,

            b.status,
            b.payment_status,

            b.notes,

            b.amount,
            b.total_amount,

            b.created_at,

            u.name AS customer,
            u.email AS customer_email,
            u.phone AS customer_phone,

            s.name AS service,
            s.price AS service_price,
            s.duration AS service_duration

        FROM bookings b

        LEFT JOIN users u
            ON b.user_id = u.id

        LEFT JOIN services s
            ON b.service_id = s.id

        WHERE 1 = 1
    ";

    $params = [];

    /*
    |----------------------------------------------------------------------
    | SEARCH
    |----------------------------------------------------------------------
    */

    if ($search !== '') {

        $sql .= "
            AND (
                b.booking_reference LIKE ?
                OR b.customer_name LIKE ?
                OR b.customer_email LIKE ?
                OR u.name LIKE ?
                OR u.email LIKE ?
                OR u.phone LIKE ?
                OR s.name LIKE ?
            )
        ";

        $searchValue = '%' . $search . '%';

        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
    }

    /*
    |----------------------------------------------------------------------
    | STATUS FILTER
    |----------------------------------------------------------------------
    */

    if ($statusFilter !== 'all') {

        $sql .= "
            AND b.status = ?
        ";

        $params[] = $statusFilter;
    }

    $sql .= "
        ORDER BY b.id DESC
    ";

    $stmt = $conn->prepare($sql);

    $stmt->execute($params);

    $bookings =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $bookings = [];

    $error =
        'Unable to load bookings: ' .
        $e->getMessage();
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
    Bookings | BookEase Admin
</title>

<style>

/* =========================================================
   GLOBAL
========================================================= */

* {
    box-sizing: border-box;
}

:root {

    --primary: #2563eb;
    --primary-dark: #1d4ed8;

    --dark: #0f172a;
    --text: #334155;
    --muted: #64748b;

    --border: #e2e8f0;

    --background: #f8fafc;
    --white: #ffffff;

    --green: #16a34a;
    --orange: #f59e0b;
    --red: #dc2626;
    --purple: #7c3aed;
}

body {

    margin: 0;

    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Roboto,
        Arial,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #f8fafc 0%,
            #eef4ff 100%
        );

    color: var(--text);

    min-height: 100vh;
}

button,
input,
select {

    font-family: inherit;
}

/* =========================================================
   PAGE
========================================================= */

.content {

    max-width: 1450px;

    margin: 0 auto;

    padding: 30px;
}

/* =========================================================
   HEADER
========================================================= */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 20px;

    margin-bottom: 28px;
}

.page-header h1 {

    margin: 0;

    font-size: 32px;

    font-weight: 800;

    color: var(--dark);

    letter-spacing: -0.6px;
}

.page-header p {

    margin: 8px 0 0;

    color: var(--muted);

    font-size: 15px;
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

    font-size: 14px;

    font-weight: 700;

    border: 1px solid var(--border);

    background: white;

    color: var(--text);

    transition: .2s;
}

.header-btn:hover {

    border-color: #cbd5e1;

    transform: translateY(-1px);

    box-shadow:
        0 5px 15px
        rgba(15, 23, 42, .06);
}

.header-btn.primary {

    background: var(--primary);

    color: white;

    border-color: var(--primary);
}

.header-btn.primary:hover {

    background: var(--primary-dark);
}

/* =========================================================
   ALERT
========================================================= */

.alert {

    padding: 14px 17px;

    border-radius: 12px;

    margin-bottom: 22px;

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

/* =========================================================
   STATISTICS
========================================================= */

.stats-grid {

    display: grid;

    grid-template-columns:
        repeat(5, minmax(0, 1fr));

    gap: 16px;

    margin-bottom: 24px;
}

.stat-card {

    background: rgba(255,255,255,.92);

    border: 1px solid rgba(226,232,240,.9);

    border-radius: 16px;

    padding: 19px;

    box-shadow:
        0 8px 25px
        rgba(15,23,42,.05);

    position: relative;

    overflow: hidden;

    transition: .2s;
}

.stat-card:hover {

    transform: translateY(-2px);

    box-shadow:
        0 12px 30px
        rgba(15,23,42,.08);
}

.stat-top {

    display: flex;

    align-items: center;

    justify-content: space-between;
}

.stat-icon {

    width: 44px;

    height: 44px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 12px;

    font-size: 20px;
}

.stat-number {

    margin-top: 17px;

    font-size: 28px;

    font-weight: 800;

    color: var(--dark);
}

.stat-label {

    margin-top: 4px;

    color: var(--muted);

    font-size: 13px;

    font-weight: 600;
}

.stat-all .stat-icon {

    background: #eff6ff;

    color: #2563eb;
}

.stat-pending .stat-icon {

    background: #fffbeb;

    color: #d97706;
}

.stat-confirmed .stat-icon {

    background: #eff6ff;

    color: #2563eb;
}

.stat-completed .stat-icon {

    background: #ecfdf5;

    color: #16a34a;
}

.stat-cancelled .stat-icon {

    background: #fef2f2;

    color: #dc2626;
}

/* =========================================================
   MAIN PANEL
========================================================= */

.booking-panel {

    background: white;

    border: 1px solid var(--border);

    border-radius: 18px;

    overflow: hidden;

    box-shadow:
        0 10px 35px
        rgba(15,23,42,.06);
}

/* =========================================================
   TOOLBAR
========================================================= */

.toolbar {

    padding: 20px;

    border-bottom: 1px solid var(--border);

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 16px;

    flex-wrap: wrap;
}

.toolbar-title h2 {

    margin: 0;

    font-size: 19px;

    color: var(--dark);
}

.toolbar-title span {

    display: block;

    margin-top: 4px;

    color: var(--muted);

    font-size: 13px;
}

.filters {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;

    align-items: center;
}

.search-box {

    position: relative;
}

.search-box span {

    position: absolute;

    left: 12px;

    top: 50%;

    transform: translateY(-50%);

    color: #94a3b8;

    font-size: 16px;
}

.search-box input {

    width: 260px;

    height: 42px;

    border: 1px solid var(--border);

    border-radius: 10px;

    padding: 0 13px 0 38px;

    outline: none;

    font-size: 13px;

    color: var(--text);

    background: #f8fafc;

    transition: .2s;
}

.search-box input:focus {

    background: white;

    border-color: #93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.10);
}

.status-filter {

    height: 42px;

    padding: 0 35px 0 12px;

    border: 1px solid var(--border);

    border-radius: 10px;

    background: #f8fafc;

    color: var(--text);

    font-size: 13px;

    outline: none;

    cursor: pointer;
}

.filter-btn {

    height: 42px;

    padding: 0 15px;

    border: 0;

    border-radius: 10px;

    background: var(--primary);

    color: white;

    font-size: 13px;

    font-weight: 700;

    cursor: pointer;
}

.filter-btn:hover {

    background: var(--primary-dark);
}

.clear-btn {

    height: 42px;

    padding: 0 14px;

    display: inline-flex;

    align-items: center;

    text-decoration: none;

    border-radius: 10px;

    border: 1px solid var(--border);

    color: var(--muted);

    font-size: 13px;

    font-weight: 700;

    background: white;
}

/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    width: 100%;

    overflow-x: auto;
}

table {

    width: 100%;

    min-width: 1150px;

    border-collapse: collapse;
}

thead {

    background: #f8fafc;
}

th {

    padding: 14px 17px;

    text-align: left;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .7px;

    color: #64748b;

    border-bottom: 1px solid var(--border);

    white-space: nowrap;
}

td {

    padding: 17px;

    border-bottom: 1px solid #eef2f7;

    vertical-align: middle;

    font-size: 13px;
}

tbody tr {

    transition: .15s;
}

tbody tr:hover {

    background: #f8fbff;
}

/* =========================================================
   CUSTOMER
========================================================= */

.customer {

    display: flex;

    align-items: center;

    gap: 11px;

    min-width: 190px;
}

.avatar {

    width: 40px;

    height: 40px;

    min-width: 40px;

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

    font-size: 14px;

    font-weight: 800;
}

.customer-info strong {

    display: block;

    color: var(--dark);

    font-size: 13px;

    margin-bottom: 3px;
}

.customer-info span {

    display: block;

    color: #94a3b8;

    font-size: 11px;
}

/* =========================================================
   BOOKING REFERENCE
========================================================= */

.reference {

    display: inline-flex;

    align-items: center;

    padding: 6px 9px;

    border-radius: 7px;

    background: #eff6ff;

    color: #1d4ed8;

    font-size: 11px;

    font-weight: 800;

    white-space: nowrap;
}

/* =========================================================
   SERVICE
========================================================= */

.service {

    font-weight: 700;

    color: var(--dark);
}

.service-duration {

    display: block;

    margin-top: 3px;

    font-size: 11px;

    color: #94a3b8;
}

/* =========================================================
   DATE
========================================================= */

.date-time strong {

    display: block;

    color: var(--dark);

    font-size: 13px;
}

.date-time span {

    display: block;

    margin-top: 3px;

    color: #64748b;

    font-size: 11px;
}

/* =========================================================
   PRICE
========================================================= */

.price {

    font-weight: 800;

    color: var(--dark);

    white-space: nowrap;
}

/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 800;

    white-space: nowrap;
}

.status::before {

    content: "";

    width: 6px;

    height: 6px;

    border-radius: 50%;

    background: currentColor;
}

.status-pending {

    background: #fffbeb;

    color: #b45309;
}

.status-confirmed {

    background: #eff6ff;

    color: #1d4ed8;
}

.status-completed {

    background: #ecfdf5;

    color: #15803d;
}

.status-cancelled {

    background: #fef2f2;

    color: #b91c1c;
}

.status-default {

    background: #f1f5f9;

    color: #475569;
}

/* =========================================================
   PAYMENT
========================================================= */

.payment {

    font-size: 11px;

    font-weight: 700;

    text-transform: capitalize;
}

.payment-paid {

    color: #15803d;
}

.payment-pending {

    color: #b45309;
}

.payment-unpaid {

    color: #64748b;
}

.payment-failed {

    color: #dc2626;
}

.payment-refunded {

    color: #7c3aed;
}

/* =========================================================
   ACTIONS
========================================================= */

.actions {

    display: flex;

    align-items: center;

    gap: 6px;

    flex-wrap: wrap;
}

.action {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-height: 32px;

    padding: 0 9px;

    border-radius: 7px;

    text-decoration: none;

    font-size: 11px;

    font-weight: 800;

    transition: .15s;

    border: 1px solid transparent;
}

.action:hover {

    transform: translateY(-1px);
}

.action-view {

    background: #eff6ff;

    color: #1d4ed8;
}

.action-confirm {

    background: #ecfdf5;

    color: #15803d;
}

.action-complete {

    background: #eef2ff;

    color: #4338ca;
}

.action-cancel {

    background: #fef2f2;

    color: #b91c1c;
}

.action-message {

    background: #f5f3ff;

    color: #6d28d9;
}

/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding: 65px 25px;

    text-align: center;

    color: var(--muted);
}

.empty-icon {

    width: 60px;

    height: 60px;

    margin: 0 auto 15px;

    border-radius: 16px;

    background: #f1f5f9;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 25px;
}

.empty strong {

    display: block;

    margin-bottom: 6px;

    color: var(--dark);

    font-size: 16px;
}

/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 1150px) {

    .stats-grid {

        grid-template-columns:
            repeat(3, 1fr);
    }
}

@media (max-width: 800px) {

    .content {

        padding: 18px;
    }

    .page-header {

        flex-direction: column;
    }

    .stats-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .toolbar {

        align-items: stretch;

        flex-direction: column;
    }

    .filters {

        width: 100%;
    }

    .search-box {

        width: 100%;
    }

    .search-box input {

        width: 100%;
    }

    .status-filter {

        flex: 1;
    }
}

@media (max-width: 520px) {

    .stats-grid {

        grid-template-columns: 1fr;
    }

    .page-header h1 {

        font-size: 26px;
    }

    .filters {

        flex-direction: column;

        align-items: stretch;
    }

    .status-filter,
    .filter-btn,
    .clear-btn {

        width: 100%;
    }
}

</style>

</head>

<body>

<div class="content">

    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="page-header">

        <div>

            <h1>
                Bookings
            </h1>

            <p>
                Manage appointments, customers, payments and booking communication.
            </p>

        </div>

        <div class="header-actions">

            <a
                href="<?= bookingSafe(BASE_URL) ?>/admin/dashboard.php"
                class="header-btn"
            >
                ← Dashboard
            </a>

            <a
                href="<?= bookingSafe(BASE_URL) ?>/admin/customers.php"
                class="header-btn"
            >
                👥 Customers
            </a>

        </div>

    </div>


    <!-- =====================================================
         ALERTS
    ====================================================== -->

    <?php if ($message !== ''): ?>

        <div class="alert alert-success">

            ✓
            <?= bookingSafe($message) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="alert alert-error">

            ⚠
            <?= bookingSafe($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <div class="stats-grid">

        <div class="stat-card stat-all">

            <div class="stat-top">

                <div class="stat-icon">
                    📋
                </div>

            </div>

            <div class="stat-number">
                <?= number_format($totalBookings) ?>
            </div>

            <div class="stat-label">
                All Bookings
            </div>

        </div>


        <div class="stat-card stat-pending">

            <div class="stat-top">

                <div class="stat-icon">
                    ⏳
                </div>

            </div>

            <div class="stat-number">
                <?= number_format($pendingBookings) ?>
            </div>

            <div class="stat-label">
                Pending
            </div>

        </div>


        <div class="stat-card stat-confirmed">

            <div class="stat-top">

                <div class="stat-icon">
                    ✓
                </div>

            </div>

            <div class="stat-number">
                <?= number_format($confirmedBookings) ?>
            </div>

            <div class="stat-label">
                Confirmed
            </div>

        </div>


        <div class="stat-card stat-completed">

            <div class="stat-top">

                <div class="stat-icon">
                    ✓
                </div>

            </div>

            <div class="stat-number">
                <?= number_format($completedBookings) ?>
            </div>

            <div class="stat-label">
                Completed
            </div>

        </div>


        <div class="stat-card stat-cancelled">

            <div class="stat-top">

                <div class="stat-icon">
                    ×
                </div>

            </div>

            <div class="stat-number">
                <?= number_format($cancelledBookings) ?>
            </div>

            <div class="stat-label">
                Cancelled
            </div>

        </div>

    </div>


    <!-- =====================================================
         BOOKING PANEL
    ====================================================== -->

    <div class="booking-panel">

        <!-- TOOLBAR -->

        <div class="toolbar">

            <div class="toolbar-title">

                <h2>
                    Customer Bookings
                </h2>

                <span>
                    View and manage bookings in real time.
                </span>

            </div>


            <form
                method="GET"
                class="filters"
            >

                <div class="search-box">

                    <span>
                        🔍
                    </span>

                    <input
                        type="search"
                        name="search"
                        value="<?= bookingSafe($search) ?>"
                        placeholder="Search customer, email, service..."
                    >

                </div>


                <select
                    name="status"
                    class="status-filter"
                >

                    <option
                        value="all"
                        <?= $statusFilter === 'all' ? 'selected' : '' ?>
                    >
                        All Statuses
                    </option>

                    <option
                        value="pending"
                        <?= $statusFilter === 'pending' ? 'selected' : '' ?>
                    >
                        Pending
                    </option>

                    <option
                        value="confirmed"
                        <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>
                    >
                        Confirmed
                    </option>

                    <option
                        value="completed"
                        <?= $statusFilter === 'completed' ? 'selected' : '' ?>
                    >
                        Completed
                    </option>

                    <option
                        value="cancelled"
                        <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>
                    >
                        Cancelled
                    </option>

                </select>


                <button
                    type="submit"
                    class="filter-btn"
                >
                    Filter
                </button>


                <?php if ($search !== '' || $statusFilter !== 'all'): ?>

                    <a
                        href="<?= bookingSafe(BASE_URL) ?>/admin/bookings.php"
                        class="clear-btn"
                    >
                        Clear
                    </a>

                <?php endif; ?>

            </form>

        </div>


        <!-- TABLE -->

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>

                        <th>
                            Booking
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Service
                        </th>

                        <th>
                            Appointment
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Payment
                        </th>

                        <th>
                            Communication
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (!empty($bookings)): ?>

                    <?php foreach ($bookings as $row): ?>

                        <?php

                        $bookingId =
                            (int) ($row['id'] ?? 0);

                        $customer =
                            trim(
                                $row['customer']
                                ?? ''
                            );

                        if ($customer === '') {
                            $customer =
                                $row['customer_name']
                                ?? 'Unknown Customer';
                        }

                        $email =
                            $row['customer_email']
                            ?? '';

                        $phone =
                            $row['customer_phone']
                            ?? '';

                        $service =
                            $row['service']
                            ?? 'Unknown Service';

                        $duration =
                            $row['service_duration']
                            ?? '';

                        $date =
                            $row['booking_date']
                            ?? '-';

                        $time =
                            $row['booking_time']
                            ?? '-';

                        $status =
                            strtolower(
                                trim(
                                    $row['status']
                                    ?? 'pending'
                                )
                            );

                        $payment =
                            strtolower(
                                trim(
                                    $row['payment_status']
                                    ?? 'unpaid'
                                )
                            );

                        $price =
                            $row['total_amount']
                            ?? $row['amount']
                            ?? $row['service_price']
                            ?? 0;

                        $reference =
                            $row['booking_reference']
                            ?? '';

                        $statusClass =
                            bookingStatusClass($status);

                        $initial =
                            strtoupper(
                                substr(
                                    trim($customer),
                                    0,
                                    1
                                )
                            );

                        ?>

                        <tr>

                            <!-- BOOKING -->

                            <td>

                                <span class="reference">

                                    <?= bookingSafe(
                                        $reference
                                        ?: 'BK-' . $bookingId
                                    ) ?>

                                </span>

                                <div
                                    style="
                                        margin-top:6px;
                                        color:#94a3b8;
                                        font-size:11px;
                                    "
                                >
                                    #<?= $bookingId ?>
                                </div>

                            </td>


                            <!-- CUSTOMER -->

                            <td>

                                <div class="customer">

                                    <div class="avatar">
                                        <?= bookingSafe($initial) ?>
                                    </div>

                                    <div class="customer-info">

                                        <strong>
                                            <?= bookingSafe($customer) ?>
                                        </strong>

                                        <span>
                                            <?= bookingSafe(
                                                $email ?: 'No email'
                                            ) ?>
                                        </span>

                                    </div>

                                </div>

                            </td>


                            <!-- SERVICE -->

                            <td>

                                <div class="service">

                                    <?= bookingSafe($service) ?>

                                </div>

                                <?php if ($duration !== ''): ?>

                                    <span class="service-duration">

                                        <?= bookingSafe($duration) ?>

                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- APPOINTMENT -->

                            <td>

                                <div class="date-time">

                                    <strong>
                                        <?= bookingSafe($date) ?>
                                    </strong>

                                    <span>
                                        <?= bookingSafe($time) ?>
                                    </span>

                                </div>

                            </td>


                            <!-- AMOUNT -->

                            <td>

                                <span class="price">

                                    KSh
                                    <?= number_format(
                                        (float) $price,
                                        2
                                    ) ?>

                                </span>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status status-<?= bookingSafe(
                                        $statusClass
                                    ) ?>"
                                >

                                    <?= bookingSafe(
                                        bookingStatusLabel($status)
                                    ) ?>

                                </span>

                            </td>


                            <!-- PAYMENT -->

                            <td>

                                <?php

                                $paymentAllowed = [
                                    'paid',
                                    'pending',
                                    'unpaid',
                                    'failed',
                                    'refunded'
                                ];

                                $paymentClass =
                                    in_array(
                                        $payment,
                                        $paymentAllowed,
                                        true
                                    )
                                    ? $payment
                                    : 'unpaid';

                                ?>

                                <span
                                    class="payment payment-<?= bookingSafe(
                                        $paymentClass
                                    ) ?>"
                                >

                                    <?= bookingSafe(
                                        ucfirst($payment)
                                    ) ?>

                                </span>

                            </td>


                            <!-- COMMUNICATION -->

                            <td>

                                <div class="actions">

                                    <?php if ($email !== ''): ?>

                                        <a
                                            href="mailto:<?= bookingSafe($email) ?>"
                                            class="action action-message"
                                            title="Email customer"
                                        >
                                            ✉ Email
                                        </a>

                                    <?php endif; ?>


                                    <?php if ($phone !== ''): ?>

                                        <?php

                                        $whatsappPhone =
                                            preg_replace(
                                                '/[^0-9]/',
                                                '',
                                                $phone
                                            );

                                        ?>

                                        <a
                                            href="https://wa.me/<?= bookingSafe($whatsappPhone) ?>?text=<?= urlencode(
                                                'Hello ' .
                                                $customer .
                                                ', this is BookEase regarding your booking ' .
                                                ($reference ?: '#' . $bookingId) .
                                                '.'
                                            ) ?>"
                                            target="_blank"
                                            rel="noopener"
                                            class="action action-message"
                                            title="Contact customer on WhatsApp"
                                        >
                                            💬 WhatsApp
                                        </a>

                                    <?php endif; ?>

                                </div>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="actions">

                                    <!-- VIEW -->

                                    <a
                                        href="<?= bookingSafe(BASE_URL) ?>/admin/booking-view.php?id=<?= $bookingId ?>"
                                        class="action action-view"
                                    >
                                        View
                                    </a>


                                    <!-- CONFIRM -->

                                    <?php if (
                                        $status !== 'confirmed' &&
                                        $status !== 'completed' &&
                                        $status !== 'cancelled'
                                    ): ?>

                                        <a
                                            href="<?= bookingSafe(BASE_URL) ?>/admin/bookings.php?id=<?= $bookingId ?>&action=confirmed"
                                            class="action action-confirm"
                                            onclick="return confirm('Confirm this booking? The customer will receive a notification.');"
                                        >
                                            Confirm
                                        </a>

                                    <?php endif; ?>


                                    <!-- COMPLETE -->

                                    <?php if (
                                        $status !== 'completed' &&
                                        $status !== 'cancelled'
                                    ): ?>

                                        <a
                                            href="<?= bookingSafe(BASE_URL) ?>/admin/bookings.php?id=<?= $bookingId ?>&action=completed"
                                            class="action action-complete"
                                            onclick="return confirm('Mark this booking as completed?');"
                                        >
                                            Complete
                                        </a>

                                    <?php endif; ?>


                                    <!-- CANCEL -->

                                    <?php if (
                                        $status !== 'cancelled' &&
                                        $status !== 'completed'
                                    ): ?>

                                        <a
                                            href="<?= bookingSafe(BASE_URL) ?>/admin/bookings.php?id=<?= $bookingId ?>&action=cancelled"
                                            class="action action-cancel"
                                            onclick="return confirm('Cancel this booking? The customer will receive a notification.');"
                                        >
                                            Cancel
                                        </a>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="9"
                            class="empty"
                        >

                            <div class="empty-icon">
                                📅
                            </div>

                            <strong>
                                No bookings found
                            </strong>

                            <span>
                                Try changing your search or status filter.
                            </span>

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