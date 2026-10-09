<?php

/**
 * ============================================================
 * BookEase - Customer Report
 * File:
 * reports/customer-report.php
 * ============================================================
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
| Admin / Staff Access
|--------------------------------------------------------------------------
*/

$role = $_SESSION['role'] ?? 'customer';

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

} catch (PDOException $e) {

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        die(
            'Database error: ' .
            htmlspecialchars($e->getMessage())
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

$search =
    trim(
        $_GET['search'] ?? ''
    );

$status =
    trim(
        $_GET['status'] ?? ''
    );


/*
|--------------------------------------------------------------------------
| Build Customer Query
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| Bookings are linked using:
|     bookings.customer_id
|
| Payments in your current payment process are linked using:
|     payments.user_id
|
| We therefore calculate each separately using subqueries.
|
| This prevents booking totals and payment totals from being
| multiplied when a customer has multiple bookings/payments.
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        u.id,
        u.name,
        u.email,
        u.phone,
        u.city,
        u.status,
        u.created_at,

        /* Total bookings */
        (
            SELECT COUNT(*)
            FROM bookings b
            WHERE b.customer_id = u.id
        ) AS total_bookings,

        /* Total booking value excluding cancelled bookings */
        (
            SELECT COALESCE(
                SUM(
                    CASE
                        WHEN b.status <> 'cancelled'
                        THEN b.total_amount
                        ELSE 0
                    END
                ),
                0
            )
            FROM bookings b
            WHERE b.customer_id = u.id
        ) AS total_booking_value,

        /* Number of payment records */
        (
            SELECT COUNT(*)
            FROM payments p
            WHERE p.user_id = u.id
        ) AS total_payments,

        /* Total amount successfully paid */
        (
            SELECT COALESCE(
                SUM(
                    CASE
                        WHEN p.status = 'paid'
                        THEN p.amount
                        ELSE 0
                    END
                ),
                0
            )
            FROM payments p
            WHERE p.user_id = u.id
        ) AS total_paid

    FROM users u

    WHERE u.role = 'customer'
";


$params = [];


/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            u.name LIKE ?
            OR u.email LIKE ?
            OR u.phone LIKE ?
        )
    ";

    $searchValue =
        '%' .
        $search .
        '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'active',
    'inactive',
    'suspended'
];

if (
    $status !== '' &&
    in_array(
        $status,
        $allowedStatuses,
        true
    )
) {

    $sql .= "
        AND u.status = ?
    ";

    $params[] = $status;

}


/*
|--------------------------------------------------------------------------
| Ordering
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        u.created_at DESC
";


/*
|--------------------------------------------------------------------------
| Execute Customer Query
|--------------------------------------------------------------------------
*/

try {

    $stmt =
        $conn->prepare($sql);

    $stmt->execute($params);

    $customers =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

    if (
        defined('DEVELOPMENT_MODE') &&
        DEVELOPMENT_MODE === true
    ) {

        die(
            'Report query error: ' .
            htmlspecialchars(
                $e->getMessage()
            )
        );

    }

    die(
        'Unable to load customer report.'
    );
}


/*
|--------------------------------------------------------------------------
| Summary Statistics
|--------------------------------------------------------------------------
*/

$totalCustomers =
    count($customers);

$activeCustomers = 0;

$totalBookings = 0;

$totalBookingValue = 0;

$totalPaid = 0;

$totalPayments = 0;


foreach ($customers as $customer) {

    if (
        strtolower(
            $customer['status'] ?? ''
        ) === 'active'
    ) {

        $activeCustomers++;
    }


    $totalBookings +=
        (int) (
            $customer['total_bookings']
            ?? 0
        );


    $totalBookingValue +=
        (float) (
            $customer['total_booking_value']
            ?? 0
        );


    $totalPayments +=
        (int) (
            $customer['total_payments']
            ?? 0
        );


    $totalPaid +=
        (float) (
            $customer['total_paid']
            ?? 0
        );
}


/*
|--------------------------------------------------------------------------
| Page Title
|--------------------------------------------------------------------------
*/

