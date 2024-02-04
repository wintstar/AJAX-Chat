<?php

namespace AjaxChat\Integrations\Standalone;

use AjaxChat\Template;

class CustomAJAXChatShoutBox extends CustomAJAXChat
{
	public $_config;

	public function __construct(array $config)
	{
		$this->initialize($config);
	}

	public function initialize(array $config)
	{
		// Initialize configuration settings:
		$this->_config = $config;

		// Initialize custom configuration settings:
		$this->initCustomConfig();
	}

	public function getShoutBoxContent()
	{
		$template = new Template($this, AJAX_CHAT_PATH . 'src/template/shoutbox.html');

		// Return parsed template content:
		return $template->getParsedContent();
	}
}
