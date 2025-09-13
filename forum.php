<?php
require_once 'includes/init.php';
requireLogin();

// параметры фильтрации
$tag = isset($_GET['tag']) ? (int)$_GET['tag'] : null;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// формирование запроса
$params = [];
$whereClause = "m.parent_id IS NULL AND m.is_deleted = 0";

if ($tag) {
    $whereClause .= " AND EXISTS (SELECT 1 FROM message_tags mt WHERE mt.message_id = m.id AND mt.tag_id = ?)";
    $params[] = $tag;
}

if (!empty($search)) {
    $whereClause .= " AND (m.content LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// количество сообщений для пагинации
$countQuery = "SELECT COUNT(*) FROM messages m JOIN users u ON m.user_id = u.id WHERE $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalMessages = $stmt->fetchColumn();
$totalPages = ceil($totalMessages / $perPage);

// получение сообщений
$query = "
    SELECT m.*, u.username, u.role,
        (SELECT COUNT(*) FROM messages WHERE parent_id = m.id AND is_deleted = 0) as comments_count
    FROM messages m 
    JOIN users u ON m.user_id = u.id 
    WHERE $whereClause 
    ORDER BY m.created_at DESC 
    LIMIT $offset, $perPage
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll();

// Получение всех тегов для фильтра
$stmt = $pdo->query("SELECT * FROM tags ORDER BY name");
$tags = $stmt->fetchAll();

// Получение статистики форума
$stats = [];
$stats['total_messages'] = $pdo->query("SELECT COUNT(*) FROM messages WHERE is_deleted = 0")->fetchColumn();
$stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$stats['total_files'] = $pdo->query("SELECT COUNT(*) FROM attachments")->fetchColumn();
$stats['encrypted_messages'] = $pdo->query("SELECT COUNT(*) FROM messages WHERE is_encrypted = 1 AND is_deleted = 0")->fetchColumn();

// Получение активных пользователей (последние 24 часа)
$activeUsers = $pdo->query("
    SELECT DISTINCT u.username, u.role 
    FROM users u 
    WHERE u.last_login > DATE_SUB(NOW(), INTERVAL 24 HOUR) 
    ORDER BY u.last_login DESC 
    LIMIT 10
")->fetchAll();

// Получение последних действий
$recentActivity = $pdo->query("
    SELECT m.id, m.created_at, u.username, 'message' as type
    FROM messages m
    JOIN users u ON m.user_id = u.id
    WHERE m.is_deleted = 0
    ORDER BY m.created_at DESC
    LIMIT 5
")->fetchAll();

// Обработка добавления нового сообщения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $content = trim($_POST['content']);
    $isEncrypted = isset($_POST['encrypt']) && $_POST['encrypt'] === '1';
    $selectedTags = $_POST['tags'] ?? [];
    
    if (!empty($content)) {
        try {
            $pdo->beginTransaction();
            
            if ($isEncrypted) {
                $content = encrypt($content, ENCRYPTION_KEY);
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO messages (user_id, content, is_encrypted) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $content, $isEncrypted]);
            $messageId = $pdo->lastInsertId();
            
            if (!empty($selectedTags)) {
                $tagValues = [];
                $tagParams = [];
                
                foreach ($selectedTags as $tagId) {
                    $tagValues[] = "(?, ?)";
                    $tagParams[] = $messageId;
                    $tagParams[] = (int)$tagId;
                }
                
                $tagQuery = "INSERT INTO message_tags (message_id, tag_id) VALUES " . implode(', ', $tagValues);
                $stmt = $pdo->prepare($tagQuery);
                $stmt->execute($tagParams);
            }
            
            $pdo->commit();
            logSystemAction('MESSAGE_CREATED', "User created new message #$messageId");
            
            // Update last_login to mark activity
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            redirect('forum.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Message creation error: ' . $e->getMessage());
            $error = 'Ошибка публикации сообщения';
        }
    } else {
        $error = 'Сообщение не может быть пустым';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>bloodbrood - форум</title>
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
            background: radial-gradient(ellipse at center, #0a0a0a 0%, #000000 100%);
            color: #cccccc;
            font-family: 'Courier New', monospace;
            line-height: 1.6;
            min-height: 100vh;
            cursor: none;
            overflow-x: hidden;
            position: relative;
        }
        
        /* Кровавый фон с улучшенными эффектами */
        .blood-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: -2;
            background: 
                radial-gradient(circle at var(--mouse-x, 50%) var(--mouse-y, 50%), 
                    rgba(139, 0, 0, 0.08) 0%, 
                    transparent 35%),
                radial-gradient(circle at 30% 60%, 
                    rgba(220, 20, 60, 0.02) 0%, 
                    transparent 40%),
                radial-gradient(circle at 70% 40%, 
                    rgba(139, 0, 0, 0.02) 0%, 
                    transparent 40%);
            transition: background 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        /* Animated blood veins background */
        .blood-veins {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.02;
            z-index: 1;
            pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M10 10 Q 50 0, 90 10 T 90 50 Q 100 90, 90 90 T 50 90 Q 10 100, 10 90 T 10 50 Q 0 10, 10 10' stroke='%238b0000' fill='none' stroke-width='0.5'/%3E%3C/svg%3E");
            background-size: 200px 200px;
            animation: veinsFlow 30s linear infinite;
        }
        
        @keyframes veinsFlow {
            0% { transform: translate(0, 0); }
            100% { transform: translate(-200px, -200px); }
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
        
        /* Main layout with sidebar */
        .main-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            position: relative;
            z-index: 5;
        }
        
        /* Sidebar */
        .sidebar {
            position: sticky;
            top: 2rem;
            height: fit-content;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .sidebar-section {
            background: rgba(26, 26, 26, 0.4);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }
        
        .sidebar-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 2px;
            height: 100%;
            background: linear-gradient(180deg, 
                var(--blood-secondary) 0%, 
                var(--blood-primary) 50%, 
                var(--blood-dark) 100%);
            opacity: 0.5;
        }
        
        .sidebar-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--blood-light);
            margin-bottom: 1rem;
            text-transform: lowercase;
            letter-spacing: 0.05em;
        }
        
        /* Stats section */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 0.75rem;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(139, 0, 0, 0.2);
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            background: rgba(139, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .stat-value {
            font-size: 1.5rem;
            color: var(--blood-secondary);
            font-weight: bold;
            display: block;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: lowercase;
        }
        
        /* Active users */
        .user-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .user-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 3px;
            height: 3px;
            background: #00ff00;
            border-radius: 50%;
            transform: translateY(-50%);
            box-shadow: 0 0 5px #00ff00;
            animation: onlineGlow 2s ease-in-out infinite;
        }
        
        @keyframes onlineGlow {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }
        
        .user-item:hover {
            background: rgba(139, 0, 0, 0.1);
            padding-left: 1rem;
        }
        
        .user-name {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .user-role {
            font-size: 0.7rem;
            padding: 0.15rem 0.5rem;
            background: rgba(139, 0, 0, 0.3);
            border-radius: 10px;
            color: var(--blood-light);
        }
        
        /* Recent activity */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .activity-item {
            padding: 0.75rem;
            background: rgba(0, 0, 0, 0.2);
            border-left: 2px solid var(--blood-primary);
            transition: all 0.3s ease;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .activity-item:hover {
            background: rgba(139, 0, 0, 0.05);
            border-left-color: var(--blood-secondary);
        }
        
        .activity-user {
            color: var(--blood-light);
            font-weight: bold;
        }
        
        .activity-time {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: block;
            margin-top: 0.25rem;
        }
        
        /* Main content */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
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
            font-size: 3rem;
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
            position: relative;
        }
        
        @keyframes bloodFlow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        .title::before {
            content: 'bloodbrood';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, 
                var(--blood-dark) 0%, 
                transparent 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            opacity: 0.5;
            filter: blur(3px);
            transform: translateX(2px) translateY(2px);
            z-index: -1;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            font-size: 0.95rem;
            color: var(--text-secondary);
        }
        
        .user-info span {
            padding: 0.5rem 1rem;
            background: rgba(139, 0, 0, 0.1);
            border: 1px solid var(--border);
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .user-info span:hover {
            background: rgba(139, 0, 0, 0.2);
            color: var(--text-primary);
            transform: translateY(-1px);
        }
        
        /* Atmospheric decorations */
        .blood-drops {
            position: fixed;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 4;
        }
        
        .blood-drop {
            position: absolute;
            width: 6px;
            height: 8px;
            background: linear-gradient(180deg, 
                var(--blood-secondary) 0%, 
                var(--blood-primary) 60%, 
                var(--blood-dark) 100%);
            border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
            opacity: 0;
            animation: bloodFall 5s linear infinite;
        }
        
        @keyframes bloodFall {
            0% {
                transform: translateY(-10px) scale(0);
                opacity: 0;
            }
            10% {
                opacity: 0.8;
            }
            90% {
                opacity: 0.8;
            }
            100% {
                transform: translateY(100vh) scale(1);
                opacity: 0;
            }
        }
        
        .blood-drop:nth-child(1) { left: 10%; animation-delay: 0s; }
        .blood-drop:nth-child(2) { left: 20%; animation-delay: 0.5s; }
        .blood-drop:nth-child(3) { left: 30%; animation-delay: 1s; }
        .blood-drop:nth-child(4) { left: 40%; animation-delay: 1.5s; }
        .blood-drop:nth-child(5) { left: 50%; animation-delay: 2s; }
        .blood-drop:nth-child(6) { left: 60%; animation-delay: 2.5s; }
        .blood-drop:nth-child(7) { left: 70%; animation-delay: 3s; }
        .blood-drop:nth-child(8) { left: 80%; animation-delay: 3.5s; }
        .blood-drop:nth-child(9) { left: 90%; animation-delay: 4s; }
        .blood-drop:nth-child(10) { left: 95%; animation-delay: 4.5s; }
        
        /* Responsive design */
        @media (max-width: 1024px) {
            .main-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: relative;
                top: 0;
                order: 2;
            }
            
            .main-content {
                order: 1;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1.5rem;
                text-align: center;
            }
            
            .title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Existing styles */
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
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent) 0%, var(--blood-primary) 100%);
            border-color: var(--accent);
            font-weight: bold;
        }
        
        .controls {
            margin-bottom: 3rem;
            padding: 2rem;
            background: rgba(26, 26, 26, 0.4);
            border: 1px solid var(--border);
            border-radius: 10px;
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }
        
        .controls::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, 
                rgba(139, 0, 0, 0.05) 0%, 
                transparent 70%);
            animation: controlsPulse 8s ease-in-out infinite;
        }
        
        @keyframes controlsPulse {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(180deg); }
        }
        
        .controls-grid {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 1rem;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .search-input,
        .select-input {
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid var(--border);
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .search-input:focus,
        .select-input:focus {
            outline: none;
            border-color: var(--blood-secondary);
            background: rgba(0, 0, 0, 0.7);
            box-shadow: 0 0 0 2px rgba(220, 20, 60, 0.2);
        }
        
        .search-input::placeholder {
            color: var(--text-muted);
            opacity: 0.7;
        }
        
        .new-message {
            background: rgba(26, 26, 26, 0.6);
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 2.5rem;
            margin-bottom: 3rem;
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
            backdrop-filter: blur(20px);
        }
        
        .new-message::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, 
                transparent 0%, 
                var(--blood-secondary) 50%, 
                transparent 100%);
            opacity: 0.5;
        }
        
        .new-message.show {
            opacity: 1;
            max-height: 1000px;
            margin-bottom: 3rem;
        }
        
        .form-group {
            margin-bottom: 2rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.75rem;
            color: var(--text-secondary);
            font-size: 0.95rem;
            letter-spacing: 0.05em;
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
        
        .tags-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .tag-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.5rem 1rem;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid currentColor;
            border-radius: 20px;
            transition: all 0.3s ease;
            opacity: 0.7;
        }
        
        .tag-option:hover {
            opacity: 1;
            background: rgba(0, 0, 0, 0.7);
            transform: translateY(-2px);
        }
        
        .tag-checkbox {
            width: 16px;
            height: 16px;
            accent-color: var(--blood-secondary);
        }
        
        .tag-checkbox:checked + .tag-option {
            opacity: 1;
            background: rgba(139, 0, 0, 0.2);
        }
        
        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
        }
        
        .encrypt-option {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .encrypt-option:hover {
            color: var(--text-primary);
        }
        
        .messages-list {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        
        .message-card {
            background: rgba(26, 26, 26, 0.4);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 2rem;
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        
        .message-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 100%;
            background: linear-gradient(180deg, 
                var(--blood-secondary) 0%, 
                var(--blood-primary) 50%, 
                var(--blood-dark) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .message-card:hover {
            transform: translateX(5px);
            border-color: rgba(220, 20, 60, 0.3);
            box-shadow: var(--shadow-medium);
        }
        
        .message-card:hover::before {
            opacity: 1;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(139, 0, 0, 0.1);
        }
        
        .message-author {
            font-weight: bold;
            color: var(--blood-light);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .admin-badge {
            background: linear-gradient(135deg, var(--blood-secondary) 0%, var(--blood-primary) 100%);
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            border-radius: 15px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: normal;
        }
        
        .message-time {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        .message-content {
            margin-bottom: 1.5rem;
            line-height: 1.8;
            color: var(--text-primary);
        }
        
        .encrypted-message {
            background: linear-gradient(135deg, 
                rgba(75, 0, 0, 0.3) 0%, 
                rgba(139, 0, 0, 0.2) 100%);
            padding: 1.5rem;
            border-radius: 5px;
            border: 1px solid var(--blood-dark);
            margin-bottom: 1rem;
            text-align: center;
            font-style: italic;
            color: var(--text-secondary);
            position: relative;
            overflow: hidden;
        }
        
        .encrypted-message::before {
            content: '🔒';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 3rem;
            opacity: 0.1;
        }
        
        .decrypt-button {
            background: transparent;
            border: 1px solid var(--blood-primary);
            color: var(--blood-light);
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .decrypt-button:hover {
            background: var(--blood-primary);
            border-color: var(--blood-secondary);
        }
        
        .message-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .tag {
            padding: 0.25rem 0.75rem;
            border: 1px solid currentColor;
            border-radius: 15px;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.3s ease;
            opacity: 0.8;
        }
        
        .tag:hover {
            opacity: 1;
            transform: translateY(-2px);
            background: rgba(0, 0, 0, 0.5);
        }
        
        .message-footer {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 1.5rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(139, 0, 0, 0.1);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        
        .empty-state p {
            margin-bottom: 0.5rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 4rem;
            padding: 2rem 0;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 0.75rem;
            background: rgba(26, 26, 26, 0.6);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            min-width: 40px;
            text-align: center;
        }
        
        .pagination a:hover {
            background: rgba(139, 0, 0, 0.2);
            border-color: var(--blood-secondary);
            color: var(--text-primary);
            transform: translateY(-2px);
        }
        
        .pagination .current {
            background: var(--blood-primary);
            border-color: var(--blood-secondary);
            color: var(--text-primary);
            font-weight: bold;
        }
        
        .blood-particle {
            position: fixed;
            width: 4px;
            height: 4px;
            background: radial-gradient(circle, var(--blood-secondary) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 4;
            opacity: 0.6;
            animation: floatParticle 15s linear infinite;
        }
        
        @keyframes floatParticle {
            0% {
                transform: translateY(100vh) translateX(0) scale(0);
                opacity: 0;
            }
            10% {
                opacity: 0.6;
            }
            90% {
                opacity: 0.6;
            }
            100% {
                transform: translateY(-100px) translateX(100px) scale(1.5);
                opacity: 0;
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .container > * {
            animation: fadeIn 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
            animation-fill-mode: both;
        }
        
        .container > *:nth-child(1) { animation-delay: 0.1s; }
        .container > *:nth-child(2) { animation-delay: 0.2s; }
        .container > *:nth-child(3) { animation-delay: 0.3s; }
        .container > *:nth-child(4) { animation-delay: 0.4s; }
        .container > *:nth-child(5) { animation-delay: 0.5s; }
        
        @media (prefers-color-scheme: dark) {
            :root {
                --text-primary: #f0f0f0;
                --text-secondary: #b0b0b0;
            }
        }
    </style>
</head>
<body>
    <div class="blood-bg"></div>
    <div class="blood-veins"></div>
    <div class="custom-cursor"></div>
    
    <!-- Blood drops animation -->
    <div class="blood-drops">
        <div class="blood-drop"></div>
        <div class="blood-drop"></div>
        <div class="blood-drop"></div>
        <div class="blood-drop"></div>
        <div class="blood-drop"></div>
        <div class="blood-drop"></div>
        <div class="blood-drop"></div>
        <div class="blood-drop"></div>
        <div class="blood-drop"></div>
        <div class="blood-drop"></div>
    </div>
    
    <div class="main-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- Stats section -->
            <div class="sidebar-section">
                <h3 class="sidebar-title">статистика форума</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-value"><?= $stats['total_messages'] ?></span>
                        <span class="stat-label">сообщений</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $stats['total_users'] ?></span>
                        <span class="stat-label">участников</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $stats['encrypted_messages'] ?></span>
                        <span class="stat-label">зашифровано</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $stats['total_files'] ?></span>
                        <span class="stat-label">файлов</span>
                    </div>
                </div>
            </div>
            
            <!-- Active users -->
            <div class="sidebar-section">
                <h3 class="sidebar-title">активные участники</h3>
                <div class="user-list">
                    <?php if (empty($activeUsers)): ?>
                        <p style="color: var(--text-muted); font-size: 0.9rem;">никого нет онлайн</p>
                    <?php else: ?>
                        <?php foreach ($activeUsers as $user): ?>
                            <div class="user-item">
                                <span class="user-name"><?= htmlspecialchars($user['username']) ?></span>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="user-role">admin</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent activity -->
            <div class="sidebar-section">
                <h3 class="sidebar-title">последняя активность</h3>
                <div class="activity-list">
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <span class="activity-user"><?= htmlspecialchars($activity['username']) ?></span>
                            опубликовал сообщение
                            <span class="activity-time"><?= timeAgo($activity['created_at']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Navigation -->
            <div class="sidebar-section">
                <h3 class="sidebar-title">навигация</h3>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <a href="profile.php" class="btn" style="width: 100%; text-align: center;">профиль</a>
                    <a href="tags.php" class="btn" style="width: 100%; text-align: center;">теги</a>
                    <a href="links.php" class="btn" style="width: 100%; text-align: center;">ссылки</a>
                    <?php if (isAdmin()): ?>
                        <a href="admin.php" class="btn" style="width: 100%; text-align: center;">админка</a>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
        
        <!-- Main content -->
        <div class="blood-bg"></div>
        
        <div class="main-content">
            <header class="header">
                <h1 class="title">bloodbrood</h1>
                <div class="user-info">
                    <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <a href="auth.php?logout=1" class="btn">выход</a>
                </div>
            </header>
            
            <div class="controls">
                <form method="GET" action="forum.php" class="controls-grid">
                    <input type="text" name="search" class="search-input" 
                           placeholder="поиск сообщений..." 
                           value="<?= htmlspecialchars($search) ?>"
                           autocomplete="off">
                    
                    <select name="tag" class="select-input">
                        <option value="">все теги</option>
                        <?php foreach ($tags as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= ($tag == $t['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="btn">найти</button>
                </form>
                
                <?php if (!empty($search) || $tag): ?>
                    <div style="margin-top: 1.5rem;">
                        <a href="forum.php" class="btn">← сбросить фильтры</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="display: flex; justify-content: flex-end; margin-bottom: 2rem;">
                <button id="toggleNewMessage" class="btn btn-primary">+ новое сообщение</button>
            </div>
            
            <?php if (isset($error)): ?>
                <div style="background: linear-gradient(135deg, rgba(139, 0, 0, 0.1) 0%, rgba(139, 0, 0, 0.05) 100%); 
                            border: 1px solid rgba(139, 0, 0, 0.3); 
                            padding: 1.5rem; 
                            margin-bottom: 2rem; 
                            color: var(--blood-light); 
                            font-family: 'Courier New', monospace; 
                            font-size: 0.95rem;
                            display: flex;
                            align-items: center;
                            gap: 0.75rem;">
                    <span style="font-size: 1.2rem;">⚠</span>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <div id="newMessageForm" class="new-message">
                <h2 style="color: var(--text-primary); 
                           margin-bottom: 2rem; 
                           font-size: 1.6rem;
                           font-weight: bold;">новое сообщение</h2>
                
                <form method="POST" action="forum.php">
                    <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                    
                    <div class="form-group">
                        <label for="content" class="form-label">содержание сообщения</label>
                        <textarea id="content" name="content" class="form-textarea" 
                                  placeholder="введите ваше сообщение здесь..." 
                                  required
                                  autocomplete="off"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">теги (опционально)</label>
                        <div class="tags-grid">
                            <?php foreach ($tags as $t): ?>
                                <label class="tag-option" style="color: <?= htmlspecialchars($t['color']) ?>">
                                    <input type="checkbox" name="tags[]" value="<?= $t['id'] ?>" class="tag-checkbox">
                                    <?= htmlspecialchars($t['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-footer">
                        <label class="encrypt-option">
                            <input type="checkbox" name="encrypt" value="1" style="width: 18px; height: 18px;">
                            зашифровать сообщение
                        </label>
                        
                        <button type="submit" class="btn btn-primary">опубликовать</button>
                    </div>
                </form>
            </div>
            
            <div class="messages-list">
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <?php if (!empty($search) || $tag): ?>
                            <p>сообщения не найдены</p>
                            <p style="margin-top: 1rem; font-size: 0.95rem;">попробуйте изменить параметры поиска</p>
                        <?php else: ?>
                            <p>здесь пока пусто</p>
                            <p style="margin-top: 1rem; font-size: 0.95rem;">будьте первым, кто оставит сообщение</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="message-card">
                            <div class="message-header">
                                <div class="message-author">
                                    <?= htmlspecialchars($message['username']) ?>
                                    <?php if ($message['role'] === 'admin'): ?>
                                        <span class="admin-badge">admin</span>
                                    <?php endif; ?>
                                </div>
                                <div class="message-time"><?= timeAgo($message['created_at']) ?></div>
                            </div>
                            
                            <div class="message-content">
                                <?php if ($message['is_encrypted']): ?>
                                    <div class="encrypted-message" id="encrypted-content-<?= $message['id'] ?>">
                                        зашифрованное сообщение
                                    </div>
                                    <button class="btn decrypt-button" data-id="<?= $message['id'] ?>">расшифровать</button>
                                <?php else: ?>
                                    <?= nl2br(htmlspecialchars(truncateText($message['content'], 300))) ?>
                                    <?php if (mb_strlen($message['content']) > 300): ?>
                                        <div style="margin-top: 1.5rem;">
                                            <a href="message.php?id=<?= $message['id'] ?>" class="btn">читать полностью →</a>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT t.* FROM tags t 
                                JOIN message_tags mt ON t.id = mt.tag_id 
                                WHERE mt.message_id = ?
                            ");
                            $stmt->execute([$message['id']]);
                            $messageTags = $stmt->fetchAll();
                            ?>
                            
                            <?php if (!empty($messageTags)): ?>
                                <div class="message-tags">
                                    <?php foreach ($messageTags as $t): ?>
                                        <a href="forum.php?tag=<?= $t['id'] ?>" class="tag" 
                                           style="color: <?= htmlspecialchars($t['color']) ?>; 
                                                  border-color: <?= htmlspecialchars($t['color']) ?>">
                                            #<?= htmlspecialchars($t['name']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="message-footer">
                                <a href="message.php?id=<?= $message['id'] ?>" class="btn">
                                    <?php if ($message['comments_count'] > 0): ?>
                                        комментарии (<?= $message['comments_count'] ?>)
                                    <?php else: ?>
                                        комментировать
                                    <?php endif; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="forum.php?page=1<?= $tag ? "&tag=$tag" : "" ?><?= $search ? "&search=" . urlencode($search) : "" ?>">
                                    ««
                                </a>
                                <a href="forum.php?page=<?= $page - 1 ?><?= $tag ? "&tag=$tag" : "" ?><?= $search ? "&search=" . urlencode($search) : "" ?>">
                                    «
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="forum.php?page=<?= $i ?><?= $tag ? "&tag=$tag" : "" ?><?= $search ? "&search=" . urlencode($search) : "" ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="forum.php?page=<?= $page + 1 ?><?= $tag ? "&tag=$tag" : "" ?><?= $search ? "&search=" . urlencode($search) : "" ?>">
                                    »
                                </a>
                                <a href="forum.php?page=<?= $totalPages ?><?= $tag ? "&tag=$tag" : "" ?><?= $search ? "&search=" . urlencode($search) : "" ?>">
                                    »»
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Современная система курсора с физикой
        class CursorSystem {
            constructor() {
                this.cursor = document.querySelector('.custom-cursor');
                this.bloodBg = document.querySelector('.blood-bg');
                this.mouseX = 0;
                this.mouseY = 0;
                this.cursorX = 0;
                this.cursorY = 0;
                this.velocityX = 0;
                this.velocityY = 0;
                this.friction = 0.88;
                this.acceleration = 0.12;
                
                this.init();
            }
            
            init() {
                document.addEventListener('mousemove', (e) => {
                    this.mouseX = e.clientX;
                    this.mouseY = e.clientY;
                });
                
                this.animate();
                this.setupHoverEffects();
            }
            
            animate() {
                // Физика движения курсора
                const dx = this.mouseX - this.cursorX;
                const dy = this.mouseY - this.cursorY;
                
                this.velocityX += dx * this.acceleration;
                this.velocityY += dy * this.acceleration;
                
                this.velocityX *= this.friction;
                this.velocityY *= this.friction;
                
                this.cursorX += this.velocityX;
                this.cursorY += this.velocityY;
                
                // Обновление позиции
                this.cursor.style.left = this.cursorX + 'px';
                this.cursor.style.top = this.cursorY + 'px';
                
                // Обновление фона
                const x = (this.cursorX / window.innerWidth) * 100;
                const y = (this.cursorY / window.innerHeight) * 100;
                this.bloodBg.style.setProperty('--mouse-x', x + '%');
                this.bloodBg.style.setProperty('--mouse-y', y + '%');
                
                requestAnimationFrame(() => this.animate());
            }
            
            setupHoverEffects() {
                // Эффекты для кнопок
                document.querySelectorAll('.btn').forEach(btn => {
                    btn.addEventListener('mouseenter', () => {
                        this.cursor.style.width = '20px';
                        this.cursor.style.height = '20px';
                        this.cursor.style.filter = 'drop-shadow(0 0 10px rgba(255, 0, 0, 0.5))';
                    });
                    
                    btn.addEventListener('mouseleave', () => {
                        this.cursor.style.width = '14px';
                        this.cursor.style.height = '14px';
                        this.cursor.style.filter = 'drop-shadow(0 0 6px rgba(255, 0, 0, 0.3))';
                    });
                });
                
                // Эффекты для карточек сообщений
                document.querySelectorAll('.message-card').forEach(card => {
                    card.addEventListener('mouseenter', () => {
                        this.cursor.style.borderColor = 'rgba(255, 0, 0, 0.4)';
                    });
                    
                    card.addEventListener('mouseleave', () => {
                        this.cursor.style.borderColor = 'rgba(255, 0, 0, 0.2)';
                    });
                });
            }
        }
        
        // Инициализация системы курсора
        const cursorSystem = new CursorSystem();
        
        // Система частиц
        class ParticleSystem {
            constructor() {
                this.particles = [];
                this.maxParticles = 5;
                this.init();
            }
            
            init() {
                setInterval(() => this.createParticle(), 5000);
            }
            
            createParticle() {
                if (this.particles.length >= this.maxParticles) return;
                
                const particle = document.createElement('div');
                particle.className = 'blood-particle';
                particle.style.left = Math.random() * window.innerWidth + 'px';
                particle.style.top = Math.random() * window.innerHeight + 'px';
                particle.style.animationDuration = (10 + Math.random() * 10) + 's';
                particle.style.animationDelay = Math.random() * 2 + 's';
                
                document.body.appendChild(particle);
                this.particles.push(particle);
                
                setTimeout(() => {
                    particle.remove();
                    this.particles = this.particles.filter(p => p !== particle);
                }, 20000);
            }
        }
        
        // Инициализация системы частиц
        const particleSystem = new ParticleSystem();
        
        // Управление формой нового сообщения
        const toggleBtn = document.getElementById('toggleNewMessage');
        const messageForm = document.getElementById('newMessageForm');
        
        toggleBtn.addEventListener('click', function() {
            if (messageForm.classList.contains('show')) {
                messageForm.classList.remove('show');
                toggleBtn.innerHTML = '+ новое сообщение';
            } else {
                messageForm.classList.add('show');
                document.getElementById('content').focus();
                toggleBtn.innerHTML = '× скрыть форму';
            }
        });
        
        // Современная система расшифровки
        document.querySelectorAll('.decrypt-button').forEach(btn => {
            btn.addEventListener('click', async function() {
                const messageId = this.getAttribute('data-id');
                const button = this;
                
                button.disabled = true;
                button.textContent = 'расшифровка...';
                
                try {
                    const response = await fetch('/bloodbrood/api/decrypt.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ message_id: parseInt(messageId) })
                    });
                    
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        const contentDiv = document.getElementById(`encrypted-content-${messageId}`);
                        // Replace the entire encrypted message container with decrypted content
                        const messageContent = contentDiv.closest('.message-content');
                        messageContent.innerHTML = data.content.replace(/\n/g, '<br>');
                        
                        // Add animation
                        messageContent.style.opacity = '0';
                        setTimeout(() => {
                            messageContent.style.transition = 'opacity 0.5s ease';
                            messageContent.style.opacity = '1';
                        }, 100);
                    } else {
                        button.textContent = data.error || 'ошибка расшифровки';
                        setTimeout(() => {
                            button.textContent = 'расшифровать';
                            button.disabled = false;
                        }, 2000);
                    }
                } catch (error) {
                    console.error('Decryption error:', error);
                    button.textContent = 'ошибка соединения';
                    setTimeout(() => {
                        button.textContent = 'расшифровать';
                        button.disabled = false;
                    }, 2000);
                }
            });
        });
        
        // Защита от инспектора
        document.addEventListener('contextmenu', e => e.preventDefault());
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F12' || 
                (e.ctrlKey && e.shiftKey && e.key === 'I') ||
                (e.ctrlKey && e.shiftKey && e.key === 'J') ||
                (e.ctrlKey && e.key === 'U')) {
                e.preventDefault();
            }
        });
        
        // Автосохранение черновика
        const contentTextarea = document.getElementById('content');
        if (contentTextarea) {
            // Загрузка сохраненного черновика
            const savedDraft = localStorage.getItem('messageDraft');
            if (savedDraft) {
                contentTextarea.value = savedDraft;
            }
            
            // Сохранение черновика при вводе
            contentTextarea.addEventListener('input', function() {
                localStorage.setItem('messageDraft', this.value);
            });
            
            // Очистка черновика при отправке формы
            document.querySelector('form').addEventListener('submit', function() {
                localStorage.removeItem('messageDraft');
            });
        }
        
        // Плавная прокрутка
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Анимация при загрузке
        window.addEventListener('load', () => {
            document.querySelectorAll('.message-card').forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
        
        // Функция для получения правильного окончания
        function getPlural(number, one, few, many) {
            if (number % 10 === 1 && number % 100 !== 11) {
                return one;
            } else if (number % 10 >= 2 && number % 10 <= 4 && (number % 100 < 10 || number % 100 >= 20)) {
                return few;
            } else {
                return many;
            }
        }
        
        // Кровавый фон с эффектами
        const bloodBg = document.querySelector(".blood-bg");
        let mouseX = 0, mouseY = 0;
        
        document.addEventListener("mousemove", (e) => {
            mouseX = e.clientX;
            mouseY = e.clientY;
            
            const x = (mouseX / window.innerWidth) * 100;
            const y = (mouseY / window.innerHeight) * 100;
            bloodBg.style.setProperty("--mouse-x", x + "%");
            bloodBg.style.setProperty("--mouse-y", y + "%");
        });
        
        // Эффекты при наведении на элементы
        const messageCards = document.querySelectorAll(".message-card");
        messageCards.forEach(card => {
            card.addEventListener("mouseenter", function() {
                this.style.transform = "translateY(-5px) scale(1.02)";
                this.style.boxShadow = "0 0 30px rgba(255, 0, 0, 0.4)";
            });
            
            card.addEventListener("mouseleave", function() {
                this.style.transform = "translateY(0) scale(1)";
                this.style.boxShadow = "0 0 20px rgba(255, 0, 0, 0.3)";
            });
        });
        
        // Эффекты для кнопок
        const buttons = document.querySelectorAll(".btn");
        buttons.forEach(btn => {
            btn.addEventListener("mouseenter", function() {
                this.style.textShadow = "0 0 15px rgba(255, 0, 0, 0.8)";
            });
            
            btn.addEventListener("mouseleave", function() {
                this.style.textShadow = "0 0 5px rgba(255, 0, 0, 0.3)";
            });
        });
    </script>
</body>
</html>