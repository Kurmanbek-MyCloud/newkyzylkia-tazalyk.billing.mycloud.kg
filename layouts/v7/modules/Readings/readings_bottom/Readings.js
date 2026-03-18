document.addEventListener("DOMContentLoaded", function () {
  const tabItems = document.querySelectorAll(".tabs");
  const modal = document.getElementById("myModal");
  const modalBody = document.getElementById("modal-body");
  const span = document.getElementById("close");

  tabItems.forEach(function (item) {
    item.addEventListener("click", function () {
      const url = item.getAttribute("data-url");
      let record = null;

      if (url) {
        const params = url.split("&");

        params.forEach(function (param) {
          if (param.startsWith("record=")) {
            record = param.split("=")[1];
          }
        });

        if (record) {
          // console.log(`Record ID: ${record}`);

          const myHeaders = new Headers();
          myHeaders.append("Content-Type", "application/json");

          const raw = JSON.stringify({
            action: "getMetersInfo",
            estatesid: record,
          });

          const requestOptions = {
            method: "POST",
            headers: myHeaders,
            body: raw,
            redirect: "manual",
          };

          fetch(
            "/layouts/v7/modules/Readings/readings_bottom/php_requests/js_requests.php",
            requestOptions
          )
            .then((response) => response.json())
            .then((result) => {
              modalBody.innerHTML = "";
              // console.log(result);

              result.forEach(function (meter) {
                const meterContainer = document.createElement("div");
                meterContainer.classList.add("meter-container");
                meterContainer.setAttribute("data-metersid", meter.metersid);
                meterContainer.style.display = "flex";
                meterContainer.style.alignItems = "center";
                meterContainer.style.justifyContent = "space-between";
                meterContainer.style.margin = "10px 0";

                const meterNumber = document.createElement("div");
                meterNumber.textContent = `Номер счетчика: ${meter.meter_number} ▼`;
                meterNumber.classList.add("meter-number");
                meterNumber.style.cursor = "pointer";
                meterNumber.style.userSelect = "none";
                meterNumber.style.webkitUserSelect = "none";
                meterNumber.style.mozUserSelect = "none";
                meterNumber.style.msUserSelect = "none";
                meterNumber.style.outline = "none";
                meterNumber.style.border = "none";
                meterNumber.style.color = "#5A9BD5";
                meterNumber.style.fontSize = "15px";

                let lastReading = 0;
                if (meter.readings && meter.readings.length > 0) {
                  lastReading =
                    meter.readings[meter.readings.length - 1].meter_reading;
                }


                const inputField = document.createElement("input");
                inputField.type = "number";
                inputField.classList.add("reading-input");
                inputField.style.marginLeft = "10px";
                inputField.min = lastReading;
                inputField.style.fontSize = "15px";
                inputField.style.width = "120px";
                inputField.style.padding = "5px";
                inputField.max = Math.pow(10, meter.cf_number_digits) - 1; // Устанавливаем максимальное значение
                inputField.title = `Максимальное количество знаков: ${meter.cf_number_digits}`; // Подсказка при наведении

                // Добавляем валидацию при вводе
                inputField.addEventListener('input', function () {
                  const value = this.value;
                  if (value.length > meter.cf_number_digits) {
                    this.value = value.slice(0, meter.cf_number_digits);
                    alert(`Превышено максимальное количество знаков (${meter.cf_number_digits})`);
                  }
                });



                const dateInput = document.createElement("input");
                dateInput.type = "date";
                dateInput.classList.add("date-input");

                // Установить текущую дату по умолчанию
                const currentDate = new Date();
                const formattedDate = currentDate.toISOString().split("T")[0]; // Формат YYYY-MM-DD
                dateInput.value = formattedDate;

                dateInput.style.marginLeft = "10px";
                dateInput.style.fontSize = "14px";


                const addButton = document.createElement("button");
                addButton.textContent = "Добавить показание";
                addButton.classList.add("add-reading-btn");
                Object.assign(addButton.style, {
                  marginLeft: "10px",
                  backgroundColor: "#5a9bd5",
                  fontSize: "13px",
                  borderRadius: "5px", // Закругленные углы
                  cursor: "pointer", // Указатель при наведении
                  transition: "background-color 0.3s ease", // Плавный переход цвета фона
                  color: "white",
                  border: "none"
                });

                // Эффект наведения
                addButton.addEventListener("mouseover", () => {
                  addButton.style.backgroundColor = "#0f5491"; // Цвет фона при наведении
                });

                addButton.addEventListener("mouseout", () => {
                  addButton.style.backgroundColor = "#5a9bd5"; // Цвет фона по умолчанию
                });

                // Добавьте кнопку на страницу
                document.body.appendChild(addButton); // Или в другой элемент


                const redingid = document.querySelector(".redingid");

                addButton.addEventListener("click", function () {
                  const inputValue = parseFloat(inputField.value);
                  const selectedDate = dateInput.value;

                  const metersid = meter.metersid;
                  const metersLink = meter.cf_meter_object_link;
                  const userId = document.querySelector('.userid').textContent.trim();

                  // Проверка на количество знаков
                  if (inputValue.toString().length > meter.cf_number_digits) {
                    alert(`Превышено максимальное количество знаков (${meter.cf_number_digits})`);
                    return;
                  }

                  const data = {
                    action: 'createReadings',
                    inputValue: inputValue,
                    metersid: metersid,
                    userid: userId,
                    metersLink: metersLink,
                    date: selectedDate,
                    prevReading: lastReading
                  };

                  if (isNaN(inputValue)) {
                    alert('Вводимое значение должно быть числовым.');
                  } else if (inputValue < lastReading) {
                    confirmAction(
                      `Введенное вами значение (${inputValue}) меньше предыдущего (${lastReading}). <br> Вы уверены, что хотите записать новое значение?`,
                      () => {
                        addReading(data).then(() => {
                          lastReading = inputValue; // Обновляем lastReading
                          inputField.min = lastReading; // Устанавливаем новое минимальное значение для поля
                          inputField.value = ""; // Очищаем поле ввода
                        });
                      }

                    );
                  } else {
                    addReading(data).then(() => {
                      lastReading = inputValue; // Обновляем lastReading
                      inputField.min = lastReading; // Устанавливаем новое минимальное значение для поля
                      inputField.value = ""; // Очищаем поле ввода
                    });
                  }
                });


                meterContainer.appendChild(meterNumber);
                meterContainer.appendChild(inputField);
                meterContainer.appendChild(dateInput);
                meterContainer.appendChild(addButton);


                const readingsList = document.createElement("ul");
                readingsList.style.display = "none";
                readingsList.style.marginTop = "10px";

                const readingsContainer = document.createElement("div");
                readingsContainer.style.display = "none";      // по умолчанию скрыто (как было у ul)
                readingsContainer.style.marginTop = "10px";
                readingsContainer.classList.add("readings-container");

                // Создаём элемент таблицы
                const readingsTable = document.createElement("table");
                readingsTable.style.width = "100%";
                readingsTable.style.borderCollapse = "collapse";
                readingsTable.style.fontSize = "14px"; // можно указать общий размер шрифта

                // ===== ШАПКА ТАБЛИЦЫ =====
                const thead = document.createElement("thead");
                thead.innerHTML = `
                  <tr>
                    <th style="border-bottom:1px solid #ccc; padding:5px; text-align:center;">Показания</th>
                    <th style="border-bottom:1px solid #ccc; padding:5px; text-align:center;">Расход</th>
                    <th style="border-bottom:1px solid #ccc; padding:5px; text-align:center;">Использовано в счёте</th>
                    <th style="border-bottom:1px solid #ccc; padding:5px; text-align:center;">Дата</th>
                    <th style="border-bottom:1px solid #ccc; padding:5px; text-align:center;">Действия</th>
                  </tr>
                `;
                readingsTable.appendChild(thead);

                // ===== ТЕЛО ТАБЛИЦЫ =====
                const tbody = document.createElement("tbody");
                readingsTable.appendChild(tbody);

                // Если есть показания
                if (meter.readings && meter.readings.length > 0) {
                  meter.readings.forEach(function (reading, index) {
                    const dateText = reading.cf_reading_date ? reading.cf_reading_date : "нет";

                    // Расчёт расхода (разница между текущим и предыдущим показанием)
                    let consumption = "—";
                    if (index > 0) {
                      const prevValue = parseFloat(meter.readings[index - 1].meter_reading);
                      const currValue = parseFloat(reading.meter_reading);
                      consumption = currValue - prevValue;
                    }
                    // console.log(reading);

                    // Создаём строку под каждое показание
                    const row = document.createElement("tr");
                    row.setAttribute("data-readingsid", reading.readingsid); // чтобы потом находить при удалении/обновлении

                    // // Ячейка "Показания"
                    // const tdReading = document.createElement("td");
                    // tdReading.style.color = "rgb(9, 116, 216)";
                    // tdReading.style.padding = "5px";
                    // tdReading.style.textAlign = "center";      // <-- добавляем центрирование по горизонтали
                    // tdReading.style.verticalAlign = "middle";
                    // tdReading.style.fontSize = "16px";
                    // tdReading.textContent = reading.meter_reading;  // Только число, без "Показание:"
                    const tdReading = document.createElement("td");
                    tdReading.style.color = "rgb(9, 116, 216)";
                    tdReading.style.padding = "5px";
                    tdReading.style.textAlign = "center";
                    tdReading.style.verticalAlign = "middle";
                    tdReading.style.fontSize = "16px";

                    // Создаём ссылку
                    const link = document.createElement("a");
                    link.href = `/index.php?module=Readings&view=Detail&record=${reading.readingsid}`;
                    link.target = "_blank";
                    link.textContent = reading.meter_reading;
                    tdReading.appendChild(link);

                    // Ячейка "Расход"
                    const tdConsumption = document.createElement("td");
                    tdConsumption.style.padding = "5px";
                    tdConsumption.style.textAlign = "center";
                    tdConsumption.style.verticalAlign = "middle";
                    tdConsumption.style.fontSize = "16px";
                    tdConsumption.style.fontWeight = "bold";
                    tdConsumption.style.color = "#555";
                    tdConsumption.textContent = consumption;

                    // Ячейка "Использовано в счёте"
                    const tdUsed = document.createElement("td");
                    tdUsed.style.padding = "5px";
                    tdUsed.style.textAlign = "center";
                    tdUsed.style.verticalAlign = "middle";
                    tdUsed.style.fontSize = "16px";
                    tdUsed.textContent = reading.cf_used_in_bill; // "Да" / "Нет" / и т.д.
                    if (reading.cf_used_in_bill === "Да") {
                      tdUsed.style.color = "rgb(20, 121, 62)"; // зелёный
                    } else {
                      tdUsed.style.color = "rgb(231, 76, 60)"; // красный
                    }

                    // Ячейка "Дата"
                    const tdDate = document.createElement("td");
                    tdDate.style.color = "#ED7D31";
                    tdDate.style.padding = "5px";
                    tdDate.style.textAlign = "center";
                    tdDate.style.verticalAlign = "middle";
                    tdDate.style.fontSize = "16px";
                    tdDate.textContent = dateText; // Просто дата

                    // Ячейка "Действия" (иконки редактирования/удаления)
                    const tdActions = document.createElement("td");
                    tdActions.style.padding = "5px";
                    tdActions.style.textAlign = "center";
                    tdActions.style.verticalAlign = "middle";

                    // Кнопка "Редактировать"
                    const editBtn = document.createElement("button");
                    editBtn.classList.add("edit-btn");
                    editBtn.style.border = "none";
                    editBtn.style.backgroundColor = "#f5f5f5";
                    editBtn.innerHTML = `<img src="/layouts/v7/modules/Readings/icon.png" alt="Edit" style="width:16px;height:16px;">`;
                    editBtn.onclick = function () {
                      editReading(
                        reading.readingsid,
                        reading.meter_reading,
                        reading.cf_reading_date ? reading.cf_reading_date : "",
                        reading.cf_used_in_bill  // "Да" или "Нет"
                      );
                    };

                    // Кнопка "Удалить"
                    const deleteBtn = document.createElement("button");
                    deleteBtn.classList.add("edit-btn");
                    deleteBtn.style.border = "none";
                    deleteBtn.style.backgroundColor = "#f5f5f5";
                    deleteBtn.innerHTML = `<img src="/layouts/v7/modules/Readings/delete.png" alt="Delete" style="width:16px;height:16px;">`;
                    deleteBtn.onclick = function () {
                      deleteReading(reading.readingsid);
                    };

                    // Добавляем кнопки в ячейку "Действия"
                    tdActions.appendChild(editBtn);
                    tdActions.appendChild(deleteBtn);

                    // Складываем ячейки в строку
                    row.appendChild(tdReading);
                    row.appendChild(tdConsumption);
                    row.appendChild(tdUsed);
                    row.appendChild(tdDate);
                    row.appendChild(tdActions);

                    // Добавляем строку в начало тела таблицы (как у вас было `prepend`)
                    tbody.prepend(row);
                  });
                }

                // Добавляем таблицу в обёртку
                readingsContainer.appendChild(readingsTable);

                // Теперь вместо
                //   meterContainer.appendChild(readingsList);
                //   modalBody.appendChild(readingsList);
                // мы делаем
                modalBody.appendChild(meterContainer);
                modalBody.appendChild(readingsContainer);

                // А событие на клик по meterNumber (чтобы раскрывать/скрывать) меняем:
                //   if (readingsList.style.display === "none") -> if (readingsContainer.style.display === "none")
                // и т.п.
                meterNumber.addEventListener("click", function () {
                  if (readingsContainer.style.display === "none") {
                    readingsContainer.style.display = "block";
                    meterNumber.textContent = `Номер счетчика: ${meter.meter_number} ▲`;
                  } else {
                    readingsContainer.style.display = "none";
                    meterNumber.textContent = `Номер счетчика: ${meter.meter_number} ▼`;
                  }
                });
              });

              modal.style.display = "block";
            })
            .catch((error) => console.error(error));
        }
      }
    });
  });

  span.onclick = function () {
    modal.style.display = "none";
  };

  window.onclick = function (event) {
    if (event.target == modal) {
      modal.style.display = "none";
    }
  };
  window.editReading = function (readingsid, previousReading, previousDate, previousUsedInBill) {
    const modal = document.createElement("div");
    modal.style.position = "fixed";
    modal.style.top = "50%";
    modal.style.left = "50%";
    modal.style.transform = "translate(-50%, -50%)";
    modal.style.backgroundColor = "white";
    modal.style.padding = "20px";
    modal.style.border = "1px solid #ccc";
    modal.style.borderRadius = "8px";
    modal.style.boxShadow = "0px 0px 10px rgba(0, 0, 0, 0.1)";
    modal.style.zIndex = "1000";

    const defaultDate = previousDate ? previousDate : new Date().toISOString().split('T')[0];
    const previousReadingNumber = isNaN(previousReading) ? 0 : parseFloat(previousReading);
    const isChecked = (previousUsedInBill == "Да") ? "checked" : "";

    const modalContent = `
      <label for="newReading" style="display: block; margin-bottom: 10px;">
        Введите новое показание (не меньше предыдущего):
      </label>
      <input 
        id="newReading" 
        type="number" 
        min="${previousReadingNumber}" 
        value="${previousReadingNumber}" 
        style="display: block; margin-bottom: 10px; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" 
      />
      <label for="readingDate" style="display: block; margin-bottom: 10px;">
        Выберите дату:
      </label>
      <input 
        id="readingDate" 
        type="date" 
        value="${defaultDate}" 
        style="display: block; margin-bottom: 10px; width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" 
      />
       <label for="usedInBill" style="display: block; margin-bottom: 10px;">
      <input
        type="checkbox"
        id="usedInBill"
        ${isChecked}
      />
      Использовать в счёте?
    </label>
      <button id="saveReading" style="padding: 10px 20px; background-color: #5a9bd5; color: white; border: none; border-radius: 4px; cursor: pointer;">
        Сохранить
      </button>
      <button id="cancelEditing" style="padding: 10px 20px; margin-left: 10px; background-color: #ccc; color: black; border: none; border-radius: 4px; cursor: pointer;">
        Отмена
      </button>
    `;

    modal.innerHTML = modalContent;
    document.body.appendChild(modal);

    const saveButton = document.getElementById("saveReading");
    const cancelButton = document.getElementById("cancelEditing");

    saveButton.addEventListener("click", function () {
      const newReading = parseFloat(document.getElementById("newReading").value);
      const readingDate = document.getElementById("readingDate").value;
      const usedInBillCheckbox = document.getElementById("usedInBill");
      // Если чекбокс отмечен, используем "Да", иначе "Нет"
      const usedInBillValue = usedInBillCheckbox.checked ? "Да" : "Нет";
      const userId = document.querySelector('.userid').textContent.trim();

      const data = {
        action: 'updateReadings',
        readingsid: readingsid,
        newReading: newReading,
        readingDate: readingDate,
        userId: userId,
        usedInBill: usedInBillValue
      };
      // console.log(data);

      if (isNaN(newReading)) {
        alert('Вводимое значение должно быть числовым.');
      } else if (newReading < previousReading) {
        confirmAction(
          `Введенное вами значение (${newReading}) меньше предыдущего (${previousReading}). <br> Вы уверены, что хотите записать новое значение?`,
          () => updateReading(data)
        );
      } else {
        updateReading(data);
      }
    });

    cancelButton.addEventListener("click", function () {
      document.body.removeChild(modal);
    });
  };



  window.deleteReading = function (readingsid) {
    const confirmed = confirm('Вы уверены, что хотите удалить это показание?');

    if (confirmed) {
      const userId = document.querySelector('.userid').textContent.trim();
      const myHeaders = new Headers();
      myHeaders.append("Content-Type", "application/json");
      const raw = JSON.stringify({
        action: "deleteReadings",
        readingsid: readingsid,
        userId: userId
      });

      const requestOptions = {
        method: "POST",
        headers: myHeaders,
        body: raw,
        redirect: "manual",
      };
      fetch(
        "/layouts/v7/modules/Readings/readings_bottom/php_requests/js_requests.php",
        requestOptions
      )
        .then((response) => response.json())
        .then((result) => {
          if (result.status === "True") {
            console.log("Показание успешно удалено!");

            // Удаляем элемент из DOM
            const readingRow = document.querySelector(`tr[data-readingsid='${readingsid}']`);
            if (readingRow) {
              readingRow.remove();
            }
          } else {
            alert("Ошибка при удалении показания: " + result.message);
          }
        })
        .catch((error) => {
          console.error("Ошибка при удалении показания:", error);
          alert("Произошла ошибка при удалении показания.");
        });

    } else {
      console.log('Удаление отменено');

    }
  };

});


