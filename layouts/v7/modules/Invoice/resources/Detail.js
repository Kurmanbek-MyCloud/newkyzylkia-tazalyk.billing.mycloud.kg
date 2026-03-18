/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

Inventory_Detail_Js("Invoice_Detail_Js", {}, {});


jQuery(document).ready(function () {
    // Обработка кнопки "Рассчитать пени"
    jQuery('#calculatePenalty').on('click', function () {
        var recordId = jQuery('#recordId').val();
        var params = {
            'module': 'Invoice',
            'action': 'CalculatePenalty',
            'record': recordId
        };

        AppConnector.request(params).then(
            function (data) {
                if (data.success) {
                    app.helper.showSuccessNotification(data.result.message);
                    window.location.reload();
                }
            }
        );
    });
});