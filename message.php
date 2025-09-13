<?php
require_once 'includes/init.php';
requireLogin();

// проверка ID сообщения
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('forum.php');
}

$messageId = (int)$_GET['id'];

// получение данных сообщения
$stmt = $pdo->prepare("
    SELECT m.*, u.username, u.role,
        (SELECT COUNT(*) FROM messages WHERE parent_id = m.id AND is_deleted = 0) as comments_count
    FROM messages m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.id = ? AND m.is_deleted = 0
");
$stmt->execute([$messageId]);
$message = $stmt->fetch();

if (!$message) {
    redirect('forum.php');
}

// получение вложений
$stmt = $pdo->prepare("SELECT * FROM attachments WHERE message_id = ?");
$stmt->execute([$messageId]);
$attachments = $stmt->fetchAll();

// получение ссылок
$stmt = $pdo->prepare("SELECT * FROM links WHERE message_id = ?");
$stmt->execute([$messageId]);
$links = $stmt->fetchAll();

// получение тегов сообщения
$stmt = $pdo->prepare("
    SELECT t.* FROM tags t 
    JOIN message_tags mt ON t.id = mt.tag_id 
    WHERE mt.message_id = ?
");
$stmt->execute([$messageId]);
$messageTags = $stmt->fetchAll();

// Получение комментариев
$stmt = $pdo->prepare("
    SELECT m.*, u.username, u.role 
    FROM messages m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.parent_id = ? AND m.is_deleted = 0 
    ORDER BY m.created_at ASC
");
$stmt->execute([$messageId]);
$comments = $stmt->fetchAll();

// Обработка добавления комментария
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $content = trim($_POST['comment']);
    $isEncrypted = isset($_POST['encrypt']) && $_POST['encrypt'] === '1';
    
    if (!empty($content)) {
        try {
            $pdo->beginTransaction();
            
            if ($isEncrypted) {
                $content = encrypt($content, ENCRYPTION_KEY);
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO messages (user_id, content, is_encrypted, parent_id) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $content, $isEncrypted, $messageId]);
            
            $pdo->commit();
            logSystemAction('COMMENT_ADDED', "Added comment to message #$messageId");
            
            redirect("message.php?id=$messageId");
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Comment error: ' . $e->getMessage());
            $error = 'Ошибка добавления комментария';
        }
    } else {
        $error = 'Комментарий не может быть пустым';
    }
}

// Установка заголовка страницы
$pageTitle = 'Просмотр сообщения';
$additionalStyles = '
        :root {
            --blood-primary: #8b0000;
            --blood-secondary: #dc143c;
            --blood-dark: #4b0000;
            --blood-light: #ff6b6b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: radial-gradient(ellipse at center, #0a0a0a 0%, #000000 100%);
            color: #cccccc;
            font-family: "Courier New", monospace;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
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

        .message-main {
            margin-bottom: 2rem;
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .message-header {
            border-bottom: 1px solid #333333;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }

        .message-title {
            color: #ff0000;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 0 0.5rem 0;
            text-shadow: 
                0 0 20px rgba(0, 0, 0, 0.9),
                0 0 40px rgba(139, 0, 0, 0.5),
                0 0 60px rgba(139, 0, 0, 0.3);
            letter-spacing: 0.08em;
            font-family: "Courier New", monospace;
            filter: contrast(1.2) brightness(0.9);
        }

        .message-meta {
            color: #666666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .message-content {
            color: #cccccc;
            line-height: 1.6;
            margin: 1rem 0;
            font-family: "Courier New", monospace;
        }

        .message-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #333333;
        }

        .message-views {
            color: #666666;
            font-size: 0.9rem;
        }

        .message-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .message-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .tag {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid #ff0000;
            color: #ff0000;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.3s ease;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
        }

        .tag:hover {
            background: rgba(255, 0, 0, 0.3);
            box-shadow: 0 0 15px rgba(255, 0, 0, 0.5);
            transform: translateY(-2px);
        }

        .file-attachments {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #333333;
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            padding: 1rem;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .attachment-item:hover {
            border-color: #ff0000;
            box-shadow: 0 0 20px rgba(255, 0, 0, 0.3);
            transform: translateY(-2px);
        }

        .attachment-icon {
            font-size: 1.5rem;
            color: #ff0000;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
        }

        .attachment-info {
            flex: 1;
        }

        .attachment-name {
            font-weight: bold;
            margin-bottom: 0.25rem;
            color: #cccccc;
        }

        .attachment-meta {
            font-size: 0.8rem;
            color: #666666;
        }

        .link-items {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #333333;
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            padding: 1rem;
        }

        .link-item {
            padding: 1rem;
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .link-item:hover {
            border-color: #ff0000;
            box-shadow: 0 0 20px rgba(255, 0, 0, 0.3);
            transform: translateY(-2px);
        }

        .link-title {
            font-weight: bold;
            margin-bottom: 0.25rem;
            color: #cccccc;
        }

        .link-url {
            word-break: break-all;
            margin-bottom: 0.5rem;
            color: #ff0000;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
        }

        .link-description {
            margin-bottom: 0.5rem;
            color: #cccccc;
        }

        .comments-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #333333;
        }

        .comment-form {
            margin-bottom: 2rem;
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            padding: 1rem;
        }

        .comment-list {
            margin-top: 1.5rem;
        }

        .decrypted-content {
            padding: 1rem;
            background: rgba(255, 0, 0, 0.1);
            border-left: 3px solid #ff0000;
            margin: 1rem 0;
            border: 1px solid #333333;
            color: #cccccc;
        }

        .btn {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid #ff0000;
            color: #ff0000;
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: "Courier New", monospace;
            text-decoration: none;
            display: inline-block;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
        }

        .btn:hover {
            background: rgba(255, 0, 0, 0.3);
            box-shadow: 0 0 15px rgba(255, 0, 0, 0.5);
            transform: translateY(-2px);
            text-shadow: 0 0 15px rgba(255, 0, 0, 0.8);
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        .card {
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1rem;
            border-bottom: 1px solid #333333;
            background: rgba(255, 0, 0, 0.1);
        }

        .card-title {
            color: #ff0000;
            margin: 0;
            font-size: 1.2rem;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
        }

        .card-body {
            padding: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group textarea {
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            color: #cccccc;
            padding: 0.5rem;
            font-family: "Courier New", monospace;
            width: 100%;
            min-height: 100px;
            resize: vertical;
        }

        .form-group textarea:focus {
            border-color: #ff0000;
            outline: none;
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.3);
        }

        .encrypted-file-badge {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid #ff0000;
            color: #ff0000;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            margin-left: 0.5rem;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
        }
';

// Маркер для подключения общих файлов
define('ACCESS_ALLOWED', true);

// Подключаем шапку
include 'includes/header.php';
?>

<div class="blood-bg"></div>

<div class="message-main card">
    <div class="message-header">
        <div class="message-author">
            <svg width="16" height="16"><use xlink:href="#icon-user"></use></svg>
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
                <svg width="16" height="16"><use xlink:href="#icon-lock"></use></svg>
                [Зашифрованное сообщение]
            </div>
            <button class="btn btn-sm decrypt-button" data-id="<?= $message['id'] ?>">Расшифровать</button>
        <?php else: ?>
            <?= nl2br(htmlspecialchars($message['content'])) ?>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($messageTags)): ?>
        <div class="message-tags">
            <?php foreach ($messageTags as $tag): ?>
                <a href="forum.php?tag=<?= $tag['id'] ?>" class="tag" style="color: <?= htmlspecialchars($tag['color']) ?>">
                    <svg width="12" height="12"><use xlink:href="#icon-tag"></use></svg>
                    <?= htmlspecialchars($tag['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div class="message-actions">
        <div class="message-views">
            Просмотров: <?= isset($message['views_count']) ? $message['views_count'] : 0 ?>
        </div>
        
        <div class="message-buttons">
            <a href="forum.php" class="btn btn-sm">
                <svg width="16" height="16"><use xlink:href="#icon-message"></use></svg>
                Назад к форуму
            </a>
            
            <?php if ($_SESSION['user_id'] === $message['user_id'] || isAdmin()): ?>
                <a href="edit_message.php?id=<?= $message['id'] ?>" class="btn btn-sm">Редактировать</a>
                <a href="delete_message.php?id=<?= $message['id'] ?>" class="btn btn-sm btn-danger" 
                   onclick="return confirm('Вы уверены, что хотите удалить это сообщение?')">Удалить</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($attachments)): ?>
    <div class="card file-attachments">
        <div class="card-header">
            <h3 class="card-title">Прикрепленные файлы</h3>
        </div>
        
        <?php foreach ($attachments as $attachment): ?>
            <div class="attachment-item hover-glow">
                <div class="attachment-icon">
                    <svg width="24" height="24"><use xlink:href="#icon-file"></use></svg>
                </div>
                
                <div class="attachment-info">
                    <div class="attachment-name"><?= htmlspecialchars($attachment['original_filename']) ?></div>
                    <div class="attachment-meta">
                        <?= formatFileSize($attachment['file_size']) ?> • <?= timeAgo($attachment['created_at']) ?>
                        <?php if ($attachment['is_encrypted']): ?>
                            <span class="encrypted-file-badge">
                                <svg width="12" height="12"><use xlink:href="#icon-lock"></use></svg>
                                Зашифровано
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <a href="download.php?id=<?= $attachment['id'] ?>" class="btn btn-sm" download>Скачать</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($links)): ?>
    <div class="card link-items">
        <div class="card-header">
            <h3 class="card-title">Ссылки</h3>
        </div>
        
        <?php foreach ($links as $link): ?>
            <div class="link-item hover-glow">
                <div class="link-title">
                    <svg width="16" height="16"><use xlink:href="#icon-link"></use></svg>
                    <?= htmlspecialchars($link['title'] ?: parse_url($link['url'], PHP_URL_HOST)) ?>
                </div>
                
                <div class="link-url">
                    <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer">
                        <?= htmlspecialchars($link['url']) ?>
                    </a>
                </div>
                
                <div class="link-description">
                    <?= htmlspecialchars($link['description']) ?>
                </div>
                
                <div class="safety-badge <?= $link['is_safe'] ? 'safe' : 'unsafe' ?>">
                    <?= $link['is_safe'] ? 'Безопасно' : 'Внимание' ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="comments-section">
    <div class="card comment-form">
        <div class="card-header">
            <h3 class="card-title">Добавить комментарий</h3>
        </div>
        
        <form method="POST" action="message.php?id=<?= $messageId ?>">
            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
            
            <div class="form-group">
                <label for="comment">Ваш комментарий:</label>
                <textarea id="comment" name="comment" rows="3" required></textarea>
            </div>
            
            <div class="form-footer">
                <label class="encrypt-option">
                    <input type="checkbox" name="encrypt" value="1">
                    <svg width="16" height="16"><use xlink:href="#icon-lock"></use></svg>
                    Зашифровать комментарий
                </label>
                
                <button type="submit" class="btn">Отправить</button>
            </div>
        </form>
    </div>
    
    <?php if (!empty($comments)): ?>
        <h3 class="subtitle">Комментарии (<?= count($comments) ?>)</h3>
        
        <div class="comment-list">
            <?php foreach ($comments as $comment): ?>
                <div class="comment">
                    <div class="comment-header">
                        <div class="message-author">
                            <svg width="16" height="16"><use xlink:href="#icon-user"></use></svg>
                            <?= htmlspecialchars($comment['username']) ?>
                            <?php if ($comment['role'] === 'admin'): ?>
                                <span class="admin-badge">admin</span>
                            <?php endif; ?>
                        </div>
                        <div class="message-time"><?= timeAgo($comment['created_at']) ?></div>
                    </div>
                    
                    <div class="comment-content">
                        <?php if ($comment['is_encrypted']): ?>
                            <div class="encrypted-message" id="encrypted-content-<?= $comment['id'] ?>">
                                <svg width="16" height="16"><use xlink:href="#icon-lock"></use></svg>
                                [Зашифрованный комментарий]
                            </div>
                            <button class="btn btn-sm decrypt-button" data-id="<?= $comment['id'] ?>">Расшифровать</button>
                        <?php else: ?>
                            <?= nl2br(htmlspecialchars($comment['content'])) ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($_SESSION['user_id'] === $comment['user_id'] || isAdmin()): ?>
                        <div class="comment-actions">
                            <a href="delete_message.php?id=<?= $comment['id'] ?>&redirect=<?= $messageId ?>" 
                               class="btn btn-sm btn-danger" onclick="return confirm('Вы уверены, что хотите удалить этот комментарий?')">
                                Удалить
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="text-center p-4">
                <p>Нет комментариев. Будьте первым, кто оставит комментарий.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Подключаем подвал
include 'includes/footer.php';
?>

<script>
    // Функция для расшифровки контента
    document.querySelectorAll('.btn-decrypt').forEach(button => {
        button.addEventListener('click', function() {
            const container = this.closest('.encrypted-content');
            const encryptedContent = container.getAttribute('data-encrypted');
            
            fetch('api/decrypt.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'content=' + encodeURIComponent(encryptedContent)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    container.innerHTML = data.content;
                } else {
                    alert('Не удалось расшифровать сообщение');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    });
    
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
    const attachmentItems = document.querySelectorAll(".attachment-item");
    attachmentItems.forEach(item => {
        item.addEventListener("mouseenter", function() {
            this.style.transform = "translateY(-3px) scale(1.02)";
            this.style.boxShadow = "0 0 25px rgba(255, 0, 0, 0.4)";
        });
        
        item.addEventListener("mouseleave", function() {
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
    
    // Предотвращение случайного закрытия страницы при вводе комментария
    const commentForm = document.querySelector('form');
    const commentInput = document.getElementById('comment');
    
    window.addEventListener('beforeunload', function(e) {
        if (commentInput.value.trim().length > 0 && !commentForm.submitted) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    
    commentForm.addEventListener('submit', function() {
        this.submitted = true;
    });
</script> 