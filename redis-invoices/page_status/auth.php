<?php
session_start();
header('Content-Type: application/json');

// Простые учетные данные (в реальном проекте лучше использовать базу данных)
$admin_credentials = [
    'username' => 'admin',
    'password' => 'sd34nwr' // В реальном проекте используйте хеширование
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if ($username === $admin_credentials['username'] && $password === $admin_credentials['password']) {
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_login_time'] = time();
        echo json_encode(['success' => true, 'message' => 'Успешная авторизация']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Неверные учетные данные']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Проверка статуса аутентификации
    if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true) {
        // Проверяем, не истекла ли сессия (например, 1 час)
        $login_time = $_SESSION['admin_login_time'] ?? 0;
        if (time() - $login_time < 3600) { // 1 час
            echo json_encode(['authenticated' => true]);
        } else {
            // Сессия истекла
            unset($_SESSION['admin_authenticated']);
            unset($_SESSION['admin_login_time']);
            echo json_encode(['authenticated' => false]);
        }
    } else {
        echo json_encode(['authenticated' => false]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Выход из системы
    unset($_SESSION['admin_authenticated']);
    unset($_SESSION['admin_login_time']);
    echo json_encode(['success' => true, 'message' => 'Выход выполнен']);
}
?>
