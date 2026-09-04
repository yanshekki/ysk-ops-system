-- YSK Ops System — 報價單模組 (additive, production-safe)
-- 用法：mysql -u ... -p ki_ops < migrations/2026-09-04-quotes.sql
-- 唔好跑 database.sql（會 DROP 全部表）

CREATE TABLE IF NOT EXISTS quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_number VARCHAR(50) UNIQUE NOT NULL,
    client_id INT NOT NULL,
    project_id INT NULL,
    title VARCHAR(200) NOT NULL,
    intro_text TEXT,
    status ENUM('draft', 'sent', 'accepted', 'declined', 'expired', 'converted', 'superseded') DEFAULT 'draft',
    issue_date DATE NOT NULL,
    valid_until DATE NOT NULL,
    tax_percent DECIMAL(5,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    subtotal DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) DEFAULT 0,
    notes TEXT,
    terms TEXT,
    created_by INT,
    sent_at DATETIME NULL,
    accepted_at DATETIME NULL,
    declined_at DATETIME NULL,
    converted_invoice_id INT NULL,
    converted_project_id INT NULL,
    converted_recurring_id INT NULL,
    revision_of_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (revision_of_id) REFERENCES quotes(id) ON DELETE SET NULL,
    KEY idx_quotes_status (status),
    KEY idx_quotes_client (client_id),
    KEY idx_quotes_valid (valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quote_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    sort_order INT DEFAULT 0,
    catalog_key VARCHAR(80) NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    billing_type ENUM('one_time', 'monthly', 'quarterly', 'yearly', 'every_30_days') NOT NULL DEFAULT 'one_time',
    qty DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit VARCHAR(20) DEFAULT '項',
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    KEY idx_quote_items_quote (quote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    sort_order INT DEFAULT 0,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    qty DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit VARCHAR(20) DEFAULT '項',
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    KEY idx_invoice_items_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- invoices: 關聯報價 + 來源
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'quote_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE invoices ADD COLUMN quote_id INT NULL AFTER project_id, ADD KEY idx_invoices_quote (quote_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'source'
);
SET @sql := IF(@col_exists = 0,
    "ALTER TABLE invoices ADD COLUMN source ENUM('manual','quote','recurring') NOT NULL DEFAULT 'manual' AFTER quote_id",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- projects.quote_id
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'quote_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE projects ADD COLUMN quote_id INT NULL AFTER created_by, ADD KEY idx_projects_quote (quote_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- recurring_invoices.quote_id + every_30_days
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recurring_invoices' AND COLUMN_NAME = 'quote_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE recurring_invoices ADD COLUMN quote_id INT NULL AFTER project_id, ADD KEY idx_recurring_quote (quote_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE recurring_invoices
    MODIFY COLUMN frequency ENUM('monthly', 'quarterly', 'yearly', 'every_30_days') DEFAULT 'monthly';
