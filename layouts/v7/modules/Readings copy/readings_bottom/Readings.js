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
            "https://ktzh.billing.mycloud.kg/layouts/v7/modules/Readings/readings_bottom/php_requests/js_requests.php",
            requestOptions
          )
            .then((response) => response.json())
            .then((result) => {
              modalBody.innerHTML = "";
              console.log(result);

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
                  const selectedDate = dateInput.value; // Получение значения выбранной даты

                  const metersid = meter.metersid;

                  const metersLink = meter.cf_meter_object_link;
                  const userId = document.querySelector('.userid').textContent.trim();

                  const data = {
                    action: 'createReadings',
                    inputValue: inputValue,
                    metersid: metersid,
                    userid: userId,
                    metersLink: metersLink,
                    date: selectedDate
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

                if (meter.readings && meter.readings.length > 0) {
                  meter.readings.forEach(function (reading) {
                    const dateText = reading.cf_reading_date ? reading.cf_reading_date : "нет";

                    const readingItem = document.createElement("li");
                    readingItem.setAttribute("data-readingsid", reading.readingsid); // Добавляем data-readingsid

                    readingItem.innerHTML = `
                      <span style="color: #70AD47;">Показание: ${reading.meter_reading}</span>, 
                      <span style="color:rgb(9, 116, 216);">Использовано в счете: ${reading.cf_used_in_bill}</span>, 
                      <span style="color: #ED7D31;">Дата показания: ${dateText}</span>
                      <button class="edit-btn" style="border: none; background-color: #f5f5f5;" 
                        onclick="editReading(${reading.readingsid}, ${reading.meter_reading}, '${reading.cf_reading_date ? reading.cf_reading_date : ''}')">
                        <img src="https://ktzh.billing.mycloud.kg/layouts/v7/modules/Readings/icon.png" alt="Edit" style="width: 16px; height: 16px;">
                      </button>
                      <button class="edit-btn" style="border: none; background-color: #f5f5f5;" 
                        onclick="deleteReading(${reading.readingsid})">
                        <img src="https://ktzh.billing.mycloud.kg/layouts/v7/modules/Readings/delete.png" alt="Delete" style="width: 16px; height: 16px;"> 
                      </button>
                    `;

                    readingItem.classList.add("reading-item");
                    readingItem.style.fontSize = "14px";

                    readingsList.prepend(readingItem);
                  });
                }


                meterNumber.addEventListener("click", function () {
                  if (readingsList.style.display === "none") {
                    readingsList.style.display = "block";
                    meterNumber.textContent = `Номер счетчика: ${meter.meter_number} ▲`;
                  } else {
                    readingsList.style.display = "none";
                    meterNumber.textContent = `Номер счетчика: ${meter.meter_number} ▼`;
                  }
                });

                modalBody.appendChild(meterContainer);
                modalBody.appendChild(readingsList);
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
  window.editReading = function (readingsid, previousReading, previousDate) {
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
      const userId = document.querySelector('.userid').textContent.trim();

      const data = {
        action: 'updateReadings',
        readingsid: readingsid,
        newReading: newReading,
        readingDate: readingDate,
        userId: userId
      };

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
        "https://ktzh.billing.mycloud.kg/layouts/v7/modules/Readings/readings_bottom/php_requests/js_requests.php",
        requestOptions
      )
        .then((response) => response.json())
        .then((result) => {
          if (result.status === "True") {
            console.log("Показание успешно удалено!");

            // Удаляем элемент из DOM
            const readingItem = document.querySelector(`li[data-readingsid='${readingsid}']`);
            if (readingItem) {
              readingItem.remove();
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
  const url = 'https://ktzh.billing.mycloud.kg/layouts/v7/modules/Readings/readings_bottom/php_requests/js_requests.php';

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
        // alert('Показание успешно создано.');

        // Найти список <ul>, где нужно добавить новое показание
        const modalBody = document.getElementById("modal-body");
        const meterContainer = modalBody.querySelector(
          `.meter-container[data-metersid='${data.metersid}']`
        );
        const readingsList = meterContainer.nextElementSibling; // <ul>, следующий за .meter-container

        if (readingsList && readingsList.tagName === "UL") {
          // Создаем новый элемент списка
          const newReadingItem = document.createElement("li");
          newReadingItem.setAttribute("data-readingsid", responseData.readings_id);
          newReadingItem.classList.add("reading-item");
          newReadingItem.style.fontSize = "14px";

          newReadingItem.innerHTML = `
            <span style="color: #70AD47;">Показание: ${data.inputValue}</span>, 
            <span style="color:rgb(9, 116, 216);">Использовано в счете: Нет</span>,
            <span style="color: #ED7D31;">Дата показания: ${data.date}</span>
            <button class="edit-btn" style="border: none; background-color: #f5f5f5;" 
              onclick="editReading(${responseData.readings_id}, ${data.inputValue}, '${data.date}')">
              <img src="https://ktzh.billing.mycloud.kg/layouts/v7/modules/Readings/icon.png" alt="Edit" style="width: 16px; height: 16px;">
            </button>
            <button class="delete-btn" style="border: none; background-color: #f5f5f5;" 
              onclick="deleteReading(${responseData.readings_id})">
              <img src="https://ktzh.billing.mycloud.kg/layouts/v7/modules/Readings/delete.png" alt="Delete" style="width: 16px; height: 16px;">
            </button>
          `;

          // Добавляем новое показание в начало списка
          readingsList.prepend(newReadingItem);

          // Убедиться, что список отображается
          readingsList.style.display = "block";
        } else {
          console.error("Список показаний (UL) не найден.");
        }
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

  const url = 'https://ktzh.billing.mycloud.kg/layouts/v7/modules/Readings/readings_bottom/php_requests/js_requests.php';

  fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
  })
    .then((response) => response.json())
    .then((responseData) => {
      if (responseData.status === 'True') {
        // alert('Показание успешно обновлено.');

        // Обновляем DOM
        const readingItem = document.querySelector(`li[data-readingsid='${data.readingsid}']`);
        if (readingItem) {
          readingItem.innerHTML = `
              <span style="color: #70AD47;">Показание: ${data.newReading}</span>, 
              <span style="color: #ED7D31;">Дата показания: ${data.readingDate}</span>
              <button class="edit-btn" style="border: none; background-color: #f5f5f5;" 
                onclick="editReading(${data.readingsid}, ${data.newReading}, '${data.readingDate}')">
                <img src="https://ktzh.billing.mycloud.kg/layouts/v7/modules/Readings/icon.png" alt="Edit" style="width: 16px; height: 16px;">
              </button>
              <button class="edit-btn" style="border: none; background-color: #f5f5f5;" 
                onclick="deleteReading(${data.readingsid})">
                <img src="https://ktzh.billing.mycloud.kg/layouts/v7/modules/Readings/delete.png" alt="Delete" style="width: 16px; height: 16px;"> 
              </button>
            `;
          const modal = document.querySelector('div[style*="position: fixed"][style*="top: 50%"][style*="left: 50%"]');

          if (modal) {
            modal.remove(); // Удаляем модальное окно из DOM
          } else {
            console.error("Модальное окно не найдено.");
          }
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
