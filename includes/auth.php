<?php

/*
|--------------------------------------------------------------------------
| BookEase Authentication
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Load Required Files
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/*
|--------------------------------------------------------------------------
| Check Login
|--------------------------------------------------------------------------
*/

function isLoggedIn()
{
    return !empty($_SESSION['user_id']);
}

/*
|--------------------------------------------------------------------------
| Get Current User ID
|--------------------------------------------------------------------------
*/

function currentUserId()
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

/*
|--------------------------------------------------------------------------
| Get Current User Role
|--------------------------------------------------------------------------
*/

function currentUserRole()
{
    return strtolower(
        trim(
            $_SESSION['role'] ?? 'customer'
        )
    );
}

/*
|--------------------------------------------------------------------------
| Require Login
|--------------------------------------------------------------------------
*/

function requireLogin()
{
    if (!isLoggedIn()) {

        header(
            'Location: ' .
            BASE_URL .
            '/login.php'
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Require Customer
|--------------------------------------------------------------------------
*/

function requireCustomer()
{
    requireLogin();

    if (currentUserRole() !== 'customer') {

        header(
            'Location: ' .
            BASE_URL .
            '/admin/dashboard.php'
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Require Admin
|--------------------------------------------------------------------------
*/

function requireAdmin()
{
    requireLogin();

    if (currentUserRole() !== 'admin') {

        header(
            'Location: ' .
            BASE_URL .
            '/customer/dashboard.php'
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Require Staff
|--------------------------------------------------------------------------
*/

function requireStaff()
{
    requireLogin();

    $role = currentUserRole();

    if (
        $role !== 'staff' &&
        $role !== 'admin'
    ) {

        header(
            'Location: ' .
            BASE_URL .
            '/customer/dashboard.php'
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Require Admin Or Staff
|--------------------------------------------------------------------------
*/

function requireAdminOrStaff()
{
    requireLogin();

    $role = currentUserRole();

    if (
        $role !== 'admin' &&
        $role !== 'staff'
    ) {

        header(
            'Location: ' .
            BASE_URL .
            '/customer/dashboard.php'
        );

        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Redirect Logged-In User
|--------------------------------------------------------------------------
*/

function redirectLoggedInUser()
{
    if (!isLoggedIn()) {
        return;
    }

    $role = currentUserRole();

    if ($role === 'admin' || $role === 'staff') {

        header(
            'Location: ' .
            BASE_URL .
            '/admin/dashboard.php'
        );

        exit;
    }

    if ($role === 'customer') {

        header(
            'Location: ' .
            BASE_URL .
            '/customer/dashboard.php'
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Invalid Role
    |--------------------------------------------------------------------------
    */

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}