// Основная функция для добавления нового показания
function addReading(data) {
  const url = '/layouts/v7/modules/Readings/readings_bottom/php_requests/js_requests.php';

  return fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
  })
    .then((response) => response.json())
    .then((responseData) => {
      if (responseData.status === 'Ok') {
        // Найти контейнер счётчика
        const modalBody = document.getElementById("modal-body");
        const meterContainer = modalBody.querySelector(
          `.meter-container[data-metersid='${data.metersid}']`
        );

        // Найти div.readings-container (она идёт следом за meterContainer в вашем коде)
        // Или можно искать через nextElementSibling, но нужно убедиться,
        // что это именно div, а не что-то другое
        const readingsContainer = meterContainer
          ? meterContainer.nextElementSibling
          : null;

        if (!readingsContainer || !readingsContainer.classList.contains("readings-container")) {
          console.error("Не найден .readings-container для metersid=", data.metersid);
          return;
        }

        // Внутри readingsContainer найти <table> и <tbody>
        const readingsTable = readingsContainer.querySelector("table");
        const tbody = readingsTable ? readingsTable.querySelector("tbody") : null;

        if (!tbody) {
          console.error("Таблица или <tbody> не найдены в readingsContainer");
          return;
        }

        // Создаём новую строку <tr>
        const newRow = document.createElement("tr");
        newRow.setAttribute("data-readingsid", responseData.readings_id);

        // Ячейка "Показания"
        const tdReading = document.createElement("td");
        tdReading.style.color = "rgb(9, 116, 216)";
        tdReading.style.padding = "5px";
        tdReading.style.textAlign = "center";
        tdReading.style.verticalAlign = "middle";
        tdReading.style.fontSize = "16px";
        const link = document.createElement("a");
        link.href = `/index.php?module=Readings&view=Detail&record=${responseData.readings_id}`;
        link.target = "_blank";
        link.textContent = data.inputValue;

        tdReading.appendChild(link);

        // Ячейка "Расход"
        const tdConsumption = document.createElement("td");
        tdConsumption.style.padding = "5px";
        tdConsumption.style.textAlign = "center";
        tdConsumption.style.verticalAlign = "middle";
        tdConsumption.style.fontSize = "16px";
        tdConsumption.style.fontWeight = "bold";
        tdConsumption.style.color = "#555";
        const consumptionValue = data.prevReading ? (data.inputValue - data.prevReading) : "—";
        tdConsumption.textContent = consumptionValue;

        // Ячейка "Использовано в счёте" – если при добавлении
        // всегда «Нет», то:
        const tdUsed = document.createElement("td");
        // tdUsed.style.color = "rgb(9, 116, 216)";
        tdUsed.style.padding = "5px";
        tdUsed.style.textAlign = "center";
        tdUsed.style.verticalAlign = "middle";
        tdUsed.style.fontSize = "16px";
        // Если на момент добавления нет отдельного чекбокса, подставим "Нет":
        tdUsed.textContent = "Нет";
        tdUsed.style.color = "rgb(231, 76, 60)";


        // Ячейка "Дата"
        const tdDate = document.createElement("td");
        tdDate.style.color = "#ED7D31";
        tdDate.style.padding = "5px";
        tdDate.style.textAlign = "center";
        tdDate.style.verticalAlign = "middle";
        tdDate.style.fontSize = "16px";
        tdDate.textContent = data.date;

        // Ячейка "Действия"
        const tdActions = document.createElement("td");
        tdActions.style.padding = "5px";
        tdActions.style.textAlign = "center";
        tdActions.style.verticalAlign = "middle";

        // Кнопка "Редактировать"
        const editBtn = document.createElement("button");
        editBtn.classList.add("edit-btn");
        editBtn.style.border = "none";
        editBtn.style.backgroundColor = "#f5f5f5";
        editBtn.innerHTML = `<img src="/layouts/v7/modules/Readings/icon.png" alt="Edit" style="width:16px;height:16px;">`;
        editBtn.onclick = function () {
          editReading(
            responseData.readings_id,
            data.inputValue,
            data.date,
            "Нет" // При добавлении вы сейчас жёстко ставите "Нет", 
            // если нужно, подставьте другое значение
          );
        };

        // Кнопка "Удалить"
        const deleteBtn = document.createElement("button");
        deleteBtn.classList.add("edit-btn");
        deleteBtn.style.border = "none";
        deleteBtn.style.backgroundColor = "#f5f5f5";
        deleteBtn.innerHTML = `<img src="/layouts/v7/modules/Readings/delete.png" alt="Delete" style="width:16px;height:16px;">`;
        deleteBtn.onclick = function () {
          deleteReading(responseData.readings_id);
        };

        tdActions.appendChild(editBtn);
        tdActions.appendChild(deleteBtn);

        newRow.appendChild(tdReading);
        newRow.appendChild(tdConsumption);
        newRow.appendChild(tdUsed);
        newRow.appendChild(tdDate);
        newRow.appendChild(tdActions);

        // Добавляем новую строку в начало
        tbody.prepend(newRow);

        // Показать контейнер, если был скрыт
        readingsContainer.style.display = "block";
        // Если таблица скрывается по table.style.display = "none"
        //   readingsTable.style.display = "table";
      } else {
        alert(responseData.message || 'Ошибка при создании показания.');
      }
    })
    .catch((error) => {
      console.error('Ошибка при отправке данных:', error);
      alert('Ошибка при отправке данных.');
    });
}

