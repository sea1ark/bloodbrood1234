<?php
// API для расшифровки сообщений и файлов
session_start();
header('Content-Type: application/json');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Включение отображения ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Получение данных из запроса
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['message_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message ID required']);
    exit;
}

$messageId = (int)$input['message_id'];

try {
    // Получение зашифрованного сообщения из базы данных
    $stmt = $pdo->prepare("
        SELECT content, is_encrypted 
        FROM messages 
        WHERE id = ? AND is_deleted = 0
    ");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    
    if (!$message) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Message not found']);
        exit;
    }
    
    if (!$message['is_encrypted']) {
        echo json_encode(['success' => true, 'content' => nl2br(htmlspecialchars($message['content']))]);
        exit;
    }
    
    // Расшифровка содержимого
    $decryptedContent = decrypt($message['content'], ENCRYPTION_KEY);
    
    if ($decryptedContent === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Decryption failed']);
        exit;
    }
    
    // Логирование действия
    logSystemAction('MESSAGE_DECRYPTED', "User decrypted message #$messageId");
    
    echo json_encode([
        'success' => true,
        'content' => nl2br(htmlspecialchars($decryptedContent))
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Decryption error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
?> 