<?php
/*
 * Mysql database class - only one connection alowed
 */
class MysqlConnection {
	public $error = null;
	private $_connection = null;
	private static $_instance; // The single instance
	                           // private $_host = "203.162.123.171";
	                           // private $_username = "phpfox";
	                           // private $_password = "KL46zrJHbBBeuuwK";
	                           // private $_database = "phpfox";
	private $_host = '192.241.121.141';
	private $_username = 'appstorewrapper';
	private $_password = 'appstorewrapper';
	private $_database = 'appstorewrapper';
	/*
	 * Get an instance of the Database
	 * @return Instance
	 */
	public static function getInstance() {
		if (! self::$_instance) { // If no instance then make one
			self::$_instance = new self ();
		}
		return self::$_instance;
	}
	// Constructor
	private function __construct() {
		$this->_connection = new mysqli ( $this->_host, $this->_username, $this->_password, $this->_database );
		
		// Error handling
		if (mysqli_connect_error ()) {
			trigger_error ( 'Failed to connect to MySQL: ' . mysqli_connect_error (), E_USER_ERROR );
		}
		mysqli_set_charset($this->_connection, 'utf8');
	}
	// Magic method clone is empty to prevent duplication of connection
	private function __clone() {
		return false;
	}
	// Get mysqli connection
	public function getConnection() {
		return $this->_connection;
	}
	
	public function __destruct() {
	    if ($this->_connection)
	        mysqli_close($this->_connection);
	}
}