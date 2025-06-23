<?php
namespace src\db;

// Абстрактный класс для работы с БД
abstract class Db
{
    abstract public function connect();
    abstract public function query($sql);

    abstract public function showTables();
    abstract public function getTable($table);

    abstract public function writeData($data): bool;
    abstract public function truncateTable(): bool;
}

// Абстрактная фабрика
abstract class DbFactory
{
    abstract public function createDb(array $config, array $fields): Db;
}


