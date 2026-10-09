<?php

/**
 * BookEase - Payment Report
 * File: reports/payment-report.php
 *
 * Uses PDO / Database class.
 * Compatible with the current BookEase structure.
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

if (!isset($_SESSION['user_id'])) {

    header(
        'Location: ' .
        BASE_URL .
        '/auth/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['admin', 'staff'], true)) {

    header(
        'Location: ' .
        BASE_URL .
        '/customer/dashboard.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

try {

    $db = new Database();

    $conn = $db->getConnection();

} catch (Exception $e) {

    die(
        'Database connection failed.'
    );
}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$status =
    trim(
        $_GET['status'] ?? ''
    );

$method =
    trim(
        $_GET['payment_method'] ?? ''
    );

$dateFrom =
    trim(
        $_GET['date_from'] ?? ''
    );

$dateTo =
    trim(
        $_GET['date_to'] ?? ''
    );


/*
|--------------------------------------------------------------------------
| Allowed Filter Values
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'pending',
    'paid',
    'failed',
    'refunded',
    'cancelled'
];

$allowedMethods = [
    'mpesa',
    'cash',
    'card',
    'bank',
    'other'
];


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


if (
    $method !== '' &&
    !in_array(
        $method,
        $allowedMethods,
        true
    )
) {

    $method = '';
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
| Build Payment Query
|--------------------------------------------------------------------------
|
| We use the actual tables instead of payment_summary.
|
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

        p.phone,

        p.status,

        p.paid_at,

        p.created_at,

        b.booking_reference,

        b.booking_date,

        b.booking_time,

        u.name AS customer_name,

        u.email AS customer_email,

        u.phone AS customer_phone

    FROM payments p

    LEFT JOIN bookings b
        ON p.booking_id = b.id

    LEFT JOIN users u
        ON p.user_id = u.id

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
        AND p.status = ?
    ";

    $params[] = $status;
}


/*
|--------------------------------------------------------------------------
| Payment Method Filter
|--------------------------------------------------------------------------
*/

if ($method !== '') {

    $sql .= "
        AND p.payment_method = ?
    ";

    $params[] = $method;
}


/*
|--------------------------------------------------------------------------
| From Date
|--------------------------------------------------------------------------
*/

if ($dateFrom !== '') {

    $sql .= "
        AND DATE(p.created_at) >= ?
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
        AND DATE(p.created_at) <= ?
    ";

    $params[] = $dateTo;
}


/*
|--------------------------------------------------------------------------
| Ordering
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        p.created_at DESC,
        p.id DESC
";


/*
|--------------------------------------------------------------------------
| Execute Query
|--------------------------------------------------------------------------
*/

