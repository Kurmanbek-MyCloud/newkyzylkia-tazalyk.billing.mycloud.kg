// app.js
// Конфигурация загружается из config.js
const BASE_API_URL = window.CONFIG?.BASE_API_URL || "./cotroller.php";
const CHECK_PASS_URL = window.CONFIG?.CHECK_PASS_URL || "../checkPass.php";

const loadingScreen = document.getElementById("loadingMessage");
const loginScreen = document.getElementById("loginScreen");
const searchScreen = document.getElementById("searchScreen");
const resultsScreen = document.getElementById("resultsScreen");

const loginForm = document.getElementById("loginForm");
const messageDiv = document.getElementById("message");
const languageSelector = document.getElementById("languageSelector");
const loginHeading = document.getElementById("loginHeading");
const loginButton = document.getElementById("loginButton");
const logoutButtonSearch = document.getElementById("logoutButtonSearch");
const logoutButtonLogin = document.getElementById("logoutButtonLogin");
let controllerNameDisplay = null; // Будет инициализирован после загрузки DOM

const searchForm = document.getElementById("searchForm");
const searchMessageDiv = document.getElementById("searchMessage");
const searchFIO = document.getElementById("searchFIO");
const searchAccount = document.getElementById("searchAccount");
const searchMunicipalEnterprise = document.getElementById("searchMunicipalEnterprise");

const backToSearchButton = document.getElementById("backToSearchButton");
const searchResultsList = document.getElementById("searchResultsList");
const resultsCountSpan = document.getElementById("resultsCount");
// resultsMessageDiv убран
const searchHeading = document.getElementById("searchHeading");
const resultsHeading = document.getElementById("resultsHeading");

const paginationContainer = document.getElementById("pagination");
const prevPageButton = document.getElementById("prevPageButton");
const nextPageButton = document.getElementById("nextPageButton");
const pageInfoSpan = document.getElementById("pageInfo");

let clientPhoneIdentifier = null;
let allFoundSubscribers = [];
let availableServices = [];
const ITEMS_PER_PAGE = 10;
let currentPage = 1;
// Языковая система вынесена в отдельный файл language.js

// Вспомогательная функция для безопасного получения переводов
function getTranslationSafe(key, fallback = null) {
  if (window.LanguageSystem?.getTranslation) {
    return window.LanguageSystem.getTranslation(key);
  }

  // Если language.js не загружен, возвращаем fallback или ключ
  return fallback || key;
}

async function safeJsonParse(response) {
  try {
    const text = await response.text();


    if (!text.trim()) {
      throw new Error("Пустой ответ от сервера");
    }

    return JSON.parse(text);
  } catch (error) {
    console.error("JSON parse error:", error);
    throw new Error("Неверный формат ответа от сервера");
  }
}

// Функция updateContentLanguage() перенесена в language.js

function showScreen(screenId) {
  document.querySelectorAll(".screen").forEach((screen) => {
    screen.classList.remove("active");
    screen.classList.remove("active");
  });
  const targetScreen = document.getElementById(screenId);
  if (targetScreen) {
    targetScreen.classList.add("active");
    // Стили применяются через CSS класс .screen.active
  }
}

function getOrCreatePhoneIdentifier() {
  try {
    let id = localStorage.getItem("client_phone_identifier");

    if (!id || id.trim() === "") {
      id = "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (c) {
        var r = (Math.random() * 16) | 0,
          v = c == "x" ? r : (r & 0x3) | 0x8;
        return v.toString(16);
      });

      localStorage.setItem("client_phone_identifier", id);
      // Отладочные сообщения убраны
    } else {
      // Отладочные сообщения убраны
    }

    return id;
  } catch (error) {
    console.error("Ошибка при работе с localStorage:", error);
    return "temp-" + Date.now() + "-" + Math.random().toString(36).substr(2, 9);
  }
}

function displayMessage(element, text, type) {
  if (!element) return;

  element.textContent = text;
  element.className = "message";
  if (type) {
    element.classList.add(type);
  }
  if (type === "success" || type === "error") {
    setTimeout(() => {
      element.textContent = "";
      element.className = "message";
    }, 5000);
  }
}

