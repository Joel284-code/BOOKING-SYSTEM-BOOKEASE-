<?php

/*
|--------------------------------------------------------------------------
| Customer Cancel Booking
| File: customer/cancel-booking.php
|--------------------------------------------------------------------------
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

    header("Location: my-bookings.php?error=invalid_booking");
    exit;
}


/*
|--------------------------------------------------------------------------
| Find booking and verify ownership
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        user_id,
        status
    FROM bookings
    WHERE id = ?
    AND user_id = ?
    LIMIT 1
");


if (!$stmt) {

    header("Location: my-bookings.php?error=database");
    exit;
}


$stmt->bind_param(
    "ii",
    $bookingId,
    $userId
);


if (!$stmt->execute()) {

    header("Location: my-bookings.php?error=database");
    exit;
}


$result = $stmt->get_result();


if ($result->num_rows === 0) {

    header("Location: my-bookings.php?error=not_found");
    exit;
}


$booking = $result->fetch_assoc();


/*
|--------------------------------------------------------------------------
| Check booking status
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    'pending',
    'confirmed'
];


if (
    !in_array(
        $booking['status'],
        $allowedStatuses,
        true
    )
) {

    header(
        "Location: booking-details.php?id="
        . $bookingId
        . "&error=cannot_cancel"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Cancel booking
|--------------------------------------------------------------------------
|
| We use a soft cancellation.
| The booking remains in the database.
|
*/

$stmt = $conn->prepare("
    UPDATE bookings
    SET
        status = 'cancelled',
        updated_at = NOW()
    WHERE id = ?
    AND user_id = ?
");


if (!$stmt) {

    header(
        "Location: booking-details.php?id="
        . $bookingId
        . "&error=database"
    );

    exit;
}


$stmt->bind_param(
    "ii",
    $bookingId,
    $userId
);


if ($stmt->execute()) {

    /*
    |--------------------------------------------------------------------------
    | Check whether the booking was actually updated
    |--------------------------------------------------------------------------
    */

    if ($stmt->affected_rows > 0) {

        header(
            "Location: booking-details.php?id="
            . $bookingId
            . "&cancelled=1"
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Cancellation failed
|--------------------------------------------------------------------------
*/

header(
    "Location: booking-details.php?id="
    . $bookingId
    . "&error=cancel_failed"
);

exit;