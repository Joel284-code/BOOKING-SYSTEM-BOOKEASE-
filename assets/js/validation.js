/* =========================================================
   BOOKING SYSTEM - FORM VALIDATION
========================================================= */

"use strict";


document.addEventListener("DOMContentLoaded", function () {

    initializeFormValidation();
    initializePasswordConfirmation();

});


/* =========================================================
   FORM VALIDATION
========================================================= */

function initializeFormValidation() {

    document
        .querySelectorAll("form[data-validate]")
        .forEach(function (form) {

            form.addEventListener(
                "submit",
                function (event) {

                    if (
                        !validateForm(form)
                    ) {

                        event.preventDefault();

                    }

                }
            );

        });

}


/* =========================================================
   VALIDATE FORM
========================================================= */

function validateForm(form) {

    let valid = true;

    clearValidationErrors(form);


    /* -----------------------------------------------------
       REQUIRED FIELDS
    ----------------------------------------------------- */

    form
        .querySelectorAll(
            "input[required], select[required], textarea[required]"
        )
        .forEach(function (input) {

            if (!input.value.trim()) {

                showFieldError(
                    input,
                    "This field is required."
                );

                valid = false;

            }

        });


    /* -----------------------------------------------------
       EMAIL
    ----------------------------------------------------- */

    form
        .querySelectorAll(
            "input[type='email']"
        )
        .forEach(function (input) {

            if (
                input.value &&
                !isValidEmail(input.value)
            ) {

                showFieldError(
                    input,
                    "Please enter a valid email address."
                );

                valid = false;

            }

        });


    /* -----------------------------------------------------
       PHONE
    ----------------------------------------------------- */

    form
        .querySelectorAll(
            "input[data-type='phone']"
        )
        .forEach(function (input) {

            if (
                input.value &&
                !isValidPhone(input.value)
            ) {

                showFieldError(
                    input,
                    "Please enter a valid phone number."
                );

                valid = false;

            }

        });


    /* -----------------------------------------------------
       PASSWORD
    ----------------------------------------------------- */

    form
        .querySelectorAll(
            "input[type='password'][data-min-length]"
        )
        .forEach(function (input) {

            const minLength =
                parseInt(
                    input.dataset.minLength,
                    10
                ) || 8;

            if (
                input.value &&
                input.value.length < minLength
            ) {

                showFieldError(
                    input,
                    `Password must contain at least ${minLength} characters.`
                );

                valid = false;

            }

        });


    /* -----------------------------------------------------
       PASSWORD CONFIRMATION
    ----------------------------------------------------- */

    const password =
        form.querySelector(
            "#password"
        );

    const confirmPassword =
        form.querySelector(
            "#confirm_password"
        );

    if (
        password &&
        confirmPassword &&
        password.value !==
        confirmPassword.value
    ) {

        showFieldError(
            confirmPassword,
            "Passwords do not match."
        );

        valid = false;

    }


    /* -----------------------------------------------------
       NUMBER
    ----------------------------------------------------- */

    form
        .querySelectorAll(
            "input[type='number']"
        )
        .forEach(function (input) {

            const min =
                input.getAttribute("min");

            const max =
                input.getAttribute("max");

            const value =
                parseFloat(input.value);

            if (
                min !== null &&
                value < parseFloat(min)
            ) {

                showFieldError(
                    input,
                    `Value must be at least ${min}.`
                );

                valid = false;

            }

            if (
                max !== null &&
                value > parseFloat(max)
            ) {

                showFieldError(
                    input,
                    `Value cannot exceed ${max}.`
                );

                valid = false;

            }

        });


    /* -----------------------------------------------------
       DATE
    ----------------------------------------------------- */

    form
        .querySelectorAll(
            "input[type='date']"
        )
        .forEach(function (input) {

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

            if (
                input.dataset.future ===
                "true" &&
                selectedDate < today
            ) {

                showFieldError(
                    input,
                    "Please select a future date."
                );

                valid = false;

            }

        });


    /* -----------------------------------------------------
       TERMS
    ----------------------------------------------------- */

    form
        .querySelectorAll(
            "input[data-terms]"
        )
        .forEach(function (checkbox) {

            if (!checkbox.checked) {

                showFieldError(
                    checkbox,
                    "You must accept the terms and conditions."
                );

                valid = false;

            }

        });


    return valid;

}


/* =========================================================
   EMAIL VALIDATION
========================================================= */

function isValidEmail(email) {

    const pattern =
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    return pattern.test(
        email.trim()
    );

}


/* =========================================================
   PHONE VALIDATION
========================================================= */

function isValidPhone(phone) {

    const cleaned =
        phone.replace(
            /[\s\-()]/g,
            ""
        );

    return /^(\+254|0)[17]\d{8}$/
        .test(cleaned);

}


/* =========================================================
   SHOW FIELD ERROR
========================================================= */

function showFieldError(
    input,
    message
) {

    input.classList.add(
        "is-invalid"
    );

    const existing =
        input.parentElement
            ?.querySelector(
                ".form-error"
            );

    if (existing) {

        existing.textContent =
            message;

        return;

    }

    const error =
        document.createElement("small");

    error.className =
        "form-error";

    error.textContent =
        message;

    input.parentElement
        ?.appendChild(error);

}


/* =========================================================
   CLEAR ERRORS
========================================================= */

function clearValidationErrors(form) {

    form
        .querySelectorAll(
            ".is-invalid"
        )
        .forEach(function (input) {

            input.classList.remove(
                "is-invalid"
            );

        });

    form
        .querySelectorAll(
            ".form-error"
        )
        .forEach(function (error) {

            error.remove();

        });

}


/* =========================================================
   PASSWORD CONFIRMATION
========================================================= */

function initializePasswordConfirmation() {

    const password =
        document.querySelector(
            "#password"
        );

    const confirmPassword =
        document.querySelector(
            "#confirm_password"
        );

    if (
        !password ||
        !confirmPassword
    ) {
        return;
    }

    confirmPassword.addEventListener(
        "input",
        function () {

            if (
                confirmPassword.value &&
                password.value !==
                confirmPassword.value
            ) {

                confirmPassword.classList.add(
                    "is-invalid"
                );

            } else {

                confirmPassword.classList.remove(
                    "is-invalid"
                );

            }

        }
    );

}


/* =========================================================
   REQUIRED FIELD HELPER
========================================================= */

function validateRequired(
    input,
    message = "This field is required."
) {

    if (!input || !input.value.trim()) {

        showFieldError(
            input,
            message
        );

        return false;

    }

    return true;

}


/* =========================================================
   KENYAN PHONE FORMAT
========================================================= */

function normalizeKenyanPhone(phone) {

    let value =
        phone.replace(
            /[\s\-()]/g,
            ""
        );

    if (
        value.startsWith("07") ||
        value.startsWith("01")
    ) {

        value =
            "+254" +
            value.substring(1);

    }

    return value;

}


/* =========================================================
   PUBLIC API
========================================================= */

window.BookingValidation = {

    validateForm,
    isValidEmail,
    isValidPhone,
    normalizeKenyanPhone,
    validateRequired

};