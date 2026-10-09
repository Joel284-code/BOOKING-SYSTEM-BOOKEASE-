<?php
/**
 * BookEase Booking System
 * Admin - Add Service
 * File: services/create.php
 */

session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Admin Access
|--------------------------------------------------------------------------
*/

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . '/customer/dashboard.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

try {
    $db = new Database();
    $conn = $db->getConnection();

    if (!$conn instanceof PDO) {
        throw new Exception('Invalid database connection.');
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

    die('Database connection failed.');
}

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] =
        bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = '';

$name = '';
$description = '';
$price = '';
$duration = '';
$status = 'active';

/*
|--------------------------------------------------------------------------
| Application Name
|--------------------------------------------------------------------------
*/

$appName = defined('APP_NAME')
    ? APP_NAME
    : 'BookEase';

/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF Validation
    |--------------------------------------------------------------------------
    */

    $submittedToken =
        $_POST['csrf_token'] ?? '';

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $submittedToken
        )
    ) {

        $error =
            'Invalid form request. Please refresh the page and try again.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Get Values
        |--------------------------------------------------------------------------
        */

        $name =
            trim($_POST['name'] ?? '');

        $description =
            trim($_POST['description'] ?? '');

        $price =
            trim($_POST['price'] ?? '');

        $duration =
            trim($_POST['duration'] ?? '');

        $status =
            trim($_POST['status'] ?? 'active');


        /*
        |--------------------------------------------------------------------------
        | Validate Name
        |--------------------------------------------------------------------------
        */

        if ($name === '') {

            $error =
                'Service name is required.';

        } elseif (mb_strlen($name) > 150) {

            $error =
                'Service name cannot exceed 150 characters.';
        }


        /*
        |--------------------------------------------------------------------------
        | Validate Description
        |--------------------------------------------------------------------------
        */

        elseif (mb_strlen($description) > 2000) {

            $error =
                'Description cannot exceed 2000 characters.';
        }


        /*
        |--------------------------------------------------------------------------
        | Validate Price
        |--------------------------------------------------------------------------
        */

        elseif (
            $price === '' ||
            !is_numeric($price)
        ) {

            $error =
                'Please enter a valid price.';

        } elseif ((float) $price < 0) {

            $error =
                'Price cannot be negative.';

        } elseif ((float) $price > 999999999) {

            $error =
                'Price is too large.';
        }


        /*
        |--------------------------------------------------------------------------
        | Validate Duration
        |--------------------------------------------------------------------------
        */

        elseif ($duration === '') {

            $error =
                'Service duration is required.';

        } elseif (mb_strlen($duration) > 100) {

            $error =
                'Duration cannot exceed 100 characters.';
        }


        /*
        |--------------------------------------------------------------------------
        | Validate Status
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
        | Insert Service
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            try {

                $priceValue =
                    number_format(
                        (float) $price,
                        2,
                        '.',
                        ''
                    );

                $stmt = $conn->prepare("
                    INSERT INTO services
                    (
                        name,
                        description,
                        price,
                        duration,
                        status,
                        created_at,
                        updated_at
                    )
                    VALUES
                    (
                        :name,
                        :description,
                        :price,
                        :duration,
                        :status,
                        NOW(),
                        NOW()
                    )
                ");

                $stmt->execute([
                    ':name' =>
                        $name,

                    ':description' =>
                        $description,

                    ':price' =>
                        $priceValue,

                    ':duration' =>
                        $duration,

                    ':status' =>
                        $status
                ]);

                /*
                |--------------------------------------------------------------------------
                | Regenerate CSRF Token
                |--------------------------------------------------------------------------
                */

                $_SESSION['csrf_token'] =
                    bin2hex(random_bytes(32));

                /*
                |--------------------------------------------------------------------------
                | Success Redirect
                |--------------------------------------------------------------------------
                */

                header(
                    'Location: ' .
                    BASE_URL .
                    '/services/index.php?success=created'
                );

                exit;

            } catch (PDOException $e) {

                if (
                    defined('DEVELOPMENT_MODE') &&
                    DEVELOPMENT_MODE === true
                ) {

                    $error =
                        'Database error: ' .
                        $e->getMessage();

                } else {

                    $error =
                        'Unable to create the service. Please try again.';
                }
            }
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
    Add Service |
    <?= htmlspecialchars(
        $appName,
        ENT_QUOTES,
        'UTF-8'
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

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f5f7fb;

    color: #0f172a;
}


/* =========================================================
   DASHBOARD CONTENT POSITION
========================================================= */

.page-wrapper {

    margin-left: 240px;

    min-height: 100vh;

    padding: 30px;
}

.container {

    width: 100%;

    max-width: 1100px;

    margin: 0 auto;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 25px;
}

.page-header-left {

    display: flex;

    align-items: center;

    gap: 14px;
}

.page-icon {

    width: 50px;

    height: 50px;

    border-radius: 12px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #eff6ff;

    color: #2563eb;

    font-size: 24px;

    border: 1px solid #dbeafe;
}

