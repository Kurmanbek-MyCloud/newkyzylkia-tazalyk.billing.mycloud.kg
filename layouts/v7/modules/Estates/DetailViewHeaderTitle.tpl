{*+**********************************************************************************
* The contents of this file are subject to the vtiger CRM Public License Version 1.1
* ("License"); You may not use this file except in compliance with the License
* The Original Code is: vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
*************************************************************************************}

{strip}
	<div class="col-lg-6 col-md-6 col-sm-6">
		<div class="record-header clearfix">
			{if !$MODULE}
				{assign var=MODULE value=$MODULE_NAME}
			{/if}
			<div class="hidden-sm hidden-xs recordImage bg_{$MODULE} app-{$SELECTED_MENU_CATEGORY}">
				<div class="name"><span><strong>{$MODULE_MODEL->getModuleIcon()}</strong></span></div>
			</div>

			<div class="recordBasicInfo">
			

				
				<div class="info-row">
				{* {include file="DetailViewHeaderFieldsView.tpl"|vtemplate_path:$MODULE}	 *}
				<span class="recordLabel pushDown" title="{$RECORD->getName()}">
							{foreach item=NAME_FIELD from=$MODULE_MODEL->getNameFields()}
								{assign var=FIELD_MODEL value=$MODULE_MODEL->getField($NAME_FIELD)}
								{* {if $FIELD_MODEL->getPermissions()}
									<span class="{$NAME_FIELD}">Дом {$RECORD->get($NAME_FIELD)}{if $RECORD->get('cf_1446')} Кв {$RECORD->get('cf_1446')}{/if}</span>&nbsp;
								{/if} *}
							{/foreach}
						</span>
						<span class="account-label">Лицевой счет: </span>
						<span class="account-value" style="font-size:16px;">{$RECORD->getName()}</span>
						<br> 
						{if $RECORD->get('cf_house_number') && $RECORD->get('cf_litera')}
							<span class="account-label">Дом: </span>
							<span class="account-value" style="font-size:16px;">{$RECORD->get('cf_house_number')} / {$RECORD->get('cf_litera')}</span>
						{elseif $RECORD->get('cf_house_number')}
							<span class="account-label">Дом: </span>
							<span class="account-value" style="font-size:16px;">{$RECORD->get('cf_house_number')}</span>
						{elseif $RECORD->get('cf_litera')}
							<span class="account-label">Дом: </span>
							<span class="account-value" style="font-size:16px;">{$RECORD->get('cf_litera')}</span>
						{/if}
						{if $RECORD->get('cf_apartment_number')}
						<span class="account-label"> Кв: </span>
						<span class="account-value" style="font-size:16px;">{$RECORD->get('cf_apartment_number')}</span>
					{/if}
					<br>
						<span class="balance-label">Переплата: </span>
						<span class="balance-value"{if $RECORD->get('cf_balance') < 0} style="color: #489e56;"{/if}>{if $RECORD->get('cf_balance') < 0} {$RECORD->get('cf_balance')} {else}0{/if}⊆</span>
						<br>
						<span class="debt-label">Общая задолженность: </span>
						<span class="debt-value"{if $RECORD->get('cf_balance') > 0} style="color: #ca2913;"{/if}>{if $RECORD->get('cf_balance') > 0} {$RECORD->get('cf_balance')} {else}0{/if}⊆</span>
				</div>
			</div>
		</div>
	</div>
{/strip}