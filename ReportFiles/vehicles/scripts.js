// $(document).ready(function () {
//     // Обработка изменения фильтров
//     $('#start-date, #end-date').on('change', function () {
//         filterRows();
//     });

//     // Обработка сортировки
//     $('.sortable').on('click', function () {
//         const sortBy = $(this).data('sort');
//         const rows = $('#vehicle-table tbody tr').get();
//         const isAscending = $(this).hasClass('asc');

//         // Сортировка строк
//         rows.sort(function (a, b) {
//             const columnIndex = $(this).index() + 1; // Получаем индекс столбца
//             const valA = $(a).find('td:nth-child(' + columnIndex + ')').text().toLowerCase();
//             const valB = $(b).find('td:nth-child(' + columnIndex + ')').text().toLowerCase();

//             if ($.isNumeric(valA) && $.isNumeric(valB)) {
//                 return (isAscending ? valA - valB : valB - valA);
//             } else {
//                 return (isAscending ? valA.localeCompare(valB) : valB.localeCompare(valA));
//             }
//         }.bind(this));

//         $.each(rows, function (index, row) {
//             $('#vehicle-table tbody').append(row);
//         });

//         // Обновляем классы сортировки для всех столбцов
//         $('.sortable').removeClass('asc desc active');
//         $(this).addClass(isAscending ? 'desc' : 'asc').addClass('active'); // Добавляем нужные классы

//         updateRowNumbers(); // Обновляем номера строк
//         updateTotalAmount(); // Обновляем общую сумму
//     });

//     function filterRows() {
//         const startDate = $('#start-date').val();
//         const endDate = $('#end-date').val();
//         const paidChecked = $('#paid-filter').is(':checked');
//         const unpaidChecked = $('#unpaid-filter').is(':checked');
//         let visibleCount = 0; // Счетчик видимых строк

//         $('#vehicle-table tbody tr').each(function () {
//             const rowDate = $(this).find('td:nth-child(2)').text(); // Дата въезда
//             const status = $(this).find('td:nth-child(7)').text(); // Статус оплаты (предполагаем, что он в 7-ом столбце)
//             let showRow = true;

//             if (startDate && new Date(rowDate) < new Date(startDate)) {
//                 showRow = false;
//             }
//             if (showRow && endDate && new Date(rowDate) > new Date(endDate)) {
//                 showRow = false;
//             }

//             // Фильтрация по статусу оплаты
//             if (showRow && status == 'Оплачен' && !paidChecked) {
//                 showRow = false;
//             }
//             if (showRow && status == 'Не оплачен' && !unpaidChecked) {
//                 showRow = false;
//             }

//             $(this).toggle(showRow);

//             // Если строка видима, увеличиваем счетчик
//             if ($(this).is(':visible')) {
//                 visibleCount++;
//             }
//         });

//         updateRowNumbers(); // Обновляем номера строк
//         updateTotalAmount(); // Обновляем общую сумму
//         $('#total-count').text(visibleCount); // Обновляем общее количество
//     }

//     function updateRowNumbers() {
//         $('#vehicle-table tbody tr:visible').each(function (index) {
//             $(this).find('td:first').text(index + 1); // Устанавливаем номер строки
//         });
//     }

//     function updateTotalAmount() {
//         let totalAmount = 0;

//         // Суммируем видимые строки
//         $('#vehicle-table tbody tr:visible').each(function () {
//             let amount = parseFloat($(this).find('td:nth-child(8)').text()); // Предполагаем, что сумма в восьмом столбце
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
//         let startDate = $('#start-date').val();
//         let endDate = $('#end-date').val();
//         let sortColumn = $('.sortable.active').data('sort'); // колонка сортировки
//         let sortOrder = $('.sortable.active').hasClass('asc') ? 'asc' : 'desc'; // порядок сортировки

//         // Формируем URL для запроса на сервер
//         let url = 'excel_export.php';
//         let params = {
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