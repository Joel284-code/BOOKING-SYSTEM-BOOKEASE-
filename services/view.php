<?php
/**
 * View Service
 * File: services/view.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
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

    die('Database connection failed.');
}


/*
|--------------------------------------------------------------------------
| Service ID
|--------------------------------------------------------------------------
*/

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get Service
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            description,
            price,
            duration,
            status,
            created_at,
            updated_at
        FROM services
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $service = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die('Unable to load service.');
}


if (!$service) {
    header('Location: index.php?error=not_found');
    exit;
}


/*
|--------------------------------------------------------------------------
| Booking Statistics
|--------------------------------------------------------------------------
*/

$stats = [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'cancelled' => 0
];

try {

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total,

            SUM(
                CASE
                    WHEN status = 'pending'
                    THEN 1
                    ELSE 0
                END
            ) AS pending,

            SUM(
                CASE
                    WHEN status = 'confirmed'
                    THEN 1
                    ELSE 0
                END
            ) AS confirmed,

            SUM(
                CASE
                    WHEN status = 'completed'
                    THEN 1
                    ELSE 0
                END
            ) AS completed,

            SUM(
                CASE
                    WHEN status = 'cancelled'
                    THEN 1
                    ELSE 0
                END
            ) AS cancelled

        FROM bookings
        WHERE service_id = ?
    ");

    $stmt->execute([$id]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {

        $stats['total'] =
            (int) ($result['total'] ?? 0);

        $stats['pending'] =
            (int) ($result['pending'] ?? 0);

        $stats['confirmed'] =
            (int) ($result['confirmed'] ?? 0);

        $stats['completed'] =
            (int) ($result['completed'] ?? 0);

        $stats['cancelled'] =
            (int) ($result['cancelled'] ?? 0);
    }

} catch (PDOException $e) {

    // Keep the service page working even if statistics fail.
}


/*
|--------------------------------------------------------------------------
| User Role
|--------------------------------------------------------------------------
*/

$userRole = $_SESSION['role'] ?? '';

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
        <?= htmlspecialchars($service['name']) ?> | BookEase
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/dashboard.css"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #1e293b;
        }

        .navbar {
            background: #0f172a;
            color: white;
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 23px;
            font-weight: bold;
        }

        .logo span {
            color: #3b82f6;
        }

        .nav-links {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 6px;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,.1);
        }

        .container {
            width: 92%;
            max-width: 950px;
            margin: 40px auto;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        .card {
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,.06);
        }

        .service-header {
            display: flex;
            justify-content: space-between;
            gap: 30px;
            padding-bottom: 25px;
            border-bottom: 1px solid #e2e8f0;
        }

        .service-header h1 {
            margin: 0 0 12px;
            font-size: 30px;
        }

        .description {
            color: #64748b;
            line-height: 1.7;
        }

        .price {
            color: #2563eb;
            font-size: 28px;
            font-weight: bold;
            white-space: nowrap;
        }

        .details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 30px;
        }

        .detail {
            background: #f8fafc;
            padding: 20px;
            border-radius: 9px;
        }

        .detail small {
            display: block;
            color: #64748b;
            margin-bottom: 8px;
        }

        .detail strong {
            font-size: 18px;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .statistics {
            margin-top: 35px;
        }

        .statistics h2 {
            margin-bottom: 15px;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
        }

        .stat {
            padding: 18px;
            background: #f8fafc;
            border-radius: 8px;
            text-align: center;
        }

        .stat strong {
            display: block;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .stat span {
            color: #64748b;
            font-size: 13px;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 35px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 12px 20px;
            border-radius: 7px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #0f172a;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        @media (max-width: 800px) {

            .service-header {
                flex-direction: column;
            }

            .details {
                grid-template-columns: 1fr;
            }

            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .navbar {
                padding: 15px 20px;
            }

            .nav-links {
                display: none;
            }

        }

        @media (max-width: 500px) {

            .container {
                width: 95%;
                margin: 25px auto;
            }

            .card {
                padding: 22px;
            }

            .stat-grid {
                grid-template-columns: 1fr;
            }

            .service-header h1 {
                font-size: 25px;
            }

            .price {
                font-size: 24px;
            }

        }

    </style>

</head>

<body>


<!-- NAVBAR -->

<div class="navbar">

    <div class="logo">
        Book<span>Ease</span>
    </div>

    <div class="nav-links">

        <?php if ($userRole === 'admin'): ?>

            <a href="../admin/dashboard.php">
                Dashboard
            </a>

        <?php elseif ($userRole === 'staff'): ?>

            <a href="../admin/dashboard.php">
                Dashboard
            </a>

        <?php elseif ($userRole === 'customer'): ?>

            <a href="../customer/dashboard.php">
                Dashboard
            </a>

        <?php endif; ?>

        <a href="index.php">
            Services
        </a>

    </div>

</div>


<div class="container">


    <a
        href="index.php"
        class="back"
    >
        ← Back to Services
    </a>


    <div class="card">


        <!-- SERVICE HEADER -->

        <div class="service-header">

            <div>

                <h1>
                    <?= htmlspecialchars(
                        $service['name']
                    ) ?>
                </h1>

                <div class="description">

                    <?php if (!empty($service['description'])): ?>

                        <?= nl2br(
                            htmlspecialchars(
                                $service['description']
                            )
                        ) ?>

                    <?php else: ?>

                        No description available.

                    <?php endif; ?>

                </div>

            </div>


            <div class="price">

                KSh
                <?= number_format(
                    (float) $service['price'],
                    2
                ) ?>

            </div>

        </div>


        <!-- SERVICE DETAILS -->

        <div class="details">


            <div class="detail">

                <small>
                    Duration
                </small>

                <strong>
                    <?= htmlspecialchars(
                        $service['duration']
                    ) ?>
                </strong>

            </div>


            <div class="detail">

                <small>
                    Status
                </small>

                <?php if (
                    $service['status'] === 'active'
                ): ?>

                    <span class="status status-active">
                        Active
                    </span>

                <?php else: ?>

                    <span class="status status-inactive">
                        Inactive
                    </span>

                <?php endif; ?>

            </div>


            <div class="detail">

                <small>
                    Created
                </small>

                <strong>

                    <?php
                    if (!empty($service['created_at'])) {

                        echo htmlspecialchars(
                            date(
                                'd M Y',
                                strtotime(
                                    $service['created_at']
                                )
                            )
                        );

                    } else {

                        echo '-';

                    }
                    ?>

                </strong>

            </div>

        </div>


        <!-- STATISTICS -->

        <div class="statistics">

            <h2>
                Booking Statistics
            </h2>

            <div class="stat-grid">


                <div class="stat">

                    <strong>
                        <?= number_format(
                            $stats['total']
                        ) ?>
                    </strong>

                    <span>
                        Total
                    </span>

                </div>


                <div class="stat">

                    <strong>
                        <?= number_format(
                            $stats['pending']
                        ) ?>
                    </strong>

                    <span>
                        Pending
                    </span>

                </div>


                <div class="stat">

                    <strong>
                        <?= number_format(
                            $stats['confirmed']
                        ) ?>
                    </strong>

                    <span>
                        Confirmed
                    </span>

                </div>


                <div class="stat">

                    <strong>
                        <?= number_format(
                            $stats['completed']
                        ) ?>
                    </strong>

                    <span>
                        Completed
                    </span>

                </div>


                <div class="stat">

                    <strong>
                        <?= number_format(
                            $stats['cancelled']
                        ) ?>
                    </strong>

                    <span>
                        Cancelled
                    </span>

                </div>

            </div>

        </div>


        <!-- ACTIONS -->

        <div class="actions">


            <?php if ($userRole === 'admin'): ?>

                <a
                    href="edit.php?id=<?= (int) $service['id'] ?>"
                    class="btn btn-warning"
                >
                    Edit Service
                </a>

            <?php endif; ?>


            <?php if (
                $userRole === 'customer' &&
                $service['status'] === 'active'
            ): ?>

                <a
                    href="../bookings/create.php?service_id=<?= (int) $service['id'] ?>"
                    class="btn btn-primary"
                >
                    Book This Service
                </a>

            <?php endif; ?>


            <a
                href="index.php"
                class="btn btn-secondary"
            >
                Back to Services
            </a>

        </div>


    </div>

</div>

</body>

</html>