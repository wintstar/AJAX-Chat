<?php

namespace AjaxChat\Testbase;

use Exception;
use PDO;
use PDOException;

// Class to initialize the MySQL DataBase pdo:
class PgsqlConnection
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

			$this->pdo = new PDO('pgsql:host=' . $this->host . ';port=' . $this->port . ';dbname=' . $this->database, $this->username, $this->password);
			$this->pdo->setAttribute(PDO::ATTR_PERSISTENT, true);
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
			$this->pdo->exec('SET NAMES \'' . $this->charset . '\'');
		} catch(PDOException $e) {
			echo $this-> getDBError($e->getMessage(), $e->getFile(), $e->getLine());

			die();
		}
	}

	// Method to connect to the DataBase server:
	public function connect()
	{
		return $this->pdo;
	}

	public function __destruct()
	{
		$this->pdo = null;
	}

	public function ipToStore($ip)
	{
		return $ip;
	}

	public function ipFromStore($ip)
	{
		return $ip;
	}

	public function error($error)
	{
		echo $error;
	}

	public function checkTable($table = null)
	{
		try {
			$stmt = $this->pdo->prepare(
				'SELECT table_catalog, table_name
				FROM  information_schema.tables
				WHERE table_catalog = :table_catalog
				AND table_name = :table_name',
			);
			$stmt->bindValue(':table_catalog', $this->database, PDO::PARAM_STR);
			$stmt->bindValue(':table_name', $table, PDO::PARAM_STR);
			$stmt->execute();

			if ($stmt->rowCount() != 1) {
				throw new Exception('Table "' . $table . '" does not exist.');
			}
		} catch(PDOException $e) {
			$message = null;
			echo $this-> getDBError($message, $e->getMessage(), $e->getFile(), $e->getLine());

			die();
		}
	}

	public function checkColumn($table = null, $column = null)
	{
		$stmt = null;

		try {
			$stmt = $this->pdo->prepare(
				'SELECT table_catalog, table_schema, table_name, column_name
				FROM  information_schema.columns
				WHERE table_catalog = :table_catalog
				AND table_schema = :table_schema
				AND table_name = :table_name
				AND column_name = :column_name',
			);
			$stmt->bindValue(':table_catalog', $this->database, PDO::PARAM_STR);
			$stmt->bindValue(':table_schema', 'public', PDO::PARAM_STR);
			$stmt->bindValue(':table_name', $table, PDO::PARAM_STR);
			$stmt->bindValue(':column_name', $column, PDO::PARAM_STR);
			$stmt->execute();

			if ($stmt->rowCount() != 1) {
				throw new Exception('Column "' . $column . '" does not exist.');
			}
		} catch(PDOException $e) {
			echo $this-> getDBError($e->getMessage(), $e->getFile(), $e->getLine());

			die();
		}
	}

	// Method to return the error report:
	public function getDBError(?string $message = 'Database error', ?string $report = null, ?string $file = null, ?int $line = null)
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
