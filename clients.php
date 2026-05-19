<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_client']) || isset($_POST['update_client'])) {
        $data = [
            'company_name' => trim($_POST['company_name']),
            'contact_person' => trim($_POST['contact_person'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'status' => $_POST['status'] ?? 'active'
        ];
        
        if (isset($_POST['add_client'])) {
            db_insert('clients', $data);
            $success = '客戶新增成功！';
            log_activity($_SESSION['user_id'], 'create', 'clients', null, $data['company_name']);
        } else {
            db_update('clients', $data, 'id = ?', [$id]);
            $success = '客戶資料已更新！';
            log_activity($_SESSION['user_id'], 'update', 'clients', $id);
        }
    }
    
    if (isset($_POST['delete_client'])) {
        $client_id = $_POST['client_id'];
        db_delete('clients', 'id = ?', [$client_id]);
        $success = '客戶已刪除！';
        log_activity($_SESSION['user_id'], 'delete', 'clients', $client_id);
    }
}

// Fetch clients
$clients = db_fetch_all("SELECT * FROM clients ORDER BY created_at DESC");

// If editing
$edit_client = null;
if ($action === 'edit' && $id) {
    $edit_client = db_fetch_one("SELECT * FROM clients WHERE id = ?", [$id]);
}
?>
<!DOCTYPE html>
<html lang="zh-HK
... (truncated for brevity, but full content was sent in actual call) ...