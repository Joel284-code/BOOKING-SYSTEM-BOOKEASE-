<?php

/*
|--------------------------------------------------------------------------
| BookEase - Sidebar
|--------------------------------------------------------------------------
|
| Shared sidebar for:
| - Admin
| - Staff
| - Customer
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Load Required Files
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';


/*
|--------------------------------------------------------------------------
| Current Page Information
|--------------------------------------------------------------------------
*/

$currentPage = basename(
    $_SERVER['PHP_SELF'] ?? ''
);

$currentDirectory = basename(
    dirname(
        $_SERVER['PHP_SELF'] ?? ''
    )
);


/*
|--------------------------------------------------------------------------
| User Information
|--------------------------------------------------------------------------
*/

$userRole = currentUserRole();

$isAdmin = ($userRole === 'admin');

$isStaff = ($userRole === 'staff');

$isAdminOrStaff = in_array(
    $userRole,
    ['admin', 'staff'],
    true
);


/*
|--------------------------------------------------------------------------
| Active Link Helper
|--------------------------------------------------------------------------
*/

function sidebarActive(
    string $page,
    ?string $directory = null
): string {

    global $currentPage;
    global $currentDirectory;

    if ($currentPage !== $page) {
        return '';
    }

    if (
        $directory !== null &&
        $currentDirectory !== $directory
    ) {
        return '';
    }

    return 'active';
}


/*
|--------------------------------------------------------------------------
| Services Active Helper
|--------------------------------------------------------------------------
*/

function servicesSidebarActive(): string
{
    global $currentDirectory;

    return $currentDirectory === 'services'
        ? 'active'
        : '';
}

?>


<aside
    class="sidebar"
    id="sidebar"
>


    <!-- SIDEBAR HEADER -->

    <div class="sidebar-title">

        <?php if ($isAdmin): ?>

            Administration

        <?php elseif ($isStaff): ?>

            Staff Menu

        <?php else: ?>

            Customer Menu

        <?php endif; ?>

    </div>


    <?php if ($isAdminOrStaff): ?>


        <!-- =========================================================
             ADMIN / STAFF MENU
        ========================================================== -->


        <a
            href="<?= e(BASE_URL) ?>/admin/dashboard.php"
            class="sidebar-link <?= sidebarActive(
                'dashboard.php',
                'admin'
            ) ?>"
        >

            <span class="sidebar-icon">▣</span>

            <span class="sidebar-text">
                Dashboard
            </span>

        </a>


        <a
            href="<?= e(BASE_URL) ?>/admin/bookings.php"
            class="sidebar-link <?= sidebarActive(
                'bookings.php'
            ) ?>"
        >

            <span class="sidebar-icon">▤</span>

            <span class="sidebar-text">
                Bookings
            </span>

        </a>


        <a
            href="<?= e(BASE_URL) ?>/admin/customers.php"
            class="sidebar-link <?= sidebarActive(
                'customers.php'
            ) ?>"
        >

            <span class="sidebar-icon">♙</span>

            <span class="sidebar-text">
                Customers
            </span>

        </a>


        <a
            href="<?= e(BASE_URL) ?>/services/index.php"
            class="sidebar-link <?= servicesSidebarActive() ?>"
        >

            <span class="sidebar-icon">▦</span>

            <span class="sidebar-text">
                Services
            </span>

        </a>


        <?php if ($isAdmin): ?>


            <!-- STAFF -->

            <a
                href="<?= e(BASE_URL) ?>/admin/staff.php"
                class="sidebar-link <?= sidebarActive(
                    'staff.php'
                ) ?>"
            >

                <span class="sidebar-icon">♟</span>

                <span class="sidebar-text">
                    Staff
                </span>

            </a>


            <!-- PAYMENTS -->

            <a
                href="<?= e(BASE_URL) ?>/admin/payments.php"
                class="sidebar-link <?= sidebarActive(
                    'payments.php'
                ) ?>"
            >

                <span class="sidebar-icon">₵</span>

                <span class="sidebar-text">
                    Payments
                </span>

            </a>


            <!-- REPORTS -->

            <a
                href="<?= e(BASE_URL) ?>/admin/reports.php"
                class="sidebar-link <?= sidebarActive(
                    'reports.php'
                ) ?>"
            >

                <span class="sidebar-icon">▥</span>

                <span class="sidebar-text">
                    Reports
                </span>

            </a>


            <!-- SETTINGS -->

            <a
                href="<?= e(BASE_URL) ?>/admin/settings.php"
                class="sidebar-link <?= sidebarActive(
                    'settings.php'
                ) ?>"
            >

                <span class="sidebar-icon">⚙</span>

                <span class="sidebar-text">
                    Settings
                </span>

            </a>


        <?php endif; ?>


        <!-- PROFILE -->

        <a
            href="<?= e(BASE_URL) ?>/admin/profile.php"
            class="sidebar-link <?= sidebarActive(
                'profile.php'
            ) ?>"
        >

            <span class="sidebar-icon">◉</span>

            <span class="sidebar-text">
                Profile
            </span>

        </a>


    <?php else: ?>


        <!-- =========================================================
             CUSTOMER MENU
        ========================================================== -->


        <!-- DASHBOARD -->

        <a
            href="<?= e(BASE_URL) ?>/customer/dashboard.php"
            class="sidebar-link <?= sidebarActive(
                'dashboard.php',
                'customer'
            ) ?>"
        >

            <span class="sidebar-icon">▣</span>

            <span class="sidebar-text">
                Dashboard
            </span>

        </a>


        <!-- BOOK SERVICE -->

        <a
            href="<?= e(BASE_URL) ?>/customer/book.php"
            class="sidebar-link <?= sidebarActive(
                'book.php'
            ) ?>"
        >

            <span class="sidebar-icon">＋</span>

            <span class="sidebar-text">
                Book Service
            </span>

        </a>


        <!-- MY BOOKINGS -->

        <a
            href="<?= e(BASE_URL) ?>/customer/my-bookings.php"
            class="sidebar-link <?= sidebarActive(
                'my-bookings.php'
            ) ?>"
        >

            <span class="sidebar-icon">▤</span>

            <span class="sidebar-text">
                My Bookings
            </span>

        </a>


        <!-- SERVICES -->

        <a
            href="<?= e(BASE_URL) ?>/services/index.php"
            class="sidebar-link <?= servicesSidebarActive() ?>"
        >

            <span class="sidebar-icon">▦</span>

            <span class="sidebar-text">
                Services
            </span>

        </a>


        <!-- NOTIFICATIONS -->

        <a
            href="<?= e(BASE_URL) ?>/customer/notifications.php"
            class="sidebar-link <?= sidebarActive(
                'notifications.php'
            ) ?>"
        >

            <span class="sidebar-icon">♢</span>

            <span class="sidebar-text">
                Notifications
            </span>

        </a>


        <!-- PROFILE -->

        <a
            href="<?= e(BASE_URL) ?>/customer/profile.php"
            class="sidebar-link <?= sidebarActive(
                'profile.php'
            ) ?>"
        >

            <span class="sidebar-icon">◉</span>

            <span class="sidebar-text">
                Profile
            </span>

        </a>


    <?php endif; ?>


    <!-- =========================================================
         COMMON LINKS
    ========================================================== -->


    <div class="sidebar-divider"></div>


    <!-- WEBSITE -->

    <a
        href="<?= e(BASE_URL) ?>/index.php"
        class="sidebar-link"
    >

        <span class="sidebar-icon">⌂</span>

        <span class="sidebar-text">
            Website
        </span>

    </a>


    <!-- LOGOUT -->

    <?php if (isLoggedIn()): ?>

        <a
            href="<?= e(BASE_URL) ?>/logout.php"
            class="sidebar-link sidebar-logout"
            data-confirm="Are you sure you want to logout?"
        >

            <span class="sidebar-icon">⇥</span>

            <span class="sidebar-text">
                Logout
            </span>

        </a>

    <?php endif; ?>


