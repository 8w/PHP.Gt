<?php

use RoadTest\Configuration\Configuration;
use RoadTest\Utility\Logger\LoggerFactory;

class Dal implements ArrayAccess {
/**
 * The DAL object is a wrapper to the actual DAL provided by PHP's PDO class.
 * There is no direct way of calling the DAL's methods, as this is done through
 * the API class (the API class can perform manipulation on the data provided
 * directly from the DAL).
 *
 * Handled in this class is the connection settings to the MySQL database, and
 * the automatic database deployment and just-in-time table creation.
 *
 * TODO: createTableAndDependencies needs optimising, plus there are too many
 * bugs at the moment.
 */
private $_connectionString;
/** @var PDO  */
private $_dbh = null;
private $_dalElArray = array();

/**
 * Only ever called internally. Stores the required settings for when the
 * connection is made.
 * For efficiency, the connection is not made until it needs to be used.
 */
public function __construct() {
	$_SESSION["DbDeploy_TableCache"] = array();
    $this->_connectionString = "mysql"
        . ":dbname=" 	. Configuration::getDatabaseName()
        . ";host=" 		. Configuration::getDatabaseHost()
        . ";port=" 		. Configuration::getDatabasePort()
        . ";charset=" 	. "utf8";
}

/**
 * Called at the end of each page request. Setting a PDO object to null
 * is all that is required to allow PHP's garbage collector to deallocate
 * the resource, however if there are any advancements in the database
 * connector, they may need extra destruction actions.
 */
public function __destruct() {
	$this->_dbh = null;
}

/**
 * Creates a new database connection with the settings provided to the
 * constructor. At the moment, it is only possible to connect to one database
 * per application.
 */
public function connect() {
	if(!is_null($this->_dbh)) {
		return;
	}
	try {
		$this->_dbh = new PDO(
			$this->_connectionString,
			Configuration::getDatabaseUser(),
			Configuration::getDatabasePassword()
		);
		$this->_dbh->setAttribute(
			PDO::ATTR_ERRMODE,
			PDO::ERRMODE_EXCEPTION);
		$this->_dbh->query("SET time_zone='+0:00';");
	}
	catch(PDOException $e) {
		LoggerFactory::get($this)->critical("Unable to connect to database", [$e]);
	}
}

/**
 * The Dal object implements ArrayAccess, so that each created DalElement
 * can be cached into an associative array. This means that during a single
 * request, only one DalElement of the same type is required to be
 * constructed.
 *
 * The function always returns true, because if the offset does not exist,
 * it will be automatically created.
 */
public function offsetExists($offset) {
	// First, check cache to see if DalObject already exists.
	if(array_key_exists($offset, $this->_dalElArray)) {
		return true;
	}

	$this->_dalElArray[$offset] = new DalEl(
		$this,
		$offset
	);

	return true;
}

/**
 * Gets the cached DalElement from the Dal object's internal array cache.
 * Providing an offset that doesn't exist will cause the offset to be
 * automatically created (from within offsetExists method).
 */
public function offsetGet($offset) {
	$offset = ucfirst($offset);
	if(!$this->offsetExists($offset)) {
		// TODO: Proper error handling - DalObject doesn't exist.
		return null;
	}

	return $this->_dalElArray[$offset];
}

/**
 * Setting the DalElement cache is not allowed.
 */
public function offsetSet($offset, $value) {}

/**
 * Unsetting the DalElement cache is not allowed.
 */
public function offsetUnset($offset) {}

/**
 * Returns the value of the last inserted primary key, null if not set.
 */
public function lastInsertID() {
	return $this->_dbh->lastInsertID();
}

/**
 * Prepares an SQL statement, ready to have variables injected and to be
 * executed later.
 */
public function prepare($sql) {
	return $this->_dbh->prepare($sql);
}
}#
