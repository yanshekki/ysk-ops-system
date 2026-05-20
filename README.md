# YSK Ops System v2.6

**純 PHP + MySQL 企業級內部運作與客戶管理系統** 專為 YSK Limited 設計的全方位營運管理平台，涵蓋企業級 RBAC 權限控制、客戶自助門戶 (Client Portal)、專案進度、工時追蹤、發票計費與高階財務分析。

## 🌟 系統最新亮點 (v2.6)
- **企業級 RBAC 權限架構**：嚴格劃分 5 大角色 (`Admin`, `PM`, `Developer`, `Finance`, `Viewer`)，實踐頁面級 (Page)、介面級 (UI) 及資料層 (Data Level) 的深度防護。
- **客戶自助門戶 (Client Portal)**：客戶擁有專屬的獨立安全登入系統，可隨時查看專案進度、查閱帳單及下載發票。
- **雙語 PDF 發票系統**：支援一鍵切換中/英文版本的專業 A4 格式發票，並帶有防偽浮水印與完整的銀行轉賬/轉數快 (FPS) 付款指示。
- **完全純 PHP + MySQL 架構**：零依賴重型框架，極致輕量，完美兼容任何 Web Hosting。
- **專業 SaaS 級介面**：採用 Bootstrap 5，支援全站 RWD 響應式佈局、黃金排版準則，確保 100% 貼底絕不走位。

## 🚀 核心模組功能

### 1. 權限與基礎管理
- **團隊用戶管理**：自訂員工角色與系統存取權限 (僅限 Admin)。
- **客戶管理 (CRM)**：管理客戶檔案、聯絡人資訊，並可為客戶開通 Portal 專屬登入帳號。
- **知識庫 (SOP)**：內部標準流程與技術文檔庫。

### 2. 專案與任務運作
- **項目管理 (Projects)**：支援 YSK 四大服務類型（AI 自動化、App 開發、雲端安全、Web3 區塊鏈），追蹤進度與合約預算。
- **任務追蹤 (Tasks)**：工程團隊任務分派、優先級管理與交付死線追蹤（開發人員僅可更新個人任務狀態）。
- **工時記錄 (Timesheets)**：日常工時申報與管理層審核機制。

### 3. 財務與計費系統
- **發票管理 (Invoices)**：開立、編輯、作廢及追蹤應收賬款，支援匯出 PDF。
- **週期性發票 (Recurring)**：自動推算計費週期（月、季、年），一鍵生成新發票。

### 4. 管理層決策工具 (CEO Tools)
- **全域數據儀表板**：即時查看業務管線總值 (Pipeline)、待收款及已收款總額。
- **收益分析 (Profit Analysis)**：嚴格監控專案預算與實際工時投入成本，計算淨利潤率。
- **客戶貢獻度報表**：追蹤核心客戶商業價值，識別 Top 貢獻客戶。
- **團隊資源利用率**：監控開發團隊與 PM 的工作負荷、產能分配及超載預警。
- **AI 智能助理 (Copilot)**：自動分析專案健康狀況並提示逾期阻塞風險。

## 🛠️ 技術棧 (Tech Stack)
- **後端**：PHP 8+ (原生 PDO 安全查詢)
- **資料庫**：MySQL (關聯式資料庫設計 + Fulltext 搜尋)
- **前端**：Bootstrap 5, HTML5, CSS3 (自訂 UI/UX), JavaScript
- **其他**：I18n (多語系架構支援中英切換)

## ⚙️ 安裝與部署
1. Clone 或下載本 Repository。
2. 建立 MySQL 資料庫，並匯入 `database.sql` (內含過百條真實模擬測試數據)。
3. 修改 `config.php` 中的資料庫連線設定。
4. 上傳至任何支援 PHP 的 Web Hosting 或伺服器。
5. 訪問 `index.php` 即可登入。
   - *管理員測試帳號*：`admin` / `password`
   - *客戶 Portal 測試帳號*：`apex0` / `password` (請訪問 `client_portal.php`)

---

## 👤 開發者 (Creator)

**Ki (yanshekki)** — 全端開發者 (Full-stack developer)、量化交易員 (Quant trader)、[YSK Limited](https://ysk.hk/) 創辦人。

🌐 [linktr.ee/yanshekki](https://linktr.ee/yanshekki) · 🏢 [ysk.hk](https://ysk.hk/)

### ☕ 支持與贊助 (Support / Donate)

如果 YSK Ops System 對你有幫助，歡迎請我飲杯咖啡！

| 網絡 (Network) | 地址 (Address) |
|---------|---------|
| **EVM** (ETH/BSC/Polygon) | `yanshekki.eth` |
| **NEAR** | `yanshekki.near` |
| **ADA** (Cardano) | `$yanshekki` |

<p align="center">
  <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=https://linktr.ee/yanshekki" alt="yanshekki QR" width="200" />
  <br/>
  <sub>掃描以支持 (Scan to support) → linktr.ee/yanshekki</sub>
</p>

---

## 📄 授權條款 (License)
MIT © YSK Ops System

---

<sub>由 [YSK Limited](https://ysk.hk/) 提供技術支持 — 香港遠端開發團隊及企業解決方案 (Hong Kong Remote Dev Team & Enterprise Solutions)</sub>