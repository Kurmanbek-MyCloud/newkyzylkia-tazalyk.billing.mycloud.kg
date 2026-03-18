/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is: vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

Inventory_Edit_Js("Invoice_Edit_Js", {}, {

	accountRefrenceField: false,

	initializeVariables: function () {
		this._super();
		var form = this.getForm();
		this.accountReferenceField = form.find('[name="account_id"]');
	},

	/**
	 * Function which will register event for Reference Fields Selection
	 */
	registerReferenceSelectionEvent: function (container) {
		this._super(container);
		var self = this;

		this.accountReferenceField.on(Vtiger_Edit_Js.referenceSelectionEvent, function (e, data) {
			self.referenceSelectionEventHandler(data, container);
		});
	},

	/**
	 * Function to get popup params
	 */
	getPopUpParams: function (container) {
		var params = this._super(container);
		var sourceFieldElement = jQuery('input[class="sourceField"]', container);
		if (!sourceFieldElement.length) {
			sourceFieldElement = jQuery('input.sourceField', container);
		}

		if (sourceFieldElement.attr('name') == 'contact_id') {
			var form = this.getForm();
			var parentIdElement = form.find('[name="account_id"]');
			if (parentIdElement.length > 0 && parentIdElement.val().length > 0 && parentIdElement.val() != 0) {
				var closestContainer = parentIdElement.closest('td');
				params['related_parent_id'] = parentIdElement.val();
				params['related_parent_module'] = closestContainer.find('[name="popupReferenceModule"]').val();
			}
		}
		return params;
	},

	/**
	 * Function to search module names
	 */
	searchModuleNames: function (params) {
		var aDeferred = jQuery.Deferred();

		if (typeof params.module == 'undefined') {
			params.module = app.getModuleName();
		}
		if (typeof params.action == 'undefined') {
			params.action = 'BasicAjax';
		}

		if (typeof params.base_record == 'undefined') {
			var record = jQuery('[name="record"]');
			var recordId = app.getRecordId();
			if (record.length) {
				params.base_record = record.val();
			} else if (recordId) {
				params.base_record = recordId;
			} else if (app.view() == 'List') {
				var editRecordId = jQuery('#listview-table').find('tr.listViewEntries.edited').data('id');
				if (editRecordId) {
					params.base_record = editRecordId;
				}
			}
		}

		if (params.search_module == 'Contacts') {
			var form = this.getForm();
			if (this.accountReferenceField.length > 0 && this.accountReferenceField.val().length > 0) {
				var closestContainer = this.accountReferenceField.closest('td');
				params.parent_id = this.accountReferenceField.val();
				params.parent_module = closestContainer.find('[name="popupReferenceModule"]').val();
			}
		}

		// Added for overlay edit as the module is different
		if (params.search_module == 'Products' || params.search_module == 'Services') {
			params.module = 'Invoice';
		}

		app.request.get({ 'data': params }).then(
			function (error, data) {
				if (error == null) {
					aDeferred.resolve(data);
				}
			},
			function (error) {
				aDeferred.reject();
			}
		)
		return aDeferred.promise();
	},

	readingsData: [],
	userGroupId: null,
	userGroupName: '',

	/**
	 * Загрузка показаний по ID объекта (estate)
	 */
	loadReadingsForEstate: function (estateId) {
		var self = this;
		if (!estateId) return;

		var requestUrl = window.location.origin + '/layouts/v7/modules/Readings/readings_bottom/php_requests/js_requests.php';

		fetch(requestUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				action: 'getReadingsByEstate',
				estatesid: estateId
			})
		})
		.then(function (response) { return response.json(); })
		.then(function (data) {
			self.readingsData = data;
			self.populateMeterDropdowns();
			self.populateReadingsDropdowns();
		})
		.catch(function (error) {
			console.error('Ошибка загрузки показаний:', error);
		});
	},

	/**
	 * Заполнить dropdown счётчиков из загруженных показаний
	 */
	populateMeterDropdowns: function () {
		var self = this;
		var meters = {};
		var metersOrder = [];
		for (var i = 0; i < self.readingsData.length; i++) {
			var r = self.readingsData[i];
			if (!meters[r.metersid]) {
				meters[r.metersid] = r.meter_number;
				metersOrder.push(r.metersid);
			}
		}

		jQuery('.meterSelect').each(function () {
			var select = jQuery(this);
			var row = select.closest('tr');
			var savedMeter = row.find('.accrualBaseHidden').val();

			select.empty();
			select.append('<option value="">-- Счётчик --</option>');

			for (var i = 0; i < metersOrder.length; i++) {
				var meterId = metersOrder[i];
				var meterNum = meters[meterId];
				var sel = (savedMeter && savedMeter == meterNum) ? ' selected' : '';
				select.append('<option value="' + meterId + '" data-meter-number="' + meterNum + '"' + sel + '>' + meterNum + '</option>');
			}
		});
	},

	/**
	 * Заполнить все dropdown показаний (предыдущее и текущее) на странице
	 * Если в строке выбран счётчик — показывает только его показания, иначе все.
	 */
	populateReadingsDropdowns: function () {
		var self = this;

		jQuery('.prevReadingSelect, .curReadingSelect').each(function () {
			var select = jQuery(this);
			var row = select.closest('tr');
			var isPrev = select.hasClass('prevReadingSelect');
			var hiddenInput = isPrev ? row.find('.prevReadingIdField') : row.find('.curReadingIdField');
			var savedReadingId = hiddenInput.val();
			var selectedMeterId = row.find('.meterSelect').val();

			var readings = self.readingsData;
			if (selectedMeterId) {
				readings = readings.filter(function (r) { return r.metersid == selectedMeterId; });
			}

			select.empty();
			select.append('<option value="">-- Выберите --</option>');

			if (!selectedMeterId) {
				var currentMeter = '';
				for (var i = 0; i < readings.length; i++) {
					var r = readings[i];
					if (r.meter_number !== currentMeter) {
						if (currentMeter !== '') select.append('</optgroup>');
						select.append('<optgroup label="Счётчик: ' + r.meter_number + '">');
						currentMeter = r.meter_number;
					}
					var usedLabel = r.cf_used_in_bill == 1 ? ' [исп.]' : '';
					var optionText = r.meter_reading + ' (' + r.cf_reading_date + ')' + usedLabel;
					var sel = (savedReadingId && savedReadingId == r.readingsid) ? ' selected' : '';
					select.append('<option value="' + r.readingsid + '" data-reading="' + r.meter_reading + '"' + sel + '>' + optionText + '</option>');
				}
				if (currentMeter !== '') select.append('</optgroup>');
			} else {
				for (var i = 0; i < readings.length; i++) {
					var r = readings[i];
					var usedLabel = r.cf_used_in_bill == 1 ? ' [исп.]' : '';
					var optionText = r.meter_reading + ' (' + r.cf_reading_date + ')' + usedLabel;
					var sel = (savedReadingId && savedReadingId == r.readingsid) ? ' selected' : '';
					select.append('<option value="' + r.readingsid + '" data-reading="' + r.meter_reading + '"' + sel + '>' + optionText + '</option>');
				}
			}
		});
	},

	/**
	 * Пересчитать qty = текущее показание - предыдущее показание
	 */
	recalcQtyFromReadings: function (row) {
		var prevSelect = row.find('.prevReadingSelect');
		var curSelect = row.find('.curReadingSelect');
		var prevReading = parseFloat(prevSelect.find('option:selected').data('reading'));
		var curReading = parseFloat(curSelect.find('option:selected').data('reading'));

		row.find('.readingsWarning').remove();
		if (!isNaN(prevReading) && !isNaN(curReading)) {
			var qty = curReading - prevReading;
			if (qty < 0) {
				qty = 0;
				row.find('.curReadingSelect').closest('td').append(
					'<div class="readingsWarning" style="background:#f2dede;color:#a94442;border:1px solid #ebccd1;padding:6px 10px;margin-top:5px;border-radius:4px;font-size:12px;">' +
					'<strong>Внимание!</strong> Пред. показание (' + prevReading + ') больше текущего (' + curReading + '). Проверьте правильность выбора.' +
					'</div>'
				);
			}
			row.find('.qty').val(qty).trigger('focusout');
		}
	},

	/**
	 * Обработчики выбора показаний из dropdown
	 */
	registerReadingSelectEvent: function () {
		var self = this;
		var form = this.getForm();

		// Выбор счётчика
		form.on('change', '.meterSelect', function () {
			var select = jQuery(this);
			var row = select.closest('tr');
			var meterNumber = select.find('option:selected').data('meter-number') || '';
			row.find('.accrualBaseHidden').val(meterNumber);
			row.find('.prevReadingIdField').val('');
			row.find('.curReadingIdField').val('');
			self.populateReadingsDropdowns();
		});

		// Предыдущее показание
		form.on('change', '.prevReadingSelect', function () {
			var select = jQuery(this);
			var row = select.closest('tr');
			row.find('.prevReadingIdField').val(select.val());
			self.recalcQtyFromReadings(row);
		});

		// Текущее показание
		form.on('change', '.curReadingSelect', function () {
			var select = jQuery(this);
			var row = select.closest('tr');
			row.find('.curReadingIdField').val(select.val());
			self.recalcQtyFromReadings(row);
		});
	},

	/**
	 * Обработчик кнопки "Добавить показание"
	 */
	registerAddReadingEvent: function () {
		var self = this;
		var form = this.getForm();

		form.on('click', '.addReadingBtn', function () {
			var btn = jQuery(this);
			var td = btn.closest('td');
			var isPrev = td.find('.prevReadingSelect').length > 0;
			var row = btn.closest('tr');
			var meterSelect = row.find('.meterSelect');
			var meterId = meterSelect.val();
			var meterNumber = meterSelect.find('option:selected').data('meter-number') || '';
			var estateId = form.find('[name="cf_estate_id"]').val();

			if (!meterId) {
				app.helper.showAlertNotification({
					'title': 'Внимание',
					'message': 'Сначала выберите счётчик в строке'
				});
				return;
			}

			if (!estateId) {
				app.helper.showAlertNotification({
					'title': 'Внимание',
					'message': 'Сначала выберите объект (Estates)'
				});
				return;
			}

			var today = new Date().toISOString().split('T')[0];
			var groupName = self.userGroupName || 'Не определена';
			var modalHtml = '<div class="modal fade" id="addReadingModal" tabindex="-1">' +
				'<div class="modal-dialog modal-sm">' +
				'<div class="modal-content">' +
				'<div class="modal-header">' +
				'<button type="button" class="close" data-dismiss="modal">&times;</button>' +
				'<h4 class="modal-title">Добавить показание</h4>' +
				'</div>' +
				'<div class="modal-body">' +
				'<div class="form-group"><label>Ответственный</label>' +
				'<input type="text" class="form-control" value="' + groupName + '" disabled />' +
				'<small style="color:red;">Обратите внимание на ответственного</small></div>' +
				'<div class="form-group"><label>Счётчик</label>' +
				'<input type="text" class="form-control" value="' + meterNumber + '" disabled /></div>' +
				'<div class="form-group"><label>Показание *</label>' +
				'<input type="number" class="form-control" id="newReadingValue" required /></div>' +
				'<div class="form-group"><label>Дата *</label>' +
				'<input type="date" class="form-control" id="newReadingDate" value="' + today + '" required /></div>' +
				'</div>' +
				'<div class="modal-footer">' +
				'<button type="button" class="btn btn-default" data-dismiss="modal">Отмена</button>' +
				'<button type="button" class="btn btn-success" id="saveNewReading">Сохранить</button>' +
				'</div></div></div></div>';

			jQuery('#addReadingModal').remove();
			jQuery('body').append(modalHtml);
			jQuery('#addReadingModal').modal('show');

			jQuery('#saveNewReading').off('click').on('click', function () {
				var readingValue = jQuery('#newReadingValue').val();
				var readingDate = jQuery('#newReadingDate').val();

				if (!readingValue || !readingDate) {
					app.helper.showAlertNotification({
						'title': 'Ошибка',
						'message': 'Заполните все обязательные поля'
					});
					return;
				}

				var saveBtn = jQuery(this);
				saveBtn.prop('disabled', true).text('Сохранение...');

				var requestUrl = window.location.origin + '/layouts/v7/modules/Readings/readings_bottom/php_requests/js_requests.php';

				fetch(requestUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({
						action: 'createReadings',
						userid: self.userGroupId || ((typeof _USERMETA !== 'undefined' && _USERMETA.id) ? _USERMETA.id : 1),
						metersid: meterId,
						inputValue: readingValue,
						metersLink: estateId,
						date: readingDate
					})
				})
				.then(function (response) { return response.json(); })
				.then(function (data) {
					jQuery('#addReadingModal').modal('hide');
					if (data.status === 'Ok') {
						app.helper.showSuccessNotification({
							'message': 'Показание добавлено'
						});
						self.loadReadingsForEstate(estateId);
						setTimeout(function () {
							if (isPrev) {
								row.find('.prevReadingIdField').val(data.readings_id);
							} else {
								row.find('.curReadingIdField').val(data.readings_id);
							}
							self.populateReadingsDropdowns();
						}, 500);
					} else {
						app.helper.showAlertNotification({
							'title': 'Ошибка',
							'message': data.message || 'Не удалось создать показание'
						});
					}
				})
				.catch(function (error) {
					jQuery('#addReadingModal').modal('hide');
					app.helper.showAlertNotification({
						'title': 'Ошибка',
						'message': 'Ошибка сети'
					});
				});
			});
		});
	},

	/**
	 * Отслеживание изменения поля cf_estate_id
	 */
	registerEstateChangeEvent: function () {
		var self = this;
		var form = this.getForm();
		var estateField = form.find('[name="cf_estate_id"]');

		if (estateField.length) {
			estateField.on('change', function () {
				var estateId = jQuery(this).val();
				self.loadReadingsForEstate(estateId);
			});
			estateField.on(Vtiger_Edit_Js.referenceSelectionEvent, function (_e, data) {
				if (data && data.id) {
					self.loadReadingsForEstate(data.id);
				}
			});
		}
	},

	registerBasicEvents: function (container) {
		this._super(container);
		this.registerForTogglingBillingandShippingAddress();
		this.registerEventForCopyAddress();
		this.registerReadingSelectEvent();
		this.registerAddReadingEvent();
		this.registerEstateChangeEvent();

		// Загрузить группу текущего пользователя
		var self = this;
		var currentUserId = (typeof _USERMETA !== 'undefined' && _USERMETA.id) ? _USERMETA.id : jQuery('#current_user_id').val();
		if (currentUserId) {
			var requestUrl = window.location.origin + '/layouts/v7/modules/Readings/readings_bottom/php_requests/js_requests.php';
			fetch(requestUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ action: 'getUserGroup', userid: currentUserId })
			})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.groupid) {
					self.userGroupId = data.groupid;
					self.userGroupName = data.groupname;
				}
			})
			.catch(function () {});
		}

		// vtiger инициализирует reference поля асинхронно, ждём перед чтением значения
		setTimeout(function () {
			var estateId = self.getForm().find('[name="cf_estate_id"]').val();
			if (estateId) {
				self.loadReadingsForEstate(estateId);
			}
		}, 500);
	},
});


