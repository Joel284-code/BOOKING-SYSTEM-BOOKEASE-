<?php
/**
 * BookEase Booking System API
 * File: api/bookings.php
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    */

    $db = new Database();
    $conn = $db->getConnection();

    if (!$conn instanceof PDO) {
        throw new Exception('Database connection failed.');
    }

    $method = strtoupper($_SERVER['REQUEST_METHOD']);


    /*
    |--------------------------------------------------------------------------
    | GET BOOKINGS
    |--------------------------------------------------------------------------
    */

    if ($method === 'GET') {

        $id = isset($_GET['id'])
            ? (int) $_GET['id']
            : 0;

        $status = trim(
            $_GET['status'] ?? ''
        );


        /*
        |----------------------------------------------------------------------
        | Get Single Booking
        |----------------------------------------------------------------------
        */

        if ($id > 0) {

            $stmt = $conn->prepare("
                SELECT
                    b.id,
                    b.user_id,
                    b.service_id,
                    b.booking_date,
                    b.booking_time,
                    b.total_amount,
                    b.notes,
                    b.status,
                    b.created_at,

                    u.name AS customer_name,
                    u.email AS customer_email,
                    u.phone AS customer_phone,

                    s.name AS service_name,
                    s.description AS service_description,
                    s.price AS service_price,
                    s.duration AS service_duration

                FROM bookings b

                LEFT JOIN users u
                    ON b.user_id = u.id

                LEFT JOIN services s
                    ON b.service_id = s.id

                WHERE b.id = ?

                LIMIT 1
            ");

            $stmt->execute([$id]);

            $booking = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$booking) {

                throw new Exception(
                    'Booking not found.'
                );
            }

            $response['success'] = true;

            $response['message'] =
                'Booking retrieved successfully.';

            $response['data'] = $booking;
        }


        /*
        |----------------------------------------------------------------------
        | Get All Bookings
        |----------------------------------------------------------------------
        */

        else {

            $sql = "
                SELECT
                    b.id,
                    b.user_id,
                    b.service_id,
                    b.booking_date,
                    b.booking_time,
                    b.total_amount,
                    b.notes,
                    b.status,
                    b.created_at,

                    u.name AS customer_name,
                    u.email AS customer_email,
                    u.phone AS customer_phone,

                    s.name AS service_name,
                    s.price AS service_price,
                    s.duration AS service_duration

                FROM bookings b

                LEFT JOIN users u
                    ON b.user_id = u.id

                LEFT JOIN services s
                    ON b.service_id = s.id
            ";

            $params = [];

            if ($status !== '') {

                $allowedStatuses = [
                    'pending',
                    'confirmed',
                    'completed',
                    'cancelled',
                    'rejected'
                ];

                if (!in_array(
                    $status,
                    $allowedStatuses,
                    true
                )) {

                    throw new Exception(
                        'Invalid booking status.'
                    );
                }

                $sql .= "
                    WHERE b.status = ?
                ";

                $params[] = $status;
            }

            $sql .= "
                ORDER BY b.created_at DESC
            ";

            $stmt = $conn->prepare($sql);

            $stmt->execute($params);

            $bookings = $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

            $response['success'] = true;

            $response['message'] =
                'Bookings retrieved successfully.';

            $response['data'] = $bookings;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE BOOKING
    |--------------------------------------------------------------------------
    */

    elseif ($method === 'POST') {

        $rawInput =
            file_get_contents('php://input');

        $input = json_decode(
            $rawInput,
            true
        );

        if (!is_array($input)) {
            $input = $_POST;
        }


        /*
        |----------------------------------------------------------------------
        | Customer ID
        |----------------------------------------------------------------------
        */

        $userId = isset($input['user_id'])
            ? (int) $input['user_id']
            : 0;


        /*
        |----------------------------------------------------------------------
        | Allow logged-in user to create booking
        |----------------------------------------------------------------------
        */

        if ($userId <= 0 && isset($_SESSION['user_id'])) {

            $userId = (int) $_SESSION['user_id'];
        }


        $serviceId = isset($input['service_id'])
            ? (int) $input['service_id']
            : 0;

        $bookingDate = trim(
            $input['booking_date'] ?? ''
        );

        $bookingTime = trim(
            $input['booking_time'] ?? ''
        );

        $notes = trim(
            $input['notes'] ?? ''
        );


        /*
        |----------------------------------------------------------------------
        | Validate Required Fields
        |----------------------------------------------------------------------
        */

        if ($userId <= 0) {

            throw new Exception(
                'Customer/user ID is required.'
            );
        }

        if ($serviceId <= 0) {

            throw new Exception(
                'Service ID is required.'
            );
        }

        if ($bookingDate === '') {

            throw new Exception(
                'Booking date is required.'
            );
        }

        if ($bookingTime === '') {

            throw new Exception(
                'Booking time is required.'
            );
        }


        /*
        |----------------------------------------------------------------------
        | Validate Date
        |----------------------------------------------------------------------
        */

        $dateObject = DateTime::createFromFormat(
            'Y-m-d',
            $bookingDate
        );

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $bookingDate
        ) {

            throw new Exception(
                'Invalid booking date.'
            );
        }


        if ($dateObject < new DateTime('today')) {

            throw new Exception(
                'Bookings cannot be made for past dates.'
            );
        }


        /*
        |----------------------------------------------------------------------
        | Validate Time
        |----------------------------------------------------------------------
        */

        $timeObject = DateTime::createFromFormat(
            'H:i',
            $bookingTime
        );

        if (!$timeObject) {

            $timeObject = DateTime::createFromFormat(
                'H:i:s',
                $bookingTime
            );
        }

        if (!$timeObject) {

            throw new Exception(
                'Invalid booking time.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check User
        |--------------------------------------------------------------------------
        */

        $userStmt = $conn->prepare("
            SELECT id, name, email, phone, role
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $userStmt->execute([
            $userId
        ]);

        $user = $userStmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$user) {

            throw new Exception(
                'Customer account not found.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check Service
        |--------------------------------------------------------------------------
        */

        $serviceStmt = $conn->prepare("
            SELECT
                id,
                name,
                description,
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

        if (!$service) {

            throw new Exception(
                'Selected service is unavailable.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Normalize Time
        |--------------------------------------------------------------------------
        */

        $normalizedTime =
            $timeObject->format('H:i:s');


        /*
        |--------------------------------------------------------------------------
        | Check Existing Booking
        |--------------------------------------------------------------------------
        */

        $availabilityStmt = $conn->prepare("
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

        $availabilityStmt->execute([
            $serviceId,
            $bookingDate,
            $normalizedTime
        ]);

        if ($availabilityStmt->fetch()) {

            throw new Exception(
                'The selected time slot is already booked.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Create Booking
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            INSERT INTO bookings
            (
                user_id,
                service_id,
                booking_date,
                booking_time,
                total_amount,
                notes,
                status,
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
                'pending',
                NOW()
            )
        ");

        $stmt->execute([
            $userId,
            $serviceId,
            $bookingDate,
            $normalizedTime,
            $service['price'],
            $notes
        ]);

        $bookingId =
            $conn->lastInsertId();


        /*
        |--------------------------------------------------------------------------
        | Create Notification
        |--------------------------------------------------------------------------
        */

        try {

            $notificationStmt = $conn->prepare("
                INSERT INTO notifications
                (
                    user_id,
                    title,
                    message,
                    type,
                    is_read,
                    created_at
                )

                VALUES
                (
                    ?,
                    ?,
                    ?,
                    'booking',
                    0,
                    NOW()
                )
            ");

            $notificationStmt->execute([
                $userId,
                'Booking Created',
                'Your booking for ' .
                $service['name'] .
                ' on ' .
                $bookingDate .
                ' at ' .
                date(
                    'h:i A',
                    strtotime($normalizedTime)
                ) .
                ' has been created successfully.'
            ]);

        } catch (Exception $notificationError) {

            /*
             * Do not cancel the booking if
             * notification creation fails.
             */
        }


        /*
        |--------------------------------------------------------------------------
        | Success Response
        |--------------------------------------------------------------------------
        */

        $response['success'] = true;

        $response['message'] =
            'Booking created successfully.';

        $response['data'] = [
            'booking_id' => $bookingId,
            'user_id' => $userId,
            'customer' => $user['name'],
            'service_id' => $serviceId,
            'service' => $service['name'],
            'date' => $bookingDate,
            'time' => $normalizedTime,
            'display_time' => date(
                'h:i A',
                strtotime($normalizedTime)
            ),
            'amount' => $service['price'],
            'status' => 'pending'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE BOOKING
    |--------------------------------------------------------------------------
    */

    elseif ($method === 'PUT') {

        $input = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($input)) {

            throw new Exception(
                'Invalid JSON request.'
            );
        }

        $bookingId = isset($input['id'])
            ? (int) $input['id']
            : 0;

        if ($bookingId <= 0) {

            throw new Exception(
                'Booking ID is required.'
            );
        }


        $allowedStatuses = [
            'pending',
            'confirmed',
            'completed',
            'cancelled',
            'rejected'
        ];

        $status = trim(
            $input['status'] ?? ''
        );

        $notes = trim(
            $input['notes'] ?? ''
        );


        if (
            $status !== '' &&
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {

            throw new Exception(
                'Invalid booking status.'
            );
        }


        /*
        |----------------------------------------------------------------------
        | Check Booking Exists
        |----------------------------------------------------------------------
        */

        $checkStmt = $conn->prepare("
            SELECT id, user_id, status
            FROM bookings
            WHERE id = ?
            LIMIT 1
        ");

        $checkStmt->execute([
            $bookingId
        ]);

        $existingBooking =
            $checkStmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$existingBooking) {

            throw new Exception(
                'Booking not found.'
            );
        }


        /*
        |----------------------------------------------------------------------
        | Update Booking
        |----------------------------------------------------------------------
        */

        if ($status !== '') {

            $stmt = $conn->prepare("
                UPDATE bookings

                SET
                    status = ?,
                    notes = ?

                WHERE id = ?
            ");

            $stmt->execute([
                $status,
                $notes,
                $bookingId
            ]);

        } else {

            $stmt = $conn->prepare("
                UPDATE bookings

                SET notes = ?

                WHERE id = ?
            ");

            $stmt->execute([
                $notes,
                $bookingId
            ]);
        }


        /*
        |----------------------------------------------------------------------
        | Notification
        |----------------------------------------------------------------------
        */

        if ($status !== '') {

            try {

                $notificationStmt =
                    $conn->prepare("
                        INSERT INTO notifications
                        (
                            user_id,
                            title,
                            message,
                            type,
                            is_read,
                            created_at
                        )

                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            'booking',
                            0,
                            NOW()
                        )
                    ");

                $notificationStmt->execute([
                    $existingBooking['user_id'],
                    'Booking Status Updated',
                    'Your booking #' .
                    $bookingId .
                    ' status has been changed to ' .
                    ucfirst($status) .
                    '.'
                ]);

            } catch (Exception $notificationError) {
                // Ignore notification failure.
            }
        }


        $response['success'] = true;

        $response['message'] =
            'Booking updated successfully.';

        $response['data'] = [
            'booking_id' => $bookingId,
            'status' => $status !== ''
                ? $status
                : $existingBooking['status']
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE / CANCEL BOOKING
    |--------------------------------------------------------------------------
    */

    elseif ($method === 'DELETE') {

        $rawInput =
            file_get_contents('php://input');

        $input = json_decode(
            $rawInput,
            true
        );

        if (!is_array($input)) {

            parse_str(
                $rawInput,
                $input
            );
        }

        $bookingId = isset($input['id'])
            ? (int) $input['id']
            : 0;

        if ($bookingId <= 0) {

            throw new Exception(
                'Booking ID is required.'
            );
        }


        /*
        |----------------------------------------------------------------------
        | Check Booking
        |----------------------------------------------------------------------
        */

        $checkStmt = $conn->prepare("
            SELECT id, user_id, status
            FROM bookings
            WHERE id = ?
            LIMIT 1
        ");

        $checkStmt->execute([
            $bookingId
        ]);

        $booking =
            $checkStmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$booking) {

            throw new Exception(
                'Booking not found.'
            );
        }


        if ($booking['status'] === 'completed') {

            throw new Exception(
                'Completed bookings cannot be cancelled.'
            );
        }


        /*
        |----------------------------------------------------------------------
        | Soft Cancel
        |----------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            UPDATE bookings
            SET status = 'cancelled'
            WHERE id = ?
        ");

        $stmt->execute([
            $bookingId
        ]);


        /*
        |----------------------------------------------------------------------
        | Notification
        |----------------------------------------------------------------------
        */

        try {

            $notificationStmt =
                $conn->prepare("
                    INSERT INTO notifications
                    (
                        user_id,
                        title,
                        message,
                        type,
                        is_read,
                        created_at
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        'booking',
                        0,
                        NOW()
                    )
                ");

            $notificationStmt->execute([
                $booking['user_id'],
                'Booking Cancelled',
                'Your booking #' .
                $bookingId .
                ' has been cancelled.'
            ]);

        } catch (Exception $notificationError) {
            // Ignore notification failure.
        }


        $response['success'] = true;

        $response['message'] =
            'Booking cancelled successfully.';

        $response['data'] = [
            'booking_id' => $bookingId,
            'status' => 'cancelled'
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Unsupported Method
    |--------------------------------------------------------------------------
    */

    else {

        http_response_code(405);

        throw new Exception(
            'HTTP method not allowed.'
        );
    }


} catch (PDOException $e) {

    http_response_code(500);

    $response['success'] = false;

    $response['message'] =
        'Database error: ' .
        $e->getMessage();

} catch (Exception $e) {

    if (http_response_code() < 400) {
        http_response_code(400);
    }

    $response['success'] = false;

    $response['message'] =
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| JSON Response
|--------------------------------------------------------------------------
*/

echo json_encode(
    $response,
    JSON_PRETTY_PRINT |
    JSON_UNESCAPED_UNICODE
);

exit;