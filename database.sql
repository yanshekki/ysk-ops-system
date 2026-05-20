-- YSK Ops System v2.5 Database Schema & Sample Data
-- 包含最新 SaaS 級別結構與大量測試數據
-- 執行前請確認已選擇對應的資料庫，或直接執行以下全部代碼

CREATE DATABASE IF NOT EXISTS ki_ops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ki_ops;

-- 停用外鍵檢查以方便重建
SET FOREIGN_KEY_CHECKS = 0;

-- 刪除舊表
DROP TABLE IF EXISTS timesheets;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS recurring_invoices;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS knowledge_base;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS users;

-- 啟用外鍵檢查
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
-- 2. Clients (客戶)
-- ==========================================
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
-- 9. Recurring Invoices (周期性發票 - 全新結構)
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
-- 以下為模擬測試數據 (Sample Data)
-- 注意：為了方便測試，所有帳號的密碼均設定為同一個 Hash 值 (對應密碼：password)
-- =========================================================================

-- 插入使用者 (密碼全部為: password)
INSERT INTO users (username, password_hash, full_name, email, role, phone, is_active) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '超級管理員', 'admin@ysk.hk', 'admin', '+852 98765432', 1),   -- 密碼: password
('jason_pm', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jason PM', 'jason@ysk.hk', 'pm', '+852 61234567', 1),        -- 密碼: password
('david_dev', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'David (Dev)', 'david@ysk.hk', 'developer', '', 1),            -- 密碼: password
('sarah_dev', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah (Dev)', 'sarah@ysk.hk', 'developer', '', 1),            -- 密碼: password
('mandy_fin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mandy Finance', 'mandy@ysk.hk', 'finance', '+852 55556666', 1),-- 密碼: password
('alex_view', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alex (Viewer)', 'alex@ysk.hk', 'viewer', '', 0);             -- 密碼: password (此帳號已停用)

-- 插入客戶資料
INSERT INTO clients (company_name, contact_person, email, phone, address, status) VALUES 
('Apex Logistics Ltd', '陳先生', 'info@apexlogistics.hk', '+852 2345 6789', '觀塘鴻圖道 12 號', 'active'),
('Retail Brand HK', '李小姐', 'contact@retailbrand.hk', '+852 9876 5432', '銅鑼灣巧明街 55 號', 'active'),
('FinTech Solutions', '張總監', 'ceo@fintechsol.com', '+852 3344 5566', '中環國際金融中心', 'active'),
('EduCare Learning', '王校長', 'admin@educare.edu.hk', '+852 2233 4455', '沙田教育路 8 號', 'lead'),
('Green Energy Corp', '劉經理', 'green@energy.com', '+852 6677 8899', '科學園科技大道 1 號', 'active'),
('Old Co. Limited', '何伯', 'info@oldco.hk', '+852 1122 3344', '荃灣工業區', 'inactive'),
('Web3 Innovators', 'Chris', 'chris@w3inno.io', '+852 5544 3322', '數碼港', 'active'),
('HealthPlus Clinic', 'Dr. Wong', 'clinic@healthplus.hk', '+852 2888 9999', '旺角彌敦道', 'active');

-- 插入專案項目
INSERT INTO projects (client_id, title, description, service_type, status, start_date, end_date, budget, progress_percent, assigned_pm_id, created_by) VALUES 
(1, '物流 ERP 系統升級', '開發司機 APP 及重構後台 ERP', 'app_development', 'in_progress', '2026-01-15', '2026-06-30', 125000.00, 45, 2, 1),
(2, '會員積分 App 開發', 'iOS/Android 雙平台會員 APP', 'app_development', 'review', '2025-10-01', '2026-02-28', 180000.00, 90, 2, 1),
(3, 'AI 客服機器人導入', '使用私有 LLM 訓練客服知識庫', 'ai_automation', 'in_progress', '2026-03-01', '2026-05-15', 68000.00, 30, 2, 1),
(5, '雲端架構安全審計', 'AWS 雲端漏洞掃描與加固', 'cloud_security', 'completed', '2025-11-01', '2025-12-15', 45000.00, 100, 1, 1),
(7, '區塊鏈智能合約開發', 'NFT 發行及質押合約編寫', 'web3_blockchain', 'planning', '2026-06-01', '2026-08-30', 95000.00, 5, 2, 1),
(8, '診所預約系統自動化', '整合 WhatsApp API 進行自動預約提醒', 'ai_automation', 'in_progress', '2026-02-15', '2026-04-30', 55000.00, 65, 2, 1),
(2, '電商網站維護合約', '年度技術支援與伺服器維護', 'other', 'in_progress', '2026-01-01', '2026-12-31', 24000.00, 50, 1, 1),
(4, '學生管理系統原型', '系統 UI/UX 設計與需求確認', 'other', 'on_hold', '2026-01-10', '2026-03-10', 30000.00, 20, 2, 1);

-- 插入任務追蹤
INSERT INTO tasks (project_id, title, description, assigned_to_id, status, priority, due_date, estimated_hours) VALUES 
(1, '後台資料庫架構設計', '設計 ERP 所需之資料表關聯', 3, 'done', 'high', '2026-01-20', 16),
(1, '司機 APP 登入介面', '開發 JWT 登入及驗證功能', 4, 'in_progress', 'high', '2026-05-25', 12),
(1, '貨單條碼掃描功能', '整合相機掃描 API', 4, 'todo', 'medium', '2026-06-05', 20),
(2, 'UAT 測試環境部署', '將程式碼推上 Staging 伺服器', 3, 'done', 'urgent', '2026-02-15', 8),
(2, '修復積分計算 Bug', '客戶回報某些特定商品積分錯誤', 3, 'in_progress', 'urgent', '2026-05-20', 6),
(3, '收集客服歷史對話', '清洗及整理 5000 筆客服記錄', 4, 'done', 'medium', '2026-03-15', 24),
(3, 'LLM 模型微調 (Fine-tuning)', '使用整理好的資料訓練模型', 3, 'in_progress', 'high', '2026-05-22', 40),
(5, '撰寫 ERC-20 智能合約', '基礎代幣合約編寫', 3, 'todo', 'medium', '2026-06-15', 16),
(6, '申請 WhatsApp Business API', '協助客戶準備 Facebook 認證資料', 2, 'done', 'high', '2026-02-28', 4),
(6, '開發自動排程腳本', '每日定時發送預約提醒', 4, 'review', 'medium', '2026-05-18', 12);

-- 插入發票記錄 (Invoice)
INSERT INTO invoices (invoice_number, client_id, project_id, issue_date, due_date, subtotal, tax_percent, total_amount, status, notes, created_by) VALUES 
('INV-20260115-001', 1, 1, '2026-01-15', '2026-01-30', 37500.00, 0, 37500.00, 'paid', '第一期訂金 (30%)', 5),
('INV-20260401-002', 1, 1, '2026-04-01', '2026-04-15', 50000.00, 0, 50000.00, 'overdue', '第二期開發款 (40%)', 5),
('INV-20251001-003', 2, 2, '2025-10-01', '2025-10-15', 90000.00, 0, 90000.00, 'paid', '第一期訂金 (50%)', 5),
('INV-20260301-004', 2, 2, '2026-03-01', '2026-03-15', 90000.00, 0, 90000.00, 'sent', '尾數款項 (50%)', 5),
('INV-20251101-005', 5, 4, '2025-11-01', '2025-11-15', 45000.00, 0, 45000.00, 'paid', '全數款項', 5),
('INV-20260301-006', 3, 3, '2026-03-01', '2026-03-15', 34000.00, 0, 34000.00, 'paid', '首期訂金 (50%)', 5),
('INV-20260515-007', 8, 6, '2026-05-15', '2026-05-30', 27500.00, 0, 27500.00, 'draft', '首期訂金 (50%)', 5);

-- 插入工時記錄 (Timesheets)
INSERT INTO timesheets (user_id, project_id, task_id, work_date, hours, description, is_approved) VALUES 
(3, 1, 1, '2026-01-18', 8.0, '資料庫結構設計與確認', 1),
(3, 1, 1, '2026-01-19', 8.0, '建立 Migration 及 Seeders', 1),
(4, 1, 2, '2026-05-15', 4.5, '完成 App 登入畫面切版', 0),
(4, 1, 2, '2026-05-16', 6.0, '串接 JWT 登入 API', 0),
(3, 2, 4, '2026-02-10', 4.0, '設定 AWS EC2 及 RDS', 1),
(3, 2, 4, '2026-02-11', 4.0, 'CI/CD Pipeline 測試', 1),
(3, 2, 5, '2026-05-18', 3.5, '排查並修復購物車積分邏輯', 0),
(4, 3, 6, '2026-03-10', 8.0, '整理 Excel 客服歷史數據', 1),
(4, 3, 6, '2026-03-11', 8.0, '數據清洗、去除敏感個資', 1),
(4, 6, 10, '2026-05-10', 6.5, '編寫 Node.js 排程腳本', 0),
(2, 1, NULL, '2026-05-18', 2.0, '與客戶進行進度匯報會議', 0),
(2, 3, NULL, '2026-05-19', 1.5, '審核模型訓練結果', 0);

-- 插入知識庫 (Knowledge Base)
INSERT INTO knowledge_base (title, content, category, created_by) VALUES 
('新員工入職指南 (Onboarding)', '歡迎加入 YSK！請完成以下步驟：\n1. 設定 Slack 與 Email 帳號\n2. 閱讀開發規範文件\n3. 設定本地端開發環境 (Docker)', 'sop', 1),
('Git 版本控制規範', '我們採用 Git Flow 流程：\n- feature/* 分支用於新功能\n- hotfix/* 用於緊急修復\n- PR 必須經由至少一人 Code Review', 'technical', 3),
('客戶報價單製作標準', '所有報價單必須包含：\n1. 專案背景與目標\n2. 功能模組清單與預估時數\n3. 付款條款 (通常為 50/50 或 40/40/20)', 'sop', 2),
('Stripe API 串接手冊', '此文件說明如何將 Stripe Checkout 整合至客戶的電商平台。請使用測試環境的 Secret Key 進行本地測試。', 'technical', 3);

-- 插入通知記錄 (Notifications)
INSERT INTO notifications (client_id, type, message, sent_by) VALUES 
(1, 'whatsapp', '陳先生您好，您的第二期開發款項 (INV-20260401-002) 已經逾期，請盡快安排付款。', 5),
(2, 'email', '李小姐您好，您的會員積分 APP 已經部署至 UAT 環境，請登入查看並進行測試。', 2),
(8, 'whatsapp', 'Dr. Wong，我們已經成功為您申請 WhatsApp Business API，請確認您的 Facebook 認證信件。', 2);

-- 插入周期性發票 (Recurring Invoices - 採用最新結構)
INSERT INTO recurring_invoices (client_id, project_id, title, amount, frequency, start_date, next_invoice_date, status, notes, created_by) VALUES 
(2, 7, '電商網站年度維護合約', 2000.00, 'monthly', '2026-01-01', '2026-06-01', 'active', '月度伺服器託管與技術支援費', 1),
(5, NULL, 'AWS 雲端資安月費', 3500.00, 'monthly', '2026-01-15', '2026-06-15', 'active', '基礎防禦及日誌分析', 1),
(1, NULL, 'ERP 系統授權年費', 15000.00, 'yearly', '2026-07-01', '2026-07-01', 'active', '涵蓋 20 個使用者帳號', 1),
(3, 3, 'AI 模型 API 調用月費', 800.00, 'monthly', '2026-05-01', '2026-06-01', 'paused', '客戶要求暫停一個月', 1);