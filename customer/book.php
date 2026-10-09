<?php
/**
 * BookEase - Customer Book Service
 * File: /customer/book.php
 *
 * Modern UI Version
 * - PDO
 * - Africa/Nairobi timezone
 * - Same date format: YYYY-MM-DD
 * - Same time format: HH:MM
 * - Prevents past bookings
 * - Checks service availability
 * - Creates bookings
 * - Redirects to booking details
 */

date_default_timezone_set('Africa/Nairobi');

session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';


/*
|--------------------------------------------------------------------------
| CHECK LOGIN
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    (int) $_SESSION['user_id'] <= 0
) {
    header("Location: ../auth/login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$userId = (int) $_SESSION['user_id'];

$userName = $_SESSION['name'] ?? 'Customer';


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

try {

    $db = new Database();

    $conn = $db->getConnection();

    if (!$conn instanceof PDO) {
        throw new Exception(
            "Database connection was not created correctly."
        );
    }

} catch (Throwable $e) {

    die(
        "Database connection error: " .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error = '';

$success = '';

$services = [];

$selectedServiceId = (int) ($_POST['service_id'] ?? 0);

$selectedDate = trim(
    $_POST['booking_date'] ?? ''
);

$selectedTime = trim(
    $_POST['booking_time'] ?? ''
);

$notes = trim(
    $_POST['notes'] ?? ''
);


/*
|--------------------------------------------------------------------------
| LOAD ACTIVE SERVICES
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
        WHERE status = 'active'
        ORDER BY name ASC
    ");

    $stmt->execute();

    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $services = [];

    $error =
        "Unable to load services from the database.";
}


/*
|--------------------------------------------------------------------------
| CREATE BOOKING
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | VALIDATE SERVICE
    |--------------------------------------------------------------------------
    */

    if ($selectedServiceId <= 0) {

        $error = "Please select a service.";

    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE DATE
    |--------------------------------------------------------------------------
    */

    elseif ($selectedDate === '') {

        $error =
            "Please select a booking date.";

    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATE TIME
    |--------------------------------------------------------------------------
    */

    elseif ($selectedTime === '') {

        $error =
            "Please select a booking time.";

    }

    else {

        $timezone = new DateTimeZone(
            'Africa/Nairobi'
        );


        /*
        |--------------------------------------------------------------------------
        | VALIDATE DATE FORMAT
        |--------------------------------------------------------------------------
        */

        $dateObject =
            DateTime::createFromFormat(
                '!Y-m-d',
                $selectedDate,
                $timezone
            );


        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $selectedDate
        ) {

            $error =
                "Invalid booking date.";

        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE TIME FORMAT
        |--------------------------------------------------------------------------
        */

        else {

            $timeObject =
                DateTime::createFromFormat(
                    '!H:i',
                    $selectedTime,
                    $timezone
                );


            if (
                !$timeObject ||
                $timeObject->format('H:i') !== $selectedTime
            ) {

                $error =
                    "Invalid booking time.";

            }


            /*
            |--------------------------------------------------------------------------
            | PREVENT PAST DATE
            |--------------------------------------------------------------------------
            */

            elseif (
                $selectedDate < date('Y-m-d')
            ) {

                $error =
                    "You cannot select a date in the past.";

            }


            else {

                /*
                |--------------------------------------------------------------------------
                | CURRENT NAIROBI TIME
                |--------------------------------------------------------------------------
                */

                $currentDateTime =
                    new DateTime(
                        'now',
                        $timezone
                    );


                /*
                |--------------------------------------------------------------------------
                | SELECTED BOOKING DATE/TIME
                |--------------------------------------------------------------------------
                */

                $selectedDateTime =
                    DateTime::createFromFormat(
                        'Y-m-d H:i',
                        $selectedDate .
                        ' ' .
                        $selectedTime,
                        $timezone
                    );


                if (!$selectedDateTime) {

                    $error =
                        "Invalid booking date or time.";

                }

                /*
                |--------------------------------------------------------------------------
                | PREVENT PAST TIME
                |--------------------------------------------------------------------------
                */

                elseif (
                    $selectedDateTime <=
                    $currentDateTime
                ) {

                    $error =
                        "The selected booking time has already passed. Please choose a future time.";

                }

                else {

                    /*
                    |--------------------------------------------------------------------------
                    | CHECK SERVICE
                    |--------------------------------------------------------------------------
                    */

                    try {

                        $stmt = $conn->prepare("
                            SELECT
                                id,
                                name,
                                description,
                                price,
                                duration
                            FROM services
                            WHERE id = ?
                            AND status = 'active'
                            LIMIT 1
                        ");

                        $stmt->execute([
                            $selectedServiceId
                        ]);

                        $service =
                            $stmt->fetch(
                                PDO::FETCH_ASSOC
                            );


                        if (!$service) {

                            $error =
                                "The selected service does not exist or is inactive.";

                        }

                        else {

                            /*
                            |--------------------------------------------------------------------------
                            | CHECK EXISTING BOOKING
                            |--------------------------------------------------------------------------
                            */

                            $stmt = $conn->prepare("
                                SELECT
                                    id,
                                    booking_reference
                                FROM bookings
                                WHERE service_id = ?
                                AND booking_date = ?
                                AND booking_time = ?
                                AND status IN (
                                    'pending',
                                    'confirmed'
                                )
                                LIMIT 1
                            ");

                            $stmt->execute([
                                $selectedServiceId,
                                $selectedDate,
                                $selectedTime
                            ]);


                            $existingBooking =
                                $stmt->fetch(
                                    PDO::FETCH_ASSOC
                                );


                            if ($existingBooking) {

                                $error =
                                    "The selected date and time are already booked. Please choose another time.";

                            }

                            else {

                                /*
                                |--------------------------------------------------------------------------
                                | GENERATE BOOKING REFERENCE
                                |--------------------------------------------------------------------------
                                */

                                try {

                                    $randomCode =
                                        strtoupper(
                                            bin2hex(
                                                random_bytes(4)
                                            )
                                        );

                                } catch (Throwable $e) {

                                    $randomCode =
                                        strtoupper(
                                            substr(
                                                md5(
                                                    uniqid(
                                                        '',
                                                        true
                                                    )
                                                ),
                                                0,
                                                8
                                            )
                                        );
                                }


                                $bookingReference =
                                    'BK-' .
                                    date('Ymd') .
                                    '-' .
                                    $randomCode;


                                /*
                                |--------------------------------------------------------------------------
                                | SERVICE PRICE
                                |--------------------------------------------------------------------------
                                */

                                $amount =
                                    (float) (
                                        $service['price']
                                        ?? 0
                                    );


                                /*
                                |--------------------------------------------------------------------------
                                | INSERT BOOKING
                                |--------------------------------------------------------------------------
                                */

                                $stmt =
                                    $conn->prepare("
                                        INSERT INTO bookings
                                        (
                                            booking_reference,
                                            user_id,
                                            service_id,
                                            booking_date,
                                            booking_time,
                                            amount,
                                            notes,
                                            status,
                                            payment_status,
                                            created_at
                                        )
                                        VALUES
                                        (
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            ?,
                                            'pending',
                                            'unpaid',
                                            NOW()
                                        )
                                    ");


                                $stmt->execute([
                                    $bookingReference,
                                    $userId,
                                    $selectedServiceId,
                                    $selectedDate,
                                    $selectedTime,
                                    $amount,
                                    $notes
                                ]);


                                /*
                                |--------------------------------------------------------------------------
                                | GET BOOKING ID
                                |--------------------------------------------------------------------------
                                */

                                $bookingId =
                                    $conn->lastInsertId();


                                if (!$bookingId) {

                                    throw new Exception(
                                        "Booking was created but the booking ID could not be retrieved."
                                    );
                                }


                                /*
                                |--------------------------------------------------------------------------
                                | REDIRECT
                                |--------------------------------------------------------------------------
                                */

                                header(
                                    "Location: booking-details.php?id=" .
                                    urlencode($bookingId) .
                                    "&created=1"
                                );

                                exit;
                            }
                        }

                    } catch (PDOException $e) {

                        $error =
                            "Unable to create booking. Please try again.";

                    } catch (Throwable $e) {

                        $error =
                            $e->getMessage();
                    }
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| ESCAPE FUNCTION
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| TODAY
|--------------------------------------------------------------------------
*/

$today =
    date('Y-m-d');

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
        Book a Service | BookEase
    </title>


    <style>

        /* =========================================================
           RESET
        ========================================================= */

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
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Arial,
                sans-serif;

            background:
                #f6f8fc;

            color:
                #0f172a;

            min-height: 100vh;
        }


        /* =========================================================
           NAVBAR
        ========================================================= */

        .navbar {

            height: 72px;

            background:
                rgba(255,255,255,.94);

            backdrop-filter:
                blur(14px);

            -webkit-backdrop-filter:
                blur(14px);

            border-bottom:
                1px solid #e5e7eb;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 5%;

            position: sticky;

            top: 0;

            z-index: 1000;

            box-shadow:
                0 4px 20px
                rgba(15,23,42,.04);
        }


        .logo {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            color:
                #2563eb;

            text-decoration: none;

            font-size: 25px;

            font-weight: 800;

            letter-spacing:
                -.7px;
        }


        .logo-icon {

            width: 38px;

            height: 38px;

            border-radius: 11px;

            display: flex;

            align-items: center;

            justify-content: center;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #4f46e5
                );

            color: white;

            font-size: 17px;

            box-shadow:
                0 7px 18px
                rgba(37,99,235,.25);
        }


        .logo span {

            color:
                #111827;
        }


        .nav-links {

            display: flex;

            align-items: center;

            gap: 5px;
        }


        .nav-links a {

            color:
                #475569;

            text-decoration: none;

            padding:
                9px 12px;

            border-radius: 9px;

            font-size: 13px;

            font-weight: 600;

            transition:
                .2s ease;
        }


        .nav-links a:hover {

            color:
                #2563eb;

            background:
                #eff6ff;
        }


        .nav-links .active {

            color:
                #2563eb;

            background:
                #eff6ff;
        }


        .logout {

            color:
                #dc2626 !important;

            background:
                #fff1f2;
        }


        .logout:hover {

            background:
                #fee2e2 !important;
        }


        /* =========================================================
           PAGE
        ========================================================= */

        .page {

            width:
                92%;

            max-width:
                1150px;

            margin:
                45px auto 80px;
        }


        /* =========================================================
           HERO
        ========================================================= */

        .hero {

            position: relative;

            overflow: hidden;

            border-radius:
                24px;

            padding:
                35px 38px;

            margin-bottom:
                25px;

            background:
                linear-gradient(
                    135deg,
                    #1d4ed8 0%,
                    #4338ca 55%,
                    #6366f1 100%
                );

            color:
                white;

            box-shadow:
                0 18px 45px
                rgba(37,99,235,.18);
        }


        .hero::before {

            content: "";

            position: absolute;

            width: 230px;

            height: 230px;

            border-radius: 50%;

            background:
                rgba(255,255,255,.09);

            right:
                -70px;

            top:
                -100px;
        }


        .hero::after {

            content: "";

            position: absolute;

            width: 170px;

            height: 170px;

            border-radius: 50%;

            background:
                rgba(255,255,255,.06);

            right:
                130px;

            bottom:
                -100px;
        }


        .hero-content {

            position:
                relative;

            z-index:
                2;

            max-width:
                700px;
        }


        .hero-badge {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                7px;

            padding:
                7px 11px;

            border-radius:
                30px;

            background:
                rgba(255,255,255,.14);

            border:
                1px solid
                rgba(255,255,255,.18);

            font-size:
                12px;

            font-weight:
                700;

            margin-bottom:
                13px;
        }


        .hero h1 {

            margin:
                0 0 9px;

            font-size:
                34px;

            line-height:
                1.15;

            letter-spacing:
                -.8px;
        }


        .hero p {

            margin:
                0;

            color:
                rgba(255,255,255,.84);

            line-height:
                1.65;

            font-size:
                14px;
        }


        /* =========================================================
           MAIN GRID
        ========================================================= */

        .booking-layout {

            display:
                grid;

            grid-template-columns:
                minmax(0, 1fr)
                330px;

            gap:
                24px;

            align-items:
                start;
        }


        /* =========================================================
           CARD
        ========================================================= */

        .card {

            background:
                white;

            border:
                1px solid #e5e7eb;

            border-radius:
                20px;

            box-shadow:
                0 10px 35px
                rgba(15,23,42,.055);
        }


        .form-card {

            padding:
                30px;
        }


        .card-title {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;

            margin-bottom:
                27px;
        }


        .card-title-icon {

            width:
                42px;

            height:
                42px;

            border-radius:
                12px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                #eff6ff;

            color:
                #2563eb;

            font-size:
                18px;
        }


        .card-title h2 {

            margin:
                0 0 3px;

            font-size:
                19px;

            letter-spacing:
                -.2px;
        }


        .card-title p {

            margin:
                0;

            font-size:
                12px;

            color:
                #64748b;
        }


        /* =========================================================
           ALERT
        ========================================================= */

        .alert {

            padding:
                14px 16px;

            border-radius:
                12px;

            margin-bottom:
                20px;

            font-size:
                13px;

            line-height:
                1.55;

            display:
                flex;

            gap:
                10px;

            align-items:
                flex-start;
        }


        .alert-error {

            background:
                #fff1f2;

            border:
                1px solid #fecdd3;

            color:
                #9f1239;
        }


        .alert-icon {

            font-size:
                17px;

            line-height:
                1;
        }


        /* =========================================================
           FORM
        ========================================================= */

        .form-group {

            margin-bottom:
                22px;
        }


        .form-label {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            margin-bottom:
                8px;
        }


        .form-label label {

            font-size:
                13px;

            font-weight:
                700;

            color:
                #334155;
        }


        .required {

            color:
                #dc2626;
        }


        .input-wrapper {

            position:
                relative;
        }


        .input-icon {

            position:
                absolute;

            left:
                14px;

            top:
                50%;

            transform:
                translateY(-50%);

            color:
                #94a3b8;

            pointer-events:
                none;

            font-size:
                16px;
        }


        .form-control {

            width:
                100%;

            height:
                49px;

            border:
                1px solid #d7dee8;

            border-radius:
                11px;

            background:
                #fff;

            color:
                #0f172a;

            padding:
                0 14px;

            font-size:
                14px;

            outline:
                none;

            transition:
                .2s ease;
        }


        .form-control.with-icon {

            padding-left:
                43px;
        }


        .form-control:hover {

            border-color:
                #aab7c8;
        }


        .form-control:focus {

            border-color:
                #2563eb;

            box-shadow:
                0 0 0 4px
                rgba(37,99,235,.10);
        }


        textarea.form-control {

            height:
                auto;

            min-height:
                120px;

            padding:
                13px 14px;

            resize:
                vertical;

            line-height:
                1.55;
        }


        .help-text {

            margin-top:
                7px;

            color:
                #94a3b8;

            font-size:
                11px;
        }


        /* =========================================================
           SERVICE SELECT
        ========================================================= */

        .service-select-wrapper {

            position:
                relative;
        }


        .service-select-wrapper::after {

            content:
                "⌄";

            position:
                absolute;

            right:
                15px;

            top:
                12px;

            color:
                #64748b;

            font-size:
                17px;

            pointer-events:
                none;
        }


        .service-select-wrapper select {

            appearance:
                none;

            padding-right:
                42px;
        }


        /* =========================================================
           SERVICE INFORMATION
        ========================================================= */

        .service-info {

            display:
                none;

            margin-top:
                10px;

            padding:
                14px;

            border:
                1px solid #dbeafe;

            background:
                linear-gradient(
                    135deg,
                    #f8fbff,
                    #eff6ff
                );

            border-radius:
                12px;
        }


        .service-info-top {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                12px;
        }


        .service-info-name {

            font-weight:
                700;

            color:
                #1e3a8a;

            font-size:
                13px;
        }


        .service-price {

            font-weight:
                800;

            color:
                #2563eb;

            font-size:
                14px;

            white-space:
                nowrap;
        }


        .service-info-bottom {

            margin-top:
                7px;

            display:
                flex;

            gap:
                12px;

            color:
                #64748b;

            font-size:
                11px;

            flex-wrap:
                wrap;
        }


        .service-description {

            margin-top:
                9px;

            padding-top:
                9px;

            border-top:
                1px solid #dbeafe;

            color:
                #64748b;

            line-height:
                1.5;

            font-size:
                12px;
        }


        /* =========================================================
           DATE TIME GRID
        ========================================================= */

        .date-time-grid {

            display:
                grid;

            grid-template-columns:
                1fr 1fr;

            gap:
                15px;
        }


        /* =========================================================
           BUTTONS
        ========================================================= */

        .button-row {

            display:
                flex;

            gap:
                11px;

            margin-top:
                28px;

            padding-top:
                23px;

            border-top:
                1px solid #eef2f7;
        }


        .btn {

            min-height:
                47px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            padding:
                0 20px;

            border:
                none;

            border-radius:
                10px;

            font-size:
                13px;

            font-weight:
                700;

            text-decoration:
                none;

            cursor:
                pointer;

            transition:
                .2s ease;
        }


        .btn-primary {

            flex:
                1;

            color:
                white;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #4f46e5
                );

            box-shadow:
                0 8px 20px
                rgba(37,99,235,.18);
        }


        .btn-primary:hover {

            transform:
                translateY(-1px);

            box-shadow:
                0 11px 25px
                rgba(37,99,235,.25);
        }


        .btn-secondary {

            color:
                #334155;

            background:
                #f1f5f9;

            border:
                1px solid #e2e8f0;
        }


        .btn-secondary:hover {

            background:
                #e2e8f0;
        }


        .btn:disabled {

            opacity:
                .55;

            cursor:
                not-allowed;

            transform:
                none;
        }


        /* =========================================================
           SUMMARY CARD
        ========================================================= */

        .summary-card {

            position:
                sticky;

            top:
                96px;

            overflow:
                hidden;
        }


        .summary-header {

            padding:
                23px;

            background:
                linear-gradient(
                    135deg,
                    #0f172a,
                    #1e293b
                );

            color:
                white;
        }


        .summary-header h3 {

            margin:
                0 0 5px;

            font-size:
                17px;
        }


        .summary-header p {

            margin:
                0;

            color:
                #94a3b8;

            font-size:
                11px;

            line-height:
                1.5;
        }


        .summary-body {

            padding:
                22px;
        }


        .summary-empty {

            text-align:
                center;

            padding:
                20px 5px;

            color:
                #94a3b8;

            font-size:
                12px;

            line-height:
                1.6;
        }


        .summary-item {

            display:
                flex;

            align-items:
                flex-start;

            gap:
                12px;

            padding:
                13px 0;

            border-bottom:
                1px solid #f1f5f9;
        }


        .summary-item:last-child {

            border-bottom:
                none;
        }


        .summary-icon {

            flex:
                0 0 34px;

            width:
                34px;

            height:
                34px;

            border-radius:
                9px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            background:
                #eff6ff;

            color:
                #2563eb;

            font-size:
                14px;
        }


        .summary-label {

            font-size:
                10px;

            color:
                #94a3b8;

            text-transform:
                uppercase;

            letter-spacing:
                .5px;

            font-weight:
                700;

            margin-bottom:
                3px;
        }


        .summary-value {

            color:
                #1e293b;

            font-size:
                13px;

            font-weight:
                700;

            word-break:
                break-word;
        }


        .summary-price {

            margin-top:
                18px;

            padding:
                17px;

            border-radius:
                12px;

            background:
                #f8fafc;

            border:
                1px solid #e2e8f0;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;
        }


        .summary-price span:first-child {

            color:
                #64748b;

            font-size:
                12px;

            font-weight:
                600;
        }


        .summary-price strong {

            color:
                #2563eb;

            font-size:
                18px;
        }


        .secure-note {

            display:
                flex;

            align-items:
                center;

            gap:
                7px;

            margin-top:
                17px;

            color:
                #64748b;

            font-size:
                10px;

            line-height:
                1.5;
        }


        /* =========================================================
           EMPTY SERVICES
        ========================================================= */

        .empty-services {

            margin-bottom:
                20px;

            padding:
                16px;

            border-radius:
                12px;

            background:
                #fff7ed;

            border:
                1px solid #fed7aa;

            color:
                #9a3412;

            font-size:
                13px;

            line-height:
                1.6;
        }


        /* =========================================================
           FOOTER NOTE
        ========================================================= */

        .page-note {

            margin-top:
                22px;

            text-align:
                center;

            color:
                #94a3b8;

            font-size:
                11px;
        }


        /* =========================================================
           MOBILE
        ========================================================= */

        @media (max-width: 900px) {

            .booking-layout {

                grid-template-columns:
                    1fr;
            }


            .summary-card {

                position:
                    static;
            }

        }


        @media (max-width: 700px) {

            .navbar {

                height:
                    auto;

                padding:
                    13px 4%;

                flex-direction:
                    column;

                align-items:
                    stretch;

                gap:
                    11px;
            }


            .logo {

                justify-content:
                    center;
            }


            .nav-links {

                width:
                    100%;

                overflow-x:
                    auto;

                justify-content:
                    flex-start;

                padding-bottom:
                    2px;
            }


            .nav-links a {

                white-space:
                    nowrap;

                font-size:
                    11px;
            }


            .page {

                width:
                    94%;

                margin:
                    25px auto 50px;
            }


            .hero {

                padding:
                    27px 23px;

                border-radius:
                    18px;
            }


            .hero h1 {

                font-size:
                    27px;
            }


            .form-card {

                padding:
                    21px;
            }


            .date-time-grid {

                grid-template-columns:
                    1fr;
            }


            .button-row {

                flex-direction:
                    column;
            }


            .btn {

                width:
                    100%;
            }

        }


        @media (max-width: 420px) {

            .service-info-top {

                flex-direction:
                    column;

                align-items:
                    flex-start;
            }

        }

    </style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar">

    <a
        href="dashboard.php"
        class="logo"
    >

        <span class="logo-icon">
            ✓
        </span>

        Book<span>Ease</span>

    </a>


    <div class="nav-links">

        <a href="dashboard.php">
            Dashboard
        </a>

        <a
            href="../services/index.php"
        >
            Services
        </a>

        <a
            href="my-bookings.php"
        >
            My Bookings
        </a>

        <a
            href="profile.php"
        >
            Profile
        </a>

        <a
            href="../logout.php"
            class="logout"
        >
            Logout
        </a>

    </div>

</nav>


<!-- =========================================================
     PAGE
========================================================= -->

<main class="page">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="hero">

        <div class="hero-content">

            <div class="hero-badge">

                <span>●</span>

                Online Booking

            </div>


            <h1>
                Book your service
            </h1>


            <p>
                Choose a service, select a convenient date and time,
                then confirm your booking in just a few steps.
            </p>

        </div>

    </section>


    <!-- =====================================================
         ERROR
    ====================================================== -->

    <?php if ($error): ?>

        <div class="alert alert-error">

            <span class="alert-icon">
                ⚠
            </span>

            <div>
                <?= e($error) ?>
            </div>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         EMPTY SERVICES
    ====================================================== -->

    <?php if (empty($services)): ?>

        <div class="empty-services">

            <strong>
                No active services are currently available.
            </strong>

            <br>

            Please ask the administrator to add or activate
            a service before making a booking.

        </div>

    <?php endif; ?>


    <!-- =====================================================
         BOOKING LAYOUT
    ====================================================== -->

    <div class="booking-layout">


        <!-- =================================================
             FORM
        ================================================== -->

        <section class="card form-card">


            <div class="card-title">

                <div class="card-title-icon">
                    ✦
                </div>

                <div>

                    <h2>
                        Booking Information
                    </h2>

                    <p>
                        Enter your preferred booking details.
                    </p>

                </div>

            </div>


            <form
                method="POST"
                action=""
                id="bookingForm"
            >


                <!-- =========================================
                     SERVICE
                ========================================== -->

                <div class="form-group">

                    <div class="form-label">

                        <label for="service_id">

                            Service
                            <span class="required">*</span>

                        </label>

                    </div>


                    <div class="service-select-wrapper">

                        <select
                            name="service_id"
                            id="service_id"
                            class="form-control"
                            required
                            <?= empty($services)
                                ? 'disabled'
                                : '' ?>
                        >

                            <option value="">
                                Select a service
                            </option>


                            <?php foreach (
                                $services as $service
                            ): ?>

                                <option
                                    value="<?= (int) $service['id'] ?>"
                                    data-name="<?= e(
                                        $service['name']
                                    ) ?>"
                                    data-price="<?= e(
                                        $service['price'] ?? 0
                                    ) ?>"
                                    data-duration="<?= e(
                                        $service['duration'] ?? ''
                                    ) ?>"
                                    data-description="<?= e(
                                        $service['description'] ?? ''
                                    ) ?>"
                                    <?= (
                                        $selectedServiceId ===
                                        (int) $service['id']
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >

                                    <?= e(
                                        $service['name']
                                    ) ?>

                                    — KSh
                                    <?= number_format(
                                        (float) (
                                            $service['price'] ?? 0
                                        ),
                                        2
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- SERVICE INFO -->

                    <div
                        id="serviceInfo"
                        class="service-info"
                    >

                        <div class="service-info-top">

                            <div
                                id="serviceName"
                                class="service-info-name"
                            ></div>

                            <div
                                id="servicePrice"
                                class="service-price"
                            ></div>

                        </div>


                        <div
                            id="serviceMeta"
                            class="service-info-bottom"
                        ></div>


                        <div
                            id="serviceDescription"
                            class="service-description"
                        ></div>

                    </div>

                </div>


                <!-- =========================================
                     DATE + TIME
                ========================================== -->

                <div class="date-time-grid">


                    <!-- DATE -->

                    <div class="form-group">

                        <div class="form-label">

                            <label for="booking_date">

                                Date
                                <span class="required">*</span>

                            </label>

                        </div>


                        <div class="input-wrapper">

                            <span class="input-icon">
                                📅
                            </span>


                            <input
                                type="date"
                                name="booking_date"
                                id="booking_date"
                                class="form-control with-icon"
                                min="<?= e($today) ?>"
                                value="<?= e(
                                    $selectedDate
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="help-text">

                            Select today or a future date.

                        </div>

                    </div>


                    <!-- TIME -->

                    <div class="form-group">

                        <div class="form-label">

                            <label for="booking_time">

                                Time
                                <span class="required">*</span>

                            </label>

                        </div>


                        <div class="input-wrapper">

                            <span class="input-icon">
                                🕐
                            </span>


                            <input
                                type="time"
                                name="booking_time"
                                id="booking_time"
                                class="form-control with-icon"
                                value="<?= e(
                                    $selectedTime
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="help-text">

                            Choose your preferred time.

                        </div>

                    </div>

                </div>


                <!-- =========================================
                     NOTES
                ========================================== -->

                <div class="form-group">

                    <div class="form-label">

                        <label for="notes">

                            Additional Notes

                        </label>

                    </div>


                    <textarea
                        name="notes"
                        id="notes"
                        class="form-control"
                        maxlength="1000"
                        placeholder="Tell us anything important about your booking..."
                    ><?= e($notes) ?></textarea>


                    <div class="help-text">

                        Optional. Maximum 1000 characters.

                    </div>

                </div>


                <!-- =========================================
                     BUTTONS
                ========================================== -->

                <div class="button-row">


                    <button
                        type="submit"
                        class="btn btn-primary"
                        id="submitButton"
                        <?= empty($services)
                            ? 'disabled'
                            : '' ?>
                    >

                        <span>
                            ✓
                        </span>

                        Confirm Booking

                    </button>


                    <a
                        href="dashboard.php"
                        class="btn btn-secondary"
                    >

                        ← Dashboard

                    </a>

                </div>


            </form>

        </section>


        <!-- =================================================
             SUMMARY
        ================================================== -->

        <aside class="card summary-card">


            <div class="summary-header">

                <h3>
                    Booking Summary
                </h3>

                <p>
                    Your selected details will appear here.
                </p>

            </div>


            <div class="summary-body">


                <!-- SERVICE -->

                <div class="summary-item">

                    <div class="summary-icon">
                        ✦
                    </div>

                    <div>

                        <div class="summary-label">
                            Service
                        </div>

                        <div
                            class="summary-value"
                            id="summaryService"
                        >
                            Not selected
                        </div>

                    </div>

                </div>


                <!-- DATE -->

                <div class="summary-item">

                    <div class="summary-icon">
                        📅
                    </div>

                    <div>

                        <div class="summary-label">
                            Date
                        </div>

                        <div
                            class="summary-value"
                            id="summaryDate"
                        >
                            Not selected
                        </div>

                    </div>

                </div>


                <!-- TIME -->

                <div class="summary-item">

                    <div class="summary-icon">
                        🕐
                    </div>

                    <div>

                        <div class="summary-label">
                            Time
                        </div>

                        <div
                            class="summary-value"
                            id="summaryTime"
                        >
                            Not selected
                        </div>

                    </div>

                </div>


                <!-- DURATION -->

                <div class="summary-item">

                    <div class="summary-icon">
                        ⏱
                    </div>

                    <div>

                        <div class="summary-label">
                            Duration
                        </div>

                        <div
                            class="summary-value"
                            id="summaryDuration"
                        >
                            —
                        </div>

                    </div>

                </div>


                <!-- PRICE -->

                <div class="summary-price">

                    <span>
                        Total Amount
                    </span>

                    <strong
                        id="summaryPrice"
                    >
                        KSh 0.00
                    </strong>

                </div>


                <div class="secure-note">

                    <span>
                        🔒
                    </span>

                    Your booking information is processed
                    securely.

                </div>

            </div>

        </aside>

    </div>


    <div class="page-note">

        BookEase · Select your service and preferred appointment time.

    </div>


</main>


<script>

/*
|--------------------------------------------------------------------------
| ELEMENTS
|--------------------------------------------------------------------------
*/

const serviceSelect =
    document.getElementById('service_id');

const serviceInfo =
    document.getElementById('serviceInfo');

const serviceName =
    document.getElementById('serviceName');

const servicePrice =
    document.getElementById('servicePrice');

const serviceMeta =
    document.getElementById('serviceMeta');

const serviceDescription =
    document.getElementById('serviceDescription');

const bookingDate =
    document.getElementById('booking_date');

const bookingTime =
    document.getElementById('booking_time');

const summaryService =
    document.getElementById('summaryService');

const summaryDate =
    document.getElementById('summaryDate');

const summaryTime =
    document.getElementById('summaryTime');

const summaryDuration =
    document.getElementById('summaryDuration');

const summaryPrice =
    document.getElementById('summaryPrice');


/*
|--------------------------------------------------------------------------
| UPDATE SERVICE
|--------------------------------------------------------------------------
*/

function updateServiceInfo() {

    if (!serviceSelect) {
        return;
    }


    const selected =
        serviceSelect.options[
            serviceSelect.selectedIndex
        ];


    if (
        !selected ||
        !selected.value
    ) {

        serviceInfo.style.display =
            'none';

        summaryService.textContent =
            'Not selected';

        summaryDuration.textContent =
            '—';

        summaryPrice.textContent =
            'KSh 0.00';

        return;
    }


    const name =
        selected.dataset.name || 'Service';


    const price =
        parseFloat(
            selected.dataset.price || '0'
        );


    const duration =
        selected.dataset.duration || '';


    const description =
        selected.dataset.description || '';


    /*
    |--------------------------------------------------------------------------
    | SERVICE INFO
    |--------------------------------------------------------------------------
    */

    serviceName.textContent =
        name;


    servicePrice.textContent =
        'KSh ' +
        price.toLocaleString(
            'en-KE',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );


    serviceMeta.innerHTML =
        duration
            ? '⏱ Duration: ' +
              escapeHtml(duration)
            : '⏱ Duration not specified';


    if (description) {

        serviceDescription.textContent =
            description;

        serviceDescription.style.display =
            'block';

    } else {

        serviceDescription.textContent =
            '';

        serviceDescription.style.display =
            'none';
    }


    serviceInfo.style.display =
        'block';


    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    summaryService.textContent =
        name;


    summaryDuration.textContent =
        duration
            ? duration
            : 'Not specified';


    summaryPrice.textContent =
        'KSh ' +
        price.toLocaleString(
            'en-KE',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        );
}


/*
|--------------------------------------------------------------------------
| DATE SUMMARY
|--------------------------------------------------------------------------
*/

function updateDateSummary() {

    if (!bookingDate.value) {

        summaryDate.textContent =
            'Not selected';

        return;
    }


    /*
    | Keep actual input value as YYYY-MM-DD.
    | Only the summary gets a readable format.
    */

    const parts =
        bookingDate.value.split('-');


    if (parts.length === 3) {

        const year =
            parts[0];

        const month =
            parts[1];

        const day =
            parts[2];


        const monthNames = [
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec'
        ];


        summaryDate.textContent =
            day +
            ' ' +
            monthNames[
                parseInt(month, 10) - 1
            ] +
            ' ' +
            year;

    } else {

        summaryDate.textContent =
            bookingDate.value;
    }
}


/*
|--------------------------------------------------------------------------
| TIME SUMMARY
|--------------------------------------------------------------------------
*/

function updateTimeSummary() {

    if (!bookingTime.value) {

        summaryTime.textContent =
            'Not selected';

        return;
    }


    /*
    | Keep actual input value as HH:MM.
    | Convert only the summary to 12-hour display.
    */

    const parts =
        bookingTime.value.split(':');


    if (parts.length === 2) {

        let hours =
            parseInt(parts[0], 10);

        const minutes =
            parts[1];

        const period =
            hours >= 12
                ? 'PM'
                : 'AM';


        hours =
            hours % 12 || 12;


        summaryTime.textContent =
            hours +
            ':' +
            minutes +
            ' ' +
            period;

    } else {

        summaryTime.textContent =
            bookingTime.value;
    }
}


/*
|--------------------------------------------------------------------------
| ESCAPE HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {

    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}


/*
|--------------------------------------------------------------------------
| EVENTS
|--------------------------------------------------------------------------
*/

if (serviceSelect) {

    serviceSelect.addEventListener(
        'change',
        updateServiceInfo
    );

    updateServiceInfo();
}


if (bookingDate) {

    bookingDate.addEventListener(
        'change',
        updateDateSummary
    );

    updateDateSummary();
}


if (bookingTime) {

    bookingTime.addEventListener(
        'change',
        updateTimeSummary
    );

    updateTimeSummary();
}


/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
|--------------------------------------------------------------------------
*/

const bookingForm =
    document.getElementById('bookingForm');

const submitButton =
    document.getElementById('submitButton');


if (bookingForm) {

    bookingForm.addEventListener(
        'submit',
        function () {

            if (submitButton) {

                submitButton.disabled =
                    true;

                submitButton.innerHTML =
                    '<span>⏳</span> Creating Booking...';
            }

        }
    );
}

</script>


</body>

</html>
