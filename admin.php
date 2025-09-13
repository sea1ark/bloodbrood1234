<?php
require_once 'includes/init.php';
requireAdmin();

// админские действия
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_user':
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $role = $_POST['role'];
            
            if (!empty($username) && !empty($password)) {
                try {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $hashedPassword, $role]);
                    $success = "Пользователь создан";
                } catch (PDOException $e) {
                    $error = "Ошибка создания пользователя";
                }
            }
            break;
            
        case 'toggle_user':
            $userId = $_POST['user_id'];
            $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$userId]);
            $success = "Статус пользователя изменен";
            break;
            
        case 'delete_message':
            $messageId = $_POST['message_id'];
            $stmt = $pdo->prepare("UPDATE messages SET is_deleted = 1 WHERE id = ?");
            $stmt->execute([$messageId]);
            $success = "Сообщение удалено";
            break;
            
        case 'purge_old':
            $stmt = $pdo->prepare("UPDATE messages SET is_deleted = 1 WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $affected = $stmt->rowCount();
            $success = "Удалено старых сообщений: $affected";
            break;
    }
}

// статистика
$stats = [];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
$stats['active_users'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM messages WHERE is_deleted = 0");
$stats['total_messages'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total FROM attachments");
$stats['total_files'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(file_size) as total FROM attachments");
$stats['storage_used'] = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM system_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stats['logs_24h'] = $stmt->fetchColumn();

// пользователи
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// последние сообщения
$stmt = $pdo->prepare("
    SELECT m.*, u.username 
    FROM messages m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.is_deleted = 0 
    ORDER BY m.created_at DESC 
    LIMIT 50
");
$stmt->execute();
$recent_messages = $stmt->fetchAll();

// Get system logs
$stmt = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 100");
$logs = $stmt->fetchAll();

// Установка заголовка страницы и дополнительных стилей
$pageTitle = 'Панель администратора';
$additionalStyles = '
        :root {
            --blood-primary: #8b0000;
            --blood-secondary: #dc143c;
            --blood-dark: #4b0000;
            --blood-light: #ff6b6b;
            --bg-primary: #000000;
            --bg-secondary: #0a0a0a;
            --bg-tertiary: #1a1a1a;
            --text-primary: #e0e0e0;
            --text-secondary: #b0b0b0;
            --text-muted: #808080;
            --border: rgba(139, 0, 0, 0.3);
            --accent: #dc143c;
            --shadow-small: 0 2px 8px rgba(0, 0, 0, 0.6);
            --shadow-medium: 0 4px 16px rgba(0, 0, 0, 0.8);
            --shadow-large: 0 8px 32px rgba(0, 0, 0, 0.9);
            --shadow-blood: 0 0 20px rgba(139, 0, 0, 0.5);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: "Courier New", monospace;
            line-height: 1.6;
            min-height: 100vh;
            cursor: none;
            overflow-x: hidden;
            position: relative;
            margin: 0;
            padding: 20px;
        }
        
        /* Background effects */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: 
                radial-gradient(circle at 20% 50%, rgba(139, 0, 0, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(220, 20, 60, 0.02) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(139, 0, 0, 0.02) 0%, transparent 50%);
            pointer-events: none;
            z-index: 1;
        }
        
        /* Noise overlay */
        body::after {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            opacity: 0.03;
            z-index: 2;
            pointer-events: none;
            background: url("data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZGVmcz48ZmlsdGVyIGlkPSJub2lzZSI+PGZlVHVyYnVsZW5jZSBiYXNlRnJlcXVlbmN5PSIwLjkiIG51bU9jdGF2ZXM9IjQiIC8+PC9maWx0ZXI+PC9kZWZzPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbHRlcj0idXJsKCNub2lzZSkiIG9wYWNpdHk9IjEiLz48L3N2Zz4=");
            animation: noise 0.2s infinite;
        }
        
        @keyframes noise {
            0%, 100% { transform: translate(0, 0); }
            10% { transform: translate(-1%, -1%); }
            20% { transform: translate(1%, 1%); }
            30% { transform: translate(-1%, 1%); }
            40% { transform: translate(1%, -1%); }
            50% { transform: translate(-0.5%, 0.5%); }
            60% { transform: translate(0.5%, -0.5%); }
            70% { transform: translate(-0.5%, -0.5%); }
            80% { transform: translate(0.5%, 0.5%); }
            90% { transform: translate(-1%, 0); }
        }
        
        /* Blood background gradient */
        .blood-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: radial-gradient(
                circle at var(--mouse-x, 50%) var(--mouse-y, 50%),
                rgba(139, 0, 0, 0.05) 0%,
                transparent 50%
            );
            pointer-events: none;
            z-index: 3;
            transition: all 0.3s ease;
        }
        
        /* Advanced cursor */
        .custom-cursor {
            position: fixed;
            width: 16px;
            height: 16px;
            background: radial-gradient(circle, 
                rgba(220, 20, 60, 0.9) 0%, 
                rgba(139, 0, 0, 0.7) 40%, 
                rgba(75, 0, 0, 0.4) 70%,
                transparent 100%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 10000;
            transform: translate(-50%, -50%);
            transition: all 0.1s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            filter: drop-shadow(0 0 8px rgba(220, 20, 60, 0.6));
            mix-blend-mode: screen;
        }
        
        .custom-cursor::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, 
                rgba(255, 0, 0, 0.4) 0%, 
                transparent 70%);
            transform: translate(-50%, -50%);
            animation: cursorPulse 2s ease-in-out infinite;
        }
        
        @keyframes cursorPulse {
            0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
            50% { transform: translate(-50%, -50%) scale(2); opacity: 0; }
        }
        
        /* Main container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            position: relative;
            z-index: 5;
        }
        
        /* Header */
        .admin-header {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
            background: rgba(26, 26, 26, 0.3);
            border: 1px solid var(--border);
            border-radius: 15px;
            margin-bottom: 3rem;
            position: relative;
            backdrop-filter: blur(10px);
        }
        
        .admin-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin: 0;
            text-align: center;
        }
        
        .admin-header::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, 
                transparent 0%, 
                var(--blood-primary) 50%, 
                transparent 100%);
            animation: headerGlow 3s ease-in-out infinite;
        }
        
        @keyframes headerGlow {
            0%, 100% { opacity: 0.3; transform: scaleX(0.5); }
            50% { opacity: 1; transform: scaleX(1); }
        }
        
        .title {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, 
                var(--blood-light) 0%, 
                var(--blood-secondary) 25%, 
                var(--blood-primary) 50%, 
                var(--blood-dark) 75%, 
                var(--blood-primary) 100%);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 40px rgba(220, 20, 60, 0.5);
            animation: bloodFlow 4s ease-in-out infinite;
            letter-spacing: 0.1em;
            text-transform: lowercase;
            margin: 0;
        }
        
        @keyframes bloodFlow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(139, 0, 0, 0.2);
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        
        .stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, 
                transparent 0%, 
                var(--blood-primary) 50%, 
                transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover {
            background: rgba(139, 0, 0, 0.1);
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-value {
            font-size: 2.5rem;
            color: var(--blood-secondary);
            font-weight: bold;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            text-transform: lowercase;
        }
        
        .tab-container {
            margin-bottom: 2rem;
        }
        
        .tab-header {
            display: flex;
            border-bottom: 1px solid #333333;
            margin-bottom: 1.5rem;
        }
        
        .tab-link {
            padding: 0.8rem 1.5rem;
            cursor: pointer;
            color: #cccccc;
            transition: all 0.3s ease;
            border: none;
            background: none;
            font-family: "Courier New", monospace;
            position: relative;
        }
        
        .tab-link:hover {
            color: #ff0000;
            background: rgba(255, 0, 0, 0.1);
            text-shadow: 0 0 10px rgba(255, 0, 0, 0.5);
        }
        
        .tab-link.active {
            color: #ff0000;
            border-bottom: 2px solid #ff0000;
            background: rgba(255, 0, 0, 0.1);
            text-shadow: 0 0 10px rgba(255, 0, 0, 0.5);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
        }
        
        .admin-table th, .admin-table td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #333333;
            color: #cccccc;
        }
        
        .admin-table th {
            color: #ff0000;
            font-weight: bold;
            background: rgba(255, 0, 0, 0.1);
            text-transform: uppercase;
            font-size: 0.9rem;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
        }
        
        .admin-table tr:hover {
            background: rgba(255, 0, 0, 0.05);
        }
        
        .log-entry {
            font-size: 0.9rem;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            border-radius: 0;
            font-family: "Courier New", monospace;
        }
        
        .log-time {
            color: #666666;
            font-size: 0.8rem;
            margin-right: 0.5rem;
        }
        
        .log-type {
            font-weight: bold;
            color: #ff0000;
            margin-right: 0.5rem;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
        }
        
        .log-message {
            color: #cccccc;
        }
        
        .status-active {
            color: #00ff00;
            text-shadow: 0 0 5px rgba(0, 255, 0, 0.3);
        }
        
        .status-inactive {
            color: #ff0000;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
        }
        
        .btn {
            background: linear-gradient(135deg, var(--blood-primary) 0%, var(--blood-dark) 100%);
            color: var(--text-primary);
            border: 1px solid var(--blood-secondary);
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            text-decoration: none;
            display: inline-block;
            position: relative;
            overflow: hidden;
            text-transform: lowercase;
            letter-spacing: 0.05em;
            box-shadow: var(--shadow-small);
        }
        
        .btn::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent 0%, 
                rgba(255, 255, 255, 0.1) 50%, 
                transparent 100%);
            transition: left 0.5s ease;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, var(--blood-secondary) 0%, var(--blood-primary) 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium), var(--shadow-blood);
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:active {
            transform: translateY(0);
            box-shadow: var(--shadow-small);
        }
        
        .btn-danger {
            background: rgba(255, 0, 0, 0.3);
            border-color: #ff0000;
        }
        
        .btn-danger:hover {
            background: rgba(255, 0, 0, 0.5);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group input, .form-group select {
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            color: #cccccc;
            padding: 0.5rem;
            font-family: "Courier New", monospace;
            width: 100%;
        }
        
        .form-group input:focus, .form-group select:focus {
            border-color: #ff0000;
            outline: none;
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.3);
        }
        
        .form-inline {
            display: flex;
            gap: 1rem;
            align-items: end;
            margin-bottom: 2rem;
        }
        
        .form-group-inline {
            display: flex;
            gap: 1rem;
            align-items: end;
        }
        
        .card {
            background: rgba(26, 26, 26, 0.4);
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 2rem;
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }
        
        .card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, 
                var(--blood-secondary) 0%, 
                var(--blood-primary) 50%, 
                var(--blood-dark) 100%);
            opacity: 0.8;
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            background: rgba(139, 0, 0, 0.1);
        }
        
        .card-title {
            color: var(--blood-light);
            margin: 0;
            font-size: 1.5rem;
            font-weight: bold;
            text-transform: lowercase;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .card-title::before {
            content: "";
            width: 30px;
            height: 2px;
            background: var(--blood-primary);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .inline-form {
            display: inline-block;
            margin-right: 0.5rem;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-inline {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-group-inline {
                flex-direction: column;
                align-items: stretch;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
';

$additionalScripts = "
document.addEventListener('DOMContentLoaded', function() {
    // Cursor system
    const cursor = document.querySelector('.custom-cursor');
    const bloodBg = document.querySelector('.blood-bg');
    let mouseX = 0;
    let mouseY = 0;
    let cursorX = 0;
    let cursorY = 0;
    
    document.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;
    });
    
    function animateCursor() {
        const dx = mouseX - cursorX;
        const dy = mouseY - cursorY;
        
        cursorX += dx * 0.15;
        cursorY += dy * 0.15;
        
        cursor.style.left = cursorX + 'px';
        cursor.style.top = cursorY + 'px';
        
        // Update blood background
        const x = (mouseX / window.innerWidth) * 100;
        const y = (mouseY / window.innerHeight) * 100;
        bloodBg.style.setProperty('--mouse-x', x + '%');
        bloodBg.style.setProperty('--mouse-y', y + '%');
        
        requestAnimationFrame(animateCursor);
    }
    
    animateCursor();
    
    // Hover effects
    document.querySelectorAll('.btn, .stat-card, .card').forEach(element => {
        element.addEventListener('mouseenter', () => {
            cursor.style.width = '20px';
            cursor.style.height = '20px';
        });
        
        element.addEventListener('mouseleave', () => {
            cursor.style.width = '16px';
            cursor.style.height = '16px';
        });
    });
    
    // Табы в админке
    const tabLinks = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Удаляем активный класс у всех табов
            tabLinks.forEach(l => l.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            // Добавляем активный класс выбранному табу
            this.classList.add('active');
            const tabId = this.getAttribute('data-tab');
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // Security
    document.addEventListener('contextmenu', e => e.preventDefault());
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F12' || 
            (e.ctrlKey && e.shiftKey && e.key === 'I') ||
            (e.ctrlKey && e.shiftKey && e.key === 'J') ||
            (e.ctrlKey && e.key === 'U')) {
            e.preventDefault();
        }
    });
    
    // Animate on load
    window.addEventListener('load', () => {
        document.querySelectorAll('.stat-card, .card').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                card.style.transition = 'all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });
});
";

// Маркер для подключения общих файлов
define('ACCESS_ALLOWED', true);

// Подключаем шапку
include 'includes/header.php';
?>

<div class="blood-bg"></div>
<div class="custom-cursor"></div>

<div class="container">
    <div class="admin-header">
        <p>добро пожаловать, <?= htmlspecialchars($_SESSION['username']) ?>. здесь вы можете управлять системой.</p>
    </div>
    
    <div class="stats-grid">
    <div class="stat-card hover-glow">
        <svg width="24" height="24" class="stat-icon"><use xlink:href="#icon-user"></use></svg>
                <span class="stat-value"><?= $stats['active_users'] ?></span>
        <span class="stat-label">Активные пользователи</span>
            </div>
    
    <div class="stat-card hover-glow">
        <svg width="24" height="24" class="stat-icon"><use xlink:href="#icon-message"></use></svg>
                <span class="stat-value"><?= $stats['total_messages'] ?></span>
        <span class="stat-label">Всего сообщений</span>
            </div>
    
    <div class="stat-card hover-glow">
        <svg width="24" height="24" class="stat-icon"><use xlink:href="#icon-file"></use></svg>
                <span class="stat-value"><?= $stats['total_files'] ?></span>
        <span class="stat-label">Файлов</span>
            </div>
    
    <div class="stat-card hover-glow">
        <svg width="24" height="24" class="stat-icon"><use xlink:href="#icon-file"></use></svg>
                <span class="stat-value"><?= formatFileSize($stats['storage_used']) ?></span>
        <span class="stat-label">Используемое место</span>
            </div>
    
    <div class="stat-card hover-glow">
        <svg width="24" height="24" class="stat-icon"><use xlink:href="#icon-admin"></use></svg>
                <span class="stat-value"><?= $stats['logs_24h'] ?></span>
        <span class="stat-label">Логов за 24ч</span>
            </div>
        </div>
        
<div class="tab-container">
    <div class="tab-header">
        <div class="tab-link active" data-tab="tab-users">
            <svg width="16" height="16"><use xlink:href="#icon-user"></use></svg>
            Пользователи
        </div>
        <div class="tab-link" data-tab="tab-messages">
            <svg width="16" height="16"><use xlink:href="#icon-message"></use></svg>
            Сообщения
        </div>
        <div class="tab-link" data-tab="tab-logs">
            <svg width="16" height="16"><use xlink:href="#icon-admin"></use></svg>
            Системные логи
        </div>
        <div class="tab-link" data-tab="tab-actions">
            <svg width="16" height="16"><use xlink:href="#icon-admin"></use></svg>
            Действия
        </div>
    </div>
    
    <div id="tab-users" class="tab-content active">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Управление пользователями</h2>
            </div>
                
            <form method="POST" action="admin.php" class="form-inline">
                    <input type="hidden" name="action" value="create_user">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                
                <div class="form-group-inline">
                    <div class="form-group">
                        <input type="text" name="username" placeholder="Имя пользователя" required>
                    </div>
                    
                    <div class="form-group">
                        <input type="password" name="password" placeholder="Пароль" required>
                    </div>
                    
                    <div class="form-group">
                        <select name="role">
                            <option value="user">Пользователь</option>
                            <option value="admin">Администратор</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">
                        <svg width="16" height="16"><use xlink:href="#icon-user"></use></svg>
                        Создать пользователя
                    </button>
                </div>
            </form>
            
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Имя</th>
                        <th>Роль</th>
                        <th>Статус</th>
                        <th>Создан</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td>
                                <svg width="16" height="16"><use xlink:href="#icon-user"></use></svg>
                                <?= htmlspecialchars($user['username']) ?>
                            </td>
                            <td>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <svg width="16" height="16"><use xlink:href="#icon-admin"></use></svg>
                                    Администратор
                                <?php else: ?>
                                    <svg width="16" height="16"><use xlink:href="#icon-user"></use></svg>
                                    Пользователь
                                <?php endif; ?>
                            </td>
                            <td class="<?= $user['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $user['is_active'] ? 'Активен' : 'Заблокирован' ?>
                            </td>
                            <td><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></td>
                            <td>
                                <form method="POST" action="admin.php" class="inline-form">
                                    <input type="hidden" name="action" value="toggle_user">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                    <button type="submit" class="btn btn-sm">
                                        <?= $user['is_active'] ? 'Заблокировать' : 'Активировать' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div id="tab-messages" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Последние сообщения</h2>
            </div>
            
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Автор</th>
                        <th>Содержание</th>
                        <th>Тип</th>
                        <th>Создано</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_messages as $msg): ?>
                        <tr>
                            <td><?= $msg['id'] ?></td>
                            <td>
                                <svg width="16" height="16"><use xlink:href="#icon-user"></use></svg>
                                <?= htmlspecialchars($msg['username']) ?>
                            </td>
                            <td>
                                <?php if ($msg['is_encrypted']): ?>
                                    <svg width="16" height="16"><use xlink:href="#icon-lock"></use></svg>
                                    [Зашифровано]
                                <?php else: ?>
                                    <?= substr(htmlspecialchars($msg['content']), 0, 50) . (strlen($msg['content']) > 50 ? '...' : '') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $msg['message_type'] ?? 'text' ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></td>
                            <td>
                                <a href="message.php?id=<?= $msg['id'] ?>" class="btn btn-sm">Просмотр</a>
                                <form method="POST" action="admin.php" class="inline-form">
                                    <input type="hidden" name="action" value="delete_message">
                                    <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Вы уверены?')">
                                        Удалить
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div id="tab-logs" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Системные логи</h2>
            </div>
            
            <div class="log-entries">
                <?php foreach ($logs as $log): ?>
                    <div class="log-entry">
                        <span class="log-time">[<?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?>]</span>
                        <span class="log-type"><?= htmlspecialchars($log['action_type'] ?? 'Unknown') ?>:</span>
                        <span class="log-message"><?= htmlspecialchars($log['message'] ?? 'No message') ?></span>
                        <?php if ($log['user_id']): ?>
                            <span class="log-user">(User ID: <?= $log['user_id'] ?>)</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div id="tab-actions" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Системные действия</h2>
            </div>
            
            <div class="admin-actions">
                <form method="POST" action="admin.php">
                    <input type="hidden" name="action" value="purge_old">
                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить все сообщения старше 30 дней?')">
                        <svg width="16" height="16"><use xlink:href="#icon-message"></use></svg>
                        Удалить сообщения старше 30 дней
                    </button>
                </form>
            </div>
        </div>
    </div>
    </div>
</div>

<?php
// Подключаем подвал
include 'includes/footer.php';
?>