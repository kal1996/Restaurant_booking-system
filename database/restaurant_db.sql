CREATE DATABASE restaurant_db;

CREATE TABLE bookings (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    table_id INT NOT NULL,
    booking_datetime DATETIME NOT NULL,
    meal_type VARCHAR(50) NOT NULL,
    guests INT NOT NULL,
    special_requests TEXT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') 
        DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    booking_date DATE NULL,
    booking_time TIME NULL,
    PRIMARY KEY (id)
);


CREATE TABLE comments (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    username VARCHAR(100) NOT NULL,
    rating INT DEFAULT 5,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);


CREATE TABLE comment_replies (
    id INT NOT NULL AUTO_INCREMENT,
    comment_id INT NOT NULL,
    admin_id INT NOT NULL,
    admin_name VARCHAR(100) NOT NULL,
    reply_message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,
    PRIMARY KEY (id)
);

CREATE TABLE kitchen_orders (
    id INT NOT NULL AUTO_INCREMENT,
    pre_order_id INT NOT NULL,
    chef_id INT NULL,

    preparation_status ENUM(
        'pending',
        'in_progress',
        'ready',
        'served'
    ) DEFAULT 'pending',

    estimated_time INT DEFAULT 30,
    actual_time INT DEFAULT NULL,

    chef_notes TEXT DEFAULT NULL,

    started_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    KEY fk_pre_order (pre_order_id),
    KEY fk_chef (chef_id)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE menu_items (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,

    category ENUM(
        'Local & Cultural Dishes',
        'Continental & Fusion'
    ) NOT NULL,

    price DECIMAL(10,2) NOT NULL,

    image VARCHAR(255) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    active TINYINT(1) DEFAULT 1,

    PRIMARY KEY (id)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_reference VARCHAR(100) NOT NULL,
    user_id INT NOT NULL,
    order_id INT NULL,
    booking_id INT NULL,
    payment_type ENUM('order', 'booking', 'deposit') NOT NULL,
    payment_method ENUM('telebirr', 'cbe', 'cash') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ETB',
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    transaction_id VARCHAR(255) NULL,
    payment_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    
    INDEX (payment_reference),
    INDEX (user_id),
    INDEX (order_id),
    INDEX (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;




CREATE TABLE pre_orders (
    id INT NOT NULL AUTO_INCREMENT,
    booking_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'confirmed', 'preparing', 'ready') DEFAULT 'pending',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


  CREATE TABLE pre_order_items (
    id INT NOT NULL AUTO_INCREMENT,
    pre_order_id INT NOT NULL,
    menu_item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX (pre_order_id),
    INDEX (menu_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE reports (
    id INT NOT NULL AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('chef_to_admin', 'admin_to_chef', 'general') DEFAULT 'general',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX (sender_id),
    INDEX (receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE restaurant_tables (
    id INT NOT NULL AUTO_INCREMENT,
    table_label VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    location ENUM('Main Hall', 'VIP', 'Patio', 'Group') NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    status ENUM('Available', 'Occupied', 'Reserved') DEFAULT 'Available',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    image VARCHAR(255) DEFAULT NULL,
    role ENUM('user', 'admin', 'chef') NOT NULL DEFAULT 'user',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



INSERT INTO menu_items (name, category, price, image, active) VALUES
( 'Spaghetti', 'Continental & Fusion Dishes', 300.00, '1766300322_spageti.jpg', 1),
( 'Pizza', 'Continental & Fusion Dishes', 500.00, '1766300148_pizza.jpg', 1),
( 'Shawarma', 'Continental & Fusion Dishes', 450.00, '1766300260_shawerma.jpg', 1),
( 'Alcha wot', 'Local & Cultural Dishes', 450.00, '1766300008_alcha wot.jpg', 1),
( 'Burger', 'Continental & Fusion Dishes', 550.00, '1766300107_burgure.jpg',  1),
( 'Shiro', 'Local & Cultural Dishes', 200.00, '1766299909_shiro.jpg',  1),
( 'Beyaynet', 'Local & Cultural Dishes', 300.00, '1766299850_beyaynet.jpg',  1),
( 'Kittfo', 'Local & Cultural Dishes', 600.00, '1766299558_kitfo.jpg',  1),
( 'FintaFinto', 'Local & Cultural Dishes', 500.00, '1766987964_fintafnto.jpg',  1),
( 'Gored Gored', 'Local & Cultural Dishes', 550.00, '1766299646_gored gored.jpg',  1),
( 'Shekila Tibs', 'Local & Cultural Dishes', 550.00, '1766299702_shekila tibs.jpg',  1),
( 'Doro Wot', 'Local & Cultural Dishes', 500.00, '1766299768_doro wot.jpg',  1),
( 'Salad', 'Continental & Fusion Dishes', 360.00, '1766300692_salad.jpg',  1),
( 'Cake', 'Desserts', 700.00, '1766300884_cack.jpg', '2025-12-20 23:08:04', 1),
( 'Slice Cake', 'Desserts', 200.00, '1766300917_slice cake.jpg',  1),
( 'Ice cream', 'Desserts', 410.00, '1766300962_ice cream.jpg',  1),
( 'Avocado Juice', 'Drink', 150.00, '1766301314_avocado juice.jpg', '2025-12-20 23:15:14', 1),
( 'Pineapple Juice', 'Drink', 150.00, '1766301365_Pine apple Juice.jpg', '2025-12-20 23:16:05', 1),
(50, 'Strawberry Juice', 'Drink', 150.00, '1766301433_strawberry juice.jpg', '2025-12-20 23:17:13', 1);



INSERT INTO restaurant_tables (id, table_label, capacity, location, image, status) VALUES
( 'T1', 3, 'Main Hall', '1766296298_other 2.jpg', 'Available'),
( 'T3', 2, 'Main Hall', '1766295869_2s1.jpg', 'Available'),
( 'T2', 2, 'Main Hall', '1766295840_2s1.jpg', 'Available'),
( 'VIP1', 6, 'VIP', '1766296427_vip1.jpg', 'Available'),
( 'T2', 5, 'Main Hall', '1766296343_other 1.jpg', 'Available'),
( 'T1', 2, 'Main Hall', '1766295794_2s1.jpg', 'Available'),
( 'P1', 4, 'Patio', '1766296837_patio.jpg', 'Available'),
( 'T4', 2, 'Main Hall', '1766295889_2s1.jpg', 'Available'),
( 'T5', 2, 'Main Hall', '1766295922_2s1.jpg', 'Available'),
( 'T6', 2, 'Main Hall', '1766295945_2s1.jpg', 'Available'),
( 'P2', 4, 'Patio', '1766297240_patio.jpg', 'Available'),
( 'T3', 3, 'Main Hall', '1766297319_4s.jpg', 'Available'),
( 'T4', 3, 'Main Hall', '1766297530_4s.jpg', 'Available'),
( 'T7', 4, 'Main Hall', '1766297615_s44.jpg', 'Available'),
( 'T8', 4, 'Main Hall', '1766297639_s44.jpg', 'Available'),
( 'T9', 4, 'Main Hall', '1766297660_s44.jpg', 'Available'),
( 'T10', 4, 'Main Hall', '1766297682_s44.jpg', 'Available'),
( 'T11', 4, 'Main Hall', '1766297698_s44.jpg', 'Available'),
( 'T12', 4, 'Main Hall', '1766297714_s44.jpg', 'Available'),
( 'VIP2', 5, 'VIP', '1766298016_vip1.jpg', 'Available'),
( 'VIP3', 4, 'VIP', '1766298047_vip1.jpg', 'Available'),
( 'VIP4', 6, 'VIP', '1766298114_vip2.jpg', 'Available'),
( 'VIP5', 5, 'VIP', '1766298133_vip2.jpg', 'Available'),
( 'VIP6', 4, 'VIP', '1766298150_vip2.jpg', 'Available'),
( 'G1', 15, 'Group', '1766298347_group1.jpg', 'Available'),
( 'G2', 10, 'Group', '1766298415_group1.jpg', 'Available'),
( 'G3', 20, 'Group', '1766298460_group1.jpg', 'Available'),
( 'G4', 6, 'Group', '1766298540_group2.jpg', 'Available'),
( 'G5', 8, 'Group', '1766298561_group2.jpg', 'Available'),
( 'G6', 12, 'Group', '1766298617_group2.jpg', 'Available'),
( 'P3', 3, 'Patio', '1766298769_patio.jpg', 'Available');
