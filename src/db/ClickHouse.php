<?php

namespace src\db;

// Фабрика ClickHouse
class ClickHouseFactory extends DbFactory
{
    public function createDb(array $config, array $fields): Db
    {
        return new ClickHouseDb($config, $fields);
    }
}

// Конкретный класс для ClickHouse
class ClickHouseDb extends Db
{
    private $config;
    private $db;
    private $fields;
    public function __construct(array $config, array $fields)
    {
        $this->config = [
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => $config['password'],
            'table' => $config['table'],
        ];

        $this->fields = $fields;
    }

    public function connect()
    {
        if ($this->db) {
            return $this->db;
        }
        require_once(__DIR__ . '/../vendor/autoload.php');
        $config = [
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'username' => $this->config['username'],
            'password' => $this->config['password']
        ];

        $db = new \ClickHouseDB\Client($config);
        $db->database($this->config['database']);
        if (!$db->ping()) {
            exit();
        }
        $this->db = $db;
        return $this->db;
    }
    public function query($sql)
    {
        $db = $this->connect(); // всегда есть подключение
        return $db->select($sql);
    }
    public function showTables()
    {
        $db = $this->connect(); // всегда есть подключение
        return $db->showTables();
    }

    public function getTable($table)
    {
        $db = $this->connect(); // всегда есть подключение
        return $db->select("SELECT * FROM {$table}");
    }

    public function writeData($data): bool
    {
        $db = $this->connect(); // всегда есть подключение
        $statementTableName = $db->insert(
            $this->config['table'],
            $data,
            $this->fields
        );

        if ($statementTableName->responseInfo()['http_code'] == 200) {
            return true;
        }
        return false;
    }

    public function truncateTable(): bool
    {
        $db = $this->connect(); // всегда есть подключение
        $statementTableName = $db->write('TRUNCATE TABLE IF EXISTS ' . $this->config['table']);

        if ($statementTableName->responseInfo()['http_code'] == 200) {
            return true;
        }
        return false;
    }
}