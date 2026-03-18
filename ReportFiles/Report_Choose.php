<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выбор отчета</title>
    <!-- Подключение шрифта Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <!-- Подключение Font Awesome для иконок -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        body {
            font-family: 'Roboto', sans-serif;
            font-weight: 400;
            text-align: center;
            background-color: #f0f0f0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: #333;
        }

        h2 {
            font-weight: 700;
            color: #333;
            margin-bottom: 30px;
        }

        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0px 10px 30px rgba(0, 0, 0, 0.1);
        }

        .button {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin: 10px 0;
            padding: 15px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1.2em;
            font-weight: 700;
            transition: background-color 0.3s ease;
        }

        .button i {
            margin-right: 10px;
            font-size: 1.5em;
        }

        .button:hover {
            background-color: #45a049;
        }

        @media (max-width: 600px) {
            .container {
                width: 90%;
            }

            .button {
                font-size: 1em;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Выберите отчет</h2>
        <a href="payments/index.php" class="button">
             Отчеты по платежам
        </a>
        <a href="payments/debtorb.php" class="button">
             Список должников
        </a>
        <a href="payments/subscriber.php" class="button">
             Список абонентов
        </a>
        <!-- <a href="payments/operator.php" class="button">
             Детализация по операторскому трафику 
        </a>
        <a href="payments/route.php" class="button">
             Маршрутный лист
        </a>
        <a href="payments/certificates.php" class="button">
            Справки
    </a> -->
    </div>
</body>
</html>