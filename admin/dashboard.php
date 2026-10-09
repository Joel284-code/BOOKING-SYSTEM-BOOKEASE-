<?php

/*
|--------------------------------------------------------------------------
| BookEase - Admin Dashboard
| File: admin/dashboard.php
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

if (
    !isset($_SESSION['user_id']) ||
    empty($_SESSION['user_id'])
) {
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
    trim($_SESSION['role'] ?? '')
);

if (!in_array($role, ['admin', 'staff'], true)) {

    header(
        'Location: ' . BASE_URL . '/customer/dashboard.php'
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
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$totalCustomers = 0;
$totalBookings = 0;
$totalServices = 0;
$totalPayments = 0;

$pendingBookings = 0;
$confirmedBookings = 0;
$completedBookings = 0;
$cancelledBookings = 0;

$recentBookings = [];

$error = '';


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function dashboardSafe($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function dashboardStatusClass($status): string
{
    $status = strtolower(
        trim((string) $status)
    );

    $allowed = [
        'pending',
        'confirmed',
        'completed',
        'cancelled',
        'rejected',
        'no_show'
    ];

    return in_array(
        $status,
        $allowed,
        true
    )
        ? $status
        : 'default';
}


function dashboardPaymentClass($status): string
{
    $status = strtolower(
        trim((string) $status)
    );

    $allowed = [
        'paid',
        'pending',
        'unpaid',
        'failed',
        'refunded'
    ];

    return in_array(
        $status,
        $allowed,
        true
    )
        ? $status
        : 'unpaid';
}


/*
|--------------------------------------------------------------------------
| TOTAL CUSTOMERS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->query("
        SELECT COUNT(*)
        FROM users
        WHERE role = 'customer'
    ");

    $totalCustomers =
        (int) $stmt->fetchColumn();

} catch (Throwable $e) {

    $error = 'Unable to load customer statistics.';
}


/*
|--------------------------------------------------------------------------
| TOTAL BOOKINGS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->query("
        SELECT COUNT(*)
        FROM bookings
    ");

    $totalBookings =
        (int) $stmt->fetchColumn();

} catch (Throwable $e) {

    $error = 'Unable to load booking statistics.';
}


/*
|--------------------------------------------------------------------------
| TOTAL SERVICES
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->query("
        SELECT COUNT(*)
        FROM services
    ");

    $totalServices =
        (int) $stmt->fetchColumn();

} catch (Throwable $e) {

    $error = 'Unable to load service statistics.';
}


/*
|--------------------------------------------------------------------------
| TOTAL PAID REVENUE
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->query("
        SELECT
            COALESCE(SUM(amount), 0)
        FROM payments
        WHERE status = 'paid'
    ");

    $totalPayments =
        (float) $stmt->fetchColumn();

} catch (Throwable $e) {

    $error = 'Unable to load payment statistics.';
}


/*
|--------------------------------------------------------------------------
| BOOKING STATUS COUNTS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->query("
        SELECT
            LOWER(TRIM(status)) AS status,
            COUNT(*) AS total
        FROM bookings
        GROUP BY LOWER(TRIM(status))
    ");

    $statusRows =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($statusRows as $statusRow) {

        $bookingStatus =
            strtolower(
                trim(
                    $statusRow['status'] ?? ''
                )
            );

        $bookingCount =
            (int) (
                $statusRow['total'] ?? 0
            );

        switch ($bookingStatus) {

            case 'pending':
                $pendingBookings =
                    $bookingCount;
                break;

            case 'confirmed':
                $confirmedBookings =
                    $bookingCount;
                break;

            case 'completed':
                $completedBookings =
                    $bookingCount;
                break;

            case 'cancelled':
                $cancelledBookings =
                    $bookingCount;
                break;
        }
    }

} catch (Throwable $e) {

    $error =
        'Unable to load booking status statistics.';
}


/*
|--------------------------------------------------------------------------
| RECENT BOOKINGS
|--------------------------------------------------------------------------
|
| This query supports the existing BookEase structure:
|
| bookings.user_id
| bookings.service_id
| users.id
| services.id
|
*/

