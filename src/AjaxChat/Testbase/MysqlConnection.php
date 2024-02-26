<?php

namespace AjaxChat\Testbase;

use PDO;
use PDOException;

class MysqlConnection
{
	# Database host address.
	protected $host;
	# Database port.
	protected $port;
	# Database name.
	protected $database;
	# Username for authentication.
	protected $username;
	# Password for authentication.
	protected $password;
	# Database charset.
	protected $charset;

	protected $connect = false;

	# Connection variable. DO NOT CHANGE!
	public $pdo;

	public function __construct($dbConnection)
	{
		try {
			$this->host = $dbConnection['host'];
			$this->username = $dbConnection['user'];
			$this->password = $dbConnection['pass'];
			$this->port = $dbConnection['port'];
			$this->database = $dbConnection['name'];
			$this->charset = $dbConnection['charset'];

			$this->pdo = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->database, $this->username, $this->password);
			$this->pdo->setAttribute(PDO::ATTR_PERSISTENT, true);
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->pdo->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, 'SET NAMES ' . $this->charset);
			$this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			$this->connect = true;
		} catch(PDOException $e) {
			$message = 'Database pdo can not be estabilished. Please try again later.';
			echo $this-> getDBError($message, $e->getMessage(), $e->getFile(), $e->getLine());

			die();
		}
	}

	// Method to connect to the DataBase server:
	public function connect(): object
	{
		return $this->pdo;
	}

	public function __destruct()
	{
		$this->pdo = null;
	}

	public function dateAddQuery(string $sqltype, mixed $amount = null, mixed $timeform = null): string
	{
		$amount		= ($amount != null) ? intval($amount) : null;
		$timeform	= ($timeform != null) ? $timeform : null;

		return "DATE_ADD({$sqltype}, INTERVAL {$amount} {$timeform})";
	}

	public function dateSubQuery(string $sqltype, mixed $amount = null, mixed $timeform = null): string
	{
		$amount		= ($amount != null) ? intval($amount) : null;
		$timeform	= ($timeform != null) ? $timeform : null;

		return "DATE_SUB({$sqltype}, INTERVAL {$amount} {$timeform})";
	}

	public function sqlUnixTimestamp(string $column_name): mixed
	{
		return "UNIX_TIMESTAMP({$column_name})";
	}

	public function sqlFromUnixTimestamp(mixed $column_name): mixed
	{
		return "FROM_UNIXTIME({$column_name})";
	}

	// Convert IP to binary
	public function ipToStore($ip): string
	{
		return inet_pton($ip);
	}

	public function ipFromStore($ip): string
	{
		return inet_ntop($ip);
	}

	public function checkTable($table)
	{
		$stmt = null;

		try {
			$stmt = $this->pdo->query("SHOW TABLES LIKE '" . $table . "'");

			if ($stmt->rowCount() != 1) {
				echo $this-> getDBError('Table "' . $table . '" does not exist.');
			}
		} catch(PDOException $e) {
			echo $this-> getDBError($e->getMessage(), $e->getFile(), $e->getLine());

			die();
		}
	}

	public function checkColumn($table = null, $column = null)
	{
		$stmt = null;

		try {
			$result = $this->pdo->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $this->database . "' AND TABLE_NAME = '" . $table . "' AND COLUMN_NAME = '" . $column . "'");

			if ($result->rowCount() != 1) {
				echo $this-> getDBError('Column "' . $column . '" does not exist.');
			}
		} catch(PDOException $e) {
			echo $this-> getDBError($e->getMessage(), $e->getFile(), $e->getLine());

			die();
		}
	}

	// Method to return the error report:
	public function getDBError(?string $message = null, ?string $report = null, ?string $file = null, ?int $line = null)
	{
		$str = '';

		if (isset($message)) {
			$str .= 'Error: ' . $message . '<br>';
		}

		if (isset($report)) {
			$str .= 'Error-Report: ' . $report . '<br>';
		}

		if (isset($file)) {
			$str .= 'File: ' . $file . '<br>';
		}

		if (isset($line)) {
			$str .= 'Line: ' . $line;
		}

		return $str;
	}
}
