const config = require('./config.json')
const express = require('express')
const multer = require('multer')
const moment = require('moment')
const mysql = require('mysql2')
const CRM = require("./crm")
const DB = require("./db")
const XLSX = require('xlsx')

const app = express()

const storageConfig = multer.diskStorage({
    destination: (request, file, cb) => { cb(null, "uploads/") },
    filename: (request, file, cb) => { cb(null, String(request.body.paySystem).replace('/', '') + moment().format('DD-MM-YYYY HH:mm:ss') + '.' + String(file.originalname).split('.').pop()) }
});
const fileFilter = (request, file, cb) => {
    // console.log(file.mimetype)
    if (file.mimetype === "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" || file.mimetype === 'application/vnd.ms-excel') cb(null, true);
    else cb(null, false);
}

var sessionName, DB_Connection

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static(__dirname));
app.use(multer({ storage: storageConfig, fileFilter: fileFilter, limits: { fieldSize: 10000 * 1024 * 1024 } }).any('file', 'invoice_file'));

app.listen(3095, async function () {
    // console.clear()
    try {
        DB_Connection = mysql.createPool({
            connectionLimit: 5,
            host: config.db_host,
            user: config.db_user,
            database: config.db_name,
            password: config.db_pass
        })
        console.log('server started ::: ' + moment().format('DD-MM-YYYY HH:mm:ss'))
    }
    catch (e) {
        console.error("Запуск не удался", e)
        process.exit(e)
    }
})

app.post('/uploadInvoice', async function (request, response) {
    try {
        console.log(`Запрос на создание платежей (${request.body.paySystem}) ::: ` + moment().format('DD-MM-YYYY HH:mm:ss'))
        if (!request.files || request.files.length == 0) throw new Error('Ошибка загрузки файла')
        sessionName = await CRM.login()
        var flats = await DB.doQuery(DB_Connection, 'getFlatsLSandCRMid')
        var paySystem = request.body.paySystem
        var sheetname = false
        var workbook = XLSX.readFile('uploads/' + request.files[0].filename);
        var created = 0, errors = 0, notFindedLS = 0, exist = 0
        for (var sheet in workbook.Sheets) { sheetname = sheet }
        try {
            var tableData = XLSX.utils.sheet_to_json(workbook.Sheets[sheetname], { header: 1 });
            if (tableData.length == 0) throw new Error('Ошибка парсинга файла')
            else {
                switch (paySystem) {
                    case 'Касса организации':
                        for (var i = 0; i < tableData.length; i++) {
                            try {
                                var ls = tableData[i][0].trim()
                                var numericDate = tableData[i][1];
                                var type_payment = tableData[i][2];
                                var amount = tableData[i][3];
                                var excelDateInMillis = (numericDate - 25569) * 86400 * 1000;
                                var dateObject = new Date(excelDateInMillis);
                                var year = dateObject.getUTCFullYear();
                                var month = String(dateObject.getUTCMonth() + 1).padStart(2, '0');
                                var day = String(dateObject.getUTCDate()).padStart(2, '0');
                                var pay_date = `${year}-${month}-${day}`;
                                var flat = flats.get(ls) //ЛС
                                if (flat) {
                                    var data = {
                                        cf_pay_date: pay_date, //Дата платежа
                                        cf_pay_type: 'Приход',
                                        cf_payment_type: "Безналичный расчет",
                                        assigned_user_id: '19x' + flat.smownerid,
                                        cf_txnid: 'Парсер',
                                        amount: parseFloat(amount),
                                        cf_payment_source: type_payment,
                                        cf_status: "Выполнен",
                                        cf_paid_object: '39x' + flat.crmid,
                                        cf_paid_service: "Вывоз ТБО"
                                    }
                                    if (await CRM.APIcreate(sessionName, 'Payments', data)) created++
                                    else errors++
                                }
                                else notFindedLS++
                            }
                            catch (e) {
                                errors++
                            }
                        }
                        break
                    // !!!!!!!!!!!!!!!!ПРи добавлении новой платежной системы проверять tableData, проврнка типа переменной суммы платежа 
                    default:
                        console.error(paySystem)
                        throw new Error('Неизвестная платежная система')
                        break
                }
            }
        }
        catch (e) {
            console.error(e)
            throw new Error(e.message)
        }
        response.send({ success: true, created: created, errors: errors, notFindedLS: notFindedLS, exist: exist })
    }
    catch (e) {
        console.error(moment().format('DD-MM-YYYY HH:mm:ss'), e)
        response.send({ success: false, message: e.message })
    }
})

app.post('/searchContact', async function (request, response) {
    try {
        var input = request.body.input
        response.send({ success: true, rows: await DB.doQuery(DB_Connection, 'searchContact', input) })
    }
    catch (e) {
        response.send({ success: false })
    }
})

function ExcelDateToJSDate(serial) {
    var utc_days = Math.floor(serial - 25569);
    var utc_value = utc_days * 86400;
    var date_info = new Date(utc_value * 1000);

    var fractional_day = serial - Math.floor(serial) + 0.0000001;

    var total_seconds = Math.floor(86400 * fractional_day);

    var seconds = total_seconds % 60;

    total_seconds -= seconds;

    var hours = Math.floor(total_seconds / (60 * 60)) + 6;
    var minutes = Math.floor(total_seconds / 60) % 60;

    return new Date(date_info.getFullYear(), date_info.getMonth(), date_info.getDate(), hours, minutes, seconds);
}


// subject: 'Платеж',
// contact_id: '12x'+flat.owner,
// cf_1265: '46x'+flat.crmid,
// invoicestatus: 'Paid',
// assigned_user_id:'19x1',
// cf_1436: paySystem, //Платежная система
// cf_1438: Number(tableData[i][2]), //Сумма платежа
// // cf_1440: tableData[i][0], //Номер система
// cf_1443: moment(tableData[i][0]).format('YYYY-MM-DD'), //Дата платежа
// cf_1445: moment(tableData[i][0]).format('HH:mm:ss'), //Время платежа
// productid:'24x1047',
// LineItems: [
//     {
//         quantity: 1.000,
//         productid: '24x1047',
//         listprice: 18
//     }
// ]