try {

    $stmt =
        $conn->prepare($sql);

    $stmt->execute($params);

    $payments =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

    $payments = [];

    $databaseError =
        'Unable to load payment records.';

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        $databaseError =
            'Database error: ' .
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| Calculate Summary
|--------------------------------------------------------------------------
*/

$totalAmount = 0;
$paidAmount = 0;
$pendingAmount = 0;
$failedAmount = 0;
$refundedAmount = 0;
$cancelledAmount = 0;


foreach (
    $payments
    as $payment
) {

    $amount =
        (float)(
            $payment['amount']
            ?? 0
        );

    $totalAmount += $amount;


    switch (
        strtolower(
            $payment['status'] ?? ''
        )
    ) {

        case 'paid':

            $paidAmount += $amount;

            break;


        case 'pending':

            $pendingAmount += $amount;

            break;


        case 'failed':

            $failedAmount += $amount;

            break;


        case 'refunded':

            $refundedAmount += $amount;

            break;


        case 'cancelled':

            $cancelledAmount += $amount;

            break;
    }
}


/*
|--------------------------------------------------------------------------
| Safe Escape Helper
|--------------------------------------------------------------------------
*/

function paymentReportEscape($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
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
        Payment Report |
        <?= paymentReportEscape(
            defined('APP_NAME')
                ? APP_NAME
                : 'BookEase'
        ) ?>
    </title>


    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>/assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="<?= BASE_URL ?>/assets/css/dashboard.css"
    >


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            background: #f1f5f9;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            color: #0f172a;
        }


        .report-container {

            padding: 25px;

            margin-left: 240px;

            max-width: 100%;
        }


        .report-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

            margin-bottom: 25px;
        }


        .report-header h1 {

            margin: 0 0 7px;

            font-size: 28px;
        }


        .report-header p {

            margin: 0;

            color: #64748b;
        }


        .filter-card,
        .table-card,
        .summary-card {

            background: #ffffff;

            border-radius: 12px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, .06);
        }


        .filter-card {

            padding: 20px;

            margin-bottom: 25px;
        }


        .filter-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(180px, 1fr)
                );

            gap: 15px;

            align-items: end;
        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            font-weight: 600;

            color: #334155;
        }


        .form-control {

            width: 100%;

            padding: 11px 12px;

            border:
                1px solid #cbd5e1;

            border-radius: 7px;

            background: #ffffff;

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


        .btn {

            border: none;

            padding: 11px 18px;

            border-radius: 7px;

            cursor: pointer;

            text-decoration: none;

            display: inline-block;

            font-size: 14px;

            font-weight: 600;
        }


        .btn-primary {

            background: #2563eb;

            color: #ffffff;
        }


        .btn-primary:hover {

            background: #1d4ed8;
        }


        .btn-secondary {

            background: #64748b;

            color: #ffffff;

            margin-left: 5px;
        }


        .btn-secondary:hover {

            background: #475569;
        }


        .summary-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(180px, 1fr)
                );

            gap: 15px;

            margin-bottom: 25px;
        }


        .summary-card {

            padding: 20px;
        }


        .summary-card h3 {

            font-size: 14px;

            color: #64748b;

            margin:
                0 0 8px;
        }


        .summary-card strong {

            font-size: 23px;

            color: #0f172a;
        }


        .table-card {

            padding: 20px;

            overflow-x: auto;
        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 950px;
        }


        th,
        td {

            padding: 12px;

            border-bottom:
                1px solid #e2e8f0;

            text-align: left;

            white-space: nowrap;
        }


        th {

            background: #f8fafc;

            color: #334155;

            font-size: 13px;
        }


        td {

            font-size: 14px;
        }


        tbody tr:hover {

            background: #f8fafc;
        }


        .status {

            display: inline-block;

            padding: 5px 9px;

            border-radius: 20px;

            font-size: 12px;

            text-transform: capitalize;

            font-weight: 600;
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

            background: #e0e7ff;

            color: #3730a3;
        }


        .status-cancelled {

            background: #e5e7eb;

            color: #374151;
        }


        .error-box {

            background: #fee2e2;

            color: #991b1b;

            padding: 14px;

            border-radius: 8px;

            margin-bottom: 20px;
        }


        .empty {

            text-align: center;

            color: #64748b;

            padding: 30px;
        }


        @media (max-width: 900px) {

            .report-container {

                margin-left: 70px;

                padding: 18px;
            }

        }


        @media (max-width: 600px) {

            .report-container {

                margin-left: 0;

                padding: 15px;
            }


            .report-header {

                align-items: flex-start;

                flex-direction: column;
            }


            .btn-secondary {

                margin-left: 0;

                margin-top: 5px;
            }

        }


        @media print {

            .no-print {

                display: none !important;
            }


            body {

                background: #ffffff;
            }


            .report-container {

                margin-left: 0;

                padding: 0;
            }


            .table-card {

                box-shadow: none;

                padding: 0;
            }

        }

    </style>

</head>


<body>


<?php

$navbarPath =
    __DIR__ .
    '/../includes/navbar.php';

if (
    file_exists($navbarPath)
) {

    include $navbarPath;
}

?>


