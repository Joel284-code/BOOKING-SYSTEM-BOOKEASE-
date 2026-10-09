<?php
/**
 * BookEase Booking System API
 * File: api/services.php
 *
 * MySQLi version
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';


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
| Check database connection
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
| Request method
|--------------------------------------------------------------------------
*/

$method = $_SERVER['REQUEST_METHOD'];


/*
|--------------------------------------------------------------------------
| Helper: Read JSON input
|--------------------------------------------------------------------------
*/

function getServiceJsonInput()
{
    $raw = file_get_contents('php://input');

    if (!$raw) {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data)
        ? $data
        : [];
}


/*
|--------------------------------------------------------------------------
| GET SERVICES
|--------------------------------------------------------------------------
|
| All active services:
|
| /booking-system/api/services.php
|
| One service:
|
| /booking-system/api/services.php?id=1
|
*/

if ($method === 'GET') {

    try {

        $id = isset($_GET['id'])
            ? (int) $_GET['id']
            : 0;


        /*
        |--------------------------------------------------------------------------
        | Get single service
        |--------------------------------------------------------------------------
        */

        if ($id > 0) {

            $stmt = $conn->prepare(
                "SELECT
                    id,
                    name,
                    description,
                    price,
                    duration,
                    status,
                    created_at
                 FROM services
                 WHERE id = ?
                 LIMIT 1"
            );


            if (!$stmt) {

                throw new Exception(
                    'Failed to prepare service query: '
                    . $conn->error
                );
            }


            $stmt->bind_param(
                "i",
                $id
            );


            if (!$stmt->execute()) {

                throw new Exception(
                    'Failed to retrieve service: '
                    . $stmt->error
                );
            }


            $result =
                $stmt->get_result();


            $service =
                $result->fetch_assoc();


            $stmt->close();


            if (!$service) {

                throw new Exception(
                    'Service not found.'
                );
            }


            $response['success'] = true;

            $response['message'] =
                'Service retrieved successfully.';

            $response['data'] = $service;


        } else {


            /*
            |--------------------------------------------------------------------------
            | Get all active services
            |--------------------------------------------------------------------------
            */

            $result = $conn->query(
                "SELECT
                    id,
                    name,
                    description,
                    price,
                    duration,
                    status,
                    created_at
                 FROM services
                 WHERE status = 'active'
                 ORDER BY name ASC"
            );


            if (!$result) {

                throw new Exception(
                    'Failed to retrieve services: '
                    . $conn->error
                );
            }


            $services = [];


            while (
                $service =
                $result->fetch_assoc()
            ) {

                $services[] = $service;
            }


            $response['success'] = true;

            $response['message'] =
                'Services retrieved successfully.';

            $response['data'] = $services;
        }


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
| POST - CREATE SERVICE
|--------------------------------------------------------------------------
*/

if ($method === 'POST') {

    try {

        $input =
            getServiceJsonInput();


        /*
         * Support normal HTML POST
         */

        if (empty($input)) {

            $input = $_POST;
        }


        $name = trim(
            $input['name'] ?? ''
        );


        $description = trim(
            $input['description'] ?? ''
        );


        $price = isset($input['price'])
            ? (float) $input['price']
            : 0;


        $duration = isset($input['duration'])
            ? (int) $input['duration']
            : 60;


        $status = trim(
            $input['status'] ?? 'active'
        );


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($name === '') {

            throw new Exception(
                'Service name is required.'
            );
        }


        if ($price < 0) {

            throw new Exception(
                'Service price cannot be negative.'
            );
        }


        if ($duration <= 0) {

            throw new Exception(
                'Service duration must be greater than 0.'
            );
        }


        $allowedStatuses = [
            'active',
            'inactive'
        ];


        if (
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {

            throw new Exception(
                'Invalid service status.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check duplicate service
        |--------------------------------------------------------------------------
        */

        $check = $conn->prepare(
            "SELECT id
             FROM services
             WHERE name = ?
             LIMIT 1"
        );


        if (!$check) {

            throw new Exception(
                'Failed to prepare duplicate check: '
                . $conn->error
            );
        }


        $check->bind_param(
            "s",
            $name
        );


        $check->execute();


        $checkResult =
            $check->get_result();


        if ($checkResult->num_rows > 0) {

            $check->close();

            throw new Exception(
                'A service with this name already exists.'
            );
        }


        $check->close();


        /*
        |--------------------------------------------------------------------------
        | Insert service
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare(
            "INSERT INTO services
            (
                name,
                description,
                price,
                duration,
                status,
                created_at
            )
            VALUES
            (?, ?, ?, ?, ?, NOW())"
        );


        if (!$stmt) {

            throw new Exception(
                'Failed to prepare service insert: '
                . $conn->error
            );
        }


        $stmt->bind_param(
            "ssdis",
            $name,
            $description,
            $price,
            $duration,
            $status
        );


        if (!$stmt->execute()) {

            throw new Exception(
                'Failed to create service: '
                . $stmt->error
            );
        }


        $serviceId =
            $conn->insert_id;


        $stmt->close();


        $response['success'] = true;

        $response['message'] =
            'Service created successfully.';

        $response['data'] = [
            'id' => $serviceId,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'duration' => $duration,
            'status' => $status
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
| PUT - UPDATE SERVICE
|--------------------------------------------------------------------------
|
| JSON example:
|
| {
|   "id": 1,
|   "name": "Consultation",
|   "description": "Professional consultation",
|   "price": 1500,
|   "duration": 60,
|   "status": "active"
| }
|
*/

if ($method === 'PUT') {

    try {

        $input =
            getServiceJsonInput();


        $id = isset($input['id'])
            ? (int) $input['id']
            : 0;


        $name = trim(
            $input['name'] ?? ''
        );


        $description = trim(
            $input['description'] ?? ''
        );


        $price = isset($input['price'])
            ? (float) $input['price']
            : 0;


        $duration = isset($input['duration'])
            ? (int) $input['duration']
            : 60;


        $status = trim(
            $input['status'] ?? 'active'
        );


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($id <= 0) {

            throw new Exception(
                'Service ID is required.'
            );
        }


        if ($name === '') {

            throw new Exception(
                'Service name is required.'
            );
        }


        if ($price < 0) {

            throw new Exception(
                'Service price cannot be negative.'
            );
        }


        if ($duration <= 0) {

            throw new Exception(
                'Service duration must be greater than 0.'
            );
        }


        $allowedStatuses = [
            'active',
            'inactive'
        ];


        if (
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {

            throw new Exception(
                'Invalid service status.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check service exists
        |--------------------------------------------------------------------------
        */

        $check = $conn->prepare(
            "SELECT id
             FROM services
             WHERE id = ?
             LIMIT 1"
        );


        if (!$check) {

            throw new Exception(
                'Failed to prepare service check: '
                . $conn->error
            );
        }


        $check->bind_param(
            "i",
            $id
        );


        $check->execute();


        $checkResult =
            $check->get_result();


        if ($checkResult->num_rows === 0) {

            $check->close();

            throw new Exception(
                'Service not found.'
            );
        }


        $check->close();


        /*
        |--------------------------------------------------------------------------
        | Update service
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare(
            "UPDATE services
             SET
                name = ?,
                description = ?,
                price = ?,
                duration = ?,
                status = ?
             WHERE id = ?"
        );


        if (!$stmt) {

            throw new Exception(
                'Failed to prepare service update: '
                . $conn->error
            );
        }


        $stmt->bind_param(
            "ssdisi",
            $name,
            $description,
            $price,
            $duration,
            $status,
            $id
        );


        if (!$stmt->execute()) {

            throw new Exception(
                'Failed to update service: '
                . $stmt->error
            );
        }


        $stmt->close();


        $response['success'] = true;

        $response['message'] =
            'Service updated successfully.';

        $response['data'] = [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'duration' => $duration,
            'status' => $status
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
| DELETE - DEACTIVATE SERVICE
|--------------------------------------------------------------------------
|
| We do NOT permanently delete the service.
|
| Example:
|
| DELETE /api/services.php?id=1
|
*/

if ($method === 'DELETE') {

    try {

        $input =
            getServiceJsonInput();


        $id = isset($input['id'])
            ? (int) $input['id']
            : 0;


        /*
         * Also allow:
         * /api/services.php?id=1
         */

        if (
            $id <= 0 &&
            isset($_GET['id'])
        ) {

            $id =
                (int) $_GET['id'];
        }


        /*
         * Support form encoded DELETE
         */

        if ($id <= 0) {

            $raw =
                file_get_contents(
                    'php://input'
                );


            parse_str(
                $raw,
                $deleteData
            );


            if (
                isset(
                    $deleteData['id']
                )
            ) {

                $id =
                    (int) $deleteData['id'];
            }
        }


        if ($id <= 0) {

            throw new Exception(
                'Service ID is required.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check service exists
        |--------------------------------------------------------------------------
        */

        $check = $conn->prepare(
            "SELECT id
             FROM services
             WHERE id = ?
             LIMIT 1"
        );


        if (!$check) {

            throw new Exception(
                'Failed to prepare service check: '
                . $conn->error
            );
        }


        $check->bind_param(
            "i",
            $id
        );


        $check->execute();


        $checkResult =
            $check->get_result();


        if ($checkResult->num_rows === 0) {

            $check->close();

            throw new Exception(
                'Service not found.'
            );
        }


        $check->close();


        /*
        |--------------------------------------------------------------------------
        | Soft delete
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare(
            "UPDATE services
             SET status = 'inactive'
             WHERE id = ?"
        );


        if (!$stmt) {

            throw new Exception(
                'Failed to prepare service deactivation: '
                . $conn->error
            );
        }


        $stmt->bind_param(
            "i",
            $id
        );


        if (!$stmt->execute()) {

            throw new Exception(
                'Failed to deactivate service: '
                . $stmt->error
            );
        }


        $stmt->close();


        $response['success'] = true;

        $response['message'] =
            'Service deactivated successfully.';

        $response['data'] = [
            'id' => $id,
            'status' => 'inactive'
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
| Unsupported HTTP method
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