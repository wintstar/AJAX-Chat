<?php
/*
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


function getURL()
{
	$secure = false;

	if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
		$secure = true;
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
		$secure = true;
	}

	$root = dirname(__DIR__, 1);
	$host = empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];

	if ($_SERVER['SERVER_NAME'] == 'localhost') {
		return 'http' . ($secure ? 's' : '') . '://' . $host . '/' . basename($root) . '/';
	}

	return 'http' . ($secure ? 's' : '') . '://' . $host . '/';
}
