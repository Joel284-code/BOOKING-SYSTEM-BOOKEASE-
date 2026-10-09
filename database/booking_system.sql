-- ============================================================
-- BOOKEASE BOOKING SYSTEM
-- COMPLETE FIXED DATABASE
-- MySQL 8.x / MariaDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS booking_system
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE booking_system;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- REMOVE OLD OBJECTS
-- ============================================================

DROP VIEW IF EXISTS booking_details_view;

DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- USERS
-- ============================================================

CREATE TABLE users (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(150) NOT NULL,

    email VARCHAR(190) NOT NULL UNIQUE,

    phone VARCHAR(30) DEFAULT NULL,

    password VARCHAR(255) NOT NULL,

    role ENUM(
        'admin',
        'customer',
        'staff'
    ) NOT NULL DEFAULT 'customer',

    status ENUM(
        'active',
        'inactive',
        'suspended'
    ) NOT NULL DEFAULT 'active',

    email_verified TINYINT(1) NOT NULL DEFAULT 0,

    verification_token VARCHAR(255) DEFAULT NULL,

    reset_token VARCHAR(255) DEFAULT NULL,

    reset_expires DATETIME DEFAULT NULL,

    last_login DATETIME DEFAULT NULL,

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_users_role (role),

    INDEX idx_users_status (status),

    INDEX idx_users_verification_token (
        verification_token
    ),

    INDEX idx_users_reset_token (
        reset_token
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- SERVICES
-- ============================================================

CREATE TABLE services (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(150) NOT NULL,

    description TEXT DEFAULT NULL,

    price DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    duration VARCHAR(100) NOT NULL DEFAULT '1 hour',

    status ENUM(
        'active',
        'inactive'
    ) NOT NULL DEFAULT 'active',

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_services_status (status),

    INDEX idx_services_name (name)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- STAFF
-- ============================================================

CREATE TABLE staff (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL UNIQUE,

    staff_number VARCHAR(50) NOT NULL UNIQUE,

    position VARCHAR(100) NOT NULL DEFAULT 'Staff',

    bio TEXT DEFAULT NULL,

    status ENUM(
        'active',
        'inactive'
    ) NOT NULL DEFAULT 'active',

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_staff_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_staff_status (status)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- BOOKINGS
-- ============================================================

CREATE TABLE bookings (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    service_id INT UNSIGNED NOT NULL,

    staff_id INT UNSIGNED DEFAULT NULL,

    booking_reference VARCHAR(50)
        DEFAULT NULL UNIQUE,

    booking_date DATE NOT NULL,

    booking_time TIME NOT NULL,

    customer_name VARCHAR(150)
        DEFAULT NULL,

    customer_email VARCHAR(190)
        DEFAULT NULL,

    customer_phone VARCHAR(30)
        DEFAULT NULL,

    notes TEXT DEFAULT NULL,

    amount DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    total_amount DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    status ENUM(
        'pending',
        'confirmed',
        'completed',
        'cancelled',
        'rejected',
        'no_show'
    ) NOT NULL DEFAULT 'pending',

    payment_status ENUM(
        'unpaid',
        'pending',
        'paid',
        'failed',
        'refunded'
    ) NOT NULL DEFAULT 'unpaid',

    cancellation_reason TEXT DEFAULT NULL,

    cancelled_at DATETIME DEFAULT NULL,

    confirmed_at DATETIME DEFAULT NULL,

    completed_at DATETIME DEFAULT NULL,

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_booking_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_booking_service
        FOREIGN KEY (service_id)
        REFERENCES services(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_booking_staff
        FOREIGN KEY (staff_id)
        REFERENCES staff(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_bookings_user (
        user_id
    ),

    INDEX idx_bookings_service (
        service_id
    ),

    INDEX idx_bookings_staff (
        staff_id
    ),

    INDEX idx_bookings_date (
        booking_date
    ),

    INDEX idx_bookings_time (
        booking_time
    ),

    INDEX idx_bookings_status (
        status
    ),

    INDEX idx_bookings_payment_status (
        payment_status
    ),

    INDEX idx_bookings_datetime (
        booking_date,
        booking_time
    ),

    UNIQUE KEY uq_staff_booking_slot (
        staff_id,
        booking_date,
        booking_time
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- PAYMENTS
-- ============================================================

CREATE TABLE payments (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    booking_id INT UNSIGNED NOT NULL,

    user_id INT UNSIGNED NOT NULL,

    amount DECIMAL(12,2)
        NOT NULL DEFAULT 0.00,

    payment_method ENUM(
        'mpesa',
        'card',
        'cash'
    ) NOT NULL,

    transaction_reference VARCHAR(100)
        NOT NULL UNIQUE,

    transaction_id VARCHAR(150)
        DEFAULT NULL,

    phone VARCHAR(30)
        DEFAULT NULL,

    status ENUM(
        'pending',
        'paid',
        'failed',
        'refunded'
    ) NOT NULL DEFAULT 'pending',

    paid_at DATETIME DEFAULT NULL,

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_payment_booking
        FOREIGN KEY (booking_id)
        REFERENCES bookings(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_payment_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_payment_booking (
        booking_id
    ),

    INDEX idx_payment_user (
        user_id
    ),

    INDEX idx_payment_status (
        status
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- NOTIFICATIONS
-- ============================================================

CREATE TABLE notifications (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    user_id INT UNSIGNED NOT NULL,

    booking_id INT UNSIGNED DEFAULT NULL,

    title VARCHAR(200) NOT NULL,

    message TEXT NOT NULL,

    type ENUM(
        'booking',
        'payment',
        'system',
        'reminder'
    ) NOT NULL DEFAULT 'system',

    is_read TINYINT(1) NOT NULL DEFAULT 0,

    read_at DATETIME DEFAULT NULL,

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notification_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_notification_booking
        FOREIGN KEY (booking_id)
        REFERENCES bookings(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_notifications_user (
        user_id
    ),

    INDEX idx_notifications_booking (
        booking_id
    ),

    INDEX idx_notifications_read (
        is_read
    ),

    INDEX idx_notifications_created (
        created_at
    )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- SETTINGS
-- ============================================================

CREATE TABLE settings (

    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    setting_key VARCHAR(100)
        NOT NULL UNIQUE,

    setting_value TEXT DEFAULT NULL,

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- ADMIN ACCOUNT
-- ============================================================
-- Email: admin@bookease.test
-- Password: password
-- ============================================================

INSERT INTO users
(
    name,
    email,
    phone,
    password,
    role,
    status,
    email_verified
)
VALUES
(
    'System Administrator',
    'admin@bookease.test',
    '0712345678',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC5fHjY5VJf4Qh3h3j1S',
    'admin',
    'active',
    1
);


-- ============================================================
-- CUSTOMER ACCOUNT
-- ============================================================
-- Email: customer@bookease.test
-- Password: password
-- ============================================================

INSERT INTO users
(
    name,
    email,
    phone,
    password,
    role,
    status,
    email_verified
)
VALUES
(
    'Demo Customer',
    'customer@bookease.test',
    '0723456789',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC5fHjY5VJf4Qh3h3j1S',
    'customer',
    'active',
    1
);


-- ============================================================
-- STAFF ACCOUNT
-- ============================================================
-- Email: staff@bookease.test
-- Password: password
-- ============================================================

INSERT INTO users
(
    name,
    email,
    phone,
    password,
    role,
    status,
    email_verified
)
VALUES
(
    'John Staff',
    'staff@bookease.test',
    '0734567890',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC5fHjY5VJf4Qh3h3j1S',
    'staff',
    'active',
    1
);


-- ============================================================
-- STAFF PROFILE
-- ============================================================

INSERT INTO staff
(
    user_id,
    staff_number,
    position,
    bio,
    status
)

SELECT
    id,
    'STF-001',
    'Booking Officer',
    'Demo booking system staff account.',
    'active'

FROM users

WHERE email = 'staff@bookease.test'

LIMIT 1;


-- ============================================================
-- SERVICES
-- ============================================================

INSERT INTO services
(
    name,
    description,
    price,
    duration,
    status
)

VALUES

(
    'General Consultation',
    'Standard consultation and appointment service.',
    1000.00,
    '1 hour',
    'active'
),

(
    'Premium Consultation',
    'Extended consultation with additional support.',
    2500.00,
    '2 hours',
    'active'
),

(
    'Website Development',
    'Professional website development service.',
    15000.00,
    '7 days',
    'active'
),

(
    'Computer Maintenance',
    'Computer troubleshooting, maintenance and software support.',
    1500.00,
    '2 hours',
    'active'
),

(
    'ICT Technical Support',
    'Technical support for computers, networks and related systems.',
    2000.00,
    '2 hours',
    'active'
);


-- ============================================================
-- SETTINGS
-- ============================================================

INSERT INTO settings
(
    setting_key,
    setting_value
)

VALUES

('site_name', 'BookEase'),

('site_email', 'info@bookease.test'),

('site_phone', '0712345678'),

('currency', 'KSh'),

('currency_code', 'KES'),

('timezone', 'Africa/Nairobi'),

('booking_status_default', 'pending'),

('payment_currency', 'KES'),

('business_name', 'BookEase Booking System'),

('business_address', 'Kenya');


-- ============================================================
-- DEMO PENDING BOOKING
-- ============================================================

INSERT INTO bookings
(
    user_id,
    service_id,
    staff_id,
    booking_reference,
    booking_date,
    booking_time,
    customer_name,
    customer_email,
    customer_phone,
    notes,
    amount,
    total_amount,
    status,
    payment_status
)

SELECT

    u.id,

    s.id,

    NULL,

    'BK-DEMO-0002',

    DATE_ADD(
        CURDATE(),
        INTERVAL 2 DAY
    ),

    '14:00:00',

    u.name,

    u.email,

    u.phone,

    'Demo pending booking for administrator testing.',

    s.price,

    s.price,

    'pending',

    'unpaid'

FROM users u

JOIN services s
    ON s.name = 'Premium Consultation'

WHERE u.email = 'customer@bookease.test'

LIMIT 1;


-- ============================================================
-- DEMO CONFIRMED BOOKING
-- ============================================================

INSERT INTO bookings
(
    user_id,
    service_id,
    staff_id,
    booking_reference,
    booking_date,
    booking_time,
    customer_name,
    customer_email,
    customer_phone,
    notes,
    amount,
    total_amount,
    status,
    payment_status,
    confirmed_at
)

SELECT

    u.id,

    s.id,

    st.id,

    'BK-DEMO-0001',

    DATE_ADD(
        CURDATE(),
        INTERVAL 1 DAY
    ),

    '10:00:00',

    u.name,

    u.email,

    u.phone,

    'Demo confirmed booking.',

    s.price,

    s.price,

    'confirmed',

    'unpaid',

    NOW()

FROM users u

JOIN services s
    ON s.name = 'General Consultation'

JOIN staff st
    ON st.staff_number = 'STF-001'

WHERE u.email = 'customer@bookease.test'

LIMIT 1;


-- ============================================================
-- PENDING BOOKING NOTIFICATION
-- ============================================================

INSERT INTO notifications
(
    user_id,
    booking_id,
    title,
    message,
    type,
    is_read
)

SELECT

    u.id,

    b.id,

    'Booking Received',

    CONCAT(
        'Your booking for ',
        s.name,
        ' is pending confirmation.'
    ),

    'booking',

    0

FROM bookings b

JOIN users u
    ON b.user_id = u.id

JOIN services s
    ON b.service_id = s.id

WHERE b.booking_reference = 'BK-DEMO-0002'

LIMIT 1;


-- ============================================================
-- CONFIRMED BOOKING NOTIFICATION
-- ============================================================

INSERT INTO notifications
(
    user_id,
    booking_id,
    title,
    message,
    type,
    is_read
)

SELECT

    u.id,

    b.id,

    'Booking Confirmed',

    CONCAT(
        'Your booking for ',
        s.name,
        ' has been confirmed.'
    ),

    'booking',

    0

FROM bookings b

JOIN users u
    ON b.user_id = u.id

JOIN services s
    ON b.service_id = s.id

WHERE b.booking_reference = 'BK-DEMO-0001'

LIMIT 1;


-- ============================================================
-- WELCOME NOTIFICATION
-- ============================================================

INSERT INTO notifications
(
    user_id,
    booking_id,
    title,
    message,
    type,
    is_read
)

SELECT

    id,

    NULL,

    'Welcome to BookEase',

    'Your BookEase account is ready. You can now make and manage bookings.',

    'system',

    0

FROM users

WHERE email = 'customer@bookease.test'

LIMIT 1;


-- ============================================================
-- BOOKING DETAILS VIEW
-- ============================================================

CREATE VIEW booking_details_view AS

SELECT

    b.id,

    b.booking_reference,

    b.booking_date,

    b.booking_time,

    b.status,

    b.payment_status,

    b.amount,

    b.total_amount,

    b.customer_name,

    b.customer_email,

    b.customer_phone,

    b.notes,

    b.cancelled_at,

    b.confirmed_at,

    b.completed_at,

    b.created_at,

    b.updated_at,

    u.id AS user_id,

    u.name AS account_name,

    u.email AS account_email,

    u.phone AS account_phone,

    s.id AS service_id,

    s.name AS service_name,

    s.description AS service_description,

    s.price AS service_price,

    s.duration,

    st.id AS staff_id,

    st.staff_number,

    st.position AS staff_position,

    su.name AS staff_name

FROM bookings b

INNER JOIN users u
    ON b.user_id = u.id

INNER JOIN services s
    ON b.service_id = s.id

LEFT JOIN staff st
    ON b.staff_id = st.id

LEFT JOIN users su
    ON st.user_id = su.id;


-- ============================================================
-- FINISHED
-- ============================================================