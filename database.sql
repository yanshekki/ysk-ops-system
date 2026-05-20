-- YSK Ops System v2.6 Database Schema & Sample Data
-- 新增客戶專屬登入 (username, password_hash)

CREATE DATABASE IF NOT EXISTS ki_ops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ki_ops;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS timesheets;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS recurring_invoices;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS knowledge_base;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================
-- 1. Users (團隊成員)
-- ==========================================
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

-- ==========================================
-- 2. Clients (客戶 - 新增 username, password)
-- ==========================================
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    password_hash VARCHAR(255),
    company_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    notes TEXT,
    status ENUM('active', 'inactive', 'lead') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FULLTEXT KEY ft_clients (company_name, contact_person, email, notes)
);

-- ==========================================
-- 3. Projects (專案項目)
-- ==========================================
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
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FULLTEXT KEY ft_projects (title, description)
);

-- ==========================================
-- 4. Tasks (任務追蹤)
-- ==========================================
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

-- ==========================================
-- 5. Invoices (發票)
-- ==========================================
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
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ==========================================
-- 6. Timesheets (工時記錄)
-- ==========================================
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

-- ==========================================
-- 7. Knowledge Base (知識庫)
-- ==========================================
CREATE TABLE knowledge_base (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    category ENUM('sop', 'technical', 'client', 'other') DEFAULT 'other',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FULLTEXT KEY ft_knowledge (title, content)
);

-- ==========================================
-- 8. Notifications (通知中心)
-- ==========================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT,
    type ENUM('whatsapp', 'email') DEFAULT 'whatsapp',
    message TEXT NOT NULL,
    sent_by INT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ==========================================
-- 9. Recurring Invoices (周期性發票)
-- ==========================================
CREATE TABLE recurring_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    project_id INT NULL,
    title VARCHAR(200) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    frequency ENUM('monthly', 'quarterly', 'yearly') DEFAULT 'monthly',
    start_date DATE NOT NULL,
    next_invoice_date DATE NOT NULL,
    status ENUM('active', 'paused', 'ended') DEFAULT 'active',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =========================================================================
-- 模擬測試數據 (Sample Data) - 密碼統一為 password
-- =========================================================================
INSERT INTO users (username, password_hash, full_name, email, role, phone, is_active) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '超級管理員', 'admin@ysk.hk', 'admin', '+852 98765432', 1),
('jason_pm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jason PM', 'jason@ysk.hk', 'pm', '+852 61234567', 1);

-- 插入客戶資料 (包含客戶登入 Portal 的帳號與密碼)
INSERT INTO clients (username, password_hash, company_name, contact_person, email, phone, address, status) VALUES 
('apex', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Apex Logistics Ltd', '陳先生', 'info@apexlogistics.hk', '+852 2345 6789', '觀塘鴻圖道 12 號', 'active'),
('retailhk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Retail Brand HK', '李小姐', 'contact@retailbrand.hk', '+852 9876 5432', '銅鑼灣巧明街 55 號', 'active'),
('fintech', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'FinTech Solutions', '張總監', 'ceo@fintechsol.com', '+852 3344 5566', '中環國際金融中心', 'active');

INSERT INTO projects (client_id, title, description, service_type, status, start_date, end_date, budget, progress_percent, assigned_pm_id, created_by) VALUES 
(1, '物流 ERP 系統升級', '開發司機 APP 及重構後台 ERP', 'app_development', 'in_progress', '2026-01-15', '2026-06-30', 125000.00, 45, 2, 1),
(2, '會員積分 App 開發', 'iOS/Android 雙平台會員 APP', 'app_development', 'review', '2025-10-01', '2026-02-28', 180000.00, 90, 2, 1);

INSERT INTO invoices (invoice_number, client_id, project_id, issue_date, due_date, subtotal, tax_percent, total_amount, status, notes, created_by) VALUES 
('INV-20260115-001', 1, 1, '2026-01-15', '2026-01-30', 37500.00, 0, 37500.00, 'paid', '第一期訂金 (30%)', 1),
('INV-20260401-002', 1, 1, '2026-04-01', '2026-04-15', 50000.00, 0, 50000.00, 'sent', '第二期開發款 (40%)', 1);