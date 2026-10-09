<?php
/**
 * Booking System
 * File: customer/cancel-booking.php
 */

session_start();

require_once "../includes/database.php";

/*
|--------------------------------------------------------------------------
| Check login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header("Location: ../auth/login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Get booking ID
|--------------------------------------------------------------------------
*/

$bookingId = (int) (
    $_POST['booking_id']
    ?? $_GET['id']
    ?? 0
);

if ($bookingId <= 0) {

    header(
        "Location: ../customer/my-bookings.php?error=invalid_booking"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Check booking ownership
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Your bookings table uses customer_id,
| not user_id.
|
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        customer_id,
        status,
        booking_date,
        booking_time
    FROM bookings
    WHERE id = ?
    AND customer_id = ?
    LIMIT 1
");

if (!$stmt) {

    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );
}

$stmt->bind_param(
    "ii",
    $bookingId,
    $userId
);

if (!$stmt->execute()) {

    $stmt->close();

    die("Unable to verify booking.");
}

$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {

    $stmt->close();

    header(
        "Location: ../customer/my-bookings.php?error=booking_not_found"
    );

    exit;
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
    strtolower($booking['status']),
    $allowedStatuses,
    true
)) {

    header(
        "Location: ../customer/my-bookings.php?error=cannot_cancel"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Cancel booking
|--------------------------------------------------------------------------
|
| Use customer_id instead of user_id.
|
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE bookings
    SET
        status = 'cancelled',
        cancelled_at = NOW(),
        updated_at = NOW()
    WHERE id = ?
    AND customer_id = ?
    AND status IN ('pending', 'confirmed')
");

if (!$stmt) {

    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );
}

$stmt->bind_param(
    "ii",
    $bookingId,
    $userId
);


/*
|--------------------------------------------------------------------------
| Execute cancellation
|--------------------------------------------------------------------------
*/

if ($stmt->execute()) {

    if ($stmt->affected_rows > 0) {

        $stmt->close();

        header(
            "Location: ../customer/my-bookings.php?cancelled=1"
        );

        exit;

    } else {

        $stmt->close();

        header(
            "Location: ../customer/my-bookings.php?error=cancellation_failed"
        );

        exit;
    }
}


$stmt->close();

header(
    "Location: ../customer/my-bookings.php?error=cancellation_failed"
);

exit;