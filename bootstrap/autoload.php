<?php
/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @author Philip Nicolcev
 * @copyright (c) Sebastian Tschan
 * @license Modified MIT License
 * @link https://blueimp.net/ajax/
 *
 * @subpackage bootstrap/autoload.php
 * @author St. Frank <webdesign@stephan-frank.de> https://www.stephan-frank.de
 * @copyright (c) 2024 St.Frank
 *
*/

/**
 * AutoLoader
 * Autoloader is equivalent to PSR-4
 *
 * @param string $className
 */
function AutoLoader($className): void
{
	// Project specific namespace prefix.
	$prefix = '';

	// Base directory for the namespace prefix.
	$baseDir = dirname(__DIR__) . '/src/';

	// Check whether the class uses the namespace prefix.
	$len = strlen($prefix);

	if (strncmp($prefix, $className, $len) !== 0) {
		// If the namespace prefix is not used, the process is aborted.
		return;
	}

	// Determine the relative class name.
	$relativeClassName = substr($className, $len);

	//Add the namespace prefix with the base directory, replace namespace separator
	// with directory separator in the relative class name,  append .php.
	$file = $baseDir . str_replace('\\', '/', $relativeClassName) . '.php';

	// Return the path to the class file.
	if (file_exists($file)) {
		require $file;
	}
}

spl_autoload_register('AutoLoader');