.page-header h1 {

    margin: 0 0 5px;

    font-size: 27px;

    font-weight: 800;

    color: #0f172a;
}

.page-header p {

    margin: 0;

    color: #64748b;

    font-size: 14px;
}


/* =========================================================
   BACK BUTTON
========================================================= */

.back-link {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 10px 15px;

    border-radius: 8px;

    background: #ffffff;

    border: 1px solid #e2e8f0;

    color: #475569;

    text-decoration: none;

    font-size: 13px;

    font-weight: 600;

    transition: .2s;
}

.back-link:hover {

    background: #f8fafc;

    border-color: #cbd5e1;

    color: #2563eb;
}


/* =========================================================
   MAIN CARD
========================================================= */

.form-card {

    background: #ffffff;

    border: 1px solid #e2e8f0;

    border-radius: 14px;

    box-shadow:
        0 5px 20px
        rgba(15, 23, 42, .05);

    overflow: hidden;
}


/* =========================================================
   CARD HEADER
========================================================= */

.card-header {

    padding: 21px 25px;

    border-bottom:
        1px solid #e2e8f0;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;
}

.card-header h2 {

    margin: 0;

    font-size: 18px;

    color: #0f172a;
}

.card-header p {

    margin: 5px 0 0;

    color: #64748b;

    font-size: 13px;
}

.header-badge {

    padding: 6px 11px;

    border-radius: 20px;

    background: #eff6ff;

    color: #2563eb;

    font-size: 11px;

    font-weight: 700;

    white-space: nowrap;
}


/* =========================================================
   FORM BODY
========================================================= */

.form-body {

    padding: 28px;
}


/* =========================================================
   ALERT
========================================================= */

.alert {

    display: flex;

    align-items: flex-start;

    gap: 10px;

    background: #fef2f2;

    color: #991b1b;

    border: 1px solid #fecaca;

    border-radius: 9px;

    padding: 13px 15px;

    margin-bottom: 24px;

    font-size: 14px;

    line-height: 1.5;
}

.alert-icon {

    font-weight: 800;
}


/* =========================================================
   FORM GRID
========================================================= */

.form-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 22px;
}

.full-width {

    grid-column: 1 / -1;
}


/* =========================================================
   FORM GROUP
========================================================= */

.form-group {

    margin-bottom: 2px;
}

label {

    display: block;

    margin-bottom: 8px;

    font-size: 13px;

    font-weight: 700;

    color: #334155;
}

.required {

    color: #dc2626;
}


/* =========================================================
   INPUTS
========================================================= */

input,
textarea,
select {

    width: 100%;

    border: 1px solid #cbd5e1;

    border-radius: 8px;

    background: #ffffff;

    color: #0f172a;

    padding: 12px 13px;

    font-family: inherit;

    font-size: 14px;

    transition: .2s;
}

input {

    height: 45px;
}

select {

    height: 45px;

    cursor: pointer;
}

textarea {

    min-height: 125px;

    resize: vertical;

    line-height: 1.5;
}

input::placeholder,
textarea::placeholder {

    color: #94a3b8;
}

input:hover,
textarea:hover,
select:hover {

    border-color: #94a3b8;
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


/* =========================================================
   INPUT WITH PREFIX
========================================================= */

.input-prefix {

    position: relative;
}

.input-prefix span {

    position: absolute;

    left: 13px;

    top: 50%;

    transform: translateY(-50%);

    color: #64748b;

    font-size: 13px;

    font-weight: 700;

    pointer-events: none;
}

.input-prefix input {

    padding-left: 48px;
}


/* =========================================================
   HELP TEXT
========================================================= */

.help-text {

    display: block;

    margin-top: 6px;

    color: #94a3b8;

    font-size: 12px;

    line-height: 1.4;
}


/* =========================================================
   STATUS SELECT
========================================================= */

.status-help {

    display: flex;

    align-items: center;

    gap: 7px;

    margin-top: 7px;

    color: #64748b;

    font-size: 12px;
}

.status-dot {

    width: 7px;

    height: 7px;

    border-radius: 50%;

    background: #16a34a;
}


/* =========================================================
   ACTIONS
========================================================= */

.actions {

    display: flex;

    align-items: center;

    justify-content: flex-end;

    gap: 10px;

    margin-top: 30px;

    padding-top: 22px;

    border-top:
        1px solid #e2e8f0;
}

.btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    min-height: 43px;

    padding:
        10px 18px;

    border-radius: 8px;

    border: none;

    text-decoration: none;

    cursor: pointer;

    font-size: 13px;

    font-weight: 700;

    font-family: inherit;

    transition: .2s;
}

.btn-primary {

    background: #2563eb;

    color: #ffffff;

    box-shadow:
        0 2px 5px
        rgba(37, 99, 235, .20);
}

.btn-primary:hover {

    background: #1d4ed8;

    transform: translateY(-1px);
}

.btn-secondary {

    background: #f1f5f9;

    color: #334155;

    border: 1px solid #e2e8f0;
}

