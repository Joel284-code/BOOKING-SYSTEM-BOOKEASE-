<?php

/*
|--------------------------------------------------------------------------
| BookEase - Navigation Bar
|--------------------------------------------------------------------------
|
| This file is shared by pages throughout the application.
| It safely loads the required authentication/helper functions.
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
| Current User Information
|--------------------------------------------------------------------------
*/

$currentRole = currentUserRole();
$currentName = currentUserName();

?>


<nav class="bookease-navbar">

    <!-- LOGO -->

    <div class="navbar-logo">

        <a href="<?= e(BASE_URL) ?>">

            Book<span>Ease</span>

        </a>

    </div>


    <!-- NAVIGATION -->

    <div class="navbar-right">

        <?php if (isLoggedIn()): ?>

            <!-- USER NAME -->

            <span class="navbar-user">

                <?= e($currentName ?: 'User') ?>

            </span>


            <!-- ROLE -->

            <span class="navbar-role">

                <?= e(ucfirst($currentRole)) ?>

            </span>


            <!-- DASHBOARD -->

            <?php if ($currentRole === 'admin' || $currentRole === 'staff'): ?>

                <a
                    href="<?= e(BASE_URL) ?>/admin/dashboard.php"
                    class="navbar-link"
                >
                    Dashboard
                </a>

            <?php else: ?>

                <a
                    href="<?= e(BASE_URL) ?>/customer/dashboard.php"
                    class="navbar-link"
                >
                    Dashboard
                </a>

            <?php endif; ?>


            <!-- LOGOUT -->

            <a
                href="<?= e(BASE_URL) ?>/logout.php"
                class="navbar-logout"
                data-confirm="Are you sure you want to logout?"
            >
                Logout
            </a>


        <?php else: ?>


            <!-- LOGIN -->

            <a
                href="<?= e(BASE_URL) ?>/auth/login.php"
                class="navbar-login"
            >
                Login
            </a>


            <!-- REGISTER -->

            <a
                href="<?= e(BASE_URL) ?>/auth/register.php"
                class="navbar-register"
            >
                Create Account
            </a>


        <?php endif; ?>

    </div>

</nav>


<style>

/*
|--------------------------------------------------------------------------
| BookEase Navbar
|--------------------------------------------------------------------------
*/

.bookease-navbar {

    min-height: 64px;

    background: #0f172a;

    color: #ffffff;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    padding: 0 25px;

    position: sticky;

    top: 0;

    z-index: 1000;

    box-shadow:
        0 2px 8px rgba(0, 0, 0, 0.08);

}


/*
|--------------------------------------------------------------------------
| Logo
|--------------------------------------------------------------------------
*/

.navbar-logo {

    font-size: 22px;

    font-weight: 700;

    white-space: nowrap;

}


.navbar-logo a {

    color: #ffffff;

    text-decoration: none;

}


.navbar-logo span {

    color: #3b82f6;

}


/*
|--------------------------------------------------------------------------
| Right Side
|--------------------------------------------------------------------------
*/

.navbar-right {

    display: flex;

    align-items: center;

    justify-content: flex-end;

    gap: 12px;

}


/*
|--------------------------------------------------------------------------
| User
|--------------------------------------------------------------------------
*/

.navbar-user {

    color: #cbd5e1;

    font-size: 14px;

    font-weight: 600;

}


.navbar-role {

    background: #1e293b;

    color: #93c5fd;

    padding: 5px 9px;

    border-radius: 15px;

    font-size: 11px;

    font-weight: 600;

}


/*
|--------------------------------------------------------------------------
| Dashboard Link
|--------------------------------------------------------------------------
*/

.navbar-link {

    color: #cbd5e1;

    text-decoration: none;

    font-size: 14px;

    padding: 8px 10px;

    border-radius: 6px;

    transition:
        background .2s,
        color .2s;

}


.navbar-link:hover {

    background: #1e293b;

    color: #ffffff;

}


/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

.navbar-login {

    color: #ffffff;

    text-decoration: none;

    font-size: 14px;

    font-weight: 600;

    padding: 8px 12px;

}


.navbar-login:hover {

    color: #93c5fd;

}


/*
|--------------------------------------------------------------------------
| Register
|--------------------------------------------------------------------------
*/

.navbar-register {

    color: #ffffff;

    background: #2563eb;

    text-decoration: none;

    padding: 8px 13px;

    border-radius: 6px;

    font-size: 13px;

    font-weight: 600;

    transition:
        background .2s;

}


.navbar-register:hover {

    background: #1d4ed8;

}


/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

.navbar-logout {

    color: #ffffff;

    background: #dc2626;

    text-decoration: none;

    padding: 8px 13px;

    border-radius: 6px;

    font-size: 13px;

    font-weight: 600;

    transition:
        background .2s;

}


.navbar-logout:hover {

    background: #b91c1c;

}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .bookease-navbar {

        padding: 0 15px;

    }

    .navbar-right {

        gap: 6px;

    }

    .navbar-user,
    .navbar-role,
    .navbar-link {

        display: none;

    }

    .navbar-logo {

        font-size: 20px;

    }

}

</style>