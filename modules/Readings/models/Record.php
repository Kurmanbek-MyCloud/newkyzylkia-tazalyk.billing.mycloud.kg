<?php
class Readings_Record_Model extends Vtiger_Record_Model {

    /**
     * Сброс использования показаний для счета
     * @param int $invoiceId ID счета
     * @return void
     */
    public static function resetReadingsForInvoice($invoiceId) {
        global $adb, $log;

        try {
            $sql = "SELECT prev_reading_id, cur_reading_id
                    FROM vtiger_inventoryproductrel
                    WHERE id = ?
                    AND (prev_reading_id IS NOT NULL OR cur_reading_id IS NOT NULL)";
            $result = $adb->pquery($sql, array($invoiceId));
            $numRows = $adb->num_rows($result);

            for ($i = 0; $i < $numRows; $i++) {
                $prevId = $adb->query_result($result, $i, 'prev_reading_id');
                $curId = $adb->query_result($result, $i, 'cur_reading_id');

                if ($prevId) {
                    $adb->pquery("UPDATE vtiger_readings SET cf_used_in_bill = 0 WHERE readingsid = ?", array($prevId));
                }
                if ($curId) {
                    $adb->pquery("UPDATE vtiger_readings SET cf_used_in_bill = 0 WHERE readingsid = ?", array($curId));
                }
            }
        } catch (Exception $e) {
            $log->debug("Ошибка при сбросе показаний для счета $invoiceId: " . $e->getMessage());
        }
    }
}