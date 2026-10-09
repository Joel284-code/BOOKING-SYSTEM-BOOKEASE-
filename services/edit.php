<?php
/**
 * Edit Service
 * File: services/edit.php
 * BookEase Booking System
 */

session_start();

/*
|--------------------------------------------------------------------------
| Load configuration and database
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';


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
| Administrator access
|--------------------------------------------------------------------------
*/

if (
    ($_SESSION['role'] ?? '') !== 'admin'
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
| Database connection
|--------------------------------------------------------------------------
*/

try {

    $db = new Database();

    $conn = $db->getConnection();

} catch (PDOException $e) {

    die('Database connection failed.');

}


/*
|--------------------------------------------------------------------------
| Get service ID
|--------------------------------------------------------------------------
*/

$id = (int) (
    $_GET['id']
    ?? $_POST['id']
    ?? 0
);


/*
|--------------------------------------------------------------------------
| Validate service ID
|--------------------------------------------------------------------------
*/

if ($id <= 0) {

    header(
        'Location: ' .
        BASE_URL .
        '/services/index.php?error=' .
        urlencode('Invalid service ID.')
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Get service
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
            status
        FROM services
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $service = $stmt->fetch(
        PDO::FETCH_ASSOC
    );


    /*
    |--------------------------------------------------------------------------
    | Service not found
    |--------------------------------------------------------------------------
    */

    if (!$service) {

        header(
            'Location: ' .
            BASE_URL .
            '/services/index.php?error=' .
            urlencode('Service not found.')
        );

        exit;
    }


} catch (PDOException $e) {

    die('Unable to load service.');

}


/*
|--------------------------------------------------------------------------
| Form variables
|--------------------------------------------------------------------------
*/

$error = '';

$name =
    $_POST['name']
    ?? $service['name'];

$description =
    $_POST['description']
    ?? ($service['description'] ?? '');

$price =
    $_POST['price']
    ?? $service['price'];

$duration =
    $_POST['duration']
    ?? $service['duration'];

$status =
    $_POST['status']
    ?? $service['status'];


/*
|--------------------------------------------------------------------------
| Handle update
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /*
    |--------------------------------------------------------------------------
    | Clean input
    |--------------------------------------------------------------------------
    */

    $name =
        trim(
            $_POST['name'] ?? ''
        );

    $description =
        trim(
            $_POST['description'] ?? ''
        );

    $price =
        trim(
            $_POST['price'] ?? ''
        );

    $duration =
        trim(
            $_POST['duration'] ?? ''
        );

    $status =
        trim(
            $_POST['status'] ?? 'active'
        );


    /*
    |--------------------------------------------------------------------------
    | Validate service name
    |--------------------------------------------------------------------------
    */

    if ($name === '') {

        $error =
            'Service name is required.';

    }


    /*
    |--------------------------------------------------------------------------
    | Validate price
    |--------------------------------------------------------------------------
    */

    elseif (
        $price === ''
        ||
        !is_numeric($price)
    ) {

        $error =
            'Please enter a valid price.';

    }

    elseif (
        (float)$price < 0
    ) {

        $error =
            'Price cannot be negative.';

    }


    /*
    |--------------------------------------------------------------------------
    | Validate duration
    |--------------------------------------------------------------------------
    */

    elseif ($duration === '') {

        $error =
            'Service duration is required.';

    }


    /*
    |--------------------------------------------------------------------------
    | Validate status
    |--------------------------------------------------------------------------
    */

    elseif (
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
    |--------------------------------------------------------------------------
    | Update database
    |--------------------------------------------------------------------------
    */

    else {

        try {

            $price =
                (float)$price;


            $stmt = $conn->prepare("
                UPDATE services
                SET
                    name = :name,
                    description = :description,
                    price = :price,
                    duration = :duration,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id
            ");


            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':price' => $price,
                ':duration' => $duration,
                ':status' => $status,
                ':id' => $id
            ]);


            /*
            |--------------------------------------------------------------------------
            | Successful update
            |--------------------------------------------------------------------------
            */

            header(
                'Location: ' .
                BASE_URL .
                '/services/index.php?success=updated'
            );

            exit;


        } catch (PDOException $e) {

            $error =
                'Failed to update service. Please try again.';

            /*
             * For development, you can temporarily use:
             *
             * $error = $e->getMessage();
             */
        }
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
        Edit Service | BookEase
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
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            background: #f5f7fb;
            color: #0f172a;
        }

        .page-wrapper {
            margin-left: 240px;
            padding: 35px;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 850px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0 0 7px;
            font-size: 28px;
        }

        .page-header p {
            margin: 0;
            color: #64748b;
        }

        .card {
            background: #ffffff;
            padding: 35px;
            border-radius: 14px;
            box-shadow:
                0 5px 20px rgba(0, 0, 0, .06);
        }

        .service-id {
            display: inline-block;
            margin-top: 8px;
            color: #64748b;
            font-size: 13px;
        }

        .alert {
            background: #fee2e2;
            color: #991b1b;
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 22px;
            border: 1px solid #fecaca;
        }

        .form-group {
            margin-bottom: 21px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .required {
            color: #dc2626;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            background: #ffffff;
            color: #0f172a;
        }

        textarea {
            min-height: 130px;
            resize: vertical;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, .10);
        }

        .help-text {
            display: block;
            margin-top: 6px;
            font-size: 13px;
            color: #64748b;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid #e2e8f0;
        }

        .btn {
            display: inline-block;
            padding: 12px 20px;
            border-radius: 8px;
            border: none;
            text-decoration: none;
            cursor: pointer;
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
            background: #e2e8f0;
            color: #0f172a;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        @media (max-width: 800px) {

            .page-wrapper {
                margin-left: 70px;
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

        }

        @media (max-width: 600px) {

            .page-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .card {
                padding: 22px;
            }

            .page-header h1 {
                font-size: 23px;
            }

            .actions {
                flex-direction: column;
                align-items: stretch;
            }

            .btn {
                width: 100%;
                text-align: center;
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

if (file_exists($navbarFile)) {
    require $navbarFile;
}


/*
|--------------------------------------------------------------------------
| Sidebar
|--------------------------------------------------------------------------
*/

$sidebarFile =
    __DIR__ .
    '/../includes/sidebar.php';

if (file_exists($sidebarFile)) {
    require $sidebarFile;
}

?>


<div class="page-wrapper">

    <div class="container">


        <!-- PAGE HEADER -->

        <div class="page-header">

            <h1>
                Edit Service
            </h1>

            <p>
                Update the service information below.
            </p>

            <span class="service-id">
                Service ID:
                #<?= (int)$service['id'] ?>
            </span>

        </div>


        <!-- FORM CARD -->

        <div class="card">


            <?php if ($error !== ''): ?>

                <div class="alert">

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action=""
            >

                <!-- Keep service ID -->

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int)$id ?>"
                >


                <!-- SERVICE NAME -->

                <div class="form-group">

                    <label for="name">

                        Service Name

                        <span class="required">
                            *
                        </span>

                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= htmlspecialchars(
                            $name,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="e.g. Website Development"
                        required
                    >

                </div>


                <!-- DESCRIPTION -->

                <div class="form-group">

                    <label for="description">
                        Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        placeholder="Describe the service..."
                    ><?= htmlspecialchars(
                        $description,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?></textarea>

                </div>


                <!-- PRICE + DURATION -->

                <div class="form-row">


                    <div class="form-group">

                        <label for="price">

                            Price (KSh)

                            <span class="required">
                                *
                            </span>

                        </label>

                        <input
                            type="number"
                            id="price"
                            name="price"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars(
                                $price,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="duration">

                            Duration

                            <span class="required">
                                *
                            </span>

                        </label>

                        <input
                            type="text"
                            id="duration"
                            name="duration"
                            value="<?= htmlspecialchars(
                                $duration,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            placeholder="e.g. 2 hours"
                            required
                        >

                    </div>

                </div>


                <!-- STATUS -->

                <div class="form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value="active"
                            <?= $status === 'active'
                                ? 'selected'
                                : '' ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?= $status === 'inactive'
                                ? 'selected'
                                : '' ?>
                        >
                            Inactive
                        </option>

                    </select>

                    <span class="help-text">
                        Inactive services cannot be selected
                        for new bookings.
                    </span>

                </div>


                <!-- ACTIONS -->

                <div class="actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Update Service
                    </button>


                    <a
                        href="<?= BASE_URL ?>/services/index.php"
                        class="btn btn-secondary"
                    >
                        Cancel
                    </a>

                </div>


            </form>

        </div>

    </div>

</div>


</body>

</html>