$pageTitle = 'Customer Report';

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

            min-height: calc(100vh - 64px);
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


        .filter-card {

            background: #ffffff;

            padding: 22px;

            border-radius: 12px;

            margin-bottom: 25px;

            box-shadow:
                0 4px 18px
                rgba(0, 0, 0, .05);
        }


        .filter-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(200px, 1fr)
                );

            gap: 16px;

            align-items: end;
        }


        .form-group {

            margin: 0;
        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            font-size: 14px;

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

            gap: 16px;

            margin-bottom: 25px;
        }


        .summary-card {

            background: #ffffff;

            padding: 20px;

            border-radius: 12px;

            box-shadow:
                0 4px 18px
                rgba(0, 0, 0, .05);
        }


        .summary-card h3 {

            margin: 0 0 8px;

            font-size: 13px;

            color: #64748b;

            text-transform: uppercase;

            letter-spacing: .4px;
        }


        .summary-card strong {

            display: block;

            font-size: 25px;

            color: #0f172a;
        }


        .table-card {

            background: #ffffff;

            padding: 20px;

            border-radius: 12px;

            box-shadow:
                0 4px 18px
                rgba(0, 0, 0, .05);

            overflow-x: auto;
        }


        table {

            width: 100%;

            border-collapse: collapse;
        }


        th,
        td {

            padding: 13px;

            border-bottom:
                1px solid #e2e8f0;

            text-align: left;

            white-space: nowrap;

            font-size: 14px;
        }


        th {

            background: #f8fafc;

            color: #334155;

            font-size: 13px;
        }


        tbody tr:hover {

            background: #f8fafc;
        }


        .status {

            display: inline-block;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 600;

            text-transform: capitalize;
        }


        .status-active {

            background: #dcfce7;

            color: #166534;
        }


        .status-inactive {

            background: #e2e8f0;

            color: #475569;
        }


        .status-suspended {

            background: #fee2e2;

            color: #991b1b;
        }


        .status-unknown {

            background: #f1f5f9;

            color: #475569;
        }


        .customer-name {

            font-weight: 600;

            color: #0f172a;
        }


        .contact-email {

            color: #475569;

            font-size: 13px;
        }


        .contact-phone {

            color: #64748b;

            font-size: 12px;

            margin-top: 3px;
        }


        .empty {

            padding: 35px;

            text-align: center;

            color: #64748b;
        }


        @media (max-width: 900px) {

            .report-container {

                margin-left: 70px;

                padding: 20px;
            }

        }


        @media (max-width: 650px) {

            .report-container {

                margin-left: 0;

                padding: 15px;
            }


            .report-header {

                flex-direction: column;

                align-items: flex-start;
            }


            .filter-grid {

                grid-template-columns: 1fr;
            }

        }


        @media print {

            .filter-card,
            .no-print {

                display: none !important;
            }


            .report-container {

                margin: 0;

                padding: 0;
            }


            .table-card,
            .summary-card {

                box-shadow: none;
            }

        }

    </style>

</head>


<body>


<?php

$navbarFile =
    __DIR__ .
    '/../includes/navbar.php';

if (file_exists($navbarFile)) {

    require_once $navbarFile;
}

?>


