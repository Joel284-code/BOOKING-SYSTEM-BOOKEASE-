/* =========================================================
   BOOKING SYSTEM - CALENDAR JAVASCRIPT
========================================================= */

"use strict";


document.addEventListener("DOMContentLoaded", function () {

    initializeCalendar();

});


/* =========================================================
   INITIALIZE CALENDAR
========================================================= */

function initializeCalendar() {

    const dateInputs =
        document.querySelectorAll(
            "input[type='date'].booking-date"
        );

    dateInputs.forEach(function (input) {

        setMinimumDate(input);

        input.addEventListener(
            "change",
            handleDateChange
        );

    });


    const singleDateInput =
        document.querySelector(
            "#booking_date"
        );

    if (
        singleDateInput &&
        !singleDateInput.classList.contains(
            "booking-date"
        )
    ) {

        setMinimumDate(singleDateInput);

        singleDateInput.addEventListener(
            "change",
            handleDateChange
        );

    }

}


/* =========================================================
   SET MINIMUM DATE
========================================================= */

function setMinimumDate(input) {

    if (!input) {
        return;
    }

    const today =
        new Date();

    const year =
        today.getFullYear();

    const month =
        String(
            today.getMonth() + 1
        ).padStart(2, "0");

    const day =
        String(
            today.getDate()
        ).padStart(2, "0");

    const formattedDate =
        `${year}-${month}-${day}`;

    input.min =
        formattedDate;

}


/* =========================================================
   DATE CHANGE
========================================================= */

function handleDateChange(event) {

    const input =
        event.target;

    if (!input.value) {
        return;
    }

    const selectedDate =
        new Date(
            input.value +
            "T00:00:00"
        );

    const today =
        new Date();

    today.setHours(
        0,
        0,
        0,
        0
    );

    if (selectedDate < today) {

        input.value = "";

        if (
            typeof showToast ===
            "function"
        ) {

            showToast(
                "Please select a future date.",
                "warning"
            );

        }

        return;

    }

    updateCalendarInformation(
        selectedDate
    );

}


/* =========================================================
   UPDATE CALENDAR INFORMATION
========================================================= */

function updateCalendarInformation(date) {

    const dayElement =
        document.querySelector(
            "#selectedDay"
        );

    const dateElement =
        document.querySelector(
            "#selectedDateText"
        );

    if (dayElement) {

        dayElement.textContent =
            date.toLocaleDateString(
                "en-KE",
                {
                    weekday: "long"
                }
            );

    }

    if (dateElement) {

        dateElement.textContent =
            date.toLocaleDateString(
                "en-KE",
                {
                    day: "numeric",
                    month: "long",
                    year: "numeric"
                }
            );

    }


    /*
     * Ask booking.js to check
     * availability when available.
     */

    if (
        window.Booking &&
        typeof window.Booking
            .checkAvailability ===
            "function"
    ) {

        window.Booking
            .checkAvailability();

    }

}


/* =========================================================
   DISABLE WEEKENDS
========================================================= */

function disableWeekends(input) {

    if (!input) {
        return;
    }

    input.addEventListener(
        "change",
        function () {

            if (!input.value) {
                return;
            }

            const date =
                new Date(
                    input.value +
                    "T00:00:00"
                );

            const day =
                date.getDay();

            /*
             * 0 = Sunday
             * 6 = Saturday
             */

            if (
                day === 0 ||
                day === 6
            ) {

                input.value = "";

                if (
                    typeof showToast ===
                    "function"
                ) {

                    showToast(
                        "Weekend bookings are not available.",
                        "warning"
                    );

                }

            }

        }
    );

}


/* =========================================================
   FORMAT DATE
========================================================= */

function formatBookingDate(dateString) {

    if (!dateString) {
        return "";
    }

    const date =
        new Date(
            dateString +
            "T00:00:00"
        );

    return date.toLocaleDateString(
        "en-KE",
        {
            day: "2-digit",
            month: "short",
            year: "numeric"
        }
    );

}


/* =========================================================
   GET TODAY
========================================================= */

function getTodayDate() {

    const today =
        new Date();

    return today.toISOString()
        .split("T")[0];

}


/* =========================================================
   PUBLIC API
========================================================= */

window.BookingCalendar = {

    formatDate:
        formatBookingDate,

    getToday:
        getTodayDate,

    disableWeekends:
        disableWeekends

};