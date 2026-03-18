<?php

class Vtiger_NotificationAjax_Action extends Vtiger_Action_Controller {

	public function requiresPermission(\Vtiger_Request $request) {
		return array();
	}

	public function process(Vtiger_Request $request) {
		$mode = $request->get('mode');
		if ($mode) {
			$this->$mode($request);
		}
	}

	/**
	 * Check for pending push notifications (dismissed modals, unresolved)
	 */
	public function check(Vtiger_Request $request) {
		global $adb;
		$currentUser = Users_Record_Model::getCurrentUserModel();
		$userId = $currentUser->getId();

		$response = new Vtiger_Response();

		// First, auto-resolve notifications where service has been added
		$this->autoResolveNotifications($userId);

		// Get all unresolved notifications for push display
		$query = "SELECT pn.id, pn.record_id, pn.record_module, pn.notification_type, pn.created_time,
					 e.estate_number, e.cf_lastname
				  FROM vtiger_pending_notifications pn
				  LEFT JOIN vtiger_estates e ON e.estatesid = pn.record_id
				  INNER JOIN vtiger_crmentity vc ON vc.crmid = pn.record_id AND vc.deleted = 0
				  WHERE pn.user_id = ?
				  AND pn.is_resolved = 0
				  ORDER BY pn.created_time DESC";
		$result = $adb->pquery($query, array($userId));
		$notifications = array();
		$numRows = $adb->num_rows($result);

		for ($i = 0; $i < $numRows; $i++) {
			$notifications[] = array(
				'id' => $adb->query_result($result, $i, 'id'),
				'record_id' => $adb->query_result($result, $i, 'record_id'),
				'record_module' => $adb->query_result($result, $i, 'record_module'),
				'notification_type' => $adb->query_result($result, $i, 'notification_type'),
				'created_time' => $adb->query_result($result, $i, 'created_time'),
				'estate_number' => $adb->query_result($result, $i, 'estate_number'),
				'estate_name' => $adb->query_result($result, $i, 'cf_lastname'),
			);
		}

		$response->setResult(array(
			'notifications' => $notifications,
			'count' => $numRows
		));
		$response->emit();
	}