<div class="report-container">


    <!-- HEADER -->

    <div class="report-header">

        <div>

            <h1>
                Payment Report
            </h1>

            <p>
                Track payments and transaction status.
            </p>

        </div>


        <div class="no-print">

            <button
                type="button"
                onclick="window.print()"
                class="btn btn-primary"
            >
                Print Report
            </button>

        </div>

    </div>


    <!-- DATABASE ERROR -->

    <?php if (
        isset($databaseError)
    ): ?>

        <div class="error-box">

            <?= paymentReportEscape(
                $databaseError
            ) ?>

        </div>

    <?php endif; ?>


    <!-- FILTER -->

    <div class="filter-card no-print">

        <form method="GET">

            <div class="filter-grid">


                <div class="form-group">

                    <label for="status">
                        Payment Status
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
                                value="<?= paymentReportEscape($item) ?>"
                                <?= (
                                    $status === $item
                                )
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= paymentReportEscape(
                                    ucfirst($item)
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="payment_method">
                        Payment Method
                    </label>

                    <select
                        id="payment_method"
                        name="payment_method"
                        class="form-control"
                    >

                        <option value="">
                            All Methods
                        </option>

                        <?php foreach (
                            $allowedMethods
                            as $item
                        ): ?>

                            <option
                                value="<?= paymentReportEscape($item) ?>"
                                <?= (
                                    $method === $item
                                )
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= paymentReportEscape(
                                    strtoupper($item)
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="date_from">
                        From Date
                    </label>

                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        class="form-control"
                        value="<?= paymentReportEscape($dateFrom) ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="date_to">
                        To Date
                    </label>

                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        class="form-control"
                        value="<?= paymentReportEscape($dateTo) ?>"
                    >

                </div>


                <div>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Filter
                    </button>


                    <a
                        href="<?= BASE_URL ?>/reports/payment-report.php"
                        class="btn btn-secondary"
                    >
                        Reset
                    </a>

                </div>

            </div>

        </form>

    </div>


    <!-- SUMMARY -->

    <div class="summary-grid">


        <div class="summary-card">

            <h3>
                Total Transactions
            </h3>

            <strong>
                <?= number_format(
                    count($payments)
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Total Amount
            </h3>

            <strong>
                KSh
                <?= number_format(
                    $totalAmount,
                    2
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Paid
            </h3>

            <strong>
                KSh
                <?= number_format(
                    $paidAmount,
                    2
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Pending
            </h3>

            <strong>
                KSh
                <?= number_format(
                    $pendingAmount,
                    2
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Failed
            </h3>

            <strong>
                KSh
                <?= number_format(
                    $failedAmount,
                    2
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Refunded
            </h3>

            <strong>
                KSh
                <?= number_format(
                    $refundedAmount,
                    2
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Cancelled
            </h3>

            <strong>
                KSh
                <?= number_format(
                    $cancelledAmount,
                    2
                ) ?>
            </strong>

        </div>


    </div>


    <!-- PAYMENT TABLE -->

    <div class="table-card">

        <table>

            <thead>

                <tr>

                    <th>#</th>

                    <th>Booking</th>

                    <th>Customer</th>

                    <th>Transaction</th>

                    <th>Method</th>

                    <th>Phone</th>

                    <th>Amount</th>

                    <th>Status</th>

                    <th>Paid At</th>

                    <th>Created</th>

                </tr>

            </thead>


            <tbody>


            <?php if (
                empty($payments)
            ): ?>

                <tr>

                    <td
                        colspan="10"
                        class="empty"
                    >
                        No payments found.
                    </td>

                </tr>

            <?php else: ?>


                <?php foreach (
                    $payments
                    as $index => $payment
                ): ?>

                    <?php

                    $paymentStatus =
                        strtolower(
                            $payment['status']
                            ?? 'pending'
                        );

                    ?>


                    <tr>


                        <td>
                            <?= $index + 1 ?>
                        </td>


                        <td>

                            <strong>

                                <?= paymentReportEscape(
                                    $payment['booking_reference']
                                    ?? '#'
                                    . ($payment['booking_id'] ?? '')
                                ) ?>

                            </strong>

                            <?php if (
                                !empty(
                                    $payment['booking_date']
                                )
                            ): ?>

                                <br>

                                <small>

                                    <?= paymentReportEscape(
                                        $payment['booking_date']
                                    ) ?>

                                </small>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= paymentReportEscape(
                                $payment['customer_name']
                                ?? 'Unknown'
                            ) ?>

                            <?php if (
                                !empty(
                                    $payment['customer_email']
                                )
                            ): ?>

                                <br>

                                <small>

                                    <?= paymentReportEscape(
                                        $payment['customer_email']
                                    ) ?>

                                </small>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= paymentReportEscape(
                                $payment['transaction_reference']
                                ?? '-'
                            ) ?>

                            <?php if (
                                !empty(
                                    $payment['transaction_id']
                                )
                            ): ?>

                                <br>

                                <small>
                                    ID:
                                    <?= paymentReportEscape(
                                        $payment['transaction_id']
                                    ) ?>
                                </small>

                            <?php endif; ?>

                        </td>


                        <td>

                            <?= paymentReportEscape(
                                strtoupper(
                                    $payment['payment_method']
                                    ?? '-'
                                )
                            ) ?>

                        </td>


                        <td>

                            <?= paymentReportEscape(
                                $payment['phone']
                                ?? $payment['customer_phone']
                                ?? '-'
                            ) ?>

                        </td>


                        <td>

                            <strong>

                                KSh
                                <?= number_format(
                                    (float)(
                                        $payment['amount']
                                        ?? 0
                                    ),
                                    2
                                ) ?>

                            </strong>

                        </td>


                        <td>

                            <span
                                class="
                                    status
                                    status-<?= paymentReportEscape(
                                        $paymentStatus
                                    ) ?>
                                "
                            >

                                <?= paymentReportEscape(
                                    ucfirst(
                                        $paymentStatus
                                    )
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= paymentReportEscape(
                                $payment['paid_at']
                                ?? '-'
                            ) ?>

                        </td>


                        <td>

                            <?= paymentReportEscape(
                                $payment['created_at']
                                ?? '-'
                            ) ?>

                        </td>


                    </tr>

                <?php endforeach; ?>


            <?php endif; ?>


            </tbody>

        </table>

    </div>


</div>


</body>

</html>