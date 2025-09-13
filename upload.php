<?php
require_once 'includes/init.php';
requireLogin();

// получение информации о файлах
$stmt = $pdo->prepare("
    SELECT a.*, m.user_id, u.username 
    FROM attachments a 
    JOIN messages m ON a.message_id = m.id 
    JOIN users u ON m.user_id = u.id 
    WHERE m.is_deleted = 0
    ORDER BY a.created_at DESC 
    LIMIT 20
");
$stmt->execute();
$recentFiles = $stmt->fetchAll();

// проверка прав на загрузку
$canUpload = true;

// загрузка файла
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && isset($_POST['description'])) {
    if (!isset($_POST['csrf_token']) || !verifyToken($_POST['csrf_token'])) {
        $error = 'Недействительный токен безопасности. Пожалуйста, попробуйте снова.';
    } else {
        $file = $_FILES['file'];
        $description = trim($_POST['description']);
        $isEncrypted = isset($_POST['encrypt']) && $_POST['encrypt'] === '1';
        $selectedTags = $_POST['tags'] ?? [];
        
        // проверка ошибок загрузки
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error = 'Файл превышает максимально допустимый размер';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error = 'Файл был загружен частично';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error = 'Файл не был загружен';
                    break;
                default:
                    $error = 'При загрузке файла произошла ошибка';
            }
        } else {
            // Проверка расширения файла
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ALLOWED_EXTENSIONS)) {
                $error = 'Недопустимый тип файла. Разрешены только: ' . implode(', ', ALLOWED_EXTENSIONS);
            } else {
                // Проверка размера файла
                if ($file['size'] > MAX_FILE_SIZE) {
                    $error = 'Размер файла превышает максимально допустимый (' . formatFileSize(MAX_FILE_SIZE) . ')';
                } else {
                    try {
                        $pdo->beginTransaction();
                        
                        // Создание сообщения
                        $stmt = $pdo->prepare("
                            INSERT INTO messages (user_id, content, message_type, is_encrypted) 
                            VALUES (?, ?, 'file', ?)
                        ");
                        $stmt->execute([$_SESSION['user_id'], $description, $isEncrypted]);
                        $messageId = $pdo->lastInsertId();
                        
                        // Создание директории для загрузки файлов, если она не существует
                        if (!file_exists(UPLOAD_DIR)) {
                            mkdir(UPLOAD_DIR, 0755, true);
                        }
                        
                        // Генерация уникального имени файла
                        $fileName = 'file_' . time() . '_' . uniqid() . '.' . $extension;
                        $filePath = UPLOAD_DIR . $fileName;
                        
                        // Вычисление хеша файла
                        $fileHash = hash_file('sha256', $file['tmp_name']);
                        
                        // Шифрование файла, если необходимо
                        if ($isEncrypted) {
                            // Чтение содержимого файла
                            $fileContent = file_get_contents($file['tmp_name']);
                            
                            // Шифрование содержимого
                            $encryptedContent = encrypt($fileContent, ENCRYPTION_KEY);
                            
                            // Сохранение зашифрованного содержимого
                            file_put_contents($filePath, $encryptedContent);
                        } else {
                            // Перемещение файла в папку для загрузки
                            move_uploaded_file($file['tmp_name'], $filePath);
                        }
                        
                        // Сохранение информации о файле в базе данных
                        $stmt = $pdo->prepare("
                            INSERT INTO attachments (message_id, filename, original_filename, file_size, file_type, file_hash, is_encrypted) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $messageId,
                            $fileName,
                            $file['name'],
                            $file['size'],
                            $file['type'],
                            $fileHash,
                            $isEncrypted
                        ]);
                        
                        // Добавление тегов
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
                        
                        logSystemAction('FILE_UPLOADED', "User uploaded file: {$file['name']} ({$fileName})");
                        $success = 'Файл успешно загружен';
                        
                        // Обновление списка последних файлов
                        $stmt = $pdo->prepare("
                            SELECT a.*, m.user_id, u.username 
                            FROM attachments a 
                            JOIN messages m ON a.message_id = m.id 
                            JOIN users u ON m.user_id = u.id 
                            WHERE m.is_deleted = 0
                            ORDER BY a.created_at DESC 
                            LIMIT 20
                        ");
                        $stmt->execute();
                        $recentFiles = $stmt->fetchAll();
                        
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        error_log('File upload error: ' . $e->getMessage());
                        $error = 'Ошибка при загрузке файла: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Получение всех тегов
$stmt = $pdo->query("SELECT * FROM tags ORDER BY name");
$tags = $stmt->fetchAll();

// Установка заголовка страницы
$pageTitle = 'Загрузка файлов';
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

        .file-preview {
            max-width: 100%;
            max-height: 300px;
            margin-top: 1rem;
            display: none;
            border-radius: 0;
            box-shadow: 0 0 10px rgba(255, 0, 0, 0.3);
            border: 1px solid #333333;
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            border-radius: 0;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .file-item:hover {
            border-color: #ff0000;
            box-shadow: 0 0 20px rgba(255, 0, 0, 0.3);
            transform: translateY(-3px);
        }

        .file-icon {
            font-size: 2rem;
            color: #ff0000;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: bold;
            margin-bottom: 0.25rem;
            color: #cccccc;
        }

        .file-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: #666666;
        }

        .file-actions {
            display: flex;
            gap: 0.5rem;
        }

        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .file-card {
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid #333333;
            border-radius: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }

        .file-card::before {
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
            z-index: 1;
        }

        .file-card:hover::before {
            opacity: 1;
        }

        .file-card:hover {
            border-color: #ff0000;
            box-shadow: 
                0 0 20px rgba(255, 0, 0, 0.3),
                0 0 40px rgba(255, 0, 0, 0.1),
                inset 0 0 20px rgba(255, 0, 0, 0.1);
            transform: translateY(-5px) scale(1.02);
        }

        .file-preview-container {
            height: 150px;
            background: rgba(26, 26, 26, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .file-preview-container img {
            max-width: 100%;
            max-height: 100%;
        }

        .file-preview-icon {
            font-size: 3rem;
            color: #ff0000;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
        }

        .file-card-info {
            padding: 1rem;
        }

        .file-card-name {
            font-weight: bold;
            margin-bottom: 0.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #cccccc;
        }

        .file-card-meta {
            font-size: 0.8rem;
            color: #666666;
            margin-bottom: 0.5rem;
        }

        .file-card-user {
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
            color: #cccccc;
        }

        .file-card-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .file-tabs {
            margin-bottom: 1rem;
        }

        .encrypted-file-badge {
            background: rgba(255, 0, 0, 0.2);
            border: 1px solid #ff0000;
            color: #ff0000;
            padding: 0.2rem 0.5rem;
            border-radius: 0;
            font-size: 0.75rem;
            margin-left: 0.5rem;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.3);
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
            font-family: \"Courier New\", monospace;
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
    const fileInput = document.getElementById(\"file\");
    const filePreview = document.getElementById(\"file-preview\");
    const fileNameDisplay = document.getElementById(\"file-name\");
    const fileSizeDisplay = document.getElementById(\"file-size\");
    const fileTypeDisplay = document.getElementById(\"file-type\");
    
    if (fileInput) {
        fileInput.addEventListener(\"change\", function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                
                // Отображаем имя и размер файла
                fileNameDisplay.textContent = file.name;
                fileSizeDisplay.textContent = formatFileSize(file.size);
                fileTypeDisplay.textContent = file.type;
                
                // Проверяем, является ли файл изображением
                if (file.type.startsWith(\"image/\")) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        filePreview.src = e.target.result;
                        filePreview.style.display = \"block\";
                    };
                    reader.readAsDataURL(file);
                } else {
                    filePreview.style.display = \"none\";
                }
            }
        });
    }
    
    // Функция для форматирования размера файла
    function formatFileSize(bytes) {
        if (bytes === 0) return \"0 Bytes\";
        const k = 1024;
        const sizes = [\"Bytes\", \"KB\", \"MB\", \"GB\"];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + \" \" + sizes[i];
    }
    
    // Табы
    const tabLinks = document.querySelectorAll(\".tab-link\");
    const tabContents = document.querySelectorAll(\".tab-content\");
    
    tabLinks.forEach(link => {
        link.addEventListener(\"click\", function() {
            // Удаляем активный класс у всех табов
            tabLinks.forEach(l => l.classList.remove(\"active\"));
            tabContents.forEach(c => c.classList.remove(\"active\"));
            
            // Добавляем активный класс выбранному табу
            this.classList.add(\"active\");
            const tabId = this.getAttribute(\"data-tab\");
            document.getElementById(tabId).classList.add(\"active\");
        });
    });
    
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
    const fileCards = document.querySelectorAll(\".file-card\");
    fileCards.forEach(card => {
        card.addEventListener(\"mouseenter\", function() {
            this.style.transform = \"translateY(-5px) scale(1.02)\";
            this.style.boxShadow = \"0 0 30px rgba(255, 0, 0, 0.4)\";
        });
        
        card.addEventListener(\"mouseleave\", function() {
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
});
";

// Маркер для подключения общих файлов
define("ACCESS_ALLOWED", true);

// Подключаем шапку
include "includes/header.php";
?>

<div class="blood-bg"></div>

<div class="file-tabs tab-container">
    <div class="tab-header">
        <div class="tab-link active" data-tab="tab-upload">Загрузить файл</div>
        <div class="tab-link" data-tab="tab-recent">Последние файлы</div>
    </div>
</div>

<div id="tab-upload" class="tab-content active">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Загрузка файла</h2>
        </div>
        
        <form method="POST" action="upload.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generateToken() ?>">
            
            <div class="form-group">
                <label for="file">Выберите файл</label>
                <input type="file" id="file" name="file" required>
                <small class="text-muted">
                    Максимальный размер: <?= formatFileSize(MAX_FILE_SIZE) ?>. 
                    Разрешенные типы файлов: <?= implode(', ', array_slice(ALLOWED_EXTENSIONS, 0, 10)) ?> и другие.
                </small>
            </div>
            
            <div class="file-info" style="display: <?= isset($_FILES['file']) ? 'block' : 'none' ?>">
                <div id="file-name"></div>
                <div id="file-size"></div>
                <div id="file-type"></div>
            </div>
            
            <img id="file-preview" class="file-preview" src="" alt="Предпросмотр">
            
            <div class="form-group">
                <label for="description">Описание</label>
                <textarea id="description" name="description" required></textarea>
            </div>
            
            <div class="form-group">
                <label>Теги</label>
                <div class="tags-selector">
                    <?php foreach ($tags as $tag): ?>
                        <label class="tag-checkbox" style="color: <?= htmlspecialchars($tag['color']) ?>">
                            <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>">
                            <?= htmlspecialchars($tag['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label class="encrypt-option">
                    <input type="checkbox" name="encrypt" value="1">
                    Зашифровать файл
                </label>
                <small class="text-muted">
                    Зашифрованные файлы могут быть расшифрованы только зарегистрированными пользователями.
                </small>
            </div>
            
            <button type="submit" class="btn">Загрузить файл</button>
        </form>
    </div>
</div>

<div id="tab-recent" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Последние загруженные файлы</h2>
        </div>
        
        <?php if (empty($recentFiles)): ?>
            <p>Нет загруженных файлов.</p>
        <?php else: ?>
            <div class="file-grid">
                <?php foreach ($recentFiles as $file): ?>
                    <div class="file-card">
                        <div class="file-preview-container">
                            <?php
                            $extension = strtolower(pathinfo($file['original_filename'], PATHINFO_EXTENSION));
                            $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                            
                            if ($isImage && !$file['is_encrypted']):
                            ?>
                                <img src="<?= UPLOAD_DIR . $file['filename'] ?>" alt="Предпросмотр">
                            <?php else: ?>
                                <div class="file-preview-icon"><?= getFileTypeIcon($file['original_filename']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="file-card-info">
                            <div class="file-card-name">
                                <?= htmlspecialchars($file['original_filename']) ?>
                                <?php if ($file['is_encrypted']): ?>
                                    <span class="encrypted-file-badge">Зашифровано</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="file-card-meta">
                                <?= formatFileSize($file['file_size']) ?> • <?= timeAgo($file['created_at']) ?>
                            </div>
                            
                            <div class="file-card-user">
                                Загрузил: <?= htmlspecialchars($file['username']) ?>
                            </div>
                            
                            <div class="file-card-actions">
                                <a href="message.php?id=<?= $file['message_id'] ?>" class="btn btn-sm">Подробнее</a>
                                <a href="download.php?id=<?= $file['id'] ?>" class="btn btn-sm" download>Скачать</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Подключаем подвал
include "includes/footer.php";
?> 