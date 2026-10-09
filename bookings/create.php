<?php
/**
 * Booking System
 * File: customer/book.php
 */

session_start();

require_once "../includes/database.php";


/*
|--------------------------------------------------------------------------
| Check login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {

    header("Location: ../auth/login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Get active services
|--------------------------------------------------------------------------
*/

$services = $conn->query("
    SELECT
        id,
        name,
        description,
        price,
        duration
    FROM services
    WHERE status = 'active'
    ORDER BY name ASC
");


if (!$services) {

    die(
        "Unable to load services: "
        . htmlspecialchars($conn->error)
    );
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

<title>Book a Service - BookEase</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
    color: #0f172a;
}

.navbar {
    background: #0f172a;
    color: white;
    padding: 18px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
    font-size: 24px;
    font-weight: bold;
}

.logo span {
    color: #3b82f6;
}

.navbar-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.navbar a {
    color: white;
    text-decoration: none;
}

.navbar a:hover {
    color: #60a5fa;
}

.container {
    width: 92%;
    max-width: 900px;
    margin: 40px auto;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0,0,0,.06);
}

h1 {
    margin-top: 0;
    margin-bottom: 8px;
}

.subtitle {
    color: #64748b;
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
}

input,
select,
textarea {
    width: 100%;
    padding: 13px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 15px;
    background: white;
}

input:focus,
select:focus,
textarea:focus {
    outline: none;
    border-color: #2563eb;
}

textarea {
    min-height: 100px;
    resize: vertical;
}

.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.service-info {
    margin-top: -5px;
    margin-bottom: 20px;
    padding: 18px;
    background: #eff6ff;
    border-left: 4px solid #2563eb;
    border-radius: 8px;
    display: none;
}

.service-info p {
    margin: 8px 0;
}

.service-description {
    color: #475569;
    line-height: 1.5;
}

.availability {
    margin-top: 10px;
    padding: 12px;
    border-radius: 7px;
    display: none;
    font-weight: 600;
}

.availability.checking {
    display: block;
    background: #fef3c7;
    color: #92400e;
}

.availability.available {
    display: block;
    background: #dcfce7;
    color: #166534;
}

.availability.unavailable {
    display: block;
    background: #fee2e2;
    color: #991b1b;
}

.availability.error {
    display: block;
    background: #fee2e2;
    color: #991b1b;
}

.btn {
    background: #2563eb;
    color: white;
    border: none;
    padding: 14px 25px;
    border-radius: 7px;
    cursor: pointer;
    font-size: 16px;
    width: 100%;
}

.btn:hover {
    background: #1d4ed8;
}

.btn:disabled {
    background: #94a3b8;
    cursor: not-allowed;
}

.back {
    display: inline-block;
    margin-bottom: 20px;
    color: #2563eb;
    text-decoration: none;
}

.back:hover {
    text-decoration: underline;
}

.no-services {
    padding: 15px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 7px;
}

@media (max-width: 650px) {

    .grid {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .navbar {
        padding: 15px 20px;
    }

    .navbar-right {
        gap: 10px;
    }

    .card {
        padding: 22px;
    }

}

</style>

</head>


<body>


<!--
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
-->

<div class="navbar">

    <div class="logo">
        Book<span>Ease</span>
    </div>

    <div class="navbar-right">

        <a href="../customer/dashboard.php">
            Dashboard
        </a>

        <a href="../auth/logout.php">
            Logout
        </a>

    </div>

</div>


<div class="container">


    <a
        href="../customer/dashboard.php"
        class="back"
    >
        ← Back to Dashboard
    </a>


    <div class="card">

        <h1>
            Book a Service
        </h1>

        <p class="subtitle">
            Select your preferred service, date and time.
        </p>


        <?php if ($services->num_rows === 0): ?>

            <div class="no-services">

                No services are currently available.
                Please check again later.

            </div>

        <?php else: ?>


        <!--
        |--------------------------------------------------------------------------
        | Booking Form
        |--------------------------------------------------------------------------
        -->

        <form
            action="store.php"
            method="POST"
            id="bookingForm"
        >


            <!-- Service -->

            <div class="form-group">

                <label for="service">
                    Service
                </label>

                <select
                    name="service_id"
                    id="service"
                    required
                >

                    <option value="">
                        Select a service
                    </option>


                    <?php while (
                        $service = $services->fetch_assoc()
                    ): ?>

                        <option
                            value="<?= (int) $service['id'] ?>"
                            data-price="<?= htmlspecialchars(
                                $service['price']
                            ) ?>"
                            data-duration="<?= (int) $service['duration'] ?>"
                            data-description="<?= htmlspecialchars(
                                $service['description'] ?? '',
                                ENT_QUOTES
                            ) ?>"
                        >

                            <?= htmlspecialchars(
                                $service['name']
                            ) ?>

                            -
                            KSh
                            <?= number_format(
                                (float) $service['price'],
                                2
                            ) ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <!-- Service Information -->

            <div
                id="serviceInfo"
                class="service-info"
            >

                <strong>
                    Service Information
                </strong>

                <p>
                    <strong>Price:</strong>

                    KSh
                    <span id="price">
                        0.00
                    </span>
                </p>

                <p>
                    <strong>Duration:</strong>

                    <span id="duration">
                        -
                    </span>
                    minutes
                </p>

                <p
                    id="descriptionContainer"
                    class="service-description"
                >
                    <strong>Description:</strong>

                    <span id="description">
                        -
                    </span>
                </p>

            </div>


            <!-- Date and Time -->

            <div class="grid">


                <div class="form-group">

                    <label for="bookingDate">
                        Booking Date
                    </label>

                    <input
                        type="date"
                        name="booking_date"
                        id="bookingDate"
                        min="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="bookingTime">
                        Booking Time
                    </label>

                    <input
                        type="time"
                        name="booking_time"
                        id="bookingTime"
                        min="08:00"
                        max="16:00"
                        required
                    >

                </div>

            </div>


            <!-- Availability -->

            <div
                id="availability"
                class="availability"
            ></div>


            <!-- Notes -->

            <div class="form-group">

                <label for="notes">
                    Notes
                </label>

                <textarea
                    name="notes"
                    id="notes"
                    maxlength="1000"
                    placeholder="Additional information..."
                ></textarea>

            </div>


            <!-- Submit -->

            <button
                type="submit"
                class="btn"
                id="submitButton"
            >
                Submit Booking
            </button>


        </form>

        <?php endif; ?>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Elements