// Функция подтверждения действия
function confirmAction(message, onConfirm) {
  const modal = document.createElement('div');
  modal.style.position = 'fixed';
  modal.style.top = '50%';
  modal.style.left = '50%';
  modal.style.transform = 'translate(-50%, -50%)';
  modal.style.backgroundColor = 'white';
  modal.style.padding = '20px';
  modal.style.border = '1px solid #ccc';
  modal.style.borderRadius = '8px';
  modal.style.boxShadow = '0px 0px 10px rgba(0, 0, 0, 0.1)';
  modal.style.zIndex = '1000';

  modal.innerHTML = `
      <p style="margin-bottom: 20px;">${message}</p>
      <button id="confirmYes" style="padding: 10px 20px; background-color: #5a9bd5; color: white; border: none; border-radius: 4px; cursor: pointer;">
        Да
      </button>
      <button id="confirmNo" style="padding: 10px 20px; margin-left: 10px; background-color: #ccc; color: black; border: none; border-radius: 4px; cursor: pointer;">
        Отмена
      </button>
    `;

  document.body.appendChild(modal);

  document.getElementById('confirmYes').addEventListener('click', () => {
    document.body.removeChild(modal);
    onConfirm();
  });

  document.getElementById('confirmNo').addEventListener('click', () => {
    document.body.removeChild(modal);
  });
}

