<?php

/*
|--------------------------------------------------------------------------
| BookEase General Functions
|--------------------------------------------------------------------------
|
| Common helper functions used throughout the application.
|
*/


/*
|--------------------------------------------------------------------------
| Escape Output
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

    function redirect(string $url): void
    {
        header(
            'Location: ' . $url
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Base URL Redirect
|--------------------------------------------------------------------------
*/

if (!function_exists('redirectTo')) {

    function redirectTo(string $path = ''): void
    {
        $url = BASE_URL;

        if ($path !== '') {

            $url .= '/' .
                ltrim(
                    $path,
                    '/'
                );
        }

        header(
            'Location: ' . $url
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

if (!function_exists('setFlash')) {

    function setFlash(
        string $type,
        string $message
    ): void {

        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
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
            !isset(
                $_SESSION['flash']
            )
        ) {

            return null;
        }

        $flash =
            $_SESSION['flash'];

        unset(
            $_SESSION['flash']
        );

        return $flash;
    }
}


/*
|--------------------------------------------------------------------------
| Display Flash Message
|--------------------------------------------------------------------------
*/

if (!function_exists('displayFlash')) {

    function displayFlash(): void
    {
        $flash = getFlash();

        if (!$flash) {
            return;
        }

        $type =
            e(
                $flash['type']
                ?? 'success'
            );

        $message =
            e(
                $flash['message']
                ?? ''
            );

        echo '
            <div class="flash-message alert alert-' .
            $type .
            '">
                ' .
            $message .
            '
            </div>
        ';
    }
}


/*
|--------------------------------------------------------------------------
| Booking Status Class
|--------------------------------------------------------------------------
*/

if (!function_exists('bookingStatusClass')) {

    function bookingStatusClass(
        string $status
    ): string {

        $status =
            strtolower(
                trim($status)
            );

        $classes = [

            'pending'
                => 'status-pending',

            'confirmed'
                => 'status-confirmed',

            'completed'
                => 'status-completed',

            'cancelled'
                => 'status-cancelled',

            'rejected'
                => 'status-rejected',

            'no_show'
                => 'status-no-show'
        ];

        return $classes[$status]
            ?? 'status-pending';
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

        $status =
            strtolower(
                trim($status)
            );

        $labels = [

            'pending'
                => 'Pending',

            'confirmed'
                => 'Confirmed',

            'completed'
                => 'Completed',

            'cancelled'
                => 'Cancelled',

            'rejected'
                => 'Rejected',

            'no_show'
                => 'No Show'
        ];

        return $labels[$status]
            ?? 'Pending';
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

        $status =
            strtolower(
                trim($status)
            );

        $classes = [

            'unpaid'
                => 'payment-unpaid',

            'pending'
                => 'payment-pending',

            'paid'
                => 'payment-paid',

            'failed'
                => 'payment-failed',

            'refunded'
                => 'payment-refunded'
        ];

        return $classes[$status]
            ?? 'payment-unpaid';
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

        $status =
            strtolower(
                trim($status)
            );

        $labels = [

            'unpaid'
                => 'Unpaid',

            'pending'
                => 'Pending',

            'paid'
                => 'Paid',

            'failed'
                => 'Failed',

            'refunded'
                => 'Refunded'
        ];

        return $labels[$status]
            ?? 'Unpaid';
    }
}


/*
|--------------------------------------------------------------------------
| Format Currency
|--------------------------------------------------------------------------
*/

if (!function_exists('formatCurrency')) {

    function formatCurrency(
        float $amount
    ): string {

        return
            CURRENCY .
            ' ' .
            number_format(
                $amount,
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
        ?string $date
    ): string {

        if (
            empty($date)
        ) {

            return 'N/A';
        }

        $timestamp =
            strtotime($date);

        if (
            $timestamp === false
        ) {

            return 'N/A';
        }

        return date(
            'd M Y',
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

            return 'N/A';
        }

        $timestamp =
            strtotime($time);

        if (
            $timestamp === false
        ) {

            return 'N/A';
        }

        return date(
            'h:i A',
            $timestamp
        );
    }
}


/*
|--------------------------------------------------------------------------
| Format Date and Time
|--------------------------------------------------------------------------
*/

if (!function_exists('formatDateTime')) {

    function formatDateTime(
        ?string $datetime
    ): string {

        if (
            empty($datetime)
        ) {

            return 'N/A';
        }

        $timestamp =
            strtotime($datetime);

        if (
            $timestamp === false
        ) {

            return 'N/A';
        }

        return date(
            'd M Y, h:i A',
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
        return 'BK-' .
            date('Ymd') .
            '-' .
            strtoupper(
                substr(
                    bin2hex(
                        random_bytes(4)
                    ),
                    0,
                    6
                )
            );
    }
}


/*
|--------------------------------------------------------------------------
| Generate Random Token
|--------------------------------------------------------------------------
*/

if (!function_exists('generateToken')) {

    function generateToken(
        int $length = 32
    ): string {

        return bin2hex(
            random_bytes(
                max(
                    16,
                    (int) ceil(
                        $length / 2
                    )
                )
            )
        );
    }
}


/*
|--------------------------------------------------------------------------
| Validate Email
|--------------------------------------------------------------------------
*/

if (!function_exists('isValidEmail')) {

    function isValidEmail(
        string $email
    ): bool {

        return filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        ) !== false;
    }
}


/*
|--------------------------------------------------------------------------
| Clean Input
|--------------------------------------------------------------------------
*/

if (!function_exists('cleanInput')) {

    function cleanInput(
        ?string $value
    ): string {

        return trim(
            $value ?? ''
        );
    }
}


/*
|--------------------------------------------------------------------------
| Get Current URL
|--------------------------------------------------------------------------
*/

if (!function_exists('currentUrl')) {

    function currentUrl(): string
    {
        $protocol =
            (
                !empty(
                    $_SERVER['HTTPS']
                )
                &&
                $_SERVER['HTTPS'] !== 'off'
            )
            ? 'https'
            : 'http';

        $host =
            $_SERVER['HTTP_HOST']
            ?? 'localhost';

        $uri =
            $_SERVER['REQUEST_URI']
            ?? '/';

        return
            $protocol .
            '://' .
            $host .
            $uri;
    }
}


/*
|--------------------------------------------------------------------------
| Check Request Method
|--------------------------------------------------------------------------
*/

if (!function_exists('isPost')) {

    function isPost(): bool
    {
        return
            $_SERVER['REQUEST_METHOD']
            === 'POST';
    }
}


if (!function_exists('isGet')) {

    function isGet(): bool
    {
        return
            $_SERVER['REQUEST_METHOD']
            === 'GET';
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
| Get Integer GET Value
|--------------------------------------------------------------------------
*/

if (!function_exists('getInt')) {

    function getInt(
        string $key,
        int $default = 0
    ): int {

        return isset(
            $_GET[$key]
        )
            ? (int) $_GET[$key]
            : $default;
    }
}


/*
|--------------------------------------------------------------------------
| Check Booking Status
|--------------------------------------------------------------------------
*/

if (!function_exists('isBookingStatus')) {

    function isBookingStatus(
        string $status
    ): bool {

        return in_array(
            strtolower(
                trim($status)
            ),
            [
                'pending',
                'confirmed',
                'completed',
                'cancelled',
                'rejected',
                'no_show'
            ],
            true
        );
    }
}


/*
|--------------------------------------------------------------------------
| Check Payment Status
|--------------------------------------------------------------------------
*/

if (!function_exists('isPaymentStatus')) {

    function isPaymentStatus(
        string $status
    ): bool {

        return in_array(
            strtolower(
                trim($status)
            ),
            [
                'unpaid',
                'pending',
                'paid',
                'failed',
                'refunded'
            ],
            true
        );
    }
}


/*
|--------------------------------------------------------------------------
| Check Booking Can Be Cancelled
|--------------------------------------------------------------------------
*/

if (!function_exists('canCancelBooking')) {

    function canCancelBooking(
        string $status
    ): bool {

        return in_array(
            strtolower(
                trim($status)
            ),
            [
                'pending',
                'confirmed'
            ],
            true
        );
    }
}


/*
|--------------------------------------------------------------------------
| Check Booking Can Be Paid
|--------------------------------------------------------------------------
*/

if (!function_exists('canPayBooking')) {

    function canPayBooking(
        string $bookingStatus,
        string $paymentStatus
    ): bool {

        return
            in_array(
                strtolower(
                    trim($bookingStatus)
                ),
                [
                    'pending',
                    'confirmed'
                ],
                true
            )
            &&
            in_array(
                strtolower(
                    trim($paymentStatus)
                ),
                [
                    'unpaid',
                    'pending'
                ],
                true
            );
    }
}


/*
|--------------------------------------------------------------------------
| Check Date Is Available
|--------------------------------------------------------------------------
*/

if (!function_exists('isPastDate')) {

    function isPastDate(
        string $date
    ): bool {

        $timestamp =
            strtotime($date);

        if (
            $timestamp === false
        ) {

            return false;
        }

        return
            $timestamp <
            strtotime(
                date('Y-m-d')
            );
    }
}


/*
|--------------------------------------------------------------------------
| Booking Status Options
|--------------------------------------------------------------------------
*/

if (!function_exists('bookingStatusOptions')) {

    function bookingStatusOptions(): array
    {
        return [

            'pending'
                => 'Pending',

            'confirmed'
                => 'Confirmed',

            'completed'
                => 'Completed',

            'cancelled'
                => 'Cancelled',

            'rejected'
                => 'Rejected',

            'no_show'
                => 'No Show'
        ];
    }
}


/*
|--------------------------------------------------------------------------
| Payment Status Options
|--------------------------------------------------------------------------
*/

if (!function_exists('paymentStatusOptions')) {

    function paymentStatusOptions(): array
    {
        return [

            'unpaid'
                => 'Unpaid',

            'pending'
                => 'Pending',

            'paid'
                => 'Paid',

            'failed'
                => 'Failed',

            'refunded'
                => 'Refunded'
        ];
    }
}


/*
|--------------------------------------------------------------------------
| User Role Options
|--------------------------------------------------------------------------
*/

if (!function_exists('userRoleOptions')) {

    function userRoleOptions(): array
    {
        return [

            'admin'
                => 'Administrator',

            'customer'
                => 'Customer',

            'staff'
                => 'Staff'
        ];
    }
}


/*
|--------------------------------------------------------------------------
| User Status Options
|--------------------------------------------------------------------------
*/

if (!function_exists('userStatusOptions')) {

    function userStatusOptions(): array
    {
        return [

            'active'
                => 'Active',

            'inactive'
                => 'Inactive',

            'suspended'
                => 'Suspended'
        ];
    }
}


/*
|--------------------------------------------------------------------------
| Service Status Options
|--------------------------------------------------------------------------
*/

if (!function_exists('serviceStatusOptions')) {

    function serviceStatusOptions(): array
    {
        return [

            'active'
                => 'Active',

            'inactive'
                => 'Inactive'
        ];
    }
}


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

if (!function_exists('pagination')) {

    function pagination(
        int $currentPage,
        int $totalPages,
        string $url
    ): string {

        if (
            $totalPages <= 1
        ) {

            return '';
        }

        $html =
            '<div class="pagination">';


        /*
        | Previous
        */

        if (
            $currentPage > 1
        ) {

            $previous =
                $currentPage - 1;

            $html .=
                '<a href="' .
                e(
                    $url .
                    '?page=' .
                    $previous
                ) .
                '">&laquo; Previous</a>';
        }


        /*
        | Page Numbers
        */

        for (
            $i = 1;
            $i <= $totalPages;
            $i++
        ) {

            $active =
                $i === $currentPage
                ? 'active'
                : '';

            $html .=
                '<a class="' .
                e($active) .
                '" href="' .
                e(
                    $url .
                    '?page=' .
                    $i
                ) .
                '">' .
                $i .
                '</a>';
        }


        /*
        | Next
        */

        if (
            $currentPage < $totalPages
        ) {

            $next =
                $currentPage + 1;

            $html .=
                '<a href="' .
                e(
                    $url .
                    '?page=' .
                    $next
                ) .
                '">Next &raquo;</a>';
        }


        $html .=
            '</div>';


        return $html;
    }
}


/*
|--------------------------------------------------------------------------
| Application Name Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('appName')) {

    function appName(): string
    {
        return defined('APP_NAME')
            ? APP_NAME
            : 'BookEase';
    }
}


/*
|--------------------------------------------------------------------------
| Application Version Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('appVersion')) {

    function appVersion(): string
    {
        return defined('APP_VERSION')
            ? APP_VERSION
            : '1.0.0';
    }
}


/*
|--------------------------------------------------------------------------
| Currency Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('currency')) {

    function currency(): string
    {
        return defined('CURRENCY')
            ? CURRENCY
            : 'KSh';
    }
}