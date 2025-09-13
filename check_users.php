<?php
require_once 'config/database.php';

// Only accessible to authenticated admins
// Comment this out if you need to check users during initial setup
// requireAdmin();

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>bloodbrood - Пользователи</title>
    <style>
        body {
            background: #0a0a0a;
            color: #e0e0e0;
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: #dc143c;
            text-align: center;
            margin-bottom: 30px;
        }
        .user-card {
            background: rgba(139, 0, 0, 0.1);
            border: 1px solid rgba(220, 20, 60, 0.3);
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .user-card h3 {
            color: #ff6b6b;
            margin: 0 0 10px 0;
        }
        .user-info {
            margin: 5px 0;
        }
        .user-info span {
            color: #999;
        }
        .password-note {
            background: rgba(255, 0, 0, 0.1);
            border: 1px solid rgba(255, 0, 0, 0.3);
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .back-link {
            display: inline-block;
            color: #dc143c;
            text-decoration: none;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .back-link:hover {
            color: #ff6b6b;
            text-shadow: 0 0 5px rgba(220, 20, 60, 0.5);
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Вернуться</a>
        <h1>Существующие пользователи</h1>
        
        <div class="password-note">
            <strong>Важно:</strong> Пароли хранятся в зашифрованном виде. Используйте указанные ниже пароли для входа.
        </div>
        
        <?php
        try {
            $stmt = $pdo->query("SELECT * FROM users ORDER BY id");
            $users = $stmt->fetchAll();
            
            foreach ($users as $user) {
                ?>
                <div class="user-card">
                    <h3><?= htmlspecialchars($user['username']) ?></h3>
                    <div class="user-info">
                        <span>ID:</span> <?= $user['id'] ?>
                    </div>
                    <div class="user-info">
                        <span>Роль:</span> <?= $user['role'] ?>
                    </div>
                    <div class="user-info">
                        <span>Статус:</span> <?= $user['is_active'] ? 'Активен' : 'Заблокирован' ?>
                    </div>
                    <div class="user-info">
                        <span>Создан:</span> <?= $user['created_at'] ?>
                    </div>
                    <?php if ($user['last_login']): ?>
                    <div class="user-info">
                        <span>Последний вход:</span> <?= $user['last_login'] ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Показываем пароли только для предустановленных пользователей
                    if ($user['username'] === 'admin') {
                        echo '<div class="user-info" style="color: #ff6b6b;">
                                <span>Пароль:</span> blood666admin
                              </div>';
                    } elseif ($user['username'] === 'blooduser') {
                        echo '<div class="user-info" style="color: #ff6b6b;">
                                <span>Пароль:</span> blood666user
                              </div>';
                    }
                    ?>
                </div>
                <?php
            }
            
            // Дополнительная информация из других таблиц
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM messages");
            $messageCount = $stmt->fetch()['count'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM tags");
            $tagCount = $stmt->fetch()['count'];
            
            ?>
            
            <div class="user-card" style="margin-top: 40px;">
                <h3>Статистика базы данных</h3>
                <div class="user-info">
                    <span>Всего пользователей:</span> <?= count($users) ?>
                </div>
                <div class="user-info">
                    <span>Всего сообщений:</span> <?= $messageCount ?>
                </div>
                <div class="user-info">
                    <span>Всего тегов:</span> <?= $tagCount ?>
                </div>
            </div>
            
        } catch (PDOException $e) {
            echo '<div class="password-note">Ошибка: ' . $e->getMessage() . '</div>';
        }
        ?>
    </div>
</body>
</html> 