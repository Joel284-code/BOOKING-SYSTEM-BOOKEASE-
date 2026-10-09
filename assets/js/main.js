/* =========================================================
   BOOKING SYSTEM - MAIN JAVASCRIPT
========================================================= */

"use strict";


/* =========================================================
   DOCUMENT READY
========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    initializeSidebar();
    initializeDropdowns();
    initializeModals();
    initializeAlerts();
    initializeMobileMenu();
    initializePasswordToggle();
    initializeConfirmActions();
    initializeAutoHideMessages();

});


/* =========================================================
   SIDEBAR
========================================================= */

function initializeSidebar() {

    const sidebarToggle =
        document.querySelector("#sidebarToggle");

    const sidebar =
        document.querySelector(".sidebar");

    const overlay =
        document.querySelector(".sidebar-overlay");

    if (!sidebarToggle || !sidebar) {
        return;
    }

    sidebarToggle.addEventListener("click", function () {

        sidebar.classList.toggle("active");

        if (overlay) {
            overlay.classList.toggle("active");
        }

    });

    if (overlay) {

        overlay.addEventListener("click", function () {

            sidebar.classList.remove("active");
            overlay.classList.remove("active");

        });

    }

}


/* =========================================================
   DROPDOWNS
========================================================= */

function initializeDropdowns() {

    const dropdownButtons =
        document.querySelectorAll("[data-dropdown-toggle]");

    dropdownButtons.forEach(function (button) {

        button.addEventListener("click", function (event) {

            event.stopPropagation();

            const targetId =
                button.getAttribute("data-dropdown-toggle");

            const dropdown =
                document.getElementById(targetId);

            if (!dropdown) {
                return;
            }

            document
                .querySelectorAll(".dropdown-menu.show")
                .forEach(function (menu) {

                    if (menu !== dropdown) {
                        menu.classList.remove("show");
                    }

                });

            dropdown.classList.toggle("show");

        });

    });


    document.addEventListener("click", function () {

        document
            .querySelectorAll(".dropdown-menu.show")
            .forEach(function (menu) {

                menu.classList.remove("show");

            });

    });

}


/* =========================================================
   MODALS
========================================================= */

function initializeModals() {

    document
        .querySelectorAll("[data-modal-open]")
        .forEach(function (button) {

            button.addEventListener("click", function () {

                const modalId =
                    button.getAttribute("data-modal-open");

                const modal =
                    document.getElementById(modalId);

                if (modal) {
                    modal.classList.add("show");
                }

            });

        });


    document
        .querySelectorAll("[data-modal-close]")
        .forEach(function (button) {

            button.addEventListener("click", function () {

                const modal =
                    button.closest(".modal-overlay");

                if (modal) {
                    modal.classList.remove("show");
                }

            });

        });


    document
        .querySelectorAll(".modal-overlay")
        .forEach(function (overlay) {

            overlay.addEventListener("click", function (event) {

                if (event.target === overlay) {
                    overlay.classList.remove("show");
                }

            });

        });


    document.addEventListener("keydown", function (event) {

        if (event.key !== "Escape") {
            return;
        }

        document
            .querySelectorAll(".modal-overlay.show")
            .forEach(function (modal) {

                modal.classList.remove("show");

            });

    });

}


/* =========================================================
   ALERT CLOSE
========================================================= */

function initializeAlerts() {

    document
        .querySelectorAll("[data-alert-close]")
        .forEach(function (button) {

            button.addEventListener("click", function () {

                const alert =
                    button.closest(".alert");

                if (alert) {
                    alert.remove();
                }

            });

        });

}


/* =========================================================
   AUTO HIDE ALERTS
========================================================= */

function initializeAutoHideMessages() {

    setTimeout(function () {

        document
            .querySelectorAll(".alert[data-auto-hide]")
            .forEach(function (alert) {

                alert.style.opacity = "0";

                setTimeout(function () {
                    alert.remove();
                }, 400);

            });

    }, 5000);

}


