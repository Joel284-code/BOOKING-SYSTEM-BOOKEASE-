```php
<?php

/*
|--------------------------------------------------------------------------
| BookEase - Admin Index
|--------------------------------------------------------------------------
| File: admin/index.php
|
| Purpose:
| - Start session
| - Load BookEase configuration
| - Verify login
| - Verify account exists and is active
| - Allow admin/staff into admin dashboard
| - Redirect customers to customer dashboard
| - Prevent invalid roles from accessing the system
|--------------------------------------------------------------------------
*/

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| Load Common Files
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';


/*
|--------------------------------------------------------------------------
| Start Session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Helper Redirect
|--------------------------------------------------------------------------
*/

function redirectTo(string $url): never
{
    header('Location: ' . $url);
    exit;
}


/*
|--------------------------------------------------------------------------
| Check Login
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    empty($_SESSION['user_id'])
) {

    redirectTo(
        BASE_URL . '/auth/login.php'
    );
}


/*
|--------------------------------------------------------------------------
| Get Logged In User ID
|--------------------------------------------------------------------------
*/

$userId = (int) $_SESSION['user_id'];


if ($userId <= 0) {

    session_unset();
    session_destroy();

    redirectTo(
        BASE_URL . '/auth/login.php'
    );
}


/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

try {

    $database = new Database();

    $conn = $database->getConnection();

    if (!$conn instanceof PDO) {
        throw new RuntimeException(
            'Database connection was not created correctly.'
        );
    }

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | If Database Fails
    |--------------------------------------------------------------------------
    */

    session_unset();
    session_destroy();

    redirectTo(
        BASE_URL . '/auth/login.php'
    );
}


/*
|--------------------------------------------------------------------------
| Get Current User
|--------------------------------------------------------------------------
|
| Always verify the role and account status from the database.
| This prevents an old/stale session from granting access.
|--------------------------------------------------------------------------
*/

try {

    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            email,
            phone,
            role,
            status
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $userId
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | User Does Not Exist
    |--------------------------------------------------------------------------
    */

    if (!$user) {

        session_unset();
        session_destroy();

        redirectTo(
            BASE_URL . '/auth/login.php'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Get User Information
    |--------------------------------------------------------------------------
    */

    $role = strtolower(
        trim(
            (string) (
                $user['role'] ?? ''
            )
        )
    );


    $accountStatus = strtolower(
        trim(
            (string) (
                $user['status'] ?? 'active'
            )
        )
    );


    /*
    |--------------------------------------------------------------------------
    | Check Account Status
    |--------------------------------------------------------------------------
    |
    | Only active accounts can enter the system.
    |--------------------------------------------------------------------------
    */

    if ($accountStatus !== 'active') {

        session_unset();
        session_destroy();

        redirectTo(
            BASE_URL . '/auth/login.php?error=account_inactive'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Refresh Session Information
    |--------------------------------------------------------------------------
    */

    $_SESSION['user_id'] = (int) $user['id'];

    $_SESSION['name'] = $user['name'] ?? '';

    $_SESSION['email'] = $user['email'] ?? '';

    $_SESSION['phone'] = $user['phone'] ?? '';

    $_SESSION['role'] = $role;

    $_SESSION['status'] = $accountStatus;


} catch (Throwable $e) {

    session_unset();
    session_destroy();

    redirectTo(
        BASE_URL . '/auth/login.php'
    );
}


/*
|--------------------------------------------------------------------------
| Admin Access
|--------------------------------------------------------------------------
*/

if ($role === 'admin') {

    redirectTo(
        BASE_URL . '/admin/dashboard.php'
    );
}


/*
|--------------------------------------------------------------------------
| Staff Access
|--------------------------------------------------------------------------
*/

if ($role === 'staff') {

    redirectTo(
        BASE_URL . '/admin/dashboard.php'
    );
}


/*
|--------------------------------------------------------------------------
| Customer Access
|--------------------------------------------------------------------------
*/

if ($role === 'customer') {

    redirectTo(
        BASE_URL . '/customer/dashboard.php'
    );
}


/*
|--------------------------------------------------------------------------
| Unknown / Invalid Role
|--------------------------------------------------------------------------
*/

session_unset();
session_destroy();

redirectTo(
    BASE_URL . '/auth/login.php?error=invalid_role'
);
```
