<?php
/**
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @author Philip Nicolcev
 * @author Stephan Frank
 * @copyright (c) Sebastian Tschan
 * @license Modified MIT License
 * @link https://blueimp.net/ajax/
 */

// Define Chat url
define('AJAX_CHAT_URL', getURL());

// Define AJAX Chat user roles:
define('AJAX_CHAT_BANNED', 6);
define('AJAX_CHAT_CUSTOM', 5);
define('AJAX_CHAT_CHATBOT', 4);
define('AJAX_CHAT_ADMIN', 3);
define('AJAX_CHAT_MODERATOR', 2);
define('AJAX_CHAT_USER', 1);
define('AJAX_CHAT_GUEST', 0);


/**
 * getURL
 * Determine Url
 * @author Stephan Frank
 *
 * @return string
 */
function getURL(): string
{
	$secure = false;

	// Check is https or not.
	if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
		$secure = true;
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
		$secure = true;
	}

	// Check Subdomain root.
	// Parent directory's path (1 => level = The number of parent directories to go up.).
	$base_root = basename(dirname(__DIR__, 1));
	// The document root directory under which the current script is executing, as defined in the server's configuration file.
	$doc_root = basename($_SERVER['DOCUMENT_ROOT']);
	// Is base_root and doc_root same? If these are the same, then do not specify a directory.
	$root = ($doc_root != $base_root) ? $base_root : '';

	// Define hostname.
	$host = empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];

	// Compose Url.
	$url = 'http' . ($secure ? 's' : '') . '://' . $host . '/';

	// Check is Subdomain or not. Url preceded by IP address (e.g.127.0.0.1) is recorded as a subdomain.
	// Do not count www if available.
	$domain = str_replace('www.', '', $url);
	$parsed = parse_url($domain);
	$exploded = explode('.', $parsed['host']);
	$is_subdomain = (count($exploded) > 2);

	if ($_SERVER['SERVER_NAME'] == 'localhost') {
		// if Server localhost:
		return $url . basename($root) . '/';
	} elseif ($is_subdomain == true) {
		// if Subdomain (xx.domain.xx or e.g 127.0.0.1):
		return $url . basename($root) . '/';
	} else {
		// if not localhost and not Subdomain (xx.domain.xx or e.g 127.0.0.1)
		return $url;
	}
}
