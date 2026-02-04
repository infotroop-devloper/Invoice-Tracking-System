-- Invoice Movement Tracking System Database Schema v2.0
-- Database: invoice_tracker

CREATE DATABASE IF NOT EXISTS abc;
USE abc;

-- Table: roles
CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    role_description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: users
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role_id INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

-- Table: invoice_stages
CREATE TABLE invoice_stages (
    stage_id INT AUTO_INCREMENT PRIMARY KEY,
    stage_name VARCHAR(100) NOT NULL UNIQUE,
    stage_order INT NOT NULL,
    next_role_id INT,
    sla_days INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (next_role_id) REFERENCES roles(role_id)
);

-- Table: projects
CREATE TABLE projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(100) NOT NULL UNIQUE,
    project_code VARCHAR(20),
    location VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: invoices
CREATE TABLE invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    vendor_name VARCHAR(200) NOT NULL,
    project_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    received_date DATE NOT NULL,
    invoice_amount DECIMAL(15,2),
    document_path VARCHAR(255),
    current_stage_id INT NOT NULL,
    current_holder_id INT NOT NULL,
    status ENUM('Active', 'Closed', 'Rejected', 'Pending Acceptance') DEFAULT 'Active',
    is_acknowledged TINYINT(1) DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id),
    FOREIGN KEY (current_stage_id) REFERENCES invoice_stages(stage_id),
    FOREIGN KEY (current_holder_id) REFERENCES users(user_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- Table: invoice_movements (Audit Trail)
CREATE TABLE invoice_movements (
    movement_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    from_stage_id INT,
    to_stage_id INT NOT NULL,
    from_user_id INT,
    to_user_id INT NOT NULL,
    remarks TEXT NOT NULL,
    is_acknowledged TINYINT(1) DEFAULT 0,
    acknowledged_at TIMESTAMP NULL,
    movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id),
    FOREIGN KEY (from_stage_id) REFERENCES invoice_stages(stage_id),
    FOREIGN KEY (to_stage_id) REFERENCES invoice_stages(stage_id),
    FOREIGN KEY (from_user_id) REFERENCES users(user_id),
    FOREIGN KEY (to_user_id) REFERENCES users(user_id)
);

-- Table: notifications
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    invoice_id INT NOT NULL,
    notification_type ENUM('Assignment', 'Reminder', 'Escalation') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id)
);

-- Insert default roles (MERGED HO Reception and Receptionist)
INSERT INTO roles (role_name, role_description) VALUES
('Admin', 'System Administrator with full access'),
('Store Manager', 'Upload and submit invoices from site/store'),
('Office Boy', 'Collect and deliver bills to head office'),
('HO Reception', 'Acknowledge, filter project-wise and allocate to purchase team'),
('Purchase Team', 'Verify invoices and forward to accounts'),
('Accounts', 'Approve and close invoices');

-- Insert default invoice stages (UPDATED - Removed duplicate reception stage)
INSERT INTO invoice_stages (stage_name, stage_order, next_role_id, sla_days) VALUES
('Received at Site/Store', 1, 2, 2),
('Handed to Office Boys', 2, 3, 1),
('Received by HO Reception', 3, 4, 2),
('Under Purchase Review', 4, 5, 3),
('Sent to Accounts', 5, 6, 3),
('Approved/Cleared', 6, NULL, 0);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, full_name, email, role_id) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@invoicetracker.com', 1);

-- Create indexes for better performance
CREATE INDEX idx_invoice_number ON invoices(invoice_number);
CREATE INDEX idx_current_holder ON invoices(current_holder_id);
CREATE INDEX idx_current_stage ON invoices(current_stage_id);
CREATE INDEX idx_invoice_movements_invoice ON invoice_movements(invoice_id);
CREATE INDEX idx_invoice_movements_date ON invoice_movements(movement_date);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);
CREATE INDEX idx_invoice_status ON invoices(status);
CREATE INDEX idx_invoice_acknowledged ON invoices(is_acknowledged);
