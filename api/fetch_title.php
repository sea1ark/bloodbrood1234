<?php
// API для получения заголовка страницы по URL
header('Content-Type: application/json');

// Получение данных из POST-запроса
$data = json_decode(file_get_contents('php://input'), true);
$url = $data['url'] ?? '';

if (empty($url)) {
    echo json_encode(['success' => false, 'message' => 'URL не указан']);
    exit;
}

// Проверка URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Некорректный URL']);
    exit;
}

// Установка timeout для запроса
$context = stream_context_create([
    'http' => [
        'timeout' => 5 // Таймаут в секундах
    ]
]);

try {
    // Получение содержимого страницы
    $html = @file_get_contents($url, false, $context);
    
    if ($html === false) {
        echo json_encode(['success' => false, 'message' => 'Не удалось загрузить страницу']);
        exit;
    }
    
    // Извлечение заголовка
    if (preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
        $title = trim($matches[1]);
        echo json_encode(['success' => true, 'title' => $title]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Заголовок не найден']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
}