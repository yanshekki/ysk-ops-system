<?php
// 1. 優先引入核心常數設定檔
require_once __DIR__ . '/../config.php';

// 2. 引入全新解耦的全域核心工具函式庫
require_once __DIR__ . '/functions.php';

// 3. 引入原有的資料庫與權限驗證核心
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// 以下保持你原有的公共 Header HTML 結構...
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " | " . SITE_NAME : SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body class="bg-light">