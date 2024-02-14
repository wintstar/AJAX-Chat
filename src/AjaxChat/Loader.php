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

namespace AjaxChat;

class Loader
{
	/**
	 * Loads the right integration version of CustomAJAXChat based on config.
	 */
	public static function NewFromConfig()
	{
		$config = self::readConfigFile();

		if (!array_key_exists('integration', $config)) {
			return new \AjaxChat\Integrations\Standalone\CustomAJAXChat();
		}

		$integrationFolder = ucwords($config['integration']);
		$fullClassName = "\\AjaxChat\\Integrations\\{$integrationFolder}\\CustomAJAXChat";

		return new $fullClassName();
	}

	/**
	 * Note that loading the standard config file has the side effect of setting several global variables.
	 */
	public static function readConfigFile()
	{
		$config = [];
		$defined_vars = null;

		if (file_exists(AJAX_CHAT_CONFIG_PATH)) {
			$defined_vars = get_defined_vars();

			require AJAX_CHAT_CONFIG_PATH;
			$config = array_diff_key(get_defined_vars(), $defined_vars);
		} else {
			echo('<strong>Error:</strong> Could not find configuration file at "' . AJAX_CHAT_CONFIG_PATH . '". Check to make sure the file exists.');

			exit;
		}

		return $config;
	}
}
