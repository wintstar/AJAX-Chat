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

// Path to the chat directory:
define('AJAX_CHAT_PATH', dirname(__DIR__) . '/');
define('AJAX_CHAT_CONFIG_PATH', AJAX_CHAT_PATH . 'src/config.php');

// Suppress errors:
// If anything goes wrong loading Settings.php, make sure the admin knows it.
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 'on');
ini_set('error_log', AJAX_CHAT_PATH . 'logs/index.log');

require AJAX_CHAT_PATH . '/src/constants.php';

require AJAX_CHAT_PATH . '/includes/debug.php';

require AJAX_CHAT_PATH . '/includes/ErrorHandler.php';

// Include custom libraries and initialization code:
require AJAX_CHAT_PATH . 'src/custom.php';

// Include Class libraries:
// if you don't want to use Composer then commented without // the autoloader to vendor and use the bootstrap to bootstrap

// with Composer
require AJAX_CHAT_PATH . 'vendor/autoload.php';

// without Composer. Autoloader is equivalent to PSR-4
// require AJAX_CHAT_PATH.'bootstrap/autoload.php';

// Initialize the chat:
$ajaxChat = \AjaxChat\Loader::newFromConfig();
