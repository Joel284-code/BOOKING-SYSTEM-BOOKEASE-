<?php

/*
|--------------------------------------------------------------------------
| BookEase - Booking Report
|--------------------------------------------------------------------------
| File: admin/booking-report.php
|
| Features:
| - Booking report
| - Filters
| - Print
| - Download CSV
| - Payment information
| - Admin / Staff access
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
| Current Role
|--------------------------------------------------------------------------
*/

$role = strtolower(
    trim(
        (string) (
            function_exists('currentUserRole')
                ? currentUserRole()
                : ($_SESSION['role'] ?? 'customer')
        )
    )
);


/*
|--------------------------------------------------------------------------
| Admin / Staff Only
|--------------------------------------------------------------------------
*/

if (
    !in_array(
        $role,
        ['admin', 'staff'],
        true
    )
) {

    header(
        'Location: ' .
        BASE_URL .
        '/customer/dashboard.php'
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

        die(
            'Database connection error: ' .
            htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            )
        );
    }

    die(
        'Unable to connect to the database.'
    );
}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$status = trim(
    (string) (
        $_GET['status'] ?? ''
    )
);

$dateFrom = trim(
    (string) (
        $_GET['date_from'] ?? ''
    )
);

$dateTo = trim(
    (string) (
        $_GET['date_to'] ?? ''
    )
);

$serviceId = (int) (
    $_GET['service_id'] ?? 0
);


/*
|--------------------------------------------------------------------------
| Allowed Statuses
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'pending',
    'confirmed',
    'completed',
    'cancelled',
    'rejected',
    'no_show'
];


/*
|--------------------------------------------------------------------------
| Validate Status
|--------------------------------------------------------------------------
*/

if (
    $status !== '' &&
    !in_array(
        $status,
        $allowedStatuses,
        true
    )
) {

    $status = '';
}


/*
|--------------------------------------------------------------------------
| Validate Dates
|--------------------------------------------------------------------------
*/

if (
    $dateFrom !== '' &&
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $dateFrom
    )
) {

    $dateFrom = '';
}


if (
    $dateTo !== '' &&
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $dateTo
    )
) {

    $dateTo = '';
}


/*
|--------------------------------------------------------------------------
| Make Sure Date Range Is Correct
|--------------------------------------------------------------------------
*/

if (
    $dateFrom !== '' &&
    $dateTo !== '' &&
    $dateFrom > $dateTo
) {

    $temporaryDate = $dateFrom;

    $dateFrom = $dateTo;

    $dateTo = $temporaryDate;
}


/*
|--------------------------------------------------------------------------
| Build Main Query
|--------------------------------------------------------------------------
|
| The report uses:
|
| bookings
| users
| services
| payments
|
| Payment information is loaded using the latest payment
| belonging to each booking.
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        b.id,

        b.booking_reference,

        b.user_id,

        b.service_id,

        b.booking_date,

        b.booking_time,

        b.status,

        b.total_amount,

        b.payment_status,

        b.customer_name AS booking_customer_name,

        b.customer_email AS booking_customer_email,

        b.customer_phone AS booking_customer_phone,

        u.name AS account_customer_name,

        u.email AS account_customer_email,

        u.phone AS account_customer_phone,

        s.name AS service_name,

        p.payment_method,

        p.transaction_reference,

        p.transaction_id,

        p.status AS payment_record_status,

        p.paid_at

    FROM bookings b

    LEFT JOIN users u
        ON b.user_id = u.id

    LEFT JOIN services s
        ON b.service_id = s.id

    LEFT JOIN payments p
        ON p.id = (
            SELECT MAX(p2.id)
            FROM payments p2
            WHERE p2.booking_id = b.id
        )

    WHERE 1 = 1
";


$params = [];


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if ($status !== '') {

    $sql .= "
        AND b.status = ?
    ";

    $params[] = $status;
}


/*
|--------------------------------------------------------------------------
| From Date
|--------------------------------------------------------------------------
*/

if ($dateFrom !== '') {

    $sql .= "
        AND b.booking_date >= ?
    ";

    $params[] = $dateFrom;
}


/*
|--------------------------------------------------------------------------
| To Date
|--------------------------------------------------------------------------
*/

if ($dateTo !== '') {

    $sql .= "
        AND b.booking_date <= ?
    ";

    $params[] = $dateTo;
}


