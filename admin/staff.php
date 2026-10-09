<?php

/*
|--------------------------------------------------------------------------
| BookEase - Admin Staff
|--------------------------------------------------------------------------
| Manage administrator and staff accounts.
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
| Check Login
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
| Check Admin / Staff Access
|--------------------------------------------------------------------------
*/

$role = strtolower(
    trim(
        $_SESSION['role'] ?? ''
    )
);

if (!in_array($role, ['admin', 'staff'], true)) {

    header(
        'Location: ' .
        BASE_URL .
        '/customer/dashboard.php'
    );

    exit;
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

        throw new PDOException(
            'Database connection was not created correctly.'
        );
    }

} catch (PDOException $e) {

    die(
        'Database connection error: ' .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


/*
|--------------------------------------------------------------------------
| Helper Function
|--------------------------------------------------------------------------
*/

function staffEscape($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Page Variables
|--------------------------------------------------------------------------
*/

$staff = [];

$error = '';

$adminName =
    $_SESSION['name']
    ?? $_SESSION['username']
    ?? 'Administrator';


/*
|--------------------------------------------------------------------------
| Get Staff Accounts
|--------------------------------------------------------------------------
|
| Includes administrators and staff accounts.
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
            status,
            created_at

        FROM users

        WHERE role IN ('admin', 'staff')

        ORDER BY id DESC
    ");

    $stmt->execute();

    $staff =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $error =
        'Unable to load staff accounts. Please check the users table.';
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalStaff = count($staff);

$totalAdmins = 0;

$totalStaffMembers = 0;

$activeStaff = 0;

$inactiveStaff = 0;


foreach ($staff as $person) {

    $personRole =
        strtolower(
            trim(
                $person['role'] ?? ''
            )
        );

    $personStatus =
        strtolower(
            trim(
                $person['status'] ?? 'active'
            )
        );


    if ($personRole === 'admin') {

        $totalAdmins++;

    } elseif ($personRole === 'staff') {

        $totalStaffMembers++;
    }


    if ($personStatus === 'active') {

        $activeStaff++;

    } else {

        $inactiveStaff++;
    }
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

<title>
    Staff | <?= staffEscape(
        $_SESSION['site_name'] ?? 'BookEase'
    ) ?>
</title>


<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f1f5f9;

    color: #1e293b;
}


/*
|--------------------------------------------------------------------------
| Admin Layout
|--------------------------------------------------------------------------
*/

.admin-layout {

    display: flex;

    min-height: 100vh;
}


/*
|--------------------------------------------------------------------------
| Sidebar
|--------------------------------------------------------------------------
*/

.sidebar {

    width: 250px;

    background: #0f172a;

    color: white;

    position: fixed;

    top: 0;

    left: 0;

    bottom: 0;

    overflow-y: auto;

    z-index: 1000;
}


/*
|--------------------------------------------------------------------------
| Sidebar Header
|--------------------------------------------------------------------------
*/

.sidebar-header {

    padding: 22px 20px;

    border-bottom:
        1px solid
        rgba(255,255,255,.10);
}

.sidebar-header h2 {

    margin: 0;

    font-size: 21px;
}

.sidebar-header p {

    margin: 5px 0 0;

    color: #94a3b8;

    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

.sidebar-nav {

    padding: 15px 10px;
}

.sidebar-nav a {

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 12px 14px;

    margin-bottom: 4px;

    color: #cbd5e1;

    text-decoration: none;

    border-radius: 8px;

    font-size: 14px;

    transition: .2s;
}

.sidebar-nav a:hover {

    background: #1e293b;

    color: white;
}

.sidebar-nav a.active {

    background: #2563eb;

    color: white;
}

.nav-icon {

    width: 22px;

    text-align: center;

    font-size: 16px;
}


/*
|--------------------------------------------------------------------------
| Main
|--------------------------------------------------------------------------
*/

.main {

    margin-left: 250px;

    width: calc(100% - 250px);

    min-height: 100vh;
}


/*
|--------------------------------------------------------------------------
| Topbar
|--------------------------------------------------------------------------
*/

.topbar {

    height: 70px;

    background: white;

    border-bottom:
        1px solid #e2e8f0;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;
}

.topbar-title {

    font-size: 20px;

    font-weight: 700;
}

.topbar-user {

    color: #64748b;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| Content
|--------------------------------------------------------------------------
*/

.content {

    max-width: 1250px;

    margin: 0 auto;

    padding: 30px;
}


/*
|--------------------------------------------------------------------------
| Page Title
|--------------------------------------------------------------------------
*/

.page-title {

    margin-bottom: 25px;
}

.page-title h1 {

    margin: 0;

    font-size: 28px;
}

.page-title p {

    margin-top: 7px;

    color: #64748b;
}


/*
|--------------------------------------------------------------------------
| Alert
|--------------------------------------------------------------------------
*/

.alert {

    padding: 14px 16px;

    margin-bottom: 20px;

    border-radius: 8px;
}

.alert-error {

    background: #fee2e2;

    color: #991b1b;

    border:
        1px solid #fecaca;
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 18px;

    margin-bottom: 25px;
}

.stat-card {

    background: white;

    padding: 20px;

    border-radius: 12px;

    border:
        1px solid #e2e8f0;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,.05);
}

.stat-card h4 {

    margin: 0 0 8px;

    color: #64748b;

    font-size: 14px;
}

.stat-number {

    font-size: 25px;

    font-weight: 700;

    color: #2563eb;
}


/*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
*/

.table-container {

    background: white;

    border-radius: 12px;

    overflow: hidden;

    border:
        1px solid #e2e8f0;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,.05);
}

.table-header {

    padding: 20px;

    border-bottom:
        1px solid #e2e8f0;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.table-header h3 {

    margin: 0;

    font-size: 18px;
}

.table-count {

    color: #64748b;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| Table Wrapper
|--------------------------------------------------------------------------
*/

.table-wrapper {

    width: 100%;

    overflow-x: auto;
}

table {

    width: 100%;

    min-width: 950px;

    border-collapse: collapse;
}

thead th {

    background: #f8fafc;

    color: #334155;

    font-weight: 600;

    text-align: left;

    font-size: 13px;

    text-transform: uppercase;
}

th,
td {

    padding: 15px 20px;

    border-bottom:
        1px solid #e2e8f0;

    white-space: nowrap;
}

tbody tr:hover {

    background: #f8fafc;
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

.status {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 600;
}

.status-active {

    background: #dcfce7;

    color: #166534;
}

.status-inactive {

    background: #fee2e2;

    color: #991b1b;
}

.status-pending {

    background: #fef3c7;

    color: #92400e;
}

.status-default {

    background: #e2e8f0;

    color: #334155;
}


/*
|--------------------------------------------------------------------------
| Role
|--------------------------------------------------------------------------
*/

.role {

    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 600;
}

.role-admin {

    background: #dbeafe;

    color: #1d4ed8;
}

.role-staff {

    background: #ede9fe;

    color: #6d28d9;
}

.role-default {

    background: #e2e8f0;

    color: #334155;
}


/*
|--------------------------------------------------------------------------
| Empty
|--------------------------------------------------------------------------
*/

.empty {

    text-align: center;

    padding: 40px;

    color: #64748b;
}


/*
|--------------------------------------------------------------------------
| Footer
|--------------------------------------------------------------------------
*/

.footer {

    padding: 25px 30px;

    text-align: center;

    color: #64748b;

    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 1000px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }

}

@media (max-width: 800px) {

    .sidebar {

        width: 220px;
    }

    .main {

        margin-left: 220px;

        width: calc(100% - 220px);
    }

    .content {

        padding: 20px;
    }

}

@media (max-width: 600px) {

    .admin-layout {

        display: block;
    }

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;
    }

    .main {

        margin-left: 0;

        width: 100%;
    }

    .topbar {

        height: auto;

        padding: 15px;

        flex-direction: column;

        align-items: flex-start;

        gap: 8px;
    }

    .content {

        padding: 15px;
    }

    .stats {

        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<div class="admin-layout">


    <!-- ================================================================
         SIDEBAR
    ================================================================= -->


    <aside class="sidebar">


        <div class="sidebar-header">

            <h2>

                <?= staffEscape(
                    $_SESSION['site_name']
                    ?? 'BookEase'
                ) ?>

            </h2>

            <p>
                Admin Panel
            </p>

        </div>


        <nav class="sidebar-nav">


            <!-- DASHBOARD -->

            <a
                href="<?= BASE_URL ?>/admin/dashboard.php"
            >

                <span class="nav-icon">
                    🏠
                </span>

                Dashboard

            </a>


            <!-- BOOKINGS -->

            <a
                href="<?= BASE_URL ?>/admin/bookings.php"
            >

                <span class="nav-icon">
                    📅
                </span>

                Bookings

            </a>


            <!-- CUSTOMERS -->

            <a
                href="<?= BASE_URL ?>/admin/customers.php"
            >

                <span class="nav-icon">
                    👥
                </span>

                Customers

            </a>


            <!-- SERVICES -->

            <a
                href="<?= BASE_URL ?>/admin/services.php"
            >

                <span class="nav-icon">
                    🛎️
                </span>

                Services

            </a>


            <!-- STAFF -->

            <a
                href="<?= BASE_URL ?>/admin/staff.php"
                class="active"
            >

                <span class="nav-icon">
                    👨‍💼
                </span>

                Staff

            </a>


            <!-- PAYMENTS -->

            <a
                href="<?= BASE_URL ?>/admin/payments.php"
            >

                <span class="nav-icon">
                    💳
                </span>

                Payments

            </a>


            <!-- REPORTS -->

            <a
                href="<?= BASE_URL ?>/admin/reports.php"
            >

                <span class="nav-icon">
                    📊
                </span>

                Reports

            </a>


            <!-- PROFILE -->

            <a
                href="<?= BASE_URL ?>/admin/profile.php"
            >

                <span class="nav-icon">
                    👤
                </span>

                My Profile

            </a>


            <!-- SETTINGS -->

            <a
                href="<?= BASE_URL ?>/admin/settings.php"
            >

                <span class="nav-icon">
                    ⚙️
                </span>

                Settings

            </a>


            <!-- LOGOUT -->

            <a
                href="<?= BASE_URL ?>/logout.php"
            >

                <span class="nav-icon">
                    🚪
                </span>

                Logout

            </a>


        </nav>

    </aside>


    <!-- ================================================================
         MAIN CONTENT
    ================================================================= -->


    <main class="main">


        <!-- TOPBAR -->

        <header class="topbar">

            <div class="topbar-title">

                Staff Management

            </div>


            <div class="topbar-user">

                Welcome,

                <strong>
                    <?= staffEscape(
                        $adminName
                    ) ?>
                </strong>

                &nbsp;

                (<?= staffEscape(
                    ucfirst($role)
                ) ?>)

            </div>

        </header>


        <!-- CONTENT -->

        <div class="content">


            <!-- PAGE TITLE -->

            <div class="page-title">

                <h1>
                    Staff
                </h1>

                <p>
                    Manage administrator and staff accounts.
                </p>

            </div>


            <!-- ERROR -->

            <?php if ($error !== ''): ?>

                <div class="alert alert-error">

                    <?= staffEscape(
                        $error
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- ========================================================
                 STATISTICS
            ========================================================= -->


            <div class="stats">


                <!-- TOTAL -->

                <div class="stat-card">

                    <h4>
                        Total Accounts
                    </h4>

                    <div class="stat-number">

                        <?= number_format(
                            $totalStaff
                        ) ?>

                    </div>

                </div>


                <!-- ADMINS -->

                <div class="stat-card">

                    <h4>
                        Administrators
                    </h4>

                    <div
                        class="stat-number"
                        style="color:#2563eb;"
                    >

                        <?= number_format(
                            $totalAdmins
                        ) ?>

                    </div>

                </div>


                <!-- STAFF -->

                <div class="stat-card">

                    <h4>
                        Staff Members
                    </h4>

                    <div
                        class="stat-number"
                        style="color:#7c3aed;"
                    >

                        <?= number_format(
                            $totalStaffMembers
                        ) ?>

                    </div>

                </div>


                <!-- ACTIVE -->

                <div class="stat-card">

                    <h4>
                        Active Accounts
                    </h4>

                    <div
                        class="stat-number"
                        style="color:#16a34a;"
                    >

                        <?= number_format(
                            $activeStaff
                        ) ?>

                    </div>

                </div>


            </div>


            <!-- ========================================================
                 STAFF TABLE
            ========================================================= -->


            <div class="table-container">


                <div class="table-header">

                    <h3>
                        Staff Accounts
                    </h3>

                    <div class="table-count">

                        <?= number_format(
                            $totalStaff
                        ) ?>

                        account(s)

                    </div>

                </div>


                <div class="table-wrapper">


                    <table>


                        <thead>

                            <tr>

                                <th>
                                    ID
                                </th>

                                <th>
                                    Name
                                </th>

                                <th>
                                    Email
                                </th>

                                <th>
                                    Phone
                                </th>

                                <th>
                                    Role
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Joined
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php if (!empty($staff)): ?>


                            <?php foreach (
                                $staff as $person
                            ): ?>


                                <?php

                                $personId =
                                    (int) (
                                        $person['id']
                                        ?? 0
                                    );


                                $personName =
                                    $person['name']
                                    ?? 'Unknown';


                                $personEmail =
                                    $person['email']
                                    ?? '-';


                                $personPhone =
                                    $person['phone']
                                    ?? '-';


                                $personRole =
                                    strtolower(
                                        trim(
                                            $person['role']
                                            ?? ''
                                        )
                                    );


                                $personStatus =
                                    strtolower(
                                        trim(
                                            $person['status']
                                            ?? 'active'
                                        )
                                    );


                                /*
                                | Role class
                                */

                                if (
                                    in_array(
                                        $personRole,
                                        [
                                            'admin',
                                            'staff'
                                        ],
                                        true
                                    )
                                ) {

                                    $roleClass =
                                        $personRole;

                                } else {

                                    $roleClass =
                                        'default';
                                }


                                /*
                                | Status class
                                */

                                if (
                                    in_array(
                                        $personStatus,
                                        [
                                            'active',
                                            'inactive',
                                            'pending'
                                        ],
                                        true
                                    )
                                ) {

                                    $statusClass =
                                        $personStatus;

                                } else {

                                    $statusClass =
                                        'default';
                                }


                                /*
                                | Role label
                                */

                                if (
                                    $personRole
                                    === 'admin'
                                ) {

                                    $roleLabel =
                                        'Administrator';

                                } elseif (
                                    $personRole
                                    === 'staff'
                                ) {

                                    $roleLabel =
                                        'Staff';

                                } else {

                                    $roleLabel =
                                        ucfirst(
                                            $personRole
                                        );
                                }


                                /*
                                | Date
                                */

                                $joinedDate = '-';

                                if (
                                    !empty(
                                        $person['created_at']
                                    )
                                ) {

                                    $timestamp =
                                        strtotime(
                                            $person['created_at']
                                        );

                                    if (
                                        $timestamp !== false
                                    ) {

                                        $joinedDate =
                                            date(
                                                'd M Y',
                                                $timestamp
                                            );
                                    }
                                }

                                ?>


                                <tr>


                                    <!-- ID -->

                                    <td>

                                        <strong>

                                            #<?= $personId ?>

                                        </strong>

                                    </td>


                                    <!-- NAME -->

                                    <td>

                                        <strong>

                                            <?= staffEscape(
                                                $personName
                                            ) ?>

                                        </strong>

                                    </td>


                                    <!-- EMAIL -->

                                    <td>

                                        <?= staffEscape(
                                            $personEmail
                                        ) ?>

                                    </td>


                                    <!-- PHONE -->

                                    <td>

                                        <?= staffEscape(
                                            $personPhone
                                        ) ?>

                                    </td>


                                    <!-- ROLE -->

                                    <td>

                                        <span
                                            class="role role-<?= staffEscape(
                                                $roleClass
                                            ) ?>"
                                        >

                                            <?= staffEscape(
                                                $roleLabel
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <span
                                            class="status status-<?= staffEscape(
                                                $statusClass
                                            ) ?>"
                                        >

                                            <?= staffEscape(
                                                ucfirst(
                                                    $personStatus
                                                )
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- JOINED -->

                                    <td>

                                        <?= staffEscape(
                                            $joinedDate
                                        ) ?>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        <?php else: ?>


                            <tr>

                                <td
                                    colspan="7"
                                    class="empty"
                                >

                                    No staff accounts found.

                                </td>

                            </tr>


                        <?php endif; ?>


                        </tbody>


                    </table>


                </div>


            </div>


        </div>


        <!-- FOOTER -->

        <footer class="footer">

            &copy;
            <?= date('Y') ?>

            <?= staffEscape(
                $_SESSION['site_name']
                ?? 'BookEase'
            ) ?>.

            All rights reserved.

        </footer>


    </main>


</div>


</body>

</html>