|--------------------------------------------------------------------------
*/

const service =
    document.getElementById("service");

const serviceInfo =
    document.getElementById("serviceInfo");

const price =
    document.getElementById("price");

const duration =
    document.getElementById("duration");

const description =
    document.getElementById("description");

const bookingDate =
    document.getElementById("bookingDate");

const bookingTime =
    document.getElementById("bookingTime");

const availability =
    document.getElementById("availability");

const bookingForm =
    document.getElementById("bookingForm");

const submitButton =
    document.getElementById("submitButton");


/*
|--------------------------------------------------------------------------
| Availability state
|--------------------------------------------------------------------------
*/

let slotAvailable = false;


/*
|--------------------------------------------------------------------------
| Show service information
|--------------------------------------------------------------------------
*/

if (service) {

    service.addEventListener(
        "change",
        function () {

            const option =
                this.options[
                    this.selectedIndex
                ];


            if (!option.value) {

                serviceInfo.style.display =
                    "none";

                slotAvailable = false;

                availability.style.display =
                    "none";

                return;
            }


            const servicePrice =
                parseFloat(
                    option.dataset.price || 0
                );


            price.textContent =
                servicePrice.toLocaleString(
                    "en-KE",
                    {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }
                );


            duration.textContent =
                option.dataset.duration || "-";


            description.textContent =
                option.dataset.description || "No description available.";


            serviceInfo.style.display =
                "block";


            checkAvailability();

        }
    );

}


/*
|--------------------------------------------------------------------------
| Check availability whenever date changes
|--------------------------------------------------------------------------
*/

if (bookingDate) {

    bookingDate.addEventListener(
        "change",
        checkAvailability
    );

}


/*
|--------------------------------------------------------------------------
| Check availability whenever time changes
|--------------------------------------------------------------------------
*/

if (bookingTime) {

    bookingTime.addEventListener(
        "change",
        checkAvailability
    );

}


/*
|--------------------------------------------------------------------------
| Check booking availability
|--------------------------------------------------------------------------
*/

async function checkAvailability() {

    slotAvailable = false;


    const serviceId =
        service
            ? service.value
            : "";

    const date =
        bookingDate
            ? bookingDate.value
            : "";

    const time =
        bookingTime
            ? bookingTime.value
            : "";


    /*
    |--------------------------------------------------------------------------
    | Nothing to check yet
    |--------------------------------------------------------------------------
    */

    if (
        !serviceId ||
        !date ||
        !time
    ) {

        if (availability) {

            availability.style.display =
                "none";

        }

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Show checking message
    |--------------------------------------------------------------------------
    */

    availability.className =
        "availability checking";

    availability.textContent =
        "Checking availability...";


    /*
    |--------------------------------------------------------------------------
    | Disable button temporarily
    |--------------------------------------------------------------------------
    */

    submitButton.disabled = true;


    try {

        const url =
            "../api/check-availability.php"
            + "?service_id="
            + encodeURIComponent(serviceId)
            + "&date="
            + encodeURIComponent(date)
            + "&time="
            + encodeURIComponent(time);


        const response =
            await fetch(url);


        const data =
            await response.json();


        if (
            data.success &&
            data.available
        ) {

            slotAvailable = true;

            availability.className =
                "availability available";

            availability.textContent =
                "✓ This time slot is available.";

            submitButton.disabled = false;

        } else {

            slotAvailable = false;

            availability.className =
                "availability unavailable";

            availability.textContent =
                data.message
                || "This time slot is not available.";

            submitButton.disabled = true;
        }


    } catch (error) {

        slotAvailable = false;

        availability.className =
            "availability error";

        availability.textContent =
            "Unable to check availability. Please try again.";

        submitButton.disabled = true;
    }

}


/*
|--------------------------------------------------------------------------
| Final form validation
|--------------------------------------------------------------------------
*/

if (bookingForm) {

    bookingForm.addEventListener(
        "submit",
        function (event) {

            if (!slotAvailable) {

                event.preventDefault();

                alert(
                    "Please select an available booking date and time."
                );

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Prevent double submission
            |--------------------------------------------------------------------------
            */

            submitButton.disabled = true;

            submitButton.textContent =
                "Submitting Booking...";

        }
    );

}

</script>


</body>

</html>