<?php
require_once 'includes/init.php';
requireLogin();

// получаем все ссылки
$stmt = $pdo->prepare("
    SELECT l.*, m.user_id, u.username, m.created_at 
    FROM links l 
    JOIN messages m ON l.message_id = m.id 
    JOIN users u ON m.user_id = u.id 
    WHERE m.is_deleted = 0
    ORDER BY l.created_at DESC 
    LIMIT 50
");
$stmt->execute();
$links = $stmt->fetchAll();

// добавление новой ссылки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url']) && isset($_POST['description'])) {
    if (!isset($_POST['csrf_token']) || !verifyToken($_POST['csrf_token'])) {
        $error = 'Недействительный токен безопасности. Пожалуйста, попробуйте снова.';
    } else {
        $url = trim($_POST['url']);
        $description = trim($_POST['description']);
        $title = trim($_POST['title'] ?? '');
        $selectedTags = $_POST['tags'] ?? [];
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error = 'Пожалуйста, введите корректный URL';
        } else {
            $isSafe = isUrlSafe($url);
            
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    INSERT INTO messages (user_id, content, message_type) 
                    VALUES (?, ?, 'link')
                ");
                $stmt->execute([$_SESSION['user_id'], $description]);
                $messageId = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("
                    INSERT INTO links (message_id, url, title, description, is_safe) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$messageId, $url, $title, $description, $isSafe]);
                
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
                
                logSystemAction('LINK_ADDED', "User added link: $url");
                $success = 'Ссылка успешно добавлена';
                
                $stmt = $pdo->prepare("
                    SELECT l.*, m.user_id, u.username, m.created_at 
                    FROM links l 
                    JOIN messages m ON l.message_id = m.id 
                    JOIN users u ON m.user_id = u.id 
                    WHERE m.is_deleted = 0
                    ORDER BY l.created_at DESC 
                    LIMIT 50
                ");
                $stmt->execute();
                $links = $stmt->fetchAll();
                
                $_POST = [];
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log('Link add error: ' . $e->getMessage());
                $error = 'Ошибка добавления ссылки';
            }
        }
    }
}

// получаем теги
$stmt = $pdo->query("SELECT * FROM tags ORDER BY name");
$tags = $stmt->fetchAll();

// разрешенные домены
$allowedDomains = URL_WHITELIST;

$pageTitle = 'Управление ссылками';
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

        .page-header {
            margin-bottom: 30px;
            border-bottom: 1px solid #333333;
            padding-bottom: 20px;
        }
        
        .title {
            color: #ff0000;
            font-size: 2rem;
            font-weight: bold;
            margin: 0 0 10px 0;
            text-shadow: 
                0 0 20px rgba(0, 0, 0, 0.9),
                0 0 40px rgba(139, 0, 0, 0.5),
                0 0 60px rgba(139, 0, 0, 0.3);
            letter-spacing: 0.08em;
            font-family: "Courier New", monospace;
            filter: contrast(1.2) brightness(0.9);
        }

        .link-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .link-card {
            position: relative;
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            border-radius: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            padding: 1rem;
            display: flex;
            flex-direction: column;
        }

        .link-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, 
                rgba(255, 0, 0, 0.1) 0%, 
                transparent 50%, 
                rgba(255, 0, 0, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
            z-index: 1;
        }

        .link-card:hover::before {
            opacity: 1;
        }

        .link-card:hover {
            border-color: #ff0000;
            box-shadow: 
                0 0 20px rgba(255, 0, 0, 0.3),
                0 0 40px rgba(255, 0, 0, 0.1),
                inset 0 0 20px rgba(255, 0, 0, 0.1);
            transform: translateY(-5px) scale(1.02);
        }

        .link-title {
            font-weight: bold;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
            color: #cccccc;
        }

        .link-url {
            margin-bottom: 0.5rem;
            word-break: break-all;
            color: #ff0000;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
        }

        .link-description {
            margin-bottom: 0.5rem;
            flex-grow: 1;
            color: #cccccc;
        }

        .link-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #666666;
            margin-top: 0.5rem;
        }

        .link-tabs {
            margin-bottom: 1rem;
        }

        .safety-badge {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            padding: 0.2rem 0.5rem;
            border-radius: 0;
            font-size: 0.75rem;
        }

        .safety-badge.safe {
            background: rgba(0, 255, 0, 0.2);
            border: 1px solid #00ff00;
            color: #00ff00;
            text-shadow: 0 0 5px rgba(0, 255, 0, 0.3);
        }

        .safety-badge.unsafe {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid #ff0000;
            color: #ff0000;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
        }

        .allowed-domains {
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            padding: 1rem;
            border-radius: 0;
            margin-top: 1rem;
        }

        .domain-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .domain-item {
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            padding: 0.3rem 0.6rem;
            border-radius: 0;
            font-size: 0.8rem;
            color: #cccccc;
        }

        .link-search {
            margin-bottom: 1rem;
        }

        .link-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
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

        .btn-danger {
            background: rgba(255, 0, 0, 0.3);
            border-color: #ff0000;
        }

        .btn-danger:hover {
            background: rgba(255, 0, 0, 0.5);
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #333333;
            background: rgba(26, 26, 26, 0.8);
        }

        .alert-success {
            border-color: #00ff00;
            color: #00ff00;
            text-shadow: 0 0 5px rgba(0, 255, 0, 0.3);
        }

        .alert-error {
            border-color: #ff0000;
            color: #ff0000;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
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

        .form-group input, .form-group textarea {
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            color: #cccccc;
            padding: 0.5rem;
            font-family: "Courier New", monospace;
            width: 100%;
        }

        .form-group input:focus, .form-group textarea:focus {
            border-color: #ff0000;
            outline: none;
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.3);
        }