try {

    $stmt = $conn->prepare("
        SELECT
            b.id,
            b.booking_reference,
            b.booking_date,
            b.booking_time,
            b.status,
            b.payment_status,
            b.amount,
            b.total_amount,

            u.name AS customer_name,
            u.email AS customer_email,

            s.name AS service_name

        FROM bookings b

        LEFT JOIN users u
            ON b.user_id = u.id

        LEFT JOIN services s
            ON b.service_id = s.id

        ORDER BY b.id DESC

        LIMIT 8
    ");

    $stmt->execute();

    $recentBookings =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Fallback query
    |--------------------------------------------------------------------------
    |
    | If payment_status, amount or total_amount are not present in an older
    | database version, the dashboard can still display bookings.
    |
    */

    try {

        $stmt = $conn->prepare("
            SELECT
                b.id,
                b.booking_reference,
                b.booking_date,
                b.booking_time,
                b.status,

                u.name AS customer_name,
                u.email AS customer_email,

                s.name AS service_name

            FROM bookings b

            LEFT JOIN users u
                ON b.user_id = u.id

            LEFT JOIN services s
                ON b.service_id = s.id

            ORDER BY b.id DESC

            LIMIT 8
        ");

        $stmt->execute();

        $recentBookings =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($recentBookings as &$booking) {

            $booking['payment_status'] =
                'unpaid';

            $booking['amount'] = 0;

            $booking['total_amount'] = 0;
        }

        unset($booking);

    } catch (Throwable $e2) {

        $recentBookings = [];

        $error =
            'Unable to load recent bookings.';
    }
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$currentUserName =
    $_SESSION['name']
    ?? 'Administrator';

$currentUserEmail =
    $_SESSION['email']
    ?? '';

$currentUserInitial =
    strtoupper(
        substr(
            trim($currentUserName),
            0,
            1
        )
    );

if ($currentUserInitial === '') {
    $currentUserInitial = 'A';
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
    Admin Dashboard | BookEase
</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


:root {

    --primary: #2563eb;
    --primary-dark: #1d4ed8;

    --sidebar: #0f172a;
    --sidebar-light: #1e293b;

    --background: #f8fafc;
    --white: #ffffff;

    --text: #0f172a;
    --muted: #64748b;

    --border: #e2e8f0;

    --green: #16a34a;
    --orange: #f59e0b;
    --red: #dc2626;
    --purple: #7c3aed;

    --shadow:
        0 8px 30px rgba(15, 23, 42, .06);
}


html {
    scroll-behavior: smooth;
}


body {

    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Arial,
        sans-serif;

    background: var(--background);

    color: var(--text);

    min-height: 100vh;
}


/* =========================================================
   LAYOUT
========================================================= */

.app {

    display: flex;

    min-height: 100vh;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: 250px;

    background:
        linear-gradient(
            180deg,
            #0f172a 0%,
            #111827 100%
        );

    color: white;

    position: fixed;

    left: 0;
    top: 0;
    bottom: 0;

    z-index: 1000;

    display: flex;

    flex-direction: column;

    transition:
        transform .3s ease,
        width .3s ease;
}


/* LOGO */

.logo {

    height: 76px;

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 0 22px;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);
}


.logo-icon {

    width: 40px;
    height: 40px;

    border-radius: 11px;

    display: flex;

    align-items: center;
    justify-content: center;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #7c3aed
        );

    font-size: 20px;

    box-shadow:
        0 8px 20px
        rgba(37,99,235,.25);
}


.logo-text {

    font-size: 20px;

    font-weight: 800;

    letter-spacing: -.5px;
}


.logo-text span {

    color: #60a5fa;
}


/* NAVIGATION */

.sidebar-nav {

    flex: 1;

    padding: 20px 12px;

    overflow-y: auto;
}


.nav-label {

    color: #64748b;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: 1px;

    font-weight: 700;

    padding:
        0 12px
        10px;
}


.nav-item {

    display: flex;

    align-items: center;

    gap: 12px;

    color: #cbd5e1;

    text-decoration: none;

    padding: 12px 14px;

    margin-bottom: 5px;

    border-radius: 9px;

    font-size: 14px;

    font-weight: 500;

    transition: .2s;
}


.nav-item:hover {

    color: white;

    background:
        rgba(255,255,255,.07);
}


.nav-item.active {

    color: white;

    background:
        linear-gradient(
            90deg,
            rgba(37,99,235,.95),
            rgba(59,130,246,.75)
        );

    box-shadow:
        0 8px 18px
        rgba(37,99,235,.20);
}


.nav-icon {

    width: 21px;

    text-align: center;

    font-size: 17px;
}


/* SIDEBAR BOTTOM */

.sidebar-footer {

    padding: 15px;

    border-top:
        1px solid
        rgba(255,255,255,.08);
}


.sidebar-user {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 10px;
}


.sidebar-avatar {

    width: 38px;
    height: 38px;

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

    font-weight: 700;
}


.sidebar-user-info {

    min-width: 0;
}


.sidebar-user-name {

    font-size: 13px;

    font-weight: 700;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


.sidebar-user-role {

    color: #94a3b8;

    font-size: 11px;

    margin-top: 2px;
}


/* =========================================================
   MAIN
========================================================= */

.main {

    flex: 1;

    margin-left: 250px;

    min-width: 0;
}


/* =========================================================
   TOP BAR
========================================================= */

.topbar {

    height: 76px;

    background: white;

    border-bottom:
        1px solid var(--border);

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding:
        0 30px;

    position: sticky;

    top: 0;

    z-index: 900;
}


.mobile-menu {

    display: none;

    width: 40px;
    height: 40px;

    border: none;

    background: #f1f5f9;

    border-radius: 8px;

    cursor: pointer;

    font-size: 20px;
}


.topbar-left {

    display: flex;

    align-items: center;

    gap: 12px;
}


.topbar-title {

    font-size: 14px;

    color: var(--muted);
}


.topbar-title strong {

    color: var(--text);
}


.topbar-right {

    display: flex;

    align-items: center;

    gap: 14px;
}


.topbar-user {

    display: flex;

    align-items: center;

    gap: 10px;
}


.topbar-avatar {

    width: 38px;
    height: 38px;

    border-radius: 50%;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #7c3aed
        );

    color: white;

    display: flex;

    align-items: center;
    justify-content: center;

    font-weight: 700;
}


.topbar-user-text {

    text-align: right;
}


.topbar-user-name {

    font-size: 13px;

    font-weight: 700;
}


.topbar-user-role {

    font-size: 11px;

    color: var(--muted);

    text-transform: capitalize;
}


.logout-btn {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 9px 13px;

    color: #dc2626;

    background: #fef2f2;

    text-decoration: none;

    border-radius: 8px;

    font-size: 13px;

    font-weight: 600;

    transition: .2s;
}


.logout-btn:hover {

    background: #fee2e2;
}


/* =========================================================
   CONTENT
========================================================= */

.content {

    max-width: 1500px;

    margin: 0 auto;

    padding: 30px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-end;

    gap: 20px;

    margin-bottom: 28px;
}


.page-header h1 {

    font-size: 29px;

    line-height: 1.2;

    letter-spacing: -.8px;

    margin-bottom: 7px;
}


.page-header p {

    color: var(--muted);

    font-size: 14px;
}


.date-display {

    background: white;

    border: 1px solid var(--border);

    border-radius: 9px;

    padding: 10px 14px;

    color: var(--muted);

    font-size: 13px;
}


/* =========================================================
   ALERT
========================================================= */

.alert {

    background: #fff7ed;

    color: #9a3412;

    border:
        1px solid #fed7aa;

    padding: 13px 16px;

    border-radius: 9px;

    margin-bottom: 22px;

    font-size: 13px;
}


/* =========================================================
   KPI GRID
========================================================= */

.kpi-grid {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 18px;

    margin-bottom: 22px;
}


.kpi-card {

    background: white;

    border:
        1px solid var(--border);

    border-radius: 14px;

    padding: 21px;

    box-shadow: var(--shadow);

    position: relative;

    overflow: hidden;

    transition:
        transform .2s,
        box-shadow .2s;
}


.kpi-card:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 15px 35px
        rgba(15,23,42,.09);
}


.kpi-top {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    margin-bottom: 17px;
}


.kpi-icon {

    width: 45px;
    height: 45px;

    border-radius: 11px;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 21px;
}


.icon-blue {

    background: #eff6ff;
    color: #2563eb;
}


.icon-purple {

    background: #f5f3ff;
    color: #7c3aed;
}


.icon-orange {

    background: #fff7ed;
    color: #ea580c;
}


.icon-green {

    background: #f0fdf4;
    color: #16a34a;
}


.kpi-label {

    color: var(--muted);

    font-size: 13px;

    font-weight: 500;

    margin-bottom: 5px;
}


.kpi-value {

    color: var(--text);

    font-size: 26px;

    font-weight: 800;

    letter-spacing: -.6px;
}


.kpi-link {

    display: inline-block;

    margin-top: 13px;

    color: var(--primary);

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;
}


.kpi-link:hover {

    text-decoration: underline;
}


/* =========================================================
   TWO COLUMN
========================================================= */

.dashboard-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1.6fr)
        minmax(300px, .8fr);

    gap: 20px;

    margin-bottom: 22px;
}


