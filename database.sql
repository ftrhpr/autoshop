-- SQL to create tables in your MySQL database
-- Run this in phpMyAdmin or cPanel database manager

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Customers table
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(200),
    phone VARCHAR(32),
    email VARCHAR(150),
    plate_number VARCHAR(20) NOT NULL,
    car_mark VARCHAR(100),
    notes TEXT,
    last_service_at DATETIME NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    creation_date DATETIME NOT NULL,
    service_manager VARCHAR(100),
    service_manager_id INT NULL,
    customer_id INT NULL,
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
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (service_manager_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default admin user
INSERT INTO users (username, password, role) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Permissions system
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin', 'manager', 'user') NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role, permission_id)
);

-- Insert default permissions
INSERT INTO permissions (name, description) VALUES
('view_analytics', 'View analytics and reports'),
('create_invoices', 'Create new invoices'),
('view_invoices', 'View existing invoices'),
('export_invoices', 'Export invoices to file'),
('manage_customers', 'Manage customer database'),
('manage_vehicles', 'Manage vehicle database'),
('manage_prices', 'Manage parts, labor, and oil prices'),
('manage_users', 'Manage user accounts'),
('manage_permissions', 'Manage roles and permissions'),
('view_reports', 'View usage reports'),
('view_logs', 'View audit logs');

-- Assign default permissions to roles
INSERT INTO role_permissions (role, permission_id) VALUES
-- Admin gets all permissions
('admin', 1), ('admin', 2), ('admin', 3), ('admin', 4), ('admin', 5),
('admin', 6), ('admin', 7), ('admin', 8), ('admin', 9), ('admin', 10), ('admin', 11),

-- Manager gets core business permissions
('manager', 1), ('manager', 2), ('manager', 3), ('manager', 5), ('manager', 6),

-- User gets basic permissions
('user', 2), ('user', 3);
-- Default password: admin123
-- Note: Change this password after first login for security

-- Table to store password reset tokens (one-time use)
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (token_hash),
    INDEX (expires_at)
);

-- Audit logs for admin actions
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Permissions & role mappings (for Pro features)
-- Note: Permissions system is now defined above with the main schema

-- Table for predefined labor items
CREATE TABLE labors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    default_price DECIMAL(10,2) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX (name)
);

-- Table for predefined parts
CREATE TABLE parts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    default_price DECIMAL(10,2) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX (name)
);

-- Vehicle database tables from Car2DB API
CREATE TABLE vehicle_types (
    id INT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE vehicle_makes (
    id INT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES vehicle_types(id),
    INDEX (name)
);

CREATE TABLE vehicle_models (
    id INT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    make_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (make_id) REFERENCES vehicle_makes(id),
    INDEX (name)
);