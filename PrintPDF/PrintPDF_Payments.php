<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Сервис недоступен</title>
  <style>
    :root{--bg:#0f1724;--card:#0b1220;--accent:#fb923c;--muted:#cbd5e1}
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      background: linear-gradient(180deg,var(--bg),#071125 80%);
      color:var(--muted);
      display:flex;
      align-items:center;
      justify-content:center;
      padding:24px;
    }
    .card{
      background:rgba(255,255,255,0.03);
      border-radius:14px;
      padding:28px;
      max-width:720px;
      width:100%;
      box-shadow:0 6px 24px rgba(2,6,23,0.6);
      text-align:center;
      backdrop-filter: blur(6px);
    }
    .icon{width:88px;height:88px;margin:0 auto 8px;display:block}
    h1{margin:6px 0 8px;font-size:22px;color:#fff}
    p{margin:0 0 18px;color:var(--muted);line-height:1.45}
    .meta{font-size:13px;color:rgba(203,213,225,0.75);margin-bottom:18px}
    .actions{display:flex;justify-content:center;margin-top:20px;width:100%;}
    .btn{
      appearance:none;border:0;padding:10px 16px;border-radius:10px;font-weight:600;cursor:pointer;
    }
    .btn-primary{background:linear-gradient(90deg,var(--accent),#ffb86b);color:#081124}
    .btn-ghost{background:transparent;border:1px solid rgba(255,255,255,0.06);color:var(--muted)}
    footer{margin-top:18px;font-size:12px;color:rgba(203,213,225,0.6)}
    @media (max-width:420px){.card{padding:18px}}
  </style>
</head>
<body>
  <div class="card" role="alert">
    <svg class="icon" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <rect x="4" y="4" width="56" height="56" rx="10" fill="#111827" />
      <path d="M32 16v20" stroke="#fb923c" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
      <circle cx="32" cy="44" r="3" fill="#fb923c"/>
    </svg>

    <h1>Недостаточно данных для ПКО</h1>
    <p></div>

    

    
  </div>
</body>
</html>



<?php


// // ini_set('display_errors', 1);
// // error_reporting(E_ALL);
// // ob_clean();
// // ini_set('memory_limit', -1);
// header('Content-Type: text/html; charset=utf-8');
// date_default_timezone_set('Asia/Bishkek');
// set_time_limit(0);

// chdir('../');

// require_once 'include/database/PearDatabase.php';
// require_once 'libraries/tcpdf/tcpdf.php';
// require 'vendor/autoload.php';

// if (isset($_GET['module'])) {

//     if ($_GET['module'] == 'Payments') {

//         if (isset($_GET['selectedIds'])) {
//             $selectedIds = $_GET['selectedIds'];
//             $idList = json_decode($selectedIds);
//             $viewName = $_GET['viewname'];
//             // var_dump($viewName);
//             // exit();
//             // $generator = new Picqer\Barcode\BarcodeGeneratorPNG();

//             $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
//             $pdf->SetAuthor('VTigerCRM - Billing');
//             $pdf->SetTitle('Payments');
//             $pdf->SetPrintHeader(false);
//             $pdf->SetPrintFooter(false);
//             $pdf->SetMargins(-2, 10, 7, 7);
//             $pdf->SetAutoPageBreak(TRUE, 7);
//             $pdf->AddPage();
//             $pdf->SetFont('freemono', '', 12);

//             if ($selectedIds == "all") {
//                 $idList = getIdList($viewName);
//             }
//             foreach ($idList as $id) {
//                 $html = getHtml($id);
//                 $pdf->writeHTML($html);
//             }


//             // New tab
//             // $pdf->Output('Invoices_' . date('YmdHis') . '.pdf', 'I');

//             $pdf->Output('Payments_' . date('YmdHis') . '.pdf', 'I');
//             // $pdf->Output('Invoices_'.date('YmdHis').'.pdf', 'D');
//         }
//     }
// }

// function getIdList($viewName) {
//     global $adb;
//     $idList = [];
//     $ids_sql = "SELECT paymentsid FROM vtiger_payments sp
// 				INNER JOIN vtiger_crmentity crm ON crm.crmid = sp.paymentsid 
// 				WHERE deleted = 0";
//     $ids_result = $adb->run_query_allrecords($ids_sql);
//     foreach ($ids_result as $value) {
//         array_push($idList, $value['paymentsid']);
//     }
//     return $idList;
// }


// function getHtml($paymentsid) {
//     global $adb;

//     $result = $adb->run_query_allrecords(
//         "SELECT 
//         COALESCE(ve.estatesid , cd.contactid, vtg.telegraphid) AS related_object_id,
//         vp.cf_paid_service,
//         COALESCE(ve.cf_lastname , cd.cf_client_name, vtg.name) AS lastname,
//         COALESCE(ve.cf_inhabited_locality) AS area,
//         COALESCE(ve.cf_streets) AS street,
//         vo.organizationname AS org_name,
//         vo.okpo,
//         vp.cf_pay_no,   
//         vp.cf_pay_date,
//         vp.amount
//     FROM vtiger_payments vp
//     INNER JOIN vtiger_crmentity vc ON vp.paymentsid = vc.crmid
//     LEFT JOIN vtiger_estates ve ON vp.cf_paid_object = ve.estatesid
//     LEFT JOIN vtiger_contactdetails cd ON vp.cf_paid_object = cd.contactid 
//     LEFT JOIN vtiger_telegraph vtg ON vp.cf_paid_object = vtg.telegraphid
//     JOIN vtiger_organizationdetails vo 
//     WHERE ve.estatesid IS NOT NULL 
//       OR cd.contactid IS NOT NULL 
//       OR vtg.telegraphid IS NOT NULL
//     AND vp.paymentsid = $paymentsid");
//     $row = $result[0];

//     $org_name = $row['org_name'];
//     $org_inn = $row['org_inn'];
//     $org_address = $row['org_address'];
//     $org_logo = $row['logoname'];
//     $lastname = $row['lastname'];
//     $okpo = $row['okpo'];
//     $pay_no = $row['cf_pay_no'];
//     $pay_date = $row['cf_pay_date'];
//     $amount = number_format($row['amount'], 0);
//     $amount = str_replace(',', '', $amount);
//     $flat = $row['flat'];
//     $apartment = $row['apartment'];
//     $area = $row['area'];
//     $street = $row['street'];
//     $ls = $row['ls'];

//     // Переводим сумму в текст
//     $amountText = mb_convert_case(getPriceWord($amount), MB_CASE_UPPER, 'UTF-8');

//     if ($apartment != NULL) {
//         $flat .= ', кв.' . $apartment;
//     }

//     // echo "<pre>";
//     // var_dump($flat);
//     // var_dump($apartment);
//     // var_dump($area);
//     // var_dump($street);
//     // echo "</pre>";




//     $html1 = "

//   <!-- Таблица, разделяющая ордер, перфорацию и квитанцию -->
//   <table border=\"0\">
//           <tr>
//             <td>
//             <!-- Название организации и подразделения, коды по ОКУД и ОКПО -->
//             <table border=\"0\" cellpadding=\"2\">
//               <tr>
//                 <td width=\"55%\" style=\"font-size: 9pt; font-weight: bold; text-align: center;\">
//                   Базар-Коргонский РУВХ <br>
//                   <tr>
//                     <td style=\"font-size: 6pt; text-align: center;\">________________________________________</td>
//                   </tr>
//                   <tr>
//                     <td style=\"font-size: 6pt; text-align: center;\">организация</td>
//                   </tr>
//                   <tr>
//                     <td style=\"font-size: 6pt; text-align: center;\"><br><br>________________________________________</td>
//                   </tr>
//                   <tr>
//                     <td style=\"font-size: 6pt; text-align: center;\">подразделение</td>
//                   </tr>
//                 </td>
//                 <td width=\"20%\" style=\"text-align: right; font-size: 8pt;\"><br><br><br>Форма по ОКУД <br>по ОКПО</td>
//                 <td width=\"25%\" style=\"text-align: center; font-size: 8pt;\">
//                   <table border=\"0.2\" cellpadding=\"2\">
//                     <tr><td>Код</td></tr>
//                     <tr><td style=\"font-size: 8pt; font-weight: bold\">0310001</td></tr>
//                     <tr><td style=\"font-size: 8pt; font-weight: bold\">5914017</td></tr>
//                     <tr><td style=\"font-size: 8pt; font-weight: bold\">02710199910026</td></tr>
//                   </table>
//                 </td>
//               </tr>
//             </table>

//           </td></tr>

//           <tr><td>
//             <!-- Номер документа, дата составления -->
//             <table border=\"0\">
//               <tr>
//                 <td align=\"center\" rowspan=\"2\" style=\"font-weight: bold; font-size: 12pt;\">ПРИХОДНЫЙ КАССОВЫЙ ОРДЕР</td>
//                 <td>
//                   <!-- Номер документа, дата составления -->
//                   <table border=\"0.5\" style=\"text-align: center; font-size: 8pt;\">
//                     <tr>
//                       <td>Номер документа</td>
//                       <td>Дата составления</td>
//                     </tr>
//                   </table>
//                 </td>
//               </tr>
//               <tr>
//                 <!-- Номер документа, дата составления -->
//                 <td>
//                   <table border=\"0.5\" style=\"text-align: center; font-size: 8pt; font-weight: bold;\">
//                     <tr>
//                       <td>$pay_no</td>
//                       <td>$pay_date</td>
//                     </tr>
//                   </table>
//                 </td>
//               </tr>
//             </table>
//             <br>
//           </td></tr>

      
//             <table border=\"0.5\" style=\"width: 100%;\">

//               <tr align=\"center\"  style=\"font-size: 8pt\" >
//                 <td width=\"18%\" rowspan=\"2\" >Дебет</td>
//                 <td width=\"54%\"colspan=\"3\">Кредит</td>
//                 <td width=\"13%\" rowspan=\"2\" >Сумма, <br>сом</td>
//                 <td width=\"15%\"rowspan=\"2\">Код <br> целевого назначения</td>
//               </tr>

//               <tr align=\"center\"  style=\"font-size: 8pt;\">
//                 <td width=\"18%\">код структ.<br/>подразделения</td>
//                 <td width=\"19%\">корр.счет,<br/>субсчет</td>
//                 <td width=\"17%\">код<br>ан.учета</td>
//               </tr>

//               <tr align=\"center\"  style=\"font-size: 8pt;\">

//                 <td></td>
//                 <td></td>
//                 <td></td>
//                 <td></td>
//                 <td>$amount<br></td>
//               </tr>

//             </table>


//             <table border=\"0\">

              
         
//               <p style=\"font-size: 8pt;\">Принято от: <b>$lastname</b> <br>Лицевой счет: <b>$ls</b><br>Адрес: <b>$area, улица $street, дом.$flat</b></p>
//               <p style=\"font-size: 8pt;\">Основание: <b>За вывоз мусора</b></p>
//               <p style=\"font-size: 8pt;\">Сумма:  $amountText СОМ</p>
//               <p style=\"font-size: 8pt;\">В том числе: НДС (Без НДС)</p>
            
//             </table>

//             <table>
//               <tr style=\"font-size: 8pt;\">
//                 <td rowspan=\"2\" style=\"width: 90;\"><b>Главный бухгалтер</b></td>
//                 <td align=\"center\" style=\"width: 70;\">_____________</td>
//                 <td align=\"center\" style=\"width: 100;\"></td>
//               </tr>
//               <tr align=\"center\" style=\"font-size: 5pt;\">
//                 <td style=\"width: 70;\">подпись</td>
//                 <td style=\"width: 100;\">расшифровка подписи</td>
//               </tr>
//               <tr style=\"font-size: 8pt;\">
//                 <td rowspan=\"2\" style=\"width: 90;\"><b>Получил кассир</b></td>
//                 <td align=\"center\" style=\"width: 70;\">_____________</td>
//                 <td align=\"center\" style=\"width: 100;\"></td>
//               </tr>
//               <tr align=\"center\" style=\"font-size: 5pt;\">
//                 <td style=\"width: 70;\">подпись</td>
//                 <td style=\"width: 100;\">расшифровка подписи</td>
//               </tr>
//             </table>

         
//         </table> <!-- Таблица ордера -->

//       </td>";

//     $html2 = '
//       <td width="3.3%">
//             <!-- Перфорация -->
//             <img src="test/logo/perforation.gif" />
//       </td>';

//     $html3 = "
  
//       <td width=\"35%\">
//         <!-- Квитанция -->
//         <!-- Внешняя таблица. Упорядочивает элементы квитанции -->
//         <table border=\"0\">

//           <tr><td>
//             <table>
//               <tr><td align=\"center\" style=\"font-size: 8pt;\">Базар-Коргонский РУВХ</td></tr>
//               <tr><td align=\"center\" style=\"font-size: 8pt;\">_________________________</td></tr>
//               <tr><td align=\"center\" style=\"font-size: 5pt;\">организация</td></tr>
//             </table>
//           </td></tr>

//           <tr><td>
//             <p align=\"center\" style=\"font-size: 8pt;\"><b><br />КВИТАНЦИЯ</b></p>
//           </td></tr>

//           <tr><td>
//             <!-- к ПКО от -->
//             <table border=\"0\" style=\"font-size: 8pt;\">

//               <tr>
//                 <td align=\"right\" style=\"width: 30\">к ПКО №</td>
//                 <td align=\"center\" style=\"width: 120\">$pay_no</td>
//               </tr>

//               <tr>
//                 <td align=\"right\" style=\"width: 30\"></td>
//                 <td align=\"center\" style=\"width: 120\">_____________</td>
//               </tr>

//               <tr>
//                 <td align=\"right\" style=\"width: 30\">от</td>
//                 <td align=\"center\" style=\"width: 120\">$pay_date</td>
//               </tr>

//               <tr>
//                 <td align=\"left\" style=\"width: 30\"></td>
//                 <td align=\"center\" style=\"width: 120\">_____________</td>
//               </tr>

//             </table>
//           </td></tr>

//           <tr style=\"font-size: 8pt;\" ><td>
//             <p>Принято от: <b>$lastname</b><br>Лицевой счет: <b>$ls</b><br>Адрес: <b>$area, <br>улица $street, дом.$flat</b></p>
//             <p>Основание: <b>За вывоз мусора</b> <br>Сумма: <b>$amount сом</b></p>
//             <p >В том числе: НДС (Без НДС)</p>
//           </td></tr>


//           <tr align=\"left\" style=\"font-size: 8pt;\"><td style=\"width: 150;\">
//             <table>
//               <tr><td>
//                 <b></b>
//               </td></tr>
//               <tr><td>
//                 <b>$pay_date</b>
//               </td></tr>
//             </table>
//             <p align=\"left\"><b>М.П. (штампа)<br /></b></p>
//           </td></tr>

//           <tr><td>

//             <table>
//               <tr align=\"left\" style=\"font-size: 8pt;\"><td colspan=\"2\">
//                 <b>Главный бухгалтер</b>
//               </td></tr>
//               <tr align=\"center\" style=\"font-size: 8pt; width: 200;\">
//                 <td></td>
//                 <td></td>
//               </tr>
//               <tr align=\"center\" style=\"font-size: 8pt;\">
//                 <td>_____________</td>
//                 <td>_____________</td>
//               </tr>
//               <tr align=\"center\" style=\"font-size: 5pt;\">
//                 <td align=\"center\">подпись</td>
//                 <td align=\"center\">расшифровка подписи</td>
//               </tr>
//             </table>

//             <table>
//               <tr align=\"left\" style=\"font-size: 8pt;\"><td colspan=\"2\">
//                 <b>Кассир</b>
//               </td></tr>
//               <tr align=\"center\" style=\"font-size: 8pt; width: 200\">
//                 <td></td>
//                 <td></td>
//               </tr>
//               <tr align=\"center\" style=\"font-size: 8pt;\">
//                 <td>_____________</td>
//                 <td>_____________</td>
//               </tr>
//               <tr align=\"center\" style=\"font-size: 5pt;\">
//                 <td>подпись</td>
//                 <td>расшифровка подписи</td>
//               </tr>
//             </table>

//           </td></tr>

//         </table>
//       </td>

//     </tr>
//   </table>";

//     $htmlResult =
//         <<<EOD


// 		<div>
//       <table>
//         <tr>
//           <td width="67%">
//             $html1
//             $html2
//             $html3
//           </td>
//         </tr>
//       </table>
//     </div>

// EOD;

//     return $htmlResult;

// }
// function getPriceWord($sourceNumber, $rodPadej = false) {
//     if (!($sourceNumber instanceof int)) {
//         $sourceNumber = (int) $sourceNumber;
//     }
//     $firstNumbers = array(
//         '',
//         'один',
//         'два',
//         'три',
//         'четыре',
//         'пять',
//         'шесть',
//         'семь',
//         'восемь',
//         'девять'
//     );
//     $secondNumbers = array(
//         'десять',
//         'одиннадцать',
//         'двенадцать',
//         'тринадцать',
//         'четырнадцать',
//         'пятнадцать',
//         'шестнадцать',
//         'семнадцать',
//         'восемнадцать',
//         'девятнадцать'
//     );
//     if ($rodPadej) {
//         $firstNumbers = array(
//             'нулевой',
//             'первый',
//             'второй',
//             'третий',
//             'четвертый',
//             'пятый',
//             'шестой',
//             'седьмой',
//             'восьмой',
//             'девятый'
//         );
//         $secondNumbers = array(
//             'десятый',
//             'одиннадцатый',
//             'двенадцатый',
//             'тринадцатый',
//             'четырнадцатый',
//             'пятнадцатый',
//             'шестнадцатый',
//             'семнадцатый',
//             'восемнадцатый',
//             'девятнадцатый'
//         );
//     }
//     $smallNumbers = array(
//         array(
//             'ноль'
//         ),
//         $firstNumbers,
//         $secondNumbers,
//         array(
//             '',
//             '',
//             'двадцать',
//             'тридцать',
//             'сорок',
//             'пятьдесят',
//             'шестьдесят',
//             'семьдесят',
//             'восемьдесят',
//             'девяносто'
//         ),
//         array(
//             '',
//             'сто',
//             'двести',
//             'триста',
//             'четыреста',
//             'пятьсот',
//             'шестьсот',
//             'семьсот',
//             'восемьсот',
//             'девятьсот'
//         ),
//         array(
//             '',
//             'одна',
//             'две'
//         )
//     );
//     $degrees = array(
//         array(
//             'больше дециллиона',
//             '',
//             'а',
//             'ов'
//         ),
//         array(
//             'тысяч',
//             'а',
//             'и',
//             ''
//         ),
//         array(
//             'миллион',
//             '',
//             'а',
//             'ов'
//         ),
//         array(
//             'миллиард',
//             '',
//             'а',
//             'ов'
//         ),
//         array(
//             'триллион',
//             '',
//             'а',
//             'ов'
//         ),
//         array(
//             'квадриллион',
//             '',
//             'а',
//             'ов'
//         ),
//         array(
//             'квинтиллион',
//             '',
//             'а',
//             'ов'
//         ),
//         array(
//             'секстиллион',
//             '',
//             'а',
//             'ов'
//         ),
//         array(
//             'септиллион',
//             '',
//             'а',
//             'ов'
//         ),
//         array(
//             'октиллион',
//             '',
//             'а',
//             'ов'
//         ),
//         array(
//             'нониллион',
//             '',
//             'а',
//             'ов'
//         ),
//         array(
//             'дециллион',
//             '',
//             'а',
//             'ов'
//         )
//     );

//     if ($sourceNumber == 0)
//         return $smallNumbers[0][0];
//     $sign = '';
//     if ($sourceNumber < 0) {
//         $sign = 'минус ';
//         $sourceNumber = substr($sourceNumber, 1);
//     }

//     $result = array();

//     $digitGroups = array_reverse(str_split(str_pad($sourceNumber, ceil(strlen($sourceNumber) / 3) * 3, '0', STR_PAD_LEFT), 3));
//     foreach ($digitGroups as $key => $value) {
//         $result[$key] = array();
//         foreach ($digit = str_split($value) as $key3 => $value3) {
//             if (!$value3)
//                 continue;
//             else {
//                 switch ($key3) {
//                     case 0:
//                         $result[$key][] = $smallNumbers[4][$value3];
//                         break;
//                     case 1:
//                         if ($value3 == 1) {
//                             $result[$key][] = $smallNumbers[2][$digit[2]];
//                             break 2;
//                         } else
//                             $result[$key][] = $smallNumbers[3][$value3];
//                         break;
//                     case 2:
//                         if (($key == 1) && ($value3 <= 2))
//                             $result[$key][] = $smallNumbers[5][$value3];
//                         else
//                             $result[$key][] = $smallNumbers[1][$value3];
//                         break;
//                 }
//             }
//         }
//         $value *= 1;
//         if (!$degrees[$key])
//             $degrees[$key] = reset($degrees);

//         if ($value && $key) {
//             $index = 3;
//             if (preg_match("/^[1]$|^\\d*[0,2-9][1]$/", $value))
//                 $index = 1;
//             else if (preg_match("/^[2-4]$|\\d*[0,2-9][2-4]$/", $value))
//                 $index = 2;
//             $result[$key][] = $degrees[$key][0] . $degrees[$key][$index];
//         }
//         $result[$key] = implode(' ', $result[$key]);
//     }
//     $answer = $sign . implode(' ', array_reverse($result));
//     return $answer;
// }