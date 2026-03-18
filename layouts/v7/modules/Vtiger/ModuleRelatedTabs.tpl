{*+**********************************************************************************
* The contents of this file are subject to the vtiger CRM Public License Version 1.1
* ("License"); You may not use this file except in compliance with the License
* The Original Code is: vtiger CRM Open Source
* The Initial Developer of the Original Code is vtiger.
* Portions created by vtiger are Copyright (C) vtiger.
* All Rights Reserved.
*************************************************************************************}

{strip}
	<div class='related-tabs row'>
		<nav class="navbar margin0" role="navigation">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle btn-group-justified collapsed border0" data-toggle="collapse" data-target="#nav-tabs" aria-expanded="false">
					<i class="fa fa-ellipsis-h"></i>
				</button>
			</div>

			<div class="collapse navbar-collapse" id="nav-tabs">
				<ul class="nav nav-tabs">
					{foreach item=RELATED_LINK from=$DETAILVIEW_LINKS['DETAILVIEWTAB']}
						{assign var=RELATEDLINK_URL value=$RELATED_LINK->getUrl()}
						{assign var=RELATEDLINK_LABEL value=$RELATED_LINK->getLabel()}
						{assign var=RELATED_TAB_LABEL value={vtranslate('SINGLE_'|cat:$MODULE_NAME, $MODULE_NAME)}|cat:" "|cat:$RELATEDLINK_LABEL}
						<li class="tab-item {if $RELATED_TAB_LABEL==$SELECTED_TAB_LABEL}active{/if}" data-url="{$RELATEDLINK_URL}&tab_label={$RELATED_TAB_LABEL}&app={$SELECTED_MENU_CATEGORY}" data-label-key="{$RELATEDLINK_LABEL}" data-link-key="{$RELATED_LINK->get('linkKey')}" >
							<a href="{$RELATEDLINK_URL}&tab_label={$RELATEDLINK_LABEL}&app={$SELECTED_MENU_CATEGORY}" class="textOverflowEllipsis">
								<span class="tab-label"><strong>{vtranslate($RELATEDLINK_LABEL,{$MODULE_NAME})}</strong></span>
							</a>
						</li>
					{/foreach}

					{assign var=RELATEDTABS value=$DETAILVIEW_LINKS['DETAILVIEWRELATED']}
                                        {if !empty($RELATEDTABS)}
                                            {assign var=COUNT value=$RELATEDTABS|@count}

                                            {assign var=LIMIT value = 10}
                                            {if $COUNT gt 10}
                                                    {assign var=COUNT1 value = $LIMIT}
                                            {else}
                                                    {assign var=COUNT1 value=$COUNT}
                                            {/if}

                                            {for $i = 0 to $COUNT1-1}
                                                    {assign var=RELATED_LINK value=$RELATEDTABS[$i]}
                                                    {assign var=RELATEDMODULENAME value=$RELATED_LINK->getRelatedModuleName()}
                                                    {assign var=RELATEDFIELDNAME value=$RELATED_LINK->get('linkFieldName')}
                                                    {assign var="DETAILVIEWRELATEDLINKLBL" value= vtranslate($RELATED_LINK->getLabel(),$RELATEDMODULENAME)}
                                                    {if $RELATED_LINK->getLabel() != 'Readings'} <!-- Добавлено условие -->
                                                    <li class="tab-item {if (trim($RELATED_LINK->getLabel())== trim($SELECTED_TAB_LABEL)) && ($RELATED_LINK->getId() == $SELECTED_RELATION_ID)}active{/if}" data-url="{$RELATED_LINK->getUrl()}&tab_label={$RELATED_LINK->getLabel()}&app={$SELECTED_MENU_CATEGORY}" data-label-key="{$RELATED_LINK->getLabel()}"
                                                            data-module="{$RELATEDMODULENAME}" data-relation-id="{$RELATED_LINK->getId()}" {if $RELATEDMODULENAME eq "ModComments"} title {else} title="{$DETAILVIEWRELATEDLINKLBL}"{/if} {if $RELATEDFIELDNAME}data-relatedfield ="{$RELATEDFIELDNAME}"{/if}>
                                                            <a href="index.php?{$RELATED_LINK->getUrl()}&tab_label={$RELATED_LINK->getLabel()}&app={$SELECTED_MENU_CATEGORY}" class="textOverflowEllipsis" displaylabel="{$DETAILVIEWRELATEDLINKLBL}" recordsCount="" >
                                                                    {if $RELATEDMODULENAME eq "ModComments"}
                                                                            <span class="tab-icon"><i class="fa fa-comment" style="font-size: 24px"></i></span>
                                                                    {else}
                                                                            <span class="tab-icon">
                                                                                    {assign var=RELATED_MODULE_MODEL value=Vtiger_Module_Model::getInstance($RELATEDMODULENAME)}
                                                                                    {$RELATED_MODULE_MODEL->getModuleIcon()}
                                                                            </span>
                                                                    {/if}
                                                                    &nbsp;<span class="numberCircle hide">0</span>
                                                                    {if $MODULE_NAME eq 'Estates'}
                                                                        {if $RELATEDMODULENAME eq 'Invoice'}
                                                                            <span class="sumBadge" title="Сумма счетов">{$RECORD->get('cf_invoice_amnt')|default:'0'} с</span>
                                                                        {elseif $RELATEDMODULENAME eq 'Payments'}
                                                                            <span class="sumBadge" title="Сумма платежей">{$RECORD->get('cf_payment_amnt')|default:'0'} с</span>
                                                                        {/if}
                                                                    {/if}
                                                            </a>
                                                    </li>
                                                    {/if} <!-- Конец условия -->
                                                    {if ($RELATED_LINK->getId() == {$REQ->get('relationId')})}
                                                            {assign var=MORE_TAB_ACTIVE value='true'}
                                                    {/if}
                                            {/for}
                                            {if $MORE_TAB_ACTIVE neq 'true'}
                                                    {for $i = 0 to $COUNT-1}
                                                            {assign var=RELATED_LINK value=$RELATEDTABS[$i]}
                                                            {if ($RELATED_LINK->getId() == {$REQ->get('relationId')})}
                                                                    {assign var=RELATEDMODULENAME value=$RELATED_LINK->getRelatedModuleName()}
                                                                    {assign var=RELATEDFIELDNAME value=$RELATED_LINK->get('linkFieldName')}
                                                                    {assign var="DETAILVIEWRELATEDLINKLBL" value= vtranslate($RELATED_LINK->getLabel(),$RELATEDMODULENAME)}
                                                                    <li class="more-tab moreTabElement active"  data-url="{$RELATED_LINK->getUrl()}&tab_label={$RELATED_LINK->getLabel()}&app={$SELECTED_MENU_CATEGORY}" data-label-key="{$RELATED_LINK->getLabel()}"
                                                                            data-module="{$RELATEDMODULENAME}" data-relation-id="{$RELATED_LINK->getId()}" {if $RELATEDMODULENAME eq "ModComments"} title {else} title="{$DETAILVIEWRELATEDLINKLBL}"{/if} {if $RELATEDFIELDNAME}data-relatedfield ="{$RELATEDFIELDNAME}"{/if}>
                                                                            <a href="index.php?{$RELATED_LINK->getUrl()}&tab_label={$RELATED_LINK->getLabel()}&app={$SELECTED_MENU_CATEGORY}" class="textOverflowEllipsis" displaylabel="{$DETAILVIEWRELATEDLINKLBL}" recordsCount="" >
                                                                                    {if $RELATEDMODULENAME eq "ModComments"}
                                                                                            <span class="tab-icon"><i class="fa fa-comment" style="font-size: 24px"></i></span>
                                                                                    {else}
                                                                                            <span class="tab-icon">
                                                                                                    {assign var=RELATED_MODULE_MODEL value=Vtiger_Module_Model::getInstance($RELATEDMODULENAME)}
                                                                                                    {$RELATED_MODULE_MODEL->getModuleIcon()}
                                                                                            </span>
                                                                                    {/if}
                                                                                    &nbsp;<span class="numberCircle hide">0</span>
                                                                            </a>
                                                                    </li>
                                                                    {break}
                                                            {/if}
                                                    {/for}
                                            {/if}
                                            {if $COUNT gt $LIMIT}
                                                    <li class="dropdown related-tab-more-element">
                                                            <a href="javascript:void(0)" data-toggle="dropdown" class="dropdown-toggle">
                                                                    <span class="tab-label">
                                                                            <strong>{vtranslate("LBL_MORE",$MODULE_NAME)}</strong> &nbsp; <b class="fa fa-caret-down"></b>
                                                                    </span>
                                                            </a>
                                                            <ul class="dropdown-menu pull-right" id="relatedmenuList">
                                                                    {for $j = $COUNT1 to $COUNT-1}
                                                                            {assign var=RELATED_LINK value=$RELATEDTABS[$j]}
                                                                            {assign var=RELATEDMODULENAME value=$RELATED_LINK->getRelatedModuleName()}
                                                                            {assign var=RELATEDFIELDNAME value=$RELATED_LINK->get('linkFieldName')}
                                                                            {assign var="DETAILVIEWRELATEDLINKLBL" value= vtranslate($RELATED_LINK->getLabel(),$RELATEDMODULENAME)}
                                                                            <li class="more-tab {if (trim($RELATED_LINK->getLabel())== trim($SELECTED_TAB_LABEL)) && ($RELATED_LINK->getId() == $SELECTED_RELATION_ID)}active{/if}" data-url="{$RELATED_LINK->getUrl()}&tab_label={$RELATED_LINK->getLabel()}&app={$SELECTED_MENU_CATEGORY}" data-label-key="{$RELATED_LINK->getLabel()}"
                                                                                    data-module="{$RELATEDMODULENAME}" title="" data-relation-id="{$RELATED_LINK->getId()}" {if $RELATEDFIELDNAME}data-relatedfield ="{$RELATEDFIELDNAME}"{/if}>
                                                                                    <a href="index.php?{$RELATED_LINK->getUrl()}&tab_label={$RELATED_LINK->getLabel()}&app={$SELECTED_MENU_CATEGORY}" displaylabel="{$DETAILVIEWRELATEDLINKLBL}" recordsCount="">
                                                                                            {if $RELATEDMODULENAME eq "ModComments"}
                                                                                                <span class="tab-icon textOverflowEllipsis">
                                                                                                    <i class="fa fa-comment"></i> &nbsp;<span class="content">{$DETAILVIEWRELATEDLINKLBL}</span>
                                                                                                </span>
                                                                                            {else}
                                                                                                    {assign var=RELATED_MODULE_MODEL value=Vtiger_Module_Model::getInstance($RELATEDMODULENAME)}
                                                                                                    <span class="tab-icon textOverflowEllipsis">
                                                                                                            {$RELATED_MODULE_MODEL->getModuleIcon()}
                                                                                                            <span class="content"> &nbsp;{$DETAILVIEWRELATEDLINKLBL}</span>
                                                                                                    </span>
                                                                                            {/if}
                                                                                            &nbsp;<span class="numberCircle hide">0</span>
                                                                                    </a>
                                                                            </li>
                                                                    {/for}
                                                            </ul>
                                                    </li>
                                            {/if}
                                        {/if}
                                       {if $MODULE_NAME eq 'Estates'}
                                                <li class="tab-item tabs" style="top: 10px" data-url="{$RELATED_LINK->getUrl()}" class="textOverflowEllipsis" displaylabel="Показания">
                                                        <span class="tab-icon">
                                                        <i class="vicon-readings" title="Показания" style="color: #8f44ad;"></i> <!-- Здесь добавлен цвет иконки -->
                                                </li>
                                        {/if}
                                        {* module=Estates&relatedModule=ModComments&view=Detail&record=223254&mode=showRelatedList&relationId=188 *}
				</ul>
			</div>
		</nav>
	</div>

