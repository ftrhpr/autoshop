-- SQL to create tables in your MySQL database
-- Run this in phpMyAdmin or cPanel database manager

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    creation_date DATETIME NOT NULL,
    service_manager VARCHAR(100),
    customer_name VARCHAR(100),
    phone VARCHAR(20),
    car_mark VARCHAR(100),
    plate_number VARCHAR(20),
    mileage VARCHAR(50),
    items JSON, -- Store items as JSON array
    parts_total DECIMAL(10,2) DEFAULT 0,
    service_total DECIMAL(10,2) DEFAULT 0,
    grand_total DECIMAL(10,2) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Insert default admin user
INSERT INTO users (username, password, role) VALUES ('admin', '$2y$10$examplehashedpassword', 'admin');
-- Note: Replace with actual hashed password, e.g., password_hash('yourpassword', PASSWORD_DEFAULT)