<?php
session_start();


$rootPath = dirname(__DIR__, 2) . '/';
chdir($rootPath);

// Подключаем файлы Vtiger
require_once 'include/utils/utils.php';
require_once 'Logger.php';
include_once 'includes/runtime/BaseModel.php';
include_once 'includes/runtime/Globals.php';
include_once 'includes/runtime/Controller.php';
include_once 'includes/http/Request.php';
require_once 'include/database/PearDatabase.php';
require_once 'include/utils/UserInfoUtil.php';
require_once 'modules/Users/Users.php';

// Инициализируем глобальные переменные, такие как $adb для доступа к БД.
global $current_user, $adb;

// Функция для логирования в наш файл
function logToFile($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] [$level] $message" . PHP_EOL, 3, 'redis-invoices/work_redis_log.log');
}

// Логируем начало работы скрипта
logToFile('Скрипт tabs_dashboard.php загружен, пользователь: ' . ($_SESSION['authenticated_user_id'] ?? 'не авторизован'), 'INFO');

// Получаем ID пользователя из сессии
$currentUserId = $_SESSION['authenticated_user_id'] ?? null;

// Если пользователь не авторизован, показываем страницу "Доступ запрещён"
if (!$currentUserId) {
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Доступ запрещён</title><style>body{background:#f4f6fb;font-family:Segoe UI,Arial,sans-serif;} .centered{max-width:420px;margin:100px auto;padding:32px 28px 24px 28px;background:#fff;border-radius:18px;box-shadow:0 8px 32px 0 rgba(31,38,135,0.18);text-align:center;} h2{color:#b91c1c;} p{color:#2d3a4b;font-size:17px;margin-top:18px;} a{color:#2193b0;text-decoration:none;font-weight:600;}</style></head><body><div class="centered"><h2>Доступ запрещён</h2><p>Пожалуйста, <a href="/index.php?module=Users&action=Login">авторизуйтесь в биллинге</a> и затем вернитесь на эту страницу.</p></div></body></html>';
    exit;
}

// Получаем groupid пользователя
$currentUserGroupId = null;
$result = $adb->pquery(
    "SELECT DISTINCT vug.groupid 
     FROM vtiger_users2group vug 
     WHERE vug.userid = ?", 
    array($currentUserId)
);

if ($adb->num_rows($result) > 0) {
    $row = $adb->fetchByAssoc($result);
    $currentUserGroupId = $row['groupid'];
}

logToFile("User ID: $currentUserId, Group ID: $currentUserGroupId", 'INFO');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мониторинг воркера генерации счетов</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .status-card:hover {
            transform: translateY(-2px);
        }
        .status-card.active {
            border-width: 3px !important;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .log-container {
            max-height: 500px;
            overflow-y: auto;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
        }
        .log-line {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
            padding: 0.25rem;
            border-radius: 0.25rem;
        }
        .log-success { background-color: #d1edff; }
        .log-error { background-color: #f8d7da; }
        .log-warning { background-color: #fff3cd; }
        .log-info { background-color: #d4edda; }
        
        .top-panel {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        
        .gap-3 {
            gap: 1rem !important;
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .progress-bar {
            transition: width 0.5s ease-in-out !important;
        }
        .progress-bar.animated {
            animation: progressGlow 2s ease-in-out infinite;
        }
        @keyframes progressGlow {
            0% { box-shadow: 0 0 5px rgba(0,123,255,0.5); }
            50% { box-shadow: 0 0 15px rgba(0,123,255,0.8); }
            100% { box-shadow: 0 0 5px rgba(0,123,255,0.5); }
        }
        .tab-content {
            min-height: 400px;
        }
        .task-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 0.5rem;
        }
        .task-item.success {
            border-left: 4px solid #28a745;
        }
        .task-item.error {
            border-left: 4px solid #dc3545;
        }
        .task-item.processing {
            border-left: 4px solid #ffc107;
        }
        .task-item.queued {
            border-left: 4px solid #007bff;
        }
        
        /* Индикатор загрузки страницы */
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: opacity 0.3s ease;
        }
        .page-loader.hidden {
            opacity: 0;
            pointer-events: none;
        }
        .page-loader-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .page-loader-text {
            font-size: 18px;
            color: #007bff;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Индикатор загрузки страницы -->
    <div class="page-loader" id="pageLoader">
        <div class="page-loader-spinner"></div>
        <div class="page-loader-text">Загрузка страницы...</div>
    </div>
    
    <div class="container-fluid mt-4">
        <!-- Верхняя панель с логотипом и информацией -->
        <div class="row mb-3 top-panel">
            <div class="col-md-6">
                <img src="oimo_billing_logo.png" alt="OIMO Billing" style="height: 40px;">
            </div>
            <div class="col-md-6 text-end">
                <div class="d-flex align-items-center justify-content-end gap-3">
                    <small class="text-muted">
                        <i class="fas fa-user"></i> ID: <span id="currentUserId">--</span> | 
                        <i class="fas fa-users"></i> Группа: <span id="currentGroupId">--</span>
                    </small>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="autoRefresh" checked>
                        <label class="form-check-label" for="autoRefresh">
                            <i class="fas fa-sync-alt" id="refreshIcon"></i> Автообновление
                        </label>
                    </div>
                    <small class="text-muted" id="lastUpdate">Обновлено: --:--:--</small>
                </div>
            </div>
        </div>


        <!-- Статистика с вкладками -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card status-card border-primary" onclick="showTab('queued')" id="queuedCard">
                    <div class="card-body text-center">
                        <i class="fas fa-list fa-2x text-primary mb-2"></i>
                        <h5 class="card-title">Задачи в очереди</h5>
                        <h2 class="text-primary" id="queueLength">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card status-card border-info" onclick="showTab('retry')" id="retryCard">
                    <div class="card-body text-center">
                        <i class="fas fa-redo fa-2x text-info mb-2"></i>
                        <h5 class="card-title">Повторы</h5>
                        <h2 class="text-info" id="retryCount">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card status-card border-success" onclick="showTab('successful')" id="successfulCard">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h5 class="card-title">Успешные</h5>
                        <h2 class="text-success" id="successful">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card status-card border-danger" onclick="showTab('errors')" id="errorsCard">
                    <div class="card-body text-center">
                        <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                        <h5 class="card-title">Ошибки</h5>
                        <h2 class="text-danger" id="errors">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card status-card border-warning" onclick="showTab('progress')" id="progressCard" style="display: none;">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-line fa-2x text-warning mb-2"></i>
                        <h5 class="card-title">Очередь прогресса</h5>
                        <h2 class="text-warning" id="progressCount">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card status-card border-secondary" onclick="showTab('scheduled')" id="scheduledCard">
                    <div class="card-body text-center">
                        <i class="fas fa-clock fa-2x text-secondary mb-2"></i>
                        <h5 class="card-title">Запланированные</h5>
                        <h2 class="text-secondary" id="scheduledCount">0</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Детальная информация по выбранному статусу -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 id="detailTitle"><i class="fas fa-list"></i> Задачи в очереди Redis</h5>
                        <div>
                            <button class="btn btn-sm btn-outline-success me-2" id="exportExcelBtn" onclick="exportErrorsToExcel()" style="display: none;">
                                <i class="fas fa-file-excel"></i> Выгрузить в Excel
                            </button>
                            <button class="btn btn-sm btn-outline-danger me-2" id="clearAllBtn" onclick="clearAllTasks()" style="display: none;">
                                <i class="fas fa-trash"></i> Очистить все
                            </button>
                            <button class="btn btn-sm btn-outline-warning me-2" id="adminBtn" onclick="toggleAdmin()" title="Админ панель">
                                <i class="fas fa-key"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="refreshData()">
                                <i class="fas fa-sync-alt" id="refreshIcon"></i> Обновить
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="detailContent">
                            <div class="text-center text-muted">
                                <i class="fas fa-spinner fa-spin"></i> Загрузка...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- История операций -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> История операций</h5>
                    </div>
                    <div class="card-body">
                        <div id="operationsHistory">
                            <div class="text-center text-muted">
                                <i class="fas fa-spinner fa-spin"></i> Загрузка истории...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>


    <!-- Модальное окно для входа в админку -->
    <div class="modal fade" id="adminModal" tabindex="-1" aria-labelledby="adminModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminModalLabel">
                        <i class="fas fa-key"></i> Вход в админ панель
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="adminLoginForm">
                        <div class="mb-3">
                            <label for="adminUsername" class="form-label">Логин</label>
                            <input type="text" class="form-control" id="adminUsername" required>
                        </div>
                        <div class="mb-3">
                            <label for="adminPassword" class="form-label">Пароль</label>
                            <input type="password" class="form-control" id="adminPassword" required>
                        </div>
                        <div id="adminError" class="alert alert-danger" style="display: none;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" onclick="adminLogin()">
                        <i class="fas fa-sign-in-alt"></i> Войти
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let autoRefreshInterval;
        let currentData = {};
        let isAdmin = false;
        
        // Получаем userId и groupId из PHP
        let userId = <?php echo json_encode($currentUserId); ?>;
        let userGroupId = <?php echo json_encode($currentUserGroupId); ?>;
        
        // Устанавливаем userId и groupId в интерфейс
        document.getElementById('currentUserId').textContent = userId;
        document.getElementById('currentGroupId').textContent = userGroupId || 'Не определен';
        
        // Убираем userId из URL, так как теперь используем сессию
        // (оставляем только для обратной совместимости, но не используем)
        
        // Загружаем данные
        refreshData();
        
        function showTab(tabName) {
            // Убираем активный класс со всех карточек
            document.querySelectorAll('.status-card').forEach(card => {
                card.classList.remove('active');
            });
            
            // Добавляем активный класс к выбранной карточке
            document.getElementById(tabName + 'Card').classList.add('active');
            
            // Показываем/скрываем кнопку "Очистить все"
            const clearBtn = document.getElementById('clearAllBtn');
            if ((tabName === 'queued' || tabName === 'successful' || tabName === 'errors' || tabName === 'retry' || tabName === 'progress' || tabName === 'scheduled') && isAdmin) {
                clearBtn.style.display = 'inline-block';
                clearBtn.setAttribute('data-list-type', tabName);
            } else {
                clearBtn.style.display = 'none';
            }
            
            // Показываем/скрываем кнопку "Выгрузить в Excel" только для вкладки ошибок
            const exportBtn = document.getElementById('exportExcelBtn');
            if (tabName === 'errors') {
                // Кнопка будет показана в updateErrorTasks, если есть ошибки
            } else {
                exportBtn.style.display = 'none';
            }
            
            // Обновляем заголовок и контент
            updateDetailContent(tabName);
        }
        
        function updateDetailContent(tabName) {
            const titleElement = document.getElementById('detailTitle');
            const contentElement = document.getElementById('detailContent');
            
            if (!currentData || !currentData.stats) {
                contentElement.innerHTML = '<div class="text-center text-muted">Загрузка данных...</div>';
                return;
            }
            
            switch(tabName) {
                case 'queued':
                    titleElement.innerHTML = '<i class="fas fa-list"></i> Задачи в очереди Redis';
                    updateQueuedTasks(currentData);
                    break;
                case 'retry':
                    titleElement.innerHTML = '<i class="fas fa-redo"></i> Задачи на повторы';
                    updateRetryTasks(currentData);
                    break;
                case 'successful':
                    titleElement.innerHTML = '<i class="fas fa-check-circle"></i> Успешно обработанные задачи';
                    updateSuccessfulTasks(currentData);
                    break;
                case 'errors':
                    titleElement.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Задачи с ошибками';
                    updateErrorTasks(currentData);
                    break;
                case 'progress':
                    titleElement.innerHTML = '<i class="fas fa-chart-line"></i> Очередь прогресса (только для админов)';
                    updateProgressTasks(currentData);
                    break;
                case 'scheduled':
                    titleElement.innerHTML = '<i class="fas fa-clock"></i> Запланированные задачи';
                    updateScheduledTasks(currentData);
                    break;
            }
        }
        
        function refreshData() {
            const refreshIcon = document.getElementById('refreshIcon');
            refreshIcon.classList.add('fa-spin');
            
            // Не передаем userid - сервер возьмет его из сессии текущего пользователя
            console.log('Making API request to: api.php (userId from session)');
            fetch('api.php', {
                credentials: 'include'
            })
                .then(response => {
                    console.log('API Response status:', response.status);
                    console.log('API Response headers:', response.headers);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    return response.text();
                })
                .then(text => {
                    console.log('API Raw response:', text);
                    
                    try {
                        const data = JSON.parse(text);
                        return data;
                    } catch (e) {
                        console.error('API Response is not JSON:', e);
                        console.error('Response text:', text);
                        throw new Error('API returned non-JSON response');
                    }
                })
                .then(data => {
                    if (data.error) {
                        console.error('Ошибка API:', data.error);
                        return;
                    }
                    
                    currentData = data;
                    
                    // Обновляем статистику
                    document.getElementById('queueLength').textContent = data.stats.queue_length;
                    document.getElementById('retryCount').textContent = data.stats.retry_length;
                    document.getElementById('successful').textContent = data.stats.success_length;
                    document.getElementById('errors').textContent = data.stats.error_length;
                    document.getElementById('progressCount').textContent = data.stats.progress_length;
                    document.getElementById('scheduledCount').textContent = data.stats.scheduled_length;
                    
                    // Обновляем историю операций
                    updateOperationsHistory(data);
                    
                    // Показываем первую вкладку по умолчанию
                    if (!document.querySelector('.status-card.active')) {
                        showTab('queued');
                    } else {
                        // Обновляем текущую активную вкладку
                        const activeCard = document.querySelector('.status-card.active');
                        const tabName = activeCard.id.replace('Card', '');
                        updateDetailContent(tabName);
                    }
                    
                    // Обновляем время
                    document.getElementById('lastUpdate').textContent = 'Обновлено: ' + data.timestamp;
                    
                    // Анимация для иконки процесса
                    const progressIcon = document.getElementById('progressIcon');
                    if (progressIcon) {
                        if (data.stats.in_progress > 0) {
                            progressIcon.classList.add('fa-spin', 'pulse');
                        } else {
                            progressIcon.classList.remove('fa-spin', 'pulse');
                        }
                    }
                })
                .catch(error => {
                    console.error('Ошибка при обновлении данных:', error);
                    document.getElementById('detailContent').innerHTML =
                        '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Ошибка загрузки данных: ' + error.message + '. Проверьте консоль браузера (F12).</div>';
                    document.getElementById('lastUpdate').textContent = 'Ошибка: ' + new Date().toLocaleTimeString();
                })
                .finally(() => {
                    refreshIcon.classList.remove('fa-spin');
                });
        }
        
        // Функция для быстрого обновления только очереди прогресса
        function refreshProgressOnly() {
            // Не передаем userid - сервер возьмет его из сессии текущего пользователя
            fetch('api.php', {
                credentials: 'include'
            })
                .then(response => response.json())
                .then(data => {
                    if (data && data.stats) {
                        // Обновляем только счетчик прогресса
                        document.getElementById('progressCount').textContent = data.stats.progress_length;
                        
                        // Если открыта вкладка прогресса, обновляем её содержимое
                        const activeCard = document.querySelector('.status-card.active');
                        if (activeCard && activeCard.id === 'progressCard') {
                            updateProgressTasks(data);
                        }
                    }
                })
                .catch(error => {
                    console.error('Ошибка при обновлении прогресса:', error);
                });
        }
        
        function updateQueuedTasks(data) {
            const container = document.getElementById('detailContent');
            
            if (data.stats.queue_length === 0) {
                container.innerHTML = '<div class="text-center text-muted">Нет задач в очереди</div>';
                return;
            }
            
            if (!data.queued_tasks || data.queued_tasks.length === 0) {
                container.innerHTML = '<div class="text-center text-muted">Нет задач в очереди</div>';
                return;
            }
            
            let html = '';
            data.queued_tasks.forEach((task, index) => {
                html += `
                    <div class="task-item queued">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><a href="/index.php?module=Estates&view=Detail&record=${task.objectID}" target="_blank" style="color:inherit;text-decoration:underline;">Object ID: ${task.objectID}</a></strong>
                                <br>
                                <small class="text-muted">Месяцы: ${task.months.join(', ')}</small>
                                <br>
                                <small class="text-muted">Типы: ${task.entityTypes.join(', ')}</small>
                                <br>
                                <small class="text-muted">User ID: ${task.userID}</small>
                                <br>
                                <small class="text-muted">Источник: ${task.source}</small>
                            </div>
                            <div>
                                <span class="badge bg-primary">В очереди</span>
                                <br>
                                <small class="text-muted">#${index + 1}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            
            // Обновляем видимость кнопок админа
            if (isAdmin) {
                showAdminButtons();
            } else {
                hideAdminButtons();
            }
        }
        
        function updateRetryTasks(data) {
            const container = document.getElementById('detailContent');
            
            if (data.stats.retry_length === 0) {
                container.innerHTML = '<div class="text-center text-muted">Нет задач на повторы</div>';
                return;
            }
            
            if (!data.retry_tasks || data.retry_tasks.length === 0) {
                container.innerHTML = '<div class="text-center text-muted">Нет задач на повторы</div>';
                return;
            }
            
            let html = '';
            data.retry_tasks.forEach((task, index) => {
                const retryCount = task.retryCount || 0;
                html += `
                    <div class="task-item retry">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><a href="/index.php?module=Estates&view=Detail&record=${task.objectID}" target="_blank" style="color:inherit;text-decoration:underline;">Object ID: ${task.objectID}</a></strong>
                                <br>
                                <small class="text-muted">Месяцы: ${task.months.join(', ')}</small>
                                <br>
                                <small class="text-muted">Типы: ${task.entityTypes.join(', ')}</small>
                                <br>
                                <small class="text-muted">User ID: ${task.userID}</small>
                                <br>
                                <small class="text-muted">Попытка: ${retryCount}/2</small>
                            </div>
                            <div>
                                <span class="badge bg-info">Повтор</span>
                                <br>
                                <small class="text-muted">#${index + 1}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function updateProcessingTasks(data) {
            const container = document.getElementById('detailContent');
            const processingLogs = data.worker_logs.filter(log => 
                log.includes('принадлежит МП Тамчы ТазаСуу') && 
                !log.includes('=== КОНЕЦ ОБРАБОТКИ')
            );
            
            if (processingLogs.length === 0) {
                container.innerHTML = '<div class="text-center text-muted">Нет задач в процессе</div>';
                return;
            }
            
            let html = '';
            processingLogs.forEach(log => {
                const match = log.match(/Object ID (\d+)/);
                if (match) {
                    const objectId = match[1];
                    html += `
                        <div class="task-item processing">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><a href="/index.php?module=Estates&view=Detail&record=${objectId}" target="_blank" style="color:inherit;text-decoration:underline;">Object ID: ${objectId}</a></strong>
                                    <br>
                                    <small class="text-muted">${log}</small>
                                </div>
                                <div>
                                    <span class="badge bg-warning">В процессе</span>
                                </div>
                            </div>
                        </div>
                    `;
                }
            });
            
            container.innerHTML = html;
        }
        
        function updateSuccessfulTasks(data) {
            const container = document.getElementById('detailContent');
            
            if (data.stats.success_length === 0) {
                container.innerHTML = '<div class="text-center text-muted">Нет успешно обработанных задач</div>';
                return;
            }
            
            if (!data.success_tasks || data.success_tasks.length === 0) {
                container.innerHTML = '<div class="text-center text-muted">Нет успешно обработанных задач</div>';
                return;
            }
            
            let html = '';
            data.success_tasks.forEach((task, index) => {
                html += `
                    <div class="task-item success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><a href="/index.php?module=Estates&view=Detail&record=${task.objectID}" target="_blank" style="color:inherit;text-decoration:underline;">Object ID: ${task.objectID}</a></strong>
                                <br>
                                <small class="text-muted">Месяцы: ${task.months.join(', ')}</small>
                                <br>
                                <small class="text-muted">Типы: ${task.entityTypes.join(', ')}</small>
                                <br>
                                <small class="text-muted">User ID: ${task.userID}</small>
                                ${task.invoiceId ? `<br><small class="text-muted"><a href="/index.php?module=Invoice&view=Detail&record=${task.invoiceId}" target="_blank" style="color:inherit;text-decoration:underline;">Счет ID: ${task.invoiceId}</a></small>` : ''}
                                ${task.completedAt ? `<br><small class="text-muted">Завершено: ${task.completedAt}</small>` : ''}
                            </div>
                            <div>
                                <span class="badge bg-success">Успешно</span>
                                <br>
                                <button class="btn btn-sm btn-outline-danger mt-1" onclick="deleteTask('${task.objectID}', 'success')" style="display: none;">
                                    <i class="fas fa-trash"></i> Удалить
                                </button>
                                <br>
                                <small class="text-muted">#${index + 1}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            
            // Обновляем видимость кнопок админа
            if (isAdmin) {
                showAdminButtons();
            } else {
                hideAdminButtons();
            }
        }
        
        function updateErrorTasks(data) {
            const container = document.getElementById('detailContent');
            const exportBtn = document.getElementById('exportExcelBtn');
            
            if (data.stats.error_length === 0) {
                container.innerHTML = '<div class="text-center text-muted">Нет ошибок</div>';
                exportBtn.style.display = 'none';
                return;
            }
            
            if (!data.error_tasks || data.error_tasks.length === 0) {
                container.innerHTML = '<div class="text-center text-muted">Нет ошибок</div>';
                exportBtn.style.display = 'none';
                return;
            }
            
            // Показываем кнопку выгрузки
            exportBtn.style.display = 'inline-block';
            
            let html = '';
            data.error_tasks.forEach((task, index) => {
                // Используем реальное сообщение об ошибке: errorMessage, lastError или дефолт
                const errorMsg = task.errorMessage || task.lastError || 'Неизвестная ошибка';
                html += `
                    <div class="task-item error">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><a href="/index.php?module=Estates&view=Detail&record=${task.objectID}" target="_blank" style="color:inherit;text-decoration:underline;">Object ID: ${task.objectID}</a></strong>
                                <br>
                                <small class="text-muted">Месяцы: ${task.months.join(', ')}</small>
                                <br>
                                <small class="text-muted">Типы: ${task.entityTypes.join(', ')}</small>
                                <br>
                                <small class="text-muted">User ID: ${task.userID}</small>
                                <br>
                                <small class="text-muted">Ошибка: ${errorMsg}</small>
                                ${task.failedAt ? `<br><small class="text-muted">Время ошибки: ${task.failedAt}</small>` : ''}
                            </div>
                            <div>
                                <span class="badge bg-danger">Ошибка</span>
                                <br>
                                <button class="btn btn-sm btn-outline-primary mt-1" onclick="retryTask('${task.objectID}')">
                                    <i class="fas fa-redo"></i> Повторить
                                </button>
                                <br>
                                <button class="btn btn-sm btn-outline-danger mt-1" onclick="deleteTask('${task.objectID}', 'errors')" style="display: none;">
                                    <i class="fas fa-trash"></i> Удалить
                                </button>
                                <br>
                                <small class="text-muted">#${index + 1}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            
            // Обновляем видимость кнопок админа
            if (isAdmin) {
                showAdminButtons();
            } else {
                hideAdminButtons();
            }
        }
        
        function exportErrorsToExcel() {
            // Получаем userid из URL
            const urlParams = new URLSearchParams(window.location.search);
            const userId = urlParams.get('userid') || '68';
            
            // Открываем страницу выгрузки
            window.location.href = `export_errors_to_excel.php?userid=${userId}`;
        }
        
        function updateOperationsHistory(data) {
            const container = document.getElementById('operationsHistory');
            
            // Собираем все операции из Redis списков
            const operations = [];
            
            // Добавляем задачи из очереди
            if (data.queued_tasks) {
                data.queued_tasks.forEach(task => {
                    operations.push({
                        type: 'queued',
                        objectId: task.objectID,
                        timestamp: new Date().toISOString(),
                        message: 'В очереди на обработку'
                    });
                });
            }
            
            // Добавляем задачи на повторы
            if (data.retry_tasks) {
                data.retry_tasks.forEach(task => {
                    operations.push({
                        type: 'retry',
                        objectId: task.objectID,
                        retryCount: task.retryCount || 0,
                        timestamp: new Date().toISOString(),
                        message: `Повтор ${task.retryCount || 0}/2`
                    });
                });
            }
            
            // Добавляем успешные задачи
            if (data.success_tasks) {
                data.success_tasks.forEach(task => {
                    operations.push({
                        type: 'success',
                        objectId: task.objectID,
                        invoiceId: task.invoiceId,
                        timestamp: task.completedAt || new Date().toISOString(),
                        message: 'Счет успешно создан'
                    });
                });
            }
            
            // Добавляем задачи с ошибками
            if (data.error_tasks) {
                data.error_tasks.forEach(task => {
                    // Используем реальное сообщение об ошибке: errorMessage, lastError или дефолт
                    const errorMsg = task.errorMessage || task.lastError || 'Неизвестная ошибка';
                    operations.push({
                        type: 'error',
                        objectId: task.objectID,
                        errorMessage: errorMsg,
                        timestamp: task.failedAt || new Date().toISOString(),
                        message: errorMsg
                    });
                });
            }
            
            // Сортируем по времени (новые сверху)
            operations.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
            
            if (operations.length === 0) {
                container.innerHTML = '<div class="text-center text-muted">Нет операций</div>';
                return;
            }
            
            let html = '';
            operations.forEach(op => {
                let badgeClass = '';
                let icon = '';
                
                switch(op.type) {
                    case 'success':
                        badgeClass = 'bg-success';
                        icon = 'fas fa-check-circle';
                        break;
                    case 'error':
                        badgeClass = 'bg-danger';
                        icon = 'fas fa-exclamation-triangle';
                        break;
                    case 'retry':
                        badgeClass = 'bg-info';
                        icon = 'fas fa-redo';
                        break;
                    case 'queued':
                        badgeClass = 'bg-primary';
                        icon = 'fas fa-list';
                        break;
                }
                
                html += `
                    <div class="task-item ${op.type}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><a href="/index.php?module=Estates&view=Detail&record=${op.objectId}" target="_blank" style="color:inherit;text-decoration:underline;">Object ID: ${op.objectId}</a></strong>
                                ${op.invoiceId ? `<br><small class="text-muted"><a href="/index.php?module=Invoice&view=Detail&record=${op.invoiceId}" target="_blank" style="color:inherit;text-decoration:underline;">Счет ID: ${op.invoiceId}</a></small>` : ''}
                                <br>
                                <small class="text-muted">${op.message}</small>
                                <br>
                                <small class="text-muted">${op.timestamp}</small>
                            </div>
                            <div>
                                <span class="badge ${badgeClass}">
                                    <i class="${icon}"></i> ${op.type === 'success' ? 'Успешно' : op.type === 'error' ? 'Ошибка' : op.type === 'retry' ? 'Повтор' : 'В очереди'}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function updateProgressTasks(data) {
            const container = document.getElementById('detailContent');

            if (data.stats.progress_length === 0) {
                container.innerHTML = '<div class="text-center text-muted">Очередь прогресса пуста</div>';
                return;
            }

            // Если очередь завершена (основная очередь пуста), показываем итог без загрузки задач
            if (data.queue_completed) {
                container.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5 class="text-success">Обработка завершена</h5>
                        <p class="text-muted">Все задачи обработаны. В истории прогресса: <strong>${data.stats.progress_length}</strong> записей.</p>
                        ${isAdmin ? '<button class="btn btn-sm btn-outline-danger" onclick="clearProgressHistory()">Очистить историю прогресса</button>' : ''}
                    </div>`;
                return;
            }

            if (!data.progress_tasks || data.progress_tasks.length === 0) {
                container.innerHTML = '<div class="text-center text-muted">Очередь прогресса пуста</div>';
                return;
            }
            
            // Группируем задачи по месяцам и типам (статус не учитываем, так как это очередь прогресса)
            const groupedTasks = {};
            data.progress_tasks.forEach(task => {
                const month = task.months.join(', ');
                const type = task.entityTypes.join(', ');
                const key = `${month} | ${type}`;
                
                if (!groupedTasks[key]) {
                    groupedTasks[key] = {
                        months: task.months,
                        entityTypes: task.entityTypes,
                        count: 0,
                        tasks: [],
                        totalProcessed: 0,
                        totalTotal: 0,
                        createdAt: task.createdAt,
                        lastUpdated: task.lastUpdated,
                        statuses: {} // Счетчики по статусам
                    };
                }
                groupedTasks[key].count++;
                groupedTasks[key].tasks.push(task);
                groupedTasks[key].totalProcessed += (task.processed || 0);
                groupedTasks[key].totalTotal += (task.total || 1);
                
                // Считаем статусы
                const status = task.status || 'Неизвестно';
                if (!groupedTasks[key].statuses[status]) {
                    groupedTasks[key].statuses[status] = 0;
                }
                groupedTasks[key].statuses[status]++;
            });
            
            let html = '';
            let groupIndex = 1;
            
            Object.entries(groupedTasks).forEach(([key, group]) => {
                const avgProgress = group.totalTotal > 0 ? Math.round((group.totalProcessed / group.totalTotal) * 100) : 0;
                
                // Определяем основной статус (самый частый)
                const mainStatus = Object.entries(group.statuses).reduce((a, b) => group.statuses[a[0]] > group.statuses[b[0]] ? a : b)[0];
                const statusClass = mainStatus === 'Завершено' ? 'success' : 
                                  mainStatus === 'В процессе' ? 'warning' : 'info';
                const statusIcon = mainStatus === 'Завершено' ? 'fas fa-check-circle' : 
                                 mainStatus === 'В процессе' ? 'fas fa-clock' : 'fas fa-hourglass-half';
                
                // Формируем строку со статусами
                const statusText = Object.entries(group.statuses)
                    .map(([status, count]) => `${status}: ${count}`)
                    .join(', ');
                
                html += `
                    <div class="task-item ${statusClass}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Группа ${groupIndex}: ${key}</strong>
                                <br>
                                <small class="text-muted">Количество задач: <span class="badge bg-info">${group.count}</span></small>
                                <br>
                                <small class="text-muted">Статусы: ${statusText}</small>
                                <br>
                                <small class="text-muted">User ID: ${group.tasks[0].userID}</small>
                                <br>
                                <small class="text-muted">Создано: ${group.createdAt || 'Неизвестно'}</small>
                                ${group.lastUpdated ? `<br><small class="text-muted">Обновлено: ${group.lastUpdated}</small>` : ''}
                                <br>
                                <small class="text-muted">Object IDs: ${group.tasks.slice(0, 5).map(t => t.objectID).join(', ')}${group.tasks.length > 5 ? '...' : ''}</small>
                            </div>
                            <div class="text-center">
                                <div class="mb-2">
                                    <span class="badge bg-${statusClass === 'success' ? 'success' : statusClass === 'warning' ? 'warning' : 'info'}">
                                        <i class="${statusIcon}"></i> ${mainStatus}
                                    </span>
                                </div>
                                <div class="progress mb-2" style="width: 150px; height: 20px;">
                                    <div class="progress-bar bg-${statusClass === 'success' ? 'success' : statusClass === 'warning' ? 'warning' : 'info'} ${mainStatus === 'В процессе' ? 'animated' : ''}" 
                                         role="progressbar" style="width: ${avgProgress}%; transition: width 0.5s ease-in-out;">
                                        ${avgProgress}%
                                    </div>
                                </div>
                                <small class="text-muted">${group.totalProcessed}/${group.totalTotal}</small>
                                <br>
                                <small class="text-muted">#${groupIndex}</small>
                            </div>
                        </div>
                    </div>
                `;
                groupIndex++;
            });
            
            container.innerHTML = html;
            
            // Обновляем видимость кнопок админа
            if (isAdmin) {
                showAdminButtons();
            } else {
                hideAdminButtons();
            }
        }
        
        function updateScheduledTasks(data) {
            const container = document.getElementById('detailContent');
            
            if (data.stats.scheduled_length === 0) {
                container.innerHTML = '<div class="text-center text-muted">Нет запланированных задач</div>';
                return;
            }
            
            if (!data.scheduled_tasks || data.scheduled_tasks.length === 0) {
                container.innerHTML = '<div class="text-center text-muted">Нет запланированных задач</div>';
                return;
            }
            
            // Группируем задачи по месяцам и типам
            const groupedTasks = {};
            data.scheduled_tasks.forEach(task => {
                const month = task.months.join(', ');
                const type = task.entityTypes.join(', ');
                const key = `${month} | ${type}`;
                
                if (!groupedTasks[key]) {
                    groupedTasks[key] = {
                        months: task.months,
                        entityTypes: task.entityTypes,
                        count: 0,
                        tasks: [],
                        scheduledTime: task.scheduledTime,
                        createdAt: task.createdAt
                    };
                }
                groupedTasks[key].count++;
                groupedTasks[key].tasks.push(task);
            });
            
            let html = '';
            let groupIndex = 1;
            
            Object.entries(groupedTasks).forEach(([key, group]) => {
                html += `
                    <div class="task-item info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Группа ${groupIndex}: ${key}</strong>
                                <br>
                                <small class="text-muted">Количество задач: <span class="badge bg-info">${group.count}</span></small>
                                <br>
                                <small class="text-muted">User ID: ${group.tasks[0].userID}</small>
                                <br>
                                <small class="text-muted">Создано: ${group.createdAt || 'Неизвестно'}</small>
                                ${group.scheduledTime ? `<br><small class="text-muted">Запланировано на: ${group.scheduledTime}</small>` : ''}
                                <br>
                                <small class="text-muted">Object IDs: ${group.tasks.slice(0, 5).map(t => t.objectID).join(', ')}${group.tasks.length > 5 ? '...' : ''}</small>
                            </div>
                            <div class="text-center">
                                <div class="mb-2">
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-clock"></i> Запланировано
                                    </span>
                                </div>
                                <div class="mb-2">
                                    <span class="badge bg-primary">
                                        <i class="fas fa-list"></i> ${group.count} задач
                                    </span>
                                </div>
                                <br>
                                <small class="text-muted">#${groupIndex}</small>
                            </div>
                        </div>
                    </div>
                `;
                groupIndex++;
            });
            
            container.innerHTML = html;
            
            // Обновляем видимость кнопок админа
            if (isAdmin) {
                showAdminButtons();
            } else {
                hideAdminButtons();
            }
        }
        
        function retryTask(objectId) {
            if (confirm(`Повторить задачу для Object ID: ${objectId}?`)) {
                // Отправляем запрос на повтор задачи
                fetch('retry_task.php', {
                    credentials: 'include',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ objectId: objectId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Задача отправлена на повтор');
                        refreshData();
                    } else {
                        alert('Ошибка при повторении задачи: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Ошибка при повторении задачи: ' + error.message);
                });
            }
        }
        
        function deleteTask(objectId, listType) {
            let listName = '';
            switch (listType) {
                case 'success':
                    listName = 'успешных';
                    break;
                case 'errors':
                    listName = 'ошибок';
                    break;
                case 'retry':
                    listName = 'повторов';
                    break;
            }
            
            if (confirm(`Удалить задачу для Object ID: ${objectId} из списка ${listName}?`)) {
                // Отправляем запрос на удаление задачи
                fetch('delete_task.php', {
                    credentials: 'include',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        objectId: objectId,
                        listType: listType
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Задача удалена из списка');
                        refreshData();
                    } else {
                        alert('Ошибка при удалении задачи: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Ошибка при удалении задачи: ' + error.message);
                });
            }
        }
        
        function deleteProgressTask(objectId, months, entityTypes) {
            if (confirm(`Удалить задачу для Object ID: ${objectId} из очереди прогресса?`)) {
                fetch('delete_progress_task.php', {
                    credentials: 'include',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        objectId: objectId,
                        months: months,
                        entityTypes: entityTypes,
                        userId: userId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Задача удалена из очереди прогресса');
                        refreshData();
                    } else {
                        alert('Ошибка при удалении задачи: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Ошибка при удалении задачи: ' + error.message);
                });
            }
        }
        
        function clearAllTasks() {
            const clearBtn = document.getElementById('clearAllBtn');
            const listType = clearBtn.getAttribute('data-list-type');
            
            let listName = '';
            switch (listType) {
                case 'queued':
                    listName = 'задач в очереди';
                    break;
                case 'successful':
                    listName = 'успешных задач';
                    break;
                case 'errors':
                    listName = 'задач с ошибками';
                    break;
                case 'retry':
                    listName = 'задач на повторы';
                    break;
                case 'progress':
                    listName = 'задач в очереди прогресса';
                    break;
                case 'scheduled':
                    listName = 'запланированных задач';
                    break;
            }
            
            if (confirm(`Очистить все ${listName}? Это действие нельзя отменить!`)) {
                // Отправляем запрос на очистку списка (userId будет взят из сессии на сервере)
                fetch(`clear_list.php`, {
                    credentials: 'include',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        listType: listType
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Response is not JSON:', text);
                            throw new Error('Сервер вернул неверный ответ. Проверьте логи.');
                        }
                    });
                })
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        refreshData();
                    } else {
                        alert('Ошибка при очистке списка: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Clear list error:', error);
                    alert('Ошибка при очистке списка: ' + error.message);
                });
            }
        }
        
        function clearProgressHistory() {
            if (confirm('Очистить историю прогресса (' + document.getElementById('progressCount').textContent + ' записей)? Это действие нельзя отменить!')) {
                fetch('clear_list.php', {
                    credentials: 'include',
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ listType: 'progress' })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        refreshData();
                    } else {
                        alert('Ошибка: ' + data.message);
                    }
                })
                .catch(err => alert('Ошибка: ' + err.message));
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Функции для работы с админкой
        function toggleAdmin() {
            if (isAdmin) {
                // Выход из админки
                adminLogout();
            } else {
                // Показать модальное окно входа
                const modal = new bootstrap.Modal(document.getElementById('adminModal'));
                modal.show();
            }
        }
        
        function adminLogin() {
            const username = document.getElementById('adminUsername').value;
            const password = document.getElementById('adminPassword').value;
            const errorDiv = document.getElementById('adminError');
            
            if (!username || !password) {
                errorDiv.textContent = 'Введите логин и пароль';
                errorDiv.style.display = 'block';
                return;
            }
            
            fetch('auth.php', {
                credentials: 'include',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    username: username,
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    isAdmin = true;
                    updateAdminUI();
                    const modal = bootstrap.Modal.getInstance(document.getElementById('adminModal'));
                    modal.hide();
                    // Очищаем форму
                    document.getElementById('adminLoginForm').reset();
                    errorDiv.style.display = 'none';
                } else {
                    errorDiv.textContent = data.message;
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                errorDiv.textContent = 'Ошибка при входе: ' + error.message;
                errorDiv.style.display = 'block';
            });
        }
        
        function adminLogout() {
            fetch('auth.php', {
                credentials: 'include',
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                isAdmin = false;
                updateAdminUI();
            })
            .catch(error => {
                console.error('Ошибка при выходе:', error);
                isAdmin = false;
                updateAdminUI();
            });
        }
        
        function checkAdminStatus() {
            fetch('auth.php', {
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                isAdmin = data.authenticated;
                updateAdminUI();
            })
            .catch(error => {
                console.error('Ошибка проверки статуса админа:', error);
                isAdmin = false;
                updateAdminUI();
            });
        }
        
        function updateAdminUI() {
            const adminBtn = document.getElementById('adminBtn');
            const clearAllBtn = document.getElementById('clearAllBtn');
            const progressCard = document.getElementById('progressCard');
            
            if (isAdmin) {
                adminBtn.innerHTML = '<i class="fas fa-sign-out-alt"></i>';
                adminBtn.title = 'Выйти из админ панели';
                adminBtn.classList.remove('btn-outline-warning');
                adminBtn.classList.add('btn-warning');
                
                // Показываем карточку прогресса для админа
                if (progressCard) {
                    progressCard.style.display = 'block';
                }
                
                // Показываем кнопки удаления для админа
                showAdminButtons();
            } else {
                adminBtn.innerHTML = '<i class="fas fa-key"></i>';
                adminBtn.title = 'Админ панель';
                adminBtn.classList.remove('btn-warning');
                adminBtn.classList.add('btn-outline-warning');
                
                // Скрываем карточку прогресса для обычных пользователей
                if (progressCard) {
                    progressCard.style.display = 'none';
                }
                
                // Скрываем кнопки удаления для обычных пользователей
                hideAdminButtons();
                
                // Скрываем кнопку "Очистить все" для обычных пользователей
                if (clearAllBtn) {
                    clearAllBtn.style.display = 'none';
                }
            }
        }
        
        function showAdminButtons() {
            // Показываем кнопки удаления в задачах
            document.querySelectorAll('.btn-outline-danger').forEach(btn => {
                if (btn.textContent.includes('Удалить')) {
                    btn.style.display = 'inline-block';
                }
            });
            
            // Показываем кнопку "Очистить все" если она должна быть видна
            const clearAllBtn = document.getElementById('clearAllBtn');
            if (clearAllBtn && clearAllBtn.getAttribute('data-list-type')) {
                clearAllBtn.style.display = 'inline-block';
            }
        }
        
        function hideAdminButtons() {
            // Скрываем кнопки удаления в задачах
            document.querySelectorAll('.btn-outline-danger').forEach(btn => {
                if (btn.textContent.includes('Удалить')) {
                    btn.style.display = 'none';
                }
            });
            
            // Скрываем кнопку "Очистить все"
            const clearAllBtn = document.getElementById('clearAllBtn');
            if (clearAllBtn) {
                clearAllBtn.style.display = 'none';
            }
        }
        
        
        // Автообновление
        let progressRefreshInterval;
        document.getElementById('autoRefresh').addEventListener('change', function() {
            if (this.checked) {
                autoRefreshInterval = setInterval(refreshData, 3000); // каждые 3 секунды полное обновление
                progressRefreshInterval = setInterval(refreshProgressOnly, 1000); // каждую секунду обновляем прогресс
            } else {
                clearInterval(autoRefreshInterval);
                clearInterval(progressRefreshInterval);
            }
        });
        
        // Скрываем индикатор загрузки после загрузки страницы
        window.addEventListener('load', function() {
            setTimeout(function() {
                const loader = document.getElementById('pageLoader');
                if (loader) {
                    loader.classList.add('hidden');
                    setTimeout(function() {
                        loader.style.display = 'none';
                    }, 300);
                }
            }, 500);
        });
        
        // Запускаем автообновление при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            refreshData();
            checkAdminStatus(); // Проверяем статус админа
            if (document.getElementById('autoRefresh').checked) {
                autoRefreshInterval = setInterval(refreshData, 3000);
                progressRefreshInterval = setInterval(refreshProgressOnly, 1000);
            }
        });
    </script>
</body>
</html>
