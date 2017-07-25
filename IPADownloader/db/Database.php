<?php
require_once dirname(__FILE__) . '/MysqlConnection.php';

class Database
{

    public $prefix = 'phpfox_';

    private $connection = null;

    public function __construct()
    {
        $this->connection = MysqlConnection::getInstance();
    }

    public function getConnection()
    {
        return $this->connection->getConnection();
    }

    public function real_escape_string($str)
    {
        return mysqli_real_escape_string($this->getConnection(), $str);
    }

    public function free_result(&$result)
    {
        return mysqli_free_result($result);
    }

    public function query($query)
    {
        return mysqli_query($this->connection->getConnection(), $query);
    }

    public function getError()
    {
        return $this->error ? $this->error : mysqli_error($this->connection->getConnection());
    }

    public function autocommit($mode)
    {
        return mysqli_autocommit($this->connection->getConnection(), $mode);
    }

    public function commit()
    {
        return mysqli_commit($this->connection->getConnection()) && $this->autocommit(true);
    }

    public function rollback()
    {
        return mysqli_rollback($this->connection->getConnection()) && $this->autocommit(true);
    }

    public function insert_id()
    {
        return mysqli_insert_id($this->connection->getConnection());
    }

    public function affected_rows()
    {
        return mysqli_affected_rows($this->connection->getConnection());
    }

    public function num_rows(&$result)
    {
        return mysqli_num_rows($result);
    }
    
    public function close(){
        unset($this->connection);
    }
}