<div class="report-container">


    <!-- HEADER -->

    <div class="report-header">

        <div>

            <h1>
                Customer Report
            </h1>

            <p>
                Customer registration, bookings and payment activity.
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


    <!-- FILTER -->

    <div class="filter-card no-print">

        <form method="GET">

            <div class="filter-grid">


                <!-- SEARCH -->

                <div class="form-group">

                    <label for="search">

                        Search Customer

                    </label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        class="form-control"
                        placeholder="Name, email or phone"
                        value="<?= e($search) ?>"
                    >

                </div>


                <!-- STATUS -->

                <div class="form-group">

                    <label for="status">

                        Status

                    </label>

                    <select
                        name="status"
                        id="status"
                        class="form-control"
                    >

                        <option value="">

                            All Customers

                        </option>


                        <?php

                        $statuses = [
                            'active',
                            'inactive',
                            'suspended'
                        ];

                        ?>


                        <?php foreach (
                            $statuses
                            as $item
                        ): ?>

                            <option
                                value="<?= e($item) ?>"
                                <?= (
                                    $status === $item
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    ucfirst($item)
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- BUTTONS -->

                <div>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        Search

                    </button>


                    <a
                        href="<?= e(
                            BASE_URL .
                            '/reports/customer-report.php'
                        ) ?>"
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
                Total Customers
            </h3>

            <strong>
                <?= number_format(
                    $totalCustomers
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Active Customers
            </h3>

            <strong>
                <?= number_format(
                    $activeCustomers
                ) ?>
            </strong>

        </div>


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
                Booking Value
            </h3>

            <strong>

                KSh
                <?= number_format(
                    $totalBookingValue,
                    2
                ) ?>

            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Total Payments
            </h3>

            <strong>
                <?= number_format(
                    $totalPayments
                ) ?>
            </strong>

        </div>


        <div class="summary-card">

            <h3>
                Total Paid
            </h3>

            <strong>

                KSh
                <?= number_format(
                    $totalPaid,
                    2
                ) ?>

            </strong>

        </div>


    </div>


    <!-- CUSTOMER TABLE -->

    <div class="table-card">

        <table>

            <thead>

                <tr>

                    <th>#</th>

                    <th>
                        Customer
                    </th>

                    <th>
                        Contact
                    </th>

                    <th>
                        City
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Bookings
                    </th>

                    <th>
                        Booking Value
                    </th>

                    <th>
                        Payments
                    </th>

                    <th>
                        Paid
                    </th>

                    <th>
                        Registered
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if (empty($customers)): ?>

                <tr>

                    <td
                        colspan="10"
                        class="empty"
                    >

                        No customers found.

                    </td>

                </tr>


            <?php else: ?>


                <?php foreach (
                    $customers
                    as $index => $customer
                ): ?>


                    <?php

                    $customerStatus =
                        strtolower(
                            trim(
                                $customer['status']
                                ?? ''
                            )
                        );

                    $statusClass =
                        in_array(
                            $customerStatus,
                            [
                                'active',
                                'inactive',
                                'suspended'
                            ],
                            true
                        )
                            ? $customerStatus
                            : 'unknown';

                    ?>


                    <tr>


                        <!-- NUMBER -->

                        <td>

                            <?= $index + 1 ?>

                        </td>


                        <!-- CUSTOMER -->

                        <td>

                            <div
                                class="customer-name"
                            >

                                <?= e(
                                    $customer['name']
                                    ?? 'Unknown'
                                ) ?>

                            </div>

                        </td>


                        <!-- CONTACT -->

                        <td>

                            <div
                                class="contact-email"
                            >

                                <?= e(
                                    $customer['email']
                                    ?? '-'
                                ) ?>

                            </div>


                            <div
                                class="contact-phone"
                            >

                                <?= e(
                                    $customer['phone']
                                    ?? '-'
                                ) ?>

                            </div>

                        </td>


                        <!-- CITY -->

                        <td>

                            <?= e(
                                $customer['city']
                                ?? '-'
                            ) ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <span
                                class="
                                    status
                                    status-<?= e(
                                        $statusClass
                                    )
                                ?>"
                            >

                                <?= e(
                                    $customerStatus !== ''
                                        ? ucfirst(
                                            $customerStatus
                                        )
                                        : 'Unknown'
                                ) ?>

                            </span>

                        </td>


                        <!-- BOOKINGS -->

                        <td>

                            <?= number_format(
                                (int) (
                                    $customer[
                                        'total_bookings'
                                    ] ?? 0
                                )
                            ) ?>

                        </td>


                        <!-- BOOKING VALUE -->

                        <td>

                            KSh

                            <?= number_format(
                                (float) (
                                    $customer[
                                        'total_booking_value'
                                    ] ?? 0
                                ),
                                2
                            ) ?>

                        </td>


                        <!-- PAYMENTS -->

                        <td>

                            <?= number_format(
                                (int) (
                                    $customer[
                                        'total_payments'
                                    ] ?? 0
                                )
                            ) ?>

                        </td>


                        <!-- PAID -->

                        <td>

                            KSh

                            <?= number_format(
                                (float) (
                                    $customer[
                                        'total_paid'
                                    ] ?? 0
                                ),
                                2
                            ) ?>

                        </td>


                        <!-- REGISTERED -->

                        <td>

                            <?= e(
                                $customer['created_at']
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