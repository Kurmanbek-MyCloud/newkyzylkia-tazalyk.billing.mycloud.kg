/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

Vtiger.Class(
  'Workspace_Scheme_Js',
  {
    schemeInstance: false,
    getInstance: function () {
      if (!Workspace_Scheme_Js.calendarInstance) {
        Workspace_Scheme_Js.calendarInstance = new Workspace_Scheme_Js();
      }
      return Workspace_Scheme_Js.calendarInstance;
    },
  },
  {
    init: function () {
      this.addComponents();
    },
    addComponents: function () {
      this.addIndexComponent();
    },
    addIndexComponent: function () {
      this.addModuleSpecificComponent(
        'Index',
        'Vtiger',
        app.getParentModuleName()
      );
    },
  }
);
