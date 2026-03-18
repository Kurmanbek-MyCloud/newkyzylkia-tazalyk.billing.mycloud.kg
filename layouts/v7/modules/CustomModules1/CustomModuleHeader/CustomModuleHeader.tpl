{assign var=userRoleID value=$CURRENT_USER_MODEL->getRole()}
<!-- Кнопка для генерации счетов -->
{*{if $MODULE == 'Invoice' and ($userRoleID == 'H1' or $userRoleID == 'H2' or $userRoleID == 'H7')}*}
{*    <li>*}
{*        *}{* <button type="button" class="btn addButton btn-default module-buttons" data-toggle="modal" data-target="#dateModal">*}
{*            <div class="vicon-invoice" style="font-size:18px; font-family: sans-serif" aria-hidden="true">Создать счета</div>*}
{*        </button> *}
{*        <button type="button" class="btn addButton btn-default module-buttons" data-toggle="modal" data-target="#dateModal" data-source="bulk" data-record-id="">*}
{*            <div class="vicon-invoice" style="font-size:18px; font-family: sans-serif" aria-hidden="true">Создать счета электр.</div>*}
{*        </button>*}
{*    </li>*}
{*{/if}*}
{* {if $MODULE == 'Invoice' and ($userRoleID == 'H1' or $userRoleID == 'H2' or $userRoleID == 'H6')}
<li>
    <button type="button" class="btn addButton btn-default module-buttons" data-toggle="modal" data-target="#telephonyModal" data-source="bulk" data-record-id="">
        <div class="vicon-invoice" style="font-size:18px; font-family: sans-serif" aria-hidden="true">Создать счета телеф.</div>
    </button>
</li>
    {include file="layouts/v7/modules/CustomModules/CustomModuleHeader/telephonyInvoices/modalTelephony.tpl"}
{/if} *}
<!-- Кнопка для Обработки звонков -->
{*{if $MODULE == 'Calls' and ($userRoleID == 'H1' or $userRoleID == 'H2' or $userRoleID == 'H6')}*}
{*    <li>*}
{*        <button id="openModalButton" type="button"*}
{*                class="btn addButton btn-default module-buttons"*}
{*                data-toggle="modal"*}
{*                data-target="#processCallsModal"*}
{*                data-userid="{$CURRENT_USER_MODEL->getId()}">*}
{*            <div class="vicon-phonecalls" style="font-size:17px; font-family: sans-serif;" aria-hidden="true">*}
{*                Обработать звонки*}
{*            </div>*}
{*        </button>*}
{*    </li>*}
{*    {include file="layouts/v7/modules/CustomModules/CustomModuleHeader/processCalls/index.php"}*}
{*{/if}*}

{* Подключаем файл, где реализовано модальное окно и серверная логика *}

