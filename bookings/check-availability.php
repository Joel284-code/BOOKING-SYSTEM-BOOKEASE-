<?php
/**
 * Booking System API
 * File: api/check-availability.php
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/database.php';

$response = [
    'success' => false,
    'available' => false,
    'message' => '',
    'data' => null
];

try {

    /*
    |--------------------------------------------------------------------------
    | Get request values
    |--------------------------------------------------------------------------
    */

    $serviceId = (int) ($_GET['service_id'] ?? 0);

    $date = trim(
        $_GET['date']
        ?? $_GET['booking_date']
        ?? ''
    );

    $time = trim(
        $_GET['time']
        ?? $_GET['booking_time']
        ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Validate required fields
    |--------------------------------------------------------------------------
    */

    if ($serviceId <= 0) {

        throw new Exception(
            'Service ID is required.'
        );
    }

    if ($date === '') {

        throw new Exception(
            'Booking date is required.'
        );
    }

    if ($time === '') {

        throw new Exception(
            'Booking time is required.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate date format
    |--------------------------------------------------------------------------
    */

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $date
    );

    $dateErrors = DateTime::getLastErrors();

    if (
        !$dateObject ||
        (
            $dateErrors !== false &&
            (
                $dateErrors['warning_count'] > 0 ||
                $dateErrors['error_count'] > 0
            )
        ) ||
        $dateObject->format('Y-m-d') !== $date
    ) {

        throw new Exception(
            'Invalid booking date. Use YYYY-MM-DD.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Prevent past dates
    |--------------------------------------------------------------------------
    */

    $today = new DateTime('today');

    if ($dateObject < $today) {

        throw new Exception(
            'Bookings cannot be made for past dates.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate time
    |--------------------------------------------------------------------------
    */

    $timeObject = DateTime::createFromFormat(
        'H:i',
        $time
    );

    if (!$timeObject) {

        $timeObject = DateTime::createFromFormat(
            'H:i:s',
            $time
        );
    }

    if (!$timeObject) {

        throw new Exception(
            'Invalid booking time.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Normalize time
    |--------------------------------------------------------------------------
    */

    $bookingTime = $timeObject->format('H:i:s');


    /*
    |--------------------------------------------------------------------------
    | Connect to database
    |--------------------------------------------------------------------------
    */

    $db = new Database();

    $conn = $db->getConnection();


    /*
    |--------------------------------------------------------------------------
    | Check whether service exists and is active
    |--------------------------------------------------------------------------
    */

    $serviceStmt = $conn->prepare("
        SELECT
            id,
            name,
            price,
            duration,
            status
        FROM services
        WHERE id = ?
        AND status = 'active'
        LIMIT 1
    ");

    if (!$serviceStmt) {

        throw new Exception(
            'Unable to check service.'
        );
    }

    $serviceStmt->execute([
        $serviceId
    ]);

    $service = $serviceStmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$service) {

        throw new Exception(
            'Selected service was not found or is inactive.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check existing booking
    |--------------------------------------------------------------------------
    |
    | Cancelled and rejected bookings do not block the time slot.
    |
    */

    $bookingStmt = $conn->prepare("
        SELECT
            id,
            status
        FROM bookings
        WHERE service_id = ?
        AND booking_date = ?
        AND booking_time = ?
        AND status NOT IN (
            'cancelled',
            'rejected'
        )
        LIMIT 1
    ");

    if (!$bookingStmt) {

        throw new Exception(
            'Unable to check booking availability.'
        );
    }

    $bookingStmt->execute([
        $serviceId,
        $date,
        $bookingTime
    ]);

    $existingBooking =
        $bookingStmt->fetch(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | Determine availability
    |--------------------------------------------------------------------------
    */

    $available =
        !$existingBooking;


    /*
    |--------------------------------------------------------------------------
    | Return response
    |--------------------------------------------------------------------------
    */

    $response['success'] = true;

    $response['available'] = $available;

    if ($available) {

        $response['message'] =
            'The selected time slot is available.';

    } else {

        $response['message'] =
            'The selected time slot is already booked.';
    }


    $response['data'] = [

        'service_id' =>
            (int) $service['id'],

        'service_name' =>
            $service['name'],

        'price' =>
            (float) $service['price'],

        'duration' =>
            (int) $service['duration'],

        'date' =>
            $date,

        'time' =>
            $bookingTime,

        'available' =>
            $available

    ];


} catch (Throwable $e) {

    http_response_code(400);

    $response['success'] = false;

    $response['available'] = false;

    $response['message'] =
        $e->getMessage();

    $response['data'] = null;
}


/*
|--------------------------------------------------------------------------
| Output JSON
|--------------------------------------------------------------------------
*/

echo json_encode(
    $response,
    JSON_PRETTY_PRINT |
    JSON_UNESCAPED_SLASHES
);

exit;