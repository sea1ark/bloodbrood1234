<?php
require_once '../includes/init.php';
header('Content-Type: application/json');

// Проверка авторизации
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Проверка запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$file = $_FILES['file'];
$messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : null;
$isEncrypted = isset($_POST['encrypt']) && $_POST['encrypt'] === '1';

// Проверка ошибок загрузки
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Файл превышает максимальный размер, указанный в php.ini',
        UPLOAD_ERR_FORM_SIZE => 'Файл превышает максимальный размер, указанный в форме',
        UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично',
        UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
        UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
        UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
        UPLOAD_ERR_EXTENSION => 'Загрузка файла была остановлена расширением PHP'
    ];
    
    $errorMessage = $errorMessages[$file['error']] ?? 'Неизвестная ошибка загрузки';
    echo json_encode(['success' => false, 'error' => $errorMessage]);
    exit;
}

// Проверка размера файла
if ($file['size'] > MAX_FILE_SIZE) {
    echo json_encode(['success' => false, 'error' => 'Файл слишком большой']);
    exit;
}

// Проверка расширения файла
$fileInfo = pathinfo($file['name']);
$extension = strtolower($fileInfo['extension']);

if (!in_array($extension, ALLOWED_EXTENSIONS)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимый тип файла']);
    exit;
}

// Создание безопасного имени файла
$safeFilename = sanitizeFilename($file['name']);
$uniqueFilename = uniqid() . '_' . time() . '.' . $extension;
$filepath = UPLOAD_DIR . $uniqueFilename;

// Перемещение файла
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'error' => 'Ошибка сохранения файла']);
    exit;
}

try {
    // Получение хеша файла для проверки дубликатов
    $fileHash = hash_file('sha256', $filepath);
    
    // Шифрование файла при необходимости
    if ($isEncrypted) {
        // Здесь можно добавить код для шифрования файла
        // В простом случае можно оставить метку о необходимости шифрования
    }
    
    // Сохранение информации о файле в базе данных
    $stmt = $pdo->prepare("
        INSERT INTO attachments (message_id, filename, original_filename, file_size, file_type, file_hash, is_encrypted) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $messageId,
        $uniqueFilename,
        $file['name'],
        $file['size'],
        $file['type'],
        $fileHash,
        $isEncrypted ? 1 : 0
    ]);
    
    $attachmentId = $pdo->lastInsertId();
    
    // Логирование
    logSystemAction('FILE_UPLOADED', "File: {$file['name']}, Size: {$file['size']}");
    
    // Возврат успешного результата
    echo json_encode([
        'success' => true,
        'attachment_id' => $attachmentId,
        'filename' => $uniqueFilename,
        'original_name' => $file['name'],
        'size' => $file['size'],
        'formatted_size' => formatFileSize($file['size']),
        'type' => $file['type'],
        'icon' => getFileTypeIcon($file['name'])
    ]);
    
} catch (PDOException $e) {
    // Удаление файла в случае ошибки
    @unlink($filepath);
    
    error_log('Upload error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
} 