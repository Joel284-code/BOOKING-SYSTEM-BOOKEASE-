<?php
/**
 * BookEase Booking System API
 * File: api/notifications.php
 *
 * MySQLi version
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/

$response = [
    'success' => false,
    'message' => '',
    'data' => null
];


/*
|--------------------------------------------------------------------------
| Make sure database connection exists
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !($conn instanceof mysqli)) {

    http_response_code(500);

    $response['message'] =
        'Database connection is not available.';

    echo json_encode(
        $response,
        JSON_PRETTY_PRINT
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Get request method
|--------------------------------------------------------------------------
*/

$method = $_SERVER['REQUEST_METHOD'];


/*
|--------------------------------------------------------------------------
| Helper: Read JSON input
|--------------------------------------------------------------------------
*/

function getJsonInput()
{
    $raw = file_get_contents('php://input');

    if (!$raw) {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}


/*
|--------------------------------------------------------------------------
| GET - Get notifications
|--------------------------------------------------------------------------
|
| Example:
| /booking-system/api/notifications.php?user_id=1
|
*/

if ($method === 'GET') {

    try {

        $userId = isset($_GET['user_id'])
            ? (int) $_GET['user_id']
            : 0;

        if ($userId <= 0) {

            throw new Exception(
                'User ID is required.'
            );
        }


        $stmt = $conn->prepare(
            "SELECT
                id,
                user_id,
                title,
                message,
                type,
                is_read,
                created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC"
        );


        if (!$stmt) {

            throw new Exception(
                'Failed to prepare notification query: '
                . $conn->error
            );
        }


        $stmt->bind_param(
            "i",
            $userId
        );


        if (!$stmt->execute()) {

            throw new Exception(
                'Failed to retrieve notifications: '
                . $stmt->error
            );
        }


        $result = $stmt->get_result();

        $notifications = [];


        while ($row = $result->fetch_assoc()) {

            $notifications[] = $row;
        }


        $stmt->close();


        $response['success'] = true;

        $response['message'] =
            'Notifications retrieved successfully.';

        $response['data'] = $notifications;


    } catch (Exception $e) {

        http_response_code(400);

        $response['message'] =
            $e->getMessage();
    }


    echo json_encode(
        $response,
        JSON_PRETTY_PRINT
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| POST - Create notification
|--------------------------------------------------------------------------
|
| JSON example:
|
| {
|   "user_id": 1,
|   "title": "Booking Confirmed",
|   "message": "Your booking has been confirmed.",
|   "type": "booking"
| }
|
*/

if ($method === 'POST') {

    try {

        $input = getJsonInput();


        /*
         * Allow normal POST form data
         */

        if (empty($input)) {

            $input = $_POST;
        }


        $userId = isset($input['user_id'])
            ? (int) $input['user_id']
            : 0;

        $title = trim(
            $input['title'] ?? ''
        );

        $message = trim(
            $input['message'] ?? ''
        );

        $type = trim(
            $input['type'] ?? 'general'
        );


        if ($userId <= 0) {

            throw new Exception(
                'User ID is required.'
            );
        }


        if ($title === '') {

            throw new Exception(
                'Notification title is required.'
            );
        }


        if ($message === '') {

            throw new Exception(
                'Notification message is required.'
            );
        }


        /*
         * Check that user exists
         */

        $checkUser = $conn->prepare(
            "SELECT id
             FROM users
             WHERE id = ?
             LIMIT 1"
        );


        if (!$checkUser) {

            throw new Exception(
                'Failed to prepare user query: '
                . $conn->error
            );
        }


        $checkUser->bind_param(
            "i",
            $userId
        );


        $checkUser->execute();

        $userResult =
            $checkUser->get_result();


        if ($userResult->num_rows === 0) {

            $checkUser->close();

            throw new Exception(
                'User not found.'
            );
        }


        $checkUser->close();


        /*
         * Insert notification
         */

        $stmt = $conn->prepare(
            "INSERT INTO notifications
            (
                user_id,
                title,
                message,
                type,
                is_read,
                created_at
            )
            VALUES
            (?, ?, ?, ?, 0, NOW())"
        );


        if (!$stmt) {

            throw new Exception(
                'Failed to prepare notification insert: '
                . $conn->error
            );
        }


        $stmt->bind_param(
            "isss",
            $userId,
            $title,
            $message,
            $type
        );


        if (!$stmt->execute()) {

            throw new Exception(
                'Failed to create notification: '
                . $stmt->error
            );
        }


        $notificationId =
            $conn->insert_id;


        $stmt->close();


        $response['success'] = true;

        $response['message'] =
            'Notification created successfully.';

        $response['data'] = [
            'id' => $notificationId,
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => 0
        ];


    } catch (Exception $e) {

        http_response_code(400);

        $response['message'] =
            $e->getMessage();
    }


    echo json_encode(
        $response,
        JSON_PRETTY_PRINT
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| PUT - Mark notification as read/unread
|--------------------------------------------------------------------------
|
| JSON:
|
| {
|   "id": 1,
|   "is_read": 1
| }
|
*/

if ($method === 'PUT') {

    try {

        $input = getJsonInput();


        $id = isset($input['id'])
            ? (int) $input['id']
            : 0;

        $isRead = isset($input['is_read'])
            ? (int) $input['is_read']
            : 1;


        if ($id <= 0) {

            throw new Exception(
                'Notification ID is required.'
            );
        }


        $isRead =
            $isRead ? 1 : 0;


        /*
         * Check notification exists
         */

        $check = $conn->prepare(
            "SELECT id
             FROM notifications
             WHERE id = ?
             LIMIT 1"
        );


        if (!$check) {

            throw new Exception(
                'Failed to prepare query: '
                . $conn->error
            );
        }


        $check->bind_param(
            "i",
            $id
        );


        $check->execute();

        $result =
            $check->get_result();


        if ($result->num_rows === 0) {

            $check->close();

            throw new Exception(
                'Notification not found.'
            );
        }


        $check->close();


        /*
         * Update notification
         */

        $stmt = $conn->prepare(
            "UPDATE notifications
             SET is_read = ?
             WHERE id = ?"
        );


        if (!$stmt) {

            throw new Exception(
                'Failed to prepare update query: '
                . $conn->error
            );
        }


        $stmt->bind_param(
            "ii",
            $isRead,
            $id
        );


        if (!$stmt->execute()) {

            throw new Exception(
                'Failed to update notification: '
                . $stmt->error
            );
        }


        $stmt->close();


        $response['success'] = true;

        $response['message'] =
            $isRead
                ? 'Notification marked as read.'
                : 'Notification marked as unread.';

        $response['data'] = [
            'id' => $id,
            'is_read' => $isRead
        ];


    } catch (Exception $e) {

        http_response_code(400);

        $response['message'] =
            $e->getMessage();
    }


    echo json_encode(
        $response,
        JSON_PRETTY_PRINT
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| DELETE - Delete notification
|--------------------------------------------------------------------------
|
| Supports:
|
| DELETE /api/notifications.php?id=1
|
| or JSON:
|
| {
|   "id": 1
| }
|
*/

if ($method === 'DELETE') {

    try {

        $input = getJsonInput();


        /*
         * First check JSON
         */

        $id = isset($input['id'])
            ? (int) $input['id']
            : 0;


        /*
         * Then check URL
         */

        if ($id <= 0 && isset($_GET['id'])) {

            $id = (int) $_GET['id'];
        }


        /*
         * Finally support form encoded DELETE
         */

        if ($id <= 0) {

            $raw =
                file_get_contents('php://input');

            parse_str(
                $raw,
                $deleteData
            );


            if (
                isset($deleteData['id'])
            ) {

                $id =
                    (int) $deleteData['id'];
            }
        }


        if ($id <= 0) {

            throw new Exception(
                'Notification ID is required.'
            );
        }


        $stmt = $conn->prepare(
            "DELETE FROM notifications
             WHERE id = ?"
        );


        if (!$stmt) {

            throw new Exception(
                'Failed to prepare delete query: '
                . $conn->error
            );
        }


        $stmt->bind_param(
            "i",
            $id
        );


        if (!$stmt->execute()) {

            throw new Exception(
                'Failed to delete notification: '
                . $stmt->error
            );
        }


        if ($stmt->affected_rows === 0) {

            $stmt->close();

            throw new Exception(
                'Notification not found.'
            );
        }


        $stmt->close();


        $response['success'] = true;

        $response['message'] =
            'Notification deleted successfully.';

        $response['data'] = [
            'id' => $id
        ];


    } catch (Exception $e) {

        http_response_code(400);

        $response['message'] =
            $e->getMessage();
    }


    echo json_encode(
        $response,
        JSON_PRETTY_PRINT
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Unsupported method
|--------------------------------------------------------------------------
*/

http_response_code(405);

$response['success'] = false;

$response['message'] =
    'HTTP method not allowed.';


echo json_encode(
    $response,
    JSON_PRETTY_PRINT
);

exit;