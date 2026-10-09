<?php

/*
|--------------------------------------------------------------------------
| BookEase - Main Website
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/includes/config.php';

/*
|--------------------------------------------------------------------------
| Session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| User Login Status
|--------------------------------------------------------------------------
*/

$isLoggedIn = isset($_SESSION['user_id'])
    && (int) $_SESSION['user_id'] > 0;

$userRole = $_SESSION['role'] ?? 'customer';

/*
|--------------------------------------------------------------------------
| Dashboard URL
|--------------------------------------------------------------------------
*/

$dashboardUrl = BASE_URL . '/customer/dashboard.php';

if ($userRole === 'admin' || $userRole === 'staff') {
    $dashboardUrl = BASE_URL . '/admin/dashboard.php';
}

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="BookEase - Simple and convenient online booking management system."
    >

    <title>
        <?= htmlspecialchars(APP_NAME) ?>
        | Online Booking System
    </title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
        }

        a {
            text-decoration: none;
        }

        /* =====================================================
           NAVBAR
        ===================================================== */

        nav {
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 7%;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            font-size: 25px;
            font-weight: 700;
            color: #2563eb;
        }

        .logo span {
            color: #0f172a;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .nav-links a {
            color: #334155;
            font-weight: 500;
            transition: .2s;
        }

        .nav-links a:hover {
            color: #2563eb;
        }

        /* =====================================================
           BUTTONS
        ===================================================== */

        .btn {
            padding: 11px 20px;
            border-radius: 7px;
            display: inline-block;
            font-weight: 600;
            border: 1px solid transparent;
            transition: .2s;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff !important;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-outline {
            border: 1px solid #2563eb;
            color: #2563eb !important;
            background: #ffffff;
        }

        .btn-outline:hover {
            background: #eff6ff;
        }

        /* =====================================================
           HERO
        ===================================================== */

        .hero {
            min-height: 600px;
            display: flex;
            align-items: center;
            gap: 60px;
            padding: 70px 7%;
            background:
                linear-gradient(
                    135deg,
                    #eff6ff,
                    #ffffff
                );
        }

        .hero-content {
            max-width: 650px;
            flex: 1;
        }

        .hero h1 {
            font-size: 56px;
            line-height: 1.1;
            margin-bottom: 25px;
            color: #0f172a;
        }

        .hero h1 span {
            color: #2563eb;
        }

        .hero p {
            font-size: 19px;
            line-height: 1.7;
            color: #64748b;
            margin-bottom: 35px;
        }

        .hero-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        /* =====================================================
           HERO CARD
        ===================================================== */

        .hero-card {
            width: 380px;
            flex-shrink: 0;
            background: #ffffff;
            padding: 35px;
            border-radius: 20px;
            box-shadow:
                0 20px 50px
                rgba(15, 23, 42, .12);
        }

        .hero-card h3 {
            margin-bottom: 25px;
            font-size: 22px;
            color: #0f172a;
        }

        .booking-item {
            padding: 17px;
            background: #f8fafc;
            border-radius: 10px;
            margin-bottom: 12px;
            border: 1px solid #e2e8f0;
        }

        .booking-item:last-child {
            margin-bottom: 0;
        }

        .booking-item strong {
            display: block;
            margin-bottom: 5px;
            color: #1e293b;
        }

        .booking-item small {
            color: #64748b;
        }

        /* =====================================================
           FEATURES
        ===================================================== */

        .features {
            padding: 80px 7%;
            text-align: center;
            background: #ffffff;
        }

        .features h2 {
            font-size: 35px;
            margin-bottom: 12px;
            color: #0f172a;
        }

        .features > p {
            color: #64748b;
            margin-bottom: 45px;
        }

        .feature-grid {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 25px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature {
            background: #ffffff;
            padding: 35px 25px;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            transition: .2s;
        }

        .feature:hover {
            transform: translateY(-4px);
            box-shadow:
                0 10px 30px
                rgba(15, 23, 42, .08);
        }

        .feature-icon {
            font-size: 40px;
            margin-bottom: 20px;
        }

        .feature h3 {
            margin-bottom: 12px;
            color: #0f172a;
        }

        .feature p {
            color: #64748b;
            line-height: 1.6;
        }

        /* =====================================================
           CALL TO ACTION
        ===================================================== */

        .cta {
            padding: 70px 7%;
            text-align: center;
            background: #eff6ff;
        }

        .cta h2 {
            font-size: 32px;
            margin-bottom: 12px;
            color: #0f172a;
        }

        .cta p {
            color: #64748b;
            margin-bottom: 25px;
        }

        /* =====================================================
           FOOTER
        ===================================================== */

        footer {
            background: #0f172a;
            color: #ffffff;
            padding: 30px 7%;
            text-align: center;
        }

        footer p {
            margin: 0;
            color: #cbd5e1;
            font-size: 14px;
        }

        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 1000px) {

            .hero {
                gap: 40px;
            }

            .hero h1 {
                font-size: 45px;
            }

            .feature-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

        }

        @media (max-width: 850px) {

            nav {
                padding: 0 5%;
            }

            .hero {
                flex-direction: column;
                text-align: center;
                padding: 60px 5%;
            }

            .hero-content {
                max-width: 700px;
            }

            .hero-card {
                width: 100%;
                max-width: 450px;
            }

            .hero-buttons {
                justify-content: center;
            }

            .feature-grid {
                grid-template-columns: 1fr;
                max-width: 500px;
            }

            .nav-links {
                gap: 10px;
            }

            .nav-links a:not(.btn) {
                display: none;
            }

        }

        @media (max-width: 500px) {

            nav {
                height: 65px;
            }

            .logo {
                font-size: 22px;
            }

            .hero {
                padding:
                    50px 20px;
            }

            .hero h1 {
                font-size: 36px;
            }

            .hero p {
                font-size: 16px;
            }

            .hero-buttons {
                flex-direction: column;
            }

            .hero-buttons .btn {
                width: 100%;
                text-align: center;
            }

            .hero-card {
                padding: 25px;
            }

            .features {
                padding:
                    60px 20px;
            }

            .features h2 {
                font-size: 29px;
            }

            .cta {
                padding:
                    60px 20px;
            }

        }

    </style>

</head>

<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav>

    <a
        href="<?= BASE_URL ?>/index.php"
        class="logo"
    >
        Book<span>Ease</span>
    </a>


    <div class="nav-links">

        <a href="<?= BASE_URL ?>/index.php">
            Home
        </a>

        <a href="#features">
            Features
        </a>


        <?php if ($isLoggedIn): ?>

            <a
                href="<?= htmlspecialchars($dashboardUrl) ?>"
                class="btn btn-primary"
            >
                Dashboard
            </a>

        <?php else: ?>

            <a
                href="<?= BASE_URL ?>/auth/login.php"
            >
                Login
            </a>

            <a
                href="<?= BASE_URL ?>/auth/register.php"
                class="btn btn-primary"
            >
                Get Started
            </a>

        <?php endif; ?>

    </div>

</nav>


<!-- =========================================================
     HERO
========================================================= -->

<section class="hero">


    <div class="hero-content">

        <h1>
            Book your services
            <span>easily.</span>
        </h1>


        <p>
            A simple and convenient booking platform that
            allows customers to find services, select
            available dates and times, and manage their
            bookings from one place.
        </p>


        <div class="hero-buttons">

            <?php if ($isLoggedIn): ?>

                <a
                    href="<?= htmlspecialchars($dashboardUrl) ?>"
                    class="btn btn-primary"
                >
                    Go to Dashboard
                </a>

                <?php if ($userRole === 'customer'): ?>

                    <a
                        href="<?= BASE_URL ?>/customer/book.php"
                        class="btn btn-outline"
                    >
                        Book a Service
                    </a>

                <?php endif; ?>

            <?php else: ?>

                <a
                    href="<?= BASE_URL ?>/auth/register.php"
                    class="btn btn-primary"
                >
                    Create Account
                </a>

                <a
                    href="<?= BASE_URL ?>/auth/login.php"
                    class="btn btn-outline"
                >
                    Login
                </a>

            <?php endif; ?>

        </div>

    </div>


    <!-- =====================================================
         BOOKING PROCESS CARD
    ====================================================== -->

    <div class="hero-card">

        <h3>
            Simple Booking Process
        </h3>


        <div class="booking-item">

            <strong>
                1. Create Account
            </strong>

            <small>
                Register your account in seconds.
            </small>

        </div>


        <div class="booking-item">

            <strong>
                2. Choose a Service
            </strong>

            <small>
                Select the service you need.
            </small>

        </div>


        <div class="booking-item">

            <strong>
                3. Select Date &amp; Time
            </strong>

            <small>
                Choose an available appointment.
            </small>

        </div>


        <div class="booking-item">

            <strong>
                4. Confirm Booking
            </strong>

            <small>
                Receive your booking confirmation.
            </small>

        </div>

    </div>

</section>


<!-- =========================================================
     FEATURES
========================================================= -->

<section
    class="features"
    id="features"
>

    <h2>
        Everything You Need
    </h2>


    <p>
        Manage your bookings quickly and conveniently.
    </p>


    <div class="feature-grid">


        <div class="feature">

            <div class="feature-icon">
                📅
            </div>

            <h3>
                Easy Booking
            </h3>

            <p>
                Select your preferred service, date and
                time without complicated processes.
            </p>

        </div>


        <div class="feature">

            <div class="feature-icon">
                🔔
            </div>

            <h3>
                Booking Updates
            </h3>

            <p>
                Keep track of your booking status and
                important booking information.
            </p>

        </div>


        <div class="feature">

            <div class="feature-icon">
                📱
            </div>

            <h3>
                Manage Anywhere
            </h3>

            <p>
                Access your bookings and account from your
                computer, tablet or phone.
            </p>

        </div>


    </div>

</section>


<!-- =========================================================
     CTA
========================================================= -->

<section class="cta">

    <?php if ($isLoggedIn): ?>

        <h2>
            Ready to make a booking?
        </h2>

        <p>
            Access your dashboard and manage your bookings.
        </p>

        <a
            href="<?= htmlspecialchars($dashboardUrl) ?>"
            class="btn btn-primary"
        >
            Open Dashboard
        </a>

    <?php else: ?>

        <h2>
            Ready to get started?
        </h2>

        <p>
            Create your account and make your first booking.
        </p>

        <a
            href="<?= BASE_URL ?>/auth/register.php"
            class="btn btn-primary"
        >
            Create Account
        </a>

    <?php endif; ?>

</section>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    <p>

        &copy;
        <?= date('Y') ?>

        <?= htmlspecialchars(APP_NAME) ?>.

        All rights reserved.

    </p>

</footer>


</body>

</html>