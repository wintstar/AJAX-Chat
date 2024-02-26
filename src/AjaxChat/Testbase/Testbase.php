<?php

namespace AjaxChat\Testbase;

use AjaxChat;

/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) Sebastian Tschan
 * @license Modified MIT License
 * @link https://blueimp.net/ajax/
 */


// Class to initialize the DataBase connection:
class Testbase
{
	protected $_db;

	public function __construct()
	{
		$dbConnection = [];
		$config = AjaxChat\Loader::readConfigFile();
		$dbConnection = $config['dbConnection'];

		if (empty($dbConnection['type'])) {
			$this->_db = new AjaxChat\Testbase\MysqlConnection($dbConnection);
		}

		$class = ucwords($dbConnection['type']);
		$fullClassName = "\\AjaxChat\\Testbase\\{$class}Connection";

		$this->_db = new $fullClassName($dbConnection);
	}

	// Method to connect to the DataBase server:
	public function connect()
	{
		$this->_db->connect();
	}

	// Method to select the DataBase:
	public function dateAddQuery(string $sqltype, mixed $amount = null, mixed $timeform = null): string
	{
		return $this->_db->dateAddQuery($sqltype, $amount, $timeform);
	}

	// Method to select the DataBase:
	public function dateSubQuery(string $sqltype, mixed $amount = null, mixed $timeform = null): string
	{
		return $this->_db->dateSubQuery($sqltype, $amount, $timeform);
	}

	// Method to select the DataBase:
	public function sqlUnixTimestamp(string $column_name): mixed
	{
		return $this->_db->sqlUnixTimestamp($column_name);
	}

	// Method to select the DataBase:
	public function sqlFromUnixTimestamp(mixed $column_name): mixed
	{
		return $this->_db->sqlFromUnixTimestamp($column_name);
	}

	public function ipToStore($ip)
	{
		return $this->_db->ipToStore($ip);
	}

	public function ipFromStore($ip)
	{
		return $this->_db->ipFromStore($ip);
	}

	// Method to select the DataBase:
	public function checkTable($table)
	{
		return $this->_db->checkTable($table);
	}

	// Method to determine if an error has occured:
	public function checkColumn($table = null, $column = null)
	{
		return $this->_db->checkColumn($table, $column);
	}

	// Method to return the error report:
	public function getDBError(?string $message = null, ?string $report = null, ?string $file = null, ?int $line = null)
	{
		return $this->_db->getDBError($message, $report, $file, $line);
	}
}
