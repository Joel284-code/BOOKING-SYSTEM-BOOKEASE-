<?php
/*
|--------------------------------------------------------------------------
| BookEase Footer
|--------------------------------------------------------------------------
*/

$appName = defined('APP_NAME')
    ? APP_NAME
    : 'BookEase';

$appVersion = defined('APP_VERSION')
    ? APP_VERSION
    : '1.0.0';
?>

<footer class="site-footer">

    <div class="footer-content">

        <div class="footer-main">

            &copy; <?= date('Y') ?>

            <strong>
                <?= htmlspecialchars(
                    $appName,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>

            — Booking Management System

        </div>

        <div class="footer-version">

            Version
            <?= htmlspecialchars(
                $appVersion,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    </div>

</footer>


<style>

/*
|--------------------------------------------------------------------------
| Footer
|--------------------------------------------------------------------------
*/

.site-footer {

    margin-left: 250px;

    padding: 25px 30px;

    text-align: center;

    color: #64748b;

    font-size: 13px;

    border-top: 1px solid #e2e8f0;

    background: #ffffff;

    margin-top: 40px;

}

.footer-main {

    line-height: 1.6;

}

.footer-main strong {

    color: #1e293b;

}

.footer-version {

    margin-top: 5px;

    color: #94a3b8;

}


/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

.flash-message {

    transition:
        opacity 0.5s ease,
        transform 0.5s ease;

}


/*
|--------------------------------------------------------------------------
| Responsive Footer
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .site-footer {

        margin-left: 70px;

        padding:
            20px 15px;

    }

}


@media (max-width: 480px) {

    .site-footer {

        margin-left: 70px;

        font-size: 12px;

    }

}

</style>


<script>

/*
|--------------------------------------------------------------------------
| BookEase Footer JavaScript
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {


        /*
        |--------------------------------------------------------------------------
        | Auto-hide Flash Messages
        |--------------------------------------------------------------------------
        */

        const messages =
            document.querySelectorAll(
                '.flash-message'
            );


        if (messages.length > 0) {

            setTimeout(
                function () {

                    messages.forEach(
                        function (message) {

                            message.style.transition =
                                'opacity 0.5s ease';

                            message.style.opacity =
                                '0';


                            setTimeout(
                                function () {

                                    if (
                                        message &&
                                        message.parentNode
                                    ) {

                                        message.remove();

                                    }

                                },
                                500
                            );

                        }
                    );

                },
                4000
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Confirm Delete / Action
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'click',
            function (event) {

                const element =
                    event.target.closest(
                        '[data-confirm]'
                    );


                if (!element) {

                    return;

                }


                const message =
                    element.getAttribute(
                        'data-confirm'
                    )
                    ||
                    'Are you sure?';


                if (!window.confirm(message)) {

                    event.preventDefault();

                    event.stopPropagation();

                }

            }
        );

    }
);

</script>


</body>

</html>