function setControllerName(name, userId = '1') {
  // Всегда ищем элемент заново для надежности
  const element = document.getElementById("controllerNameDisplay");

  if (element) {
    element.textContent = name;
    element.setAttribute('data-user-id', userId);

    // Принудительно устанавливаем стили через JavaScript
    element.style.color = "#000000";
    element.style.fontWeight = "600";

    // Адаптивный размер шрифта
    const isMobile = window.innerWidth <= 768;
    const isSmallMobile = window.innerWidth <= 480;

    if (isSmallMobile) {
      element.style.fontSize = "0.8rem";
    } else if (isMobile) {
      element.style.fontSize = "0.85rem";
    } else {
      element.style.fontSize = "0.95rem";
    }

    element.style.display = "block";
    element.style.visibility = "visible";
    element.style.opacity = "1";
  }
}

// Универсальная система модальных окон
const ModalSystem = {
  // Скрытие клавиатуры на мобильных
  hideKeyboard(callback) {
    const activeElement = document.activeElement;
    if (activeElement && (activeElement.tagName === 'INPUT' || activeElement.tagName === 'SELECT' || activeElement.tagName === 'TEXTAREA')) {
      activeElement.blur();
    }

    if (window.innerWidth <= 768) {
      const tempInput = document.createElement('input');
      Object.assign(tempInput.style, {
        position: 'absolute', left: '-9999px', opacity: '0',
        pointerEvents: 'none', height: '0', width: '0',
        border: 'none', outline: 'none'
      });
      document.body.appendChild(tempInput);

      let originalType = null;
      if (activeElement?.tagName === 'INPUT' && activeElement.type !== 'hidden') {
        originalType = activeElement.type;
        activeElement.type = 'hidden';
      }

      tempInput.focus();
      setTimeout(() => {
        tempInput.blur();
        document.body.removeChild(tempInput);
        if (activeElement && originalType) activeElement.type = originalType;
        setTimeout(callback, 100);
      }, 200);
    } else {
      callback();
    }
  },

  // Создание модального окна
  createModal(id, type, message, buttons) {
    // Удаляем существующее модальное окно
    const existing = document.getElementById(id);
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = id;
    modal.className = 'modal-overlay';

    const content = document.createElement('div');
    content.className = `modal-content ${type}-content`;

    const messageEl = document.createElement('div');
    messageEl.className = `modal-message ${type}-message`;
    messageEl.textContent = message;

    const buttonContainer = document.createElement('div');
    buttonContainer.className = 'modal-buttons';

    buttons.forEach(button => {
      const btn = document.createElement('button');
      btn.className = `modal-button ${button.class || ''}`;
      btn.textContent = button.text;
      btn.addEventListener('click', () => {
        modal.remove();
        if (button.callback) button.callback();
      });
      buttonContainer.appendChild(btn);
    });

    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.remove();
        const cancelBtn = buttons.find(b => b.class?.includes('cancel'));
        if (cancelBtn?.callback) cancelBtn.callback();
      }
    });

    content.appendChild(messageEl);
    content.appendChild(buttonContainer);
    modal.appendChild(content);
    document.body.appendChild(modal);

    // Фокус на первой кнопке
    const firstButton = buttonContainer.querySelector('button');
    if (firstButton) firstButton.focus();
  },

  // Показать alert
  alert(message) {
    this.hideKeyboard(() => {
      this.createModal('customModal', 'alert', message, [
        { text: 'OK', class: 'primary' }
      ]);
    });
  },

  // Показать confirm
  confirm(message, onConfirm, onCancel) {
    this.hideKeyboard(() => {
      this.createModal('customModal', 'confirm', message, [
        { text: 'Отмена', class: 'cancel', callback: onCancel },
        { text: 'Да', class: 'primary', callback: onConfirm }
      ]);
    });
  }
};

// Упрощенные функции для обратной совместимости
function showAlertWithKeyboardHide(message) {
  ModalSystem.alert(message);
}

function showCustomAlert(message) {
  ModalSystem.alert(message);
}

function showCustomConfirm(message, onConfirm, onCancel) {
  ModalSystem.confirm(message, onConfirm, onCancel);
}