/*
|--------------------------------------------------------------------------
| Service Filter
|--------------------------------------------------------------------------
*/

if ($serviceId > 0) {

    $sql .= "
        AND b.service_id = ?
    ";

    $params[] = $serviceId;
}


/*
|--------------------------------------------------------------------------
| Ordering
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        b.booking_date DESC,
        b.booking_time DESC,
        b.id DESC
";


/*
|--------------------------------------------------------------------------
| Execute Query
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->prepare($sql);

    $stmt->execute($params);

    $bookings = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $e) {

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        die(
            'Booking report error: ' .
            htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            )
        );
    }

    die(
        'Unable to load booking report.'
    );
}


/*
|--------------------------------------------------------------------------
| Currency
|--------------------------------------------------------------------------
*/

$currency = defined('CURRENCY')
    ? CURRENCY
    : 'KSh';


/*
|--------------------------------------------------------------------------
| Calculate Totals
|--------------------------------------------------------------------------
*/

$totalBookings = count($bookings);

$totalAmount = 0.0;

$paidAmount = 0.0;

$pendingPaymentAmount = 0.0;

$pendingBookings = 0;

$confirmedBookings = 0;

$completedBookings = 0;

$cancelledBookings = 0;

$rejectedBookings = 0;

$noShowBookings = 0;


/*
|--------------------------------------------------------------------------
| Process Report Rows
|--------------------------------------------------------------------------
*/

foreach ($bookings as &$booking) {

    $booking['total_amount'] =
        (float) (
            $booking['total_amount'] ?? 0
        );

    $totalAmount +=
        $booking['total_amount'];


    /*
    |----------------------------------------------------------------------
    | Booking Status
    |----------------------------------------------------------------------
    */

    $bookingStatus = strtolower(
        trim(
            (string) (
                $booking['status'] ?? 'pending'
            )
        )
    );


    switch ($bookingStatus) {

        case 'pending':

            $pendingBookings++;

            break;


        case 'confirmed':

            $confirmedBookings++;

            break;


        case 'completed':

            $completedBookings++;

            break;


        case 'cancelled':

            $cancelledBookings++;

            break;


        case 'rejected':

            $rejectedBookings++;

            break;


        case 'no_show':

            $noShowBookings++;

            break;
    }


    /*
    |----------------------------------------------------------------------
    | Payment Status
    |----------------------------------------------------------------------
    */

    $paymentStatus = strtolower(
        trim(
            (string) (
                $booking['payment_status']
                ?? $booking['payment_record_status']
                ?? 'unpaid'
            )
        )
    );


    /*
    |----------------------------------------------------------------------
    | Paid Amount
    |----------------------------------------------------------------------
    */

    if (
        in_array(
            $paymentStatus,
            [
                'paid',
                'success',
                'successful',
                'completed'
            ],
            true
        )
    ) {

        $paidAmount +=
            $booking['total_amount'];

    } else {

        $pendingPaymentAmount +=
            $booking['total_amount'];
    }


    /*
    |----------------------------------------------------------------------
    | Customer Name Fallback
    |----------------------------------------------------------------------
    */

    $booking['display_customer_name'] =
        $booking['booking_customer_name']
        ?: $booking['account_customer_name']
        ?: 'Unknown Customer';


    /*
    |----------------------------------------------------------------------
    | Customer Email Fallback
    |----------------------------------------------------------------------
    */

    $booking['display_customer_email'] =
        $booking['booking_customer_email']
        ?: $booking['account_customer_email']
        ?: '';


    /*
    |----------------------------------------------------------------------
    | Customer Phone Fallback
    |----------------------------------------------------------------------
    */

    $booking['display_customer_phone'] =
        $booking['booking_customer_phone']
        ?: $booking['account_customer_phone']
        ?: '';


    /*
    |----------------------------------------------------------------------
    | Reference Fallback
    |----------------------------------------------------------------------
    */

    $booking['display_reference'] =
        $booking['booking_reference']
        ?: 'BOOK-' . $booking['id'];


    /*
    |----------------------------------------------------------------------
    | Payment Status
    |----------------------------------------------------------------------
    */

    $booking['display_payment_status'] =
        $paymentStatus !== ''
            ? $paymentStatus
            : 'unpaid';

}