</aside>


<style>

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

.sidebar {

    position: fixed;

    left: 0;

    top: 64px;

    bottom: 0;

    width: 240px;

    background: #ffffff;

    border-right:
        1px solid #e2e8f0;

    padding: 20px 12px;

    overflow-y: auto;

    z-index: 900;

}


/*
|--------------------------------------------------------------------------
| Sidebar Title
|--------------------------------------------------------------------------
*/

.sidebar-title {

    padding:
        10px 13px 15px;

    color: #64748b;

    font-size: 11px;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: .7px;

}


/*
|--------------------------------------------------------------------------
| Sidebar Links
|--------------------------------------------------------------------------
*/

.sidebar-link {

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        12px 14px;

    margin-bottom: 5px;

    color: #475569;

    text-decoration: none;

    border-radius: 7px;

    font-size: 14px;

    transition:
        background .2s,
        color .2s;

}


/*
|--------------------------------------------------------------------------
| Hover
|--------------------------------------------------------------------------
*/

.sidebar-link:hover {

    background: #f1f5f9;

    color: #2563eb;

}


/*
|--------------------------------------------------------------------------
| Active
|--------------------------------------------------------------------------
*/

.sidebar-link.active {

    background: #eff6ff;

    color: #2563eb;

    font-weight: 600;

}


/*
|--------------------------------------------------------------------------
| Icons
|--------------------------------------------------------------------------
*/

.sidebar-icon {

    width: 20px;

    min-width: 20px;

    text-align: center;

    font-size: 16px;

}


/*
|--------------------------------------------------------------------------
| Text
|--------------------------------------------------------------------------
*/

.sidebar-text {

    white-space: nowrap;

}


/*
|--------------------------------------------------------------------------
| Divider
|--------------------------------------------------------------------------
*/

.sidebar-divider {

    height: 1px;

    background: #e2e8f0;

    margin:
        15px 8px;

}


/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

.sidebar-logout {

    color: #dc2626;

}


.sidebar-logout:hover {

    background: #fef2f2;

    color: #b91c1c;

}


/*
|--------------------------------------------------------------------------
| Scrollbar
|--------------------------------------------------------------------------
*/

.sidebar::-webkit-scrollbar {

    width: 5px;

}


.sidebar::-webkit-scrollbar-thumb {

    background: #cbd5e1;

    border-radius: 10px;

}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 800px) {

    .sidebar {

        width: 70px;

        padding:
            15px 8px;

    }


    .sidebar-title {

        display: none;

    }


    .sidebar-link {

        justify-content: center;

        padding:
            13px 8px;

    }


    .sidebar-text {

        display: none;

    }


    .sidebar-icon {

        font-size: 18px;

    }


    .sidebar-divider {

        margin:
            15px 5px;

    }

}

</style>