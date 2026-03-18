<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
include_once 'modules/Floor/models/FloorModel.php';


class Space_Scheme_View extends Vtiger_Index_View
{

	public function checkPermission(Vtiger_Request $request)
	{
		
		$currentUserModel = Users_Record_Model::getCurrentUserModel();
		$record = $request->get('record');
		
		if ($currentUserModel->isAdminUser() == true || $currentUserModel->get('id') == $record) {
			return true;
		} else {
			throw new AppException(vtranslate('LBL_PERMISSION_DENIED'));
		}
		
	}

	public function getHeaderScripts(Vtiger_Request $request)
	{
		
		$headerScriptInstances = parent::getHeaderScripts($request);
		$jsFileNames = array(
			"modules.Space.resources.Scheme",
			"~libraries/pointIntPolygon/pointIntPolygon.js",
			"https://cdnjs.cloudflare.com/ajax/libs/d3/5.7.0/d3.min.js",
			"~layouts/" . Vtiger_Viewer::getDefaultLayoutName() . "/modules/Space/resources/FloorsSchemeAdapter.js",
			"~layouts/" . Vtiger_Viewer::getDefaultLayoutName() . "/modules/Space/resources/FloorsScheme.js",
			"~layouts/" . Vtiger_Viewer::getDefaultLayoutName() . "/modules/Space/resources/mouseMove.js",
			"~layouts/" . Vtiger_Viewer::getDefaultLayoutName() . "/modules/Space/resources/main.js",
		);
		
		$jsScriptInstances = $this->checkAndConvertJsScripts($jsFileNames);
		$headerScriptInstances = array_merge($headerScriptInstances, $jsScriptInstances);

		return $headerScriptInstances;
	}

	public function getHeaderCss(Vtiger_Request $request)
	{
		$parentHeaderCssScriptInstances = parent::getHeaderCss($request);
		
		$headerCss = array(
			"~layouts/" . Vtiger_Viewer::getDefaultLayoutName() . "/modules/Space/resources/style.css",
		);
		$cssScripts = $this->checkAndConvertCssStyles($headerCss);
		$headerCssScriptInstances = array_merge($parentHeaderCssScriptInstances, $cssScripts);
		return $headerCssScriptInstances;
	}
	
	public function process(Vtiger_Request $request)
	{	
		$moduleName = $request->getModule();
		
		$floors = Floor_Model::listAll();
		
		
		$viewer = $this->getViewer($request);
		
		$viewer->assign('FLOORS', $floors);
		
		
		$viewer->view('Scheme.tpl', $moduleName);
	}
	
}
