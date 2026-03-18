<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class Vtiger_RelationAjax_Action extends Vtiger_Action_Controller {
	function __construct() {
		parent::__construct();
		$this->exposeMethod('addRelation');
		$this->exposeMethod('deleteRelation');
		$this->exposeMethod('getRelatedListPageCount');
		$this->exposeMethod('getRelatedRecordInfo');
		$this->exposeMethod('updateWeight');
	}

	public function requiresPermission(Vtiger_Request $request) {
		$permissions = parent::requiresPermission($request);
		$mode = $request->getMode();
		if (!empty ($mode)) {
			switch ($mode) {
				case 'addRelation':
				case 'deleteRelation':
					$permissions[] = array('module_parameter' => 'module', 'action' => 'DetailView', 'record_parameter' => 'src_record');
					$permissions[] = array('module_parameter' => 'related_module', 'action' => 'DetailView');
					break;
				case 'getRelatedListPageCount':
					$permissions[] = array('module_parameter' => 'module', 'action' => 'DetailView', 'record_parameter' => 'record');
					$permissions[] = array('module_parameter' => 'relatedModule', 'action' => 'DetailView');
				case 'getRelatedRecordInfo':
					$permissions[] = array('module_parameter' => 'module', 'action' => 'DetailView', 'record_parameter' => 'id');
				default:
					break;
			}
		}
		return $permissions;
	}

	function checkPermission(Vtiger_Request $request) {
		return parent::checkPermission($request);
	}

	function preProcess(Vtiger_Request $request) {
		return true;
	}

	function postProcess(Vtiger_Request $request) {
		return true;
	}

	function process(Vtiger_Request $request) {
		$mode = $request->get('mode');
		if (!empty ($mode)) {
			$this->invokeExposedMethod($mode, $request);
			return;
		}
	}

	/*
	 * Function to add relation for specified source record id and related record id list
	 * @param <array> $request
	 *		keys					Content
	 *		src_module				source module name
	 *		src_record				source record id
	 *		related_module			related module name
	 *		related_record_list		json encoded of list of related record ids
	 */
	function addRelation($request) {
		$sourceModule = $request->getModule();
		$sourceRecordId = $request->get('src_record');

		$relatedModule = $request->get('related_module');
		$relatedRecordIdList = $request->get('related_record_list');

		$sourceModuleModel = Vtiger_Module_Model::getInstance($sourceModule);
		$relatedModuleModel = Vtiger_Module_Model::getInstance($relatedModule);
		$relationModel = Vtiger_Relation_Model::getInstance($sourceModuleModel, $relatedModuleModel);
		foreach ($relatedRecordIdList as $relatedRecordId) {
			$relationModel->addRelation($sourceRecordId, $relatedRecordId);
		}

		$response = new Vtiger_Response();
		$response->setResult(true);
		$response->emit();
	}

	/**
	 * Function to delete the relation for specified source record id and related record id list
	 * @param <array> $request
	 *		keys					Content
	 *		src_module				source module name
	 *		src_record				source record id
	 *		related_module			related module name
	 *		related_record_list		json encoded of list of related record ids
	 */
	// Обновление баланса в объекте при откреплении связи.
	function updateWeight($request) {
		global $adb;
		$main_module = $request->get('module'); // исходный модуль
		$module_change = $request->get('related_module'); // изменяемый модуль
		$estates_id = $request->get('src_record'); // id дома
		$related_id = $request->get('related_id'); // id отвязываемой записи

		if ($main_module == 'Estates') {
			$invsum = $adb->run_query_field("SELECT sum(inv.total) FROM vtiger_invoice inv 
            INNER JOIN vtiger_crmentity vc ON vc.crmid=inv.invoiceid 
            WHERE vc.deleted = 0 
            AND inv.invoicestatus NOT IN ('Cancel')
            AND inv.cf_estate_id = $estates_id");

			$paysum = $adb->run_query_field("SELECT sum(vp.amount) FROM vtiger_payments as vp
                INNER JOIN vtiger_crmentity vc ON vc.crmid=vp.paymentsid  
                WHERE vc.deleted = 0 
                AND vp.cf_pay_type = 'Приход'
                AND vp.cf_paid_object = $estates_id");

			$result = round(($invsum - $paysum), 2);

			$adb->pquery("UPDATE vtiger_estates SET cf_balance='$result' WHERE estatesid='$estates_id'");
		}
	}

	function deleteRelation($request) {
		$sourceModule = $request->getModule();
		$sourceRecordId = $request->get('src_record');

		$relatedModule = $request->get('related_module');
		$relatedRecordIdList = $request->get('related_record_list');
		$recurringEditMode = $request->get('recurringEditMode');
		$relatedRecordList = array();
		if ($relatedModule == 'Calendar' && !empty ($recurringEditMode) && $recurringEditMode != 'current') {
			foreach ($relatedRecordIdList as $relatedRecordId) {
				$recordModel = Vtiger_Record_Model::getCleanInstance($relatedModule);
				$recordModel->set('id', $relatedRecordId);
				$recurringRecordsList = $recordModel->getRecurringRecordsList();
				foreach ($recurringRecordsList as $parent => $childs) {
					$parentRecurringId = $parent;
					$childRecords = $childs;
				}
				if ($recurringEditMode == 'future') {
					$parentKey = array_keys($childRecords, $relatedRecordId);
					$childRecords = array_slice($childRecords, $parentKey[0]);
				}
				foreach ($childRecords as $recordId) {
					$relatedRecordList[] = $recordId;
				}
				$relatedRecordIdList = array_slice($relatedRecordIdList, $relatedRecordId);
			}
		}

		foreach ($relatedRecordList as $record) {
			$relatedRecordIdList[] = $record;
		}

		//Setting related module as current module to delete the relation
		vglobal('currentModule', $relatedModule);

		$sourceModuleModel = Vtiger_Module_Model::getInstance($sourceModule);
		$relatedModuleModel = Vtiger_Module_Model::getInstance($relatedModule);
		$relationModel = Vtiger_Relation_Model::getInstance($sourceModuleModel, $relatedModuleModel);
		foreach ($relatedRecordIdList as $relatedRecordId) {
			$response = $relationModel->deleteRelation($sourceRecordId, $relatedRecordId);
		}

		$response = new Vtiger_Response();
		$response->setResult(true);
		$response->emit();
	}

	/**
	 * Function to get the page count for reltedlist
	 * @return total number of pages
	 */
	function getRelatedListPageCount(Vtiger_Request $request) {
		$moduleName = $request->getModule();
		$relatedModuleName = $request->get('relatedModule');
		$parentId = $request->get('record');
		$label = $request->get('tab_label');
		$pagingModel = new Vtiger_Paging_Model();
		$parentRecordModel = Vtiger_Record_Model::getInstanceById($parentId, $moduleName);
		$relationListView = Vtiger_RelationListView_Model::getInstance($parentRecordModel, $relatedModuleName, $label);
		$totalCount = $relationListView->getRelatedEntriesCount();
		$pageLimit = $pagingModel->getPageLimit();
		$pageCount = ceil((int) $totalCount / (int) $pageLimit);

		if ($pageCount == 0) {
			$pageCount = 1;
		}
		$result = array();
		$result['numberOfRecords'] = $totalCount;
		$result['page'] = $pageCount;
		$response = new Vtiger_Response();
		$response->setResult($result);
		$response->emit();
	}

	public function validateRequest(Vtiger_Request $request) {
		$request->validateWriteAccess();
	}

	function getRelatedRecordInfo($request) {
		try {
			return $this->getParentRecordInfo($request);
		} catch (Exception $e) {
			$response = new Vtiger_Response();
			$response->setError($e->getCode(), $e->getMessage());
			$response->emit();
		}
	}

	function getParentRecordInfo($request) {
		$moduleName = $request->get('module');
		$recordModel = Vtiger_Record_Model::getInstanceById($request->get('id'), $moduleName);
		$moduleModel = $recordModel->getModule();
		$autoFillData = $moduleModel->getAutoFillModuleAndField($moduleName);

		if ($autoFillData) {
			foreach ($autoFillData as $data) {
				$autoFillModule = $data['module'];
				$autoFillFieldName = $data['fieldname'];
				$autofillRecordId = $recordModel->get($autoFillFieldName);

				$autoFillNameArray = getEntityName($autoFillModule, $autofillRecordId);
				$autoFillName = $autoFillNameArray[$autofillRecordId];

				$resultData[] = array('id' => $request->get('id'),
					'name' => decode_html($recordModel->getName()),
					'parent_id' => array('id' => $autofillRecordId,
						'name' => decode_html($autoFillName),
						'module' => $autoFillModule));
			}

			$result[$request->get('id')] = $resultData;

		} else {
			$resultData = array('id' => $request->get('id'),
				'name' => decode_html($recordModel->getName()),
				'info' => $recordModel->getRawData());
			$result[$request->get('id')] = $resultData;
		}

		$response = new Vtiger_Response();
		$response->setResult($result);
		$response->emit();
	}

}
