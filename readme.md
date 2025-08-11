
Скрипт для выгрузки данных с Yandex metrika

---------------------------------------------------------
Развёртывание в docker:

    docker build -t yandex-clickhouse-i --file Dockerfile .
    docker run --restart unless-stopped -d -it -p 8094:80 --name yandex-clickhouse yandex-clickhouse-i
    docker stop yandex-clickhouse
    docker rm yandex-clickhouse
    docker image rm yandex-clickhouse-i
    docker exec -it yandex-clickhouse bash или sh

---------------------------------------------------------
Аргументы для запуска php script.php:

    history - данные метрик за всё время
    daily - данные метрик за день
    range - данные метрик за диапазон дат
    truncate - очистка таблицы clickhouse
    reset - сброс даты для блокировки daily выгрузки

---------------------------------------------------------
Пример запуска:
    php script.php daily (Единоразовая выгрузка за вчерашний день)
или
    php script.php range date=YYYY-MM-DD:YYYY-MM-DD (От[включительно] и до[включительно], но не больше месяца.)
или
    php script.php reset (Разблокирует время, чтобы можно было выгрузить daily данные)
или
    php script.php truncate (!!!ОСТОРОЖНО!!! Очищает всю таблицу метрик.)
или
    php script.php history (Не реализовано.)

---------------------------------------------------------
Постановка на cron:

    в терминале выполнить команду crontab -e и ввести команду:
    0 2 * * * docker exec yandex-clickhouse php /src/src/script.php daily >> /var/log/cronlog-yametrika.log 2>&1

---------------------------------------------------------
Создание БД clickhouse с нужными полями

    CREATE TABLE YandexMetrika (
        visitID UInt32 PRIMARY KEY,
        dateTime String,
        lastTrafficSource String,
        pageViews String,
        goalsId String,
        isNewUser String,
        bounce String,
        visitDuration String,
        regionCountry String,
        regionCity String,
        startURL String,
        endURL String,
        counterUserIDHash String,
        browser String,
        purchaseID String,
        purchaseProductQuantity String,
        purchaseRevenue String
    ) ENGINE = MergeTree() 

---------------------------------------------------------
В главной дирректории необходимо создать файл config.json:

 {
    "token": "",
    "counter_id": "",
    "visits_fields": {
        "ym:s:visitID": "visitID",
        "ym:s:dateTime": "dateTime",
        "ym:s:lastTrafficSource": "lastTrafficSource",
        "ym:s:pageViews": "pageViews",
        "ym:s:goalsID": "goalsId",
        "ym:s:isNewUser": "isNewUser",
        "ym:s:bounce": "bounce",
        "ym:s:visitDuration": "visitDuration",
        "ym:s:regionCountry": "regionCountry",
        "ym:s:regionCity": "regionCity",
        "ym:s:startURL": "startURL",
        "ym:s:endURL": "endURL",
        "ym:s:counterUserIDHash": "counterUserIDHash",
        "ym:s:browser": "browser",
        "ym:s:purchaseID": "purchaseID",
        "ym:s:purchaseProductQuantity": "purchaseProductQuantity",
        "ym:s:purchaseRevenue": "purchaseRevenue"
    },
    "log_level": "INFO",
    "max_attempts": 40,
    "delay": 10,
    "clickhouse": {
        "host": "",
        "port": "",
        "username": "",
        "password": "",
        "database": "",
        "table": ""
    }
}       