/* =========================================================
   PANEL
========================================================= */

.panel {

    background: white;

    border:
        1px solid var(--border);

    border-radius: 14px;

    box-shadow: var(--shadow);

    overflow: hidden;
}


.panel-header {

    padding: 20px 22px;

    border-bottom:
        1px solid var(--border);

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;
}


.panel-title {

    font-size: 16px;

    font-weight: 750;
}


.panel-subtitle {

    margin-top: 4px;

    color: var(--muted);

    font-size: 12px;
}


.panel-link {

    color: var(--primary);

    text-decoration: none;

    font-size: 12px;

    font-weight: 700;

    white-space: nowrap;
}


.panel-link:hover {

    text-decoration: underline;
}


/* =========================================================
   STATUS OVERVIEW
========================================================= */

.status-content {

    padding: 22px;
}


.status-item {

    margin-bottom: 20px;
}


.status-item:last-child {

    margin-bottom: 0;
}


.status-row {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 8px;
}


.status-name {

    display: flex;

    align-items: center;

    gap: 9px;

    font-size: 13px;

    color: #475569;
}


.status-dot {

    width: 9px;
    height: 9px;

    border-radius: 50%;
}


.dot-pending {
    background: #f59e0b;
}

.dot-confirmed {
    background: #2563eb;
}

.dot-completed {
    background: #16a34a;
}

.dot-cancelled {
    background: #dc2626;
}


.status-number {

    font-size: 13px;

    font-weight: 700;
}


.progress {

    height: 7px;

    background: #f1f5f9;

    border-radius: 10px;

    overflow: hidden;
}


.progress-bar {

    height: 100%;

    border-radius: 10px;

    min-width: 2px;
}


.progress-pending {
    background: #f59e0b;
}

.progress-confirmed {
    background: #2563eb;
}

