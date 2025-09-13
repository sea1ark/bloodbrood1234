<?php
require_once 'includes/init.php';
requireLogin();

// Получение ID файла из GET-параметра
$fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$fileId) {
    die('ID файла не указан');
}

try {
    // Получение информации о файле
    $stmt = $pdo->prepare("
        SELECT a.*, m.user_id, m.is_encrypted 
        FROM attachments a 
        JOIN messages m ON a.message_id = m.id 
        WHERE a.id = ? AND m.is_deleted = 0
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    
    if (!$file) {
        die('Файл не найден');
    }
    
    // Проверка существования файла на диске
    $filePath = UPLOAD_DIR . $file['filename'];
    if (!file_exists($filePath)) {
        die('Файл не найден на сервере');
    }
    
    // Логирование скачивания
    logSystemAction('FILE_DOWNLOADED', "User downloaded file: {$file['original_filename']}");
    
    // Установка заголовков для скачивания
    header('Content-Description: File Transfer');
    header('Content-Type: ' . ($file['file_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Если файл зашифрован, расшифровываем его перед отправкой
    if ($file['is_encrypted']) {
        // Чтение зашифрованного содержимого
        $encryptedContent = file_get_contents($filePath);
        
        // Расшифровка содержимого
        $content = decrypt($encryptedContent, ENCRYPTION_KEY);
        
        // Отправка расшифрованного содержимого
        header('Content-Length: ' . strlen($content));
        echo $content;
    } else {
        // Отправка файла без расшифровки
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
    }
    
    exit;
    
} catch (Exception $e) {
    error_log('File download error: ' . $e->getMessage());
    die('Ошибка при скачивании файла: ' . $e->getMessage());
}
?> 