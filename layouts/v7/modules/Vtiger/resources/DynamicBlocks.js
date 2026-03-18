// Подключено в файле JSResources.tpl
// <script type="text/javascript" src="{vresource_url('layouts/v7/modules/Vtiger/resources/DynamicBlocks.js')}"></script>

/* Отображение динамических полей и блоков при редактировании Объекта 
реализовано в файле Edit.js */

// Динамические блоки и поля при загрузке страницы
$(document).ready(function () {
    function toggleFieldsBasedOnEntity() {
        var entity = $("td#Estates_detailView_fieldValue_cf_object_type > span.value").text().trim();
        var apartmentLabel = $("td#Estates_detailView_fieldLabel_cf_apartment_number");
        var apartmentValue = $("td#Estates_detailView_fieldValue_cf_apartment_number");
        var typeRealEstateLabel = $("td#Estates_detailView_fieldLabel_cf_estate_type");
        var typeRealEstateValue = $("td#Estates_detailView_fieldValue_cf_estate_type");

        // Скрываем все поля и блоки по умолчанию
        apartmentLabel.hide();
        apartmentValue.hide();
        typeRealEstateLabel.hide();
        typeRealEstateValue.hide();
        $('[data-block="LBL_LEGAL_ENTITY_INFORMATION"]').addClass('hide');
        $('[data-block="LBL_OFFICE_INFORMATION"]').addClass('hide');

        // Показываем нужные блоки в зависимости от типа объекта
        if (entity === 'Юр. лицо') {
            $('[data-block="LBL_LEGAL_ENTITY_INFORMATION"]').removeClass('hide');
        } else if (entity === 'Офис') {
            $('[data-block="LBL_OFFICE_INFORMATION"]').removeClass('hide');
        } else if (entity === 'Физ. лицо') {
            // Показываем все поля для физ. лиц
            apartmentLabel.show();
            apartmentValue.show();
            typeRealEstateLabel.show();
            typeRealEstateValue.show();
        }
    }

    // Вызываем функцию при загрузке страницы
    toggleFieldsBasedOnEntity();

    // Инициализация MutationObserver для отслеживания изменений на странице
    const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            if (mutation.attributeName === 'class' && $(mutation.target).data('url').includes('showDetailViewByMode')) {
                toggleFieldsBasedOnEntity();
            }
        });
    });

    // Наблюдение за изменениями в первых двух элементах с классом tab-item
    $('.tab-item').slice(0, 2).each(function () {
        observer.observe(this, { attributes: true });
    });
});