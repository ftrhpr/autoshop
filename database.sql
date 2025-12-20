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
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    label VARCHAR(150) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Seed basic permissions
INSERT INTO permissions (name, label, description) VALUES
('export_csv', 'Export Invoices CSV', 'Allow exporting invoices as CSV'),
('view_logs', 'View Audit Logs', 'Allow viewing admin audit logs'),
('manage_users', 'Manage Users', 'Create and modify user accounts'),
('manage_invoices', 'Manage Invoices', 'Create/Edit invoices'),
('view_charts', 'View Analytics', 'Access dashboard charts'),
('manage_customers', 'Manage Customers', 'Create/Edit/Delete customers');

-- Assign defaults: admins get all, managers get invoice, chart and customer access
INSERT INTO role_permissions (role, permission_id)
SELECT 'admin', id FROM permissions;
INSERT INTO role_permissions (role, permission_id)
SELECT 'manager', id FROM permissions WHERE name IN ('manage_invoices', 'view_charts', 'manage_customers');

-- Notifications Table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    invoice_id INT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);