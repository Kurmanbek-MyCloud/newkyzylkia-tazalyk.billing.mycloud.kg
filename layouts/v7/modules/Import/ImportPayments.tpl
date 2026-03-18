{*<!--
/*********************************************************************************
** The contents of this file are subject to the vtiger CRM Public License Version 1.0
* ("License"); You may not use this file except in compliance with the License
* The Original Code is:  vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
*
********************************************************************************/
-->*}

<div class ="importBlockContainer show" id = "uploadFileContainer">
    <form enctype="multipart/form-data" method="POST" id="importInvoice">
        <table class = "table table-borderless" cellpadding = "30" >
            <tr id="file_type_container" style="height:50px">
                <td>Выберите файл</td>
                <td>
                    <div>
                        <input name="paySystem" hidden value="{$PAY_SISTEMS[0]}">
                        <div class="fileUploadBtn btn btn-primary">
                            <span><i class="fa fa-laptop"></i>Выберите файл</span>
                            <input type="file" name="invoice_file" id="invoice_file" onchange="Vtiger_Import_Js.checkInvoiceFile(event)">
                        </div>
                        <div id="importFileDetails" class="padding10"></div>
                    </div>
                </td>
            </tr>
            <tr>
                <td>Платежная система</td>
                <td>
                    <select class="select2" onchange="$('input[name=paySystem]').val($('option:selected', this).val())">
                        {foreach item=PAY_SISTEM from=$PAY_SISTEMS}
                            <option value="{$PAY_SISTEM}">{$PAY_SISTEM}</option>
                        {/foreach}
                    </select>
                </td>
            </tr>
            <tr>
                <td colspan="2" align="center">
                    <button class="btn btn-success btn-lg invoiceAction" onclick="$(this).blur();Vtiger_Import_Js.uploadInvoiceFile();return false;">Загрузить</button>
                </td>
            </tr>
        </table>
    </form>
</div>