.btn-secondary:hover {

    background: #e2e8f0;
}


/* =========================================================
   FORM FOOTNOTE
========================================================= */

.form-footer {

    margin-top: 18px;

    color: #94a3b8;

    font-size: 12px;

    text-align: right;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .page-wrapper {

        margin-left: 70px;

        padding: 25px;
    }

    .form-grid {

        grid-template-columns: 1fr;
    }

    .full-width {

        grid-column: auto;
    }
}


@media (max-width: 650px) {

    .page-wrapper {

        margin-left: 0;

        padding: 18px 15px;
    }

    .page-header {

        align-items: flex-start;

        flex-direction: column;
    }

    .page-header-left {

        align-items: flex-start;
    }

    .back-link {

        width: 100%;

        justify-content: center;
    }

    .form-body {

        padding: 20px;
    }

    .card-header {

        align-items: flex-start;

        flex-direction: column;
    }

    .header-badge {

        align-self: flex-start;
    }

    .actions {

        flex-direction: column-reverse;

        align-items: stretch;
    }

    .btn {

        width: 100%;
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


<!-- =========================================================
     PAGE CONTENT
========================================================= -->

<div class="page-wrapper">

    <div class="container">


        <!-- =====================================================
             PAGE HEADER
        ====================================================== -->

        <div class="page-header">

            <div class="page-header-left">

                <div class="page-icon">
                    +
                </div>

                <div>

                    <h1>
                        Add Service
                    </h1>

                    <p>
                        Create a new service that customers can book.
                    </p>

                </div>

            </div>


            <a
                href="<?= BASE_URL ?>/services/index.php"
                class="back-link"
            >
                ← Back to Services
            </a>

        </div>


        <!-- =====================================================
             FORM CARD
        ====================================================== -->

        <div class="form-card">


            <!-- CARD HEADER -->

            <div class="card-header">

                <div>

                    <h2>
                        Service Information
                    </h2>

                    <p>
                        Enter the details of the service below.
                    </p>

                </div>

                <span class="header-badge">
                    NEW SERVICE
                </span>

            </div>


            <!-- FORM BODY -->

            <div class="form-body">


                <!-- ERROR -->

                <?php if ($error !== ''): ?>

                    <div class="alert">

                        <span class="alert-icon">
                            !
                        </span>

                        <span>

                            <?= htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    action=""
                    autocomplete="off"
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


                    <div class="form-grid">


                        <!-- =================================================
                             SERVICE NAME
                        ================================================== -->

                        <div class="form-group full-width">

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
                                maxlength="150"
                                placeholder="e.g. Website Development"
                                value="<?= htmlspecialchars(
                                    $name,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required
                                autofocus
                            >

                            <span class="help-text">
                                Enter the name customers will see when selecting a service.
                            </span>

                        </div>


                        <!-- =================================================
                             DESCRIPTION
                        ================================================== -->

                        <div class="form-group full-width">

                            <label for="description">

                                Description

                            </label>

                            <textarea
                                id="description"
                                name="description"
                                maxlength="2000"
                                placeholder="Describe what is included in this service..."
                            ><?= htmlspecialchars(
                                $description,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></textarea>

                            <span class="help-text">
                                Provide a short description of what the customer receives.
                            </span>

                        </div>


                        <!-- =================================================
                             PRICE
                        ================================================== -->

                        <div class="form-group">

                            <label for="price">

                                Price

                                <span class="required">
                                    *
                                </span>

                            </label>

                            <div class="input-prefix">

                                <span>
                                    KSh
                                </span>

                                <input
                                    type="number"
                                    id="price"
                                    name="price"
                                    min="0"
                                    max="999999999"
                                    step="0.01"
                                    placeholder="0.00"
                                    value="<?= htmlspecialchars(
                                        $price,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    required
                                >

                            </div>

                            <span class="help-text">
                                Enter the amount customers will pay for this service.
                            </span>

                        </div>


                        <!-- =================================================
                             DURATION
                        ================================================== -->

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
                                maxlength="100"
                                placeholder="e.g. 30 minutes"
                                value="<?= htmlspecialchars(
                                    $duration,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required
                            >

                            <span class="help-text">
                                Example: 30 minutes, 2 hours, or 3 days.
                            </span>

                        </div>


                        <!-- =================================================
                             STATUS
                        ================================================== -->

                        <div class="form-group">

                            <label for="status">

                                Service Status

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

                            <div class="status-help">

                                <span class="status-dot"></span>

                                Active services are available for booking.

                            </div>

                        </div>


                    </div>


                    <!-- =================================================
                         ACTIONS
                    ================================================== -->

                    <div class="actions">


                        <a
                            href="<?= BASE_URL ?>/services/index.php"
                            class="btn btn-secondary"
                        >
                            Cancel
                        </a>


                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            ✓ Save Service
                        </button>


                    </div>


                    <div class="form-footer">

                        Fields marked with
                        <span class="required">*</span>
                        are required.

                    </div>


                </form>


            </div>

        </div>


    </div>

</div>


</body>

</html>