// JS для модального окна генерации счетов для объектов недвижимости

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('estateInvoiceModal');
    const form = document.getElementById('generateEstateInvoicesForm');
    const btn = document.getElementById('startEstateInvoiceGenerationButton');
    const entityTypeContainer = document.getElementById('estateEntityTypeCheckboxes');
    const modalDialog = modal.querySelector('.modal-dialog');
    const modalTitle = modal.querySelector('#estateInvoiceModalLabel');
    const localityDropdownContainer = document.getElementById('localityDropdownContainer');
    const streetDropdownContainer = document.getElementById('streetDropdownContainer');
    const localityDropdown = document.getElementById('localityDropdown');
    const streetDropdown = document.getElementById('streetDropdown');
    const scheduledDateTime = document.getElementById('scheduledDateTime');
    const taskQueueSection = document.getElementById('taskQueueSection');
    const taskQueueTableBody = document.getElementById('taskQueueTableBody');
    const taskQueueCount = document.getElementById('taskQueueCount');
    const toggleTaskQueue = document.getElementById('toggleTaskQueue');
    const taskQueueTableContainer = document.getElementById('taskQueueTableContainer');
    let allLocalityData = [];
    let selectedLocalities = [];
    let selectedStreets = [];
    let selectedEntityTypes = [];
    let taskQueue = [];
    let modalRefreshInterval;
    
    // Безопасно получаем userId из сессии
    let userId = null;
    
    // Получаем ID пользователя из атрибута модального окна
    function getUserId() {
        const modal = document.getElementById('estateInvoiceModal');
        if (modal) {
            userId = modal.getAttribute('data-user-id');
            window.userId = userId; // Сохраняем в глобальную переменную
            console.log('User ID получен из атрибута модального окна:', userId);
        }
        
        // Если не получили из атрибута, пробуем через AJAX
        if (!userId) {
            fetch('/redis-invoices/page_status/get_user_id.php', {
                credentials: 'include'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        userId = data.userId;
                        window.userId = userId; // Сохраняем в глобальную переменную
                        console.log('User ID получен из сессии:', userId);
                    } else {
                        console.error('Ошибка получения ID пользователя:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Ошибка AJAX при получении ID пользователя:', error);
                });
        }
    }
    
    // Вызываем функцию получения userId при загрузке
    getUserId();

    // Удалено: создание и использование streetsContainer и html-вывод div/ul-списков населённых пунктов и улиц

    // Добавляем чекбоксы типов лиц (можно заменить на динамику)
    entityTypeContainer.innerHTML = `
        <label><input type="checkbox" name="entityType[]" value="Физ. лицо"> Физ. лицо</label><br>
        <label><input type="checkbox" name="entityType[]" value="Юр. лицо"> Юр. лицо</label>
    `;
    console.log('Чекбоксы типов лиц созданы:', entityTypeContainer.innerHTML);

    // Блокировка будущих месяцев (с учетом выбранного года)
    function disableFutureMonths() {
        const yearSelect = document.getElementById('invoiceYear');
        const selectedYear = yearSelect ? parseInt(yearSelect.value) : new Date().getFullYear();
        const currentDate = new Date();
        const currentYear = currentDate.getFullYear();
        const currentMonth = currentDate.getMonth() + 1;
        
        const monthCheckboxes = form.querySelectorAll('input[name="months[]"]');
        monthCheckboxes.forEach(function (checkbox) {
            const monthValue = parseInt(checkbox.value);
            // Блокируем будущие месяцы только если выбран текущий год
            if (selectedYear === currentYear) {
                checkbox.disabled = monthValue > currentMonth;
            } else {
                checkbox.disabled = false; // Разблокируем все месяцы для прошлых/будущих лет
            }
        });
    }
    // Автовыбор текущего месяца
    function selectCurrentMonth() {
        const currentMonth = new Date().getMonth() + 1;
        let currentMonthFormatted = currentMonth < 10 ? '0' + currentMonth : '' + currentMonth;
        const currentMonthCheckbox = form.querySelector('input[name="months[]"][value="' + currentMonthFormatted + '"]');
        if (currentMonthCheckbox) currentMonthCheckbox.checked = true;
    }

    // Инициализация выбора года
    function initYearSelect() {
        const yearSelect = document.getElementById('invoiceYear');
        if (!yearSelect) return;
        
        const currentYear = new Date().getFullYear();
        const startYear = currentYear - 2; // Начинаем с 2 лет назад
        const endYear = currentYear; // Заканчиваем на текущий год (нельзя генерировать за будущий год)
        
        yearSelect.innerHTML = '';
        for (let year = startYear; year <= endYear; year++) {
            const option = document.createElement('option');
            option.value = year;
            option.textContent = year;
            if (year === currentYear) {
                option.selected = true;
            }
            yearSelect.appendChild(option);
        }
    }

    // Установка текущей даты и времени
    function setCurrentDateTime() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const datetimeString = `${year}-${month}-${day}T${hours}:${minutes}`;
        scheduledDateTime.value = datetimeString;
    }

    // Валидация: активировать кнопку только если выбран месяц и тип лица
    function validate() {
        const source = modal.getAttribute('data-source');
        const monthsChecked = form.querySelectorAll('input[name="months[]"]:checked').length > 0;
        let entityChecked;
        if (source === 'single') {
            entityChecked = true;
            btn.disabled = !(monthsChecked && entityChecked);
            return;
        }
        entityChecked = form.querySelectorAll('input[name="entityType[]"]:checked').length > 0;
        const streetChecked = selectedStreets && selectedStreets.length > 0;
        btn.disabled = !(monthsChecked && entityChecked && streetChecked);
    }
    form.addEventListener('change', validate);
    
    // Обработчик изменения года
    const yearSelect = document.getElementById('invoiceYear');
    if (yearSelect) {
        yearSelect.addEventListener('change', function() {
            disableFutureMonths();
            validate();
        });
    }

    // Следим за изменением типа лица
    form.addEventListener('change', function (e) {
        if (e.target.name === 'entityType[]') {
            selectedEntityTypes = Array.from(form.querySelectorAll('input[name="entityType[]"]:checked')).map(cb => cb.value);
        }
    });

    // Скрываем select-ы при открытии модалки
    function hideLocalityStreetSelects() {
        localityDropdownContainer.style.display = 'none';
        streetDropdownContainer.style.display = 'none';
        localityDropdown.innerHTML = '';
        streetDropdown.innerHTML = '';
        selectedLocalities = [];
        selectedStreets = [];
    }
    // Показываем select-ы
    function showLocalityStreetSelects() {
        localityDropdownContainer.style.display = '';
        streetDropdownContainer.style.display = '';
    }
    // Скрыть dropdown-ы
    function hideDropdowns() {
        localityDropdownContainer.style.display = 'none';
        streetDropdownContainer.style.display = 'none';
        localityDropdown.innerHTML = '';
        streetDropdown.innerHTML = '';
        selectedLocalities = [];
        selectedStreets = [];
    }
    // Показать locality dropdown
    function showLocalityDropdown() {
        localityDropdownContainer.style.display = '';
    }
    // Показать street dropdown
    function showStreetDropdown() {
        if (modal.getAttribute('data-source') === 'bulk') {
            streetDropdownContainer.style.display = 'block';
        }
    }
    // Удалено: любой html-вывод в estateStreetsContainer и подобные div/ul-списки населённых пунктов и улиц

    // --- Компактные dropdown-ы ---
    function renderDropdown(container, items, selected, onChange, options = {}) {
        // options: { selectAll: true, labelId: '...' }
        let labelId = options.labelId || '';
        let selectAllBtn = '';
        if (options.selectAll) {
            selectAllBtn = `<button type="button" id="selectAllBtn_${labelId}" style="float:right;margin-left:8px;margin-bottom:0;">Выбрать все</button>`;
        }
        // Вставляем кнопку на уровень с label, если labelId передан
        if (labelId && options.selectAll) {
            const label = document.getElementById(labelId);
            if (label && !document.getElementById(`selectAllBtn_${labelId}`)) {
                label.insertAdjacentHTML('beforeend', selectAllBtn);
            }
        }
        container.innerHTML = `<div style="max-height:180px;overflow-y:auto;border:1px solid #e0e0e0;border-radius:4px;padding:6px 8px;background:#fafbfc;">
        ${items.map(item =>
            `<label style="display:block;margin-bottom:2px;cursor:pointer;font-size:0.98em;">
                <input type="checkbox" value="${item.value}" ${selected.includes(item.value) ? 'checked' : ''} style="margin-right:6px;">
                ${item.label}
            </label>`
        ).join('')}
    </div>`;
        // Вешаем обработчик
        Array.from(container.querySelectorAll('input[type=checkbox]')).forEach(cb => {
            cb.addEventListener('change', function () {
                const checked = Array.from(container.querySelectorAll('input[type=checkbox]:checked')).map(i => i.value);
                onChange(checked);
                // Обновить текст кнопки при ручном выборе
                if (options.selectAll && labelId) {
                    updateSelectAllBtnText(container, labelId);
                }
            });
        });
        // Обработчик для toggle "Выбрать все/Убрать выделенное"
        if (options.selectAll && labelId) {
            const btn = document.getElementById(`selectAllBtn_${labelId}`);
            if (btn) {
                btn.onclick = function () {
                    const checkboxes = Array.from(container.querySelectorAll('input[type=checkbox]'));
                    const allChecked = checkboxes.length && checkboxes.every(cb => cb.checked);
                    checkboxes.forEach(cb => cb.checked = !allChecked);
                    const checked = Array.from(container.querySelectorAll('input[type=checkbox]:checked')).map(i => i.value);
                    onChange(checked);
                    updateSelectAllBtnText(container, labelId);
                };
                // Сразу обновить текст при рендере
                updateSelectAllBtnText(container, labelId);
            }
        }
    }

    function updateSelectAllBtnText(container, labelId) {
        const btn = document.getElementById(`selectAllBtn_${labelId}`);
        if (!btn) return;
        const checkboxes = Array.from(container.querySelectorAll('input[type=checkbox]'));
        const allChecked = checkboxes.length && checkboxes.every(cb => cb.checked);
        btn.textContent = allChecked ? 'Убрать выделенное' : 'Выбрать все';
    }

    // Заполняем locality dropdown
    function fillLocalityDropdown(data) {
        allLocalityData = data;
        const items = data.map(loc => ({ value: loc.locality, label: `${loc.locality} (${loc.count})` }));
        renderDropdown(localityDropdown, items, selectedLocalities, function (checked) {
            // Если убрали галочку с locality — убираем улицы, которых больше нет
            const removed = selectedLocalities.filter(x => !checked.includes(x));
            if (removed.length > 0) {
                // Собираем все улицы, которые остались в выбранных localities
                let validStreets = [];
                allLocalityData.forEach(loc => {
                    if (checked.includes(loc.locality)) {
                        validStreets = validStreets.concat(loc.streets.filter(st => st.name && st.name.trim() !== "").map(st => st.name));
                    }
                });
                selectedStreets = selectedStreets.filter(st => validStreets.includes(st));
            }
            selectedLocalities = checked;
            if (selectedLocalities.length > 0) {
                fillStreetDropdown();
                // ЯВНО показываем блок
                streetDropdownContainer.style.display = 'block';
            } else {
                streetDropdownContainer.style.display = 'none';
                streetDropdown.innerHTML = '';
            }
        }, { selectAll: true, labelId: 'localityDropdownLabel' });
        showLocalityDropdown();
        // Сброс dropdown улиц
        streetDropdown.innerHTML = '';
        streetDropdownContainer.style.display = 'none';
        selectedStreets = [];
    }
    // Заполняем street dropdown по выбранным localities
    function fillStreetDropdown() {
        if (modal.getAttribute('data-source') === 'single') {
            streetDropdownContainer.style.display = 'none';
            return;
        }
        let streets = [];
        allLocalityData.forEach(loc => {
            if (selectedLocalities.includes(loc.locality)) {
                streets = streets.concat(loc.streets.filter(st => st.name && st.name.trim() !== ""));
            }
        });
        const uniqueStreets = {};
        streets.forEach(st => {
            if (!st.name) return;
            if (!uniqueStreets[st.name]) uniqueStreets[st.name] = 0;
            uniqueStreets[st.name] += st.count;
        });
        const items = Object.entries(uniqueStreets).map(([name, count]) => ({ value: name, label: `${name} (${count})` }));
        renderDropdown(streetDropdown, items, selectedStreets, function (checked) {
            selectedStreets = checked;
            validate();
        }, { selectAll: true, labelId: 'streetDropdownLabel' });
        showStreetDropdown();
    }

    // Функции для работы с очередью задач
    function addTaskToQueue(task) {
        taskQueue.push(task);
        updateTaskQueueDisplay();
        showTaskQueueSection();
        
        // Обновляем данные из Redis после добавления задачи
        setTimeout(() => {
            loadTasksFromRedis();
        }, 500); // Небольшая задержка, чтобы задача успела добавиться в Redis
    }

    function updateTaskQueueDisplay() {
        taskQueueTableBody.innerHTML = '';
        
        // Группируем задачи по типу лица, месяцам и времени запуска
        const groupedTasks = {};
        
        taskQueue.forEach(task => {
            const entityType = task.entityTypes[0] || 'Неизвестно';
            const months = task.months.join(', ');
            const scheduledTime = task.scheduledTime || 'Немедленно';
            const status = task.status;
            const queueType = task.queueType || 'progress';
            
            const key = `${entityType}|${months}|${scheduledTime}|${status}|${queueType}`;
            
            if (!groupedTasks[key]) {
                groupedTasks[key] = {
                    entityType: entityType,
                    months: months,
                    scheduledTime: scheduledTime,
                    status: status,
                    queueType: queueType,
                    count: 0,
                    totalProcessed: 0,
                    totalTasks: 0,
                    tasks: [] // Сохраняем ссылки на задачи для удаления
                };
            }
            
            groupedTasks[key].count += (task.count || 1);
            groupedTasks[key].totalProcessed += (task.processed || 0);
            groupedTasks[key].totalTasks += (task.total || 1);
            groupedTasks[key].tasks.push(task);
        });
        
        // Отображаем сгруппированные задачи
        Object.values(groupedTasks).forEach(group => {
            const row = document.createElement('tr');
            const entityTypeIcon = getEntityTypeIcon(group.entityType);
            const monthsShort = group.months.split(', ').map(m => getMonthShort(m)).join(', ');
            const progress = group.totalTasks > 0 ? Math.round((group.totalProcessed / group.totalTasks) * 100) : 0;
            
            // Добавляем информацию об ошибке, если есть
            let errorInfo = '';
            if (group.status === 'Ошибка' && group.tasks.length > 0 && group.tasks[0].error) {
                errorInfo = `<br><small class="text-danger">${group.tasks[0].error}</small>`;
            }
            
            row.innerHTML = `
                <td>
                    <span class="badge badge-${entityTypeIcon.color}">
                        <i class="${entityTypeIcon.icon}"></i> ${group.entityType}
                    </span>
                </td>
                <td>${monthsShort}</td>
                <td>${formatDateTime(group.scheduledTime)}</td>
                <td>${group.count}</td>
                <td>
                    <span class="badge badge-${getStatusBadge(group.status)}">${group.status}</span>
                    ${errorInfo}
                </td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar" role="progressbar" style="width: ${progress}%">
                            ${progress}%
                        </div>
                    </div>
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteTaskGroup('${group.queueType}', ${JSON.stringify(group.tasks.map(t => t.objectID)).replace(/"/g, '&quot;')})" title="Удалить все задачи этой группы">
                        Удалить
                    </button>
                </td>
            `;
            taskQueueTableBody.appendChild(row);
        });
    }

    function getEntityTypeIcon(entityType) {
        switch(entityType) {
            case 'Физ. лицо':
                return { icon: 'fas fa-user', color: 'primary' };
            case 'Юр. лицо':
                return { icon: 'fas fa-building', color: 'info' };
            default:
                return { icon: 'fas fa-question', color: 'secondary' };
        }
    }

    function getMonthShort(month) {
        const monthMap = {
            'Январь': 'Янв', 'Февраль': 'Фев', 'Март': 'Мар',
            'Апрель': 'Апр', 'Май': 'Май', 'Июнь': 'Июн',
            'Июль': 'Июл', 'Август': 'Авг', 'Сентябрь': 'Сен',
            'Октябрь': 'Окт', 'Ноябрь': 'Ноя', 'Декабрь': 'Дек'
        };
        return monthMap[month] || month.substring(0, 3);
    }

    function formatDateTime(dateTime) {
        if (!dateTime) return 'Немедленно';
        const date = new Date(dateTime);
        return date.toLocaleString('ru-RU', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function getStatusBadge(status) {
        switch(status) {
            case 'Ожидает': return 'warning';
            case 'В процессе': return 'info';
            case 'Завершено': return 'success';
            case 'Ошибка': return 'danger';
            case 'Запланировано': return 'info';
            case 'Повтор': return 'warning';
            default: return 'secondary';
        }
    }

    // Функция для удаления группы задач (глобальная)
    window.deleteTaskGroup = function(queueType, objectIds) {
        console.log('deleteTaskGroup вызвана с параметрами:', { queueType, objectIds, userId });
        
        if (!userId) {
            console.error('User ID не найден:', userId);
            alert('Ошибка: не получен ID пользователя');
            return;
        }
        
        if (!Array.isArray(objectIds) || objectIds.length === 0) {
            console.error('Некорректные objectIds:', objectIds);
            alert('Нет задач для удаления');
            return;
        }
        
        const confirmMessage = `Вы уверены, что хотите удалить ${objectIds.length} задач из очереди "${queueType}"?`;
        if (!confirm(confirmMessage)) {
            console.log('Пользователь отменил удаление');
            return;
        }
        
        console.log('Начинаем удаление задач...');
        
        // Удаляем каждую задачу
        let deletedCount = 0;
        let errorCount = 0;
        
        const deletePromises = objectIds.map(objectId => {
            console.log(`Отправляем запрос на удаление задачи ${objectId} из очереди ${queueType}`);
            
            return fetch('/redis-invoices/invoiceModal.php', {
                credentials: 'include',
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'deleteTask',
                    userID: userId,
                    taskId: objectId,
                    queueType: queueType
                })
            })
            .then(res => {
                console.log(`Ответ сервера для задачи ${objectId}:`, res.status);
                return res.json();
            })
            .then(data => {
                console.log(`Данные ответа для задачи ${objectId}:`, data);
                if (data.status === 'success') {
                    deletedCount++;
                    console.log(`Задача ${objectId} успешно удалена`);
                } else {
                    errorCount++;
                    console.error('Ошибка удаления задачи:', data.message);
                }
            })
            .catch(error => {
                errorCount++;
                console.error('Ошибка при удалении задачи:', error);
            });
        });
        
        Promise.all(deletePromises).then(() => {
            if (deletedCount > 0) {
                alert(`Удалено задач: ${deletedCount}${errorCount > 0 ? `, ошибок: ${errorCount}` : ''}`);
                // Перезагружаем список задач
                loadTasksFromRedis();
            } else {
                alert('Не удалось удалить ни одной задачи');
            }
        });
    };


    function calculateProgress(task) {
        if (!task.processed) return 0;
        if (!task.total) return 100;
        return Math.round((task.processed / task.total) * 100);
    }

    function showTaskQueueSection() {
        if (taskQueue.length > 0) {
            taskQueueSection.style.display = 'block';
            updateTaskQueueCount();
        }
    }

    function hideTaskQueueSection() {
        taskQueueSection.style.display = 'none';
    }

    function updateTaskQueueCount() {
        const totalTasks = taskQueue.length;
        taskQueueCount.textContent = totalTasks;
        
        if (totalTasks > 0) {
            taskQueueCount.className = 'badge badge-info';
        } else {
            taskQueueCount.className = 'badge badge-secondary';
        }
    }

    function toggleTaskQueueDisplay() {
        const isVisible = taskQueueTableContainer.style.display !== 'none';
        const icon = toggleTaskQueue.querySelector('i');
        
        if (isVisible) {
            taskQueueTableContainer.style.display = 'none';
            icon.className = 'fas fa-chevron-down';
            toggleTaskQueue.innerHTML = '<i class="fas fa-chevron-down"></i> Показать';
        } else {
            taskQueueTableContainer.style.display = 'block';
            icon.className = 'fas fa-chevron-up';
            toggleTaskQueue.innerHTML = '<i class="fas fa-chevron-up"></i> Скрыть';
        }
    }

    // Функция для запуска автообновления модального окна
    function startModalAutoRefresh() {
        if (modalRefreshInterval) {
            clearInterval(modalRefreshInterval);
        }
        modalRefreshInterval = setInterval(loadTasksFromRedis, 2000); // каждые 2 секунды
    }
    
    // Функция для остановки автообновления модального окна
    function stopModalAutoRefresh() {
        if (modalRefreshInterval) {
            clearInterval(modalRefreshInterval);
            modalRefreshInterval = null;
        }
    }

    // Функция для загрузки задач из Redis
    function loadTasksFromRedis() {
        if (!userId) {
            console.log('User ID не получен, пропускаем загрузку задач');
            return;
        }
        
        // Добавляем индикатор загрузки
        if (taskQueueCount) {
            taskQueueCount.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }
        
        fetch('/redis-invoices/invoiceModal.php', {
            credentials: 'include',
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'getTasks',
                userID: userId
            })
        })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                
                // Проверяем, что ответ действительно JSON
                const contentType = res.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server returned non-JSON response');
                }
                
                return res.json();
            })
            .then(data => {
                console.log('Загружены задачи из Redis:', data);
                
                if (data.status === 'success') {
                    // Очищаем текущую очередь
                    taskQueue = [];
                    
                    // Добавляем задачи из всех очередей
                    if (data.tasks.progress && data.tasks.progress.length > 0) {
                        data.tasks.progress.forEach(taskData => {
                            const task = {
                                months: taskData.months || [],
                                entityTypes: taskData.entityTypes || [],
                                scheduledTime: taskData.scheduledTime || null,
                                count: taskData.total || 1,
                                status: taskData.status || 'Ожидает',
                                processed: taskData.processed || 0,
                                total: taskData.total || 1,
                                objectID: taskData.objectID,
                                queueType: 'progress'
                            };
                            taskQueue.push(task);
                        });
                    }
                    
                    if (data.tasks.scheduled && data.tasks.scheduled.length > 0) {
                        data.tasks.scheduled.forEach(taskData => {
                            const task = {
                                months: taskData.months || [],
                                entityTypes: taskData.entityTypes || [],
                                scheduledTime: taskData.scheduledTime || null,
                                count: 1,
                                status: 'Запланировано',
                                processed: 0,
                                total: 1,
                                objectID: taskData.objectID,
                                queueType: 'scheduled'
                            };
                            taskQueue.push(task);
                        });
                    }
                    
                    if (data.tasks.retry && data.tasks.retry.length > 0) {
                        data.tasks.retry.forEach(taskData => {
                            const task = {
                                months: taskData.months || [],
                                entityTypes: taskData.entityTypes || [],
                                scheduledTime: taskData.scheduledTime || null,
                                count: 1,
                                status: 'Повтор',
                                processed: 0,
                                total: 1,
                                objectID: taskData.objectID,
                                queueType: 'retry'
                            };
                            taskQueue.push(task);
                        });
                    }
                    
                    if (data.tasks.error && data.tasks.error.length > 0) {
                        data.tasks.error.forEach(taskData => {
                            const task = {
                                months: taskData.months || [],
                                entityTypes: taskData.entityTypes || [],
                                scheduledTime: taskData.scheduledTime || null,
                                count: 1,
                                status: 'Ошибка',
                                processed: 0,
                                total: 1,
                                objectID: taskData.objectID,
                                queueType: 'error',
                                error: taskData.error || 'Неизвестная ошибка',
                                errorTime: taskData.error_time || null
                            };
                            taskQueue.push(task);
                        });
                    }
                    
                    // Обновляем отображение
                    if (taskQueue.length > 0) {
                        updateTaskQueueDisplay();
                        showTaskQueueSection();
                    } else {
                        hideTaskQueueSection();
                    }
                } else {
                    console.error('Ошибка загрузки задач:', data.message);
                    hideTaskQueueSection();
                }
            })
            .catch(error => {
                console.error('Ошибка при загрузке задач из Redis:', error);
                hideTaskQueueSection();
            });
    }


    // 1. Открытие модалки — только сброс, никаких fetch
    $('#estateInvoiceModal').on('show.bs.modal', function (e) {
        hideDropdowns();
        form.reset();
        initYearSelect();
        disableFutureMonths();
        selectCurrentMonth();
        setCurrentDateTime();
        validate();
        selectedLocalities = [];
        selectedStreets = [];
        selectedEntityTypes = [];
        allLocalityData = [];
        taskQueue = [];
        hideTaskQueueSection();
        
        // Получаем userId из атрибута модального окна
        getUserId();
        
        // Загружаем задачи из Redis
        setTimeout(() => {
            loadTasksFromRedis();
        }, 100); // Небольшая задержка, чтобы userId успел загрузиться
        
        // Запускаем автообновление
        startModalAutoRefresh();
        
        // Добавляем обработчик для кнопки сворачивания очереди
        if (toggleTaskQueue) {
            toggleTaskQueue.addEventListener('click', toggleTaskQueueDisplay);
        }

        const button = e.relatedTarget;
        console.log('Кнопка, которая открыла модальное окно:', button);
        if (button) {
            const source = button.getAttribute('data-source');
            const entityType = button.getAttribute('data-entity-type');
            const recordId = button.getAttribute('data-record-id');
            console.log('Атрибуты кнопки:', { source, entityType, recordId });
            // ... другие параметры
            modal.setAttribute('data-source', source || '');
            modal.setAttribute('data-record-id', recordId || '');
            console.log('data-source установлен в:', modal.getAttribute('data-source'));

            if (source === 'single') {
                // Ставим большой размер, если надо
                modalDialog.classList.add('modal-lg');
                if (modalTitle) modalTitle.textContent = 'Только для этого дома';

                // Автоматически выбираем тип лица
                if (entityType === 'Физ. лицо' || entityType === 'Юр. лицо') {
                    form.querySelectorAll('input[name="entityType[]"]').forEach(cb => {
                        cb.checked = (cb.value === entityType);
                        cb.disabled = true;
                    });
                } else {
                    // Если тип не определён — снимаем всё и блокируем
                    form.querySelectorAll('input[name="entityType[]"]').forEach(cb => {
                        cb.checked = false;
                        cb.disabled = true;
                    });
                }
                // НИКАКИХ fetch!
                // Не показываем населённые пункты и улицы вообще
                hideDropdowns();
                streetDropdownContainer.style.display = 'none'; // <-- явно скрыть
                validate(); // Вызываем validate для single
            } else {
                // bulk: разблокируем чекбоксы типа лица
                modalDialog.classList.add('modal-lg');
                if (modalTitle) modalTitle.textContent = 'Генерация счетов для всех выбранных домов';
                form.querySelectorAll('input[name="entityType[]"]').forEach(cb => {
                    cb.disabled = false;
                    cb.checked = false;
                });
            }
        }
    });

    // Обработчик закрытия модального окна
    $('#estateInvoiceModal').on('hidden.bs.modal', function (e) {
        // Останавливаем автообновление при закрытии модального окна
        stopModalAutoRefresh();
    });

    // 2. Обработчик на тип лица — только он делает fetch
    form.addEventListener('change', function (e) {
        console.log('Событие change:', e.target.name, e.target.value, e.target.checked);
        if (e.target.name === 'entityType[]') {
            // fetch только если НЕ single
            const source = modal.getAttribute('data-source');
            if (source === 'single') return; // ничего не делаем!
            selectedEntityTypes = Array.from(form.querySelectorAll('input[name="entityType[]"]:checked')).map(cb => cb.value);
            console.log('Выбранные типы лиц:', selectedEntityTypes);
            if (selectedEntityTypes.length) {
                // Получаем userId из глобальной переменной или из атрибута модального окна
                const userId = window.userId || modal.getAttribute('data-user-id');
                console.log('User ID из атрибута модального окна:', modal.getAttribute('data-user-id'));
                console.log('User ID из глобальной переменной:', window.userId);
                console.log('Используемый User ID:', userId);
                console.log('Отправляем запрос с данными:', { userID: userId, entityTypes: selectedEntityTypes });
                
                if (!userId) {
                    console.error('User ID не получен, не можем отправить запрос');
                    localityDropdown.innerHTML = '<span style="color:#c00;">Ошибка: не получен ID пользователя</span>';
                    return;
                }
                
                fetch('/redis-invoices/invoiceModal.php', {
                    credentials: 'include',
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ userID: userId, entityTypes: selectedEntityTypes })
                })
                .then(res => {
                    console.log('Ответ сервера получен:', res);
                    console.log('Статус ответа:', res.status);
                    if (!res.ok) {
                        throw new Error(`HTTP error! status: ${res.status}`);
                    }
                    
                    // Проверяем, что ответ действительно JSON
                    const contentType = res.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('Server returned non-JSON response');
                    }
                    
                    return res.json();
                })
                    .then(data => {
                        console.log('Данные от сервера:', data);
                        console.log('Тип данных:', typeof data);
                        console.log('Длина данных:', Array.isArray(data) ? data.length : 'не массив');
                        
                        if (Array.isArray(data) && data.length) {
                            console.log('Заполняем dropdown населенных пунктов');
                            fillLocalityDropdown(data);
                            localityDropdown.dispatchEvent(new Event('change'));
                        } else {
                            console.log('Данные пустые или не массив');
                            localityDropdown.innerHTML = '<span style="color:#c00;">Нет данных для отображения</span>';
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка при загрузке:', error);
                        localityDropdown.innerHTML = '<span style="color:#c00;">Ошибка загрузки: ' + error.message + '</span>';
                    });
            } else {
                hideDropdowns();
            }
        }
    });

    btn.addEventListener('click', function () {
        const selectedMonths = Array.from(form.querySelectorAll('input[name="months[]"]:checked')).map(el => el.value);
        const monthsNames = ["Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"];
        const selectedMonthsNames = selectedMonths.map(month => monthsNames[parseInt(month) - 1]);
        const yearSelect = document.getElementById('invoiceYear');
        const selectedYear = yearSelect ? parseInt(yearSelect.value) : new Date().getFullYear();
        const userId = modal.getAttribute('data-user-id');
        const source = modal.getAttribute('data-source');
        const recordId = modal.getAttribute('data-record-id');
        const scheduledTime = scheduledDateTime.value;

        // Типы лица
        let selectedEntityTypes;
        if (source === 'single') {
            // В single тип лица всегда выбран и заблокирован, ищем отмеченный чекбокс
            selectedEntityTypes = Array.from(form.querySelectorAll('input[name="entityType[]"]')).filter(cb => cb.checked).map(cb => cb.value);
        } else {
            selectedEntityTypes = Array.from(form.querySelectorAll('input[name="entityType[]"]:checked')).map(cb => cb.value);
        }

        // Проверки
        if (!selectedMonthsNames.length) {
            alert('Выберите хотя бы один месяц!');
            return;
        }
        if (!selectedEntityTypes.length) {
            alert('Выберите хотя бы один тип лица!');
            return;
        }
        if (source === 'bulk' && (!selectedStreets || !selectedStreets.length)) {
            alert('Выберите хотя бы одну улицу!');
            return;
        }

        // bulk: получаем id объектов по выбранным улицам и типу лица
        if (source === 'bulk') {
            fetch('/redis-invoices/invoiceModal.php', {
                credentials: 'include',
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'getObjectIds',
                    userID: userId,
                    entityTypes: selectedEntityTypes,
                    localities: selectedLocalities,
                    streets: selectedStreets
                })
            })
                .then(res => res.json())
                .then(objectIds => {
                    console.log('Полученные ID объектов:', objectIds);
                    fetch('/redis-invoices/invoiceModal.php', {
                        credentials: 'include',
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'enqueueInvoiceTask',
                            months: selectedMonthsNames,
                            year: selectedYear,
                            entityTypes: selectedEntityTypes,
                            userID: userId,
                            source: source,
                            objectIds: objectIds,
                            scheduledTime: scheduledTime
                        })
                    })
                        // .then(res => res.json())
                        // .then(data => {
                        //     if (data.status === 'ok') {
                        //         alert('Задачи поставлены в очередь!');
                        //     }
                        // });
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'ok') {
                                // Добавляем задачу в локальную очередь
                                const task = {
                                    months: selectedMonthsNames,
                                    entityTypes: selectedEntityTypes,
                                    scheduledTime: scheduledTime,
                                    count: objectIds.length,
                                    status: 'Ожидает',
                                    processed: 0,
                                    total: objectIds.length
                                };
                                addTaskToQueue(task);
                                alert(`Задача добавлена в очередь! Обработано объектов: ${objectIds.length}`);
                            }
                        })
                        .catch(error => {
                            console.error('Ошибка:', error);
                            alert('Ошибка при добавлении задачи в очередь');
                        });
                });
        } else {
            fetch('/redis-invoices/invoiceModal.php', {
                credentials: 'include',
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'enqueueInvoiceTask',
                    months: selectedMonthsNames,
                    year: selectedYear,
                    entityTypes: selectedEntityTypes,
                    userID: userId,
                    source: source,
                    recordId: recordId,
                    scheduledTime: scheduledTime
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ok') {
                        // Добавляем задачу в локальную очередь
                        const task = {
                            months: selectedMonthsNames,
                            entityTypes: selectedEntityTypes,
                            scheduledTime: scheduledTime,
                            count: 1,
                            status: 'Ожидает',
                            processed: 0,
                            total: 1
                        };
                        addTaskToQueue(task);
                        alert('Задача добавлена в очередь!');
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    alert('Ошибка при добавлении задачи в очередь');
                });
        }
    });
}); 