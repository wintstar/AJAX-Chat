<?php
namespace AjaxChat\Integrations\PhpBB3;
/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) Sebastian Tschan
 * @license Modified MIT License
 * @link https://blueimp.net/ajax/
 */
use AjaxChat\CustomAJAXChat;
use AjaxChat\Template;

class CustomAJAXChatShoutBox extends CustomAJAXChat
{
	protected $_config;

	function __construct() {
		$config = Loader::readConfigFile(AJAX_CHAT_PATH.'src/config.php');

		$this->initialize($config);
	}

	public function initialize(array $config) {
		// Initialize configuration settings:
		$this->_config = &$config;

		// Initialize custom configuration settings:
		$this->initCustomConfig();
	}

	function getShoutBoxContent()
	{
		$template = new Template($this, AJAX_CHAT_PATH . 'src/template/shoutbox.html');

		// Return parsed template content:
		return $template->getParsedContent();
	}
}
