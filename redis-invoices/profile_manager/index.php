<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профили генерации счетов</title>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #f8f8f8;
            font-family: 'Lato', 'Open Sans', Helvetica, Arial, sans-serif;
            font-size: 13px;
            color: #333;
            min-height: 100vh;
        }

        /* ===== MODULE BAR ===== */
        .vt-module-bar {
            background: #fff;
            border-bottom: 1px solid #ddd;
            padding: 0 20px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .vt-module-bar-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .vt-module-title {
            font-weight: 700;
            font-size: 13px;
            color: #333;
            text-transform: uppercase;
        }
        .vt-breadcrumb-sep {
            color: #ccc;
            font-size: 14px;
        }
        .vt-breadcrumb-link {
            color: #999;
            text-decoration: none;
            font-size: 13px;
        }
        .vt-breadcrumb-link:hover { color: #333; }
        .vt-module-bar-right {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        /* ===== CONTENT AREA ===== */
        .vt-content {
            padding: 12px 20px;
            max-width: 1050px;
            margin: 0 auto;
        }

        /* ===== BUTTONS (vtiger outline style) ===== */
        .vt-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            font-family: 'Lato', sans-serif;
            font-size: 12px;
            font-weight: 600;
            border-radius: 2px;
            border: 1px solid #ccc;
            background: #fff;
            color: #555;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
            line-height: 1.6;
            white-space: nowrap;
        }
        .vt-btn:hover {
            background: #f5f5f5;
            border-color: #aaa;
            color: #333;
        }
        .vt-btn-primary {
            background: #3cb878;
            border-color: #3cb878;
            color: #fff;
        }
        .vt-btn-primary:hover {
            background: #35a56c;
            border-color: #35a56c;
            color: #fff;
        }
        .vt-btn-sm {
            padding: 3px 10px;
            font-size: 11px;
        }

        /* ===== PANELS ===== */
        .vt-panel {
            background: #fff;
            border: 1px solid #ddd;
            margin-bottom: 12px;
        }
        .vt-panel-head {
            padding: 8px 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .vt-panel-head h3 {
            font-size: 14px;
            font-weight: 700;
            color: #333;
            margin: 0;
        }
        .vt-panel-body {
            padding: 15px;
        }

        /* ===== TABLES (vtiger listview) ===== */
        .vt-table {
            width: 100%;
            border-collapse: collapse;
        }
        .vt-table th {
            padding: 10px 12px;
            font-size: 12px;
            font-weight: 600;
            color: #888;
            text-align: left;
            border-bottom: 1px solid #ddd;
            background: #fff;
        }
        .vt-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            color: #555;
            vertical-align: middle;
            font-size: 13px;
        }
        .vt-table tbody tr:hover td {
            background: #f9f9f9;
        }
        .vt-table .vt-actions {
            white-space: nowrap;
        }
        .vt-table .vt-actions span {
            padding: 0 6px;
            color: #aaa;
            cursor: pointer;
            font-size: 14px;
            transition: color 0.15s;
        }
        .vt-table .vt-actions span:hover {
            color: #333;
        }
        .vt-name-link {
            color: #3498db;
            font-weight: 600;
            cursor: pointer;
        }
        .vt-name-link:hover {
            color: #2980b9;
            text-decoration: underline;
        }

        /* ===== FORMS ===== */
        .vt-form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .vt-form-field {
            flex: 1;
            min-width: 200px;
        }
        .vt-form-field label {
            display: block;
            font-size: 12px;
            color: #888;
            margin-bottom: 4px;
        }
        .vt-input {
            width: 100%;
            padding: 6px 10px;
            font-size: 13px;
            font-family: 'Lato', sans-serif;
            border: 1px solid #ccc;
            border-radius: 1px;
            color: #333;
            background: #fff;
        }
        .vt-input:focus {
            outline: none;
            border-color: #3cb878;
        }
        select.vt-input {
            height: 32px;
        }

        /* ===== BADGES ===== */
        .vt-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 2px;
            font-size: 11px;
            font-weight: 600;
        }
        .vt-badge-fixed { background: #e3f2fd; color: #1565c0; }
        .vt-badge-residents { background: #f3e5f5; color: #7b1fa2; }
        .vt-badge-meters { background: #e8f5e9; color: #2e7d32; }
        .vt-badge-area { background: #fff3e0; color: #e65100; }
        .vt-badge-period { background: #eceff1; color: #455a64; }

        /* ===== SERVICE RULES ===== */
        .vt-svc-row {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 6px;
            padding: 6px 8px;
            background: #fafafa;
            border: 1px solid #eee;
        }
        .vt-svc-row select { flex: 1; }
        .vt-svc-row .vt-svc-calc { max-width: 180px; }
        .vt-svc-row .vt-btn-remove {
            background: none;
            border: none;
            color: #e74c3c;
            font-size: 15px;
            cursor: pointer;
            padding: 2px 6px;
        }
        .vt-svc-row .vt-btn-remove:hover { color: #c0392b; }

        /* ===== TEST RESULT ===== */
        .vt-test-result { margin-top: 12px; }
        .vt-test-result table {
            width: 100%;
            border-collapse: collapse;
        }
        .vt-test-result th {
            background: #2c3b49;
            color: #fff;
            padding: 8px 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
        }
        .vt-test-result td {
            padding: 7px 12px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            color: #555;
        }
        .vt-test-result .meter-detail {
            font-size: 11px;
            color: #888;
            padding-left: 24px;
            background: #fafafa;
        }
        .vt-test-result .total-row {
            font-weight: 700;
            background: #f5f5f5;
            color: #333;
        }
        .vt-test-result .total-row td {
            border-top: 2px solid #2c3b49;
        }

        /* ===== UTILITY ===== */
        .section-hidden { display: none; }
        .vt-loading { text-align: center; padding: 30px; color: #999; }
        .vt-divider { border: none; border-top: 1px solid #eee; margin: 12px 0; }
        .vt-estate-info {
            padding: 8px 12px;
            background: #fafafa;
            border: 1px solid #eee;
            margin-bottom: 8px;
            font-size: 13px;
            color: #555;
        }
        .vt-estate-info strong { color: #333; }
        .vt-alert-error {
            padding: 8px 12px;
            background: #fdf0ef;
            border-left: 3px solid #e74c3c;
            color: #c0392b;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .vt-alert-success {
            padding: 8px 12px;
            background: #eafaf1;
            border-left: 3px solid #3cb878;
            color: #2e7d32;
            font-weight: 600;
            text-align: center;
            margin-top: 8px;
        }

        @media (max-width: 768px) {
            .vt-form-row { flex-direction: column; }
            .vt-svc-row { flex-direction: column; }
            .vt-svc-row .vt-svc-calc { max-width: 100%; }
        }
    </style>
</head>
<body>

    <!-- MODULE BAR -->
    <div class="vt-module-bar">
                <div class="vt-module-bar-left">
                    <span class="vt-module-title" id="pageTitle">ПРОФИЛИ ГЕНЕРАЦИИ</span>
                    <span class="vt-breadcrumb-sep">&rsaquo;</span>
                    <a href="/redis-invoices/invoiceModal.php" class="vt-breadcrumb-link">Генерация</a>
                </div>
                <div class="vt-module-bar-right" id="barActions">
                    <button class="vt-btn vt-btn-primary" onclick="showEditor(null)"><i class="fa fa-plus"></i> Создать профиль</button>
                </div>
            </div>

    <!-- CONTENT -->
    <div class="vt-content">

        <!-- SECTION 1: Profile List -->
                <div id="sectionList">
                    <div class="vt-panel">
                        <div id="profilesLoading" class="vt-loading"><i class="fa fa-spinner fa-spin"></i> Загрузка...</div>
                        <table class="vt-table" id="profilesTable" style="display:none;">
                            <thead>
                                <tr>
                                    <th>Название</th>
                                    <th>Группа</th>
                                    <th>Услуги</th>
                                    <th>Период показаний</th>
                                    <th style="width:110px;">Действия</th>
                                </tr>
                            </thead>
                            <tbody id="profilesBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- SECTION 2: Profile Editor -->
                <div id="sectionEditor" class="section-hidden">
                    <div class="vt-panel">
                        <div class="vt-panel-head">
                            <h3 id="editorTitle"><i class="fa fa-plus"></i> Новый профиль</h3>
                            <button class="vt-btn" onclick="showList()"><i class="fa fa-arrow-left"></i> Назад</button>
                        </div>
                        <div class="vt-panel-body">
                            <div class="vt-form-row">
                                <div class="vt-form-field">
                                    <label>Название профиля</label>
                                    <input type="text" class="vt-input" id="profileName" placeholder="Например: Тамчы ТазаСуу">
                                </div>
                                <div class="vt-form-field" style="max-width:250px;">
                                    <label>Группа (МП)</label>
                                    <select class="vt-input" id="profileGroup"></select>
                                </div>
                                <div class="vt-form-field" style="max-width:220px;">
                                    <label>Период показаний</label>
                                    <select class="vt-input" id="profilePeriod">
                                        <option value="next_month">След. месяц закрывает</option>
                                        <option value="last_two">Последние 2 показания</option>
                                        <option value="same_month">В том же месяце</option>
                                    </select>
                                </div>
                            </div>
                            <div class="vt-form-row">
                                <div class="vt-form-field" style="max-width:400px;">
                                    <label>Фильтр по аккаунту (необязательно)</label>
                                    <select class="vt-input" id="profileAccount">
                                        <option value="">-- Без фильтра --</option>
                                    </select>
                                </div>
                            </div>

                            <hr class="vt-divider">

                            <div style="font-size:13px;font-weight:700;color:#333;margin-bottom:8px;">
                                <i class="fa fa-list-ol"></i> Правила услуг
                            </div>
                            <div id="serviceRules"></div>
                            <button class="vt-btn vt-btn-sm" onclick="addServiceRule()" style="margin-top:6px;">
                                <i class="fa fa-plus"></i> Добавить услугу
                            </button>

                            <hr class="vt-divider">

                            <div style="display: flex; gap: 8px;">
                                <button class="vt-btn vt-btn-primary" onclick="saveProfile()"><i class="fa fa-check"></i> Сохранить</button>
                                <button class="vt-btn" onclick="showTestWindow()" style="color:#3cb878;border-color:#3cb878;">
                                    <i class="fa fa-flask"></i> Тестировать
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: Test Window -->
                <div id="sectionTest" class="section-hidden">
                    <div class="vt-panel">
                        <div class="vt-panel-head">
                            <h3><i class="fa fa-flask"></i> Тестирование расчёта</h3>
                            <button class="vt-btn" onclick="hideTestWindow()"><i class="fa fa-arrow-left"></i> К редактору</button>
                        </div>
                        <div class="vt-panel-body">
                            <div class="vt-form-row">
                                <div class="vt-form-field">
                                    <label>ID или Лицевой счёт объекта</label>
                                    <input type="number" class="vt-input" id="testEstateId" placeholder="50910">
                                </div>
                                <div class="vt-form-field">
                                    <label>Месяц</label>
                                    <select class="vt-input" id="testMonth">
                                        <option>Январь</option><option>Февраль</option><option>Март</option>
                                        <option>Апрель</option><option>Май</option><option>Июнь</option>
                                        <option>Июль</option><option>Август</option><option>Сентябрь</option>
                                        <option>Октябрь</option><option>Ноябрь</option><option>Декабрь</option>
                                    </select>
                                </div>
                                <div class="vt-form-field">
                                    <label>Год</label>
                                    <select class="vt-input" id="testYear"></select>
                                </div>
                            </div>
                            <button class="vt-btn vt-btn-primary" id="btnRunTest" onclick="runTest()"><i class="fa fa-play"></i> Запустить тест</button>
                        </div>
                    </div>

                    <div id="testResult" class="vt-test-result"></div>
                </div>

    </div>

<script>
var API_URL = 'api.php';
var allGroups = [];
var allServices = [];
var allAccounts = [];
var currentFilename = null;

document.addEventListener('DOMContentLoaded', function() {
    var now = new Date();
    var yearSelect = document.getElementById('testYear');
    for (var y = now.getFullYear() + 1; y >= now.getFullYear() - 3; y--) {
        var opt = document.createElement('option');
        opt.value = y;
        opt.textContent = y;
        if (y === now.getFullYear()) opt.selected = true;
        yearSelect.appendChild(opt);
    }
    document.getElementById('testMonth').selectedIndex = now.getMonth();
    loadProfiles();
    loadLookups();
});

function apiCall(action, data) {
    var body = Object.assign({ action: action }, data || {});
    return fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    }).then(function(r) { return r.json(); });
}

function loadLookups() {
    apiCall('getGroups').then(function(data) {
        allGroups = data;
        var sel = document.getElementById('profileGroup');
        sel.innerHTML = '<option value="">-- Выберите --</option>';
        data.forEach(function(g) {
            sel.innerHTML += '<option value="' + g.groupid + '">' + escHtml(g.groupname) + ' (' + g.groupid + ')</option>';
        });
    });
    apiCall('getServices').then(function(data) { allServices = data; });
    apiCall('getAccounts').then(function(data) {
        allAccounts = data;
        var sel = document.getElementById('profileAccount');
        sel.innerHTML = '<option value="">-- Без фильтра --</option>';
        data.forEach(function(a) {
            sel.innerHTML += '<option value="' + a.accountid + '">' + escHtml(a.accountname) + '</option>';
        });
    });
}

function loadProfiles() {
    apiCall('getProfiles').then(function(data) {
        document.getElementById('profilesLoading').style.display = 'none';
        document.getElementById('profilesTable').style.display = 'table';
        renderProfiles(data);
    });
}

function renderProfiles(profiles) {
    var tbody = document.getElementById('profilesBody');
    if (profiles.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;padding:30px;">Нет профилей</td></tr>';
        return;
    }
    var periodLabels = { 'next_month': 'След. месяц', 'last_two': 'Последние 2', 'same_month': 'Тот же месяц' };
    var html = '';
    profiles.forEach(function(p) {
        var svcCount = Object.keys(p.services || {}).length;
        var period = periodLabels[p.reading_period] || p.reading_period;
        var groupName = p.group_id || '-';
        allGroups.forEach(function(g) { if (g.groupid == p.group_id) groupName = g.groupname; });
        html += '<tr>';
        html += '<td><span class="vt-name-link" onclick="editProfile(\'' + p._filename + '\')">' + escHtml(p.name) + '</span></td>';
        html += '<td>' + escHtml(groupName) + '</td>';
        html += '<td>' + svcCount + ' услуг</td>';
        html += '<td><span class="vt-badge vt-badge-period">' + period + '</span></td>';
        html += '<td class="vt-actions">';
        html += '<span title="Редактировать" onclick="editProfile(\'' + p._filename + '\')"><i class="fa fa-pencil"></i></span>';
        html += '<span title="Тестировать" onclick="quickTest(\'' + p._filename + '\')"><i class="fa fa-flask"></i></span>';
        html += '<span title="Удалить" onclick="deleteProfile(\'' + p._filename + '\', \'' + escHtml(p.name) + '\')"><i class="fa fa-trash"></i></span>';
        html += '</td>';
        html += '</tr>';
    });
    tbody.innerHTML = html;
}

function deleteProfile(filename, name) {
    if (!confirm('Удалить профиль "' + name + '"?')) return;
    apiCall('deleteProfile', { filename: filename }).then(function() { loadProfiles(); });
}

function showList(skipHistory) {
    document.getElementById('sectionList').classList.remove('section-hidden');
    document.getElementById('sectionEditor').classList.add('section-hidden');
    document.getElementById('sectionTest').classList.add('section-hidden');
    document.getElementById('pageTitle').textContent = 'ПРОФИЛИ ГЕНЕРАЦИИ';
    document.getElementById('barActions').innerHTML = '<button class="vt-btn vt-btn-primary" onclick="showEditor(null)"><i class="fa fa-plus"></i> Создать профиль</button>';
    if (!skipHistory) history.pushState({ view: 'list' }, '', '#list');
    loadProfiles();
}

function showEditor(profile, skipHistory) {
    document.getElementById('sectionList').classList.add('section-hidden');
    document.getElementById('sectionEditor').classList.remove('section-hidden');
    document.getElementById('sectionTest').classList.add('section-hidden');
    document.getElementById('barActions').innerHTML = '';
    if (!skipHistory) {
        var fn = (profile && profile._filename) ? profile._filename : 'new';
        history.pushState({ view: 'editor', filename: fn }, '', '#editor/' + fn);
    }

    if (profile) {
        var title = escHtml(profile.name);
        document.getElementById('editorTitle').innerHTML = '<i class="fa fa-pencil"></i> ' + title;
        document.getElementById('pageTitle').textContent = title.toUpperCase();
        document.getElementById('profileName').value = profile.name || '';
        document.getElementById('profileGroup').value = profile.group_id || '';
        document.getElementById('profilePeriod').value = profile.reading_period || 'last_two';
        document.getElementById('profileAccount').value = profile.account_filter_id || '';
        currentFilename = profile._filename || null;
        var rulesDiv = document.getElementById('serviceRules');
        rulesDiv.innerHTML = '';
        var services = profile.services || {};
        Object.keys(services).forEach(function(sid) {
            addServiceRule(sid, services[sid].calc, services[sid].label);
        });
    } else {
        document.getElementById('editorTitle').innerHTML = '<i class="fa fa-plus"></i> Новый профиль';
        document.getElementById('pageTitle').textContent = 'НОВЫЙ ПРОФИЛЬ';
        document.getElementById('profileName').value = '';
        document.getElementById('profileGroup').value = '';
        document.getElementById('profilePeriod').value = 'last_two';
        document.getElementById('profileAccount').value = '';
        currentFilename = null;
        document.getElementById('serviceRules').innerHTML = '';
    }
}

function editProfile(filename) {
    apiCall('getProfile', { filename: filename }).then(function(data) {
        if (data.error) { alert(data.error); return; }
        showEditor(data);
    });
}

function quickTest(filename) {
    apiCall('getProfile', { filename: filename }).then(function(data) {
        if (data.error) { alert(data.error); return; }
        showEditor(data);
        showTestWindow();
    });
}

function addServiceRule(serviceId, calcType, label) {
    var div = document.getElementById('serviceRules');
    var row = document.createElement('div');
    row.className = 'vt-svc-row';
    var svcSelect = '<select class="vt-input svc-id"><option value="">-- Выберите услугу --</option>';
    allServices.forEach(function(s) {
        var selected = (s.serviceid == serviceId) ? ' selected' : '';
        svcSelect += '<option value="' + s.serviceid + '"' + selected + '>' + escHtml(s.servicename) + ' (' + s.unit_price + ' сом)</option>';
    });
    svcSelect += '</select>';
    var calcSelect = '<select class="vt-input vt-svc-calc svc-calc">';
    [['fixed','Фиксированная'],['by_residents','По жильцам'],['by_meters','По счётчикам'],['by_area','По площади']].forEach(function(o) {
        var selected = (o[0] == calcType) ? ' selected' : '';
        calcSelect += '<option value="' + o[0] + '"' + selected + '>' + o[1] + '</option>';
    });
    calcSelect += '</select>';
    row.innerHTML = svcSelect + calcSelect + '<button class="vt-btn-remove" onclick="this.parentElement.remove()"><i class="fa fa-times"></i></button>';
    div.appendChild(row);
}

function getProfileFromForm() {
    var services = {};
    document.querySelectorAll('.vt-svc-row').forEach(function(row) {
        var sid = row.querySelector('.svc-id').value;
        var calc = row.querySelector('.svc-calc').value;
        if (sid) {
            var label = row.querySelector('.svc-id option:checked').textContent.split(' (')[0];
            services[sid] = { calc: calc, label: label };
        }
    });
    return {
        name: document.getElementById('profileName').value.trim(),
        group_id: parseInt(document.getElementById('profileGroup').value) || null,
        account_filter_id: parseInt(document.getElementById('profileAccount').value) || null,
        reading_period: document.getElementById('profilePeriod').value,
        services: services
    };
}

function saveProfile() {
    var profile = getProfileFromForm();
    if (!profile.name) { alert('Укажите название профиля'); return; }
    if (Object.keys(profile.services).length === 0) { alert('Добавьте хотя бы одну услугу'); return; }
    apiCall('saveProfile', { profile: profile, filename: currentFilename }).then(function(data) {
        if (data.error) { alert(data.error); return; }
        currentFilename = data.filename;
        alert('Профиль сохранён!');
    });
}

function showTestWindow(skipHistory) {
    document.getElementById('sectionTest').classList.remove('section-hidden');
    document.getElementById('testResult').innerHTML = '';
    if (!skipHistory) history.pushState({ view: 'test' }, '', '#test');
}

function hideTestWindow() {
    document.getElementById('sectionTest').classList.add('section-hidden');
}

function runTest() {
    var estateId = document.getElementById('testEstateId').value;
    if (!estateId) { alert('Введите ID объекта'); return; }
    var profile = getProfileFromForm();
    if (Object.keys(profile.services).length === 0) { alert('Нет услуг в профиле'); return; }
    var btn = document.getElementById('btnRunTest');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Расчёт...';
    document.getElementById('testResult').innerHTML = '<div class="vt-loading"><i class="fa fa-spinner fa-spin"></i> Расчёт...</div>';
    apiCall('testGeneration', {
        profile: profile, estatesid: estateId,
        month: document.getElementById('testMonth').value,
        year: document.getElementById('testYear').value
    }).then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-play"></i> Запустить тест';
        renderTestResult(data);
    }).catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-play"></i> Запустить тест';
        document.getElementById('testResult').innerHTML = '<div class="vt-alert-error"><i class="fa fa-exclamation-triangle"></i> ' + err.message + '</div>';
    });
}

function renderTestResult(data) {
    var div = document.getElementById('testResult');
    if (data.error) {
        div.innerHTML = '<div class="vt-panel"><div class="vt-panel-body"><div class="vt-alert-error"><i class="fa fa-exclamation-triangle"></i> ' + escHtml(data.error) + '</div></div></div>';
        return;
    }
    var html = '<div class="vt-panel">';
    if (data.estate) {
        var e = data.estate;
        html += '<div class="vt-estate-info"><strong>Объект #' + e.estatesid + '</strong>';
        html += ' &nbsp;|&nbsp; ЛС: ' + (e.estate_number || '-');
        html += ' &nbsp;|&nbsp; Тип: ' + (e.cf_object_type || '-');
        html += ' &nbsp;|&nbsp; Жильцов: ' + (e.cf_number_of_residents || 0);
        html += ' &nbsp;|&nbsp; Площадь: ' + (e.cf_area || 0) + ' м²</div>';
    }
    if (data.errors && data.errors.length > 0) {
        data.errors.forEach(function(err) {
            html += '<div class="vt-alert-error"><i class="fa fa-exclamation-triangle"></i> ' + escHtml(err) + '</div>';
        });
    }
    html += '<table><thead><tr><th>Услуга</th><th>Тип</th><th>Кол-во</th><th>Цена</th><th>Сумма</th><th>Налог</th><th>Итого</th></tr></thead><tbody>';
    var calcLabels = { fixed: 'Фикс.', by_residents: 'Жильцы', by_meters: 'Счётчик', by_area: 'Площадь' };
    var calcBadges = { fixed: 'vt-badge-fixed', by_residents: 'vt-badge-residents', by_meters: 'vt-badge-meters', by_area: 'vt-badge-area' };
    (data.services || []).forEach(function(svc) {
        if (svc.status === 'skipped') {
            html += '<tr><td>' + escHtml(svc.label) + '</td><td colspan="6" style="color:#999;">' + escHtml(svc.message) + '</td></tr>';
            return;
        }
        var badge = '<span class="vt-badge ' + (calcBadges[svc.calc]||'') + '">' + (calcLabels[svc.calc]||svc.calc) + '</span>';
        var warn = svc.status === 'warning' ? ' <i class="fa fa-exclamation-triangle" style="color:#e65100;" title="' + escHtml(svc.message||'') + '"></i>' : '';
        (svc.lines || []).forEach(function(line, idx) {
            html += '<tr>';
            if (idx === 0) {
                html += '<td rowspan="' + svc.lines.length + '">' + escHtml(svc.servicename||svc.label) + warn + '</td>';
                html += '<td rowspan="' + svc.lines.length + '">' + badge + '<br><small style="color:#999;">НДС ' + svc.tax1 + '% НСП ' + svc.tax2 + '%</small></td>';
            }
            html += '<td>' + line.qty + '</td><td>' + fmt(line.price) + '</td><td>' + fmt(line.margin) + '</td><td>' + fmt(line.tax_amount) + '</td><td><strong>' + fmt(line.total) + '</strong></td></tr>';
            if (line.meter_number) {
                html += '<tr><td colspan="7" class="meter-detail"><i class="fa fa-gauge"></i> №' + line.meter_number + ' | Пред: ' + line.prev_reading + ' (' + (line.prev_date||'-') + ') | Тек: ' + line.cur_reading + ' (' + (line.cur_date||'-') + ') | Расход: ' + line.qty + '</td></tr>';
            }
        });
    });
    if (data.totals) {
        html += '<tr class="total-row"><td colspan="4" style="text-align:right;">Без налога:</td><td colspan="3">' + fmt(data.totals.margin) + ' сом</td></tr>';
        html += '<tr class="total-row"><td colspan="4" style="text-align:right;">Налоги:</td><td colspan="3">' + fmt(data.totals.tax_amount) + ' сом</td></tr>';
        html += '<tr class="total-row"><td colspan="4" style="text-align:right;font-size:14px;">ИТОГО:</td><td colspan="3" style="font-size:14px;">' + fmt(data.totals.total) + ' сом</td></tr>';
    }
    html += '</tbody></table>';
    if (!data.errors || data.errors.length === 0) {
        html += '<div class="vt-alert-success"><i class="fa fa-check-circle"></i> Тестовый расчёт выполнен успешно (счёт НЕ создан)</div>';
    }
    html += '</div>';
    div.innerHTML = html;
}

function escHtml(str) {
    if (!str) return '';
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
function fmt(n) {
    if (n === null || n === undefined) return '0';
    return parseFloat(n).toFixed(2);
}

// ===== Browser history navigation =====
history.replaceState({ view: 'list' }, '', '#list');

window.addEventListener('popstate', function(e) {
    var state = e.state;
    if (!state || state.view === 'list') {
        showList(true);
    } else if (state.view === 'editor') {
        if (state.filename && state.filename !== 'new') {
            apiCall('getProfile', { filename: state.filename }).then(function(data) {
                if (data.error) { showList(true); return; }
                showEditor(data, true);
            });
        } else {
            showEditor(null, true);
        }
    } else if (state.view === 'test') {
        document.getElementById('sectionTest').classList.remove('section-hidden');
    }
});
</script>
</body>
</html>