<!-- Модальное окно для генерации счетов -->
<div class="modal fade" id="dateModal" tabindex="-1" role="dialog" aria-labelledby="dateModalLabel" aria-hidden="true" data-source="" data-record-id="">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <!-- Заголовок модального окна -->
            <div class="modal-header">
                <h5 class="modal-title" id="dateModalLabel" style="width:100%;text-align:center;font-size:2.2rem;">Генерировать для всех домов</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!-- Тело модального окна -->
            <div class="modal-body">
                <form id="generateInvoicesForm">
                    <!-- Выбор месяцев -->
                    <div class="form-group">
                        <label>Выберите месяц(ы):</label>
                        <i class="fa fa-info-circle"
                           style="margin-left:8px;cursor:pointer;color:#2196f3;"
                           data-toggle="tooltip"
                           data-placement="right"
                           title="Если у дома уже есть счет за определенный месяц, то счет не будет генерироваться во избежании дубликатов. Нужно поменять месяц в старом счете и обратно сгенерировать счет.">
                        </i>
                        <div class="row">
                            <div class="col-sm-6">
                                <label><input type="checkbox" name="months[]" value="01"> Январь</label><br>
                                <label><input type="checkbox" name="months[]" value="02"> Февраль</label><br>
                                <label><input type="checkbox" name="months[]" value="03"> Март</label><br>
                                <label><input type="checkbox" name="months[]" value="04"> Апрель</label><br>
                                <label><input type="checkbox" name="months[]" value="05"> Май</label><br>
                                <label><input type="checkbox" name="months[]" value="06"> Июнь</label>
                            </div>
                            <div class="col-sm-6">
                                <label><input type="checkbox" name="months[]" value="07"> Июль</label><br>
                                <label><input type="checkbox" name="months[]" value="08"> Август</label><br>
                                <label><input type="checkbox" name="months[]" value="09"> Сентябрь</label><br>
                                <label><input type="checkbox" name="months[]" value="10"> Октябрь</label><br>
                                <label><input type="checkbox" name="months[]" value="11"> Ноябрь</label><br>
                                <label><input type="checkbox" name="months[]" value="12"> Декабрь</label>
                            </div>
                        </div>
                    </div>

                    <!-- Выбор типа лица -->
                    <div class="form-group">
                        <label>Тип лица:</label>
                        <div>
                            <input type="checkbox" id="individual" name="entityType[]" value="individual">
                            <label for="individual">Физ. лицо</label>
                        </div>
                        <div>
                            <input type="checkbox" id="legalEntity" name="entityType[]" value="legalEntity">
                            <label for="legalEntity">Юр. лицо</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="wateringQuantity">Количество полива:</label>
                        <input type="number" id="wateringQuantity" name="wateringQuantity" class="form-control" min="0" step="0.01">
                    </div>



                    <!-- Кнопка Запустить генерацию -->
                    <div class="form-group text-center">
                        <button type="button" class="btn btn-primary" id="startGenerationButton">Запустить генерацию</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Скрипт для Модального окна для генерации счетов -->
