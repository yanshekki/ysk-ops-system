<?php
/**
 * YSK 公開服務目錄 — 報價模板
 * 價錢為 ysk.hk 已公開起步價，可在報價單人手改。
 * unit_price = 0 代表必須人手填寫。
 */

function quote_catalog_groups(): array {
    $vps_ysk_server = <<<'TXT'
【託管主機】
• 1 部美國獨立 IP 伺服器 (VPS)，含系統設定及域名綁定
• 全機預裝 YSK Server（單機 Linux 控制平面：網頁面板 + CLI + API）

【YSK Server 安全防護】
• 防護中心：防火牆、fail2ban、IP 封鎖／解封
• SSH 身分管理；面板及 SSH 雙重驗證 (2FA)
• HTTPS 管理面板（埠 9287）；網站 SSL／Let's Encrypt
• 危險主機操作預設 dry-run，須明確授權才真正套用
• 單機控制平面，資料留在您的伺服器，並非多租戶共享面板

【資料庫與備份】
• 支援 MySQL／MariaDB、PostgreSQL、Redis
• 定期資料庫備份及主機資料備份，降低誤刪、故障或勒索造成的資料遺失風險

【YSK Server 營運優點】
• 面板、命令列、API 同一核心，方便人手操作及自動化
• 一部主機管理網站專案、檔案、電郵、DNS／SSL 與 Docker
• 誠實運維：未套用到主機不會假裝已上線
• MIT 開源；主機由您擁有（或本計劃提供之 VPS）
TXT;

    $contract_notes = <<<'TXT'
【合約說明】
• 一年約、只限遠端
• 私有 LLM 全託管為獨立年費，不包含於此外判月費
• App 上架：使用 YSK Limited 開發者帳號則官方平台費全免；使用客戶公司帳號則客戶自付 Apple／Google 年費
TXT;

    return [
        'outsourcing' => [
            'label' => '開發者外判',
            'icon' => 'bi-people',
            'items' => [
                [
                    'key' => 'remote_basic',
                    'title' => '開發者外判 — 基本計劃',
                    'description' => "【遠端技術支援】\n• 每月 5 條：系統 Bug 修復、程式碼諮詢及架構建議（只限遠端）\n\n【開發配額】\n• 網站、iOS 及 Android 雙平台，合共 5 頁動態頁面\n\n{$vps_ysk_server}\n\n{$contract_notes}",
                    'billing_type' => 'monthly',
                    'unit' => '月',
                    'unit_price' => 2000,
                    'qty' => 1,
                    'service_type' => 'other',
                ],
                [
                    'key' => 'remote_standard',
                    'title' => '開發者外判 — 標準計劃',
                    'description' => "【遠端技術支援】\n• 每月 20 條：深度程式碼支援、API 串接除錯及效能優化（只限遠端）\n\n【開發配額】\n• 網站、iOS 及 Android 雙平台，合共 20 頁動態頁面\n\n{$vps_ysk_server}\n\n【本計劃加強】\n• VPS 配備進階效能設定\n\n{$contract_notes}",
                    'billing_type' => 'monthly',
                    'unit' => '月',
                    'unit_price' => 6000,
                    'qty' => 1,
                    'service_type' => 'other',
                ],
                [
                    'key' => 'remote_premium',
                    'title' => '開發者外判 — 尊貴計劃',
                    'description' => "【遠端技術支援】\n• 每月 50 條：高階架構支援、資料庫優化與即時漏洞修補（只限遠端）\n\n【開發配額】\n• 網站、iOS 及 Android 雙平台，合共 50 頁動態頁面\n\n{$vps_ysk_server}\n\n【本計劃加強】\n• 頂級運算效能、負載平衡及零信任架構設定\n\n{$contract_notes}",
                    'billing_type' => 'monthly',
                    'unit' => '月',
                    'unit_price' => 12000,
                    'qty' => 1,
                    'service_type' => 'other',
                ],
                [
                    'key' => 'remote_enterprise',
                    'title' => '開發者外判 — 企業定製與 AI',
                    'description' => "【服務範圍】\n• 自訂 SLA，專屬首席架構師及項目經理對接\n• 大型企業網站及跨平台 APP，無頁面數量限制，按規格書敏捷開發\n• 大型混合雲（AWS／GCP）叢集、高可用本地 AI 伺服器或區塊鏈節點可另列\n\n{$vps_ysk_server}\n\n{$contract_notes}\n• 公開價為起步價，本行金額須按規格填寫",
                    'billing_type' => 'monthly',
                    'unit' => '月',
                    'unit_price' => 0,
                    'qty' => 1,
                    'service_type' => 'other',
                ],
            ],
        ],
        'ai' => [
            'label' => '私有 LLM',
            'icon' => 'bi-cpu',
            'items' => [
                [
                    'key' => 'llm_hosted',
                    'title' => '私有 LLM 全託管',
                    'description' => "企業私有大語言模型全託管年費計劃（起步價）。\n涵蓋 Dataset 製作與清洗、QLoRA／LoRA 微調、私有化部署與託管、OpenAI 相容私有 API。\n100% 數據不出境。不包含於開發者外判月費，須獨立年費。\n最終金額按數據量、模型規模及算力需求確認。",
                    'billing_type' => 'yearly',
                    'unit' => '年',
                    'unit_price' => 88000,
                    'qty' => 1,
                    'service_type' => 'ai_automation',
                ],
            ],
        ],
        'engineering' => [
            'label' => '定製工程',
            'icon' => 'bi-code-slash',
            'items' => [
                [
                    'key' => 'app_custom',
                    'title' => '香港 APP 開發（iOS / Android / Web）',
                    'description' => "【範圍】\n• 按規格書開發跨平台應用或企業系統（React Native／PHP／Node 等）\n• 含 UI/UX 對接、後端資料庫、測試及上架協助\n• 金額按範圍填寫\n\n【App 上架】\n• 使用 YSK Limited 開發者帳號則官方平台費全免\n• 使用客戶公司帳號則客戶自付 Apple／Google 年費",
                    'billing_type' => 'one_time',
                    'unit' => '項',
                    'unit_price' => 0,
                    'qty' => 1,
                    'service_type' => 'app_development',
                ],
                [
                    'key' => 'cloud_security',
                    'title' => '香港雲端遷移與網絡安全',
                    'description' => "遷移至 AWS、Google Cloud 或獨立 VPS，導入零信任架構、SSL、防火牆與災難復原。\n金額按現有架構與 SLA 要求填寫。",
                    'billing_type' => 'one_time',
                    'unit' => '項',
                    'unit_price' => 0,
                    'qty' => 1,
                    'service_type' => 'cloud_security',
                ],
                [
                    'key' => 'web3',
                    'title' => '香港 Web3 區塊鏈開發',
                    'description' => "智能合約編寫、DApp 開發及區塊鏈節點部署。金額按鏈種、審計範圍與交付範圍填寫。",
                    'billing_type' => 'one_time',
                    'unit' => '項',
                    'unit_price' => 0,
                    'qty' => 1,
                    'service_type' => 'web3_blockchain',
                ],
                [
                    'key' => 'api_integration',
                    'title' => '第三方系統 API 串接與整合',
                    'description' => "Stripe／支付寶付款、WhatsApp Business API、私有 AI 模型或其他現有系統串接。",
                    'billing_type' => 'one_time',
                    'unit' => '項',
                    'unit_price' => 0,
                    'qty' => 1,
                    'service_type' => 'other',
                ],
                [
                    'key' => 'db_optimization',
                    'title' => '系統效能優化與資料庫重構',
                    'description' => "針對載入緩慢或高併發當機的舊系統進行程式碼優化與 MySQL／PostgreSQL 重構。",
                    'billing_type' => 'one_time',
                    'unit' => '項',
                    'unit_price' => 0,
                    'qty' => 1,
                    'service_type' => 'other',
                ],
                [
                    'key' => 'seo_ssr',
                    'title' => '技術級 SEO 與 SSR 渲染架構',
                    'description' => "Next.js 伺服器端渲染架構、Core Web Vitals 優化，提升自然搜尋表現。",
                    'billing_type' => 'one_time',
                    'unit' => '項',
                    'unit_price' => 0,
                    'qty' => 1,
                    'service_type' => 'app_development',
                ],
                [
                    'key' => 'cicd',
                    'title' => 'CI/CD 自動化部署',
                    'description' => "為內部開發團隊建立 Git 版本控制、自動化測試與部署管線。",
                    'billing_type' => 'one_time',
                    'unit' => '項',
                    'unit_price' => 0,
                    'qty' => 1,
                    'service_type' => 'other',
                ],
                [
                    'key' => 'uiux',
                    'title' => 'UI/UX 介面設計與程式碼轉化',
                    'description' => "由 Figma 原型到 React／Vue 前端實作，還原設計細節與動態效果。",
                    'billing_type' => 'one_time',
                    'unit' => '項',
                    'unit_price' => 0,
                    'qty' => 1,
                    'service_type' => 'app_development',
                ],
            ],
        ],
        'products' => [
            'label' => 'SaaS 與產品',
            'icon' => 'bi-shop',
            'items' => [
                [
                    'key' => 'salonease_early',
                    'title' => 'SalonEase 店舖管理系統 — 早鳥',
                    'description' => "雲端店舖 ERP（POS、預約、佣金、多分店）。早鳥價每 30 日 HK$100，永久鎖定此價。\n不包含專用 VPS 加購。",
                    'billing_type' => 'every_30_days',
                    'unit' => '30日',
                    'unit_price' => 100,
                    'qty' => 1,
                    'service_type' => 'other',
                ],
                [
                    'key' => 'salonease_std',
                    'title' => 'SalonEase 店舖管理系統 — 標準',
                    'description' => "雲端店舖 ERP（POS、預約、佣金、多分店）。標準價每 30 日 HK$350。\n不包含專用 VPS 加購。",
                    'billing_type' => 'every_30_days',
                    'unit' => '30日',
                    'unit_price' => 350,
                    'qty' => 1,
                    'service_type' => 'other',
                ],
                [
                    'key' => 'salonease_vps',
                    'title' => 'SalonEase 專用 VPS 加購',
                    'description' => "SalonEase 專用 VPS 加購，每 30 日 HK$200。須與 SalonEase 訂閱一併提供。",
                    'billing_type' => 'every_30_days',
                    'unit' => '30日',
                    'unit_price' => 200,
                    'qty' => 1,
                    'service_type' => 'other',
                ],
                [
                    'key' => 'ysk_server_install',
                    'title' => 'YSK Server 代裝／企業訂造',
                    'description' => "【範圍】\n• 開源單機 Linux 控制平面之代裝、加固或企業訂造再開發\n• 金額按主機環境與書面範圍填寫\n\n【安全防護】\n• 防護中心：防火牆、fail2ban、IP 封鎖／解封\n• SSH 身分；面板及 SSH 雙重驗證 (2FA)\n• HTTPS 面板與網站 SSL／Let's Encrypt\n• 危險操作預設 dry-run，須明確授權才套用\n\n【資料庫與備份】\n• MySQL／MariaDB、PostgreSQL、Redis\n• 定期資料庫及主機資料備份\n\n【營運優點】\n• 面板 ≡ CLI ≡ API 同一核心\n• 一部主機管理網站、檔案、電郵、DNS／SSL、Docker\n• 單機控制平面，資料留在您的伺服器，並非多租戶 SaaS",
                    'billing_type' => 'one_time',
                    'unit' => '項',
                    'unit_price' => 0,
                    'qty' => 1,
                    'service_type' => 'cloud_security',
                ],
                [
                    'key' => 'instant_drama_ent',
                    'title' => '瞬劇魔法師企業訂造',
                    'description' => "InstantDrama Magician 開源自用以外的企業訂造／再開發。金額按規格填寫。",
                    'billing_type' => 'one_time',
                    'unit' => '項',
                    'unit_price' => 0,
                    'qty' => 1,
                    'service_type' => 'ai_automation',
                ],
            ],
        ],
    ];
}

function quote_catalog_flat(): array {
    $flat = [];
    foreach (quote_catalog_groups() as $group) {
        foreach ($group['items'] as $item) {
            $flat[$item['key']] = $item;
        }
    }
    return $flat;
}

function quote_catalog_item(string $key): ?array {
    return quote_catalog_flat()[$key] ?? null;
}

function quote_billing_labels(): array {
    return [
        'one_time' => ['zh' => '一次性', 'en' => 'One-off', 'unit' => '項'],
        'monthly' => ['zh' => '每月', 'en' => 'Monthly', 'unit' => '月'],
        'quarterly' => ['zh' => '每季', 'en' => 'Quarterly', 'unit' => '季'],
        'yearly' => ['zh' => '每年', 'en' => 'Yearly', 'unit' => '年'],
        'every_30_days' => ['zh' => '每 30 日', 'en' => 'Every 30 days', 'unit' => '30日'],
    ];
}

function quote_map_service_type(?string $catalog_key): string {
    if (!$catalog_key) {
        return 'other';
    }
    $item = quote_catalog_item($catalog_key);
    return $item['service_type'] ?? 'other';
}
