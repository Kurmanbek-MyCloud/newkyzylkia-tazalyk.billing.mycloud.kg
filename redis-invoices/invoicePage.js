// Упрощенный JS для страницы генерации счетов

document.addEventListener('DOMContentLoaded', function () {
    const config = window.pageConfig || {};
    const userId = config.userId;
    const source = config.source || 'bulk';
    const recordId = config.recordId || '';
    const entityType = config.entityType || '';
    
    const form = document.getElementById('generateEstateInvoicesForm');
    const btn = document.getElementById('startEstateInvoiceGenerationButton');
    const pageTitle = document.getElementById('pageTitle');
    
    let selectedLocalities = [];
    let selectedStreets = [];
    let selectedEntityTypes = [];
    let allLocalityData = [];
    let allTasks = [];
    let groupedTasks = [];
    let currentPage = 1;
    const tasksPerPage = 10;
    let refreshInterval;
    let selectedObjects = [];
    let foundObjects = [];
    let generationMode = 'bulk';
    let searchTimeout = null;
    
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

    // Инициализация
    function init() {
        initYearSelect();
        
        const yearSelect = document.getElementById('invoiceYear');
        const selectedYear = yearSelect ? parseInt(yearSelect.value) : new Date().getFullYear();
        const currentYear = new Date().getFullYear();
        const currentMonth = new Date().getMonth() + 1;
        
        form.querySelectorAll('input[name="months[]"]').forEach(cb => {
            // Блокируем будущие месяцы только если выбран текущий год
            if (selectedYear === currentYear) {
                if (parseInt(cb.value) > currentMonth) cb.disabled = true;
                if (parseInt(cb.value) === currentMonth) cb.checked = true;
            } else {
                cb.disabled = false; // Разблокируем все месяцы для прошлых/будущих лет
            }
        });
        
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('scheduledDateTime').value = now.toISOString().slice(0, 16);
        
        // Обработка переключателя режимов
        form.querySelectorAll('input[name="generationMode"]').forEach(radio => {
            radio.addEventListener('change', function() {
                generationMode = this.value;
                toggleGenerationMode();
            });
        });
        
        // Если пришли с source=single, устанавливаем режим "для конкретного объекта"
        if (source === 'single' && recordId) {
            document.getElementById('modeSingle').checked = true;
            generationMode = 'single';
            toggleGenerationMode();
            // Автоматически добавляем объект в выбранные
            selectedObjects = [recordId];
        } else {
            generationMode = 'bulk';
            toggleGenerationMode();
        }
        
        // Обработчик поиска объектов
        const searchInput = document.getElementById('objectSearchInput');
        const searchBtn = document.getElementById('searchObjectsBtn');
        
        searchBtn.addEventListener('click', searchObjects);
        
        // Автодополнение при вводе текста
        searchInput.addEventListener('input', function(e) {
            const query = this.value.trim();
            
            // Очищаем предыдущий таймер
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            // Если введено меньше 2 символов, скрываем список
            if (query.length < 2) {
                document.getElementById('objectsListContainer').style.display = 'none';
                return;
            }
            
            // Запускаем поиск с задержкой (debounce)
            searchTimeout = setTimeout(() => {
                performSearch(query, false);
            }, 300);
        });
        
        // Показываем список при клике на поле (если пустое - показываем все)
        searchInput.addEventListener('focus', function() {
            const query = this.value.trim();
            if (query.length >= 2) {
                performSearch(query, false);
            } else if (query.length === 0) {
                // Показываем последние объекты или все объекты
                performSearch('', true);
            }
        });
        
        // Поиск по Enter
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }
                searchObjects();
            }
        });
        
        // Скрываем список при клике вне поля
        document.addEventListener('click', function(e) {
            const container = document.getElementById('objectSearchContainer');
            if (container && !container.contains(e.target)) {
                // Не скрываем, если клик был на элемент списка
                const listContainer = document.getElementById('objectsListContainer');
                if (listContainer && !listContainer.contains(e.target)) {
                    // Можно скрыть, но лучше оставить видимым для удобства
                }
            }
        });
        
        loadTasks();
        refreshInterval = setInterval(loadTasks, 3000);
        
        form.addEventListener('change', validate);
        form.addEventListener('change', onEntityTypeChange);
        
        // Обработчик изменения года
        const yearSelectElement = document.getElementById('invoiceYear');
        if (yearSelectElement) {
            yearSelectElement.addEventListener('change', function() {
                const selectedYear = parseInt(this.value);
                const currentYear = new Date().getFullYear();
                const currentMonth = new Date().getMonth() + 1;
                
                form.querySelectorAll('input[name="months[]"]').forEach(cb => {
                    if (selectedYear === currentYear) {
                        const monthValue = parseInt(cb.value);
                        cb.disabled = monthValue > currentMonth;
                    } else {
                        cb.disabled = false;
                    }
                });
                validate();
            });
        }
        
        btn.addEventListener('click', onSubmit);
        document.getElementById('prevPage').addEventListener('click', () => changePage(-1));
        document.getElementById('nextPage').addEventListener('click', () => changePage(1));
    }
    
    function toggleGenerationMode() {
        const isBulk = generationMode === 'bulk';
        
        // Показываем/скрываем соответствующие блоки
        document.getElementById('objectSearchContainer').style.display = isBulk ? 'none' : 'block';
        document.getElementById('localityDropdownContainer').style.display = isBulk ? 'block' : 'none';
        document.getElementById('streetDropdownContainer').style.display = isBulk ? 'block' : 'none';
        document.getElementById('entityTypeContainer').style.display = isBulk ? 'block' : 'none';
        
        // Очищаем выбранные объекты при переключении на массовую генерацию
        if (isBulk) {
            selectedObjects = [];
            foundObjects = [];
            document.getElementById('objectSearchInput').value = '';
            document.getElementById('objectsListContainer').style.display = 'none';
            document.getElementById('selectedObjectsInfo').style.display = 'none';
        } else {
            // При переключении на режим конкретного объекта очищаем выбор населённых пунктов
            selectedLocalities = [];
            selectedStreets = [];
            document.getElementById('localityDropdown').innerHTML = '';
            document.getElementById('streetDropdown').innerHTML = '';
        }
        
        validate();
    }
    
    function performSearch(query, showAll = false) {
        const container = document.getElementById('objectsList');
        container.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="spinner-border spinner-border-sm" role="status"></i> Поиск...</div>';
        document.getElementById('objectsListContainer').style.display = 'block';
        
        // Если показываем все объекты, используем специальный запрос
        const searchQuery = showAll ? '' : query;
        
        fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'searchObjects',
                userID: userId,
                query: searchQuery,
                showAll: showAll
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.objects && data.objects.length > 0) {
                foundObjects = data.objects;
                renderObjectsList();
            } else {
                container.innerHTML = '<div style="text-align: center; padding: 20px; color: #999;">Объекты не найдены</div>';
            }
        })
        .catch(err => {
            console.error('Ошибка поиска:', err);
            container.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;">Ошибка поиска</div>';
        });
    }
    
    function searchObjects() {
        const searchQuery = document.getElementById('objectSearchInput').value.trim();
        if (!searchQuery || searchQuery.length < 2) {
            alert('Введите минимум 2 символа для поиска');
            return;
        }
        
        performSearch(searchQuery, false);
    }
    
    function renderObjectsList() {
        const container = document.getElementById('objectsList');
        container.innerHTML = '';
        
        foundObjects.forEach(obj => {
            const isSelected = selectedObjects.includes(obj.id);
            const item = document.createElement('div');
            item.className = 'object-item';
            item.innerHTML = `
                <input type="checkbox" value="${obj.id}" ${isSelected ? 'checked' : ''}>
                <div class="object-item-info">
                    <div class="object-item-title">${obj.estateNumber || 'Без ЛС'}</div>
                    <div class="object-item-details">
                        ${obj.ownerName || 'Владелец не указан'} | 
                        ${obj.address || 'Адрес не указан'} | 
                        ${obj.objectType || ''}
                    </div>
                </div>
            `;
            
            const checkbox = item.querySelector('input');
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    if (!selectedObjects.includes(obj.id)) {
                        selectedObjects.push(obj.id);
                    }
                } else {
                    selectedObjects = selectedObjects.filter(id => id !== obj.id);
                }
                updateSelectedObjectsInfo();
                validate();
            });
            
            container.appendChild(item);
        });
    }
    
    function updateSelectedObjectsInfo() {
        const infoEl = document.getElementById('selectedObjectsInfo');
        if (selectedObjects.length > 0) {
            infoEl.textContent = `Выбрано объектов: ${selectedObjects.length}`;
            infoEl.style.display = 'block';
        } else {
            infoEl.style.display = 'none';
        }
    }
    
    function validate() {
        const months = form.querySelectorAll('input[name="months[]"]:checked').length > 0;
        
        if (generationMode === 'single') {
            // Для конкретного объекта: нужны месяцы и выбранные объекты
            btn.disabled = !(months && selectedObjects.length > 0);
        } else {
            // Массовая генерация: нужны месяцы, тип лица и улицы
            const entities = form.querySelectorAll('input[name="entityType[]"]:checked').length > 0;
            const streets = selectedStreets.length > 0;
            btn.disabled = !(months && entities && streets);
        }
    }
    
    function onEntityTypeChange(e) {
        if (e.target.name !== 'entityType[]' || source === 'single') return;
        
        selectedEntityTypes = Array.from(form.querySelectorAll('input[name="entityType[]"]:checked')).map(cb => cb.value);
        
        if (selectedEntityTypes.length === 0) {
            document.getElementById('localityDropdownContainer').style.display = 'none';
            document.getElementById('streetDropdownContainer').style.display = 'none';
            return;
        }
        
        // Показываем индикатор загрузки
        const container = document.getElementById('localityDropdown');
        container.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="spinner-border spinner-border-sm" role="status"></i> Загрузка данных...</div>';
        document.getElementById('localityDropdownContainer').style.display = 'block';
        
        fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ userID: userId, entityTypes: selectedEntityTypes })
        })
        .then(res => res.json())
        .then(data => {
            if (Array.isArray(data) && data.length) {
                allLocalityData = data;
                renderLocalities();
            } else {
                container.innerHTML = '<div style="text-align: center; padding: 20px; color: #999;">Нет данных</div>';
            }
        })
        .catch(err => {
            console.error('Ошибка:', err);
            container.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;">Ошибка загрузки</div>';
        });
    }
    
    function renderLocalities() {
        const container = document.getElementById('localityDropdown');
        container.innerHTML = '';
        
        allLocalityData.forEach(loc => {
            const label = document.createElement('label');
            label.innerHTML = `<input type="checkbox" value="${loc.locality}"> <span>${loc.locality} <strong>(${loc.count})</strong></span>`;
            const checkbox = label.querySelector('input');
            checkbox.addEventListener('change', function() {
                selectedLocalities = Array.from(container.querySelectorAll('input:checked')).map(cb => cb.value);
                updateSelectAllButton();
                renderStreets();
                validate();
            });
            container.appendChild(label);
        });
        
        document.getElementById('localityDropdownContainer').style.display = 'block';
        updateSelectAllButton();
        
        // Добавляем обработчик для кнопки "Выбрать всё"
        setupSelectAllLocalitiesButton();
    }
    
    function setupSelectAllLocalitiesButton() {
        const selectAllBtn = document.getElementById('selectAllLocalities');
        const container = document.getElementById('localityDropdown');
        
        if (!selectAllBtn || !container) return;
        
        // Удаляем все существующие обработчики, создавая новую кнопку
        const newBtn = selectAllBtn.cloneNode(true);
        selectAllBtn.parentNode.replaceChild(newBtn, selectAllBtn);
        
        // Добавляем новый обработчик
        newBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const checkboxes = container.querySelectorAll('input[type="checkbox"]');
            if (checkboxes.length === 0) return;
            
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            const newState = !allChecked;
            
            checkboxes.forEach(cb => {
                cb.checked = newState;
                // Вызываем событие change для обновления состояния
                const event = new Event('change', { bubbles: true });
                cb.dispatchEvent(event);
            });
        });
    }
    
    function updateSelectAllButton() {
        const container = document.getElementById('localityDropdown');
        const selectAllBtn = document.getElementById('selectAllLocalities');
        if (!container || !selectAllBtn) return;
        
        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
        const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
        
        if (allChecked) {
            selectAllBtn.innerHTML = '<i class="fas fa-square"></i> Снять выделение';
        } else {
            selectAllBtn.innerHTML = '<i class="fas fa-check-square"></i> Выбрать всё';
        }
    }
    
    function renderStreets() {
        const container = document.getElementById('streetDropdown');
        container.innerHTML = '';
        
        const streets = {};
        allLocalityData.forEach(loc => {
            if (selectedLocalities.includes(loc.locality)) {
                loc.streets.forEach(st => {
                    if (st.name && st.name.trim()) {
                        streets[st.name] = (streets[st.name] || 0) + st.count;
                    }
                });
            }
        });
        
        Object.entries(streets).forEach(([name, count]) => {
            const label = document.createElement('label');
            label.innerHTML = `<input type="checkbox" value="${name}"> <span>${name} <strong>(${count})</strong></span>`;
            const checkbox = label.querySelector('input');
            checkbox.addEventListener('change', function() {
                selectedStreets = Array.from(container.querySelectorAll('input:checked')).map(cb => cb.value);
                updateSelectAllStreetsButton();
                validate();
            });
            container.appendChild(label);
        });
        
        document.getElementById('streetDropdownContainer').style.display = selectedLocalities.length > 0 ? 'block' : 'none';
        updateSelectAllStreetsButton();
        
        // Добавляем обработчик для кнопки "Выбрать всё" для улиц
        setupSelectAllStreetsButton();
    }
    
    function setupSelectAllStreetsButton() {
        const selectAllBtn = document.getElementById('selectAllStreets');
        const container = document.getElementById('streetDropdown');
        
        if (!selectAllBtn || !container) return;
        
        // Удаляем все существующие обработчики, создавая новую кнопку
        const newBtn = selectAllBtn.cloneNode(true);
        selectAllBtn.parentNode.replaceChild(newBtn, selectAllBtn);
        
        // Добавляем новый обработчик
        newBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const checkboxes = container.querySelectorAll('input[type="checkbox"]');
            if (checkboxes.length === 0) return;
            
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            const newState = !allChecked;
            
            checkboxes.forEach(cb => {
                cb.checked = newState;
                // Вызываем событие change для обновления состояния
                const event = new Event('change', { bubbles: true });
                cb.dispatchEvent(event);
            });
        });
    }
    
    function updateSelectAllStreetsButton() {
        const container = document.getElementById('streetDropdown');
        const selectAllBtn = document.getElementById('selectAllStreets');
        if (!container || !selectAllBtn) return;
        
        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
        const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
        
        if (allChecked) {
            selectAllBtn.innerHTML = '<i class="fas fa-square"></i> Снять выделение';
        } else {
            selectAllBtn.innerHTML = '<i class="fas fa-check-square"></i> Выбрать всё';
        }
    }
    
    function onSubmit() {
        const months = Array.from(form.querySelectorAll('input[name="months[]"]:checked')).map(cb => cb.value);
        const monthsNames = ["Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"];
        const selectedMonthsNames = months.map(m => monthsNames[parseInt(m) - 1]);
        
        const yearSelect = document.getElementById('invoiceYear');
        const selectedYear = yearSelect ? parseInt(yearSelect.value) : new Date().getFullYear();
        
        const scheduledTime = document.getElementById('scheduledDateTime').value || null;
        
        if (generationMode === 'single') {
            // Генерация для конкретных объектов
            if (selectedObjects.length === 0) {
                alert('Выберите хотя бы один объект');
                return;
            }
            
            // Получаем тип лица из выбранных объектов (из поля objectType)
            const entityTypes = [];
            foundObjects.forEach(obj => {
                if (selectedObjects.includes(obj.id) && obj.objectType && !entityTypes.includes(obj.objectType)) {
                    entityTypes.push(obj.objectType);
                }
            });
            
            // Если не удалось определить тип из объектов, используем выбранный вручную (на случай если объект не найден в списке)
            if (entityTypes.length === 0) {
                const manualTypes = Array.from(form.querySelectorAll('input[name="entityType[]"]:checked')).map(cb => cb.value);
                if (manualTypes.length > 0) {
                    entityTypes.push(...manualTypes);
                } else {
                    alert('Не удалось определить тип лица для выбранных объектов');
                    return;
                }
            }
            
            fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'enqueueInvoiceTask',
                    months: selectedMonthsNames,
                    year: selectedYear,
                    entityTypes: entityTypes,
                    userID: userId,
                    source: 'single',
                    objectIds: selectedObjects,
                    scheduledTime: scheduledTime
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok') {
                    alert(`Задачи добавлены в очередь! (${selectedObjects.length} объектов)`);
                    selectedObjects = [];
                    document.getElementById('objectSearchInput').value = '';
                    document.getElementById('objectsListContainer').style.display = 'none';
                    document.getElementById('selectedObjectsInfo').style.display = 'none';
                    loadTasks();
                } else {
                    alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
                }
            })
            .catch(err => {
                console.error('Ошибка:', err);
                alert('Ошибка при отправке задачи');
            });
        } else {
            // Массовая генерация
            const entityTypes = selectedEntityTypes;
            
            fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'getObjectIds',
                    userID: userId,
                    entityTypes: entityTypes,
                    localities: selectedLocalities,
                    streets: selectedStreets
                })
            })
            .then(res => res.json())
            .then(objectIds => {
                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'enqueueInvoiceTask',
                        months: selectedMonthsNames,
                        year: selectedYear,
                        entityTypes: entityTypes,
                        userID: userId,
                        source: 'bulk',
                        objectIds: objectIds,
                        scheduledTime: scheduledTime
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'ok') {
                        alert('Задачи добавлены в очередь!');
                        loadTasks();
                    } else {
                        alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
                    }
                });
            })
            .catch(err => {
                console.error('Ошибка:', err);
                alert('Ошибка при отправке задачи');
            });
        }
    }
    
    // Загрузка задач из Redis - только из очереди progress со статусом "Ожидает"
    function loadTasks() {
        // Не передаем userID - сервер возьмет его из сессии текущего пользователя
        fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include', // Важно для передачи сессии
            body: JSON.stringify({ action: 'getTasks' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                allTasks = [];
                
                // Показываем только задачи из очереди progress со статусом "Ожидает"
                if (data.tasks.progress && data.tasks.progress.length > 0) {
                    data.tasks.progress.forEach(task => {
                        // Нормализуем статус - убираем пробелы
                        const status = (task.status || '').trim();
                        
                        // СТРОГАЯ фильтрация: только задачи со статусом "Ожидает" или без статуса (пустой)
                        // Исключаем: "Завершено", "В процессе", "Ошибка", "Запланировано", "Повтор"
                        if (status === 'Ожидает' || status === '') {
                            // Дополнительная проверка: если прогресс 100% или больше - это завершенная задача, пропускаем
                            const processed = task.processed || 0;
                            const total = task.total || 1;
                            const progressPercent = total > 0 ? (processed / total) * 100 : 0;
                            
                            // Показываем только если прогресс меньше 100% (не завершено)
                            if (progressPercent < 100) {
                                allTasks.push({
                                    ...task,
                                    queueType: 'progress',
                                    status: 'Ожидает'
                                });
                            }
                        }
                    });
                }
                
                groupTasks();
                renderTasks();
            }
        })
        .catch(err => console.error('Ошибка загрузки задач:', err));
    }
    
    // Группировка задач - группируем только по типу, месяцам и времени
    function groupTasks() {
        const groups = {};
        
        allTasks.forEach(task => {
            const months = Array.isArray(task.months) ? task.months.join(', ') : (task.months || '-');
            const entityType = task.entityTypes?.[0] || '-';
            const scheduledTime = task.scheduledTime || 'Немедленно';
            
            // Группируем только по типу, месяцам и времени
            const key = `${entityType}|${months}|${scheduledTime}`;
            
            if (!groups[key]) {
                groups[key] = {
                    entityType: entityType,
                    months: months,
                    scheduledTime: scheduledTime,
                    status: 'Ожидает', // Все задачи в этой группе имеют статус "Ожидает"
                    queueType: task.queueType,
                    count: 0,
                    total: 0,
                    processed: 0,
                    objectIds: []
                };
            }
            
            groups[key].count += (task.total || 1);
            groups[key].total += (task.total || 1);
            groups[key].processed += (task.processed || 0);
            if (task.objectID) {
                groups[key].objectIds.push(task.objectID);
            }
        });
        
        groupedTasks = Object.values(groups);
    }
    
    // Рендер задач с пагинацией
    function renderTasks() {
        const container = document.getElementById('taskQueueList');
        const countEl = document.getElementById('taskQueueCount');
        const paginationControls = document.getElementById('paginationControls');
        
        // Показываем общее количество задач, а не групп
        const totalTasks = allTasks.length;
        countEl.textContent = totalTasks;
        
        if (groupedTasks.length === 0) {
            container.innerHTML = '<div style="text-align: center; color: #999; padding: 20px;">Нет задач</div>';
            paginationControls.style.display = 'none';
            return;
        }
        
        paginationControls.style.display = 'flex';
        
        const totalPages = Math.ceil(groupedTasks.length / tasksPerPage);
        const start = (currentPage - 1) * tasksPerPage;
        const end = start + tasksPerPage;
        const pageTasks = groupedTasks.slice(start, end);
        
        container.innerHTML = '';
        
        pageTasks.forEach(group => {
            const div = document.createElement('div');
            div.className = 'task-group';
            const progress = group.total > 0 ? Math.round((group.processed / group.total) * 100) : 0;
            const statusColor = getStatusColor(group.status);
            
            div.innerHTML = `
                <div class="task-group-header">
                    ${group.entityType} - ${group.months}
                </div>
                <div class="task-group-details">
                    <div>Время: ${group.scheduledTime}</div>
                    <div>Кол-во: ${group.count}</div>
                    <div>Статус: <span class="badge badge-${statusColor}">${group.status}</span></div>
                    <div>Прогресс: ${progress}%</div>
                    <button class="btn btn-sm btn-danger" style="margin-top: 5px; font-size: 11px;" 
                            onclick="deleteTaskGroup('${group.queueType}', ${JSON.stringify(group.objectIds).replace(/"/g, '&quot;')})">
                        Удалить
                    </button>
                </div>
            `;
            container.appendChild(div);
        });
        
        // Пагинация - показываем информацию о группах
        document.getElementById('paginationInfo').textContent = `Групп: ${start + 1}-${Math.min(end, groupedTasks.length)} из ${groupedTasks.length} (Всего задач: ${totalTasks})`;
        document.getElementById('prevPage').disabled = currentPage === 1;
        document.getElementById('nextPage').disabled = currentPage >= totalPages;
    }
    
    function changePage(direction) {
        const totalPages = Math.ceil(groupedTasks.length / tasksPerPage);
        const newPage = currentPage + direction;
        if (newPage >= 1 && newPage <= totalPages) {
            currentPage = newPage;
            renderTasks();
        }
    }
    
    function getStatusColor(status) {
        const colors = {
            'Ожидает': 'warning',
            'В процессе': 'info',
            'Завершено': 'success',
            'Ошибка': 'danger',
            'Запланировано': 'info',
            'Повтор': 'warning'
        };
        return colors[status] || 'secondary';
    }
    
    // Удаление группы задач
    window.deleteTaskGroup = function(queueType, objectIds) {
        if (!confirm('Удалить эту группу задач?')) return;
        
        let deleted = 0;
        let errors = [];
        const promises = objectIds.map(objectId => {
            return fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'deleteTask',
                    userID: userId, // Отправляем, но сервер будет использовать userId из сессии
                    taskId: objectId,
                    queueType: queueType
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    deleted++;
                } else {
                    errors.push(data.message || 'Ошибка удаления');
                }
            })
            .catch(err => {
                errors.push('Ошибка сети: ' + err.message);
            });
        });
        
        Promise.all(promises).then(() => {
            if (deleted > 0) {
                if (errors.length > 0) {
                    alert(`Удалено задач: ${deleted}. Ошибок: ${errors.length}`);
                } else {
                    alert(`Успешно удалено задач: ${deleted}`);
                }
                loadTasks();
            } else {
                alert('Не удалось удалить задачи. ' + (errors[0] || 'Неизвестная ошибка'));
            }
        });
    };
    
    init();
});