<script>
    $('#dateModal').on('show.bs.modal', function (e) {
        const button = $(e.relatedTarget);
        const source = button.data('source');
        const recordId = button.data('record-id');
        const entityType = button.data('entity-type');
        const $header = $(this).find('.modal-header');
        const $title = $(this).find('#dateModalLabel');

        // Сохраняем source и recordId в атрибутах модального окна
        $(this).attr('data-source', source);
        $(this).attr('data-record-id', recordId);

        // Управляем чекбоксами типов
        if (source === 'single') {
            if (entityType === 'individual') {
                $('#individual').prop('checked', true);
                $('#legalEntity').prop('checked', false);
                $title.text('Только для этого дома');
                $header.css({
                    'background-color': '#e3f2fd', // мягкий голубой
                    'color': '#1a237e'             // тёмно-синий
                });
            } else if (entityType === 'legalEntity') {
                $('#legalEntity').prop('checked', true);
                $('#individual').prop('checked', false);
            }
            // Блокируем изменение типа для single
            $('#individual, #legalEntity').prop('disabled', true);
        } else {
            // Для bulk разрешаем выбор и снимаем все галочки
            $('#individual, #legalEntity').prop('disabled', false);
            $('#individual, #legalEntity').prop('checked', false);
        }

        // При открытии из карточки объекта:
        console.log('source',$('#dateModal').attr('data-source')); // Должно быть 'single'
        console.log($('#dateModal').attr('data-record-id')); // Должно содержать ID
    });

    document.getElementById('startGenerationButton').addEventListener('click', function () {
        const selectedMonths = Array.from(document.querySelectorAll('input[name="months[]"]:checked')).map(el => el.value);
        const entityTypes = Array.from(document.querySelectorAll('input[name="entityType[]"]:checked')).map(el => el.value);
        // Получаем source и recordId из самого модального окна
        const wateringQuantity = document.getElementById('wateringQuantity').value;
        const modal = document.getElementById('dateModal');
        const requestSource = modal.getAttribute('data-source');
        const recordId = modal.getAttribute('data-record-id');

        // Массив с именами месяцев
        const monthsNames = ["Январь", "Февраль", "Март", "Апрель", "Май", "Июнь",
            "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"];

        // Маппинг для типов лиц
        const entityTypesMap = {
            "individual": "Физ. лицо",
            "legalEntity": "Юр. лицо"
        };

        const selectedMonthsNames = selectedMonths.map(month => monthsNames[parseInt(month) - 1]);
        const selectedEntityTypesNames = entityTypes.map(type => entityTypesMap[type]);

        if (selectedMonthsNames.length === 0) {
            alert('Выберите хотя бы один месяц!');
            return;
        }

        if (selectedEntityTypesNames.length === 0) {
            alert('Выберите хотя бы один тип лица!');
            return;
        }


        const confirmGeneration = confirm(
            "Вы выбрали месяцы: " + selectedMonthsNames.join(', ') +
            ". Типы лица: " + selectedEntityTypesNames.join(', ') +
            ". Подтвердить запуск генерации?"
        );

        if (confirmGeneration) {
            const userID = '{$CURRENT_USER_MODEL->getId()}';

            const requestData = {
                months: selectedMonthsNames,
                entityTypes: selectedEntityTypesNames,
                userID: userID,
                wateringQuantity: wateringQuantity,
                source: requestSource // Добавляем источник запроса
            };

            // Добавляем recordId только если это single генерация
            if (requestSource === 'single' && recordId) {
                requestData.recordId = recordId;
            }

            fetch('https://ruvkh.billing.mycloud.kg/createInvoice/createInvoice.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json; charset=UTF-8'
                },
                body: JSON.stringify(requestData)
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Ошибка при отправке запроса на сервер');
                    }
                    return response.text();
                })
                .then(result => {
                    console.log(result);
                    alert('Генерация запущена!');
                })
                .catch(error => {
                    alert('Ошибка: ' + error.message);
                });
        }
    });

    // Функция для блокировки будущих месяцев
    function disableFutureMonths() {
        const currentMonth = new Date().getMonth() + 1; // Получаем текущий месяц (от 0 до 11, добавляем 1 для удобства)
        const monthCheckboxes = document.querySelectorAll('input[name="months[]"]');

        monthCheckboxes.forEach(function(checkbox) {
            const monthValue = parseInt(checkbox.value);
            if (monthValue > currentMonth) {
                checkbox.disabled = true; // Блокируем чекбокс, если месяц будущий
            } else {
                checkbox.disabled = false; // Разблокируем для прошедших месяцев
            }
        });
    }

    // Функция для автоматического выбора текущего месяца
    function selectCurrentMonth() {
        const currentMonth = new Date().getMonth() + 1; // Получаем текущий месяц (от 0 до 11, добавляем 1 для удобства)

        // Формируем месяц в двухзначном формате
        let currentMonthFormatted = currentMonth;
        if (currentMonth < 10) {
            currentMonthFormatted = '0' + currentMonth; // Добавляем ведущий ноль
        }

        // Получаем соответствующий чекбокс
        const currentMonthCheckbox = document.querySelector('input[name="months[]"][value="' + currentMonthFormatted + '"]');
        if (currentMonthCheckbox) {
            currentMonthCheckbox.checked = true; // Устанавливаем текущий месяц как выбранный
        }
    }

    // Вызываем функции при загрузке страницы
    window.onload = function() {
        disableFutureMonths(); // Блокируем будущие месяцы
        selectCurrentMonth();  // Автоматически выбираем текущий месяц
    };
</script>

{* <script>
document.addEventListener("DOMContentLoaded", function() {
    const openModalButton = document.getElementById('openModalButton');
    openModalButton.addEventListener('click', function() {
        // Проверяем, загружено ли модальное окно
        if (!document.getElementById('processCallsModal')) {
            fetch('layouts/v7/modules/CustomModules/CustomModuleHeader/processCalls/index.php')
                .then(response => response.text())
                .then(html => {
                    // Вставляем HTML модального окна в конец body
                    document.body.insertAdjacentHTML('beforeend', html);
                    // Открываем модальное окно, если используется Bootstrap
                    $('#processCallsModal').modal('show');
                })
                .catch(err => console.error('Ошибка загрузки модального окна:', err));
        } else {
            // Если модальное окно уже загружено, просто открываем его
            $('#processCallsModal').modal('show');
        }
    });
});
</script> *}