async function checkPhoneAuth() {
  // Инициализируем языковую систему
  if (typeof window.LanguageSystem !== 'undefined') {
    window.LanguageSystem.initLanguageSystem();
  }

  clientPhoneIdentifier = getOrCreatePhoneIdentifier();

  if (!clientPhoneIdentifier || clientPhoneIdentifier.trim() === "") {
    console.error("Failed to get or create phone identifier");
    displayMessage(messageDiv, window.LanguageSystem.getTranslationSafe("login_error_id_not_defined"), "error");
    showScreen("loginScreen");
    return;
  }

  try {
    const response = await fetch(BASE_API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "checkAuth",
        phoneIdentifier: clientPhoneIdentifier
      }),
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await safeJsonParse(response);


    if (data.success && data.data !== "empty" && data.data.username) {
      setControllerName(data.data.fullname || data.data.username, data.data.vtiger_user_id || '1');
      showScreen("searchScreen");
      loadServicesDropdown();
    } else {
      showScreen("loginScreen");
    }
  } catch (error) {
    console.error("Ошибка при проверке авторизации по идентификатору телефона:", error);
    displayMessage(messageDiv, getTranslationSafe("login_error_auth_check"), "error");
    showScreen("loginScreen");
  }
}

if (loginForm) {
  loginForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    showScreen("loadingMessage");
    displayMessage(messageDiv, "", "");

    const login = document.getElementById("login").value;
    const password = document.getElementById("password").value;

    if (!clientPhoneIdentifier) {
      displayMessage(messageDiv, window.LanguageSystem.getTranslationSafe("login_error_id_not_defined"), "error");
      showScreen("loginScreen");
      return;
    }

    try {
      const responseHash = await fetch(CHECK_PASS_URL, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `username=${encodeURIComponent(login)}&password=${encodeURIComponent(password)}`,
      });

      if (!responseHash.ok) {
        throw new Error(`HTTP ${responseHash.status}: ${responseHash.statusText}`);
      }

      const dataHash = await safeJsonParse(responseHash);

      if (dataHash.success && dataHash.result) {
        const receivedHash = dataHash.result;

        const responseAuth = await fetch(BASE_API_URL, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            action: "checkUser",
            username: login,
            hashed_password: receivedHash
          }),
        });

        if (!responseAuth.ok) {
          throw new Error(`HTTP ${responseAuth.status}: ${responseAuth.statusText}`);
        }

        const dataAuth = await safeJsonParse(responseAuth);


        if (dataAuth.success) {
          const userVtigerId = dataAuth.user_id;
          const userFullname = dataAuth.fullname;

          const checkAuthResponse = await fetch(BASE_API_URL, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              action: "checkAuth",
              phoneIdentifier: clientPhoneIdentifier
            }),
          });

          if (!checkAuthResponse.ok) {
            throw new Error(`HTTP ${checkAuthResponse.status}: ${checkAuthResponse.statusText}`);
          }

          const checkAuthData = await safeJsonParse(checkAuthResponse);

          let authPhoneResponse;
          if (checkAuthData.success && checkAuthData.data !== "empty") {
            authPhoneResponse = await fetch(BASE_API_URL, {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                action: "updateAuth",
                phoneIdentifier: clientPhoneIdentifier,
                vtiger_user_id: userVtigerId
              }),
            });
          } else {
            authPhoneResponse = await fetch(BASE_API_URL, {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                action: "addAuth",
                phoneIdentifier: clientPhoneIdentifier,
                vtiger_user_id: userVtigerId
              }),
            });
          }

          if (!authPhoneResponse.ok) {
            throw new Error(`HTTP ${authPhoneResponse.status}: ${authPhoneResponse.statusText}`);
          }

          const authPhoneData = await safeJsonParse(authPhoneResponse);

          if (authPhoneData.success) {
            displayMessage(messageDiv, getTranslationSafe("login_success"), "success");
            setControllerName(userFullname || login, userVtigerId || '1');
            showScreen("searchScreen");
            loadServicesDropdown();
          } else {
            displayMessage(messageDiv, authPhoneData.message || getTranslationSafe("login_error_auth_failed"), "error");
            showScreen("loginScreen");
          }
        } else {
          displayMessage(messageDiv, dataAuth.message || getTranslationSafe("login_error_auth_failed"), "error");
          showScreen("loginScreen");
        }
      } else {
        displayMessage(messageDiv, dataHash.message || getTranslationSafe("login_error_hash_failed"), "error");
        showScreen("loginScreen");
      }
    } catch (error) {
      console.error("Произошла критическая ошибка при входе:", error);
      displayMessage(messageDiv, getTranslationSafe("login_error_critical"), "error");
      showScreen("loginScreen");
    }
  });
}

