<?php
/**
 * Class Logger
 * Простой класс для логирования сообщений в файл.
 * Поддерживает разные уровни логирования (инфо, ошибка и т.д.)
 */
class Logger
{
    /**
     * Логирование сообщения любого типа (инфо, ошибка и т.д.)
     * @param string $message Сообщение для логирования
     * @param string $filename Имя файла для логирования
     * @param string $level Уровень логирования (например, INFO, ERROR)
     * @return bool|int
     */
    public static function log(string $message, string $filename, string $level = 'INFO'): bool|int
    {
        $logEntry = sprintf(
            "[%s] [%s]: %s%s",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            PHP_EOL
        );
        try {
            return file_put_contents($filename, $logEntry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $exception) {
            // Можно добавить вывод в error_log или другую обработку
            return false;
        }
    }

    /**
     * Логирование ошибок
     * @param string $message Сообщение ошибки
     * @param string $filename Имя файла для логирования
     * @return bool|int
     */
    public static function error(string $message, string $filename): bool|int
    {
        return self::log($message, $filename, 'ERROR');
    }

    /**
     * Логирование информационных сообщений
     * @param string $message Сообщение
     * @param string $filename Имя файла для логирования
     * @return bool|int
     */
    public static function info(string $message, string $filename): bool|int
    {
        return self::log($message, $filename, 'INFO');
    }
}