<div class="modal fade" id="serviceNotificationModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
	<div class="modal-dialog" role="document" style="margin-top: 15%;">
		<div class="modal-content">
			<div class="modal-header" style="background-color: #5bc0de; color: #fff; border-radius: 4px 4px 0 0;">
				<button type="button" class="close" id="dismissServiceModalClose" style="color: #fff; opacity: 0.8;">
					<span aria-hidden="true">&times;</span>
				</button>
				<h4 class="modal-title" style="margin: 0;">
					<i class="fa fa-plus-circle"></i> Добавьте услугу
				</h4>
			</div>
			<div class="modal-body" style="padding: 20px;">
				<p style="margin-bottom: 15px;">Объект создан. Выберите услуги для привязки:</p>
				<div id="serviceCheckboxList" style="max-height: 300px; overflow-y: auto;">
					<p class="text-muted text-center"><i class="fa fa-spinner fa-spin"></i> Загрузка услуг...</p>
				</div>
				<div id="noServicesMessage" style="display: none;">
					<p class="text-muted text-center">Нет доступных услуг для данного МП. Добавьте услугу вручную.</p>
				</div>
			</div>
			<div class="modal-footer" style="text-align: center;">
				<button type="button" class="btn btn-default" id="dismissServiceModalBtn">Закрыть</button>
				<button type="button" class="btn btn-primary" id="linkServicesBtn" disabled>
					<i class="fa fa-check"></i> Привязать выбранные
				</button>
				<button type="button" class="btn btn-default" id="addServiceManualBtn">
					<i class="fa fa-plus"></i> Добавить вручную
				</button>
			</div>
		</div>
	</div>
</div>
