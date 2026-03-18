<!-- layouts/v7/modules/CustomModules/CustomModuleHeader/estateInvoice/invoiceModal.tpl -->
<div class="modal fade estate-invoice-modal" id="estateInvoiceModal" tabindex="-1" role="dialog"
    aria-labelledby="estateInvoiceModalLabel" aria-hidden="true" data-user-id="{$CURRENT_USER_MODEL->getId()}">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content estate-invoice-modal-content">
            <div class="modal-header estate-invoice-modal-header">
                <h5 class="modal-title" id="estateInvoiceModalLabel">Генерация счетов для объектов недвижимости</h5>
                <button type="button" class="close estate-invoice-modal-close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body estate-invoice-modal-body">
                <form id="generateEstateInvoicesForm">
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

                    <div class="form-group estate-invoice-modal-group" id="localityDropdownContainer" style="margin-bottom: 18px; display: none;">
                        <label>Населённые пункты:</label>
                        <div id="localityDropdown" class="custom-multiselect-dropdown"></div>
                    </div>
                    <div class="form-group estate-invoice-modal-group" id="streetDropdownContainer" style="margin-bottom: 18px; display: none;">
                        <label>Улицы:</label>
                        <div id="streetDropdown" class="custom-multiselect-dropdown"></div>
                    </div>

                    <div class="form-group estate-invoice-modal-group">
                        <label>Тип лица:</label>
                        <div class="estate-invoice-modal-checkboxes" id="estateEntityTypeCheckboxes">
                            <!-- чекбоксы будут добавляться JS-ом -->
                        </div>
                    </div>
                    <div class="form-group text-center estate-invoice-modal-group">
                        <button type="button" class="btn btn-primary estate-invoice-modal-btn"
                            id="startEstateInvoiceGenerationButton" disabled>Запустить генерацию</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<link rel="stylesheet" href="layouts/v7/modules/CustomModules/CustomModuleHeader/estateInvoice/invoiceModal.css">
<script src="layouts/v7/modules/CustomModules/CustomModuleHeader/estateInvoice/invoiceModal.js"></script>