function handleLogout() {
  // Показываем подтверждение выхода
  const confirmMessage = getTranslationSafe("logout_confirm_message");
  showCustomConfirm(confirmMessage, () => {
    // Пользователь подтвердил выход
    performLogout();
  });
  // Функция не завершается здесь, ждем ответа пользователя
}

function performLogout() {

  if (!clientPhoneIdentifier) {
    console.warn("Не могу выйти: Идентификатор устройства не установлен.");
    showScreen("loginScreen");
    return;
  }

  fetch(BASE_API_URL, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      action: "logout",
      phoneIdentifier: clientPhoneIdentifier
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        localStorage.removeItem("client_phone_identifier");
        clientPhoneIdentifier = null;
        location.reload();
      } else {
        displayMessage(messageDiv, data.message || getTranslationSafe("logout_error"), "error");
        localStorage.removeItem("client_phone_identifier");
        clientPhoneIdentifier = null;
        location.reload();
      }
    })
    .catch((error) => {
      console.error("Ошибка при отправке запроса на выход:", error);
      displayMessage(messageDiv, getTranslationSafe("logout_error_network"), "error");
      localStorage.removeItem("client_phone_identifier");
      clientPhoneIdentifier = null;
      location.reload();
    });
}

function handleMunicipalLogout() {
  // Показываем подтверждение выхода из муниципального предприятия
  const confirmMessage = getTranslationSafe("municipal_logout_confirm_message");
  showCustomConfirm(confirmMessage, () => {
    // Пользователь подтвердил выход
    performMunicipalLogout();
  });
  // Функция не завершается здесь, ждем ответа пользователя
}

function performMunicipalLogout() {

  // Отправляем уведомление в Flutter о полном выходе
  if (window.flutter_inappwebview) {
    window.flutter_inappwebview.callHandler("onMunicipalLogout");
  }

  // Очищаем все данные
  localStorage.removeItem("client_phone_identifier");
  clientPhoneIdentifier = null;
}

if (logoutButtonSearch) {
  logoutButtonSearch.addEventListener("click", handleLogout);
}

if (logoutButtonLogin) {
  logoutButtonLogin.addEventListener("click", handleMunicipalLogout);
}

if (backToSearchButton) {
  backToSearchButton.addEventListener("click", () => {
    showScreen("searchScreen");
    if (searchResultsList) searchResultsList.innerHTML = "";
    allFoundSubscribers = [];
    currentPage = 1;
    if (resultsCountSpan) resultsCountSpan.textContent = "";
    // resultsMessageDiv убран

    if (searchFIO) searchFIO.value = "";
    if (searchAccount) searchAccount.value = "";
    if (searchMunicipalEnterprise) searchMunicipalEnterprise.value = "";
  });
}

async function loadServicesDropdown() {
  try {
    const response = await fetch(BASE_API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "getServices"
      }),
    });
    const data = await response.json();

    if (data.success && data.services && data.services.length > 0) {
      availableServices = data.services;

    } else {
      console.warn("Услуги не найдены");
      availableServices = [];
    }
  } catch (error) {
    console.error("Ошибка при загрузке услуг:", error);
    availableServices = [];
  }
}

