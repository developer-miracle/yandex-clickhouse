<?php

/**
 * YandexMetricaService
 *
 * Сервис для работы с API Яндекс Метрики.
 * Позволяет запрашивать отчёты по логам и получать данные за указанный диапазон дат.
 *
 * @package YandexMetricaService
 */
class YandexMetricaService
{
    private $counterId;
    private $token;
    private $url;
    private $headers;

    /**
     * YandexMetricaService constructor.
     *
     * @param string $counterId Идентификатор счётчика
     * @param string $token Токен доступа
     */
    public function __construct($counterId, $token)
    {
        $this->counterId = $counterId;
        $this->token = $token;
        $this->url = 'https://api-metrika.yandex.net/stat/v1/data';
        $this->headers = [
            'Authorization' => "OAuth {$this->token}",
            'Content-Type' => 'application/json',
        ];
    }

    // /**
    //  * Получение данных из API Яндекс Метрики
    //  *
    //  * @param array $params Параметры запроса
    //  * @return array Ответ API
    //  */
    // public function getData($params)
    // {
    //     $url = $this->url . '?id=' . $this->counterId . '&' . http_build_query($params);
    //     $ch = curl_init($url);
    //     curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //     $response = curl_exec($ch);
    //     curl_close($ch);

    //     return json_decode($response, true);
    // }

    /**
     * Запрос отчёта по логам.
     *
     * @param array $params Параметры запроса
     * @return array Ответ API
     */
    private function requestReport($params)
    {
        $url = "https://api-metrika.yandex.net/management/v1/counter/{$this->counterId}/logrequests?" . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: OAuth {$this->token}"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            Logger::error("Ошибка: $error", 'logs.log');
        }
        curl_close($ch);
        return json_decode($response, true);
    }

    /**
     * Проверяет статус запроса.
     *
     * @param string $requestId Идентификатор запроса
     * @return string|null Статус запроса или null в случае ошибки
     */
    private function checkStatus($requestId)
    {
        $url = "https://api-metrika.yandex.net/management/v1/counter/{$this->counterId}/logrequest/{$requestId}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: OAuth {$this->token}"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if (isset($data['log_request']['status'])) {
            return $data['log_request']['status'];
        }
        return null;
    }

    /**
     * Скачивает данные по запросу.
     *
     * @param string $requestId Идентификатор запроса
     * @return string|false Ответ API в виде строки или false в случае ошибки
     */
    private function download($requestId)
    {
        $url = "https://api-metrika.yandex.net/management/v1/counter/{$this->counterId}/logrequest/{$requestId}/part/0/download";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: OAuth {$this->token}"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            Logger::error("Ошибка: $error", 'logs.log');
        }
        curl_close($ch);
        return $response;
    }

    /**
     * Очищает кэш для указанного запроса.
     *
     * @param string $requestId Идентификатор запроса
     * @return array Ответ API
     */
    private function clearCache($requestId)
    {
        $url = "https://api-metrika.yandex.net/management/v1/counter/{$this->counterId}/logrequest/{$requestId}/clean";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: OAuth {$this->token}"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            Logger::error("Ошибка: $error", 'logs.log');
        }
        curl_close($ch);
        return json_decode($response, true);
    }

    /**
     * Парсит TSV данные в массив.
     *
     * @param string $tsv Строка в формате TSV
     * @return array Массив с данными
     */
    private function parseTsvData($tsv)
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($tsv));
        if (count($lines) < 2)
            return [];

        $header = preg_split('/\s+/', array_shift($lines));
        $result = [];
        foreach ($lines as $line) {
            if (trim($line) === '')
                continue;
            $fields = explode("\t", $line);
            if (count($fields) !== count($header))
                continue;
            $result[] = $fields; // только значения, без заголовков
        }
        return $result;
    }

    /**
     * Получает данные за указанный диапазон дат.
     *
     * @param string $startDate Дата начала в формате 'YYYY-MM-DD'
     * @param string $endDate Дата окончания в формате 'YYYY-MM-DD'
     * @param array $visitsFields Массив полей для получения
     * @param int $maxAttempts Максимальное количество попыток проверки статуса
     * @param int $delay Задержка между проверками статуса в секундах
     * @return array|null Массив с данными или null в случае ошибки
     */
    public function getRangeData($startDate, $endDate, $visitsFields, $maxAttempts, $delay)
    {
        $params = [
            'date1' => $startDate,
            'date2' => $endDate,
            'source' => 'visits',
            'fields' => implode(',', $visitsFields),
        ];

        $responseRequestReport = $this->requestReport($params);

        if (!isset($responseRequestReport['log_request']['request_id'])) {
            // Ошибка при создании запроса
            return null;
        }

        $requestId = $responseRequestReport['log_request']['request_id'];
        $attempt = 0;
        $status = null;

        // Ожидание обработки отчёта
        do {
            if ($attempt > 0) {
                // Задержка перед повторной проверкой статуса
                sleep($delay);
            }
            $status = $this->checkStatus($requestId);
            $attempt++;
        } while ($status !== 'processed' && $attempt < $maxAttempts);

        if ($status !== 'processed') {
            Logger::error("Ошибка: Запрос не был обработан. Статус: $status. Попыток: $attempt", 'logs.log');
            exit();
        }

        // created — создан.
        // canceled — отменён.
        // processed — обработан.
        // cleaned_by_user — очищен пользователем.
        // cleaned_automatically_as_too_old — очищен автоматически.
        // processing_failed — ошибка при обработке.
        // awaiting_retry — ожидает перезапуска.

        // Если статус 'processed', то можно скачать данные
        $responseDownload = $this->download($requestId);
        $responseDownloadDecoded = json_decode($responseDownload, true);

        if ($responseDownloadDecoded != null && array_key_exists("errors", $responseDownloadDecoded)) {
            Logger::error("Ошибка: " . implode(', ', $responseDownloadDecoded['errors']), 'logs.log');
            exit();
        }

        $this->clearCache($requestId);

        $data = $this->parseTsvData($responseDownload);

        if (empty($data)) {
            Logger::error("Ошибка: Не удалось получить данные из отчёта. Возможно, данные отсутствуют или формат ответа некорректен.", 'logs.log');
            exit();
        }
        return $data;
    }
}