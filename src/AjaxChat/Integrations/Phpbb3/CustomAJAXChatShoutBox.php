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

namespace AjaxChat\Integrations\PhpBB3;

use AjaxChat\Template;

class CustomAJAXChatShoutBox extends CustomAJAXChat
{

	public function initialize() {
		// Initialize configuration settings:
		$this->initConfig();
	}

	public function getShoutBoxContent()
	{
		if ($this->getConfig('allowGuestAccessShoutbox') === false) {
			header('Location: ' . AJAX_CHAT_URL);

			exit;
		}

		if ($this->getConfig('allowGuestLogins') === false) {
			header('Location: ' . AJAX_CHAT_URL);

			exit;
		}

		$template = new Template($this, AJAX_CHAT_PATH . 'src/template/shoutbox.html');

		// Return parsed template content:
		return $template->getParsedContent();
	}
}