if (searchForm) {
  searchForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    displayMessage(searchMessageDiv, "", "");

    if (searchResultsList) searchResultsList.innerHTML = "";
    allFoundSubscribers = [];
    currentPage = 1;

    const mpId = searchMunicipalEnterprise ? searchMunicipalEnterprise.value.trim() : "";
    const fio = searchFIO ? searchFIO.value.trim() : "";
    const accountNumber = searchAccount ? searchAccount.value.trim() : "";

    // Отладочные сообщения убраны

    // Проверка: МП должно быть выбрано обязательно
    if (!mpId || mpId === "") {
      showAlertWithKeyboardHide(getTranslationSafe("search_message_select_mp"));
      return;
    }



    const searchParams = {
      action: "searchSubscribers",
      mp_id: mpId,
      fio: fio,
      account_number: accountNumber,
    };

    // Убираем сообщение "Выполняется поиск"

    try {
      const response = await fetch(BASE_API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(searchParams),
      });

      const data = await response.json();


      if (data.success) {
        if (data.data && data.data.length > 0) {
          allFoundSubscribers = data.data;
          if (resultsCountSpan) {
            resultsCountSpan.textContent = `(${allFoundSubscribers.length} ${getTranslationSafe("results_count_found")})`;
          }
          showScreen("resultsScreen");
          renderCurrentPageResults();
          // resultsMessageDiv убран
        } else {
          allFoundSubscribers = [];
          if (resultsCountSpan) {
            resultsCountSpan.textContent = `(0 ${getTranslationSafe("results_count_found")})`;
          }
          showScreen("resultsScreen");
          renderCurrentPageResults();
          // resultsMessageDiv убран
        }
      } else {
        displayMessage(searchMessageDiv, data.message || getTranslationSafe("search_message_error"), "error");
      }
    } catch (error) {
      console.error("Ошибка сети при поиске:", error);
      displayMessage(searchMessageDiv, getTranslationSafe("search_message_network_error"), "error");
    }
  });
}

// Функция processPayment удалена - используется прямая логика в обработчиках кнопок

// Функция для создания платежа после подтверждения от Flutter
async function createPaymentAfterFlutterConfirmation(paymentData) {
  try {
    // Отладочная информация
    console.log("🔍 Получены данные от Flutter:", paymentData);

    // Проверяем обязательные поля
    if (!paymentData.ls || !paymentData.service_id || !paymentData.amount) {
      console.error("❌ Отсутствуют обязательные поля:", paymentData);
      errorWaitingPayment("Отсутствуют обязательные данные для платежа");
      return;
    }

    const requestData = {
      action: "processPayment",
      ls: paymentData.ls,
      service_id: paymentData.service_id,
      service: paymentData.service,
      amount: paymentData.amount,
      payment_type: "terminal",
      date: paymentData.date,
      user_id: paymentData.user_id
    };

    console.log("📤 Отправляем данные на сервер:", requestData);

    const response = await fetch(BASE_API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(requestData),
    });

    const result = await response.json();
    console.log("📥 Ответ сервера:", result);

    if (result.success) {
      // Успешно создали платеж - закрываем модальное окно и очищаем поля
      completeWaitingPayment();
      showAlertWithKeyboardHide(getTranslationSafe("payment_terminal_success"));
    } else {
      // Ошибка создания платежа
      console.error("❌ Ошибка сервера:", result.message);
      errorWaitingPayment("Ошибка при создании платежа: " + result.message);
    }
  } catch (error) {
    console.error("Ошибка при создании платежа после Flutter:", error);
    errorWaitingPayment("Ошибка при создании платежа");
  }
}

// Глобальные функции для Flutter
window.createPaymentAfterFlutterConfirmation = createPaymentAfterFlutterConfirmation;
window.errorWaitingPayment = errorWaitingPayment;

// Глобальная переменная для хранения данных ожидающего платежа
let currentWaitingPayment = null;

// Функция показа модального окна ожидания
function showPaymentWaitingModal(paymentData, button, details) {
  // Сохраняем данные для последующего использования
  currentWaitingPayment = {
    paymentData: paymentData,
    button: button,
    details: details
  };

  // Создаем модальное окно
  const modal = document.createElement("div");
  modal.id = "paymentWaitingModal";

  const content = document.createElement("div");
  content.className = "payment-waiting-content";

  const title = document.createElement("h2");
  title.textContent = getTranslationSafe("payment_waiting_title");
  title.className = "payment-waiting-title";

  const message = document.createElement("p");
  message.innerHTML = `
    <strong>Оплатите через терминал:</strong><br>
    • QR-код<br>
    • Банковскую карту<br><br>
    <strong>Сумма:</strong> ${paymentData.amount} сом<br>
    <strong>Услуга:</strong> ${paymentData.service}<br>
    <strong>Лицевой счет:</strong> ${paymentData.ls}
  `;
  message.className = "payment-waiting-message";

  const spinner = document.createElement("div");
  spinner.className = "payment-waiting-spinner";

  const statusText = document.createElement("p");
  statusText.textContent = getTranslationSafe("payment_waiting_status");
  statusText.className = "payment-waiting-status";

  const cancelBtn = document.createElement("button");
  cancelBtn.textContent = getTranslationSafe("payment_waiting_cancel");
  cancelBtn.className = "button button-danger payment-waiting-cancel";
  cancelBtn.onclick = () => {
    // Отменяем платеж
    cancelWaitingPayment();
    modal.remove();
  };

  content.appendChild(title);
  content.appendChild(message);
  content.appendChild(spinner);
  content.appendChild(statusText);
  content.appendChild(cancelBtn);
  modal.appendChild(content);
  document.body.appendChild(modal);

  // Блокируем кнопку
  button.disabled = true;
  button.textContent = getTranslationSafe("payment_waiting_button");
}

