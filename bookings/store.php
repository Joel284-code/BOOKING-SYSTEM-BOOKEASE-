<?php
/**
 * Booking System
 * File: booking/store.php
 *
 * Creates a new customer booking.
 */

session_start();

require_once "../includes/database.php";


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header("Location: ../auth/login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Only POST requests are allowed
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: create.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Current user
|--------------------------------------------------------------------------
*/

$userId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Get submitted data
|--------------------------------------------------------------------------
*/

$serviceId = (int) ($_POST['service_id'] ?? 0);

$bookingDate = trim(
    $_POST['booking_date'] ?? ''
);

$bookingTime = trim(
    $_POST['booking_time'] ?? ''
);

$notes = trim(
    $_POST['notes'] ?? ''
);


/*
|--------------------------------------------------------------------------
| Basic validation
|--------------------------------------------------------------------------
*/

if ($serviceId <= 0) {

    die("Please select a valid service.");
}

if ($bookingDate === '') {

    die("Booking date is required.");
}

if ($bookingTime === '') {

    die("Booking time is required.");
}


/*
|--------------------------------------------------------------------------
| Validate date format
|--------------------------------------------------------------------------
*/

$dateObject = DateTime::createFromFormat(
    'Y-m-d',
    $bookingDate
);

if (
    !$dateObject ||
    $dateObject->format('Y-m-d') !== $bookingDate
) {

    die("Invalid booking date.");
}


/*
|--------------------------------------------------------------------------
| Validate time format
|--------------------------------------------------------------------------
*/

$timeObject = DateTime::createFromFormat(
    'H:i',
    $bookingTime
);

if (
    !$timeObject ||
    $timeObject->format('H:i') !== $bookingTime
) {

    die("Invalid booking time.");
}


/*
|--------------------------------------------------------------------------
| Prevent past bookings
|--------------------------------------------------------------------------
*/

$bookingDateTime = DateTime::createFromFormat(
    'Y-m-d H:i',
    $bookingDate . ' ' . $bookingTime
);

$now = new DateTime();

if (!$bookingDateTime) {

    die("Invalid booking date or time.");
}

if ($bookingDateTime < $now) {

    die("You cannot make a booking in the past.");
}


/*
|--------------------------------------------------------------------------
| Validate service
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        name,
        price,
        duration
    FROM services
    WHERE id = ?
    AND status = 'active'
    LIMIT 1
");

if (!$stmt) {

    die(
        "Database error: "
        . htmlspecialchars($conn->error)
    );
}

$stmt->bind_param(
    "i",
    $serviceId
);

if (!$stmt->execute()) {

    die(
        "Unable to check service: "
        . htmlspecialchars($stmt->error)
    );
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    die("Selected service does not exist or is inactive.");
}

$service = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Check booking availability
|--------------------------------------------------------------------------
|
| Only pending and confirmed bookings block a time slot.
|
*/

$stmt = $conn->prepare("
    SELECT id
    FROM bookings
    WHERE service_id = ?
    AND booking_date = ?
    AND booking_time = ?
    AND status IN ('pending', 'confirmed')
    LIMIT 1
");

if (!$stmt) {

    die(
        "Database error: "
        . htmlspecialchars($conn->error)
    );
}

$stmt->bind_param(
    "iss",
    $serviceId,
    $bookingDate,
    $bookingTime
);

if (!$stmt->execute()) {

    die(
        "Unable to check booking availability: "
        . htmlspecialchars($stmt->error)
    );
}

$existing = $stmt->get_result();

if ($existing->num_rows > 0) {

    $stmt->close();

    die("
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport'
                  content='width=device-width, initial-scale=1.0'>
            <title>Time Slot Unavailable</title>

            <style>

                body {
                    margin: 0;
                    font-family: Arial, sans-serif;
                    background: #f1f5f9;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                }

                .card {
                    width: 90%;
                    max-width: 500px;
                    background: white;
                    padding: 35px;
                    border-radius: 12px;
                    text-align: center;
                    box-shadow: 0 10px 30px rgba(0,0,0,.08);
                }

                h2 {
                    color: #dc2626;
                    margin-bottom: 10px;
                }

                p {
                    color: #64748b;
                }

                a {
                    display: inline-block;
                    margin-top: 15px;
                    padding: 12px 20px;
                    background: #2563eb;
                    color: white;
                    text-decoration: none;
                    border-radius: 7px;
                }

            </style>
        </head>

        <body>

            <div class='card'>

                <h2>
                    Time Slot Unavailable
                </h2>

                <p>
                    The selected date and time has already been booked.
                </p>

                <a href='create.php'>
                    Choose Another Time
                </a>

            </div>

        </body>
        </html>
    ");

}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Generate unique booking reference
|--------------------------------------------------------------------------
*/

$bookingReference =
    'BK-' .
    date('Ymd') .
    '-' .
    strtoupper(
        bin2hex(
            random_bytes(4)
        )
    );


/*
|--------------------------------------------------------------------------
| Booking amount
|--------------------------------------------------------------------------
*/

$amount = (float) $service['price'];


/*
|--------------------------------------------------------------------------
| Insert booking
|--------------------------------------------------------------------------
|
| This version uses:
|
| user_id
| service_id
| booking_date
| booking_time
| amount
| notes
| status
| payment_status
|
*/

$stmt = $conn->prepare("
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

if (!$stmt) {

    die(
        "Unable to prepare booking: "
        . htmlspecialchars($conn->error)
    );
}


/*
|--------------------------------------------------------------------------
| Bind parameters
|--------------------------------------------------------------------------
*/

$stmt->bind_param(
    "siissds",
    $bookingReference,
    $userId,
    $serviceId,
    $bookingDate,
    $bookingTime,
    $amount,
    $notes
);


/*
|--------------------------------------------------------------------------
| Create booking
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {

    die(
        "Unable to create booking: "
        . htmlspecialchars($stmt->error)
    );
}


$bookingId = $stmt->insert_id;

$stmt->close();


/*
|--------------------------------------------------------------------------
| Redirect to booking details
|--------------------------------------------------------------------------
*/

header(
    "Location: ../customer/booking-details.php?id="
    . $bookingId
    . "&success=1"
);

exit;