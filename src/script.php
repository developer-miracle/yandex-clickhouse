<?php

// Подключаем файл конфигурации
$config = json_decode(file_get_contents(__DIR__ . '/../config.json'), true);

// Подключаем файлы с классами
require_once __DIR__ . '/db/Db.php';
require_once __DIR__ . '/db/MySql.php';
require_once __DIR__ . '/db/ClickHouse.php';
require_once __DIR__ . '/YandexMetricaService.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/settings.php';

use src\db\ClickHouseFactory;

function getConfig($config, $key)
{
    return $config[$key] ?? null;
}

$allowedModes = ['history', 'daily', 'range', 'truncate', 'reset'];

// foreach ($argv as $arg) {
//     if (strpos($arg, 'mode=') === 0) {
//         $value = explode('=', $arg, 2)[1];
//         // Используйте $value
//     }
// }
// if (isset($value) && !in_array($value, $allowedModes)) {
//     exit("Недопустимый режим. Возможные значения: " . implode(', ', $allowedModes));
// }

$mode = null;

foreach ($argv as $arg) {
    if (in_array($arg, $allowedModes)) {
        $mode = $arg;
        break;
    }
}

$strArgs = implode(', ', $argv);
Logger::info("Запуск скрипта: $strArgs", __DIR__ . '/logs.log');

$counterId = getConfig($config, 'counter_id');
$token = getConfig($config, 'token');
$visitFields = array_keys(getConfig($config, 'visits_fields'));
$max_attempts = getConfig($config, 'max_attempts');
$delay = getConfig($config, 'delay');
if (!$counterId || !$token || !$visitFields || !$delay || !$max_attempts) {
    Logger::error("Ошибка: Проверьте настройки в config.json. Не указаны обязательные параметры: counter_id, token, visits_fields, max_attempts или delay.", __DIR__ . '/logs.log');
    exit();
}

$data = [];

switch ($mode) {
    case 'range':
        foreach ($argv as $arg) {
            if (strpos($arg, 'date=') === 0) {
                $dates = explode('=', $arg, 2)[1];
                $dateFrom = explode(':', $dates)[0];
                $dateTo = explode(':', $dates)[1];
                if (strtotime($dateFrom) === false || strtotime($dateTo) === false) {
                    Logger::error("Недопустимые даты. Укажите даты в формате 'YYYY-MM-DD:YYYY-MM-DD'.", __DIR__ . '/logs.log');
                    exit();
                }
                if (strtotime($dateTo) - strtotime($dateFrom) > 30 * 24 * 60 * 60) {
                    Logger::error("Ошибка: Интервал дат не должен превышать 30 дней. Начало: $dateFrom, Конец: $dateTo", __DIR__ . '/logs.log');
                    exit();
                }
                if (strtotime($dateTo) < strtotime($dateFrom)) {
                    Logger::error("Ошибка: Конечная дата не может быть раньше начальной. Начало: $dateFrom, Конец: $dateTo", __DIR__ . '/logs.log');
                    exit();
                }
                if (strtotime($dateFrom) > time()) {
                    Logger::error("Ошибка: Начальная дата не может быть в будущем. Начало: $dateFrom", __DIR__ . '/logs.log');
                    exit();
                }
                if (strtotime($dateTo) > time()) {
                    Logger::error("Ошибка: Конечная дата не может быть в будущем. Конец: $dateTo", __DIR__ . '/logs.log');
                    exit();
                }

                $yandexMetricaService = new YandexMetricaService($counterId, $token);
                $data = $yandexMetricaService->getRangeData($dateFrom, $dateTo, $visitFields, $max_attempts, $delay);
            }
        }
        if (isset($value) && !in_array($value, $allowedModes)) {
            exit("Недопустимый режим. Возможные значения: " . implode(', ', $allowedModes));
        }
        break;
    case 'history':
        Logger::error("Ошибка: Режим 'history' не реализован.", __DIR__ . '/logs.log');
        exit();
    case 'daily':
        // Проверяем дату последнего запуска
        $state = json_decode(file_get_contents(__DIR__ . '/state.json'), true);
        $lastRun = $state['last_run'] ?? null;
        if (!$lastRun) {
            Logger::error("Ошибка: Не удалось получить дату последнего запуска.", __DIR__ . '/logs.log');
            exit();
        }
        // Проверка если текущая дата -1 день от даты последнего запуска, то продолжаем
        $lastRunDate = date('Y-m-d', strtotime($lastRun));
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        if ($today <= $lastRunDate) {
            Logger::error("Нет новых данных для обработки. Последний запуск был: $lastRunDate, А выгрузка была за $yesterday", __DIR__ . '/logs.log');
            exit();
        }

        $yandexMetricaService = new YandexMetricaService($counterId, $token);
        $data = $yandexMetricaService->getRangeData($yesterday, $yesterday, $visitFields, $max_attempts, $delay);
        break;
    case 'truncate':
        //FIXME: Сделать дополнительную проверку на пароль введенный параметром следом за truncate:password
        $factory = new ClickHouseFactory();
        $db = $factory->createDb(getConfig($config, 'clickhouse'), array_values(getConfig($config, 'visits_fields')));
        $db->connect();
        $db->truncateTable();
        Logger::info("Таблица успешно очищена.", __DIR__ . '/logs.log');
        exit();
    case 'reset':
        file_put_contents(__DIR__ . '/state.json', json_encode(['last_run' => '2000-01-01'], JSON_PRETTY_PRINT));
        Logger::info("Сброс последнего запуска выполнен. Дата установлена на 2000-01-01.", __DIR__ . '/logs.log');
        exit();
    default:
        Logger::error("Необходим режим работы. Возможные значения: " . implode(', ', $allowedModes) . ". Используйте --help для получения справки.", __DIR__ . '/logs.log');
        exit();
}

if (empty($data)) {
    Logger::error("Нет данных для обработки. Проверьте настройки и параметры запроса.", __DIR__ . '/logs.log');
    exit();
}
// Для ClickHouse
$factory = new ClickHouseFactory();
$db = $factory->createDb(getConfig($config, 'clickhouse'), array_values(getConfig($config, 'visits_fields')));
$db->connect();

// // Для MySQL
// $factory = new MySqlFactory();
// $db = $factory->createDb();
// $db->connect();

// Записываем данные в ClickHouse
$status = $db->writeData($data);
if (!$status) {
    Logger::error("Ошибка при записи данных в ClickHouse. Мод: $mode", __DIR__ . '/logs.log');
    exit();
} else {
    Logger::info("Данные успешно записаны в ClickHouse. Мод: $mode", __DIR__ . '/logs.log');
}

// Обновляем дату последнего запуска
file_put_contents(__DIR__ . '/state.json', json_encode(['last_run' => date('Y-m-d')], JSON_PRETTY_PRINT));