// Функция отмены ожидающего платежа
function cancelWaitingPayment() {
  if (currentWaitingPayment) {
    // Разблокируем кнопку
    currentWaitingPayment.button.disabled = false;
    currentWaitingPayment.button.textContent = getTranslationSafe("accept_payment_button_terminal");

    // Очищаем данные
    currentWaitingPayment = null;

    showAlertWithKeyboardHide(getTranslationSafe("payment_cancelled"));
  }
}

// Функция успешного завершения платежа
function completeWaitingPayment() {
  if (currentWaitingPayment) {
    // Очищаем поля
    currentWaitingPayment.details.querySelector(`.amount-input`).value = "";
    currentWaitingPayment.details.querySelector(`.service-select`).selectedIndex = 0;

    // Разблокируем кнопку
    currentWaitingPayment.button.disabled = false;
    currentWaitingPayment.button.textContent = getTranslationSafe("accept_payment_button_terminal");

    // Удаляем модальное окно
    const modal = document.getElementById("paymentWaitingModal");
    if (modal) {
      modal.remove();
    }

    // Очищаем данные
    currentWaitingPayment = null;
  }
}

// Функция ошибки платежа
function errorWaitingPayment(errorMessage) {
  if (currentWaitingPayment) {
    // Разблокируем кнопку
    currentWaitingPayment.button.disabled = false;
    currentWaitingPayment.button.textContent = getTranslationSafe("accept_payment_button_terminal");

    // Удаляем модальное окно
    const modal = document.getElementById("paymentWaitingModal");
    if (modal) {
      modal.remove();
    }

    // Очищаем данные
    currentWaitingPayment = null;

    showAlertWithKeyboardHide(getTranslationSafe("payment_terminal_error") + errorMessage);
  }
}

