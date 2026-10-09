<?php
/**
 * Booking System
 * File: admin/confirm-booking.php
 */

session_start();

require_once "../includes/database.php";


/*
|--------------------------------------------------------------------------
| Admin authentication
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {

    header("Location: ../auth/login.php");
    exit;
}


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
        "Location: ../admin/bookings.php?error=invalid_booking"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Check booking
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        status
    FROM bookings
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {

    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );
}

$stmt->bind_param(
    "i",
    $bookingId
);

if (!$stmt->execute()) {

    $stmt->close();

    die("Unable to check booking.");
}

$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {

    $stmt->close();

    header(
        "Location: ../admin/bookings.php?error=booking_not_found"
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

if ($booking['status'] !== 'pending') {

    header(
        "Location: ../admin/booking-view.php?id="
        . $bookingId
        . "&error=not_pending"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Confirm booking
|--------------------------------------------------------------------------
|
| This version only updates the status.
| It does not require confirmed_at or updated_at
| columns in your database.
|
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE bookings
    SET status = 'confirmed'
    WHERE id = ?
    AND status = 'pending'
");

if (!$stmt) {

    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );
}

$stmt->bind_param(
    "i",
    $bookingId
);


/*
|--------------------------------------------------------------------------
| Execute confirmation
|--------------------------------------------------------------------------
*/

if ($stmt->execute()) {

    if ($stmt->affected_rows > 0) {

        $stmt->close();

        header(
            "Location: ../admin/booking-view.php?id="
            . $bookingId
            . "&confirmed=1"
        );

        exit;

    } else {

        $stmt->close();

        header(
            "Location: ../admin/booking-view.php?id="
            . $bookingId
            . "&error=confirmation_failed"
        );

        exit;
    }
}


$stmt->close();

header(
    "Location: ../admin/booking-view.php?id="
    . $bookingId
    . "&error=confirmation_failed"
);

exit;