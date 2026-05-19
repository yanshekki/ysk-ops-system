# YSK 業務運作系統 (YSK Operations System)
**PHP + MySQL 企業內部管理平台**  
專為 YSK Limited 設計，管理客戶、項目（AI 自動化 / App 開發 / 雲端安全 / Web3 區塊鏈）、任務、發票等日常運作。

## 系統特色
- 完全開源、輕量級（無需框架，純 PHP + Bootstrap 5 CDN）
- 支援多角色權限（admin / pm / developer / finance / viewer）
- 專為 YSK 服務類型優化（service_type 下拉選單）
- 即開即用，適合任何 Web Hosting（共享主機 / VPS 皆可）
- 完整 CRUD + 儀表板 + 統計

## 安裝步驟（5 分鐘完成）
1. **上傳檔案**  
   將整個 `ysk-ops-system` 資料夾上傳到你 Web Hosting 根目錄（例如 public_html/ysk-ops）

2. **建立 MySQL 資料庫**  
   - 登入 phpMyAdmin 或 MySQL 主機
   - 執行 `database.sql` 裡的所有 SQL 指令（會自動建立 `ysk_ops` 資料庫 + 表格 + 測試數據）

3. **設定 config.php**  
   編輯 `config.php`：
   ```php
   define('DB_HOST', 'localhost');      // 你的 MySQL 主機
   define('DB_NAME', 'ysk_ops');
   define('DB_USER', '你的資料庫用戶名');
   define('DB_PASS', '你的資料庫密碼');
   define('SITE_URL', 'https://你的域名/ysk-ops');  // 改成實際網址
   ```

4. **首次登入**  
   - 瀏覽 `https://你的域名/ysk-ops/index.php`
   - 用戶名：`admin`
   - 密碼：`admin123`
   - **強烈建議登入後立即更改密碼**（可於用戶管理頁面或直接改資料庫）

5. **完成！**  
   開始使用客戶管理、項目管理、任務追蹤、發票系統。

## 主要功能模組
- **儀表板**：即時統計（活躍客戶、進行中項目、待辦任務、收入）
- **客戶管理**：新增/編輯/刪除客戶 + 聯絡資訊 + 狀態（活躍/潛在/非活躍）
- **項目管理**：支援 YSK 四大服務類型（AI 自動化、App 開發、雲端安全、Web3 區塊鏈）
  - 進度條、預算、負責 PM、狀態追蹤
- **任務追蹤**：Kanban 式任務分配 + 優先級 + 到期日 + 工時預估
- **發票管理**：快速開立發票 + 關聯客戶/項目 + 稅率計算

## 進階建議（可自行擴充）
- 加入 **時間記錄 (Timesheets)** 自動計費
- **PDF 發票產生**（使用 TCPDF 或 mPDF）
- ** recurring 月費** 自動開立（適合你哩的月費計劃）
- **電郵通知**（新任務、發票到期）
- **文件上傳**（合約、需求文件）
- **報表匯出**（Excel / PDF）
- 改用 **Laravel** 或 **CodeIgniter** 做更大型系統

## 技術堆疊
- PHP 8.2+
- MySQL 8.0+
- Bootstrap 5.3 (CDN)
- PDO + Prepared Statements（安全）
- Session-based 登入 + Role-based Access Control

## 支援與自訂
如需進一步開發（例如加入你公司現有客戶數據匯入、Stripe 付款整合、WhatsApp 通知、或私有 LLM 管理介面），歡迎聯絡 YSK 團隊！

**公司網站**：https://ysk.hk  
**聯絡 WhatsApp**：852 6160 4242

© 2026 YSK Limited - 你的專屬遠端開發團隊