
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
Постановка на cron:

    в терминале выполнить команду crontab -e и ввести команду:
    0 2 * * * docker exec yandex-clickhouse php /src/src/script.php daily >> /var/log/cronlog-yametrika.log 2>&1

---------------------------------------------------------
Создание БД clickhouse с нужными полями

    CREATE TABLE YandexMetrika (
        id UInt32 PRIMARY KEY,
        date String,
        pageViews String,
        goalsId String,
        isNewUser String
    ) ENGINE = MergeTree() 

---------------------------------------------------------
В главной дирректории необходимо создать файл config.json:

 {
    "token": "",
    "counter_id": "",
    "visits_fields": {
        "ym:s:visitID": "id",
        "ym:s:date": "date",
        "ym:s:pageViews": "pageViews",
        "ym:s:goalsID": "goalsId",
        "ym:s:isNewUser": "isNewUser"
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