// $(document).ready(function () {
//     // Обработка изменения фильтров
//     $('#system-select, #start-date, #end-date').on('change', function () {
//         filterRows();
//     });
//     // Обработка сортировки
//     $('.sortable').on('click', function () {
//         const sortBy = $(this).data('sort');
//         const rows = $('#payments-table tbody tr').get();
//         const isAscending = $(this).hasClass('asc');
//         rows.sort(function (a, b) {
//             const valA = $(a).find(`td[data-key="${sortBy}"]`).text().toLowerCase();
//             const valB = $(b).find(`td[data-key="${sortBy}"]`).text().toLowerCase();

//             if ($.isNumeric(valA) && $.isNumeric(valB)) {
//                 return (isAscending ? valA - valB : valB - valA);
//             } else {
//                 return (isAscending ? valA.localeCompare(valB) : valB.localeCompare(valA));
//             }
//         });
//         $.each(rows, function (index, row) {
//             $('#payments-table tbody').append(row);
//         });
//         // Сбрасываем классы сортировки для всех столбцов
//         $('.sortable').removeClass('asc desc active');
//         $(this).addClass(isAscending ? 'desc' : 'asc').addClass('active'); // Добавляем нужные классы
//         updateRowNumbers(); // Обновляем номера строк
//         updateTotalAmount(); // Обновляем общую сумму
//     });
//     function filterRows() {
//         const selectedSystem = $('#system-select').val();
//         const startDate = $('#start-date').val();
//         const endDate = $('#end-date').val();
//         $('#payments-table tbody tr').each(function () {
//             const rowSystem = $(this).data('system');
//             const rowDate = $(this).data('date');
//             let showRow = true;
//             if (selectedSystem && rowSystem !== selectedSystem) {
//                 showRow = false;
//             }
//             if (showRow && startDate) {
//                 showRow = new Date(rowDate) >= new Date(startDate);
//             }
//             if (showRow && endDate) {
//                 showRow = new Date(rowDate) <= new Date(endDate);
//             }
//             $(this).toggle(showRow);
//         });
//         updateRowNumbers(); // Обновляем номера строк
//         updateTotalAmount(); // Обновляем общую сумму
//     }
//     function updateRowNumbers() {
//         $('#payments-table tbody tr:visible').each(function (index) {
//             $(this).find('td[data-key="number"]').text(index + 1); // Устанавливаем номер строки
//         });
//     }
//     function updateTotalAmount() {
//         let totalAmount = 0;
//         // Суммируем видимые строки
//         $('#payments-table tbody tr:visible').each(function () {
//             let amount = parseFloat($(this).find('td[data-key="amount"]').text());
//             if (!isNaN(amount)) {
//                 totalAmount += amount;
//             }
//         });
//         // Обновляем значение суммы на странице
//         $('#total-amount').text(totalAmount.toFixed(2));
//     }
//     // Экспорт в Excel
//     $('#excel_export').click(function () {
//         // Получаем текущие параметры сортировки и фильтрации
//         let system = $('#system-select').val();
//         let startDate = $('#start-date').val();
//         let endDate = $('#end-date').val();
//         let sortColumn = $('.sortable.active').data('sort'); // колонка сортировки
//         let sortOrder = $('.sortable.active').hasClass('asc') ? 'desc' : 'asc'; // порядок сортировки
//         // Формируем URL для запроса на сервер
//         let url = 'excel_export.php';
//         let params = {
//             system: system,
//             start_date: startDate,
//             end_date: endDate,
//             sort_column: sortColumn,
//             sort_order: sortOrder
//         };
//         // Переходим по URL с параметрами для генерации отчета
//         let query = $.param(params); // Преобразуем объект параметров в строку запроса
//         window.location.href = url + '?' + query;
//     });
// });