.progress-completed {
    background: #16a34a;
}

.progress-cancelled {
    background: #dc2626;
}


/* =========================================================
   QUICK ACTIONS
========================================================= */

.quick-actions {

    padding: 20px;
}


.action {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 13px;

    border:
        1px solid var(--border);

    border-radius: 10px;

    text-decoration: none;

    color: var(--text);

    margin-bottom: 10px;

    transition: .2s;
}


.action:last-child {

    margin-bottom: 0;
}


.action:hover {

    border-color:
        #bfdbfe;

    background:
        #eff6ff;

    transform:
        translateX(2px);
}


.action-icon {

    width: 38px;
    height: 38px;

    border-radius: 9px;

    display: flex;

    align-items: center;
    justify-content: center;

    background: #eff6ff;

    font-size: 17px;
}


.action-text strong {

    display: block;

    font-size: 13px;

    margin-bottom: 2px;
}


.action-text span {

    color: var(--muted);

    font-size: 11px;
}


/* =========================================================
   RECENT BOOKINGS
========================================================= */

.booking-panel {

    margin-top: 22px;
}


.table-wrapper {

    width: 100%;

    overflow-x: auto;
}


table {

    width: 100%;

    min-width: 900px;

    border-collapse: collapse;
}


thead {

    background: #f8fafc;
}


th {

    text-align: left;

    padding:
        13px 17px;

    color: #64748b;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: .6px;

    font-weight: 700;

    border-bottom:
        1px solid var(--border);
}


td {

    padding:
        15px 17px;

    border-bottom:
        1px solid #f1f5f9;

    font-size: 13px;

    vertical-align: middle;
}


tbody tr {

    transition: .15s;
}


tbody tr:hover {

    background: #f8fafc;
}


.booking-reference {

    color: var(--primary);

    font-weight: 700;
}


.customer-name {

    font-weight: 650;

    color: #1e293b;
}


.customer-email {

    color: var(--muted);

    font-size: 11px;

    margin-top: 3px;
}


.service-name {

    color: #475569;
}


.date-cell {

    white-space: nowrap;

    color: #475569;
}


.amount {

    font-weight: 700;

    white-space: nowrap;
}


/* =========================================================
   STATUS BADGES
========================================================= */

.status {

    display: inline-flex;

    align-items: center;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 750;

    text-transform: capitalize;

    white-space: nowrap;
}


.status-pending {

    background: #fef3c7;

    color: #92400e;
}


.status-confirmed {

    background: #dbeafe;

    color: #1d4ed8;
}


.status-completed {

    background: #dcfce7;

    color: #166534;
}


.status-cancelled {

    background: #fee2e2;

    color: #991b1b;
}


.status-rejected {

    background: #fee2e2;

    color: #991b1b;
}


.status-no_show {

    background: #f1f5f9;

    color: #475569;
}


.status-default {

    background: #e2e8f0;

    color: #334155;
}


/* PAYMENT */

.payment {

    display: inline-flex;

    padding: 5px 8px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 700;

    text-transform: capitalize;
}


.payment-paid {

    background: #dcfce7;

    color: #166534;
}


.payment-pending {

    background: #fef3c7;

    color: #92400e;
}


.payment-unpaid {

    background: #f1f5f9;

    color: #475569;
}


.payment-failed {

    background: #fee2e2;

    color: #991b1b;
}


.payment-refunded {

    background: #ede9fe;

    color: #6d28d9;
}


/* VIEW BUTTON */

.view-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding: 7px 11px;

    border-radius: 7px;

    background: #eff6ff;

    color: #2563eb;

    text-decoration: none;

    font-size: 11px;

    font-weight: 700;

    transition: .2s;
}


.view-btn:hover {

    background: #dbeafe;
}


/* EMPTY */

.empty {

    text-align: center;

    padding: 45px 20px;

    color: var(--muted);
}


.empty-icon {

    font-size: 35px;

    margin-bottom: 10px;
}


/* =========================================================
   OVERLAY
========================================================= */

.sidebar-overlay {

    display: none;

    position: fixed;

    inset: 0;

    background:
        rgba(15,23,42,.55);

    z-index: 999;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1200px) {

    .kpi-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .dashboard-grid {

        grid-template-columns: 1fr;
    }
}


@media (max-width: 850px) {

    .sidebar {

        transform:
            translateX(-100%);
    }

    .sidebar.open {

        transform:
            translateX(0);
    }

    .sidebar-overlay.open {

        display: block;
    }

    .main {

        margin-left: 0;
    }

    .mobile-menu {

        display: flex;

        align-items: center;

        justify-content: center;
    }

    .content {

        padding: 22px;
    }

    .topbar {

        padding:
            0 20px;
    }
}


