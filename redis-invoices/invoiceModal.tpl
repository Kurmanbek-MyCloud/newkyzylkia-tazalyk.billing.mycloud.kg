<!-- redis-invoices/invoiceModal.tpl -->
<div class="modal fade estate-invoice-modal" id="estateInvoiceModal" tabindex="-1" role="dialog"
    aria-labelledby="estateInvoiceModalLabel" aria-hidden="true" data-user-id="{$CURRENT_USER_MODEL->getId()}">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content estate-invoice-modal-content">
            <div class="modal-header estate-invoice-modal-header">
                <h5 class="modal-title" id="estateInvoiceModalLabel">Генерация счетов для объектов недвижимости</h5>
                <button type="button" class="close estate-invoice-modal-close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body estate-invoice-modal-body">
                <form id="generateEstateInvoicesForm">
                    <!-- ВЫБОР ГОДА -->
                    <div class="form-group estate-invoice-modal-group">
                        <label>Выберите год:</label>
                        <select class="form-control" id="invoiceYear" name="year" required>
                            <!-- Годы будут добавлены через JS -->
                        </select>
                    </div>
                    <!-- КОНЕЦ блока года -->
                    
                    <!-- ВЫБОР МЕСЯЦЕВ -->
                    <div class="form-group estate-invoice-modal-group">
                        <label>Выберите месяц(ы):</label>
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
                    <!-- КОНЕЦ блока месяцев -->

                    <div class="form-group estate-invoice-modal-group">
                        <label>Тип лица:</label>
                        <div class="estate-invoice-modal-checkboxes" id="estateEntityTypeCheckboxes">
                            <!-- чекбоксы будут добавляться JS-ом -->
                        </div>
                    </div>

                    <!-- ДАТА И ВРЕМЯ ЗАПУСКА -->
                    <div class="form-group estate-invoice-modal-group">
                        <label>Дата и время запуска генерации:</label>
                        <div class="input-group">
                            <input type="datetime-local" class="form-control" id="scheduledDateTime" 
                                   value="" required>
                            <div class="input-group-append">
                                <span class="input-group-text">
                                    <i class="fas fa-calendar-alt"></i>
                                </span>
                            </div>
                        </div>
                        <small class="form-text text-muted">Если не указано, задача выполнится немедленно</small>
                    </div>

                    <!-- ОЧЕРЕДЬ ЗАДАЧ -->
                    <div class="form-group estate-invoice-modal-group" id="taskQueueSection" style="display: none;">
                        <label>
                            Очередь задач: 
                            <span id="taskQueueCount" class="badge badge-info">0</span>
                        </label>
                        <button type="button" class="btn btn-sm btn-primary w-100 mt-2" id="toggleTaskQueue">
                            <i class="fas fa-chevron-down"></i> Показать
                        </button>
                        <div class="table-responsive" id="taskQueueTableContainer" style="display: none;">
                            <table class="table table-sm table-striped" id="taskQueueTable">
                                <thead>
                                    <tr>
                                        <th>Тип</th>
                                        <th>Месяцы</th>
                                        <th>Время запуска</th>
                                        <th>Кол-во</th>
                                        <th>Статус</th>
                                        <th>Прогресс</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody id="taskQueueTableBody">
                                    <!-- Задачи будут добавляться динамически -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="form-group estate-invoice-modal-group" id="localityDropdownContainer" style="margin-bottom: 18px; display: none;">
                        <label id="localityDropdownLabel">Населённые пункты:</label>
                        <div id="localityDropdown" class="custom-multiselect-dropdown"></div>
                    </div>
                    <div class="form-group estate-invoice-modal-group" id="streetDropdownContainer" style="margin-bottom: 18px; display: none;">
                        <label id="streetDropdownLabel">Улицы:</label>
                        <div id="streetDropdown" class="custom-multiselect-dropdown"></div>
                    </div>

                    <div class="form-group text-center estate-invoice-modal-group">
                        <button type="button" class="btn btn-primary estate-invoice-modal-btn"
                            id="startEstateInvoiceGenerationButton">Запустить генерацию</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
<link rel="stylesheet" href="redis-invoices/invoiceModal.css">
<script src="redis-invoices/invoiceModal.js"></script>