// Функция обновления существующего показания
function updateReading(data) {
  const url = '/layouts/v7/modules/Readings/readings_bottom/php_requests/js_requests.php';

  fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  })
    .then((response) => response.json())
    .then((responseData) => {
      if (responseData.status === 'True') {
        // Находим нужную строку <tr>
        const readingRow = document.querySelector(`tr[data-readingsid='${data.readingsid}']`);
        if (readingRow) {
          // Допустим, "Использовано в счёте" (вторая колонка) мы не меняем,
          // а дата и показание берём из data.newReading / data.readingDate
          // Если нужно обновлять и cf_used_in_bill, возьмите из responseData
          // или из data (зависит от логики на бэке).
          const usedInBillColor = (data.usedInBill === "Да")
            ? "rgb(20, 121, 62)"    // зелёный
            : "rgb(231, 76, 60)";    // красный
          // Сохраняем текущее значение расхода перед перезаписью
          const existingConsumption = readingRow.cells[1] ? readingRow.cells[1].textContent : "—";
          // Полностью пересоздаём содержимое
          readingRow.innerHTML = `
            <!-- Показания -->
             <td style="color: rgb(9, 116, 216); padding: 5px; text-align: center; vertical-align: middle;font-size: 16px;">
              <a
                href="/index.php?module=Readings&view=Detail&record=${data.readingsid}"
                target="_blank"
              >
                ${data.newReading}
              </a>
            </td>

            <!-- Расход -->
            <td style="color: #555; padding: 5px; text-align: center; vertical-align: middle; font-size: 16px; font-weight: bold;">${existingConsumption}</td>

            <!-- "Использовано в счёте" -->
            <td style="color: ${usedInBillColor}; padding: 5px; text-align: center; vertical-align: middle;font-size: 16px;">${data.usedInBill}</td>

            <!-- Дата -->
            <td style="color: #ED7D31; padding: 5px; text-align: center; vertical-align: middle;font-size: 16px;">${data.readingDate}</td>

            <!-- Действия -->
            <td style="padding: 5px; text-align: center; vertical-align: middle;">
              <button class="edit-btn" style="border: none; background-color: #f5f5f5;"
                onclick="editReading(${data.readingsid}, ${data.newReading}, '${data.readingDate}', '${data.usedInBill}')">
                <img src="/layouts/v7/modules/Readings/icon.png" 
                     alt="Edit" style="width:16px;height:16px;">
              </button>
              <button class="delete-btn" style="border: none; background-color: #f5f5f5;"
                onclick="deleteReading(${data.readingsid})">
                <img src="/layouts/v7/modules/Readings/delete.png" 
                     alt="Delete" style="width:16px;height:16px;">
              </button>
            </td>
          `;

          // Закрываем всплывшее окно редактирования (если оно у вас через JS-модалку)
          const modal = document.querySelector('div[style*="position: fixed"][style*="top: 50%"][style*="left: 50%"]');
          if (modal) modal.remove();

        } else {
          console.error("Не найдена строка <tr> с data-readingsid=", data.readingsid);
        }

      } else {
        alert('Ошибка при обновлении показания: ' + responseData.message);
      }
    })
    .catch((error) => {
      console.error('Ошибка при отправке данных:', error);
      alert('Ошибка при обновлении показания.');
    });
}