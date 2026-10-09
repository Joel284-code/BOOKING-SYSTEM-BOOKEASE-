<?php
/**
 * Deactivate Service
 * File: services/delete.php
 * BookEase Booking System
 */

session_start();

/*
|--------------------------------------------------------------------------
| Load configuration and database
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';


/*
|--------------------------------------------------------------------------
| Check authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header(
        'Location: ' .
        BASE_URL .
        '/auth/login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Check administrator access
|--------------------------------------------------------------------------
*/

if (
    ($_SESSION['role'] ?? '') !== 'admin'
) {

    header(
        'Location: ' .
        BASE_URL .
        '/customer/dashboard.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Database connection
|--------------------------------------------------------------------------
*/

try {

    $db = new Database();

    $conn = $db->getConnection();

} catch (PDOException $e) {

    die('Database connection failed.');
}


/*
|--------------------------------------------------------------------------
| Get service ID
|--------------------------------------------------------------------------
*/

$id = (int) (
    $_POST['id']
    ?? $_GET['id']
    ?? 0
);


/*
|--------------------------------------------------------------------------
| Validate service ID
|--------------------------------------------------------------------------
*/

if ($id <= 0) {

    header(
        'Location: ' .
        BASE_URL .
        '/services/index.php?error=' .
        urlencode('Invalid service ID.')
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Find service
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            status
        FROM services
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $service = $stmt->fetch(
        PDO::FETCH_ASSOC
    );


    /*
    |--------------------------------------------------------------------------
    | Service not found
    |--------------------------------------------------------------------------
    */

    if (!$service) {

        header(
            'Location: ' .
            BASE_URL .
            '/services/index.php?error=' .
            urlencode('Service not found.')
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Already inactive
    |--------------------------------------------------------------------------
    */

    if (
        strtolower(
            $service['status'] ?? ''
        ) === 'inactive'
    ) {

        header(
            'Location: ' .
            BASE_URL .
            '/services/index.php?error=' .
            urlencode('Service is already inactive.')
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Deactivate service
    |--------------------------------------------------------------------------
    |
    | We do NOT permanently delete the service.
    | This keeps existing bookings and records safe.
    |
    */

    $stmt = $conn->prepare("
        UPDATE services
        SET
            status = 'inactive',
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $id
    ]);


    /*
    |--------------------------------------------------------------------------
    | Check whether update happened
    |--------------------------------------------------------------------------
    */

    if ($stmt->rowCount() > 0) {

        header(
            'Location: ' .
            BASE_URL .
            '/services/index.php?success=deleted'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Update did not happen
    |--------------------------------------------------------------------------
    */

    header(
        'Location: ' .
        BASE_URL .
        '/services/index.php?error=' .
        urlencode('Unable to deactivate service.')
    );

    exit;


} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | Database error
    |--------------------------------------------------------------------------
    */

    header(
        'Location: ' .
        BASE_URL .
        '/services/index.php?error=' .
        urlencode('Database error occurred.')
    );

    exit;
}