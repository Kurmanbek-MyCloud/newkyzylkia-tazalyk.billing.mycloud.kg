<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (ob_get_level()) ob_clean();
ini_set('memory_limit', -1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
date_default_timezone_set('Asia/Bishkek');
set_time_limit(0);
ini_set('max_execution_time', 3600); // 1 час

chdir('../');

require_once 'include/database/PearDatabase.php';
require_once 'libraries/tcpdf/tcpdf.php';
require 'vendor/autoload.php';
require_once 'Logger.php';

$logger = new CustomLogger('PrintPDF/PrintPDF.log');
$logger->log('test');


if (isset($_GET['module'])) {
	if ($_GET['module'] == 'Invoice') {
		if (isset($_GET['selectedIds'])) {
			$selectedIds = $_GET['selectedIds'];
			$idList = json_decode($selectedIds);
			$viewName = $_GET['viewname'];
			$params = $_GET['params'];
			$parsedSearchParams = json_decode($params, true);

			if (is_array($idList)) {
				$groupIds = [];
				foreach ($idList as $id) {
					global $adb;
					$sql = "SELECT ve.cf_municipal_enterprise FROM vtiger_invoice vi
                            INNER JOIN vtiger_crmentity vc on vc.crmid = vi.invoiceid
                            INNER JOIN vtiger_estates ve on ve.estatesid = vi.cf_estate_id
                            INNER JOIN vtiger_crmentity vc2 on vc2.crmid = ve.estatesid
                            WHERE vc.deleted = 0 and vc2.deleted = 0  and vi.invoiceid =  $id";
					$smownerid_result = $adb->run_query_allrecords($sql);

					if (isset($smownerid_result[0]['cf_municipal_enterprise'])) {
						$groupIds[] = $smownerid_result[0]['cf_municipal_enterprise'];
					} else {
						echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Ошибка печати</title></head><body>
						<div style="max-width:520px;margin:80px auto;padding:35px 45px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:12px;text-align:center;font-family:\'Segoe UI\',sans-serif;box-shadow:0 4px 16px rgba(0,0,0,0.08);">
							<div style="font-size:48px;margin-bottom:15px;">&#9888;</div>
							<h2 style="color:#721c24;margin:0 0 15px;font-size:22px;">Объект не найден</h2>
							<p style="color:#721c24;font-size:15px;margin:0 0 20px;line-height:1.6;">У счёта <b>ID ' . htmlspecialchars($id) . '</b> не найден привязанный объект или он не относится ни к одной группе.</p>
							<a href="javascript:history.back()" style="display:inline-block;padding:10px 30px;background:#721c24;color:#fff;border-radius:6px;text-decoration:none;font-size:14px;">Вернуться назад</a>
						</div>
						</body></html>';
						$logger -> log("Объект не найден или не привязан к группе. Invoice ID: $id");
						exit();
					}
				}
				$uniqueGroups = array_unique($groupIds);
				if (count($uniqueGroups) > 1) {
					echo '<div style="max-width:500px;margin:60px auto;padding:30px 40px;background:#fff3cd;border:1px solid #ffeeba;border-radius:10px;text-align:center;font-family:sans-serif;box-shadow:0 2px 12px #0001;">
						<h2 style="color:#856404;margin-bottom:20px;">В списке выбраны счета из разных групп!</h2>
						<p style="color:#856404;font-size:16px;margin-bottom:0;">Пожалуйста, вернитесь назад и выберите счета, относящиеся к одной группе.</p>
					</div>';
					exit();
				}
				$smownerid = $uniqueGroups[0];
				$redirectParams = '?module=Invoice&selectedIds=' . urlencode(json_encode($idList)) . '&viewname=' . urlencode($viewName) . '&params=' . urlencode($params);
				if ($smownerid == 51840) {
					header('Location: /PrintPDF/PrintPDF_for_invoice_ak_emgek.php' . $redirectParams);
					exit();
				// } elseif ($smownerid == 8) {
				// 	header('Location: /PrintPDF/PrintPDF_for_invoice_toru_aygyr_taza_suu.php' . $redirectParams);
				// 	exit();
				// } elseif ($smownerid == 9) {
				// 	header('Location: /PrintPDF/PrintPDF_for_invoice_toru_aygyr_taza_aimak.php' . $redirectParams);
				// 	exit();
				} else {
					echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Ошибка печати</title></head><body>
					<div style="max-width:520px;margin:80px auto;padding:35px 45px;background:#f8d7da;border:1px solid #f5c6cb;border-radius:12px;text-align:center;font-family:\'Segoe UI\',sans-serif;box-shadow:0 4px 16px rgba(0,0,0,0.08);">
						<div style="font-size:48px;margin-bottom:15px;">&#9888;</div>
						<h2 style="color:#721c24;margin:0 0 15px;font-size:22px;">Группа не поддерживается</h2>
						<p style="color:#721c24;font-size:15px;margin:0 0 20px;line-height:1.6;">Объект этого счёта относится к группе <b>' . htmlspecialchars($smownerid) . '</b>, для которой печать PDF ещё не настроена.</p>
						<a href="javascript:history.back()" style="display:inline-block;padding:10px 30px;background:#721c24;color:#fff;border-radius:6px;text-decoration:none;font-size:14px;">Вернуться назад</a>
					</div>
					</body></html>';
					$logger -> log("Группа не поддерживается для печати PDF. Группа: $smownerid");
					exit();
				}
			} else {
				echo '<h2>idList не массив!</h2>';
				exit();
			}
		}
	}
	exit();
}