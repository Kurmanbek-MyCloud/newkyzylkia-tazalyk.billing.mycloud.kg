// JS для модального окна генерации счетов для объектов недвижимости

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('estateInvoiceModal');
    const form = document.getElementById('generateEstateInvoicesForm');
    const btn = document.getElementById('startEstateInvoiceGenerationButton');
    const entityTypeContainer = document.getElementById('estateEntityTypeCheckboxes');
    const modalDialog = modal.querySelector('.modal-dialog');
    const modalTitle = modal.querySelector('#estateInvoiceModalLabel');
    const selectsBlock = document.getElementById('localityStreetSelects');
    const localitySelect = document.getElementById('localitySelect');
    const streetSelect = document.getElementById('streetSelect');
    const localityDropdownContainer = document.getElementById('localityDropdownContainer');
    const streetDropdownContainer = document.getElementById('streetDropdownContainer');
    const localityDropdown = document.getElementById('localityDropdown');
    const streetDropdown = document.getElementById('streetDropdown');
    let allLocalityData = [];
    let selectedLocalities = [];
    let selectedStreets = [];

    // Удалено: создание и использование streetsContainer и html-вывод div/ul-списков населённых пунктов и улиц

    // Добавляем чекбоксы типов лиц (можно заменить на динамику)
    entityTypeContainer.innerHTML = `
        <label><input type="checkbox" name="entityType[]" value="individual"> Физ. лицо</label><br>
        <label><input type="checkbox" name="entityType[]" value="legalEntity"> Юр. лицо</label>
    `;

    // Блокировка будущих месяцев
    function disableFutureMonths() {
        const currentMonth = new Date().getMonth() + 1;
        const monthCheckboxes = form.querySelectorAll('input[name="months[]"]');
        monthCheckboxes.forEach(function (checkbox) {
            const monthValue = parseInt(checkbox.value);
            checkbox.disabled = monthValue > currentMonth;
        });
    }
    // Автовыбор текущего месяца
    function selectCurrentMonth() {
        const currentMonth = new Date().getMonth() + 1;
        let currentMonthFormatted = currentMonth < 10 ? '0' + currentMonth : '' + currentMonth;
        const currentMonthCheckbox = form.querySelector('input[name="months[]"][value="' + currentMonthFormatted + '"]');
        if (currentMonthCheckbox) currentMonthCheckbox.checked = true;
    }

    // Валидация: активировать кнопку только если выбран месяц и тип лица
    function validate() {
        const monthsChecked = form.querySelectorAll('input[name="months[]"]:checked').length > 0;
        const entityChecked = form.querySelectorAll('input[name="entityType[]"]:checked').length > 0;
        btn.disabled = !(monthsChecked && entityChecked);
    }
    form.addEventListener('change', validate);

    // Скрываем select-ы при открытии модалки
    function hideLocalityStreetSelects() {
        selectsBlock.style.display = 'none';
        localitySelect.innerHTML = '';
        streetSelect.innerHTML = '';
        localitySelect.disabled = true;
        streetSelect.disabled = true;
    }
    // Показываем select-ы
    function showLocalityStreetSelects() {
        selectsBlock.style.display = '';
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
        streetDropdownContainer.style.display = '';
    }
    // Удалено: любой html-вывод в estateStreetsContainer и подобные div/ul-списки населённых пунктов и улиц

    // --- Компактные dropdown-ы ---
    function renderDropdown(container, items, selected, onChange) {
        container.innerHTML = `<div style="max-height:180px;overflow-y:auto;border:1px solid #e0e0e0;border-radius:4px;padding:6px 8px;background:#fafbfc;">
            ${items.map(item =>
            `<label style=\"display:block;margin-bottom:2px;cursor:pointer;font-size:0.98em;\">
                    <input type=\"checkbox\" value=\"${item.value}\" ${selected.includes(item.value) ? 'checked' : ''} style=\"margin-right:6px;\">
                    ${item.label}
                </label>`
        ).join('')}
        </div>`;
        // Вешаем обработчик
        Array.from(container.querySelectorAll('input[type=checkbox]')).forEach(cb => {
            cb.addEventListener('change', function () {
                const checked = Array.from(container.querySelectorAll('input[type=checkbox]:checked')).map(i => i.value);
                onChange(checked);
            });
        });
    }
    // Заполняем locality dropdown
    function fillLocalityDropdown(data) {
        allLocalityData = data;
        const items = data.map(loc => ({ value: loc.locality, label: `${loc.locality} (${loc.count})` }));
        renderDropdown(localityDropdown, items, selectedLocalities, function (checked) {
            selectedLocalities = checked;
            fillStreetDropdown();
        });
        showLocalityDropdown();
        // Сброс dropdown улиц
        streetDropdown.innerHTML = '';
        streetDropdownContainer.style.display = 'none';
        selectedStreets = [];
    }
    // Заполняем street dropdown по выбранным localities
    function fillStreetDropdown() {
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
        console.log('items for streets:', items);
        renderDropdown(streetDropdown, items, selectedStreets, function (checked) {
            selectedStreets = checked;
        });
        showStreetDropdown();
    }
    // При изменении месяцев — показываем locality dropdown и запрашиваем данные
    form.addEventListener('change', function (e) {
        const monthsChecked = form.querySelectorAll('input[name="months[]"]:checked').length > 0;
        if (monthsChecked) {
            if (allLocalityData.length) {
                fillLocalityDropdown(allLocalityData);
            } else {
                const userId = modal.getAttribute('data-user-id');
                fetch('/layouts/v7/modules/CustomModules/CustomModuleHeader/estateInvoice/invoiceModal.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ userID: userId })
                })
                    .then(res => res.json())
                    .then(data => {
                        fillLocalityDropdown(data);
                    })
                    .catch(() => {
                        localityDropdown.innerHTML = '<span style="color:#c00;">Ошибка загрузки</span>';
                    });
            }
        } else {
            hideDropdowns();
        }
    });
    // При открытии модалки — скрываем dropdown-ы
    $('#estateInvoiceModal').on('show.bs.modal', function (e) {
        hideDropdowns();
        // Сброс формы и стандартные действия
        form.reset();
        disableFutureMonths();
        selectCurrentMonth();
        validate();

        // Получаем параметры из кнопки, которая открыла модалку
        const button = e.relatedTarget;
        if (button) {
            const source = button.getAttribute('data-source');
            const recordId = button.getAttribute('data-record-id');
            const entityType = button.getAttribute('data-entity-type');

            // Сохраняем в data-атрибуты модалки (если нужно использовать при отправке)
            modal.setAttribute('data-source', source || '');
            modal.setAttribute('data-record-id', recordId || '');
            modal.setAttribute('data-entity-type', entityType || '');

            // Меняем заголовок и размер окна
            if (source === 'single') {
                if (modalDialog.classList.contains('modal-sm')) modalDialog.classList.remove('modal-sm');
                modalDialog.classList.add('modal-lg');
                if (modalTitle) modalTitle.textContent = 'Только для этого дома';
                if (entityType === 'individual') {
                    form.querySelector('input[name="entityType[]"][value="individual"]').checked = true;
                    form.querySelector('input[name="entityType[]"][value="legalEntity"]').checked = false;
                } else if (entityType === 'legalEntity') {
                    form.querySelector('input[name="entityType[]"][value="legalEntity"]').checked = true;
                    form.querySelector('input[name="entityType[]"][value="individual"]').checked = false;
                }
                // Блокируем чекбоксы типа лица
                form.querySelectorAll('input[name="entityType[]"]').forEach(cb => cb.disabled = true);
                // Очищаем контейнер улиц
                // streetsContainer.innerHTML = ''; // Удалено
            } else {
                modalDialog.classList.remove('modal-sm');
                modalDialog.classList.add('modal-lg');
                if (modalTitle) modalTitle.textContent = 'Генерация счетов для всех выбранных домов';
                // bulk: разблокируем и снимаем все галочки
                form.querySelectorAll('input[name="entityType[]"]').forEach(cb => {
                    cb.disabled = false;
                    cb.checked = false;
                });
                // Получаем userId и делаем ajax-запрос за улицами
                const userId = modal.getAttribute('data-user-id');
                console.log('userId из data-user-id:', userId);
                // streetsContainer.innerHTML = '<span style="color:#888;">Загрузка улиц...</span>'; // Удалено
                fetch('/layouts/v7/modules/CustomModules/CustomModuleHeader/estateInvoice/invoiceModal.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ userID: userId })
                })
                    .then(res => res.json())
                    .then(data => {
                        console.log('Ответ с улицами:', data);
                        // или временно так:
                        // alert('Ответ с улицами: ' + JSON.stringify(data));
                        if (Array.isArray(data) && data.length) {
                            // data — массив [{locality, count, streets: [{name, count}, ...]}, ...]
                            // Заполняем список населённых пунктов
                            fillLocalityDropdown(data);

                            // Триггерим обновление улиц при первой загрузке (если нужно)
                            localityDropdown.dispatchEvent(new Event('change'));

                            // let html = ''; // Удалено
                            // data.forEach((loc, i) => { // Удалено
                            //     html += ` // Удалено
                            //     <div style="margin-bottom:12px;"> // Удалено
                            //         <div style="cursor:pointer;font-weight:600;" onclick="document.getElementById('streets_${i}').classList.toggle('hidden')"> // Удалено
                            //             ${loc.locality} <span style="color:#1976d2;">(${loc.count})</span> // Удалено
                            //         </div> // Удалено
                            //         <ul id="streets_${i}" style="margin:6px 0 0 18px;padding:0;"> // Удалено
                            //             ${loc.streets // Удалено
                            //             .filter(st => st.name) // Удалено
                            //             .map(st => `<li>${st.name} <span style="color:#555;">(${st.count})</span></li>`) // Удалено
                            //             .join('') // Удалено
                            //         } // Удалено
                            //         </ul> // Удалено
                            //     </div>`; // Удалено
                            // }); // Удалено
                            // streetsContainer.innerHTML = html; // Удалено
                        } else {
                            // streetsContainer.innerHTML = '<span style="color:#c00;">Улицы не найдены</span>'; // Удалено
                        }
                    })
                    .catch(() => {
                        // streetsContainer.innerHTML = '<span style="color:#c00;">Ошибка загрузки улиц</span>'; // Удалено
                    });
            }
        }
    });

    btn.addEventListener('click', function () {
        const selectedMonths = Array.from(form.querySelectorAll('input[name="months[]"]:checked')).map(el => el.value);
        const entityTypes = Array.from(form.querySelectorAll('input[name="entityType[]"]:checked')).map(el => el.value);
        const monthsNames = ["Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"];
        const entityTypesMap = { "individual": "Физ. лицо", "legalEntity": "Юр. лицо" };
        const selectedMonthsNames = selectedMonths.map(month => monthsNames[parseInt(month) - 1]);
        const selectedEntityTypesNames = entityTypes.map(type => entityTypesMap[type]);
        if (!selectedMonthsNames.length) {
            alert('Выберите хотя бы один месяц!');
            return;
        }
        if (!selectedEntityTypesNames.length) {
            alert('Выберите хотя бы один тип лица!');
            return;
        }
        const avarageKWT = parseFloat(((700 * 12) / 365).toFixed(4));
        const userId = modal.getAttribute('data-user-id');
        const source = modal.getAttribute('data-source');
        const recordId = modal.getAttribute('data-record-id');
        const confirmGeneration = confirm(
            "Вы выбрали месяцы: " + selectedMonthsNames.join(', ') +
            ". Типы лица: " + selectedEntityTypesNames.join(', ') +
            ". Подтвердить запуск генерации?"
        );
        if (!confirmGeneration) return;
        const requestData = {
            months: selectedMonthsNames,
            entityTypes: selectedEntityTypesNames,
            avarageKWT: avarageKWT,
            userID: userId,
            source: source,
        };
        if (source === 'single' && recordId) {
            requestData.recordId = recordId;
        }
        const selectedLocalities = Array.from(localitySelect.selectedOptions).map(opt => opt.value);
        const selectedStreets = Array.from(streetSelect.selectedOptions).map(opt => opt.value);
        console.log('Выбранные населённые пункты:', selectedLocalities);
        console.log('Выбранные улицы:', selectedStreets);
        fetch('https://ktzh.billing.mycloud.kg/createInvoice/createInvoice_electrocity.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json; charset=UTF-8' },
            body: JSON.stringify(requestData)
        })
            .then(response => {
                if (!response.ok) throw new Error('Ошибка при отправке запроса на сервер');
                return response.text();
            })
            .then(result => {
                alert('Генерация запущена!');
            })
            .catch(error => {
                alert('Ошибка: ' + error.message);
            });
    });

    // Инициализация при первой загрузке
    disableFutureMonths();
    selectCurrentMonth();
    validate();
}); 