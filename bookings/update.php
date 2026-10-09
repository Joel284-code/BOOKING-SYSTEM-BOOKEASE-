<?php
/**
 * Booking System
 * File: booking/update.php
 *
 * Allows a customer to reschedule their booking.
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

    header("Location: ../customer/my-bookings.php");
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
| Get submitted information
|--------------------------------------------------------------------------
*/

$bookingId = (int) (
    $_POST['booking_id'] ?? 0
);

$bookingDate = trim(
    $_POST['booking_date'] ?? ''
);

$bookingTime = trim(
    $_POST['booking_time'] ?? ''
);


/*
|--------------------------------------------------------------------------
| Basic validation
|--------------------------------------------------------------------------
*/

if ($bookingId <= 0) {

    die("Invalid booking ID.");
}

if ($bookingDate === '') {

    die("Booking date is required.");
}

if ($bookingTime === '') {

    die("Booking time is required.");
}


/*
|--------------------------------------------------------------------------
| Validate date
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
| Validate time
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
| Create booking date/time
|--------------------------------------------------------------------------
*/

$bookingDateTime = DateTime::createFromFormat(
    'Y-m-d H:i',
    $bookingDate . ' ' . $bookingTime
);

if (!$bookingDateTime) {

    die("Invalid booking date or time.");
}


/*
|--------------------------------------------------------------------------
| Prevent past bookings
|--------------------------------------------------------------------------
*/

$now = new DateTime();

if ($bookingDateTime < $now) {

    die("You cannot reschedule a booking to a past date or time.");
}


/*
|--------------------------------------------------------------------------
| Find customer's booking
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        service_id,
        booking_date,
        booking_time,
        status
    FROM bookings
    WHERE id = ?
    AND user_id = ?
    LIMIT 1
");

if (!$stmt) {

    die(
        "Database error: "
        . htmlspecialchars($conn->error)
    );
}

$stmt->bind_param(
    "ii",
    $bookingId,
    $userId
);

if (!$stmt->execute()) {

    die(
        "Unable to find booking: "
        . htmlspecialchars($stmt->error)
    );
}

$result = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Check booking exists
|--------------------------------------------------------------------------
*/

if ($result->num_rows === 0) {

    $stmt->close();

    die("Booking not found.");
}

$booking = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Check booking status
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'pending',
    'confirmed'
];

if (!in_array(
    $booking['status'],
    $allowedStatuses,
    true
)) {

    die(
        "This booking cannot be updated because its current status is "
        . htmlspecialchars($booking['status'])
        . "."
    );
}


/*
|--------------------------------------------------------------------------
| Check service still exists and is active
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id
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
    $booking['service_id']
);

if (!$stmt->execute()) {

    die(
        "Unable to verify service: "
        . htmlspecialchars($stmt->error)
    );
}

$serviceResult = $stmt->get_result();

if ($serviceResult->num_rows === 0) {

    $stmt->close();

    die("The service associated with this booking is no longer available.");
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Check whether the new slot is already booked
|--------------------------------------------------------------------------
|
| The current booking is excluded using:
|
| id != ?
|
*/

$stmt = $conn->prepare("
    SELECT id
    FROM bookings
    WHERE service_id = ?
    AND booking_date = ?
    AND booking_time = ?
    AND id != ?
    AND status IN ('pending', 'confirmed')
    LIMIT 1
");

if (!$stmt) {

    die(
        "Database error: "
        . htmlspecialchars($conn->error)
    );
}

$serviceId = (int) $booking['service_id'];

$stmt->bind_param(
    "issi",
    $serviceId,
    $bookingDate,
    $bookingTime,
    $bookingId
);

if (!$stmt->execute()) {

    die(
        "Unable to check availability: "
        . htmlspecialchars($stmt->error)
    );
}

$result = $stmt->get_result();


if ($result->num_rows > 0) {

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

                * {
                    box-sizing: border-box;
                }

                body {
                    margin: 0;
                    font-family: Arial, sans-serif;
                    background: #f1f5f9;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .card {
                    width: 90%;
                    max-width: 500px;
                    background: white;
                    padding: 35px;
                    border-radius: 12px;
                    text-align: center;
                    box-shadow: 0 10px 35px rgba(0,0,0,.08);
                }

                h2 {
                    color: #dc2626;
                    margin-bottom: 10px;
                }

                p {
                    color: #64748b;
                    line-height: 1.6;
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

                a:hover {
                    background: #1d4ed8;
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
                    Please choose another available time.
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
| Update booking
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE bookings
    SET
        booking_date = ?,
        booking_time = ?,
        updated_at = NOW()
    WHERE id = ?
    AND user_id = ?
");

if (!$stmt) {

    die(
        "Unable to prepare update: "
        . htmlspecialchars($conn->error)
    );
}

$stmt->bind_param(
    "ssii",
    $bookingDate,
    $bookingTime,
    $bookingId,
    $userId
);


/*
|--------------------------------------------------------------------------
| Execute update
|--------------------------------------------------------------------------
*/

if (!$stmt->execute()) {

    die(
        "Unable to update booking: "
        . htmlspecialchars($stmt->error)
    );
}


/*
|--------------------------------------------------------------------------
| Check whether anything was actually updated
|--------------------------------------------------------------------------
*/

if ($stmt->affected_rows === 0) {

    $stmt->close();

    /*
     * affected_rows can be 0 when the user submits
     * exactly the same date and time.
     * This is not necessarily an error.
     */

    header(
        "Location: ../customer/booking-details.php?id="
        . $bookingId
        . "&updated=1"
    );

    exit;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Success redirect
|--------------------------------------------------------------------------
*/

header(
    "Location: ../customer/booking-details.php?id="
    . $bookingId
    . "&updated=1"
);

exit;