<div id="myModal" class="modal custom-modal">
    <div class="modal-content custom-modal-content">
        <span class="close custom-close" id="close">&times;</span>
        <p class="userid">{$USER_MODEL->get('id')}</p>
        <h3 class="main-text custom-main-text">Счетчики</h3>
        <div id="modal-body" class="custom-modal-body"></div>
    </div>
</div>


<style>
        /* Основной контейнер модального окна */
        .custom-modal {
            display: none; /* Скрыть модальное окно по умолчанию */
            position: fixed;
            z-index: 1000; /* Высокий z-index для наложения поверх других элементов */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto; /* Позволяет прокручивать содержимое, если оно превышает высоту окна */
            background-color: rgba(0, 0, 0, 0.5); /* Темный фон с большей прозрачностью */
            backdrop-filter: blur(5px); /* Эффект размытия фона */
        }
        .userid{
                    visibility: hidden;

        }

        /* Содержимое модального окна */
        .custom-modal-content {
            background-color: #f5f5f5; /* Светло-серый фон */
            margin: 10% auto; /* Центрирование модального окна */
            padding: 20px;
            border-radius: 10px; /* Более скругленные углы */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); /* Более глубокая тень */
            width: 70%;
            max-width: 800px;
            font-family: "Arial", sans-serif; /* Стиль шрифта */
        }

        /* Кнопка закрытия */
        .custom-close {
            color: #ff6b6b; /* Яркий красный цвет кнопки закрытия */
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .custom-close:hover {
            color: #ff4c4c; /* Более темный оттенок при наведении */
        }

        /* Заголовок модального окна */
        .custom-main-text {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            color: #333; /* Темный цвет текста */
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0; /* Тонкая граница под заголовком */
        }

        /* Стили для содержимого модального окна */
        .custom-modal-body {
            margin-top: 20px;
            color: #555; /* Темно-серый текст */
            line-height: 1.6;
        }

        /* Бейдж суммы на табах Счета/Платежи */
        .sumBadge {
            display: block;
            font-size: 9px;
            color: #555;
            text-align: center;
            line-height: 1;
            margin-top: 2px;
            white-space: nowrap;
        }

        /* Контейнер для счетчиков */
        .meter-container {
            border: 1px solid #e0e0e0;
            padding: 15px;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 10px;
        }
    </style>


        <script src="{$SITE_URL}layouts/v7/modules/Readings/readings_bottom/Readings.js"></script>
{strip}   