@media (max-width: 600px) {

    .content {

        padding: 16px;
    }

    .topbar {

        height: 66px;

        padding:
            0 15px;
    }

    .topbar-title {

        display: none;
    }

    .topbar-user-text {

        display: none;
    }

    .logout-btn span {

        display: none;
    }

    .logout-btn {

        width: 38px;
        height: 38px;

        padding: 0;

        justify-content: center;
    }

    .page-header {

        display: block;
    }

    .page-header h1 {

        font-size: 24px;
    }

    .date-display {

        display: inline-block;

        margin-top: 15px;
    }

    .kpi-grid {

        grid-template-columns: 1fr;

        gap: 13px;
    }

    .kpi-card {

        padding: 18px;
    }

    .panel-header {

        padding: 17px;
    }

    .status-content,
    .quick-actions {

        padding: 17px;
    }
}


@media (max-width: 400px) {

    .kpi-value {

        font-size: 23px;
    }

}

</style>

</head>


<body>


<div class="app">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside
        class="sidebar"
        id="sidebar"
    >


        <!-- LOGO -->

        <div class="logo">

            <div class="logo-icon">
                B
            </div>

            <div class="logo-text">
                Book<span>Ease</span>
            </div>

        </div>


        <!-- NAVIGATION -->

        <nav class="sidebar-nav">


            <div class="nav-label">
                Main
            </div>


            <!-- Dashboard -->

            <a
                href="<?= dashboardSafe(BASE_URL) ?>/admin/dashboard.php"
                class="nav-item active"
            >

                <span class="nav-icon">
                    📊
                </span>

                <span>
                    Dashboard
                </span>

            </a>


            <!-- Bookings -->

            <a
                href="<?= dashboardSafe(BASE_URL) ?>/admin/bookings.php"
                class="nav-item"
            >

                <span class="nav-icon">
                    📅
                </span>

                <span>
                    Bookings
                </span>

            </a>


            <!-- Customers -->

            <a
                href="<?= dashboardSafe(BASE_URL) ?>/admin/customers.php"
                class="nav-item"
            >

                <span class="nav-icon">
                    👥
                </span>

                <span>
                    Customers
                </span>

            </a>


            <!-- Services -->

            <a
                href="<?= dashboardSafe(BASE_URL) ?>/admin/services.php"
                class="nav-item"
            >

                <span class="nav-icon">
                    🛎️
                </span>

                <span>
                    Services
                </span>

            </a>


            <!-- Payments -->

            <a
                href="<?= dashboardSafe(BASE_URL) ?>/admin/payments.php"
                class="nav-item"
            >

                <span class="nav-icon">
                    💳
                </span>

                <span>
                    Payments
                </span>

            </a>


            <div class="nav-label" style="margin-top:22px;">
                Management
            </div>


            <!-- Staff -->

            <a
                href="<?= dashboardSafe(BASE_URL) ?>/admin/staff.php"
                class="nav-item"
            >

                <span class="nav-icon">
                    🧑‍💼
                </span>

                <span>
                    Staff
                </span>

            </a>


            <!-- Reports -->

            <a
                href="<?= dashboardSafe(BASE_URL) ?>/admin/reports.php"
                class="nav-item"
            >

                <span class="nav-icon">
                    📈
                </span>

                <span>
                    Reports
                </span>

            </a>


            <!-- Settings -->

            <a
                href="<?= dashboardSafe(BASE_URL) ?>/admin/settings.php"
                class="nav-item"
            >

                <span class="nav-icon">
                    ⚙️
                </span>

                <span>
                    Settings
                </span>

            </a>


            <!-- Profile -->

            <a
                href="<?= dashboardSafe(BASE_URL) ?>/admin/profile.php"
                class="nav-item"
            >

                <span class="nav-icon">
                    👤
                </span>

                <span>
                    My Profile
                </span>

            </a>


        </nav>


        <!-- SIDEBAR USER -->

        <div class="sidebar-footer">

            <div class="sidebar-user">

                <div class="sidebar-avatar">

                    <?= dashboardSafe(
                        $currentUserInitial
                    ) ?>

                </div>


                <div class="sidebar-user-info">

                    <div class="sidebar-user-name">

                        <?= dashboardSafe(
                            $currentUserName
                        ) ?>

                    </div>

                    <div class="sidebar-user-role">

                        <?= dashboardSafe(
                            ucfirst($role)
                        ) ?>

                    </div>

                </div>

            </div>

        </div>


    </aside>


    <!-- MOBILE OVERLAY -->

    <div
        class="sidebar-overlay"
        id="sidebarOverlay"
    ></div>


    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="main">


        <!-- =================================================
             TOPBAR
        ================================================== -->

        <header class="topbar">


            <div class="topbar-left">


                <button
                    type="button"
                    class="mobile-menu"
                    id="mobileMenu"
                    aria-label="Open menu"
                >
                    ☰
                </button>


                <div class="topbar-title">

                    BookEase
                    /
                    <strong>
                        Dashboard
                    </strong>

                </div>

            </div>


            <div class="topbar-right">


                <div class="topbar-user">


                    <div class="topbar-user-text">

                        <div class="topbar-user-name">

                            <?= dashboardSafe(
                                $currentUserName
                            ) ?>

                        </div>

                        <div class="topbar-user-role">

                            <?= dashboardSafe(
                                $role
                            ) ?>

                        </div>

                    </div>


                    <div class="topbar-avatar">

                        <?= dashboardSafe(
                            $currentUserInitial
                        ) ?>

                    </div>


                </div>


                <a
                    href="<?= dashboardSafe(
                        BASE_URL
                    ) ?>/logout.php"
                    class="logout-btn"
                >

                    <span>
                        Logout
                    </span>

                    ↪

                </a>


            </div>


        </header>


        <!-- =================================================
             CONTENT
        ================================================== -->

        <div class="content">


            <!-- PAGE HEADER -->

            <div class="page-header">


                <div>

                    <h1>
                        Dashboard
                    </h1>

                    <p>
                        Welcome back,
                        <?= dashboardSafe(
                            $currentUserName
                        ) ?>.
                        Here's what's happening with your bookings today.
                    </p>

                </div>


                <div class="date-display">

                    📅

                    <?= date(
                        'l, d M Y'
                    ) ?>

                </div>


            </div>


            <!-- ERROR -->

            <?php if ($error !== ''): ?>

                <div class="alert">

                    ⚠️

                    <?= dashboardSafe(
                        $error
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 KPI CARDS
            ================================================== -->

            <div class="kpi-grid">


                <!-- CUSTOMERS -->

                <div class="kpi-card">


                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Total Customers
                            </div>

                            <div class="kpi-value">

                                <?= number_format(
                                    $totalCustomers
                                ) ?>

                            </div>

                        </div>


                        <div class="kpi-icon icon-blue">
                            👥
                        </div>

                    </div>


                    <a
                        href="<?= dashboardSafe(
                            BASE_URL
                        ) ?>/admin/customers.php"
                        class="kpi-link"
                    >
                        View customers →
                    </a>


                </div>


                <!-- BOOKINGS -->

                <div class="kpi-card">


                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Total Bookings
                            </div>

                            <div class="kpi-value">

                                <?= number_format(
                                    $totalBookings
                                ) ?>

                            </div>

                        </div>


                        <div class="kpi-icon icon-purple">
                            📅
                        </div>

                    </div>


                    <a
                        href="<?= dashboardSafe(
                            BASE_URL
                        ) ?>/admin/bookings.php"
                        class="kpi-link"
                    >
                        Manage bookings →
                    </a>


                </div>


                <!-- SERVICES -->

                <div class="kpi-card">


                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Available Services
                            </div>

                            <div class="kpi-value">

                                <?= number_format(
                                    $totalServices
                                ) ?>

                            </div>

                        </div>


                        <div class="kpi-icon icon-orange">
                            🛎️
                        </div>

                    </div>


                    <a
                        href="<?= dashboardSafe(
                            BASE_URL
                        ) ?>/admin/services.php"
                        class="kpi-link"
                    >
                        Manage services →
                    </a>


                </div>


                <!-- REVENUE -->

                <div class="kpi-card">


                    <div class="kpi-top">

                        <div>

                            <div class="kpi-label">
                                Paid Revenue
                            </div>

                            <div class="kpi-value">

                                KSh
                                <?= number_format(
                                    $totalPayments,
                                    2
                                ) ?>

                            </div>

                        </div>


                        <div class="kpi-icon icon-green">
                            💰
                        </div>

                    </div>


                    <a
                        href="<?= dashboardSafe(
                            BASE_URL
                        ) ?>/admin/payments.php"
                        class="kpi-link"
                    >
                        View payments →
                    </a>


                </div>


            </div>


            <!-- =================================================
                 STATUS + QUICK ACTIONS
            ================================================== -->

            <div class="dashboard-grid">


                <!-- STATUS OVERVIEW -->

                <div class="panel">


                    <div class="panel-header">


                        <div>

                            <div class="panel-title">
                                Booking Overview
                            </div>

                            <div class="panel-subtitle">
                                Current booking status distribution
                            </div>

                        </div>


                        <a
                            href="<?= dashboardSafe(
                                BASE_URL
                            ) ?>/admin/reports.php"
                            class="panel-link"
                        >
                            View report →
                        </a>


                    </div>


                    <div class="status-content">


                        <?php

                        $statusTotal =
                            max(
                                1,
                                $totalBookings
                            );


                        $pendingPercent =
                            min(
                                100,
                                ($pendingBookings /
                                $statusTotal) * 100
                            );


                        $confirmedPercent =
                            min(
                                100,
                                ($confirmedBookings /
                                $statusTotal) * 100
                            );


                        $completedPercent =
                            min(
                                100,
                                ($completedBookings /
                                $statusTotal) * 100
                            );


                        $cancelledPercent =
                            min(
                                100,
                                ($cancelledBookings /
                                $statusTotal) * 100
                            );

                        ?>


                        <!-- PENDING -->

                        <div class="status-item">


                            <div class="status-row">

                                <div class="status-name">

                                    <span class="status-dot dot-pending"></span>

                                    Pending

                                </div>


                                <div class="status-number">

                                    <?= number_format(
                                        $pendingBookings
                                    ) ?>

                                </div>

                            </div>


                            <div class="progress">

                                <div
                                    class="progress-bar progress-pending"
                                    style="width:<?= $pendingPercent ?>%;"
                                ></div>

                            </div>


                        </div>


                        <!-- CONFIRMED -->

                        <div class="status-item">


                            <div class="status-row">

                                <div class="status-name">

                                    <span class="status-dot dot-confirmed"></span>

                                    Confirmed

                                </div>


                                <div class="status-number">

                                    <?= number_format(
                                        $confirmedBookings
                                    ) ?>

                                </div>

                            </div>


                            <div class="progress">

                                <div
                                    class="progress-bar progress-confirmed"
                                    style="width:<?= $confirmedPercent ?>%;"
                                ></div>

                            </div>


                        </div>


                        <!-- COMPLETED -->

                        <div class="status-item">


                            <div class="status-row">

                                <div class="status-name">

                                    <span class="status-dot dot-completed"></span>

                                    Completed

                                </div>


                                <div class="status-number">

                                    <?= number_format(
                                        $completedBookings
                                    ) ?>

                                </div>

                            </div>


                            <div class="progress">

                                <div
                                    class="progress-bar progress-completed"
                                    style="width:<?= $completedPercent ?>%;"
                                ></div>

                            </div>


                        </div>


                        <!-- CANCELLED -->

                        <div class="status-item">


                            <div class="status-row">

                                <div class="status-name">

                                    <span class="status-dot dot-cancelled"></span>

                                    Cancelled

                                </div>


                                <div class="status-number">

                                    <?= number_format(
                                        $cancelledBookings
                                    ) ?>

                                </div>

                            </div>


                            <div class="progress">

                                <div
                                    class="progress-bar progress-cancelled"
                                    style="width:<?= $cancelledPercent ?>%;"
                                ></div>

                            </div>


                        </div>


                    </div>


                </div>


                <!-- QUICK ACTIONS -->

                <div class="panel">


                    <div class="panel-header">


                        <div>

                            <div class="panel-title">
                                Quick Actions
                            </div>

                            <div class="panel-subtitle">
                                Frequently used tools
                            </div>

                        </div>


                    </div>


                    <div class="quick-actions">


                        <a
                            href="<?= dashboardSafe(
                                BASE_URL
                            ) ?>/admin/bookings.php"
                            class="action"
                        >

                            <div class="action-icon">
                                📅
                            </div>

                            <div class="action-text">

                                <strong>
                                    Manage Bookings
                                </strong>

                                <span>
                                    View and update bookings
                                </span>

                            </div>

                        </a>


                        <a
                            href="<?= dashboardSafe(
                                BASE_URL
                            ) ?>/admin/services.php"
                            class="action"
                        >

                            <div class="action-icon">
                                🛎️
                            </div>

                            <div class="action-text">

                                <strong>
                                    Manage Services
                                </strong>

                                <span>
                                    Add or update services
                                </span>

                            </div>

                        </a>


                        <a
                            href="<?= dashboardSafe(
                                BASE_URL
                            ) ?>/admin/customers.php"
                            class="action"
                        >

                            <div class="action-icon">
                                👥
                            </div>

                            <div class="action-text">

                                <strong>
                                    Customers
                                </strong>

                                <span>
                                    Manage customer accounts
                                </span>

                            </div>

                        </a>


                        <a
                            href="<?= dashboardSafe(
                                BASE_URL
                            ) ?>/admin/reports.php"
                            class="action"
                        >

                            <div class="action-icon">
                                📈
                            </div>

                            <div class="action-text">

                                <strong>
                                    View Reports
                                </strong>

                                <span>
                                    Analyze system performance
                                </span>

                            </div>

                        </a>


                    </div>


                </div>


            </div>


            <!-- =================================================
                 RECENT BOOKINGS
            ================================================== -->

            <div class="panel booking-panel">


                <div class="panel-header">


                    <div>

                        <div class="panel-title">
                            Recent Bookings
                        </div>

                        <div class="panel-subtitle">
                            Latest activity from your booking system
                        </div>

                    </div>


                    <a
                        href="<?= dashboardSafe(
                            BASE_URL
                        ) ?>/admin/bookings.php"
                        class="panel-link"
                    >
                        View all bookings →
                    </a>


                </div>


                <div class="table-wrapper">


                    <table>


                        <thead>

                            <tr>

                                <th>
                                    Reference
                                </th>

                                <th>
                                    Customer
                                </th>

                                <th>
                                    Service
                                </th>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Payment
                                </th>

                                <th>
                                    Amount
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php if (
                            !empty(
                                $recentBookings
                            )
                        ): ?>


                            <?php foreach (
                                $recentBookings
                                as $row
                            ): ?>


                                <?php

                                $bookingId =
                                    (int) (
                                        $row['id']
                                        ?? 0
                                    );


                                $reference =
                                    $row[
                                        'booking_reference'
                                    ]
                                    ?? (
                                        'BK-' .
                                        $bookingId
                                    );


                                $customer =
                                    $row[
                                        'customer_name'
                                    ]
                                    ?? 'Unknown Customer';


                                $customerEmail =
                                    $row[
                                        'customer_email'
                                    ]
                                    ?? '';


                                $service =
                                    $row[
                                        'service_name'
                                    ]
                                    ?? 'Unknown Service';


                                $bookingDate =
                                    $row[
                                        'booking_date'
                                    ]
                                    ?? '';


                                $bookingTime =
                                    $row[
                                        'booking_time'
                                    ]
                                    ?? '';


                                $bookingStatus =
                                    strtolower(
                                        trim(
                                            $row[
                                                'status'
                                            ]
                                            ?? 'pending'
                                        )
                                    );


                                $paymentStatus =
                                    strtolower(
                                        trim(
                                            $row[
                                                'payment_status'
                                            ]
                                            ?? 'unpaid'
                                        )
                                    );


                                $amount =
                                    (float) (
                                        $row[
                                            'total_amount'
                                        ]
                                        ??
                                        $row[
                                            'amount'
                                        ]
                                        ?? 0
                                    );


                                $statusClass =
                                    dashboardStatusClass(
                                        $bookingStatus
                                    );


                                $paymentClass =
                                    dashboardPaymentClass(
                                        $paymentStatus
                                    );


                                ?>


                                <tr>


                                    <!-- REFERENCE -->

                                    <td>

                                        <div class="booking-reference">

                                            <?= dashboardSafe(
                                                $reference
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- CUSTOMER -->

                                    <td>

                                        <div class="customer-name">

                                            <?= dashboardSafe(
                                                $customer
                                            ) ?>

                                        </div>


                                        <?php if (
                                            $customerEmail !== ''
                                        ): ?>

                                            <div class="customer-email">

                                                <?= dashboardSafe(
                                                    $customerEmail
                                                ) ?>

                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- SERVICE -->

                                    <td>

                                        <div class="service-name">

                                            <?= dashboardSafe(
                                                $service
                                            ) ?>

                                        </div>

                                    </td>


                                    <!-- DATE -->

                                    <td>

                                        <div class="date-cell">

                                            <?php

                                            if (
                                                $bookingDate !== ''
                                            ) {

                                                echo dashboardSafe(
                                                    date(
                                                        'd M Y',
                                                        strtotime(
                                                            $bookingDate
                                                        )
                                                    )
                                                );

                                            } else {

                                                echo '-';
                                            }

                                            ?>


                                            <?php if (
                                                $bookingTime !== ''
                                            ): ?>

                                                <div
                                                    style="
                                                        color:#94a3b8;
                                                        font-size:11px;
                                                        margin-top:3px;
                                                    "
                                                >

                                                    <?= dashboardSafe(
                                                        $bookingTime
                                                    ) ?>

                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span
                                            class="status status-<?= dashboardSafe(
                                                $statusClass
                                            ) ?>"
                                        >

                                            <?= dashboardSafe(
                                                ucfirst(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $bookingStatus
                                                    )
                                                )
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- PAYMENT -->

                                    <td>

                                        <span
                                            class="payment payment-<?= dashboardSafe(
                                                $paymentClass
                                            ) ?>"
                                        >

                                            <?= dashboardSafe(
                                                ucfirst(
                                                    $paymentStatus
                                                )
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


                                    <!-- ACTION -->

                                    <td>

                                        <?php if (
                                            $bookingId > 0
                                        ): ?>

                                            <a
                                                href="<?= dashboardSafe(
                                                    BASE_URL
                                                ) ?>/admin/booking-view.php?id=<?= $bookingId ?>"
                                                class="view-btn"
                                            >
                                                View
                                            </a>

                                        <?php else: ?>

                                            -

                                        <?php endif; ?>

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
                                        📅
                                    </div>

                                    <div>
                                        No bookings found yet.
                                    </div>

                                </td>

                            </tr>


                        <?php endif; ?>


                        </tbody>


                    </table>


                </div>


            </div>


        </div>


    </main>