// Делаем функцию глобальной для доступа из language.js
window.renderCurrentPageResults = function renderCurrentPageResults() {
  if (!searchResultsList) return;

  searchResultsList.innerHTML = "";
  const totalPages = Math.ceil(allFoundSubscribers.length / ITEMS_PER_PAGE);

  // Обновляем информацию о странице
  if (pageInfoSpan) {
    pageInfoSpan.textContent = `${currentPage} / ${totalPages === 0 ? 1 : totalPages}`;
  }

  // Управляем кнопками пагинации
  if (prevPageButton) prevPageButton.disabled = currentPage === 1;
  if (nextPageButton) nextPageButton.disabled = currentPage === totalPages || totalPages === 0;

  // Показываем/скрываем пагинацию
  if (allFoundSubscribers.length === 0) {
    if (paginationContainer) paginationContainer.classList.add("hidden");
    if (resultsCountSpan) resultsCountSpan.textContent = `(0 ${getTranslationSafe("results_count_found")})`;
    // resultsMessageDiv убран
    return;
  } else {
    // Показываем пагинацию только если больше 1 страницы
    if (paginationContainer) {
      if (totalPages > 1) {
        paginationContainer.classList.remove("hidden");
      } else {
        paginationContainer.classList.add("hidden");
      }
    }
    // resultsMessageDiv убран
  }

  const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
  const endIndex = startIndex + ITEMS_PER_PAGE;
  const subscribersToRender = allFoundSubscribers.slice(startIndex, endIndex);

  if (subscribersToRender.length === 0 && allFoundSubscribers.length > 0 && currentPage > 1) {
    currentPage = totalPages;
    renderCurrentPageResults();
    return;
  }

  subscribersToRender.forEach((subscriber) => {
    const subscriberCard = document.createElement("div");
    subscriberCard.className = "subscriber-card";

    const header = document.createElement("div");
    header.className = "subscriber-header";
    header.innerHTML = `
      <span>${subscriber.account_number || getTranslationSafe("data_not_available")} &bull; ${subscriber.full_name || getTranslationSafe("data_unknown")}</span>
      <span class="toggle-icon">▶</span>
    `;
    subscriberCard.appendChild(header);

    const details = document.createElement("div");
    details.className = "subscriber-details";

    let serviceOptionsHtml = `<option value="">${getTranslationSafe("select_service")}</option>`;
    if (availableServices.length > 0) {
      availableServices.forEach((service) => {
        serviceOptionsHtml += `<option value="${service.id}">${service.name}</option>`;
      });
    } else {
      serviceOptionsHtml += `<option value="" disabled>Услуги не найдены</option>`;
    }

    details.innerHTML = `
      <div class="detail-row">
        <span class="detail-label">${getTranslationSafe("ls_label")}</span>
        <span class="detail-value">${subscriber.account_number || getTranslationSafe("data_not_available")}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">${getTranslationSafe("fio_label")}</span>
        <span class="detail-value">${subscriber.full_name || getTranslationSafe("data_not_available")}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">${getTranslationSafe("locality_label")}</span>
        <span class="detail-value">${subscriber.locality || getTranslationSafe("data_not_available")}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">${getTranslationSafe("street_label")}</span>
        <span class="detail-value">${subscriber.street || getTranslationSafe("data_not_available")}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">${getTranslationSafe("house_label")}</span>
        <span class="detail-value">${subscriber.house || getTranslationSafe("data_not_available")}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">${getTranslationSafe("flat_label")}</span>
        <span class="detail-value">${subscriber.flat || getTranslationSafe("data_not_available")}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">${getTranslationSafe("balance_label")}</span>
        <span class="detail-value ${subscriber.balance && parseFloat(subscriber.balance) < 0 ? 'negative-balance' : ''}">${subscriber.balance || getTranslationSafe("data_not_available")}</span>
      </div>
      <div class="service-section">
        <select id="service-${subscriber.account_number}" class="service-select" name="service">
          ${serviceOptionsHtml}
        </select>
        <input type="number" id="amount-${subscriber.account_number
      }" class="amount-input" name="amount" placeholder="${getTranslationSafe("enter_amount")}" step="0.01" min="0">
        <div class="button-group">
          <button type="button" class="button pay-button" data-payment-type="cash">
            💵 ${getTranslationSafe("accept_payment_button_cash")}
          </button>
          <button type="button" class="button pay-button" data-payment-type="terminal">
            💳 ${getTranslationSafe("accept_payment_button_terminal")}
          </button>
        </div>
      </div>
    `;
    subscriberCard.appendChild(details);

    header.addEventListener("click", () => {
      subscriberCard.classList.toggle("active");
      // Стили применяются через CSS класс .subscriber-card.active
      const icon = header.querySelector(".toggle-icon");
      icon.textContent = subscriberCard.classList.contains("active") ? "▼" : "▶";
    });

    const payButtons = details.querySelectorAll(".pay-button");
    payButtons.forEach((button) => {
      button.addEventListener("click", async (e) => {
        const selectedServiceId = details.querySelector(`.service-select`).value;
        const enteredAmount = parseFloat(details.querySelector(`.amount-input`).value);
        const paymentType = e.target.dataset.paymentType;
        const serviceName = details.querySelector(`.service-select`).options[details.querySelector(`.service-select`).selectedIndex].text;
        const controllerName = controllerNameDisplay ? controllerNameDisplay.textContent.trim() : "";

        if (!selectedServiceId) {
          showAlertWithKeyboardHide(getTranslationSafe("validation_select_service"));
          return;
        }
        if (isNaN(enteredAmount) || enteredAmount <= 0) {
          showAlertWithKeyboardHide(getTranslationSafe("validation_enter_amount"));
          return;
        }

        // Блокируем кнопку
        button.disabled = true;
        button.textContent = getTranslationSafe("payment_processing_button");

        try {
          if (paymentType === "cash") {
            // Для наличных - сразу создаем платеж в базе
            const finalUserId = controllerNameDisplay ? controllerNameDisplay.getAttribute('data-user-id') || '1' : '1';

            const paymentData = {
              action: "processPayment",
              ls: subscriber.account_number,
              service_id: selectedServiceId,
              service: serviceName,
              amount: enteredAmount,
              payment_type: "cash",
              date: new Date().toISOString().split("T")[0],
              controllerName: controllerName,
              user_id: finalUserId
            };

            const response = await fetch(BASE_API_URL, {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(paymentData),
            });

            const result = await response.json();


            if (result.success) {
              showAlertWithKeyboardHide(getTranslationSafe("payment_success_message"));

              // Очищаем поля
              details.querySelector(`.amount-input`).value = "";
              details.querySelector(`.service-select`).selectedIndex = 0;

              // НЕ отправляем в Flutter - сразу создали платеж
            } else {
              showAlertWithKeyboardHide(getTranslationSafe("payment_error_message"));
            }

          } else if (paymentType === "terminal") {
            // Для терминала/QR - отправляем в Flutter и ждем ответа
            const finalUserIdTerminal = controllerNameDisplay ? controllerNameDisplay.getAttribute('data-user-id') || '1' : '1';

            const paymentData = {
              ls: subscriber.account_number,
              service_id: selectedServiceId,
              service: serviceName,
              amount: enteredAmount,
              payment_type: "terminal",
              date: new Date().toISOString().split("T")[0],
              controllerName: controllerName,
              user_id: finalUserIdTerminal
            };

            // Отправляем данные в Flutter
            if (window.flutter_inappwebview) {
              // Показываем модальное окно ожидания
              showPaymentWaitingModal(paymentData, button, details);

              // Отправляем данные в Flutter
              window.flutter_inappwebview.callHandler("onPayment", paymentData);
            } else {
              showAlertWithKeyboardHide(getTranslationSafe("payment_flutter_error"));
            }
          }
        } catch (error) {
          console.error("Ошибка сети:", error);
          showAlertWithKeyboardHide(getTranslationSafe("payment_network_error"));
        } finally {
          // Разблокируем кнопку только для наличных (для терминала кнопка управляется модальным окном)
          if (paymentType === "cash") {
            button.disabled = false;
            button.textContent = getTranslationSafe("accept_payment_button_cash");
          }
          // Для терминала кнопка разблокируется в модальном окне
        }
      });
    });

    searchResultsList.appendChild(subscriberCard);
  });

  // Автоматически открываем карточку, если найден только один абонент
  if (subscribersToRender.length === 1) {
    const singleCard = searchResultsList.querySelector('.subscriber-card');
    if (singleCard) {
      singleCard.classList.add('active');
      const icon = singleCard.querySelector('.toggle-icon');
      if (icon) {
        icon.textContent = '▼';
      }
    }
  }
}