	/**
	 * Dismiss a modal notification (user closed it without adding service)
	 */
	public function dismiss(Vtiger_Request $request) {
		global $adb;
		$notificationId = $request->get('notification_id');
		$estateId = $request->get('estate_id');
		$response = new Vtiger_Response();

		if ($notificationId) {
			$adb->pquery("UPDATE vtiger_pending_notifications
						  SET is_modal_dismissed = 1
						  WHERE id = ?", array($notificationId));
		} elseif ($estateId) {
			$notificationType = $request->get('notification_type');
			if (!$notificationType) $notificationType = 'missing_service';
			$adb->pquery("UPDATE vtiger_pending_notifications
						  SET is_modal_dismissed = 1
						  WHERE record_id = ? AND notification_type = ? AND is_resolved = 0",
						  array($estateId, $notificationType));
		}

		$response->setResult(array('success' => true));
		$response->emit();
	}

	/**
	 * Resolve a notification (service was added)
	 */
	public function resolve(Vtiger_Request $request) {
		global $adb;
		$estateId = $request->get('estate_id');
		$response = new Vtiger_Response();

		if ($estateId) {
			$adb->pquery("UPDATE vtiger_pending_notifications
						  SET is_resolved = 1, resolved_time = NOW()
						  WHERE record_id = ? AND notification_type = 'missing_service' AND is_resolved = 0",
						  array($estateId));
		}

		$response->setResult(array('success' => true));
		$response->emit();
	}

	/**
	 * Check if an estate has services. If not, create notification and return show_modal flag.
	 * Called from JS when user lands on Estates detail view.
	 */
	public function checkEstate(Vtiger_Request $request) {
		global $adb;
		$estateId = $request->get('estate_id');
		$response = new Vtiger_Response();

		if (!$estateId) {
			$response->setResult(array('has_services' => true, 'show_modal' => false));
			$response->emit();
			return;
		}

		$currentUser = Users_Record_Model::getCurrentUserModel();
		$userId = $currentUser->getId();

		// Check if estate has services
		$query = "SELECT COUNT(*) as cnt FROM vtiger_crmentityrel
				  WHERE crmid = ? AND module = 'Estates' AND relmodule = 'Services'";
		$result = $adb->pquery($query, array($estateId));
		$count = $adb->query_result($result, 0, 'cnt');

		if ($count > 0) {
			$response->setResult(array('has_services' => true, 'show_modal' => false));
			$response->emit();
			return;
		}

		// No services — get available services from the same МП
		$availableServices = $this->getServicesForEstate($estateId);

		// Check if notification already exists
		$query2 = "SELECT id, is_modal_dismissed FROM vtiger_pending_notifications
				   WHERE record_id = ? AND notification_type = 'missing_service' AND is_resolved = 0
				   LIMIT 1";
		$result2 = $adb->pquery($query2, array($estateId));

		if ($adb->num_rows($result2) > 0) {
			$dismissed = $adb->query_result($result2, 0, 'is_modal_dismissed');
			$response->setResult(array(
				'has_services' => false,
				'show_modal' => ($dismissed == 0),
				'services' => $availableServices
			));
		} else {
			// Create new notification
			try {
				// Ensure table exists
				$tableCheck = $adb->pquery("SHOW TABLES LIKE 'vtiger_pending_notifications'", array());
				if ($adb->num_rows($tableCheck) == 0) {
					$adb->pquery("CREATE TABLE vtiger_pending_notifications (
						id INT AUTO_INCREMENT PRIMARY KEY,
						record_id INT NOT NULL,
						record_module VARCHAR(50) NOT NULL DEFAULT 'Estates',
						user_id INT NOT NULL,
						notification_type VARCHAR(50) NOT NULL,
						is_modal_dismissed TINYINT(1) DEFAULT 0,
						is_resolved TINYINT(1) DEFAULT 0,
						created_time DATETIME DEFAULT CURRENT_TIMESTAMP,
						resolved_time DATETIME NULL
					) ENGINE=InnoDB DEFAULT CHARSET=utf8", array());
				}

				$adb->pquery("INSERT INTO vtiger_pending_notifications
							  (record_id, record_module, user_id, notification_type)
							  VALUES (?, 'Estates', ?, 'missing_service')",
							  array($estateId, $userId));
			} catch (Exception $ex) {
				// Table/insert failure should not break the response
			}

			$response->setResult(array(
				'has_services' => false,
				'show_modal' => true,
				'services' => $availableServices
			));
		}

		$response->emit();
	}

	/**
	 * Get available services for an estate based on its МП (municipal enterprise).
	 * Finds distinct services used by other estates in the same МП.
	 */
	private function getServicesForEstate($estateId) {
		global $adb;

		// Get the МП of this estate
		$mpResult = $adb->pquery(
			"SELECT cf_municipal_enterprise FROM vtiger_estates WHERE estatesid = ?",
			array($estateId)
		);
		if ($adb->num_rows($mpResult) == 0) {
			return array();
		}
		$mpId = $adb->query_result($mpResult, 0, 'cf_municipal_enterprise');
		if (!$mpId) {
			return array();
		}

		// Get distinct services linked to other estates in the same МП
		$query = "SELECT DISTINCT s.serviceid, s.servicename, s.unit_price
				  FROM vtiger_service s
				  INNER JOIN vtiger_crmentity crm ON crm.crmid = s.serviceid AND crm.deleted = 0
				  INNER JOIN vtiger_crmentityrel rel ON rel.relcrmid = s.serviceid AND rel.relmodule = 'Services'
				  INNER JOIN vtiger_estates es ON es.estatesid = rel.crmid
				  INNER JOIN vtiger_crmentity ec ON ec.crmid = es.estatesid AND ec.deleted = 0
				  WHERE es.cf_municipal_enterprise = ?
				  ORDER BY s.servicename";
		$result = $adb->pquery($query, array($mpId));

		$services = array();
		for ($i = 0; $i < $adb->num_rows($result); $i++) {
			$services[] = array(
				'serviceid' => $adb->query_result($result, $i, 'serviceid'),
				'servicename' => $adb->query_result($result, $i, 'servicename'),
				'unit_price' => $adb->query_result($result, $i, 'unit_price'),
			);
		}
		return $services;
	}

	/**
	 * Link selected services to an estate via checkboxes in the modal
	 */
	public function linkServices(Vtiger_Request $request) {
		global $adb;
		$estateId = $request->get('estate_id');
		$serviceIds = $request->get('service_ids');
		$response = new Vtiger_Response();

		if (!$estateId || !$serviceIds || !is_array($serviceIds)) {
			$response->setResult(array('success' => false, 'error' => 'Invalid parameters'));
			$response->emit();
			return;
		}

		$linked = 0;
		foreach ($serviceIds as $serviceId) {
			$serviceId = intval($serviceId);
			if ($serviceId <= 0) continue;

			// Check if already linked
			$existing = $adb->pquery(
				"SELECT 1 FROM vtiger_crmentityrel
				 WHERE crmid = ? AND module = 'Estates' AND relcrmid = ? AND relmodule = 'Services'",
				array($estateId, $serviceId)
			);
			if ($adb->num_rows($existing) == 0) {
				$adb->pquery(
					"INSERT INTO vtiger_crmentityrel (crmid, module, relcrmid, relmodule)
					 VALUES (?, 'Estates', ?, 'Services')",
					array($estateId, $serviceId)
				);
				$linked++;
			}
		}

		// Auto-resolve the notification
		$adb->pquery(
			"UPDATE vtiger_pending_notifications
			 SET is_resolved = 1, resolved_time = NOW()
			 WHERE record_id = ? AND notification_type = 'missing_service' AND is_resolved = 0",
			array($estateId)
		);

		// Check if any linked service requires a meter
		$needsMeter = false;
		$meterCheckQuery = "SELECT COUNT(*) as cnt FROM vtiger_crmentityrel rel
						    INNER JOIN vtiger_service s ON s.serviceid = rel.relcrmid
						    INNER JOIN vtiger_crmentity crm ON crm.crmid = s.serviceid AND crm.deleted = 0
						    WHERE rel.crmid = ? AND rel.module = 'Estates' AND rel.relmodule = 'Services'
						    AND s.cf_method = 'Счетчик'";
		$meterCheckResult = $adb->pquery($meterCheckQuery, array($estateId));
		$meterServiceCount = intval($adb->query_result($meterCheckResult, 0, 'cnt'));

		if ($meterServiceCount > 0) {
			// Has meter-based services — check if estate has any meters
			$meterQuery = "SELECT COUNT(*) as cnt FROM vtiger_meters m
						   INNER JOIN vtiger_crmentity vc ON vc.crmid = m.metersid AND vc.deleted = 0
						   WHERE m.cf_meter_object_link = ?";
			$meterResult = $adb->pquery($meterQuery, array($estateId));
			$meterCount = intval($adb->query_result($meterResult, 0, 'cnt'));

			if ($meterCount == 0) {
				$needsMeter = true;
				// Create missing_meter notification
				$currentUser = Users_Record_Model::getCurrentUserModel();
				$userId = $currentUser->getId();
				$existingNotif = $adb->pquery(
					"SELECT id FROM vtiger_pending_notifications
					 WHERE record_id = ? AND notification_type = 'missing_meter' AND is_resolved = 0 LIMIT 1",
					array($estateId)
				);
				if ($adb->num_rows($existingNotif) == 0) {
					try {
						$adb->pquery(
							"INSERT INTO vtiger_pending_notifications
							 (record_id, record_module, user_id, notification_type)
							 VALUES (?, 'Estates', ?, 'missing_meter')",
							array($estateId, $userId)
						);
					} catch (Exception $ex) {}
				}
			}
		}

		$response->setResult(array('success' => true, 'linked' => $linked, 'needs_meter' => $needsMeter));
		$response->emit();
	}

	/**
	 * Check if estate needs a meter (has service with cf_method='Счетчик' but no meters).
	 * Only creates a push notification, no modal.
	 */
	public function checkEstateMeter(Vtiger_Request $request) {
		global $adb;
		$estateId = $request->get('estate_id');
		$response = new Vtiger_Response();

		if (!$estateId) {
			$response->setResult(array('needs_meter' => false));
			$response->emit();
			return;
		}

		$currentUser = Users_Record_Model::getCurrentUserModel();
		$userId = $currentUser->getId();

		// Check if estate has services with cf_method = 'Счетчик'
		$query = "SELECT COUNT(*) as cnt FROM vtiger_crmentityrel rel
				  INNER JOIN vtiger_service s ON s.serviceid = rel.relcrmid
				  INNER JOIN vtiger_crmentity crm ON crm.crmid = s.serviceid AND crm.deleted = 0
				  WHERE rel.crmid = ? AND rel.module = 'Estates' AND rel.relmodule = 'Services'
				  AND s.cf_method = 'Счетчик'";
		$result = $adb->pquery($query, array($estateId));
		$meterServiceCount = intval($adb->query_result($result, 0, 'cnt'));

		if ($meterServiceCount == 0) {
			$response->setResult(array('needs_meter' => false));
			$response->emit();
			return;
		}

		// Has meter-based services — check if estate has any active meters
		$query2 = "SELECT COUNT(*) as cnt FROM vtiger_meters m
				   INNER JOIN vtiger_crmentity vc ON vc.crmid = m.metersid AND vc.deleted = 0
				   WHERE m.cf_meter_object_link = ?";
		$result2 = $adb->pquery($query2, array($estateId));
		$meterCount = intval($adb->query_result($result2, 0, 'cnt'));

		if ($meterCount > 0) {
			// Has meters — auto-resolve if notification exists
			$adb->pquery("UPDATE vtiger_pending_notifications
						  SET is_resolved = 1, resolved_time = NOW()
						  WHERE record_id = ? AND notification_type = 'missing_meter' AND is_resolved = 0",
						  array($estateId));
			$response->setResult(array('needs_meter' => false));
			$response->emit();
			return;
		}

		// Needs meter — check if notification already exists
		$query3 = "SELECT id FROM vtiger_pending_notifications
				   WHERE record_id = ? AND notification_type = 'missing_meter' AND is_resolved = 0
				   LIMIT 1";
		$result3 = $adb->pquery($query3, array($estateId));

		if ($adb->num_rows($result3) == 0) {
			try {
				$adb->pquery("INSERT INTO vtiger_pending_notifications
							  (record_id, record_module, user_id, notification_type)
							  VALUES (?, 'Estates', ?, 'missing_meter')",
							  array($estateId, $userId));
			} catch (Exception $ex) {
				// Ignore insert failure
			}
		}

		$response->setResult(array('needs_meter' => true));
		$response->emit();
	}

	/**
	 * Auto-resolve notifications where the estate already has services
	 */
	private function autoResolveNotifications($userId) {
		global $adb;

		// Auto-resolve missing_service
		$query = "UPDATE vtiger_pending_notifications pn
				  SET pn.is_resolved = 1, pn.resolved_time = NOW()
				  WHERE pn.user_id = ?
				  AND pn.notification_type = 'missing_service'
				  AND pn.is_resolved = 0
				  AND EXISTS (
					  SELECT 1 FROM vtiger_crmentityrel cr
					  WHERE cr.crmid = pn.record_id
					  AND cr.module = 'Estates'
					  AND cr.relmodule = 'Services'
				  )";
		$adb->pquery($query, array($userId));

		// Auto-resolve missing_meter
		$query2 = "UPDATE vtiger_pending_notifications pn
				   SET pn.is_resolved = 1, pn.resolved_time = NOW()
				   WHERE pn.user_id = ?
				   AND pn.notification_type = 'missing_meter'
				   AND pn.is_resolved = 0
				   AND EXISTS (
					   SELECT 1 FROM vtiger_meters m
					   INNER JOIN vtiger_crmentity vc ON vc.crmid = m.metersid AND vc.deleted = 0
					   WHERE m.cf_meter_object_link = pn.record_id
				   )";
		$adb->pquery($query2, array($userId));
	}
}
