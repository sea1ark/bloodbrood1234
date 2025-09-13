<?php
require_once 'includes/init.php';
requireLogin();

// добавление нового тега
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_tag') {
    $tagName = sanitizeInput($_POST['tag_name']);
    $tagColor = $_POST['tag_color'] ?? '#660000';
    
    if (empty($tagName)) {
        $error = 'Название тега не может быть пустым';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
            $stmt->execute([$tagName]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'Тег с таким названием уже существует';
            } else {
                $stmt = $pdo->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
                $stmt->execute([$tagName, $tagColor]);
                
                logSystemAction('TAG_CREATED', "Created tag: $tagName");
                
                $success = 'Тег успешно добавлен';
            }
        } catch (PDOException $e) {
            error_log('Tag creation error: ' . $e->getMessage());
            $error = 'Ошибка при создании тега';
        }
    }
}

// Обработка удаления тега
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $tagId = (int)$_GET['delete'];
    
    try {
        // Получаем имя тега для логирования
        $stmt = $pdo->prepare("SELECT name FROM tags WHERE id = ?");
        $stmt->execute([$tagId]);
        $tagName = $stmt->fetchColumn();
        
        // Удаление тега
        $stmt = $pdo->prepare("DELETE FROM tags WHERE id = ?");
        $stmt->execute([$tagId]);
        
        if ($stmt->rowCount() > 0) {
            logSystemAction('TAG_DELETED', "Deleted tag: $tagName");
            $success = 'Тег успешно удален';
        } else {
            $error = 'Тег не найден';
        }
    } catch (PDOException $e) {
        error_log('Tag deletion error: ' . $e->getMessage());
        $error = 'Ошибка при удалении тега';
    }
}

// Обработка редактирования тега
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_tag') {
    $tagId = (int)$_POST['tag_id'];
    $tagName = sanitizeInput($_POST['tag_name']);
    $tagColor = $_POST['tag_color'] ?? '#660000';
    
    if (empty($tagName)) {
        $error = 'Название тега не может быть пустым';
    } else {
        try {
            // Проверка на существование тега с таким именем (кроме текущего)
            $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ? AND id != ?");
            $stmt->execute([$tagName, $tagId]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'Тег с таким названием уже существует';
            } else {
                // Обновление тега
                $stmt = $pdo->prepare("UPDATE tags SET name = ?, color = ? WHERE id = ?");
                $stmt->execute([$tagName, $tagColor, $tagId]);
                
                logSystemAction('TAG_UPDATED', "Updated tag: $tagName");
                
                $success = 'Тег успешно обновлен';
            }
        } catch (PDOException $e) {
            error_log('Tag update error: ' . $e->getMessage());
            $error = 'Ошибка при обновлении тега';
        }
    }
}

// Получение всех тегов
$stmt = $pdo->query("SELECT * FROM tags ORDER BY name");
$tags = $stmt->fetchAll();

// Получение статистики использования тегов
$tagStats = [];
foreach ($tags as $tag) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM message_tags WHERE tag_id = ?");
    $stmt->execute([$tag['id']]);
    $tagStats[$tag['id']] = $stmt->fetchColumn();
}

