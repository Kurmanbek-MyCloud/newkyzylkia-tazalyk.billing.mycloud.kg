jQuery(document).ready(function () {

	var ServiceNotifications = {

		init: function () {
			var modalShown = this.checkEstateDetailView();
			// Only check push notifications if modal was NOT shown on this page
			if (!modalShown) {
				this.checkPushNotifications();
			}
		},

		/**
		 * Detect if we are on Estates detail view and check for missing services via AJAX
		 */
		checkEstateDetailView: function () {
			var module = app.getModuleName();
			var view = app.getViewName ? app.getViewName() : '';
			var recordId = jQuery('#recordId').val();

			if (module !== 'Estates' || view !== 'Detail' || !recordId) {
				return false;
			}

			var self = this;

			app.request.post({
				data: {
					module: 'Vtiger',
					action: 'NotificationAjax',
					mode: 'checkEstate',
					estate_id: recordId
				}
			}).then(function (err, data) {
				if (err === null && data && data.show_modal === true) {
					self.showServiceModal(recordId, data.services || []);
				}
				// After service check, also check for missing meters
				self.checkEstateMeter(recordId);
			});

			return true;
		},

		/**
		 * Check if estate needs a meter (has meter-based service but no meters)
		 */
		checkEstateMeter: function (recordId) {
			app.request.post({
				data: {
					module: 'Vtiger',
					action: 'NotificationAjax',
					mode: 'checkEstateMeter',
					estate_id: recordId
				}
			}).then(function (err, data) {
				if (err === null && data && data.needs_meter === true) {
					var meterUrl = 'index.php?module=Estates&relatedModule=Meters&view=Detail&record=' +
						recordId + '&mode=showRelatedList&tab_label=Meters';

					app.helper.showAlertNotification({
						'title': 'Внимание!',
						'message': 'Объекту необходимо добавить счётчик.' +
							'<br><a href="' + meterUrl + '" style="color:#fff;text-decoration:underline;font-weight:bold;">' +
							'→ Добавить счётчик</a>'
					}, {
						'delay': 0
					});
				}
			});
		},

		/**
		 * Show the modal with service checkboxes
		 */
		showServiceModal: function (estateId, services) {
			var self = this;
			var modal = jQuery('#serviceNotificationModal');

			if (modal.length === 0) return;

			// Populate checkboxes
			var checkboxList = jQuery('#serviceCheckboxList');
			var noServicesMsg = jQuery('#noServicesMessage');
			var linkBtn = jQuery('#linkServicesBtn');

			checkboxList.empty();

			if (services.length > 0) {
				noServicesMsg.hide();
				for (var i = 0; i < services.length; i++) {
					var s = services[i];
					var item = jQuery('<div class="checkbox" style="margin: 8px 0; padding: 8px 12px; border: 1px solid #eee; border-radius: 4px;">' +
						'<label style="width: 100%; cursor: pointer;">' +
						'<input type="checkbox" class="service-checkbox" value="' + s.serviceid + '" style="margin-right: 8px;"> ' +
						'<strong>' + self.escapeHtml(s.servicename) + '</strong>' +
						' <span class="text-muted">(' + parseFloat(s.unit_price).toFixed(2) + ' сом)</span>' +
						'</label></div>');
					checkboxList.append(item);
				}

				// Enable/disable link button based on checkbox selection
				checkboxList.off('change', '.service-checkbox').on('change', '.service-checkbox', function () {
					var checked = checkboxList.find('.service-checkbox:checked').length;
					linkBtn.prop('disabled', checked === 0);
				});
			} else {
				checkboxList.hide();
				noServicesMsg.show();
				linkBtn.hide();
			}

			modal.modal('show');

			// "Link selected" button
			linkBtn.off('click').on('click', function () {
				var selectedIds = [];
				checkboxList.find('.service-checkbox:checked').each(function () {
					selectedIds.push(jQuery(this).val());
				});

				if (selectedIds.length === 0) return;

				linkBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Привязываем...');

				app.request.post({
					data: {
						module: 'Vtiger',
						action: 'NotificationAjax',
						mode: 'linkServices',
						estate_id: estateId,
						service_ids: selectedIds
					}
				}).then(function (err, data) {
					if (err === null && data && data.success) {
						modal.modal('hide');
						app.helper.showSuccessNotification({
							message: 'Услуги успешно привязаны (' + data.linked + ' шт.)'
						});

						if (data.needs_meter === true) {
							// Show meter notification immediately, no reload
							var meterUrl = 'index.php?module=Estates&relatedModule=Meters&view=Detail&record=' +
								estateId + '&mode=showRelatedList&tab_label=Meters';
							app.helper.showAlertNotification({
								'title': 'Внимание!',
								'message': 'Объекту необходимо добавить счётчик.' +
									'<br><a href="' + meterUrl + '" style="color:#fff;text-decoration:underline;font-weight:bold;">' +
									'→ Добавить счётчик</a>'
							}, {
								'delay': 0
							});
						} else {
							// No meter needed — reload page
							setTimeout(function () {
								window.location.reload();
							}, 1000);
						}
					} else {
						linkBtn.prop('disabled', false).html('<i class="fa fa-check"></i> Привязать выбранные');
						app.helper.showErrorNotification({
							message: 'Ошибка при привязке услуг'
						});
					}
				});
			});

			// "Add manually" button - navigate to services tab
			jQuery('#addServiceManualBtn').off('click').on('click', function () {
				self.dismissNotification(estateId);
				modal.modal('hide');
				var serviceUrl = 'index.php?module=Estates&relatedModule=Services&view=Detail&record=' +
					estateId + '&mode=showRelatedList&tab_label=Services';
				window.location.href = serviceUrl;
			});

			// "Close" / "X" buttons
			jQuery('#dismissServiceModalClose, #dismissServiceModalBtn').off('click').on('click', function () {
				self.dismissNotification(estateId);
				modal.modal('hide');
			});
		},

		/**
		 * Escape HTML to prevent XSS
		 */
		escapeHtml: function (text) {
			var div = document.createElement('div');
			div.appendChild(document.createTextNode(text));
			return div.innerHTML;
		},

		/**
		 * Dismiss notification via AJAX
		 */
		dismissNotification: function (estateId) {
			app.request.post({
				data: {
					module: 'Vtiger',
					action: 'NotificationAjax',
					mode: 'dismiss',
					estate_id: estateId
				}
			});
		},

		/**
		 * Check for push notifications (on every non-Estates-detail page)
		 */
		checkPushNotifications: function () {
			var self = this;

			app.request.post({
				data: {
					module: 'Vtiger',
					action: 'NotificationAjax',
					mode: 'check'
				}
			}).then(function (err, data) {
				if (err === null && data && data.count > 0) {
					self.showPushNotification(data.notifications);
				}
			});
		},

		/**
		 * Show push notification using built-in vtiger notification system
		 */
		showPushNotification: function (notifications) {
			if (!notifications || notifications.length === 0) return;

			for (var i = 0; i < notifications.length; i++) {
				var n = notifications[i];
				var url, message, linkLabel;

				if (n.notification_type === 'missing_meter') {
					url = 'index.php?module=Estates&relatedModule=Meters&view=Detail&record=' +
						n.record_id + '&mode=showRelatedList&tab_label=Meters';
					message = 'Объекту необходимо добавить счётчик.';
					linkLabel = '→ Добавить счётчик';
				} else {
					url = 'index.php?module=Estates&relatedModule=Services&view=Detail&record=' +
						n.record_id + '&mode=showRelatedList&tab_label=Services';
					message = 'Вам осталось добавить услугу.';
					linkLabel = '→ Нажмите для добавления услуги';
				}

				var linkText = '<br><a href="' + url + '" style="color:#fff;text-decoration:underline;font-weight:bold;">'
					+ linkLabel
					+ (n.estate_number ? ' (ЛС: ' + n.estate_number + ')' : '')
					+ '</a>';

				app.helper.showAlertNotification({
					'title': 'Внимание!',
					'message': message + linkText
				}, {
					'delay': 0
				});

				break; // Show only the first notification
			}
		}
	};

	ServiceNotifications.init();
});