unset($booking);


/*
|--------------------------------------------------------------------------
| Get Services
|--------------------------------------------------------------------------
*/

$services = [];

try {

    $serviceStmt = $conn->query("
        SELECT
            id,
            name
        FROM services
        ORDER BY name ASC
    ");

    $services = $serviceStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $e) {

    $services = [];
}


/*
|--------------------------------------------------------------------------
| Download CSV
|--------------------------------------------------------------------------
|
| URL:
|
| booking-report.php?export=csv
|
|--------------------------------------------------------------------------
*/

$export = strtolower(
    trim(
        (string) (
            $_GET['export'] ?? ''
        )
    )
);


if ($export === 'csv') {

    /*
    |----------------------------------------------------------------------
    | Prevent cached output
    |----------------------------------------------------------------------
    */

    while (
        ob_get_level() > 0
    ) {

        ob_end_clean();
    }


    $filename =
        'bookease-booking-report-' .
        date('Y-m-d-H-i-s') .
        '.csv';


    header(
        'Content-Type: text/csv; charset=utf-8'
    );

    header(
        'Content-Disposition: attachment; filename="' .
        $filename .
        '"'
    );

    header(
        'Pragma: no-cache'
    );

    header(
        'Expires: 0'
    );


    $output = fopen(
        'php://output',
        'w'
    );


    /*
    |----------------------------------------------------------------------
    | UTF-8 BOM
    |----------------------------------------------------------------------
    |
    | Helps Microsoft Excel correctly detect UTF-8.
    |
    |----------------------------------------------------------------------
    */

    fprintf(
        $output,
        chr(0xEF) .
        chr(0xBB) .
        chr(0xBF)
    );


    /*
    |----------------------------------------------------------------------
    | Report Header
    |----------------------------------------------------------------------
    */

    fputcsv(
        $output,
        [
            'BookEase Booking Report'
        ]
    );


    fputcsv(
        $output,
        [
            'Generated',
            date('Y-m-d H:i:s')
        ]
    );


    fputcsv(
        $output,
        [
            'Filters',
            'Status: ' .
            ($status !== '' ? $status : 'All') .
            ' | From: ' .
            ($dateFrom !== '' ? $dateFrom : 'All') .
            ' | To: ' .
            ($dateTo !== '' ? $dateTo : 'All')
        ]
    );


    fputcsv(
        $output,
        []
    );


    /*
    |----------------------------------------------------------------------
    | Summary
    |----------------------------------------------------------------------
    */

    fputcsv(
        $output,
        [
            'Total Bookings',
            $totalBookings
        ]
    );

    fputcsv(
        $output,
        [
            'Total Value',
            number_format(
                $totalAmount,
                2,
                '.',
                ''
            )
        ]
    );

    fputcsv(
        $output,
        [
            'Paid Amount',
            number_format(
                $paidAmount,
                2,
                '.',
                ''
            )
        ]
    );

    fputcsv(
        $output,
        [
            'Pending Payment Amount',
            number_format(
                $pendingPaymentAmount,
                2,
                '.',
                ''
            )
        ]
    );


    fputcsv(
        $output,
        []
    );


    /*
    |----------------------------------------------------------------------
    | Table Headings
    |----------------------------------------------------------------------
    */

    fputcsv(
        $output,
        [
            '#',
            'Booking Reference',
            'Customer',
            'Email',
            'Phone',
            'Service',
            'Booking Date',
            'Booking Time',
            'Amount',
            'Booking Status',
            'Payment Status',
            'Payment Method',
            'Transaction Reference',
            'Transaction ID',
            'Paid At'
        ]
    );


    /*
    |----------------------------------------------------------------------
    | Data
    |----------------------------------------------------------------------
    */

    foreach (
        $bookings
        as $index => $booking
    ) {

        $time = '';

        if (
            !empty(
                $booking['booking_time']
            )
        ) {

            $timestamp = strtotime(
                $booking['booking_time']
            );

            $time =
                $timestamp !== false
                    ? date(
                        'h:i A',
                        $timestamp
                    )
                    : $booking['booking_time'];
        }


        fputcsv(
            $output,
            [
                $index + 1,

                $booking['display_reference'],

                $booking['display_customer_name'],

                $booking['display_customer_email'],

                $booking['display_customer_phone'],

                $booking['service_name']
                    ?? 'Service',

                $booking['booking_date']
                    ?? '',

                $time,

                number_format(
                    (float) (
                        $booking['total_amount']
                        ?? 0
                    ),
                    2,
                    '.',
                    ''
                ),

                ucfirst(
                    str_replace(
                        '_',
                        ' ',
                        $booking['status']
                        ?? 'pending'
                    )
                ),

                ucfirst(
                    str_replace(
                        '_',
                        ' ',
                        $booking['display_payment_status']
                    )
                ),

                strtoupper(
                    $booking['payment_method']
                    ?? ''
                ),

                $booking['transaction_reference']
                    ?? '',

                $booking['transaction_id']
                    ?? '',

                $booking['paid_at']
                    ?? ''
            ]
        );
    }


    /*
    |----------------------------------------------------------------------
    | Close CSV
    |----------------------------------------------------------------------
    */

    fclose($output);

    exit;
}


/*
|--------------------------------------------------------------------------
| Page Title
|--------------------------------------------------------------------------
*/

$pageTitle =
    'Booking Report';


/*
|--------------------------------------------------------------------------
| Current Date
|--------------------------------------------------------------------------
*/

$generatedAt =
    date('d M Y, h:i A');


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
        <?= e($pageTitle) ?>
        |
        <?= e(APP_NAME) ?>
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

            background: #f5f7fb;

            color: #1e293b;
        }


        .report-container {

            margin-left: 240px;

            padding: 30px;

            max-width: 1800px;
        }


        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .report-header {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            margin-bottom: 25px;
        }


        .report-title h1 {

            margin: 0 0 6px;

            font-size: 28px;

            color: #0f172a;
        }


        .report-title p {

            margin: 0;

            color: #64748b;

            font-size: 14px;
        }


        .generated {

            margin-top: 7px;

            color: #94a3b8;

            font-size: 12px;
        }


        /*
        |--------------------------------------------------------------------------
        | Buttons
        |--------------------------------------------------------------------------
        */

        .actions {

            display: flex;

            flex-wrap: wrap;

            gap: 10px;
        }


        .btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            border: none;

            padding:
                11px 16px;

            border-radius: 7px;

            cursor: pointer;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;

            transition:
                .2s ease;
        }


        .btn:hover {

            transform:
                translateY(-1px);
        }


        .btn-primary {

            background: #2563eb;

            color: #ffffff;
        }


        .btn-primary:hover {

            background: #1d4ed8;
        }


        .btn-success {

            background: #16a34a;

            color: #ffffff;
        }


        .btn-success:hover {

            background: #15803d;
        }


        .btn-secondary {

            background: #64748b;

            color: #ffffff;
        }


        .btn-secondary:hover {

            background: #475569;
        }


        /*
        |--------------------------------------------------------------------------
        | Filter Card
        |--------------------------------------------------------------------------
        */

        .filter-card {

            background: #ffffff;

            padding: 22px;

            border-radius: 12px;

            margin-bottom: 25px;

            box-shadow:
                0 4px 18px
                rgba(
                    0,
                    0,
                    0,
                    .05
                );
        }


        .filter-title {

            margin:
                0 0 18px;

            font-size: 16px;

            color: #0f172a;
        }


        .filter-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(
                        170px,
                        1fr
                    )
                );

            gap: 16px;

            align-items: end;
        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            font-size: 13px;

            font-weight: 600;

            color: #334155;
        }


        .form-control {

            width: 100%;

            padding:
                11px 12px;

            border:
                1px solid #cbd5e1;

            border-radius: 7px;

            background: #ffffff;

            color: #1e293b;

            font-size: 14px;

            outline: none;
        }


        .form-control:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(
                    37,
                    99,
                    235,
                    .10
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */

        .summary-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(
                        180px,
                        1fr
                    )
                );

            gap: 16px;

            margin-bottom: 25px;
        }


        .summary-card {

            background: #ffffff;

            padding: 20px;

            border-radius: 12px;

            box-shadow:
                0 4px 18px
                rgba(
                    0,
                    0,
                    0,
                    .05
                );
        }


        .summary-card h3 {

            margin:
                0 0 8px;

            color: #64748b;

            font-size: 13px;

            font-weight: 600;
        }


        .summary-card strong {

            font-size: 23px;

            color: #0f172a;
        }


        /*
        |--------------------------------------------------------------------------
        | Report Information
        |--------------------------------------------------------------------------
        */

        .report-info {

            background: #ffffff;

            padding: 18px 20px;

            border-radius: 12px;

            margin-bottom: 20px;

            border:
                1px solid #e2e8f0;
        }


        .report-info strong {

            color: #0f172a;
        }


        /*
        |--------------------------------------------------------------------------
        | Table
        |--------------------------------------------------------------------------
        */

        .table-card {

            background: #ffffff;

            border-radius: 12px;

            overflow: hidden;

            box-shadow:
                0 4px 18px
                rgba(
                    0,
                    0,
                    0,
                    .05
                );
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


        th {

            padding:
                14px 15px;

            text-align: left;

            background: #f8fafc;

            color: #64748b;

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;

            border-bottom:
                1px solid #e2e8f0;

            white-space: nowrap;
        }


        td {

            padding:
                14px 15px;

            font-size: 13px;

            border-bottom:
                1px solid #e2e8f0;

            vertical-align: middle;
        }


        tbody tr:hover {

            background: #f8fafc;
        }


        tbody tr:last-child td {

            border-bottom: none;
        }


        .reference {

            color: #2563eb;

            font-weight: 700;

            white-space: nowrap;
        }


        .customer-name {

            font-weight: 600;

            color: #1e293b;
        }


        .customer-contact {

            margin-top: 4px;

            color: #64748b;

            font-size: 11px;

            line-height: 1.5;
        }


        .amount {

            font-weight: 700;

            white-space: nowrap;
        }


        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        .status {

            display: inline-block;

            padding:
                5px 9px;

            border-radius: 20px;

            font-size: 10px;

            font-weight: 700;

            text-transform: capitalize;

            white-space: nowrap;
        }


        .status-pending {

            background: #fef3c7;

            color: #92400e;
        }


        .status-confirmed {

            background: #dbeafe;

            color: #1e40af;
        }


        .status-completed {

            background: #dcfce7;

            color: #166534;
        }


        .status-cancelled,

        .status-rejected {

            background: #fee2e2;

            color: #991b1b;
        }


        .status-no_show {

            background: #f1f5f9;

            color: #475569;
        }


        /*
        |--------------------------------------------------------------------------
        | Payment Status
        |--------------------------------------------------------------------------
        */

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

            color: #5b21b6;
        }


        /*
        |--------------------------------------------------------------------------
        | Empty
        |--------------------------------------------------------------------------
        */

        .empty {

            text-align: center;

            padding: 60px 20px;

            color: #64748b;
        }


        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        .report-footer {

            display: flex;

            justify-content: space-between;

            gap: 20px;

            margin-top: 20px;

            padding: 15px 0;

            color: #64748b;

            font-size: 12px;
        }


        /*
        |--------------------------------------------------------------------------
        | Mobile
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .report-container {

                margin-left: 70px;

                padding: 20px 15px;
            }


            .report-header {

                align-items: flex-start;

                flex-direction: column;
            }


            .actions {

                width: 100%;
            }


            .actions .btn {

                flex: 1;
            }

        }


        @media (max-width: 600px) {

            .report-container {

                margin-left: 0;

                padding: 15px;
            }


            .actions {

                flex-direction: column;
            }


            .actions .btn {

                width: 100%;
            }

        }


        /*
        |--------------------------------------------------------------------------
        | Print
        |--------------------------------------------------------------------------
        */

        @media print {

            @page {

                size: landscape;

                margin: 10mm;
            }


            body {

                background: #ffffff;

                color: #000000;
            }


            .no-print,

            nav,

            .navbar,

            .sidebar,

            aside,

            header:not(.report-print-header) {

                display: none !important;
            }


            .report-container {

                margin: 0 !important;

                padding: 0 !important;

                max-width: none !important;

                width: 100% !important;
            }


            .report-header {

                display: block;

                margin-bottom: 15px;
            }


            .report-title h1 {

                font-size: 22px;
            }


            .report-title p {

                color: #000000;
            }


            .generated {

                color: #444444;
            }


            .summary-grid {

                grid-template-columns:
                    repeat(
                        4,
                        1fr
                    );

                gap: 8px;

                margin-bottom: 15px;
            }


            .summary-card {

                padding: 10px;

                border:
                    1px solid #cccccc;

                box-shadow: none;

                border-radius: 4px;
            }


            .summary-card h3 {

                color: #444444;

                font-size: 10px;
            }


            .summary-card strong {

                font-size: 16px;
            }


            .table-card {

                box-shadow: none;

                border:
                    1px solid #cccccc;

                border-radius: 0;
            }


            .table-wrapper {

                overflow: visible;
            }


            table {

                min-width: 0;

                width: 100%;

                font-size: 9px;
            }


            th {

                background: #eeeeee !important;

                color: #000000 !important;

                padding: 6px;

                font-size: 8px;
            }


            td {

                padding: 6px;

                font-size: 8px;

                color: #000000;
            }


            .status,

            .payment-paid,

            .payment-pending,

            .payment-unpaid,

            .payment-failed,

            .payment-refunded {

                background: transparent !important;

                color: #000000 !important;

                padding: 0;

                font-size: 8px;
            }


            .report-info {

                padding: 8px;

                border:
                    1px solid #cccccc;

                border-radius: 0;

                margin-bottom: 10px;

                font-size: 9px;
            }


            .report-footer {

                color: #000000;

                border-top:
                    1px solid #cccccc;

                padding-top: 8px;
            }

        }

    </style>