/* =========================================================
   MOBILE MENU
========================================================= */

function initializeMobileMenu() {

    const menuButton =
        document.querySelector("#mobileMenuButton");

    const mobileMenu =
        document.querySelector("#mobileMenu");

    if (!menuButton || !mobileMenu) {
        return;
    }

    menuButton.addEventListener("click", function () {

        mobileMenu.classList.toggle("show");

    });

}


/* =========================================================
   PASSWORD VISIBILITY
========================================================= */

function initializePasswordToggle() {

    document
        .querySelectorAll("[data-password-toggle]")
        .forEach(function (button) {

            button.addEventListener("click", function () {

                const inputId =
                    button.getAttribute("data-password-toggle");

                const input =
                    document.getElementById(inputId);

                if (!input) {
                    return;
                }

                if (input.type === "password") {

                    input.type = "text";

                    button.textContent = "Hide";

                } else {

                    input.type = "password";

                    button.textContent = "Show";

                }

            });

        });

}


/* =========================================================
   CONFIRM ACTIONS
========================================================= */

function initializeConfirmActions() {

    document
        .querySelectorAll("[data-confirm]")
        .forEach(function (element) {

            element.addEventListener("click", function (event) {

                const message =
                    element.getAttribute("data-confirm") ||
                    "Are you sure you want to continue?";

                if (!window.confirm(message)) {
                    event.preventDefault();
                }

            });

        });

}


/* =========================================================
   DELETE CONFIRMATION
========================================================= */

function confirmDelete(message) {

    return window.confirm(
        message ||
        "Are you sure you want to delete this item?"
    );

}


/* =========================================================
   FORMAT CURRENCY
========================================================= */

function formatCurrency(amount) {

    amount = Number(amount) || 0;

    return new Intl.NumberFormat("en-KE", {
        style: "currency",
        currency: "KES",
        minimumFractionDigits: 2
    }).format(amount);

}


/* =========================================================
   FORMAT NUMBER
========================================================= */

function formatNumber(number) {

    return new Intl.NumberFormat("en-KE")
        .format(Number(number) || 0);

}


/* =========================================================
   AJAX HELPER
========================================================= */

async function postData(url, data = {}) {

    try {

        const response = await fetch(url, {

            method: "POST",

            headers: {
                "Content-Type":
                    "application/x-www-form-urlencoded; charset=UTF-8"
            },

            body: new URLSearchParams(data)

        });

        return await response.json();

    } catch (error) {

        console.error("Request failed:", error);

        return {
            success: false,
            message: "Unable to complete the request."
        };

    }

}


/* =========================================================
   SHOW LOADING
========================================================= */

function showLoading(button) {

    if (!button) {
        return;
    }

    button.dataset.originalText =
        button.innerHTML;

    button.disabled = true;

    button.innerHTML =
        "Please wait...";

}


/* =========================================================
   HIDE LOADING
========================================================= */

function hideLoading(button) {

    if (!button) {
        return;
    }

    button.disabled = false;

    if (button.dataset.originalText) {

        button.innerHTML =
            button.dataset.originalText;

    }

}


/* =========================================================
   GLOBAL TOAST
========================================================= */

function showToast(message, type = "success") {

    const toast =
        document.createElement("div");

    toast.className =
        `alert alert-${type}`;

    toast.style.position = "fixed";
    toast.style.top = "20px";
    toast.style.right = "20px";
    toast.style.zIndex = "9999";
    toast.style.minWidth = "280px";

    toast.textContent = message;

    document.body.appendChild(toast);

    setTimeout(function () {

        toast.style.opacity = "0";

        setTimeout(function () {
            toast.remove();
        }, 300);

    }, 3500);

}


/* =========================================================
   GLOBAL EXPORTS
========================================================= */

window.BookingSystem = {

    formatCurrency,
    formatNumber,
    postData,
    showLoading,
    hideLoading,
    showToast,
    confirmDelete

};