if (prevPageButton) {
  prevPageButton.addEventListener("click", () => {
    if (currentPage > 1) {
      currentPage--;
      renderCurrentPageResults();
    }
  });
}

if (nextPageButton) {
  nextPageButton.addEventListener("click", () => {
    const totalPages = Math.ceil(allFoundSubscribers.length / ITEMS_PER_PAGE);
    if (currentPage < totalPages) {
      currentPage++;
      renderCurrentPageResults();
    }
  });
}

document.addEventListener("DOMContentLoaded", () => {
  // Инициализируем элементы после загрузки DOM
  controllerNameDisplay = document.getElementById("controllerNameDisplay");

  // Сбрасываем селект муниципального предприятия в пустое состояние
  const municipalSelect = document.getElementById("searchMunicipalEnterprise");
  if (municipalSelect) {
    municipalSelect.selectedIndex = -1; // Ничего не выбрано
    municipalSelect.setAttribute('data-selected', 'false');

    // Добавляем обработчик изменения селекта
    municipalSelect.addEventListener('change', () => {
      if (municipalSelect.selectedIndex >= 0) {
        municipalSelect.setAttribute('data-selected', 'true');
      } else {
        municipalSelect.setAttribute('data-selected', 'false');
      }
    });
  }

  // Отключаем стандартную валидацию формы
  const searchForm = document.getElementById("searchForm");
  if (searchForm) {
    searchForm.addEventListener('submit', (e) => {
      e.preventDefault(); // Предотвращаем стандартную отправку формы
      // Наша логика поиска уже обрабатывается в отдельном обработчике
    });
  }

  checkPhoneAuth();
});
