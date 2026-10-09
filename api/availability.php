<?php

/**
 * BookEase Booking System
 * API: Check Booking Availability
 * File: api/availability.php
 */


/*
|--------------------------------------------------------------------------
| JSON Response
|--------------------------------------------------------------------------
*/

header('Content-Type: application/json; charset=UTF-8');


/*
|--------------------------------------------------------------------------
| Required Files
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';


/*
|--------------------------------------------------------------------------
| Response Structure
|--------------------------------------------------------------------------
*/

$response = [
    'success'  => false,
    'available' => false,
    'message'  => '',
    'data'     => []
];


try {

    /*
    |--------------------------------------------------------------------------
    | Only GET Requests
    |--------------------------------------------------------------------------
    */

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

        http_response_code(405);

        throw new Exception(
            'Only GET requests are allowed.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Get Parameters
    |--------------------------------------------------------------------------
    */

    $date = trim(
        $_GET['date'] ?? ''
    );

    $serviceId = (int) (
        $_GET['service_id'] ?? 0
    );


    /*
    |--------------------------------------------------------------------------
    | Validate Date
    |--------------------------------------------------------------------------
    */

    if ($date === '') {

        throw new Exception(
            'Booking date is required.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Service
    |--------------------------------------------------------------------------
    */

    if ($serviceId <= 0) {

        throw new Exception(
            'Service ID is required.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Date Format
    |--------------------------------------------------------------------------
    */

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $date
    );

    $dateErrors =
        DateTime::getLastErrors();

    /*
    | PHP may return false instead of an array
    | when there are no errors.
    */

    if (
        !$dateObject ||
        (
            is_array($dateErrors) &&
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
    | Prevent Past Dates
    |--------------------------------------------------------------------------
    */

    $today = new DateTime(
        date('Y-m-d')
    );

    if ($dateObject < $today) {

        throw new Exception(
            'Bookings cannot be made for past dates.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    */

    $database = new Database();

    $conn = $database->getConnection();


    /*
    |--------------------------------------------------------------------------
    | Check Service
    |--------------------------------------------------------------------------
    |
    | We also get the service duration.
    |
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

    $serviceStmt->execute([
        $serviceId
    ]);

    $service = $serviceStmt->fetch(
        PDO::FETCH_ASSOC
    );


    /*
    |--------------------------------------------------------------------------
    | Service Not Found
    |--------------------------------------------------------------------------
    */

    if (!$service) {

        throw new Exception(
            'The selected service was not found or is inactive.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Service Duration
    |--------------------------------------------------------------------------
    */

    $duration = (int) (
        $service['duration'] ?? 60
    );


    /*
    | Prevent invalid duration
    */

    if ($duration <= 0) {

        $duration = 60;
    }


    /*
    |--------------------------------------------------------------------------
    | Business Hours
    |--------------------------------------------------------------------------
    |
    | Default:
    |
    | Opening: 08:00
    | Closing: 17:00
    |
    */

    $businessStart = 8 * 60;

    $businessEnd = 17 * 60;


    /*
    |--------------------------------------------------------------------------
    | Generate Slots
    |--------------------------------------------------------------------------
    */

    $slots = [];


    for (
        $minutes = $businessStart;

        $minutes + $duration <= $businessEnd;

        $minutes += $duration
    ) {


        /*
        |--------------------------------------------------------------------------
        | Calculate Start Time
        |--------------------------------------------------------------------------
        */

        $hours =
            floor($minutes / 60);

        $mins =
            $minutes % 60;


        $time = sprintf(
            '%02d:%02d:00',
            $hours,
            $mins
        );


        /*
        |--------------------------------------------------------------------------
        | Display Time
        |--------------------------------------------------------------------------
        */

        $displayTime = date(
            'h:i A',
            strtotime($time)
        );


        /*
        |--------------------------------------------------------------------------
        | Calculate End Time
        |--------------------------------------------------------------------------
        */

        $endMinutes =
            $minutes + $duration;

        $endHours =
            floor($endMinutes / 60);

        $endMins =
            $endMinutes % 60;


        $endTime = sprintf(
            '%02d:%02d:00',
            $endHours,
            $endMins
        );


        /*
        |--------------------------------------------------------------------------
        | Check Existing Booking
        |--------------------------------------------------------------------------
        |
        | We check bookings for the same
        | service, date and time.
        |
        */

        $bookingStmt = $conn->prepare("
            SELECT id

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


        $bookingStmt->execute([
            $serviceId,
            $date,
            $time
        ]);


        $existingBooking =
            $bookingStmt->fetch(
                PDO::FETCH_ASSOC
            );


        $booked =
            $existingBooking !== false;


        /*
        |--------------------------------------------------------------------------
        | Add Slot
        |--------------------------------------------------------------------------
        */

        $slots[] = [

            'time' =>
                $time,

            'end_time' =>
                $endTime,

            'display_time' =>
                $displayTime,

            'available' =>
                !$booked

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Available Slots
    |--------------------------------------------------------------------------
    */

    $availableSlots = array_filter(
        $slots,
        function ($slot) {

            return $slot['available'] === true;
        }
    );


    $availableCount =
        count($availableSlots);


    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    $response['success'] = true;

    $response['available'] =
        $availableCount > 0;


    if ($availableCount > 0) {

        $response['message'] =
            'Available booking slots found.';

    } else {

        $response['message'] =
            'No booking slots are available for this date.';
    }


    $response['data'] = [

        'service' => [

            'id' =>
                (int) $service['id'],

            'name' =>
                $service['name'],

            'price' =>
                (float) $service['price'],

            'duration' =>
                $duration

        ],

        'date' =>
            $date,

        'business_hours' => [

            'start' =>
                '08:00:00',

            'end' =>
                '17:00:00'

        ],

        'available_count' =>
            $availableCount,

        'slots' =>
            array_values($slots)

    ];


} catch (PDOException $e) {


    /*
    |--------------------------------------------------------------------------
    | Database Error
    |--------------------------------------------------------------------------
    */

    http_response_code(500);

    $response['success'] =
        false;

    $response['available'] =
        false;

    $response['message'] =
        'A database error occurred while checking availability.';

    $response['data'] = [];


} catch (Exception $e) {


    /*
    |--------------------------------------------------------------------------
    | General Error
    |--------------------------------------------------------------------------
    */

    if (
        http_response_code() === 200
    ) {

        http_response_code(400);
    }


    $response['success'] =
        false;

    $response['available'] =
        false;

    $response['message'] =
        $e->getMessage();

    $response['data'] = [];
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