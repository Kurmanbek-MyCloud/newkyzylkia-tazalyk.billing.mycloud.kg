{*<!--
/*********************************************************************************
  ** The contents of this file are subject to the vtiger CRM Public License Version 1.0
   * ("License"); You may not use this file except in compliance with the License
   * The Original Code is: vtiger CRM Open Source
   * The Initial Developer of the Original Code is vtiger.
   * Portions created by vtiger are Copyright (C) vtiger.
   * All Rights Reserved.
  *
 ********************************************************************************/
-->*}
{strip}
{assign var="SPECIAL_VALIDATOR" value=$FIELD_MODEL->getValidator()}
{assign var="FIELD_INFO" value=$FIELD_MODEL->getFieldInfo()}
{if $MODULE eq 'Payments' and $FIELD_MODEL->getFieldName() eq 'assigned_user_id' and $USER_MODEL->get('is_admin') eq 'off'}

{assign var=ALL_ACTIVEUSER_LIST value=$FIELD_INFO['picklistvalues'][vtranslate('LBL_USERS')]}
	{assign var=ASSIGNED_USER_ID value=$FIELD_MODEL->get('name')}
	{assign var=FIELD_VALUE value=$FIELD_MODEL->get('fieldvalue')}

	{if $FIELD_VALUE eq ''}
		{assign var=FIELD_VALUE value=$CURRENT_USER_ID}
	{/if}
	{assign var=DISPLAY_VALUE value=$ALL_ACTIVEUSER_LIST[$FIELD_VALUE]}
	
	<span class="value textOverflowEllipsis" data-field-type="owner">
		<a href="index.php?module=Users&amp;parent=Settings&amp;view=Detail&amp;record={$FIELD_VALUE}">{$DISPLAY_VALUE}</a>
	</span>
{else}
{if $FIELD_MODEL->get('uitype') eq '53'}
	{assign var=ALL_ACTIVEUSER_LIST value=$FIELD_INFO['picklistvalues'][vtranslate('LBL_USERS')]}
	{assign var=ALL_ACTIVEGROUP_LIST value=$FIELD_INFO['picklistvalues'][vtranslate('LBL_GROUPS')]}
	{assign var=ASSIGNED_USER_ID value=$FIELD_MODEL->get('name')}
        {assign var=CURRENT_USER_ID value=$USER_MODEL->get('id')}
	{assign var=FIELD_VALUE value=$FIELD_MODEL->get('fieldvalue')}

	{assign var=ACCESSIBLE_USER_LIST value=$USER_MODEL->getAccessibleUsersForModule($MODULE)}
	{assign var=ACCESSIBLE_GROUP_LIST value=$USER_MODEL->getAccessibleGroupForModule($MODULE)}

	{if $FIELD_VALUE eq ''}
		{assign var=FIELD_VALUE value=$CURRENT_USER_ID}
	{/if}
	<select class="inputElement select2" type="owner" data-fieldtype="owner" data-fieldname="{$ASSIGNED_USER_ID}" data-name="{$ASSIGNED_USER_ID}" name="{$ASSIGNED_USER_ID}" 
            {if $FIELD_INFO["mandatory"] eq true} data-rule-required="true" {/if}
            {if php7_count($FIELD_INFO['validator'])} 
                data-specific-rules='{ZEND_JSON::encode($FIELD_INFO["validator"])}'
            {/if}
            >
		{if $FIELD_MODEL->isCustomField() || $VIEW_SOURCE eq 'MASSEDIT'} <option value="">{vtranslate('LBL_SELECT_OPTION','Vtiger')}</option> {/if}
		<optgroup label="{vtranslate('LBL_USERS')}">
			{foreach key=OWNER_ID item=OWNER_NAME from=$ALL_ACTIVEUSER_LIST}
                    <option value="{$OWNER_ID}" data-picklistvalue= '{$OWNER_NAME}' {if $FIELD_VALUE eq $OWNER_ID && $VIEW_SOURCE neq 'MASSEDIT'} selected {/if}
						{if array_key_exists($OWNER_ID, $ACCESSIBLE_USER_LIST)} data-recordaccess=true {else} data-recordaccess=false {/if}
						data-userId="{$CURRENT_USER_ID}">
                    {$OWNER_NAME}
                    </option>
			{/foreach}
		</optgroup>
		<optgroup label="{vtranslate('LBL_GROUPS')}">
			{foreach key=OWNER_ID item=OWNER_NAME from=$ALL_ACTIVEGROUP_LIST}
				<option value="{$OWNER_ID}" data-picklistvalue= '{$OWNER_NAME}' {if $FIELD_MODEL->get('fieldvalue') eq $OWNER_ID} selected {/if}
					{if array_key_exists($OWNER_ID, $ACCESSIBLE_GROUP_LIST)} data-recordaccess=true {else} data-recordaccess=false {/if} >
				{$OWNER_NAME}
				</option>
			{/foreach}
		</optgroup>
	</select>
{/if}
{/if}
{* TODO - UI type 52 needs to be handled *}
{/strip}
