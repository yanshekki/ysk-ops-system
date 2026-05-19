-- YSK Operations System Database Schema
-- Run this in your MySQL to create the database and tables

CREATE DATABASE IF NOT EXISTS ysk_ops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ysk_ops;

-- Users table (team members + admins)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'pm', 'developer', 'finance', 'viewer') DEFAULT 'viewer',
    phone VARCHAR(20),
    avatar VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Clients table
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    notes TEXT,
    status ENUM('active', 'inactive', 'lead') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Projects table (linked to services)
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    service_type ENUM('ai_automation', 'app_development', 'cloud_security', 'web3_blockchain', 'other') NOT NULL,
    status ENUM('planning', 'in_progress', 'review', 'completed', 'on_hold', 'cancelled') DEFAULT 'planning',
    start_date DATE,
    end_date DATE,
    budget DECIMAL(12,2) DEFAULT 0,
    progress_percent INT DEFAULT 0 CHECK (progress_percent BETWEEN 0 AND 100),
    assigned_pm_id INT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_pm_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Tasks table
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    assigned_to_id INT,
    status ENUM('todo', 'in_progress', 'review', 'done') DEFAULT 'todo',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    due_date DATE,
    estimated_hours DECIMAL(6,2) DEFAULT 0,
    logged_hours DECIMAL(6,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Invoices table
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    client_id INT NOT NULL,
    project_id INT,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax_percent DECIMAL(5,2) DEFAULT 0,
    total_amount DECIMAL(12,2) DEFAULT 0,
    status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    notes TEXT,
    pdf_path VARCHAR(255),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Invoice items (line items)
CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

-- Timesheets (for billing & tracking)
CREATE TABLE timesheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_id INT NOT NULL,
    task_id INT,
    work_date DATE NOT NULL,
    hours DECIMAL(5,2) NOT NULL,
    description TEXT,
    is_approved BOOLEAN DEFAULT FALSE,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Activity log (optional audit)
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default admin user (password: admin123 - CHANGE IMMEDIATELY!)
INSERT INTO users (username, password_hash, full_name, email, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'YSK Admin', 'admin@ysk.hk', 'admin');

-- Sample data for testing (optional - remove in production)
INSERT INTO clients (company_name, contact_person, email, phone, status) VALUES 
('Demo Logistics Ltd', '陳先生', 'demo@logistics.hk', '+852 2345 6789', 'active'),
('Retail Brand HK', '李小姐', 'contact@retailbrand.hk', '+852 9876 5432', 'active');

INSERT INTO projects (client_id, title, description, service_type, status, start_date, end_date, budget, progress_percent, assigned_pm_id, created_by) VALUES 
(1, '物流ERP系統開發', '開發司橛APP及後台ERP', 'app_development', 'in_progress', '2026-01-15', '2026-06-30', 85000.00, 45, 1, 1),
(2, '私有LLM合約審閱系統', '法律合約AI自動化', 'ai_automation', 'planning', '2026-05-01', '2026-08-15', 120000.00, 10, 1, 1);

INSERT INTO tasks (project_id, title, description, assigned_to_id, status, priority, due_date, estimated_hours) VALUES 
(1, '需求分析會議', '與客戶討論ERP流程', 1, 'done', 'high', '2026-01-20', 8),
(1, '資料庫設計', '設計MySQL schema', 1, 'in_progress', 'high', '2026-02-10', 16),
(2, '資料集準備', '整理法律合約PDF', 1, 'todo', 'medium', '2026-05-10', 20);

-- Note: Change default password after first login!