// Установка заголовка страницы
$pageTitle = 'Управление тегами';
$additionalStyles = "
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
            font-family: \"Courier New\", monospace;
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
            font-family: \"Courier New\", monospace;
            filter: contrast(1.2) brightness(0.9);
        }

        .tag-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .tag-item {
            position: relative;
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            border-radius: 0;
            padding: 1.2rem;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .tag-item::before {
            content: \"\";
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
        }

        .tag-item:hover::before {
            opacity: 1;
        }

        .tag-item:hover {
            border-color: #ff0000;
            box-shadow: 
                0 0 20px rgba(255, 0, 0, 0.3),
                0 0 40px rgba(255, 0, 0, 0.1),
                inset 0 0 20px rgba(255, 0, 0, 0.1);
            transform: translateY(-5px) scale(1.02);
        }

        .tag-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            color: #cccccc;
        }

        .tag-color {
            display: inline-block;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin-right: 0.5rem;
            border: 1px solid #333333;
        }

        .tag-stats {
            margin-top: 0.5rem;
            color: #666666;
            font-size: 0.9rem;
        }

        .tag-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
        }

        .tag-add-form {
            margin-bottom: 2rem;
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group input {
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            color: #cccccc;
            padding: 0.5rem;
            font-family: \"Courier New\", monospace;
            width: 100%;
        }

        .form-group input:focus {
            border-color: #ff0000;
            outline: none;
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.3);
        }

        .btn {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid #ff0000;
            color: #ff0000;
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: \"Courier New\", monospace;
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
";

$additionalScripts = "
function openEditTagModal(id, name, color) {
    document.getElementById(\"edit_tag_id\").value = id;
    document.getElementById(\"edit_tag_name\").value = name;
    document.getElementById(\"edit_tag_color\").value = color;
    document.getElementById(\"editTagModal\").classList.add(\"active\");
}

function closeEditTagModal() {
    document.getElementById(\"editTagModal\").classList.remove(\"active\");
}

// Кровавый фон с эффектами
const bloodBg = document.querySelector(\".blood-bg\");
let mouseX = 0, mouseY = 0;

document.addEventListener(\"mousemove\", (e) => {
    mouseX = e.clientX;
    mouseY = e.clientY;
    
    const x = (mouseX / window.innerWidth) * 100;
    const y = (mouseY / window.innerHeight) * 100;
    bloodBg.style.setProperty(\"--mouse-x\", x + \"%\");
    bloodBg.style.setProperty(\"--mouse-y\", y + \"%\");
});

// Эффекты при наведении на элементы
const tagItems = document.querySelectorAll(\".tag-item\");
tagItems.forEach(item => {
    item.addEventListener(\"mouseenter\", function() {
        this.style.transform = \"translateY(-5px) scale(1.02)\";
        this.style.boxShadow = \"0 0 30px rgba(255, 0, 0, 0.4)\";
    });
    
    item.addEventListener(\"mouseleave\", function() {
        this.style.transform = \"translateY(0) scale(1)\";
        this.style.boxShadow = \"0 0 20px rgba(255, 0, 0, 0.3)\";
    });
});

// Эффекты для кнопок
const buttons = document.querySelectorAll(\".btn\");
buttons.forEach(btn => {
    btn.addEventListener(\"mouseenter\", function() {
        this.style.textShadow = \"0 0 15px rgba(255, 0, 0, 0.8)\";
    });
    
    btn.addEventListener(\"mouseleave\", function() {
        this.style.textShadow = \"0 0 5px rgba(255, 0, 0, 0.3)\";
    });
});
";

// Маркер для подключения общих файлов
define("ACCESS_ALLOWED", true);

// Подключаем шапку
include "includes/header.php";
?>

<div class="blood-bg"></div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Добавление нового тега</h2>
    </div>
    
    <form method="POST" action="tags.php" class="tag-add-form">
        <input type="hidden" name="action" value="add_tag">
        <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
        
        <div class="form-group-inline">
            <div class="form-group">
                <label for="tag_name">Название тега:</label>
                <input type="text" id="tag_name" name="tag_name" required>
            </div>
            
            <div class="form-group">
                <label for="tag_color">Цвет тега:</label>
                <input type="color" id="tag_color" name="tag_color" value="#660000">
            </div>
            
            <button type="submit" class="btn">
                <svg width="16" height="16"><use xlink:href="#icon-tag"></use></svg>
                Добавить тег
            </button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Управление тегами</h2>
    </div>
    
    <?php if (empty($tags)): ?>
        <div class="text-center p-4">
            <p>Теги еще не созданы.</p>
        </div>
    <?php else: ?>
        <div class="tag-grid">
            <?php foreach ($tags as $tag): ?>
                <div class="tag-item hover-glow">
                    <div class="tag-name">
                        <span class="tag-color" style="background-color: <?= htmlspecialchars($tag["color"]) ?>"></span>
                        <svg width="16" height="16"><use xlink:href="#icon-tag"></use></svg>
                        <?= htmlspecialchars($tag["name"]) ?>
                    </div>
                    
                    <div class="tag-stats">
                        Использований: <?= $tagStats[$tag["id"]] ?>
                    </div>
                    
                    <div class="tag-actions">
                        <button type="button" class="btn btn-sm" 
                                onclick=\"openEditTagModal('<?= htmlspecialchars($tag['id']) ?>', '<?= htmlspecialchars($tag['name']) ?>', '<?= htmlspecialchars($tag['color']) ?>')\">
                            Редактировать
                        </button>
                        
                        <a href=\"tags.php?delete=<?= $tag['id'] ?>\" class=\"btn btn-sm btn-danger\" 
                           onclick=\"return confirm('Вы уверены, что хотите удалить этот тег?')\">
                            Удалить
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Модальное окно для редактирования тега -->
<div class="modal-overlay" id="editTagModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Редактирование тега</h3>
            <button type="button" class="modal-close" onclick="closeEditTagModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="tags.php" id="editTagForm">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                <input type="hidden" name="action" value="edit_tag">
                <input type="hidden" name="tag_id" id="edit_tag_id">
                <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
                
                <div class="form-group">
                    <label for="edit_tag_name">Название тега:</label>
                    <input type="text" id="edit_tag_name" name="tag_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_tag_color">Цвет тега:</label>
                    <input type="color" id="edit_tag_color" name="tag_color">
                </div>
                
                <button type="submit" class="btn">
                    <svg width="16" height="16"><use xlink:href="#icon-tag"></use></svg>
                    Сохранить изменения
                </button>
            </form>
        </div>
    </div>
</div>

<?php
// Подключаем подвал
include "includes/footer.php";
?> 