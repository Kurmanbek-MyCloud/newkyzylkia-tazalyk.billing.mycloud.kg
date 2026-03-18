<?php
class PenaltyCalculator {
    private $adb;
    private $penalty_percent = 0.1; // 0.1% в день
    private $penalty_start = 10; // 10 дней отсрочки

    public function __construct() {
        global $adb;
        $this->adb = $adb;
    }

    public function calculatePenalty($estatesid, $invoice_id) {
        // var_dump("Расчет пени для счета: " . $invoice_id);
        // var_dump("Для объекта: " . $estatesid);
        // exit();

        // 1. Получаем предыдущий счет
        $old_invoice = $this->adb->pquery("SELECT vi.invoiceid, vc.createdtime, vi.total
                                            FROM vtiger_invoice vi 
                                            INNER JOIN vtiger_crmentity vc ON vi.invoiceid = vc.crmid 
                                            WHERE vc.deleted = 0
                                            AND vi.cf_estate_id = ?
                                            AND vc.createdtime < (
                                                    SELECT vc2.createdtime
                                                    FROM vtiger_invoice vi2
                                                    INNER JOIN vtiger_crmentity vc2 ON vi2.invoiceid = vc2.crmid
                                                    WHERE vi2.invoiceid = ?
                                                )
                                            ORDER BY vc.createdtime DESC
                                            LIMIT 1", array($estatesid, $invoice_id));

        // var_dump("Предыдущий счет:");
        // // var_dump($old_invoice);
        // var_dump($this->adb->fetch_array($old_invoice));
        // exit();

        if ($this->adb->num_rows($old_invoice) == 0) {
            return 0;
        }

        $last_invoice_date = $this->adb->query_result($old_invoice, 0, 'createdtime');
        $last_invoice_amount = $this->adb->query_result($old_invoice, 0, 'total');

        // var_dump("Дата последнего счета: " . $last_invoice_date);
        // var_dump("Сумма последнего счета: " . $last_invoice_amount);
        // exit();

        // 2. Получаем сумму всех счетов до текущего (включая пени)
        $previous_invoices_query = "SELECT COALESCE(SUM(inv.total), 0) as invoices_sum
                                   FROM vtiger_invoice inv
                                   INNER JOIN vtiger_crmentity vc2 ON inv.invoiceid = vc2.crmid
                                   WHERE vc2.deleted = 0
                                   AND inv.cf_estate_id = ?
                                   AND vc2.createdtime < ?";
        $previous_invoices_result = $this->adb->pquery($previous_invoices_query, array($estatesid, $last_invoice_date));
        $previous_invoices_sum = $this->adb->query_result($previous_invoices_result, 0, 'invoices_sum');

        // var_dump("Сумма предыдущих счетов: " . $previous_invoices_sum);
        // exit();

        // 3. Получаем сумму всех пеней по предыдущим счетам
        $previous_penalties_query = "SELECT COALESCE(SUM(p.penalty_amount), 0) as penalties_sum
                                    FROM vtiger_penalty p
                                    INNER JOIN vtiger_crmentity vc3 ON p.penaltyid = vc3.crmid
                                    INNER JOIN vtiger_invoice inv ON p.cf_to_ivoice = inv.invoiceid
                                    WHERE vc3.deleted = 0
                                    AND inv.cf_estate_id = ?
                                    AND vc3.createdtime < ?";
        $previous_penalties_result = $this->adb->pquery($previous_penalties_query, array($estatesid, $last_invoice_date));
        $previous_penalties_sum = $this->adb->query_result($previous_penalties_result, 0, 'penalties_sum');

        // var_dump("Сумма предыдущих пеней: " . $previous_penalties_sum);
        // exit();

        // 4. Получаем сумму всех платежей до текущего счета
        $previous_payments_query = "SELECT COALESCE(SUM(vp.amount), 0) as payments_sum
                                   FROM vtiger_payments vp
                                   INNER JOIN vtiger_crmentity vc4 ON vp.paymentsid = vc4.crmid
                                   WHERE vc4.deleted = 0
                                   AND vp.cf_paid_object = ?
                                   AND vc4.createdtime < ?
                                   AND vp.cf_status != 'Отменен'";
        $previous_payments_result = $this->adb->pquery($previous_payments_query, array($estatesid, $last_invoice_date));
        $previous_payments_sum = $this->adb->query_result($previous_payments_result, 0, 'payments_sum');

        // var_dump("Сумма предыдущих платежей: " . $previous_payments_sum);
        // exit();

        // 5. Рассчитываем баланс
        $previous_balance = ($previous_invoices_sum + $previous_penalties_sum) - $previous_payments_sum;

        // var_dump("Предыдущий баланс: " . $previous_balance);
        // exit();

        // Если есть переплата и она полностью покрывает сумму последнего счета
        if ($previous_balance < 0 && abs($previous_balance) >= $last_invoice_amount) {
            return 0;
        }

        // Рассчитываем дату начала начисления пени (10 дней после предыдущего счета)
        $penalty_start_date = new DateTime($last_invoice_date);
        $penalty_start_date->modify("+{$this->penalty_start} days");

        // Получаем дату конца расчетного периода пени
        $current_invoice_info = $this->adb->pquery("SELECT vi.invoicedate FROM vtiger_invoice vi
                    INNER JOIN vtiger_crmentity vc ON vi.invoiceid = vc.crmid
                    WHERE vi.invoiceid = ?", array($invoice_id));

        $current_invoice_date = $this->adb->query_result($current_invoice_info, 0, 'invoicedate');
        $current_invoice_date = new DateTime($current_invoice_date);
        // $end_of_month = new DateTime($current_invoice_date->format('Y-m-d'));
        // $end_of_month->modify('last day of this month');

        // Получаем все платежи после даты предыдущего счета до конца месяца
        $payments_result = $this->adb->pquery("SELECT vp.amount, vp.cf_pay_date 
                                            FROM vtiger_payments vp
                                            INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.paymentsid 
                                            WHERE vc.deleted = 0
                                            AND vp.cf_paid_object = ?
                                            AND vp.cf_pay_date >= ?
                                            AND vp.cf_pay_date <= ?
                                            AND vp.cf_status != 'Отменен'
                                            ORDER BY vp.cf_pay_date ASC",
            array($estatesid, $last_invoice_date, $current_invoice_date->format('Y-m-d')));
        // var_dump($payments_result);
        $total_penalty = 0;
        $remaining_amount = $last_invoice_amount;
        $penalty_description = array();

        // Если есть переплата, вычитаем её из суммы счета
        if ($previous_balance < 0) {
            $remaining_amount += $previous_balance;
            $penalty_description[] = sprintf(
                "Учтена переплата %.2f сом на момент генерации счета",
                abs($previous_balance)
            );
            $penalty_description[] = sprintf(
                "Остаток к оплате: %.2f сом",
                $remaining_amount
            );
        }

        // var_dump("Сумма для расчета пени: " . $remaining_amount);

        // Если есть платежи, рассчитываем пени для каждого периода
        if ($this->adb->num_rows($payments_result) > 0) {
            $period_start_date = $penalty_start_date;
            $period_amount = $remaining_amount;
            $original_amount = $remaining_amount;

            $penalty_description[] = sprintf(
                "Начальная сумма для расчета пени: %.2f сом",
                $original_amount
            );

            for ($i = 0; $i < $this->adb->num_rows($payments_result); $i++) {
                $payment_amount = $this->adb->query_result($payments_result, $i, 'amount');
                $payment_date = new DateTime($this->adb->query_result($payments_result, $i, 'cf_pay_date'));

                $penalty_description[] = sprintf(
                    "Оплата: %.2f сом от %s",
                    $payment_amount,
                    $payment_date->format('d.m.Y')
                );

                // Если платеж после даты начала начисления пени и есть долг
                if ($payment_date >= $penalty_start_date && $period_amount > 0) {
                    // Рассчитываем пени за период до платежа
                    $days = $payment_date->diff($period_start_date)->days;
                    if ($days > 0) {
                        $period_penalty = $period_amount * ($this->penalty_percent / 100) * $days;
                        $total_penalty += $period_penalty;

                        $penalty_description[] = sprintf(
                            "Период с %s по %s: %.2f сом (%.2f сом * %.1f%% * %d дней)",
                            $period_start_date->format('d.m.Y'),
                            $payment_date->format('d.m.Y'),
                            $period_penalty,
                            $period_amount,
                            $this->penalty_percent,
                            $days
                        );
                    }
                }

                // Уменьшаем сумму долга на размер платежа
                $period_amount -= $payment_amount;

                $penalty_description[] = sprintf(
                    "Остаток после оплаты: %.2f сом",
                    $period_amount
                );

                // Если платеж был после даты начала пени, обновляем дату начала следующего периода
                if ($payment_date >= $penalty_start_date) {
                    $period_start_date = $payment_date;
                }

                // Если долг погашен, прерываем цикл
                if ($period_amount <= 0) {
                    $period_amount = 0;
                    $penalty_description[] = "Долг полностью погашен";
                    break;
                }

                if ($total_penalty >= $remaining_amount) {
                    $total_penalty = $remaining_amount;
                    // $penalty_description[] = "Пеня достигла суммы предыдущего счета: {$remaining_amount} сом";
                    break;
                }
            }

            // Если после всех платежей остался долг, начисляем пени до конца месяца
            if ($period_amount > 0) {
                $days = $current_invoice_date->diff($period_start_date)->days;
                if ($days > 0) {
                    $period_penalty = $period_amount * ($this->penalty_percent / 100) * $days;
                    $total_penalty += $period_penalty;

                    if ($total_penalty >= $remaining_amount) {
                        $total_penalty = $remaining_amount;
                        // $penalty_description[] = "Пеня достигла суммы предыдущего счета: {$remaining_amount} сом";
                    }

                    $penalty_description[] = sprintf(
                        "Период с %s по %s: %.2f сом (%.2f сом * %.1f%% * %d дней)",
                        $period_start_date->format('d.m.Y'),
                        $current_invoice_date->format('d.m.Y'),
                        $period_penalty,
                        $period_amount,
                        $this->penalty_percent,
                        $days
                    );
                }
            }
        } else {
            // Если платежей нет, начисляем пени с даты начала до конца месяца
            $days = $current_invoice_date->diff($penalty_start_date)->days;
            if ($days > 0) {
                $period_penalty = $remaining_amount * ($this->penalty_percent / 100) * $days;
                $total_penalty += $period_penalty;

                if ($total_penalty >= $remaining_amount) {
                    $total_penalty = $remaining_amount;
                    // $penalty_description[] = "Пеня достигла суммы предыдущего счета: {$remaining_amount} сом";
                }

                $penalty_description[] = sprintf(
                    "Период с %s по %s: %.2f сом (%.2f сом * %.1f%% * %d дней)",
                    $penalty_start_date->format('d.m.Y'),
                    $current_invoice_date->format('d.m.Y'),
                    $period_penalty,
                    $remaining_amount,
                    $this->penalty_percent,
                    $days
                );
            }
        }

        // var_dump("Описание пени:");
        // exit();

        // Получаем информацию о предыдущем счете
        $last_invoice_id = $this->adb->query_result($old_invoice, 0, 'invoiceid');

        $invoice_info = $this->adb->pquery("SELECT vi.subject, vc.createdtime, vi.total
                                          FROM vtiger_invoice vi
                                          INNER JOIN vtiger_crmentity vc ON vi.invoiceid = vc.crmid
                                          WHERE vi.invoiceid = ?", array($last_invoice_id));

        $invoice_subject = $this->adb->query_result($invoice_info, 0, 'subject');
        $invoice_date = new DateTime($this->adb->query_result($invoice_info, 0, 'createdtime'));
        $invoice_amount = $this->adb->query_result($invoice_info, 0, 'total');

        // Добавляем информацию о счете в начало описания
        array_unshift($penalty_description, sprintf(
            "Счет: %s\nДата генерации: %s\nСумма счета: %.2f сом\nБаланс на момент генерации: %.2f сом",
            $invoice_subject,
            $invoice_date->format('d.m.Y'),
            $invoice_amount,
            $previous_balance
        ));

        // Округляем до 2 знаков после запятой
        $total_penalty = round($total_penalty, 2);

        // Проверяем, есть ли уже пени по этому счету
        $existing_penalty = $this->adb->pquery("SELECT p.penaltyid 
                                        FROM vtiger_penalty p
                                        INNER JOIN vtiger_crmentity vc ON p.penaltyid = vc.crmid 
                                        WHERE p.cf_to_ivoice = ?
                                        AND vc.deleted = 0", array($invoice_id));

        if ($total_penalty > 0) {
            echo '<div style="padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; margin: 10px 0;">';
            echo '<h4 style="margin-top: 0; color: #333;">Расчет пени:</h4>';
            echo '<ul style="list-style: none; padding-left: 0;">';
            foreach ($penalty_description as $desc) {
                echo '<li style="margin-bottom: 8px; padding: 8px; background: white; border-radius: 3px;">' . nl2br(htmlspecialchars($desc)) . '</li>';
            }
            echo '</ul>';
            echo '<div style="margin-top: 10px; padding: 10px; background: #e9ecef; border-radius: 3px; font-weight: bold;">';
            echo 'Итоговая сумма пени: ' . number_format($total_penalty, 2, '.', ' ') . ' сом';
            echo '</div>';
            echo '</div>';
            if ($this->adb->num_rows($existing_penalty) > 0) {
                // Обновляем существующую пени
                $penalty_id = $this->adb->query_result($existing_penalty, 0, 'penaltyid');
                $penalty = Vtiger_Record_Model::getInstanceById($penalty_id, 'Penalty');
                $penalty->set('penalty_amount', $total_penalty);
                $penalty->set('cf_penalty_description', implode("\n", $penalty_description));
                $penalty->set('mode', 'edit');
                $penalty->save();
            } else {
                // Создаем новую пени
                $penalty = Vtiger_Record_Model::getCleanInstance("Penalty");
                $penalty->set('penalty_amount', $total_penalty);
                $penalty->set('cf_to_ivoice', $invoice_id);
                $penalty->set('cf_type_penalty', 'Неоплачено');
                $penalty->set('cf_penalty_description', implode("\n", $penalty_description));
                $penalty->set('assigned_user_id', 3);
                $penalty->set('mode', 'create');
                $penalty->save();
            }
        } else if ($this->adb->num_rows($existing_penalty) > 0) {
            // Если пени нет, но запись существует - удаляем её
            $penalty_id = $this->adb->query_result($existing_penalty, 0, 'penaltyid');
            $penalty = Vtiger_Record_Model::getInstanceById($penalty_id, 'Penalty');
            $penalty->delete();
            echo '<div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; color: #155724;">';
            echo 'Пени не начислены';
            echo '</div>';
        }

        return $total_penalty;
    }
}