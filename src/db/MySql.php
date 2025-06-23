<?php
namespace src\db;

// Фабрика MySQL
class MySqlFactory extends DbFactory
{
    public function createDb(array $config, array $fields): MySqlDb
    {
        return new MySqlDb($config, $fields);
    }
}

// Конкретный класс для MySQL
class MySqlDb extends Db
{
    private $config;

    public function __construct(array $config, array $fields)
    {
        $this->config = $config;
    }

    public function connect()
    {
        // Логика подключения к MySQL
    }
    public function query($sql)
    {
        // Логика выполнения запроса в MySQL
    }

    public function showTables()
    {
        // Логика получения списка таблиц в MySQL
    }

    public function getTable($table)
    {
        // Логика получения данных из таблицы в MySQL
    }

    public function writeData($data): bool
    {
        return false;
    }

    public function truncateTable(): bool
    {
        return false;
    }
}