</div>


<script>

/*
|--------------------------------------------------------------------------
| Mobile Sidebar
|--------------------------------------------------------------------------
*/

const mobileMenu =
    document.getElementById('mobileMenu');

const sidebar =
    document.getElementById('sidebar');

const sidebarOverlay =
    document.getElementById('sidebarOverlay');


function openSidebar() {

    sidebar.classList.add('open');

    sidebarOverlay.classList.add('open');

}


function closeSidebar() {

    sidebar.classList.remove('open');

    sidebarOverlay.classList.remove('open');

}


if (mobileMenu) {

    mobileMenu.addEventListener(
        'click',
        openSidebar
    );

}


if (sidebarOverlay) {

    sidebarOverlay.addEventListener(
        'click',
        closeSidebar
    );

}


/*
|--------------------------------------------------------------------------
| Close sidebar when clicking a link on mobile
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll('.sidebar .nav-item')
    .forEach(function(link) {

        link.addEventListener(
            'click',
            function() {

                if (
                    window.innerWidth <= 850
                ) {

                    closeSidebar();

                }

            }
        );

    });


/*
|--------------------------------------------------------------------------
| Prevent mobile menu from staying open after resizing
|--------------------------------------------------------------------------
*/

window.addEventListener(
    'resize',
    function() {

        if (
            window.innerWidth > 850
        ) {

            closeSidebar();

        }

    }
);

</script>


</body>

</html>