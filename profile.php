<?php
require_once 'includes/init.php';
requireLogin();

$userId = isset($_GET['id']) ? (int)$_GET['id'] : $_SESSION['user_id'];

// получение данных пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    redirect('forum.php');
}

$isOwnProfile = ($userId === $_SESSION['user_id']);

// статистика пользователя
$stats = [];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND is_deleted = 0");
$stmt->execute([$userId]);
$stats['messages'] = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND parent_id IS NOT NULL AND is_deleted = 0");
$stmt->execute([$userId]);
$stats['comments'] = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND is_encrypted = 1 AND is_deleted = 0");
$stmt->execute([$userId]);
$stats['encrypted'] = $stmt->fetchColumn() ?: 0;

// последние сообщения пользователя
$stmt = $pdo->prepare("
    SELECT m.*, 
        (SELECT COUNT(*) FROM messages WHERE parent_id = m.id AND is_deleted = 0) as comments_count
    FROM messages m 
    WHERE m.user_id = ? AND m.parent_id IS NULL AND m.is_deleted = 0 
    ORDER BY m.created_at DESC 
    LIMIT 5
");
$stmt->execute([$userId]);
$recentMessages = $stmt->fetchAll();

// Обработка обновления профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwnProfile) {
    if (!verifyToken($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed');
    }
    
    $bio = sanitizeInput($_POST['bio'] ?? '');
    
    // Обновление bio
    $stmt = $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?");
    $stmt->execute([$bio, $userId]);
    
    logSystemAction('PROFILE_UPDATED', "User updated profile");
    
    redirect('profile.php');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['username']) ?> - bloodbrood</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
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
            font-family: 'Courier New', monospace;
            line-height: 1.6;
            min-height: 100vh;
            cursor: none;
            overflow-x: hidden;
            position: relative;
        }
        
        /* Background effects */
        body::before {
            content: '';
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
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            opacity: 0.03;
            z-index: 2;
            pointer-events: none;
            background: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZGVmcz48ZmlsdGVyIGlkPSJub2lzZSI+PGZlVHVyYnVsZW5jZSBiYXNlRnJlcXVlbmN5PSIwLjkiIG51bU9jdGF2ZXM9IjQiIC8+PC9maWx0ZXI+PC9kZWZzPjxyZWN0IHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiIGZpbHRlcj0idXJsKCNub2lzZSkiIG9wYWNpdHk9IjEiLz48L3N2Zz4=');
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
            content: '';
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
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem;
            background: rgba(26, 26, 26, 0.3);
            border: 1px solid var(--border);
            border-radius: 15px;
            margin-bottom: 3rem;
            position: relative;
            backdrop-filter: blur(10px);
        }
        
        .header::after {
            content: '';
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
        }
        
        @keyframes bloodFlow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        /* Profile container */
        .profile-container {
            background: rgba(26, 26, 26, 0.4);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 3rem;
            margin-bottom: 3rem;
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }
        
        .profile-container::before {
            content: '';
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
        
        /* User info section */
        .user-info-section {
            display: flex;
            align-items: flex-start;
            gap: 3rem;
            margin-bottom: 3rem;
        }
        
        .avatar-container {
            position: relative;
            width: 150px;
            height: 150px;
            flex-shrink: 0;
        }
        
        .avatar-placeholder {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blood-dark) 0%, var(--blood-primary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--bg-primary);
            border: 3px solid var(--blood-primary);
            text-transform: uppercase;
            font-weight: bold;
            position: relative;
            overflow: hidden;
        }
        
        .avatar-placeholder::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, 
                rgba(255, 255, 255, 0.1) 0%, 
                transparent 70%);
            animation: avatarShine 8s linear infinite;
        }
        
        @keyframes avatarShine {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .user-details {
            flex: 1;
        }
        
        .username-display {
            font-size: 2.5rem;
            color: var(--blood-light);
            margin-bottom: 1rem;
            font-weight: bold;
            letter-spacing: 0.05em;
            text-transform: lowercase;
        }
        
        .user-role {
            display: inline-block;
            padding: 0.25rem 1rem;
            background: rgba(139, 0, 0, 0.2);
            border: 1px solid var(--blood-primary);
            border-radius: 20px;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            color: var(--blood-light);
            text-transform: lowercase;
        }
        
        .user-meta {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
        }
        
        .stat-card::before {
            content: '';
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
        
        /* Bio section */
        .bio-section {
            background: rgba(26, 26, 26, 0.4);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 2.5rem;
            margin-bottom: 3rem;
            backdrop-filter: blur(10px);
        }
        
        .section-title {
            font-size: 1.5rem;
            color: var(--blood-light);
            margin-bottom: 1.5rem;
            font-weight: bold;
            text-transform: lowercase;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .section-title::before {
            content: '';
            width: 30px;
            height: 2px;
            background: var(--blood-primary);
        }
        
        .bio-content {
            line-height: 1.8;
            color: var(--text-primary);
            white-space: pre-wrap;
        }
        
        .bio-form {
            margin-top: 1.5rem;
        }
        
        .form-textarea {
            width: 100%;
            min-height: 150px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid var(--border);
            color: var(--text-primary);
            padding: 1rem;
            font-family: inherit;
            font-size: 0.95rem;
            resize: vertical;
            transition: all 0.3s ease;
            line-height: 1.6;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: var(--blood-secondary);
            background: rgba(0, 0, 0, 0.9);
            box-shadow: 0 0 0 2px rgba(220, 20, 60, 0.2);
        }
        
        /* Recent activity */
        .activity-section {
            background: rgba(26, 26, 26, 0.4);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 2.5rem;
            backdrop-filter: blur(10px);
        }
        
        .message-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .message-preview {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(139, 0, 0, 0.2);
            border-radius: 10px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .message-preview::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 2px;
            height: 100%;
            background: var(--blood-primary);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .message-preview:hover {
            transform: translateX(5px);
            border-color: rgba(220, 20, 60, 0.3);
        }
        
        .message-preview:hover::before {
            opacity: 0.5;
        }
        
        .message-preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(139, 0, 0, 0.1);
        }
        
        .message-preview-time {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        .message-preview-content {
            color: var(--text-primary);
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .message-preview-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        /* Buttons */
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
            content: '';
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
        
        .edit-btn {
            position: absolute;
            top: 2rem;
            right: 2rem;
            background: transparent;
            border: 1px solid var(--blood-primary);
            color: var(--blood-light);
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .edit-btn:hover {
            background: var(--blood-primary);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header {
                flex-direction: column;
                gap: 1.5rem;
                text-align: center;
            }
            
            .title {
                font-size: 2rem;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .user-info-section {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .edit-btn {
                position: static;
                margin-top: 1rem;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="blood-bg"></div>
    <div class="custom-cursor"></div>
    
    <div class="container">
        <header class="header">
            <h1 class="title">bloodbrood</h1>
            <nav class="nav-links">
                <a href="forum.php" class="btn">форум</a>
                <a href="tags.php" class="btn">теги</a>
                <a href="links.php" class="btn">ссылки</a>
                <?php if (isAdmin()): ?>
                    <a href="admin.php" class="btn">админка</a>
                <?php endif; ?>
                <a href="auth.php?logout=1" class="btn">выход</a>
            </nav>
        </header>
        
        <div class="profile-container">
            <?php if ($isOwnProfile): ?>
                <button class="edit-btn" id="editProfileBtn">редактировать</button>
            <?php endif; ?>
            
            <div class="user-info-section">
                <div class="avatar-container">
                    <div class="avatar-placeholder">
                        <?= mb_strtoupper(mb_substr($user['username'], 0, 1)) ?>
                    </div>
                </div>
                
                <div class="user-details">
                    <h2 class="username-display"><?= htmlspecialchars($user['username']) ?></h2>
                    <?php if ($user['role'] === 'admin'): ?>
                        <span class="user-role">администратор</span>
                    <?php else: ?>
                        <span class="user-role">участник</span>
                    <?php endif; ?>
                    
                    <div class="user-meta">
                        <div>зарегистрирован: <?= date('d.m.Y', strtotime($user['created_at'])) ?></div>
                        <div>последний вход: <?= timeAgo($user['last_login']) ?></div>
                    </div>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-value"><?= $stats['messages'] ?></span>
                    <span class="stat-label">сообщений</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= $stats['comments'] ?></span>
                    <span class="stat-label">комментариев</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= $stats['encrypted'] ?></span>
                    <span class="stat-label">зашифровано</span>
                </div>
            </div>
        </div>
        
        <div class="bio-section">
            <h3 class="section-title">о себе</h3>
            
            <div id="bioDisplay" class="bio-content">
                <?= !empty($user['bio']) ? nl2br(htmlspecialchars($user['bio'])) : '<span style="color: var(--text-muted);">не указано</span>' ?>
            </div>
            
            <?php if ($isOwnProfile): ?>
                <form id="bioForm" method="POST" style="display: none;" class="bio-form">
                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                    <textarea name="bio" class="form-textarea" placeholder="расскажите о себе..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                        <button type="submit" class="btn">сохранить</button>
                        <button type="button" class="btn" id="cancelEditBtn">отмена</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="activity-section">
            <h3 class="section-title">последние сообщения</h3>
            
            <?php if (!empty($recentMessages)): ?>
                <div class="message-list">
                    <?php foreach ($recentMessages as $message): ?>
                        <div class="message-preview">
                            <div class="message-preview-header">
                                <div class="message-preview-time"><?= timeAgo($message['created_at']) ?></div>
                                <?php if ($message['is_encrypted']): ?>
                                    <span style="color: var(--blood-light); font-size: 0.85rem;">🔒 зашифровано</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="message-preview-content">
                                <?php if ($message['is_encrypted']): ?>
                                    <em style="color: var(--text-muted);">зашифрованное сообщение</em>
                                <?php else: ?>
                                    <?= htmlspecialchars(truncateText($message['content'], 200)) ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="message-preview-footer">
                                <span>комментариев: <?= $message['comments_count'] ?></span>
                                <a href="message.php?id=<?= $message['id'] ?>" class="btn" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                    читать полностью →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>пока нет сообщений</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
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
            
            cursorX += dx * 0.4;
            cursorY += dy * 0.4;
            
            cursor.style.left = cursorX + 'px';
            cursor.style.top = cursorY + 'px';
            
            // Update blood background
            const x = (cursorX / window.innerWidth) * 100;
            const y = (cursorY / window.innerHeight) * 100;
            bloodBg.style.setProperty('--mouse-x', x + '%');
            bloodBg.style.setProperty('--mouse-y', y + '%');
            
            requestAnimationFrame(animateCursor);
        }
        
        animateCursor();
        
        // Hover effects
        document.querySelectorAll('.btn, .stat-card, .message-preview').forEach(element => {
            element.addEventListener('mouseenter', () => {
                cursor.style.width = '20px';
                cursor.style.height = '20px';
            });
            
            element.addEventListener('mouseleave', () => {
                cursor.style.width = '16px';
                cursor.style.height = '16px';
            });
        });
        
        // Profile edit functionality
        <?php if ($isOwnProfile): ?>
        const editBtn = document.getElementById('editProfileBtn');
        const cancelBtn = document.getElementById('cancelEditBtn');
        const bioDisplay = document.getElementById('bioDisplay');
        const bioForm = document.getElementById('bioForm');
        
        editBtn?.addEventListener('click', () => {
            bioDisplay.style.display = 'none';
            bioForm.style.display = 'block';
            editBtn.style.display = 'none';
        });
        
        cancelBtn?.addEventListener('click', () => {
            bioDisplay.style.display = 'block';
            bioForm.style.display = 'none';
            editBtn.style.display = 'block';
        });
        <?php endif; ?>
        
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
            document.querySelectorAll('.stat-card, .message-preview').forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html> 