";

$additionalScripts = "
document.addEventListener(\"DOMContentLoaded\", function() {
    // табы
    const tabLinks = document.querySelectorAll(\".tab-link\");
    const tabContents = document.querySelectorAll(\".tab-content\");
    
    tabLinks.forEach(link => {
        link.addEventListener(\"click\", function() {
            tabLinks.forEach(l => l.classList.remove(\"active\"));
            tabContents.forEach(c => c.classList.remove(\"active\"));
            
            this.classList.add(\"active\");
            const tabId = this.getAttribute(\"data-tab\");
            document.getElementById(tabId).classList.add(\"active\");
        });
    });
    
    // получение заголовка
    const urlInput = document.getElementById("url");
    const titleInput = document.getElementById("title");
    const fetchTitleBtn = document.getElementById("fetch-title");
    
    if (fetchTitleBtn) {
        fetchTitleBtn.addEventListener("click", function() {
            const url = urlInput.value.trim();
            
            if (!url) {
                alert("Пожалуйста, введите URL");
                return;
            }
            
            this.innerHTML = \"<span class="loading"></span> Получение...\";
            this.disabled = true;
            
            fetch("api/fetch_title.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ url: url }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.title) {
                    titleInput.value = data.title;
                } else {
                    alert("Не удалось получить заголовок: " + (data.message || "Неизвестная ошибка"));
                }
            })
            .catch(error => {
                console.error("Ошибка:", error);
                alert("Произошла ошибка при получении заголовка");
            })
            .finally(() => {
                this.innerHTML = "Получить заголовок";
                this.disabled = false;
            });
        });
    }
    
    // поиск ссылок
    const searchInput = document.getElementById("link-search");
    const linkCards = document.querySelectorAll(".link-card");
    
    if (searchInput) {
        searchInput.addEventListener("input", function() {
            const searchTerm = this.value.toLowerCase();
            
            linkCards.forEach(card => {
                const title = card.querySelector(".link-title").textContent.toLowerCase();
                const url = card.querySelector(".link-url").textContent.toLowerCase();
                const description = card.querySelector(".link-description").textContent.toLowerCase();
                
                if (title.includes(searchTerm) || url.includes(searchTerm) || description.includes(searchTerm)) {
                    card.style.display = "flex";
                } else {
                    card.style.display = "none";
                }
            });
        });
    }
    
    // кровавый фон
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
    
    // эффекты для карточек
    const linkCards = document.querySelectorAll(".link-card");
    linkCards.forEach(card => {
        card.addEventListener("mouseenter", function() {
            this.style.transform = "translateY(-5px) scale(1.02)";
            this.style.boxShadow = "0 0 30px rgba(255, 0, 0, 0.4)";
        });
        
        card.addEventListener("mouseleave", function() {
            this.style.transform = "translateY(0) scale(1)";
            this.style.boxShadow = "0 0 20px rgba(255, 0, 0, 0.3)";
        });
    });
    
    // эффекты для кнопок
    const buttons = document.querySelectorAll(".btn");
    buttons.forEach(btn => {
        btn.addEventListener("mouseenter", function() {
            this.style.textShadow = "0 0 15px rgba(255, 0, 0, 0.8)";
        });
        
        btn.addEventListener("mouseleave", function() {
            this.style.textShadow = "0 0 5px rgba(255, 0, 0, 0.3)";
        });
    });
});
";

// Маркер для подключения общих файлов
define("ACCESS_ALLOWED", true);

// Подключаем шапку
include "includes/header.php";
?>

<div class="blood-bg"></div>

<div class="link-tabs tab-container">
    <div class="tab-header">
        <div class="tab-link active" data-tab="tab-add">Добавить ссылку</div>
        <div class="tab-link" data-tab="tab-list">Все ссылки</div>
        <div class="tab-link" data-tab="tab-info">Информация</div>
    </div>
</div>

<div id="tab-add" class="tab-content active">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Добавление новой ссылки</h2>
        </div>
        
        <form method="POST" action="links.php">
            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
            
            <div class="form-group">
                <label for="url">URL</label>
                <input type="url" id="url" name="url" required value="<?= htmlspecialchars($_POST["url"] ?? "") ?>">
                <small class="text-muted">Введите полный URL, включая http:// или https://</small>
            </div>
            
            <div class="form-group">
                <label for="title">Заголовок</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($_POST["title"] ?? "") ?>">
                <button type="button" id="fetch-title" class="btn btn-sm">Получить заголовок</button>
                <small class="text-muted">Если не указан, будет использоваться URL</small>
            </div>
            
            <div class="form-group">
                <label for="description">Описание</label>
                <textarea id="description" name="description" required><?= htmlspecialchars($_POST["description"] ?? "") ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Теги</label>
                <div class="tags-selector">
                    <?php foreach ($tags as $tag): ?>
                        <label class="tag-checkbox" style="color: <?= htmlspecialchars($tag['color']) ?>">
                            <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" <?= in_array($tag['id'], $_POST['tags'] ?? []) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($tag['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <button type="submit" class="btn">Добавить ссылку</button>
        </form>
    </div>
</div>

<div id="tab-list" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Все ссылки</h2>
        </div>
        
        <div class="link-search">
            <input type="text" id="link-search" placeholder="Поиск по ссылкам...">
        </div>
        
        <?php if (empty($links)): ?>
            <p>Пока нет добавленных ссылок.</p>
        <?php else: ?>
            <div class="link-grid">
                <?php foreach ($links as $link): ?>
                    <div class="link-card">
                        <div class="safety-badge <?= $link['is_safe'] ? 'safe' : 'unsafe' ?>">
                            <?= $link['is_safe'] ? 'Безопасно' : 'Внимание' ?>
                        </div>
                        
                        <div class="link-title">
                            <?= htmlspecialchars($link['title'] ?: parse_url($link['url'], PHP_URL_HOST)) ?>
                        </div>
                        
                        <div class="link-url">
                            <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer">
                                <?= htmlspecialchars(substr($link['url'], 0, 50) . (strlen($link['url']) > 50 ? '...' : '')) ?>
                            </a>
                        </div>
                        
                        <div class="link-description">
                            <?= htmlspecialchars(substr($link['description'], 0, 150) . (strlen($link['description']) > 150 ? '...' : '')) ?>
                        </div>
                        
                        <div class="link-meta">
                            <div>
                                <?= timeAgo($link['created_at']) ?> • <?= htmlspecialchars($link['username']) ?>
                            </div>
                        </div>
                        
                        <div class="link-actions">
                            <a href="message.php?id=<?= $link['message_id'] ?>" class="btn btn-sm">Подробнее</a>
                            <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm">Перейти</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="tab-info" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Информация о ссылках</h2>
        </div>
        
        <p>При добавлении ссылок система проверяет их безопасность по белому списку доменов.</p>
        
        <div class="allowed-domains">
            <h3>Разрешенные домены:</h3>
            <div class="domain-list">
                <?php foreach ($allowedDomains as $domain): ?>
                    <div class="domain-item"><?= htmlspecialchars($domain) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <p>Если вы хотите добавить домен в белый список, обратитесь к администратору.</p>
        
        <h3>Правила добавления ссылок:</h3>
        <ul>
            <li>Указывайте полный URL, включая http:// или https://</li>
            <li>Добавляйте краткое, но информативное описание</li>
            <li>Используйте теги для категоризации ссылок</li>
            <li>Проверяйте ссылки перед добавлением</li>
            <li>Не добавляйте вредоносные или фишинговые ссылки</li>
        </ul>
    </div>
</div>

<?php
// создаем API для заголовков
if (!file_exists('api')) {
    mkdir('api', 0755, true);
}

$fetchTitleApiFile = 'api/fetch_title.php';
if (!file_exists($fetchTitleApiFile)) {
    $fetchTitleApiContent = <<<'PHP'
<?php
// API для получения заголовка страницы
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$url = $data['url'] ?? '';

if (empty($url)) {
    echo json_encode(['success' => false, 'message' => 'URL не указан']);
    exit;
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Некорректный URL']);
    exit;
}

$context = stream_context_create([
    'http' => [
        'timeout' => 5
    ]
]);

try {
    $html = @file_get_contents($url, false, $context);
    
    if ($html === false) {
        echo json_encode(['success' => false, 'message' => 'Не удалось загрузить страницу']);
        exit;
    }
    
    if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
        $title = trim($matches[1]);
        echo json_encode(['success' => true, 'title' => $title]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Заголовок не найден']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}
PHP;
    file_put_contents($fetchTitleApiFile, $fetchTitleApiContent);
}

// Подключаем подвал
include "includes/footer.php";
?> 