<?php
// Простой тест для clear_list.php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config.php';
    $redis = redisConnect();
    
    // Тестируем получение данных
    $userId = 68;
    $userGroupId = 58; // Хардкод для тестирования
    $mpPrefix = "mp{$userGroupId}";
    $listName = "task:invoice:scheduled:{$mpPrefix}";
    
    // Получаем количество задач
    $count = $redis->lLen($listName);
    
    echo json_encode([
        'success' => true,
        'message' => "Тест успешен. Найдено $count запланированных задач",
        'userId' => $userId,
        'userGroupId' => $userGroupId,
        'listName' => $listName,
        'count' => $count
    ]);
    
    $redis->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка: ' . $e->getMessage(),
        'error' => $e->getTraceAsString()
    ]);
}
?>
