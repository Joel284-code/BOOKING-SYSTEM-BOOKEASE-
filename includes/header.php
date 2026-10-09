<?php

/*
|--------------------------------------------------------------------------
| BookEase - General Helper Functions
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Escape HTML
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {

    function e($value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

if (!function_exists('redirect')) {

    function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Redirect to URL
|--------------------------------------------------------------------------
*/

if (!function_exists('redirectTo')) {

    function redirectTo(string $path): never
    {
        if (defined('BASE_URL')) {

            $url =
                rtrim(BASE_URL, '/') .
                '/' .
                ltrim($path, '/');

        } else {

            $url = $path;
        }

        header('Location: ' . $url);
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Flash Message
|--------------------------------------------------------------------------
*/

if (!function_exists('setFlash')) {

    function setFlash(
        string $message,
        string $type = 'success'
    ): void {

        $_SESSION['flash'] = [
            'message' => $message,
            'type'    => $type
        ];
    }
}


/*
|--------------------------------------------------------------------------
| Get Flash Message
|--------------------------------------------------------------------------
*/

if (!function_exists('getFlash')) {

    function getFlash(): ?array
    {
        if (
            !isset($_SESSION['flash']) ||
            !is_array($_SESSION['flash'])
        ) {
            return null;
        }

        $flash = $_SESSION['flash'];

        unset($_SESSION['flash']);

        return $flash;
    }
}


/*
|--------------------------------------------------------------------------
| Check Flash Message
|--------------------------------------------------------------------------
*/

if (!function_exists('hasFlash')) {

    function hasFlash(): bool
    {
        return isset($_SESSION['flash'])
            && is_array($_SESSION['flash']);
    }
}


/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

if (!function_exists('csrfToken')) {

    function csrfToken(): string
    {
        if (
            empty($_SESSION['csrf_token'])
        ) {

            $_SESSION['csrf_token'] =
                bin2hex(
                    random_bytes(32)
                );
        }

        return $_SESSION['csrf_token'];
    }
}


/*
|--------------------------------------------------------------------------
| CSRF Input
|--------------------------------------------------------------------------
*/

if (!function_exists('csrfInput')) {

    function csrfInput(): string
    {
        return
            '<input type="hidden" name="csrf_token" value="' .
            e(csrfToken()) .
            '">';
    }
}


/*
|--------------------------------------------------------------------------
| Verify CSRF Token
|--------------------------------------------------------------------------
*/

if (!function_exists('verifyCsrfToken')) {

    function verifyCsrfToken(
        ?string $token
    ): bool {

        if (
            empty($token) ||
            empty($_SESSION['csrf_token'])
        ) {
            return false;
        }

        return hash_equals(
            $_SESSION['csrf_token'],
            $token
        );
    }
}


/*
|--------------------------------------------------------------------------
| Require POST Request
|--------------------------------------------------------------------------
*/

if (!function_exists('requirePost')) {

    function requirePost(): void
    {
        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST'
        ) {

            http_response_code(405);

            exit(
                'Method Not Allowed'
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| Booking Status Class
|--------------------------------------------------------------------------
|
| Used by:
| customer/my-bookings.php
|
*/

if (!function_exists('bookingStatusClass')) {

    function bookingStatusClass(
        string $status
    ): string {

        return match (
            strtolower(trim($status))
        ) {

            'pending' =>
                'status-pending',

            'confirmed' =>
                'status-confirmed',

            'completed' =>
                'status-completed',

            'cancelled' =>
                'status-cancelled',

            'rejected' =>
                'status-rejected',

            'no_show' =>
                'status-no-show',

            default =>
                'status-pending'
        };
    }
}


/*
|--------------------------------------------------------------------------
| Booking Status Label
|--------------------------------------------------------------------------
*/

if (!function_exists('bookingStatusLabel')) {

    function bookingStatusLabel(
        string $status
    ): string {

        return match (
            strtolower(trim($status))
        ) {

            'pending' =>
                'Pending',

            'confirmed' =>
                'Confirmed',

            'completed' =>
                'Completed',

            'cancelled' =>
                'Cancelled',

            'rejected' =>
                'Rejected',

            'no_show' =>
                'No Show',

            default =>
                ucfirst(
                    str_replace(
                        '_',
                        ' ',
                        strtolower($status)
                    )
                )
        };
    }
}


/*
|--------------------------------------------------------------------------
| Payment Status Class
|--------------------------------------------------------------------------
*/

if (!function_exists('paymentStatusClass')) {

    function paymentStatusClass(
        string $status
    ): string {

        return match (
            strtolower(trim($status))
        ) {

            'paid' =>
                'badge-success',

            'pending' =>
                'badge-warning',

            'failed' =>
                'badge-danger',

            'refunded' =>
                'badge-secondary',

            'unpaid' =>
                'badge-warning',

            default =>
                'badge-secondary'
        };
    }
}


/*
|--------------------------------------------------------------------------
| Payment Status Label
|--------------------------------------------------------------------------
*/

if (!function_exists('paymentStatusLabel')) {

    function paymentStatusLabel(
        string $status
    ): string {

        return match (
            strtolower(trim($status))
        ) {

            'paid' =>
                'Paid',

            'pending' =>
                'Pending',

            'failed' =>
                'Failed',

            'refunded' =>
                'Refunded',

            'unpaid' =>
                'Unpaid',

            default =>
                ucfirst(
                    str_replace(
                        '_',
                        ' ',
                        strtolower($status)
                    )
                )
        };
    }
}


/*
|--------------------------------------------------------------------------
| Format Currency
|--------------------------------------------------------------------------
*/

if (!function_exists('formatCurrency')) {

    function formatCurrency(
        $amount
    ): string {

        $currency =
            defined('CURRENCY')
                ? CURRENCY
                : 'KSh';

        return
            $currency .
            ' ' .
            number_format(
                (float) $amount,
                2
            );
    }
}


/*
|--------------------------------------------------------------------------
| Format Date
|--------------------------------------------------------------------------
*/

if (!function_exists('formatDate')) {

    function formatDate(
        ?string $date,
        string $format = 'd M Y'
    ): string {

        if (
            empty($date)
        ) {
            return '';
        }

        $timestamp =
            strtotime($date);

        if ($timestamp === false) {
            return '';
        }

        return date(
            $format,
            $timestamp
        );
    }
}


/*
|--------------------------------------------------------------------------
| Format Time
|--------------------------------------------------------------------------
*/

if (!function_exists('formatTime')) {

    function formatTime(
        ?string $time
    ): string {

        if (
            empty($time)
        ) {
            return '';
        }

        $timestamp =
            strtotime($time);

        if ($timestamp === false) {
            return $time;
        }

        return date(
            'h:i A',
            $timestamp
        );
    }
}


/*
|--------------------------------------------------------------------------
| Generate Booking Reference
|--------------------------------------------------------------------------
*/

if (!function_exists('generateBookingReference')) {

    function generateBookingReference(): string
    {
        return
            'BK-' .
            date('YmdHis') .
            '-' .
            strtoupper(
                bin2hex(
                    random_bytes(3)
                )
            );
    }
}


/*
|--------------------------------------------------------------------------
| Generate Transaction Reference
|--------------------------------------------------------------------------
*/

if (!function_exists('generateTransactionReference')) {

    function generateTransactionReference(): string
    {
        return
            'TXN-' .
            date('YmdHis') .
            '-' .
            strtoupper(
                bin2hex(
                    random_bytes(3)
                )
            );
    }
}


/*
|--------------------------------------------------------------------------
| Current Request Method
|--------------------------------------------------------------------------
*/

if (!function_exists('isPost')) {

    function isPost(): bool
    {
        return
            $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}


/*
|--------------------------------------------------------------------------
| Current Request Method GET
|--------------------------------------------------------------------------
*/

if (!function_exists('isGet')) {

    function isGet(): bool
    {
        return
            $_SERVER['REQUEST_METHOD'] === 'GET';
    }
}


/*
|--------------------------------------------------------------------------
| Get Request Value
|--------------------------------------------------------------------------
*/

if (!function_exists('request')) {

    function request(
        string $key,
        $default = null
    ) {

        if (
            isset($_POST[$key])
        ) {
            return $_POST[$key];
        }

        if (
            isset($_GET[$key])
        ) {
            return $_GET[$key];
        }

        return $default;
    }
}


/*
|--------------------------------------------------------------------------
| Get POST Value
|--------------------------------------------------------------------------
*/

if (!function_exists('post')) {

    function post(
        string $key,
        $default = null
    ) {

        return
            $_POST[$key]
            ?? $default;
    }
}


/*
|--------------------------------------------------------------------------
| Get GET Value
|--------------------------------------------------------------------------
*/

if (!function_exists('get')) {

    function get(
        string $key,
        $default = null
    ) {

        return
            $_GET[$key]
            ?? $default;
    }
}


/*
|--------------------------------------------------------------------------
| Check User Role
|--------------------------------------------------------------------------
*/

if (!function_exists('hasRole')) {

    function hasRole(
        string $role
    ): bool {

        return
            isset($_SESSION['role']) &&
            $_SESSION['role'] === $role;
    }
}


/*
|--------------------------------------------------------------------------
| Admin URL
|--------------------------------------------------------------------------
*/

if (!function_exists('adminUrl')) {

    function adminUrl(
        string $path = ''
    ): string {

        return
            rtrim(BASE_URL, '/') .
            '/admin/' .
            ltrim($path, '/');
    }
}


/*
|--------------------------------------------------------------------------
| Customer URL
|--------------------------------------------------------------------------
*/

if (!function_exists('customerUrl')) {

    function customerUrl(
        string $path = ''
    ): string {

        return
            rtrim(BASE_URL, '/') .
            '/customer/' .
            ltrim($path, '/');
    }
}


/*
|--------------------------------------------------------------------------
| Auth URL
|--------------------------------------------------------------------------
*/

if (!function_exists('authUrl')) {

    function authUrl(
        string $path = ''
    ): string {

        return
            rtrim(BASE_URL, '/') .
            '/auth/' .
            ltrim($path, '/');
    }
}


/*
|--------------------------------------------------------------------------
| Old Input Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('old')) {

    function old(
        string $key,
        $default = ''
    ): string {

        return e(
            $_POST[$key]
            ?? $default
        );
    }
}


/*
|--------------------------------------------------------------------------
| Selected Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('selected')) {

    function selected(
        $value1,
        $value2
    ): string {

        return
            (string) $value1 ===
            (string) $value2
                ? 'selected'
                : '';
    }
}


/*
|--------------------------------------------------------------------------
| Checked Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('checked')) {

    function checked(
        $value1,
        $value2
    ): string {

        return
            (string) $value1 ===
            (string) $value2
                ? 'checked'
                : '';
    }
}


/*
|--------------------------------------------------------------------------
| Active Menu Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('activeClass')) {

    function activeClass(
        string $current,
        string $expected
    ): string {

        return
            $current === $expected
                ? 'active'
                : '';
    }
}


/*
|--------------------------------------------------------------------------
| User Initial
|--------------------------------------------------------------------------
*/

if (!function_exists('userInitial')) {

    function userInitial(
        ?string $name
    ): string {

        $name =
            trim(
                (string) $name
            );

        if ($name === '') {
            return 'U';
        }

        return strtoupper(
            substr(
                $name,
                0,
                1
            )
        );
    }
}


/*
|--------------------------------------------------------------------------
| Safe Integer
|--------------------------------------------------------------------------
*/

if (!function_exists('intValue')) {

    function intValue(
        $value
    ): int {

        return (int) $value;
    }
}


/*
|--------------------------------------------------------------------------
| Safe Decimal
|--------------------------------------------------------------------------
*/

if (!function_exists('decimalValue')) {

    function decimalValue(
        $value
    ): float {

        return (float) $value;
    }
}