<?php

/*
|--------------------------------------------------------------------------
| BookEase - Admin Services
|--------------------------------------------------------------------------
| Location:
| C:\xampp\htdocs\booking-system\admin\services.php
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';


/*
|--------------------------------------------------------------------------
| START SESSION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {

    header(
        'Location: ' .
        BASE_URL .
        '/auth/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| ADMIN / STAFF ACCESS
|--------------------------------------------------------------------------
*/

$role = strtolower(
    trim(
        $_SESSION['role'] ?? ''
    )
);

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
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

try {

    $database = new Database();

    $conn = $database->getConnection();

    if (!$conn instanceof PDO) {
        throw new RuntimeException(
            'Invalid database connection.'
        );
    }

    $conn->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

} catch (Throwable $e) {

    die(
        'Database connection error. Please check your database configuration.'
    );
}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$message = '';
$error = '';

$editService = null;


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] =
        bin2hex(
            random_bytes(32)
        );
}

$csrfToken =
    $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| HELPER - REDIRECT
|--------------------------------------------------------------------------
*/

function servicesRedirect($message = '', $error = '')
{
    $url =
        BASE_URL .
        '/admin/services.php';

    $params = [];

    if ($message !== '') {
        $params['message'] = $message;
    }

    if ($error !== '') {
        $params['error'] = $error;
    }

    if (!empty($params)) {

        $url .= '?' .
            http_build_query($params);
    }

    header(
        'Location: ' .
        $url
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| READ FLASH MESSAGE
|--------------------------------------------------------------------------
*/

if (isset($_GET['message'])) {

    $message =
        trim(
            (string) $_GET['message']
        );
}

if (isset($_GET['error'])) {

    $error =
        trim(
            (string) $_GET['error']
        );
}


/*
|--------------------------------------------------------------------------
| DELETE SERVICE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'delete'
) {

    $postedToken =
        $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $csrfToken,
            $postedToken
        )
    ) {

        servicesRedirect(
            '',
            'Invalid security token. Please try again.'
        );
    }


    $serviceId =
        (int) (
            $_POST['service_id'] ?? 0
        );


    if ($serviceId <= 0) {

        servicesRedirect(
            '',
            'Invalid service ID.'
        );
    }


    try {

        /*
        |----------------------------------------------------------------------
        | Check service exists
        |----------------------------------------------------------------------
        */

        $stmt =
            $conn->prepare("
                SELECT
                    id,
                    name
                FROM services
                WHERE id = ?
                LIMIT 1
            ");

        $stmt->execute([
            $serviceId
        ]);

        $service =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$service) {

            servicesRedirect(
                '',
                'Service not found.'
            );
        }


        /*
        |----------------------------------------------------------------------
        | Check existing bookings
        |----------------------------------------------------------------------
        */

        $stmt =
            $conn->prepare("
                SELECT COUNT(*)
                FROM bookings
                WHERE service_id = ?
            ");

        $stmt->execute([
            $serviceId
        ]);

        $bookingCount =
            (int) $stmt->fetchColumn();


        if ($bookingCount > 0) {

            servicesRedirect(
                '',
                'This service cannot be deleted because it is already used by existing bookings. Set it to inactive instead.'
            );
        }


        /*
        |----------------------------------------------------------------------
        | Delete
        |----------------------------------------------------------------------
        */

        $stmt =
            $conn->prepare("
                DELETE FROM services
                WHERE id = ?
            ");

        $stmt->execute([
            $serviceId
        ]);


        servicesRedirect(
            'Service deleted successfully.'
        );


    } catch (Throwable $e) {

        servicesRedirect(
            '',
            'Unable to delete the service.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| ADD / UPDATE SERVICE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'save'
) {

    $postedToken =
        $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $csrfToken,
            $postedToken
        )
    ) {

        $error =
            'Invalid security token. Please refresh the page and try again.';

    } else {

        $serviceId =
            (int) (
                $_POST['id'] ?? 0
            );

        $name =
            trim(
                (string) (
                    $_POST['name'] ?? ''
                )
            );

        $description =
            trim(
                (string) (
                    $_POST['description'] ?? ''
                )
            );

        $priceInput =
            trim(
                (string) (
                    $_POST['price'] ?? ''
                )
            );

        $durationInput =
            trim(
                (string) (
                    $_POST['duration'] ?? ''
                )
            );

        $status =
            strtolower(
                trim(
                    (string) (
                        $_POST['status'] ?? 'active'
                    )
                )
            );


        /*
        |----------------------------------------------------------------------
        | Validation
        |----------------------------------------------------------------------
        */

        if ($name === '') {

            $error =
                'Service name is required.';

        } elseif (
            strlen($name) > 150
        ) {

            $error =
                'Service name is too long.';

        } elseif (
            $priceInput === '' ||
            !is_numeric($priceInput)
        ) {

            $error =
                'Please enter a valid service price.';

        } elseif (
            (float) $priceInput < 0
        ) {

            $error =
                'Price cannot be negative.';

        } elseif (
            $durationInput === '' ||
            !ctype_digit($durationInput) ||
            (int) $durationInput <= 0
        ) {

            $error =
                'Duration must be a positive number of minutes.';

        } elseif (
            !in_array(
                $status,
                ['active', 'inactive'],
                true
            )
        ) {

            $error =
                'Invalid service status.';

        }


        /*
        |----------------------------------------------------------------------
        | SAVE
        |----------------------------------------------------------------------
        */

        if ($error === '') {

            $price =
                round(
                    (float) $priceInput,
                    2
                );

            $duration =
                (int) $durationInput;


            try {

                /*
                |------------------------------------------------------------------
                | UPDATE
                |------------------------------------------------------------------
                */

                if ($serviceId > 0) {

                    $stmt =
                        $conn->prepare("
                            SELECT id
                            FROM services
                            WHERE id = ?
                            LIMIT 1
                        ");

                    $stmt->execute([
                        $serviceId
                    ]);


                    if (!$stmt->fetch()) {

                        $error =
                            'The selected service was not found.';

                    } else {

                        $stmt =
                            $conn->prepare("
                                UPDATE services

                                SET
                                    name = ?,
                                    description = ?,
                                    price = ?,
                                    duration = ?,
                                    status = ?

                                WHERE id = ?
                            ");

                        $stmt->execute([
                            $name,
                            $description,
                            $price,
                            $duration,
                            $status,
                            $serviceId
                        ]);


                        servicesRedirect(
                            'Service updated successfully.'
                        );
                    }


                /*
                |------------------------------------------------------------------
                | ADD
                |------------------------------------------------------------------
                */

                } else {

                    $stmt =
                        $conn->prepare("
                            INSERT INTO services
                            (
                                name,
                                description,
                                price,
                                duration,
                                status
                            )

                            VALUES
                            (?, ?, ?, ?, ?)
                        ");

                    $stmt->execute([
                        $name,
                        $description,
                        $price,
                        $duration,
                        $status
                    ]);


                    servicesRedirect(
                        'Service added successfully.'
                    );
                }


            } catch (Throwable $e) {

                $error =
                    'Unable to save the service. Please check that the services table contains the required columns.';
            }
        }


        /*
        |----------------------------------------------------------------------
        | Preserve submitted values if validation fails
        |----------------------------------------------------------------------
        */

        if ($error !== '') {

            $editService = [
                'id' =>
                    $serviceId,

                'name' =>
                    $name,

                'description' =>
                    $description,

                'price' =>
                    $priceInput,

                'duration' =>
                    $durationInput,

                'status' =>
                    $status
            ];
        }
    }
}


/*
|--------------------------------------------------------------------------
| EDIT SERVICE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['edit'])
) {

    $editId =
        (int) $_GET['edit'];


    if ($editId > 0) {

        try {

            $stmt =
                $conn->prepare("
                    SELECT
                        id,
                        name,
                        description,
                        price,
                        duration,
                        status
                    FROM services
                    WHERE id = ?
                    LIMIT 1
                ");

            $stmt->execute([
                $editId
            ]);

            $editService =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$editService) {

                $error =
                    'Service not found.';
            }

        } catch (Throwable $e) {

            $error =
                'Unable to load the selected service.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET ALL SERVICES
|--------------------------------------------------------------------------
*/

$services = [];


try {

    $stmt =
        $conn->prepare("
            SELECT
                id,
                name,
                description,
                price,
                duration,
                status,
                created_at

            FROM services

            ORDER BY id DESC
        ");

    $stmt->execute();

    $services =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (Throwable $e) {

    $error =
        'Unable to load services.';
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalServices =
    count($services);

$activeServices = 0;
$inactiveServices = 0;


foreach ($services as $service) {

    if (
        strtolower(
            trim(
                $service['status'] ?? ''
            )
        ) === 'active'
    ) {

        $activeServices++;

    } else {

        $inactiveServices++;
    }
}


$isEditing =
    is_array($editService);


/*
|--------------------------------------------------------------------------
| FORM VALUES
|--------------------------------------------------------------------------
*/

$formId =
    $editService['id']
    ?? 0;

$formName =
    $editService['name']
    ?? '';

$formDescription =
    $editService['description']
    ?? '';

$formPrice =
    $editService['price']
    ?? '0.00';

$formDuration =
    $editService['duration']
    ?? 60;

$formStatus =
    $editService['status']
    ?? 'active';

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
    Services | BookEase Admin
</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Arial,
        sans-serif;

    background: #f1f5f9;

    color: #1e293b;
}

.content {

    max-width: 1250px;

    margin: 0 auto;

    padding: 30px;
}


/* HEADER */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-bottom: 25px;
}

.page-title h1 {

    margin: 0;

    font-size: 28px;
}

.page-title p {

    margin: 7px 0 0;

    color: #64748b;
}


/* NAVIGATION */

.top-links {

    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}

.top-links a {

    padding: 9px 13px;

    background: white;

    border: 1px solid #e2e8f0;

    border-radius: 7px;

    color: #334155;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;
}

.top-links a:hover {

    background: #f8fafc;

    color: #2563eb;
}


/* ALERTS */

.alert {

    padding: 14px 16px;

    border-radius: 8px;

    margin-bottom: 20px;
}

.alert-success {

    background: #dcfce7;

    color: #166534;

    border: 1px solid #bbf7d0;
}

.alert-error {

    background: #fee2e2;

    color: #991b1b;

    border: 1px solid #fecaca;
}


/* STATISTICS */

.stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}

.stat-card {

    background: white;

    padding: 20px;

    border-radius: 12px;

    border: 1px solid #e2e8f0;

    box-shadow:
        0 5px 20px
        rgba(0, 0, 0, .05);
}

.stat-card h4 {

    margin: 0 0 8px;

    color: #64748b;

    font-size: 14px;
}

.stat-number {

    font-size: 27px;

    font-weight: bold;
}

.stat-blue {
    color: #2563eb;
}

.stat-green {
    color: #16a34a;
}

.stat-red {
    color: #dc2626;
}


/* FORM */

.form-card {

    background: white;

    padding: 25px;

    border-radius: 12px;

    border: 1px solid #e2e8f0;

    box-shadow:
        0 5px 20px
        rgba(0, 0, 0, .05);

    margin-bottom: 30px;
}

.form-card h3 {

    margin: 0 0 20px;

    font-size: 20px;
}

.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 18px;
}

.form-group {

    margin-bottom: 5px;
}

.form-group.full {

    grid-column: 1 / -1;
}

.form-group label {

    display: block;

    margin-bottom: 7px;

    font-weight: 600;

    color: #334155;

    font-size: 14px;
}

.form-control {

    width: 100%;

    padding: 12px 13px;

    border:
        1px solid #cbd5e1;

    border-radius: 8px;

    font-size: 14px;

    background: white;

    color: #1e293b;

    outline: none;
}

.form-control:focus {

    border-color: #2563eb;

    box-shadow:
        0 0 0 3px
        rgba(37, 99, 235, .10);
}

textarea.form-control {

    min-height: 110px;

    resize: vertical;
}

.form-actions {

    display: flex;

    gap: 10px;

    margin-top: 20px;

    flex-wrap: wrap;
}


/* BUTTONS */

.btn {

    display: inline-block;

    border: none;

    padding: 10px 15px;

    border-radius: 7px;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;

    cursor: pointer;
}

.btn-primary {

    background: #2563eb;

    color: white;
}

.btn-primary:hover {

    background: #1d4ed8;
}

.btn-secondary {

    background: #e2e8f0;

    color: #334155;
}

.btn-secondary:hover {

    background: #cbd5e1;
}

.btn-danger {

    background: #dc2626;

    color: white;
}

.btn-danger:hover {

    background: #b91c1c;
}

.btn-edit {

    background: #f59e0b;

    color: white;
}

.btn-edit:hover {

    background: #d97706;
}


/* TABLE */

.table-container {

    background: white;

    border-radius: 12px;

    overflow: hidden;

    border: 1px solid #e2e8f0;

    box-shadow:
        0 5px 20px
        rgba(0, 0, 0, .05);
}

.table-header {

    padding: 20px 25px;

    border-bottom:
        1px solid #e2e8f0;
}

.table-header h3 {

    margin: 0;

    font-size: 19px;
}

.table-wrapper {

    width: 100%;

    overflow-x: auto;
}

table {

    width: 100%;

    min-width: 950px;

    border-collapse: collapse;
}

thead {

    background: #f8fafc;
}

th {

    text-align: left;

    padding: 14px 16px;

    color: #475569;

    font-size: 12px;

    text-transform: uppercase;

    border-bottom:
        1px solid #e2e8f0;

    white-space: nowrap;
}

td {

    padding: 14px 16px;

    border-bottom:
        1px solid #e2e8f0;

    vertical-align: middle;
}

tbody tr:hover {

    background: #f8fafc;
}


/* STATUS */

.status {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 600;
}

.status-active {

    background: #dcfce7;

    color: #166534;
}

.status-inactive {

    background: #fee2e2;

    color: #991b1b;
}

.status-default {

    background: #e2e8f0;

    color: #334155;
}


/* ACTIONS */

.actions {

    display: flex;

    gap: 7px;

    flex-wrap: wrap;
}

.actions form {

    display: inline;
}


/* EMPTY */

.empty {

    text-align: center;

    padding: 40px;

    color: #64748b;
}


/* RESPONSIVE */

@media (max-width: 850px) {

    .page-header {

        align-items: flex-start;

        flex-direction: column;
    }

    .stats {

        grid-template-columns:
            1fr;
    }

    .form-grid {

        grid-template-columns:
            1fr;
    }

    .form-group.full {

        grid-column: auto;
    }
}

@media (max-width: 600px) {

    .content {

        padding: 15px;
    }

    .page-title h1 {

        font-size: 24px;
    }

    .form-card {

        padding: 20px;
    }
}

</style>

</head>

<body>

<div class="content">

```
<!-- PAGE HEADER -->

<div class="page-header">

    <div class="page-title">

        <h1>
            Services
        </h1>

        <p>
            Manage the services customers can book.
        </p>

    </div>


    <!-- ADMIN NAVIGATION -->

    <div class="top-links">

        <a
            href="<?= BASE_URL ?>/admin/dashboard.php"
        >
            Dashboard
        </a>

        <a
            href="<?= BASE_URL ?>/admin/bookings.php"
        >
            Bookings
        </a>

        <a
            href="<?= BASE_URL ?>/admin/customers.php"
        >
            Customers
        </a>

        <a
            href="<?= BASE_URL ?>/admin/payments.php"
        >
            Payments
        </a>

        <a
            href="<?= BASE_URL ?>/admin/reports.php"
        >
            Reports
        </a>

    </div>

</div>


<!-- SUCCESS -->

<?php if ($message !== ''): ?>

    <div class="alert alert-success">

        <?= htmlspecialchars(
            $message,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

<?php endif; ?>


<!-- ERROR -->

<?php if ($error !== ''): ?>

    <div class="alert alert-error">

        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

<?php endif; ?>


<!-- STATISTICS -->

<div class="stats">


    <div class="stat-card">

        <h4>
            Total Services
        </h4>

        <div class="stat-number stat-blue">

            <?= number_format(
                $totalServices
            ) ?>

        </div>

    </div>


    <div class="stat-card">

        <h4>
            Active Services
        </h4>

        <div class="stat-number stat-green">

            <?= number_format(
                $activeServices
            ) ?>

        </div>

    </div>


    <div class="stat-card">

        <h4>
            Inactive Services
        </h4>

        <div class="stat-number stat-red">

            <?= number_format(
                $inactiveServices
            ) ?>

        </div>

    </div>


</div>


<!-- ADD / EDIT SERVICE -->

<div class="form-card">

    <h3>

        <?= $isEditing
            ? 'Edit Service'
            : 'Add New Service'
        ?>

    </h3>


    <form
        method="POST"
        action="<?= BASE_URL ?>/admin/services.php"
    >

        <input
            type="hidden"
            name="action"
            value="save"
        >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $csrfToken,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <input
            type="hidden"
            name="id"
            value="<?= (int) $formId ?>"
        >


        <div class="form-grid">


            <!-- NAME -->

            <div class="form-group full">

                <label for="name">
                    Service Name
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $formName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    placeholder="e.g. Consultation"
                    maxlength="150"
                    required
                >

            </div>


            <!-- DESCRIPTION -->

            <div class="form-group full">

                <label for="description">
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    class="form-control"
                    placeholder="Describe the service..."
                ><?= htmlspecialchars(
                    $formDescription,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>

            </div>


            <!-- PRICE -->

            <div class="form-group">

                <label for="price">
                    Price (KSh)
                </label>

                <input
                    type="number"
                    id="price"
                    name="price"
                    class="form-control"
                    min="0"
                    step="0.01"
                    value="<?= htmlspecialchars(
                        (string) $formPrice,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    required
                >

            </div>


            <!-- DURATION -->

            <div class="form-group">

                <label for="duration">
                    Duration (minutes)
                </label>

                <input
                    type="number"
                    id="duration"
                    name="duration"
                    class="form-control"
                    min="1"
                    value="<?= (int) $formDuration ?>"
                    required
                >

            </div>


            <!-- STATUS -->

            <div class="form-group">

                <label for="status">
                    Status
                </label>

                <select
                    id="status"
                    name="status"
                    class="form-control"
                >

                    <option
                        value="active"
                        <?= $formStatus === 'active'
                            ? 'selected'
                            : '' ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= $formStatus === 'inactive'
                            ? 'selected'
                            : '' ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>


        </div>


        <!-- FORM ACTIONS -->

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >

                <?= $isEditing
                    ? 'Update Service'
                    : 'Add Service'
                ?>

            </button>


            <?php if ($isEditing): ?>

                <a
                    href="<?= BASE_URL ?>/admin/services.php"
                    class="btn btn-secondary"
                >
                    Cancel Edit
                </a>

            <?php endif; ?>


        </div>


    </form>

</div>


<!-- SERVICES TABLE -->

<div class="table-container">


    <div class="table-header">

        <h3>
            All Services
        </h3>

    </div>


    <div class="table-wrapper">

        <table>

            <thead>

                <tr>

                    <th>
                        ID
                    </th>

                    <th>
                        Name
                    </th>

                    <th>
                        Description
                    </th>

                    <th>
                        Price
                    </th>

                    <th>
                        Duration
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Created
                    </th>

                    <th>
                        Actions
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if (!empty($services)): ?>


                <?php foreach (
                    $services as $service
                ): ?>

                    <?php

                    $serviceId =
                        (int) (
                            $service['id'] ?? 0
                        );

                    $serviceName =
                        $service['name']
                        ?? '';

                    $serviceDescription =
                        $service['description']
                        ?? '';

                    $servicePrice =
                        (float) (
                            $service['price']
                            ?? 0
                        );

                    $serviceDuration =
                        (int) (
                            $service['duration']
                            ?? 0
                        );

                    $serviceStatus =
                        strtolower(
                            trim(
                                $service['status']
                                ?? 'inactive'
                            )
                        );

                    $statusClass =
                        in_array(
                            $serviceStatus,
                            ['active', 'inactive'],
                            true
                        )
                        ? $serviceStatus
                        : 'default';

                    $createdAt =
                        $service['created_at']
                        ?? '';

                    ?>


                    <tr>


                        <!-- ID -->

                        <td>

                            <strong>
                                #<?= $serviceId ?>
                            </strong>

                        </td>


                        <!-- NAME -->

                        <td>

                            <strong>

                                <?= htmlspecialchars(
                                    $serviceName,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </strong>

                        </td>


                        <!-- DESCRIPTION -->

                        <td>

                            <?= htmlspecialchars(
                                $serviceDescription
                                ?: '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <!-- PRICE -->

                        <td>

                            <strong>

                                KSh
                                <?= number_format(
                                    $servicePrice,
                                    2
                                ) ?>

                            </strong>

                        </td>


                        <!-- DURATION -->

                        <td>

                            <?= $serviceDuration ?>

                            mins

                        </td>


                        <!-- STATUS -->

                        <td>

                            <span
                                class="status status-<?= htmlspecialchars(
                                    $statusClass,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                                <?= ucfirst(
                                    htmlspecialchars(
                                        $serviceStatus,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                ) ?>

                            </span>

                        </td>


                        <!-- CREATED -->

                        <td>

                            <?php

                            if (
                                !empty($createdAt) &&
                                strtotime($createdAt) !== false
                            ) {

                                echo htmlspecialchars(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $createdAt
                                        )
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                );

                            } else {

                                echo '-';
                            }

                            ?>

                        </td>


                        <!-- ACTIONS -->

                        <td>

                            <div class="actions">


                                <!-- EDIT -->

                                <a
                                    href="<?= BASE_URL ?>/admin/services.php?edit=<?= $serviceId ?>"
                                    class="btn btn-edit"
                                >
                                    Edit
                                </a>


                                <!-- DELETE -->

                                <form
                                    method="POST"
                                    action="<?= BASE_URL ?>/admin/services.php"
                                    onsubmit="return confirm('Are you sure you want to delete this service?');"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="service_id"
                                        value="<?= $serviceId ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                            $csrfToken,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn btn-danger"
                                    >
                                        Delete
                                    </button>

                                </form>


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

                        No services found.

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