</head>


<body>


<?php

/*
|--------------------------------------------------------------------------
| Navbar
|--------------------------------------------------------------------------
*/

$navbarFile =
    __DIR__ .
    '/../includes/navbar.php';

if (
    file_exists($navbarFile)
) {

    require_once $navbarFile;
}


/*
|--------------------------------------------------------------------------
| Sidebar
|--------------------------------------------------------------------------
*/

$sidebarFile =
    __DIR__ .
    '/../includes/sidebar.php';

if (
    $role === 'admin' &&
    file_exists($sidebarFile)
) {

    require_once $sidebarFile;
}

?>


<div class="report-container">


    <!-- HEADER -->

    <div class="report-header">


        <div class="report-title">

            <h1>
                Booking Report
            </h1>

            <p>
                View, print and download BookEase booking information.
            </p>

            <div class="generated">

                Generated:
                <?= e($generatedAt) ?>

            </div>

        </div>


        <!-- ACTIONS -->

        <div class="actions no-print">


            <!-- PRINT -->

            <button
                type="button"
                onclick="window.print()"
                class="btn btn-primary"
            >

                🖨 Print Report

            </button>


            <!-- DOWNLOAD CSV -->

            <?php

            $downloadParams = $_GET;

            $downloadParams['export'] = 'csv';

            $downloadUrl =
                'booking-report.php?' .
                http_build_query(
                    $downloadParams
                );

            ?>

            <a
                href="<?= e($downloadUrl) ?>"
                class="btn btn-success"
            >

                ↓ Download CSV

            </a>


            <!-- BACK -->

            <a
                href="<?= BASE_URL ?>/admin/dashboard.php"
                class="btn btn-secondary"
            >

                Dashboard

            </a>

        </div>


    </div>


    <!-- FILTERS -->

    <div class="filter-card no-print">


        <h2 class="filter-title">

            Report Filters

        </h2>


        <form
            method="GET"
            action="booking-report.php"
        >


            <div class="filter-grid">


                <!-- STATUS -->

                <div class="form-group">

                    <label for="status">
                        Booking Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        class="form-control"
                    >

                        <option value="">
                            All Statuses
                        </option>


                        <?php foreach (
                            $allowedStatuses
                            as $item
                        ): ?>

                            <option
                                value="<?= e($item) ?>"
                                <?= (
                                    $status === $item
                                )
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= e(
                                    ucwords(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $item
                                        )
                                    )
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- SERVICE -->

                <div class="form-group">

                    <label for="service_id">
                        Service
                    </label>

                    <select
                        id="service_id"
                        name="service_id"
                        class="form-control"
                    >

                        <option value="0">
                            All Services
                        </option>


                        <?php foreach (
                            $services
                            as $service
                        ): ?>

                            <option
                                value="<?= (int) $service['id'] ?>"
                                <?= (
                                    $serviceId ===
                                    (int) $service['id']
                                )
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= e(
                                    $service['name']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- FROM DATE -->

                <div class="form-group">

                    <label for="date_from">
                        From Date
                    </label>

                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        class="form-control"
                        value="<?= e($dateFrom) ?>"
                    >

                </div>


                <!-- TO DATE -->

                <div class="form-group">

                    <label for="date_to">
                        To Date
                    </label>

                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        class="form-control"
                        value="<?= e($dateTo) ?>"
                    >

                </div>


                <!-- BUTTONS -->

                <div>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        Filter

                    </button>


                    <a
                        href="booking-report.php"
                        class="btn btn-secondary"
                    >

                        Reset

                    </a>

                </div>


            </div>


        </form>


    </div>


    <!-- REPORT FILTER INFORMATION -->

    <div class="report-info">

        <strong>
            Report:
        </strong>

        <?= $status !== ''
            ? e(
                ucwords(
                    str_replace(
                        '_',
                        ' ',
                        $status
                    )
                )
            )
            : 'All Booking Statuses'
        ?>

        &nbsp; | &nbsp;

        <strong>
            Date:
        </strong>

        <?= $dateFrom !== ''
            ? e($dateFrom)
            : 'All'
        ?>

        -

        <?= $dateTo !== ''
            ? e($dateTo)
            : 'All'
        ?>

        &nbsp; | &nbsp;

        <strong>
            Service:
        </strong>

        <?php

        $selectedServiceName =
            'All Services';

        foreach (
            $services
            as $service
        ) {

            if (
                (int) $service['id'] ===
                $serviceId
            ) {

                $selectedServiceName =
                    $service['name'];

                break;
            }
        }

        ?>

        <?= e($selectedServiceName) ?>

    </div>


    <!-- SUMMARY -->

    <div class="summary-grid">


        <div class="summary-card">

            <h3>
                Total Bookings
            </h3>

            <strong>
                <?= number_format(
                    $totalBookings
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Total Value
            </h3>

            <strong>

                <?= e($currency) ?>

                <?= number_format(
                    $totalAmount,
                    2
                ) ?>

            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Paid Amount
            </h3>

            <strong>

                <?= e($currency) ?>

                <?= number_format(
                    $paidAmount,
                    2
                ) ?>

            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Pending Payment
            </h3>

            <strong>

                <?= e($currency) ?>

                <?= number_format(
                    $pendingPaymentAmount,
                    2
                ) ?>

            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Pending Bookings
            </h3>

            <strong>
                <?= number_format(
                    $pendingBookings
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Confirmed
            </h3>

            <strong>
                <?= number_format(
                    $confirmedBookings
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Completed
            </h3>

            <strong>
                <?= number_format(
                    $completedBookings
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Cancelled
            </h3>

            <strong>
                <?= number_format(
                    $cancelledBookings
                ) ?>
            </strong>

        </div>


    </div>


    <!-- BOOKING TABLE -->

    <div class="table-card">


        <div class="table-wrapper">


            <table>


                <thead>

                    <tr>

                        <th>
                            #
                        </th>

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
                            Time
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Booking Status
                        </th>

                        <th>
                            Payment Status
                        </th>

                        <th>
                            Payment Method
                        </th>

                        <th>
                            Transaction
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    empty($bookings)
                ): ?>

                    <tr>

                        <td
                            colspan="11"
                            class="empty"
                        >

                            No bookings found for the selected filters.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach (
                        $bookings
                        as $index => $booking
                    ): ?>


                        <?php

                        /*
                        |--------------------------------------------------------------------------
                        | Booking Status
                        |--------------------------------------------------------------------------
                        */

                        $bookingStatus =
                            strtolower(
                                trim(
                                    (string) (
                                        $booking['status']
                                        ?? 'pending'
                                    )
                                )
                            );


                        $statusClass =
                            'status-' .
                            preg_replace(
                                '/[^a-z0-9_-]/',
                                '',
                                $bookingStatus
                            );


                        $statusLabel =
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $bookingStatus
                                )
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | Payment Status
                        |--------------------------------------------------------------------------
                        */

                        $paymentStatus =
                            $booking[
                                'display_payment_status'
                            ];


                        $paymentClass =
                            'payment-' .
                            preg_replace(
                                '/[^a-z0-9_-]/',
                                '',
                                $paymentStatus
                            );


                        $paymentLabel =
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $paymentStatus
                                )
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | Time
                        |--------------------------------------------------------------------------
                        */

                        $bookingTime =
                            $booking[
                                'booking_time'
                            ]
                            ?? '';

                        $displayTime = '-';


                        if (
                            $bookingTime !== ''
                        ) {

                            $timestamp =
                                strtotime(
                                    $bookingTime
                                );

                            $displayTime =
                                $timestamp !== false
                                    ? date(
                                        'h:i A',
                                        $timestamp
                                    )
                                    : $bookingTime;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Payment Method
                        |--------------------------------------------------------------------------
                        */

                        $paymentMethod =
                            trim(
                                (string) (
                                    $booking[
                                        'payment_method'
                                    ]
                                    ?? ''
                                )
                            );


                        /*
                        |--------------------------------------------------------------------------
                        | Transaction
                        |--------------------------------------------------------------------------
                        */

                        $transaction =
                            $booking[
                                'transaction_reference'
                            ]
                            ?? '';


                        ?>

                        <tr>


                            <!-- NUMBER -->

                            <td>

                                <?= $index + 1 ?>

                            </td>


                            <!-- REFERENCE -->

                            <td>

                                <span class="reference">

                                    <?= e(
                                        $booking[
                                            'display_reference'
                                        ]
                                    ) ?>

                                </span>

                            </td>


                            <!-- CUSTOMER -->

                            <td>

                                <div class="customer-name">

                                    <?= e(
                                        $booking[
                                            'display_customer_name'
                                        ]
                                    ) ?>

                                </div>


                                <div class="customer-contact">

                                    <?php if (
                                        $booking[
                                            'display_customer_phone'
                                        ] !== ''
                                    ): ?>

                                        <?= e(
                                            $booking[
                                                'display_customer_phone'
                                            ]
                                        ) ?>

                                    <?php endif; ?>


                                    <?php if (
                                        $booking[
                                            'display_customer_email'
                                        ] !== ''
                                    ): ?>

                                        <br>

                                        <?= e(
                                            $booking[
                                                'display_customer_email'
                                            ]
                                        ) ?>

                                    <?php endif; ?>

                                </div>

                            </td>


                            <!-- SERVICE -->

                            <td>

                                <?= e(
                                    $booking[
                                        'service_name'
                                    ]
                                    ?? 'Service'
                                ) ?>

                            </td>


                            <!-- DATE -->

                            <td>

                                <?= e(
                                    $booking[
                                        'booking_date'
                                    ]
                                    ?? '-'
                                ) ?>

                            </td>


                            <!-- TIME -->

                            <td>

                                <?= e(
                                    $displayTime
                                ) ?>

                            </td>


                            <!-- AMOUNT -->

                            <td>

                                <span class="amount">

                                    <?= e(
                                        $currency
                                    ) ?>

                                    <?= number_format(
                                        (float) (
                                            $booking[
                                                'total_amount'
                                            ]
                                            ?? 0
                                        ),
                                        2
                                    ) ?>

                                </span>

                            </td>


                            <!-- BOOKING STATUS -->

                            <td>

                                <span
                                    class="status <?= e(
                                        $statusClass
                                    ) ?>"
                                >

                                    <?= e(
                                        $statusLabel
                                    ) ?>

                                </span>

                            </td>


                            <!-- PAYMENT STATUS -->

                            <td>

                                <span
                                    class="status <?= e(
                                        $paymentClass
                                    ) ?>"
                                >

                                    <?= e(
                                        $paymentLabel
                                    ) ?>

                                </span>

                            </td>


                            <!-- PAYMENT METHOD -->

                            <td>

                                <?= $paymentMethod !== ''
                                    ? e(
                                        strtoupper(
                                            $paymentMethod
                                        )
                                    )
                                    : '-'
                                ?>

                            </td>


                            <!-- TRANSACTION -->

                            <td>

                                <?= $transaction !== ''
                                    ? e($transaction)
                                    : '-'
                                ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>


            </table>


        </div>


    </div>


    <!-- FOOTER -->

    <div class="report-footer">

        <div>

            BookEase Booking Report

        </div>

        <div>

            Generated:
            <?= e($generatedAt) ?>

        </div>

    </div>


</div>


</body>

</html>
