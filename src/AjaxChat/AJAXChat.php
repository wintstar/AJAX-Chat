<?php

namespace AjaxChat;

/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) Sebastian Tschan
 * @license Modified MIT License
 * @link https://blueimp.net/ajax/
 */

use AjaxChat\Database\Database;

// Ajax Chat backend logic:
class AJAXChat
{
	public $db;

	protected $_config;
	protected $_requestVars;
	protected $_infoMessages;
	protected $_channels;
	protected $_allChannels;
	protected $_view;
	protected $_lang;
	protected $_invitations;
	protected $_customVars;
	protected $_sessionNew;
	protected $_onlineUsersData;
	protected $_bannedUsersData;

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

		// Initialize the DataBase connection:
		$this->initDataBaseConnection();

		// Initialize request variables:
		$this->initRequestVars();

		// Initialize the chat session:
		$this->initSession();

		// Handle the browser request and send the response content:
		$this->handleRequest();
	}

	public function initRequestVars()
	{
		$this->_requestVars = [];
		$this->_requestVars['ajax']			= isset($_REQUEST['ajax']) ? true : false;
		$this->_requestVars['userID']		= isset($_REQUEST['userID']) ? (int) $_REQUEST['userID'] : null;
		$this->_requestVars['userName']		= $_REQUEST['userName'] ?? null;
		$this->_requestVars['channelID']	= isset($_REQUEST['channelID']) ? (int) $_REQUEST['channelID'] : null;
		$this->_requestVars['channelName']	= $_REQUEST['channelName'] ?? null;
		$this->_requestVars['text']			= $_POST['text'] ?? null;
		$this->_requestVars['lastID']		= isset($_REQUEST['lastID']) ? (int) $_REQUEST['lastID'] : 0;
		$this->_requestVars['login']		= isset($_REQUEST['login']) ? true : false;
		$this->_requestVars['logout']		= isset($_REQUEST['logout']) ? true : false;
		$this->_requestVars['tologout']		= isset($_REQUEST['tologout']) ? true : false;
		$this->_requestVars['password']		= $_REQUEST['password'] ?? null;
		$this->_requestVars['view']			= $_REQUEST['view'] ?? null;
		$this->_requestVars['year']			= isset($_REQUEST['year']) ? (int) $_REQUEST['year'] : null;
		$this->_requestVars['month']		= isset($_REQUEST['month']) ? (int) $_REQUEST['month'] : null;
		$this->_requestVars['day']			= isset($_REQUEST['day']) ? (int) $_REQUEST['day'] : null;
		$this->_requestVars['hour']			= isset($_REQUEST['hour']) ? (int) $_REQUEST['hour'] : null;
		$this->_requestVars['search']		= $_REQUEST['search'] ?? null;
		$this->_requestVars['shoutbox']		= isset($_REQUEST['shoutbox']) ? true : false;
		$this->_requestVars['getInfos']		= $_REQUEST['getInfos'] ?? null;
		$this->_requestVars['lang']			= $_REQUEST['lang'] ?? null;
		$this->_requestVars['delete']		= isset($_REQUEST['delete']) ? (int) $_REQUEST['delete'] : null;
		$this->_requestVars['token']		= $_REQUEST['token'] ?? null;

		// Initialize custom request variables:
		$this->initCustomRequestVars();
	}

	public function initDataBaseConnection()
	{
		// Create a new database object:
		$this->db = new Database(
			$this->_config['dbConnection'],
		);

		// Use a new database connection if no existing is given:
		if(!$this->_config['dbConnection']['link']) {
			// Connect to the database server:
			$this->db->connect($this->_config['dbConnection']);

			if($this->db->error()) {
				echo $this->db->getError();

				die();
			}
			// Select the database:
			$this->db->select($this->_config['dbConnection']['name']);

			if($this->db->error()) {
				echo $this->db->getError();

				die();
			}
		}
		// Unset the dbConnection array for safety purposes:
		unset($this->_config['dbConnection']);
	}

	public function getDataBaseTable($table)
	{
		return ($this->db->getName() ? '`' . $this->db->getName() . '`.' . $this->getConfig('dbTableNames', $table) : $this->getConfig('dbTableNames', $table));
	}

	public function initSession()
	{
		// Start the PHP session (if not already started):
		$this->startSession();

		if($this->isLoggedIn()) {
			// Logout if the Session IP is not the same when logged in and ipCheck is enabled:
			if($this->getConfig('ipCheck') && ($this->getSessionIP() === null || $this->getSessionIP() != $_SERVER['REMOTE_ADDR'])) {
				$this->logout('IP');

				return;
			}

			// Logout if we receive a logout request, the chat has been closed or the userID could not be revalidated:
			if($this->getRequestVar('logout') && $this->getRequestVar('token') == session_id() || !$this->isChatOpen() || !$this->revalidateUserID()) {
				$this->logout();

				return;
			}

			// Logout if we receive a tologout request, the chat has been closed or the userID could not be revalidated:
			if($this->getRequestVar('tologout') && $this->getRequestVar('token') == session_id() || !$this->revalidateUserID()) {
				$this->logout();

				return;
			}

		} elseif(
			// Login if auto-login enabled or a login, userName or shoutbox parameter is given:
			$this->getConfig('forceAutoLogin')
			|| $this->getRequestVar('login')
			|| $this->getRequestVar('userName')
			|| $this->getRequestVar('shoutbox')
			) {
			$this->login();
		}

		// Initialize the view:
		$this->initView();

		if($this->getView() == 'chat') {
			$this->initChatViewSession();
		}

		if(!$this->getRequestVar('ajax') && !headers_sent()) {
			// Set style cookie:
			$this->setStyle();
			// Set langCode cookie:
			$this->setLangCodeCookie();
		}

		$this->initCustomSession();
	}

	public function updateLogsViewSocketAuthentication()
	{
		if($this->getUserRole() != AJAX_CHAT_ADMIN) {
			$channels = [];

			foreach($this->getChannels() as $channel) {
				if($this->getConfig('logsUserAccessChannelList') && !in_array($channel, $this->getConfig('logsUserAccessChannelList'))) {
					continue;
				}
				array_push($channels, $channel);
			}
			array_push($channels, $this->getPrivateMessageID());
			array_push($channels, $this->getPrivateChannelID());
		} else {
			// The channelID "ALL" authenticates for all channels:
			$channels = ['ALL'];
		}
		$this->updateSocketAuthentication(
			$this->getUserID(),
			$this->getSocketRegistrationID(),
			$channels,
		);
	}

	public function initChatViewSession()
	{
		// If channel is not null we are logged in to the chat view:
		if($this->getChannel() !== null) {
			// Check if the current user has been logged out due to inactivity:
			if(!$this->isUserOnline()) {
				$this->logout();

				return;
			}

			if($this->getRequestVar('ajax')) {
				$this->initChannel();
				$this->updateOnlineStatus();
				$this->checkAndRemoveInactive();
			}
		} else {
			if($this->getRequestVar('ajax')) {
				// Set channel, insert login messages and add to online list on first ajax request in chat view:
				$this->chatViewLogin();
			}
		}
	}

	public function isChatOpen()
	{
		if($this->getUserRole() == AJAX_CHAT_ADMIN) {
			return true;
		}

		if($this->getConfig('chatClosed')) {
			return false;
		}
		$time = time();

		if($this->getConfig('timeZoneOffset') !== null) {
			// Subtract the server timezone offset and add the config timezone offset:
			$time -= date('Z', $time);
			$time += $this->getConfig('timeZoneOffset');
		}

		// Check the opening hours:
		if($this->getConfig('openingHour') < $this->getConfig('closingHour')) {
			if(($this->getConfig('openingHour') > date('G', $time)) || ($this->getConfig('closingHour') <= date('G', $time))) {
				return false;
			}
		} else {
			if(($this->getConfig('openingHour') > date('G', $time)) && ($this->getConfig('closingHour') <= date('G', $time))) {
				return false;
			}
		}

		// Check the opening weekdays:
		return !(!in_array(date('w', $time), $this->getConfig('openingWeekDays')));
	}

	public function handleRequest()
	{
		if($this->getRequestVar('ajax')) {
			if($this->isLoggedIn()) {
				// Parse info requests (for current userName, etc.):
				$this->parseInfoRequests();

				// Parse command requests (e.g. message deletion):
				$this->parseCommandRequests();

				// Parse message requests:
				$this->initMessageHandling();
			}
			// Send chat messages and online user list in XML format:
			$this->sendXMLMessages();
		} else {
			// Display XHTML content for non-ajax requests:
			$this->sendXHTMLContent();
		}
	}

	public function parseCommandRequests()
	{
		if($this->getRequestVar('delete') !== null) {
			$this->deleteMessage($this->getRequestVar('delete'));
		}
	}

	public function parseInfoRequests()
	{
		if($this->getRequestVar('getInfos')) {
			$infoRequests = explode(',', $this->getRequestVar('getInfos'));

			foreach($infoRequests as $infoRequest) {
				$this->parseInfoRequest($infoRequest);
			}
		}
	}

	public function parseInfoRequest($infoRequest)
	{
		switch($infoRequest) {
			case 'userID':
				$this->addInfoMessage($this->getUserID(), 'userID');
				break;

			case 'userName':
				$this->addInfoMessage($this->getUserName(), 'userName');
				break;

			case 'userRole':
				$this->addInfoMessage($this->getUserRole(), 'userRole');
				break;

			case 'channelID':
				$this->addInfoMessage($this->getChannel(), 'channelID');
				break;

			case 'channelName':
				$this->addInfoMessage($this->getChannelName(), 'channelName');
				break;

			case 'socketRegistrationID':
				$this->addInfoMessage($this->getSocketRegistrationID(), 'socketRegistrationID');
				break;

			default:
				$this->parseCustomInfoRequest($infoRequest);
		}
	}

	public function sendXHTMLContent()
	{
		$httpHeader = new HTTPHeader($this->getConfig('contentEncoding'), $this->getConfig('contentType'));

		$template = new Template($this, $this->getTemplateFileName(), $httpHeader->getContentType());

		// Send HTTP header:
		$httpHeader->send();

		// Send parsed template content:
		echo $template->getParsedContent();
	}

	public function getTemplateDir()
	{
		return AJAX_CHAT_PATH . 'src/template/';
	}

	public function getTemplateFileName()
	{
		switch($this->getView()) {
			case 'chat':
				return $this->getTemplateDir() . 'loggedIn.html';

			case 'logs':
				return $this->getTemplateDir() . 'logs.html';

			default:
				return $this->getTemplateDir() . 'loggedOut.html';
		}
	}

	public function initView()
	{
		$this->_view = null;
		// "chat" is the default view:
		$view = ($this->getRequestVar('view') === null) ? 'chat' : $this->getRequestVar('view');

		if($this->hasAccessTo($view)) {
			$this->_view = $view;
		}
	}

	public function getView()
	{
		return $this->_view;
	}

	public function hasAccessTo($view)
	{
		switch($view) {
			case 'chat':
			case 'teaser':
				return (bool) ($this->isLoggedIn());

			case 'logs':
				return (bool) ($this->isLoggedIn() && (
					$this->getUserRole() == AJAX_CHAT_ADMIN
					|| ($this->getConfig('logsUserAccess')
					&& ($this->getUserRole() == AJAX_CHAT_MODERATOR || $this->getUserRole() == AJAX_CHAT_USER))
				));

			default:
				return false;
		}
	}

	public function login()
	{
		// Retrieve valid login user data (from request variables or session data):
		$userData = $this->getValidLoginUserData();

		if(!$userData) {
			$this->addInfoMessage('errorInvalidUser');

			return false;
		}

		// If the chat is closed, only the admin may login:
		if(!$this->isChatOpen() && $userData['userRole'] != AJAX_CHAT_ADMIN) {
			$this->addInfoMessage('errorChatClosed');

			return false;
		}

		if(!$this->getConfig('allowGuestLogins') && $userData['userRole'] == AJAX_CHAT_GUEST) {
			return false;
		}

		// Check if userID or userName are already listed online:
		if($this->isUserOnline($userData['userID']) || $this->isUserNameInUse($userData['userName'])) {
			if($userData['userRole'] == AJAX_CHAT_USER || $userData['userRole'] == AJAX_CHAT_MODERATOR || $userData['userRole'] == AJAX_CHAT_ADMIN) {
				// Set the registered user inactive and remove the inactive users so the user can be logged in again:
				$this->setInactive($userData['userID'], $userData['userName']);
				$this->removeInactive();
			} else {
				$this->addInfoMessage('errorUserInUse');

				return false;
			}
		}

		// Check if user is banned:
		if($userData['userRole'] != AJAX_CHAT_ADMIN && $this->isUserBanned($userData['userName'], $userData['userID'], $_SERVER['REMOTE_ADDR'])) {
			$this->addInfoMessage('errorBanned');

			return false;
		}

		// Check if the max number of users is logged in (not affecting moderators or admins):
		if(!($userData['userRole'] == AJAX_CHAT_MODERATOR || $userData['userRole'] == AJAX_CHAT_ADMIN) && $this->isMaxUsersLoggedIn()) {
			$this->addInfoMessage('errorMaxUsersLoggedIn');

			return false;
		}

		// Use a new session id (if session has been started by the chat):
		$this->regenerateSessionID();

		// Log in:
		$this->setUserID($userData['userID']);
		$this->setUserName($userData['userName']);
		$this->setLoginUserName($userData['userName']);
		$this->setUserRole($userData['userRole']);
		$this->setLoggedIn(true);
		$this->setLoginTimeStamp(time());

		// IP Security check variable:
		$this->setSessionIP($_SERVER['REMOTE_ADDR']);

		// Add userID, userName and userRole to info messages:
		$this->addInfoMessage($this->getUserID(), 'userID');
		$this->addInfoMessage($this->getUserName(), 'userName');
		$this->addInfoMessage($this->getUserRole(), 'userRole');

		// Purge logs:
		if($this->getConfig('logsPurgeLogs')) {
			$this->purgeLogs();
		}

		return true;
	}

	public function chatViewLogin()
	{
		$this->setChannel($this->getValidRequestChannelID());
		$this->addToOnlineList();

		// Add channelID and channelName to info messages:
		$this->addInfoMessage($this->getChannel(), 'channelID');
		$this->addInfoMessage($this->getChannelName(), 'channelName');

		// Login message:
		$text = '/login ' . $this->getUserName();
		$this->insertChatBotMessage(
			$this->getChannel(),
			$text,
			null,
			1,
		);
	}

	public function getValidRequestChannelID()
	{
		$channelID = $this->getRequestVar('channelID');
		$channelName = $this->getRequestVar('channelName');

		// Check the given channelID, or get channelID from channelName:
		if($channelID === null) {
			if($channelName !== null) {
				$channelID = $this->getChannelIDFromChannelName($channelName);

				// channelName might need encoding conversion:
				if($channelID === null) {
					$channelID = $this->getChannelIDFromChannelName(
						$this->trimChannelName($channelName, $this->getConfig('contentEncoding')),
					);
				}
			}
		}

		// Validate the resulting channelID:
		if(!$this->validateChannel($channelID)) {
			if($this->getChannel() !== null) {
				return $this->getChannel();
			}

			return $this->getConfig('defaultChannelID');
		}

		return $channelID;
	}

	public function initChannel()
	{
		$channelID = $this->getRequestVar('channelID');
		$channelName = $this->getRequestVar('channelName');

		if($channelID !== null) {
			$this->switchChannel($this->getChannelNameFromChannelID($channelID));
		} elseif($channelName !== null) {
			if($this->getChannelIDFromChannelName($channelName) === null) {
				// channelName might need encoding conversion:
				$channelName = $this->trimChannelName($channelName, $this->getConfig('contentEncoding'));
			}
			$this->switchChannel($channelName);
		}
	}

	public function logout($type = null)
	{
		if($this->isUserOnline()) {
			$this->chatViewLogout($type);
		}
		$this->setLoggedIn(false);
		$this->destroySession();

		// Re-initialize the view:
		$this->initView();
	}

	public function chatViewLogout($type)
	{
		$this->removeFromOnlineList();

		if($type !== null) {
			$type = ' ' . $type;
		}
		// Logout message
		$text = '/logout ' . $this->getUserName() . $type;
		$this->insertChatBotMessage(
			$this->getChannel(),
			$text,
			null,
			1,
		);
	}

	public function switchChannel($channelName)
	{
		$channelID = $this->getChannelIDFromChannelName($channelName);

		if($channelID !== null && $this->getChannel() == $channelID) {
			// User is already in the given channel, return:
			return;
		}

		// Check if we have a valid channel:
		if(!$this->validateChannel($channelID)) {
			// Invalid channel:
			$text = '/error InvalidChannelName ' . $channelName;
			$this->insertChatBotMessage(
				$this->getPrivateMessageID(),
				$text,
			);

			return;
		}

		$oldChannel = $this->getChannel();

		$this->setChannel($channelID);
		$this->updateOnlineList();

		// Channel leave message
		$text = '/channelLeave ' . $this->getUserName();
		$this->insertChatBotMessage(
			$oldChannel,
			$text,
			null,
			1,
		);

		// Channel enter message
		$text = '/channelEnter ' . $this->getUserName();
		$this->insertChatBotMessage(
			$this->getChannel(),
			$text,
			null,
			1,
		);

		$this->addInfoMessage($channelName, 'channelSwitch');
		$this->addInfoMessage($channelID, 'channelID');
		$this->_requestVars['lastID'] = 0;
	}

	public function addToOnlineList()
	{
		$sql = 'INSERT INTO ' . $this->getDataBaseTable('online') . '(
					userID,
					userName,
					userRole,
					channel,
					dateTime,
					ip
				)
				VALUES (
					' . $this->db->makeSafe($this->getUserID()) . ',
					' . $this->db->makeSafe($this->getUserName()) . ',
					' . $this->db->makeSafe($this->getUserRole()) . ',
					' . $this->db->makeSafe($this->getChannel()) . ',
					NOW(),
					' . $this->db->makeSafe($this->ipToStorageFormat($_SERVER['REMOTE_ADDR'])) . '
				);';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}

		$this->resetOnlineUsersData();
	}

	public function removeFromOnlineList()
	{
		$sql = 'DELETE FROM
					' . $this->getDataBaseTable('online') . '
				WHERE
					userID = ' . $this->db->makeSafe($this->getUserID()) . ';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}

		$this->removeUserFromOnlineUsersData();
	}

	public function updateOnlineList()
	{
		$sql = 'UPDATE
					' . $this->getDataBaseTable('online') . '
				SET
					userName 	= ' . $this->db->makeSafe($this->getUserName()) . ',
					channel 	= ' . $this->db->makeSafe($this->getChannel()) . ',
					dateTime 	= NOW(),
					ip			= ' . $this->db->makeSafe($this->ipToStorageFormat($_SERVER['REMOTE_ADDR'])) . '
				WHERE
					userID = ' . $this->db->makeSafe($this->getUserID()) . ';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}

		$this->resetOnlineUsersData();
	}

	public function initMessageHandling()
	{
		// Don't handle messages if we are not in chat view:
		if($this->getView() != 'chat') {
			return;
		}

		// Check if we have been uninvited from a private or restricted channel:
		if(!$this->validateChannel($this->getChannel())) {
			// Switch to the default channel:
			$this->switchChannel($this->getChannelNameFromChannelID($this->getConfig('defaultChannelID')));

			return;
		}

		if($this->getRequestVar('text') !== null) {
			$this->insertMessage($this->getRequestVar('text'));
		}
	}

	public function insertParsedMessage($text)
	{

		// If a queryUserName is set, sent all messages as private messages to this userName:
		if($this->getQueryUserName() !== null && strpos($text, '/') !== 0) {
			$text = '/msg ' . $this->getQueryUserName() . ' ' . $text;
		}

		// Parse IRC-style commands:
		if(strpos($text, '/') === 0) {
			$textParts = explode(' ', $text);

			switch($textParts[0]) {

				// Channel switch:
				case '/join':
					$this->insertParsedMessageJoin($textParts);
					break;

				// Logout:
				case '/quit':
					$this->logout();
					break;

				// Private message:
				case '/msg':
				case '/describe':
					$this->insertParsedMessagePrivMsg($textParts);
					break;

				// Invitation:
				case '/invite':
					$this->insertParsedMessageInvite($textParts);
					break;

				// Uninvitation:
				case '/uninvite':
					$this->insertParsedMessageUninvite($textParts);
					break;

				// Private messaging:
				case '/query':
					$this->insertParsedMessageQuery($textParts);
					break;

				// Kicking offending users from the chat:
				case '/kick':
					$this->insertParsedMessageKick($textParts);
					break;

				// Listing banned users:
				case '/bans':
					$this->insertParsedMessageBans($textParts);
					break;

				// Unban user (remove from ban list):
				case '/unban':
					$this->insertParsedMessageUnban($textParts);
					break;

				// Describing actions:
				case '/action':
					$this->insertParsedMessageAction($textParts);
					break;


				// Listing online Users:
				case '/who':
					$this->insertParsedMessageWho($textParts);
					break;

				// Listing available channels:
				case '/list':
					$this->insertParsedMessageList($textParts);
					break;

				// Retrieving the channel of a User:
				case '/whereis':
					$this->insertParsedMessageWhereis($textParts);
					break;

				// Listing information about a User:
				case '/whois':
					$this->insertParsedMessageWhois($textParts);
					break;

				// Rolling dice:
				case '/roll':
					$this->insertParsedMessageRoll($textParts);
					break;

				// Switching userName:
				case '/nick':
					$this->insertParsedMessageNick($textParts);
					break;

				// Custom or unknown command:
				default:
					if(!$this->parseCustomCommands($text, $textParts)) {
						$this->insertChatBotMessage(
							$this->getPrivateMessageID(),
							'/error UnknownCommand ' . $textParts[0],
						);
					}
			}

		} else {
			// No command found, just insert the plain message:
			$this->insertCustomMessage(
				$this->getUserID(),
				$this->getUserName(),
				$this->getUserRole(),
				$this->getChannel(),
				$text,
			);
		}
	}

	public function insertParsedMessageJoin($textParts)
	{
		if(count($textParts) == 1) {
			// join with no arguments is the own private channel, if allowed:
			if($this->isAllowedToCreatePrivateChannel()) {
				// Private channels are identified by square brackets:
				$this->switchChannel($this->getChannelNameFromChannelID($this->getPrivateChannelID()));
			} else {
				$this->insertChatBotMessage(
					$this->getPrivateMessageID(),
					'/error MissingChannelName',
				);
			}
		} else {
			$this->switchChannel($textParts[1]);
		}
	}

	public function insertParsedMessagePrivMsg($textParts)
	{
		if($this->isAllowedToSendPrivateMessage()) {
			if(count($textParts) < 3) {
				if(count($textParts) == 2) {
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/error MissingText',
					);
				} else {
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/error MissingUserName',
					);
				}
			} else {
				// Get UserID from UserName:
				$toUserID = $this->getIDFromName($textParts[1]);

				if($toUserID === null) {
					if($this->getQueryUserName() !== null) {
						// Close the current query:
						$this->insertMessage('/query');
					} else {
						$this->insertChatBotMessage(
							$this->getPrivateMessageID(),
							'/error UserNameNotFound ' . $textParts[1],
						);
					}
				} else {
					// Insert /privaction command if /describe is used:
					$command = ($textParts[0] == '/describe') ? '/privaction' : '/privmsg';
					// Copy of private message to current User:
					$this->insertCustomMessage(
						$this->getUserID(),
						$this->getUserName(),
						$this->getUserRole(),
						$this->getPrivateMessageID(),
						$command . 'to ' . $textParts[1] . ' ' . implode(' ', array_slice($textParts, 2)),
					);
					// Private message to requested User:
					$this->insertCustomMessage(
						$this->getUserID(),
						$this->getUserName(),
						$this->getUserRole(),
						$this->getPrivateMessageID($toUserID),
						$command . ' ' . implode(' ', array_slice($textParts, 2)),
					);
				}
			}
		} else {
			$this->insertChatBotMessage(
				$this->getPrivateMessageID(),
				'/error PrivateMessageNotAllowed',
			);
		}
	}

	public function insertParsedMessageInvite($textParts)
	{
		if($this->getChannel() == $this->getPrivateChannelID() || in_array($this->getChannel(), $this->getChannels())) {
			if(count($textParts) == 1) {
				$this->insertChatBotMessage(
					$this->getPrivateMessageID(),
					'/error MissingUserName',
				);
			} else {
				$toUserID = $this->getIDFromName($textParts[1]);

				if($toUserID === null) {
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/error UserNameNotFound ' . $textParts[1],
					);
				} else {
					// Add the invitation to the database:
					$this->addInvitation($toUserID);
					$invitationChannelName = $this->getChannelNameFromChannelID($this->getChannel());
					// Copy of invitation to current User:
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/inviteto ' . $textParts[1] . ' ' . $invitationChannelName,
					);
					// Invitation to requested User:
					$this->insertChatBotMessage(
						$this->getPrivateMessageID($toUserID),
						'/invite ' . $this->getUserName() . ' ' . $invitationChannelName,
					);
				}
			}
		} else {
			$this->insertChatBotMessage(
				$this->getPrivateMessageID(),
				'/error InviteNotAllowed',
			);
		}
	}

	public function insertParsedMessageUninvite($textParts)
	{
		if($this->getChannel() == $this->getPrivateChannelID() || in_array($this->getChannel(), $this->getChannels())) {
			if(count($textParts) == 1) {
				$this->insertChatBotMessage(
					$this->getPrivateMessageID(),
					'/error MissingUserName',
				);
			} else {
				$toUserID = $this->getIDFromName($textParts[1]);

				if($toUserID === null) {
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/error UserNameNotFound ' . $textParts[1],
					);
				} else {
					// Remove the invitation from the database:
					$this->removeInvitation($toUserID);
					$invitationChannelName = $this->getChannelNameFromChannelID($this->getChannel());
					// Copy of uninvitation to current User:
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/uninviteto ' . $textParts[1] . ' ' . $invitationChannelName,
					);
					// Uninvitation to requested User:
					$this->insertChatBotMessage(
						$this->getPrivateMessageID($toUserID),
						'/uninvite ' . $this->getUserName() . ' ' . $invitationChannelName,
					);
				}
			}
		} else {
			$this->insertChatBotMessage(
				$this->getPrivateMessageID(),
				'/error UninviteNotAllowed',
			);
		}
	}

	public function insertParsedMessageQuery($textParts)
	{
		if($this->isAllowedToSendPrivateMessage()) {
			if(count($textParts) == 1) {
				if($this->getQueryUserName() !== null) {
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/queryClose ' . $this->getQueryUserName(),
					);
					// Close the current query:
					$this->setQueryUserName(null);
				} else {
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/error NoOpenQuery',
					);
				}
			} else {
				if($this->getIDFromName($textParts[1]) === null) {
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/error UserNameNotFound ' . $textParts[1],
					);
				} else {
					if($this->getQueryUserName() !== null) {
						// Close the current query:
						$this->insertMessage('/query');
					}
					// Open a query to the requested user:
					$this->setQueryUserName($textParts[1]);
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/queryOpen ' . $textParts[1],
					);
				}
			}
		} else {
			$this->insertChatBotMessage(
				$this->getPrivateMessageID(),
				'/error PrivateMessageNotAllowed',
			);
		}
	}

	public function insertParsedMessageKick($textParts)
	{
		// Only moderators/admins may kick users:
		if($this->getUserRole() == AJAX_CHAT_ADMIN || $this->getUserRole() == AJAX_CHAT_MODERATOR) {
			if(count($textParts) == 1) {
				$this->insertChatBotMessage(
					$this->getPrivateMessageID(),
					'/error MissingUserName',
				);
			} else {
				// Get UserID from UserName:
				$kickUserID = $this->getIDFromName($textParts[1]);

				if($kickUserID === null) {
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/error UserNameNotFound ' . $textParts[1],
					);
				} else {
					// Check the role of the user to kick:
					$kickUserRole = $this->getRoleFromID($kickUserID);

					if($kickUserRole == AJAX_CHAT_ADMIN || ($kickUserRole == AJAX_CHAT_MODERATOR && $this->getUserRole() != AJAX_CHAT_ADMIN)) {
						// Admins and moderators may not be kicked:
						$this->insertChatBotMessage(
							$this->getPrivateMessageID(),
							'/error KickNotAllowed ' . $textParts[1],
						);
					} else {
						// Kick user and insert message:
						$channel = $this->getChannelFromID($kickUserID);
						$banMinutes = (count($textParts) > 2) ? $textParts[2] : null;
						$this->kickUser($textParts[1], $banMinutes, $kickUserID);

						// If no channel found, user logged out before he could be kicked
						if($channel !== null) {
							$this->insertChatBotMessage(
								$channel,
								'/kick ' . $textParts[1],
								null,
								1,
							);

							// Send a copy of the message to the current user, if not in the channel:
							if($channel != $this->getChannel()) {
								$this->insertChatBotMessage(
									$this->getPrivateMessageID(),
									'/kick ' . $textParts[1],
									null,
									1,
								);
							}
						}
					}
				}
			}
		} else {
			$this->insertChatBotMessage(
				$this->getPrivateMessageID(),
				'/error CommandNotAllowed ' . $textParts[0],
			);
		}
	}

	public function insertParsedMessageBans($textParts)
	{
		// Only moderators/admins may see the list of banned users:
		if($this->getUserRole() == AJAX_CHAT_ADMIN || $this->getUserRole() == AJAX_CHAT_MODERATOR) {
			$this->removeExpiredBans();
			$bannedUsers = $this->getBannedUsers();

			if(count($bannedUsers) > 0) {
				$this->insertChatBotMessage(
					$this->getPrivateMessageID(),
					'/bans ' . implode(' ', $bannedUsers),
				);
			} else {
				$this->insertChatBotMessage(
					$this->getPrivateMessageID(),
					'/bansEmpty -',
				);
			}
		} else {
			$this->insertChatBotMessage(
				$this->getPrivateMessageID(),
				'/error CommandNotAllowed ' . $textParts[0],
			);
		}
	}

	public function insertParsedMessageUnban($textParts)
	{
		// Only moderators/admins may unban users:
		if($this->getUserRole() == AJAX_CHAT_ADMIN || $this->getUserRole() == AJAX_CHAT_MODERATOR) {
			$this->removeExpiredBans();

			if(count($textParts) == 1) {
				$this->insertChatBotMessage(
					$this->getPrivateMessageID(),
					'/error MissingUserName',
				);
			} else {
				if(!in_array($textParts[1], $this->getBannedUsers())) {
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/error UserNameNotFound ' . $textParts[1],
					);
				} else {
					// Unban user and insert message:
					$this->unbanUser($textParts[1]);
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/unban ' . $textParts[1],
					);
				}
			}
		} else {
			$this->insertChatBotMessage(
				$this->getPrivateMessageID(),
				'/error CommandNotAllowed ' . $textParts[0],
			);
		}
	}

	public function insertParsedMessageAction($textParts)
	{
		if(count($textParts) == 1) {
			$this->insertChatBotMessage(
				$this->getPrivateMessageID(),
				'/error MissingText',
			);
		} else {
			if($this->getQueryUserName() !== null) {
				// If we are in query mode, sent the action to the query user:
				$this->insertMessage('/describe ' . $this->getQueryUserName() . ' ' . implode(' ', array_slice($textParts, 1)));
			} else {
				$this->insertCustomMessage(
					$this->getUserID(),
					$this->getUserName(),
					$this->getUserRole(),
					$this->getChannel(),
					implode(' ', $textParts),
				);
			}
		}
	}

	public function insertParsedMessageWho($textParts)
	{
		if(count($textParts) == 1) {
			if($this->isAllowedToListHiddenUsers()) {
				// List online users from any channel:
				$this->insertChatBotMessage(
					$this->getPrivateMessageID(),
					'/who ' . implode(' ', $this->getOnlineUsers()),
				);
			} else {
				// Get online users for all accessible channels:
				$channels = $this->getChannels();

				// Add the own private channel if allowed:
				if($this->isAllowedToCreatePrivateChannel()) {
					array_push($channels, $this->getPrivateChannelID());
				}

				// Add the invitation channels:
				foreach($this->getInvitations() as $channelID) {
					if(!in_array($channelID, $channels)) {
						array_push($channels, $channelID);
					}
				}
				$this->insertChatBotMessage(
					$this->getPrivateMessageID(),
					'/who ' . implode(' ', $this->getOnlineUsers($channels)),
				);
			}
		} else {
			$channelName = $textParts[1];
			$channelID = $this->getChannelIDFromChannelName($channelName);

			if(!$this->validateChannel($channelID)) {
				// Invalid channel:
				$this->insertChatBotMessage(
					$this->getPrivateMessageID(),
					'/error InvalidChannelName ' . $channelName,
				);
			} else {
				// Get online users for the given channel:
				$onlineUsers = $this->getOnlineUsers([$channelID]);

				if(count($onlineUsers) > 0) {
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/whoChannel ' . $channelName . ' ' . implode(' ', $onlineUsers),
					);
				} else {
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/whoEmpty -',
					);
				}
			}
		}
	}

	public function insertParsedMessageList($textParts)
	{
		// Get the names of all accessible channels:
		$channelNames = $this->getChannelNames();

		// Add the own private channel, if allowed:
		if($this->isAllowedToCreatePrivateChannel()) {
			array_push($channelNames, $this->getPrivateChannelName());
		}

		// Add the invitation channels:
		foreach($this->getInvitations() as $channelID) {
			$channelName = $this->getChannelNameFromChannelID($channelID);

			if($channelName !== null && !in_array($channelName, $channelNames)) {
				array_push($channelNames, $channelName);
			}
		}
		$this->insertChatBotMessage(
			$this->getPrivateMessageID(),
			'/list ' . implode(' ', $channelNames),
		);
	}

	public function insertParsedMessageWhereis($textParts)
	{
		if(count($textParts) == 1) {
			$this->insertChatBotMessage(
				$this->getPrivateMessageID(),
				'/error MissingUserName',
			);
		} else {
			// Get UserID from UserName:
			$whereisUserID = $this->getIDFromName($textParts[1]);

			if($whereisUserID === null) {
				$this->insertChatBotMessage(
					$this->getPrivateMessageID(),
					'/error UserNameNotFound ' . $textParts[1],
				);
			} else {
				$channelID = $this->getChannelFromID($whereisUserID);

				if($this->validateChannel($channelID)) {
					$channelName = $this->getChannelNameFromChannelID($channelID);
				} else {
					$channelName = null;
				}

				if($channelName === null) {
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/error UserNameNotFound ' . $textParts[1],
					);
				} else {
					// List user information:
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/whereis ' . $textParts[1] . ' ' . $channelName,
					);
				}
			}
		}
	}

	public function insertParsedMessageWhois($textParts)
	{
		// Only moderators/admins:
		if($this->getUserRole() == AJAX_CHAT_ADMIN || $this->getUserRole() == AJAX_CHAT_MODERATOR) {
			if(count($textParts) == 1) {
				$this->insertChatBotMessage(
					$this->getPrivateMessageID(),
					'/error MissingUserName',
				);
			} else {
				// Get UserID from UserName:
				$whoisUserID = $this->getIDFromName($textParts[1]);

				if($whoisUserID === null) {
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/error UserNameNotFound ' . $textParts[1],
					);
				} else {
					// List user information:
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/whois ' . $textParts[1] . ' ' . $this->getIPFromID($whoisUserID),
					);
				}
			}
		} else {
			$this->insertChatBotMessage(
				$this->getPrivateMessageID(),
				'/error CommandNotAllowed ' . $textParts[0],
			);
		}
	}

	public function insertParsedMessageRoll($textParts)
	{
		if(count($textParts) == 1) {
			// default is one d6:
			$text = '/roll ' . $this->getUserName() . ' 1d6 ' . $this->rollDice(6);
		} else {
			$diceParts = explode('d', $textParts[1]);

			if(count($diceParts) == 2) {
				$number = (int) $diceParts[0];
				$sides = (int) $diceParts[1];

				// Dice number must be an integer between 1 and 100, else roll only one:
				$number = ($number > 0 && $number <= 100) ? $number : 1;

				// Sides must be an integer between 1 and 100, else take 6:
				$sides = ($sides > 0 && $sides <= 100) ? $sides : 6;

				$text = '/roll ' . $this->getUserName() . ' ' . $number . 'd' . $sides . ' ';

				for($i = 0; $i < $number; $i++) {
					if($i != 0) {
						$text .= ',';
					}
					$text .= $this->rollDice($sides);
				}
			} else {
				// if dice syntax is invalid, roll one d6:
				$text = '/roll ' . $this->getUserName() . ' 1d6 ' . $this->rollDice(6);
			}
		}
		$this->insertChatBotMessage(
			$this->getChannel(),
			$text,
		);
	}

	public function insertParsedMessageNick($textParts)
	{
		if(!$this->getConfig('allowNickChange')
			|| (!$this->getConfig('allowGuestUserName') && $this->getUserRole() == AJAX_CHAT_GUEST)) {
			$this->insertChatBotMessage(
				$this->getPrivateMessageID(),
				'/error CommandNotAllowed ' . $textParts[0],
			);
		} elseif(count($textParts) == 1) {
			$this->insertChatBotMessage(
				$this->getPrivateMessageID(),
				'/error MissingUserName',
			);
		} else {
			$newUserName = implode(' ', array_slice($textParts, 1));

			if($newUserName == $this->getLoginUserName()) {
				// Allow the user to regain the original login userName:
				$prefix = '';
				$suffix = '';
			} elseif($this->getUserRole() == AJAX_CHAT_GUEST) {
				$prefix = $this->getConfig('guestUserPrefix');
				$suffix = $this->getConfig('guestUserSuffix');
			} else {
				$prefix = $this->getConfig('changedNickPrefix');
				$suffix = $this->getConfig('changedNickSuffix');
			}
			$maxLength =	$this->getConfig('userNameMaxLength')
							- $this->stringLength($prefix)
							- $this->stringLength($suffix);
			$newUserName = $this->trimString($newUserName, 'UTF-8', $maxLength, true);

			if(!$newUserName) {
				$this->insertChatBotMessage(
					$this->getPrivateMessageID(),
					'/error InvalidUserName',
				);
			} else {
				$newUserName = $prefix . $newUserName . $suffix;

				if($this->isUserNameInUse($newUserName)) {
					$this->insertChatBotMessage(
						$this->getPrivateMessageID(),
						'/error UserNameInUse',
					);
				} else {
					$oldUserName = $this->getUserName();
					$this->setUserName($newUserName);
					$this->updateOnlineList();
					// Add info message to update the client-side stored userName:
					$this->addInfoMessage($this->getUserName(), 'userName');
					$this->insertChatBotMessage(
						$this->getChannel(),
						'/nick ' . $oldUserName . ' ' . $newUserName,
						null,
						2,
					);
				}
			}
		}
	}

	public function insertMessage($text)
	{
		if(!$this->isAllowedToWriteMessage()) {
			return;
		}

		if(!$this->floodControl()) {
			return;
		}

		$text = $this->trimMessageText($text);

		if($text == '') {
			return;
		}

		if(!$this->onNewMessage($text)) {
			return;
		}

		$text = $this->replaceCustomText($text);

		$this->insertParsedMessage($text);
	}

	public function deleteMessage($messageID)
	{
		// Retrieve the channel of the given message:
		$sql = 'SELECT
					channel
				FROM
					' . $this->getDataBaseTable('messages') . '
				WHERE
					id=' . $this->db->makeSafe($messageID) . ';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}

		$row = $result->fetch();

		if($row['channel'] !== null) {
			$channel = $row['channel'];

			if($this->getUserRole() == AJAX_CHAT_ADMIN) {
				$condition = '';
			} elseif($this->getUserRole() == AJAX_CHAT_MODERATOR) {
				$condition = '	AND
									NOT (userRole=' . $this->db->makeSafe(AJAX_CHAT_ADMIN) . ')
								AND
									NOT (userRole=' . $this->db->makeSafe(AJAX_CHAT_CHATBOT) . ')';
			} elseif($this->getUserRole() == AJAX_CHAT_USER && $this->getConfig('allowUserMessageDelete')) {
				$condition = 'AND
								(
								userID=' . $this->db->makeSafe($this->getUserID()) . '
								OR
									(
									channel = ' . $this->db->makeSafe($this->getPrivateMessageID()) . '
									OR
									channel = ' . $this->db->makeSafe($this->getPrivateChannelID()) . '
									)
									AND
										NOT (userRole=' . $this->db->makeSafe(AJAX_CHAT_ADMIN) . ')
									AND
										NOT (userRole=' . $this->db->makeSafe(AJAX_CHAT_CHATBOT) . ')
								)';
			} else {
				return false;
			}

			// Remove given message from the database:
			$sql = 'DELETE FROM
						' . $this->getDataBaseTable('messages') . '
					WHERE
						id=' . $this->db->makeSafe($messageID) . '
						' . $condition . ';';

			// Create a new SQL query:
			$result = $this->db->sqlQuery($sql);

			// Stop if an error occurs:
			if($result->error()) {
				echo $result->getError();

				die();
			}

			if($result->affectedRows() == 1) {
				// Insert a deletion command to remove the message from the clients chatlists:
				$this->insertChatBotMessage($channel, '/delete ' . $messageID);

				return true;
			}
		}

		return false;
	}

	public function floodControl()
	{
		// Moderators and Admins need no flood control:
		if($this->getUserRole() == AJAX_CHAT_MODERATOR || $this->getUserRole() == AJAX_CHAT_ADMIN) {
			return true;
		}
		$time = time();

		// Check the time of the last inserted message:
		if($this->getInsertedMessagesRateTimeStamp() + 60 < $time) {
			$this->setInsertedMessagesRateTimeStamp($time);
			$this->setInsertedMessagesRate(1);
		} else {
			// Increase the inserted messages rate:
			$rate = $this->getInsertedMessagesRate() + 1;
			$this->setInsertedMessagesRate($rate);

			// Check if message rate is too high:
			if($rate > $this->getConfig('maxMessageRate')) {
				$this->insertChatBotMessage(
					$this->getPrivateMessageID(),
					'/error MaxMessageRate',
				);

				// Return false so the message is not inserted:
				return false;
			}
		}

		return true;
	}

	public function isAllowedToWriteMessage()
	{
		if($this->getUserRole() != AJAX_CHAT_GUEST) {
			return true;
		}

		return (bool) ($this->getConfig('allowGuestWrite'));
	}

	public function insertChatBotMessage($channelID, $messageText, $ip = null, $mode = 0)
	{
		$this->insertCustomMessage(
			$this->getConfig('chatBotID'),
			$this->getConfig('chatBotName'),
			AJAX_CHAT_CHATBOT,
			$channelID,
			$messageText,
			$ip,
			$mode,
		);
	}

	public function insertCustomMessage($userID, $userName, $userRole, $channelID, $text, $ip = null, $mode = 0)
	{
		// The $mode parameter is used for socket updates:
		// 0 = normal messages
		// 1 = channel messages (e.g. login/logout, channel enter/leave, kick)
		// 2 = messages with online user updates (nick)

		$ip = $ip ? $ip : $_SERVER['REMOTE_ADDR'];

		$sql = 'INSERT INTO ' . $this->getDataBaseTable('messages') . '(
								userID,
								userName,
								userRole,
								channel,
								dateTime,
								ip,
								text
							)
				VALUES (
					' . $this->db->makeSafe($userID) . ',
					' . $this->db->makeSafe($userName) . ',
					' . $this->db->makeSafe($userRole) . ',
					' . $this->db->makeSafe($channelID) . ',
					NOW(),
					' . $this->db->makeSafe($this->ipToStorageFormat($ip)) . ',
					' . $this->db->makeSafe($text) . '
				);';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}
	}

	public function getSocketBroadcastMessage(
		$messageID,
		$timeStamp,
		$userID,
		$userName,
		$userRole,
		$channelID,
		$text,
		$mode,
	) {
		// The $mode parameter:
		// 0 = normal messages
		// 1 = channel messages (e.g. login/logout, channel enter/leave, kick)
		// 2 = messages with online user updates (nick)

		// Get the message XML content:
		$xml = '<root chatID="' . $this->getConfig('socketServerChatID') . '" channelID="' . $channelID . '" mode="' . $mode . '">';

		if($mode) {
			// Add the list of online users if the user list has been updated ($mode > 0):
			$xml .= $this->getChatViewOnlineUsersXML([$channelID]);
		}

		if($mode != 1 || $this->getConfig('showChannelMessages')) {
			$xml .= '<messages>';
			$xml .= $this->getChatViewMessageXML(
				$messageID,
				$timeStamp,
				$userID,
				$userName,
				$userRole,
				$channelID,
				$text,
			);
			$xml .= '</messages>';
		}
		$xml .= '</root>';

		return $xml;
	}

	public function sendSocketMessage($message)
	{
		// Open a TCP socket connection to the socket server:
		if($socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {
			if(@socket_connect($socket, $this->getConfig('socketServerIP'), $this->getConfig('socketServerPort'))) {
				// Append a null-byte to the string as EOL (End Of Line) character
				// which is required by Flash XML socket communication:
				$message .= "\0";
				@socket_write(
					$socket,
					$message,
					strlen($message), // Using strlen to count the bytes instead of the number of UTF-8 characters
				);
			}
			@socket_close($socket);
		}
	}

	public function updateSocketAuthentication($userID, $socketRegistrationID = null, $channels = null)
	{
		// If no $socketRegistrationID or no $channels are given the authentication is removed for the given user:
		$authentication = '<authenticate chatID="' . $this->getConfig('socketServerChatID') . '" userID="' . $userID . '" regID="' . $socketRegistrationID . '">';

		if($channels) {
			foreach($channels as $channelID) {
				$authentication .= '<channel id="' . $channelID . '"/>';
			}
		}
		$authentication .= '</authenticate>';
		$this->sendSocketMessage($authentication);
	}

	public function setSocketRegistrationID($value)
	{
		$this->setSessionVar('SocketRegistrationID', $value);
	}

	public function getSocketRegistrationID()
	{
		return $this->getSessionVar('SocketRegistrationID');
	}

	public function rollDice($sides)
	{
		return mt_rand(1, $sides);
	}

	public function kickUser($userName, $banMinutes = null, $userID = null)
	{
		if($userID === null) {
			$userID = $this->getIDFromName($userName);
		}

		if($userID === null) {
			return;
		}

		$banMinutes = ($banMinutes !== null) ? $banMinutes : $this->getConfig('defaultBanTime');

		if($banMinutes) {
			// Ban User for the given time in minutes:
			$this->banUser($userName, $banMinutes, $userID);
		}

		// Remove given User from online list:
		$sql = 'DELETE FROM
					' . $this->getDataBaseTable('online') . '
				WHERE
					userID = ' . $this->db->makeSafe($userID) . ';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}

		$this->removeUserFromOnlineUsersData($userID);
	}

	public function getBannedUsersData($key = null, $value = null)
	{
		if($this->_bannedUsersData === null) {
			$this->_bannedUsersData = [];

			$sql = 'SELECT
						userID,
						userName,
						ip
					FROM
						' . $this->getDataBaseTable('bans') . '
					WHERE
						NOW() < dateTime;';

			// Create a new SQL query:
			$result = $this->db->sqlQuery($sql);

			// Stop if an error occurs:
			if($result->error()) {
				echo $result->getError();

				die();
			}

			while($row = $result->fetch()) {
				$row['ip'] = $this->ipFromStorageFormat($row['ip']);
				array_push($this->_bannedUsersData, $row);
			}

			$result->free();
		}

		if($key) {
			$bannedUsersData = [];

			foreach($this->_bannedUsersData as $bannedUserData) {
				if(!isset($bannedUserData[$key])) {
					return $bannedUsersData;
				}

				if($value) {
					if($bannedUserData[$key] == $value) {
						array_push($bannedUsersData, $bannedUserData);
					} else {
						continue;
					}
				} else {
					array_push($bannedUsersData, $bannedUserData[$key]);
				}
			}

			return $bannedUsersData;
		}

		return $this->_bannedUsersData;
	}

	public function getBannedUsers()
	{
		return $this->getBannedUsersData('userName');
	}

	public function banUser($userName, $banMinutes = null, $userID = null)
	{
		if($userID === null) {
			$userID = $this->getIDFromName($userName);
		}
		$ip = $this->getIPFromID($userID);

		if(!$ip || $userID === null) {
			return;
		}

		// Remove expired bans:
		$this->removeExpiredBans();

		$banMinutes = (int) $banMinutes;

		if(!$banMinutes) {
			// If banMinutes is not a valid integer, use the defaultBanTime:
			$banMinutes = $this->getConfig('defaultBanTime');
		}

		$sql = 'INSERT INTO ' . $this->getDataBaseTable('bans') . '(
					userID,
					userName,
					dateTime,
					ip
				)
				VALUES (
					' . $this->db->makeSafe($userID) . ',
					' . $this->db->makeSafe($userName) . ',
					DATE_ADD(NOW(), interval ' . $this->db->makeSafe($banMinutes) . ' MINUTE),
					' . $this->db->makeSafe($this->ipToStorageFormat($ip)) . '
				);';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}
	}

	public function unbanUser($userName)
	{
		$sql = 'DELETE FROM
					' . $this->getDataBaseTable('bans') . '
				WHERE
					userName = ' . $this->db->makeSafe($userName) . ';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}
	}

	public function removeExpiredBans()
	{
		$sql = 'DELETE FROM
					' . $this->getDataBaseTable('bans') . '
				WHERE
					dateTime < NOW();';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}
	}

	public function setInactive($userID, $userName = null)
	{
		$condition = 'userID=' . $this->db->makeSafe($userID);

		if($userName !== null) {
			$condition .= ' OR userName=' . $this->db->makeSafe($userName);
		}
		$sql = 'UPDATE
					' . $this->getDataBaseTable('online') . '
				SET
					dateTime = DATE_SUB(NOW(), interval ' . (intval($this->getConfig('inactiveTimeout')) + 1) . ' MINUTE)
				WHERE
					' . $condition . ';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}

		$this->resetOnlineUsersData();
	}

	public function removeInactive()
	{
		$sql = 'SELECT
					userID,
					userName,
					channel
				FROM
					' . $this->getDataBaseTable('online') . '
				WHERE
					NOW() > DATE_ADD(dateTime, interval ' . $this->getConfig('inactiveTimeout') . ' MINUTE);';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}

		if($result->numRows() > 0) {
			$condition = '';

			while($row = $result->fetch()) {
				if(!empty($condition)) {
					$condition .= ' OR ';
				}
				// Add userID to condition for removal:
				$condition .= 'userID=' . $this->db->makeSafe($row['userID']);

				$this->removeUserFromOnlineUsersData($row['userID']);

				// Insert logout timeout message:
				$text = '/logout ' . $row['userName'] . ' Timeout';
				$this->insertChatBotMessage(
					$row['channel'],
					$text,
					null,
					1,
				);
			}

			$result->free();

			$sql = 'DELETE FROM
						' . $this->getDataBaseTable('online') . '
					WHERE
						' . $condition . ';';

			// Create a new SQL query:
			$result = $this->db->sqlQuery($sql);

			// Stop if an error occurs:
			if($result->error()) {
				echo $result->getError();

				die();
			}
		}
	}

	public function updateOnlineStatus()
	{
		// Update online status every 50 seconds (this allows update requests to be in time):
		if(!$this->getStatusUpdateTimeStamp() || ((time() - $this->getStatusUpdateTimeStamp()) > 50)) {
			$this->updateOnlineList();
			$this->setStatusUpdateTimeStamp(time());
		}
	}

	public function checkAndRemoveInactive()
	{
		// Remove inactive users every inactiveCheckInterval:
		if(!$this->getInactiveCheckTimeStamp() || ((time() - $this->getInactiveCheckTimeStamp()) > $this->getConfig('inactiveCheckInterval') * 60)) {
			$this->removeInactive();
			$this->setInactiveCheckTimeStamp(time());
		}
	}

	public function sendXMLMessages()
	{
		$httpHeader = new HTTPHeader('UTF-8', 'text/xml');

		// Send HTTP header:
		$httpHeader->send();

		// Output XML messages:
		echo $this->getXMLMessages();
	}

	public function getXMLMessages()
	{
		switch($this->getView()) {
			case 'chat':
				return $this->getChatViewXMLMessages();

			case 'teaser':
				return $this->getTeaserViewXMLMessages();

			case 'logs':
				return $this->getLogsViewXMLMessages();

			case 'tologout':
				return $this->getToLogoutXMLMessage();

			default:
				return $this->getLogoutXMLMessage();
		}
	}

	public function getMessageCondition()
	{
		$condition = 	'id > ' . $this->db->makeSafe($this->getRequestVar('lastID')) . '
						AND (
							channel = ' . $this->db->makeSafe($this->getChannel()) . '
							OR
							channel = ' . $this->db->makeSafe($this->getPrivateMessageID()) . '
						)
						AND
						';

		if($this->getConfig('requestMessagesPriorChannelEnter')
			|| ($this->getConfig('requestMessagesPriorChannelEnterList') && in_array($this->getChannel(), $this->getConfig('requestMessagesPriorChannelEnterList')))) {
			$condition .= 'NOW() < DATE_ADD(dateTime, interval ' . $this->getConfig('requestMessagesTimeDiff') . ' HOUR)';
		} else {
			$condition .= 'dateTime >= FROM_UNIXTIME(' . $this->getChannelEnterTimeStamp() . ')';
		}

		return $condition;
	}

	public function getMessageFilter()
	{
			$filterChannelMessages = '';

			if(!$this->getConfig('showChannelMessages') || $this->getRequestVar('shoutbox')) {
				$filterChannelMessages = '	AND NOT (
											text LIKE (\'/login%\')
											OR
											text LIKE (\'/logout%\')
											OR
											text LIKE (\'/channelEnter%\')
											OR
											text LIKE (\'/channelLeave%\')
											OR
											text LIKE (\'/kick%\')
										)';
			}

			return $filterChannelMessages;
	}

	public function getInfoMessagesXML()
	{
		$xml = '<infos>';

		// Go through the info messages:
		foreach($this->getInfoMessages() as $type => $infoArray) {
			foreach($infoArray as $info) {
				$xml .= '<info type="' . $type . '">';
				$xml .= '<![CDATA[' . $this->encodeSpecialChars($info) . ']]>';
				$xml .= '</info>';
			}
		}
		$xml .= '</infos>';

		return $xml;
	}

	public function getChatViewOnlineUsersXML($channelIDs)
	{
		// Get the online users for the given channels:
		$onlineUsersData = $this->getOnlineUsersData($channelIDs);
		$xml = '<users>';

		foreach($onlineUsersData as $onlineUserData) {
			$xml .= '<user';
			$xml .= ' userID="' . $onlineUserData['userID'] . '"';
			$xml .= ' userRole="' . $onlineUserData['userRole'] . '"';
			$xml .= ' channelID="' . $onlineUserData['channel'] . '"';
			$xml .= '>';
			$xml .= '<![CDATA[' . $this->encodeSpecialChars($onlineUserData['userName']) . ']]>';
			$xml .= '</user>';
		}
		$xml .= '</users>';

		return $xml;
	}

	public function getLogoutXMLMessage()
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<root>';
		$xml .= '<infos>';
		$xml .= '<info type="logout">';
		$xml .= '<![CDATA[' . $this->encodeSpecialChars($this->getConfig('logoutData')) . ']]>';
		$xml .= '</info>';
		$xml .= '</infos>';
		$xml .= '</root>';

		return $xml;
	}

	public function getToLogoutXMLMessage()
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<root>';
		$xml .= '<infos>';
		$xml .= '<info type="tologout">';
		$xml .= '<![CDATA[./?tologout=true]]>';
		$xml .= '</info>';
		$xml .= '</infos>';
		$xml .= '</root>';

		return $xml;
	}

	public function getChatViewMessageXML(
		$messageID,
		$timeStamp,
		$userID,
		$userName,
		$userRole,
		$channelID,
		$text,
	) {
		$message = '<message';
		$message .= ' id="' . $messageID . '"';
		$message .= ' dateTime="' . date('r', $timeStamp) . '"';
		$message .= ' userID="' . $userID . '"';
		$message .= ' userRole="' . $userRole . '"';
		$message .= ' channelID="' . $channelID . '"';
		$message .= '>';
		$message .= '<username><![CDATA[' . $this->encodeSpecialChars($userName) . ']]></username>';
		$message .= '<text><![CDATA[' . $this->encodeSpecialChars($text) . ']]></text>';
		$message .= '</message>';

		return $message;
	}

	public function getChatViewMessagesXML()
	{
		// Get the last messages in descending order (this optimises the LIMIT usage):
		$sql = 'SELECT
					id,
					userID,
					userName,
					userRole,
					channel AS channelID,
					UNIX_TIMESTAMP(dateTime) AS timeStamp,
					text
				FROM
					' . $this->getDataBaseTable('messages') . '
				WHERE
					' . $this->getMessageCondition() . '
					' . $this->getMessageFilter() . '
				ORDER BY
					id
					DESC
				LIMIT ' . $this->getConfig('requestMessagesLimit') . ';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}

		$messages = '';

		// Add the messages in reverse order so it is ascending again:
		while($row = $result->fetch()) {
			$message = $this->getChatViewMessageXML(
				$row['id'],
				$row['timeStamp'],
				$row['userID'],
				$row['userName'],
				$row['userRole'],
				$row['channelID'],
				$row['text'],
			);
			$messages = $message . $messages;
		}
		$result->free();

		$messages = '<messages>' . $messages . '</messages>';

		return $messages;
	}

	public function getChatViewXMLMessages()
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<root>';
		$xml .= $this->getInfoMessagesXML();
		$xml .= $this->getChatViewOnlineUsersXML([$this->getChannel()]);
		$xml .= $this->getChatViewMessagesXML();
		$xml .= '</root>';

		return $xml;
	}

	public function getTeaserMessageCondition()
	{
		$channelID = $this->getValidRequestChannelID();
		$condition = 	'channel = ' . $this->db->makeSafe($channelID) . '
						AND
						';

		if($this->getConfig('requestMessagesPriorChannelEnter')
			|| ($this->getConfig('requestMessagesPriorChannelEnterList') && in_array($channelID, $this->getConfig('requestMessagesPriorChannelEnterList')))) {
			$condition .= 'NOW() < DATE_ADD(dateTime, interval ' . $this->getConfig('requestMessagesTimeDiff') . ' HOUR)';
		} else {
			// Teaser content may not be shown for this channel:
			$condition .= '0 = 1';
		}

		return $condition;
	}

	public function getTeaserViewMessagesXML()
	{
		// Get the last messages in descending order (this optimises the LIMIT usage):
		$sql = 'SELECT
					id,
					userID,
					userName,
					userRole,
					channel AS channelID,
					UNIX_TIMESTAMP(dateTime) AS timeStamp,
					text
				FROM
					' . $this->getDataBaseTable('messages') . '
				WHERE
					' . $this->getTeaserMessageCondition() . '
					' . $this->getMessageFilter() . '
				ORDER BY
					id
					DESC
				LIMIT ' . $this->getConfig('requestMessagesLimit') . ';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}

		$messages = '';

		// Add the messages in reverse order so it is ascending again:
		while($row = $result->fetch()) {
			$message = '';
			$message .= '<message';
			$message .= ' id="' . $row['id'] . '"';
			$message .= ' dateTime="' . date('r', $row['timeStamp']) . '"';
			$message .= ' userID="' . $row['userID'] . '"';
			$message .= ' userRole="' . $row['userRole'] . '"';
			$message .= ' channelID="' . $row['channelID'] . '"';
			$message .= '>';
			$message .= '<username><![CDATA[' . $this->encodeSpecialChars($row['userName']) . ']]></username>';
			$message .= '<text><![CDATA[' . $this->encodeSpecialChars($row['text']) . ']]></text>';
			$message .= '</message>';
			$messages = $message . $messages;
		}
		$result->free();

		$messages = '<messages>' . $messages . '</messages>';

		return $messages;
	}

	public function getTeaserViewXMLMessages()
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<root>';
		$xml .= $this->getInfoMessagesXML();
		$xml .= $this->getTeaserViewMessagesXML();
		$xml .= '</root>';

		return $xml;
	}

	public function getLogsViewCondition()
	{
		$condition = 'id > ' . $this->db->makeSafe($this->getRequestVar('lastID'));

		// Check the channel condition:
		switch($this->getRequestVar('channelID')) {
			case '-3':
				// Just display messages from all accessible channels
				if($this->getUserRole() != AJAX_CHAT_ADMIN) {
					$condition .= ' AND (channel = ' . $this->db->makeSafe($this->getPrivateMessageID());
					$condition .= ' OR channel = ' . $this->db->makeSafe($this->getPrivateChannelID());

					foreach($this->getChannels() as $channel) {
						if($this->getConfig('logsUserAccessChannelList') && !in_array($channel, $this->getConfig('logsUserAccessChannelList'))) {
							continue;
						}
						$condition .= ' OR channel = ' . $this->db->makeSafe($channel);
					}
					$condition .= ')';
				}
				break;

			case '-2':
				if($this->getUserRole() != AJAX_CHAT_ADMIN) {
					$condition .= ' AND channel = ' . ($this->getPrivateMessageID());
				} else {
					$condition .= ' AND channel > ' . ($this->getConfig('privateMessageDiff') - 1);
				}
				break;

			case '-1':
				if($this->getUserRole() != AJAX_CHAT_ADMIN) {
					$condition .= ' AND channel = ' . ($this->getPrivateChannelID());
				} else {
					$condition .= ' AND (channel > ' . ($this->getConfig('privateChannelDiff') - 1) . ' AND channel < ' . ($this->getConfig('privateMessageDiff')) . ')';
				}
				break;

			default:
				if(($this->getUserRole() == AJAX_CHAT_ADMIN || !$this->getConfig('logsUserAccessChannelList') || in_array($this->getRequestVar('channelID'), $this->getConfig('logsUserAccessChannelList')))
					&& $this->validateChannel($this->getRequestVar('channelID'))) {
					$condition .= ' AND channel = ' . $this->db->makeSafe($this->getRequestVar('channelID'));
				} else {
					// No valid channel:
					$condition .= ' AND 0 = 1';
				}
		}

		// Check the period condition:
		$hour	= ($this->getRequestVar('hour') === null || $this->getRequestVar('hour') > 23 || $this->getRequestVar('hour') < 0) ? null : $this->getRequestVar('hour');
		$day	= ($this->getRequestVar('day') === null || $this->getRequestVar('day') > 31 || $this->getRequestVar('day') < 1) ? null : $this->getRequestVar('day');
		$month	= ($this->getRequestVar('month') === null || $this->getRequestVar('month') > 12 || $this->getRequestVar('month') < 1) ? null : $this->getRequestVar('month');
		$year	= ($this->getRequestVar('year') === null || $this->getRequestVar('year') > date('Y') || $this->getRequestVar('year') < $this->getConfig('logsFirstYear')) ? null : $this->getRequestVar('year');

		// If a time (hour) is given but no date (year, month, day), use the current date:
		if($hour !== null) {
			if($day === null) {
				$day = date('j');
			}

			if($month === null) {
				$month = date('n');
			}

			if($year === null) {
				$year = date('Y');
			}
		}

		if($year === null) {
			// No year given, so no period condition
		} elseif($month === null) {
			// Define the given year as period:
			$periodStart = mktime(0, 0, 0, 1, 1, $year);
			// The last day in a month can be expressed by using 0 for the day of the next month:
			$periodEnd = mktime(23, 59, 59, 13, 0, $year);
		} elseif($day === null) {
			// Define the given month as period:
			$periodStart = mktime(0, 0, 0, $month, 1, $year);
			// The last day in a month can be expressed by using 0 for the day of the next month:
			$periodEnd = mktime(23, 59, 59, $month + 1, 0, $year);
		} elseif($hour === null) {
			// Define the given day as period:
			$periodStart = mktime(0, 0, 0, $month, $day, $year);
			$periodEnd = mktime(23, 59, 59, $month, $day, $year);
		} else {
			// Define the given hour as period:
			$periodStart = mktime($hour, 0, 0, $month, $day, $year);
			$periodEnd = mktime($hour, 59, 59, $month, $day, $year);
		}

		if(isset($periodStart)) {
			$condition .= ' AND dateTime > \'' . date('Y-m-d H:i:s', $periodStart) . '\' AND dateTime <= \'' . date('Y-m-d H:i:s', $periodEnd) . '\'';
		}

		// Check the search condition:
		if($this->getRequestVar('search')) {
			if(($this->getUserRole() == AJAX_CHAT_ADMIN || $this->getUserRole() == AJAX_CHAT_MODERATOR) && strpos($this->getRequestVar('search'), 'ip=') === 0) {
				// Search for messages with the given IP:
				$ip = substr($this->getRequestVar('search'), 3);
				$condition .= ' AND (ip = ' . $this->db->makeSafe($this->ipToStorageFormat($ip)) . ')';
			} elseif(strpos($this->getRequestVar('search'), 'userID=') === 0) {
				// Search for messages with the given userID:
				$userID = substr($this->getRequestVar('search'), 7);
				$condition .= ' AND (userID = ' . $this->db->makeSafe($userID) . ')';
			} else {
				// Use the search value as regular expression on message text and username:
				$condition .= ' AND (userName REGEXP ' . $this->db->makeSafe($this->getRequestVar('search')) . ' OR text REGEXP ' . $this->db->makeSafe($this->getRequestVar('search')) . ')';
			}
		}

		// If no period or search condition is given, just monitor the last messages on the given channel:
		if(!isset($periodStart) && !$this->getRequestVar('search')) {
			$condition .= ' AND NOW() < DATE_ADD(dateTime, interval ' . $this->getConfig('logsRequestMessagesTimeDiff') . ' HOUR)';
		}

		return $condition;
	}

	public function getLogsViewMessagesXML()
	{
		$sql = 'SELECT
					id,
					userID,
					userName,
					userRole,
					channel AS channelID,
					UNIX_TIMESTAMP(dateTime) AS timeStamp,
					ip,
					text
				FROM
					' . $this->getDataBaseTable('messages') . '
				WHERE
					' . $this->getLogsViewCondition() . '
				ORDER BY
					id
				LIMIT ' . $this->getConfig('logsRequestMessagesLimit') . ';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}

		$xml = '<messages>';

		while($row = $result->fetch()) {
			$xml .= '<message';
			$xml .= ' id="' . $row['id'] . '"';
			$xml .= ' dateTime="' . date('r', $row['timeStamp']) . '"';
			$xml .= ' userID="' . $row['userID'] . '"';
			$xml .= ' userRole="' . $row['userRole'] . '"';
			$xml .= ' channelID="' . $row['channelID'] . '"';

			if($this->getUserRole() == AJAX_CHAT_ADMIN || $this->getUserRole() == AJAX_CHAT_MODERATOR) {
				$xml .= ' ip="' . $this->ipFromStorageFormat($row['ip']) . '"';
			}
			$xml .= '>';
			$xml .= '<username><![CDATA[' . $this->encodeSpecialChars($row['userName']) . ']]></username>';
			$xml .= '<text><![CDATA[' . $this->encodeSpecialChars($row['text']) . ']]></text>';
			$xml .= '</message>';
		}
		$result->free();

		$xml .= '</messages>';

		return $xml;
	}

	public function getLogsViewXMLMessages()
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<root>';
		$xml .= $this->getInfoMessagesXML();
		$xml .= $this->getLogsViewMessagesXML();
		$xml .= '</root>';

		return $xml;
	}

	public function purgeLogs()
	{
		$sql = 'DELETE FROM
					' . $this->getDataBaseTable('messages') . '
				WHERE
					dateTime < DATE_SUB(NOW(), interval ' . $this->getConfig('logsPurgeTimeDiff') . ' DAY);';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}
	}

	public function getInfoMessages($type = null)
	{
		if(!isset($this->_infoMessages)) {
			$this->_infoMessages = [];
		}

		if($type) {
			if(!isset($this->_infoMessages[$type])) {
				$this->_infoMessages[$type] = [];
			}

			return $this->_infoMessages[$type];
		}

			return $this->_infoMessages;

	}

	public function addInfoMessage($info, $type = 'error')
	{
		if(!isset($this->_infoMessages)) {
			$this->_infoMessages = [];
		}

		if(!isset($this->_infoMessages[$type])) {
			$this->_infoMessages[$type] = [];
		}

		if(!in_array($info, $this->_infoMessages[$type])) {
			array_push($this->_infoMessages[$type], $info);
		}
	}

	public function getRequestVars()
	{
		return $this->_requestVars;
	}

	public function getRequestVar($key)
	{
		if($this->_requestVars && isset($this->_requestVars[$key])) {
			return $this->_requestVars[$key];
		}

	}

	public function setRequestVar($key, $value)
	{
		if(!$this->_requestVars) {
			$this->_requestVars = [];
		}
		$this->_requestVars[$key] = $value;
	}

	public function getOnlineUsersData($channelIDs = null, $key = null, $value = null)
	{
		if($this->_onlineUsersData === null) {
			$this->_onlineUsersData = [];

			$sql = 'SELECT
						userID,
						userName,
						userRole,
						channel,
						UNIX_TIMESTAMP(dateTime) AS timeStamp,
						ip
					FROM
						' . $this->getDataBaseTable('online') . '
					ORDER BY
						LOWER(userName);';

			// Create a new SQL query:
			$result = $this->db->sqlQuery($sql);

			// Stop if an error occurs:
			if($result->error()) {
				echo $result->getError();

				die();
			}

			while($row = $result->fetch()) {
				$row['ip'] = $this->ipFromStorageFormat($row['ip']);
				array_push($this->_onlineUsersData, $row);
			}

			$result->free();
		}

		if($channelIDs || $key) {
			$onlineUsersData = [];

			foreach($this->_onlineUsersData as $userData) {
				if($channelIDs && !in_array($userData['channel'], $channelIDs)) {
					continue;
				}

				if($key) {
					if(!isset($userData[$key])) {
						return $onlineUsersData;
					}

					if($value !== null) {
						if($userData[$key] == $value) {
							array_push($onlineUsersData, $userData);
						} else {
							continue;
						}
					} else {
						array_push($onlineUsersData, $userData[$key]);
					}
				} else {
					array_push($onlineUsersData, $userData);
				}
			}

			return $onlineUsersData;
		}

		return $this->_onlineUsersData;
	}

	public function removeUserFromOnlineUsersData($userID = null)
	{
		if(!$this->_onlineUsersData) {
			return;
		}
		$userID = ($userID === null) ? $this->getUserID() : $userID;

		for($i = 0; $i < count($this->_onlineUsersData); $i++) {
			if($this->_onlineUsersData[$i]['userID'] == $userID) {
				array_splice($this->_onlineUsersData, $i, 1);
				break;
			}
		}
	}

	public function resetOnlineUsersData()
	{
		$this->_onlineUsersData = null;
	}

	public function getOnlineUsers($channelIDs = null)
	{
		return $this->getOnlineUsersData($channelIDs, 'userName');
	}

	public function getOnlineUserIDs($channelIDs = null)
	{
		return $this->getOnlineUsersData($channelIDs, 'userID');
	}

	public function startSession()
	{
		if(!session_id()) {
			// Set the session name:
			session_name($this->getConfig('sessionName'));

			// Set session cookie parameters:
			session_set_cookie_params([
				'lifetime' 	=> 0, // The session is destroyed on logout anyway, so no use to set this
				'path' 		=> $this->getConfig('sessionCookiePath'),
				'domain' 	=> $this->getConfig('sessionCookieDomain'),
				'secure' 	=> $this->getConfig('sessionCookieSecure'),
				'samesite' 	=> $this->getConfig('sessioncookieSamesite'),
			]);

			// Start the session:
			session_start();

			// We started a new session:
			$this->_sessionNew = true;
		}
	}

	public function destroySession()
	{
		$cookie_name = $this->getConfig('sessionName');

		if($this->_sessionNew) {
			// Delete all session variables:
			$_SESSION = [];

			// Delete all existing cookies after logging out
			if (isset($_COOKIE[$cookie_name])) {
				setcookie($cookie_name, '', time() - 7000000);
			}

			if (isset($_COOKIE[$cookie_name . '_lang'])) {
				setcookie($cookie_name . '_lang', '', time() - 7000000);
			}

			if (isset($_COOKIE[$cookie_name . '_settings'])) {
				setcookie($cookie_name . '_settings', '', time() - 7000000);
			}

			if (isset($_COOKIE[$cookie_name . '_style'])) {
				setcookie($cookie_name . '_style', '', time() - 7000000);
			}

			// Destroy the session:
			session_unset();
			session_destroy();
		} else {
			// Unset all session variables starting with the sessionKeyPrefix:
			foreach($_SESSION as $key => $value) {
				if(strpos($key, $this->getConfig('sessionKeyPrefix')) === 0) {
					unset($_SESSION[$key]);
				}
			}
		}
	}

	public function regenerateSessionID()
	{
		if($this->_sessionNew) {
			// Regenerate session id:
			@session_regenerate_id(true);
		}
	}

	public function getSessionVar($key, $prefix = null)
	{
		if($prefix === null) {
			$prefix = $this->getConfig('sessionKeyPrefix');
		}

		// Return the session value if existing:
		if(isset($_SESSION[$prefix . $key])) {
			return $_SESSION[$prefix . $key];
		}


	}

	public function setSessionVar($key, $value, $prefix = null)
	{
		if($prefix === null) {
			$prefix = $this->getConfig('sessionKeyPrefix');
		}

		// Set the session value:
		$_SESSION[$prefix . $key] = $value;
	}

	public function getSessionIP()
	{
		return $this->getSessionVar('IP');
	}

	public function setSessionIP($ip)
	{
		$this->setSessionVar('IP', $ip);
	}

	public function getQueryUserName()
	{
		return $this->getSessionVar('QueryUserName');
	}

	public function setQueryUserName($userName)
	{
		$this->setSessionVar('QueryUserName', $userName);
	}

	public function getInvitations()
	{
		if($this->_invitations === null) {
			$this->_invitations = [];

			$sql = 'SELECT
						channel
					FROM
						' . $this->getDataBaseTable('invitations') . '
					WHERE
						userID=' . $this->db->makeSafe($this->getUserID()) . '
						AND
						DATE_SUB(NOW(), interval 1 DAY) < dateTime;';

			// Create a new SQL query:
			$result = $this->db->sqlQuery($sql);

			// Stop if an error occurs:
			if($result->error()) {
				echo $result->getError();

				die();
			}

			while($row = $result->fetch()) {
				array_push($this->_invitations, $row['channel']);
			}

			$result->free();
		}

		return $this->_invitations;
	}

	public function removeExpiredInvitations()
	{
		$sql = 'DELETE FROM
					' . $this->getDataBaseTable('invitations') . '
				WHERE
					DATE_SUB(NOW(), interval 1 DAY) > dateTime;';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}
	}

	public function addInvitation($userID, $channelID = null)
	{
		$this->removeExpiredInvitations();

		$channelID = ($channelID === null) ? $this->getChannel() : $channelID;

		$sql = 'INSERT INTO ' . $this->getDataBaseTable('invitations') . '(
					userID,
					channel,
					dateTime
				)
				VALUES (
					' . $this->db->makeSafe($userID) . ',
					' . $this->db->makeSafe($channelID) . ',
					NOW()
				);';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}
	}

	public function removeInvitation($userID, $channelID = null)
	{
		$channelID = ($channelID === null) ? $this->getChannel() : $channelID;

		$sql = 'DELETE FROM
					' . $this->getDataBaseTable('invitations') . '
				WHERE
					userID=' . $this->db->makeSafe($userID) . '
					AND
					channel=' . $this->db->makeSafe($channelID) . ';';

		// Create a new SQL query:
		$result = $this->db->sqlQuery($sql);

		// Stop if an error occurs:
		if($result->error()) {
			echo $result->getError();

			die();
		}
	}

	public function getUserID()
	{
		return $this->getSessionVar('UserID');
	}

	public function setUserID($id)
	{
		$this->setSessionVar('UserID', $id);
	}

	public function getUserName()
	{
		return $this->getSessionVar('UserName');
	}

	public function setUserName($name)
	{
		$this->setSessionVar('UserName', $name);
	}

	public function getLoginUserName()
	{
		return $this->getSessionVar('LoginUserName');
	}

	public function setLoginUserName($name)
	{
		$this->setSessionVar('LoginUserName', $name);
	}

	public function getUserRole()
	{
		$userRole = $this->getSessionVar('UserRole');

		if($userRole === null) {
			return AJAX_CHAT_GUEST;
		}

		return $userRole;
	}

	public function setUserRole($role)
	{
		$this->setSessionVar('UserRole', $role);
	}

	public function getChannel()
	{
		return $this->getSessionVar('Channel');
	}

	public function setChannel($channel)
	{
		$this->setSessionVar('Channel', $channel);

		// Save the channel enter timestamp:
		$this->setChannelEnterTimeStamp(time());

		// Reset the logs view socket authentication session var:
		if($this->getSessionVar('logsViewSocketAuthenticated')) {
			$this->setSessionVar('logsViewSocketAuthenticated', false);
		}
	}

	public function isLoggedIn()
	{
		return (bool) $this->getSessionVar('LoggedIn');
	}

	public function setLoggedIn($bool)
	{
		$this->setSessionVar('LoggedIn', $bool);
	}

	public function getLoginTimeStamp()
	{
		return $this->getSessionVar('LoginTimeStamp');
	}

	public function setLoginTimeStamp($time)
	{
		$this->setSessionVar('LoginTimeStamp', $time);
	}

	public function getChannelEnterTimeStamp()
	{
		return $this->getSessionVar('ChannelEnterTimeStamp');
	}

	public function setChannelEnterTimeStamp($time)
	{
		$this->setSessionVar('ChannelEnterTimeStamp', $time);
	}

	public function getStatusUpdateTimeStamp()
	{
		return $this->getSessionVar('StatusUpdateTimeStamp');
	}

	public function setStatusUpdateTimeStamp($time)
	{
		$this->setSessionVar('StatusUpdateTimeStamp', $time);
	}

	public function getInactiveCheckTimeStamp()
	{
		return $this->getSessionVar('InactiveCheckTimeStamp');
	}

	public function setInactiveCheckTimeStamp($time)
	{
		$this->setSessionVar('InactiveCheckTimeStamp', $time);
	}

	public function getInsertedMessagesRate()
	{
		return $this->getSessionVar('InsertedMessagesRate');
	}

	public function setInsertedMessagesRate($rate)
	{
		$this->setSessionVar('InsertedMessagesRate', $rate);
	}

	public function getInsertedMessagesRateTimeStamp()
	{
		return $this->getSessionVar('InsertedMessagesRateTimeStamp');
	}

	public function setInsertedMessagesRateTimeStamp($time)
	{
		$this->setSessionVar('InsertedMessagesRateTimeStamp', $time);
	}

	public function getLangCode()
	{
		// Get the langCode from request or cookie:
		$langCodeCookie = isset($_COOKIE[$this->getConfig('sessionName') . '_lang']) ? $_COOKIE[$this->getConfig('sessionName') . '_lang'] : null;
		$langCode = $this->getRequestVar('lang') ? $this->getRequestVar('lang') : $langCodeCookie;

		// Check if the langCode is valid:
		if(!in_array($langCode, $this->getConfig('langAvailable'))) {
			// Determine the user language:
			$language = new Language($this->getConfig('langAvailable'), $this->getConfig('langDefault'));
			$langCode = $language->getLangCode();
		}

		return $langCode;
	}

	public function setLangCodeCookie()
	{
		$path			= $this->getConfig('sessionCookiePath');
		$domain			= $this->getConfig('sessionCookieDomain');
		$secure_secure	= $this->getConfig('sessionCookieSecure');
		$samesite		= $this->getConfig('sessioncookieSamesite');
		$name 			= $this->getConfig('sessionName') . '_lang';
		$value 			= $this->getLangCode();

		$options = [
			'expires'	=> time() + 60 * 60 * 24 * $this->getConfig('sessionCookieLifeTime'),
			'path'		=> $path,
			'domain'	=> $domain,
			'secure'	=> $secure_secure,
			'samesite'	=> $samesite,
		];

		setcookie($name, $value, $options);
	}

	public function removeUnsafeCharacters($str)
	{
		// Remove NO-WS-CTL, non-whitespace control characters (RFC 2822), decimal 1–8, 11–12, 14–31, and 127:
		return Encoding::removeUnsafeCharacters($str);
	}

	public function subString($str, $start = 0, $length = null, $encoding = 'UTF-8')
	{
		return mb_substr($str, $start, $length, $encoding);
	}

	public function stringLength($str, $encoding = 'UTF-8')
	{
		return mb_strlen($str, $encoding);
	}

	public function trimMessageText($text)
	{
		return $this->trimString($text, 'UTF-8', $this->getConfig('messageTextMaxLength'));
	}

	public function trimUserName($userName)
	{
		return $this->trimString($userName, null, $this->getConfig('userNameMaxLength'), true, true);
	}

	public function trimChannelName($channelName)
	{
		return $this->trimString($channelName, null, null, true, true);
	}

	public function trimString($str, $sourceEncoding = null, $maxLength = null, $replaceWhitespace = false, $decodeEntities = false, $htmlEntitiesMap = null)
	{
		// Make sure the string contains valid unicode:
		$str = $this->convertToUnicode($str, $sourceEncoding);

		// Make sure the string contains no unsafe characters:
		$str = $this->removeUnsafeCharacters($str);

		// Strip whitespace from the beginning and end of the string:
		$str = trim($str);

		if($replaceWhitespace) {
			// Replace any whitespace in the userName with the underscore "_":
			$str = preg_replace('/\s/u', '_', $str);
		}

		if($decodeEntities) {
			// Decode entities:
			$str = $this->decodeEntities($str, 'UTF-8', $htmlEntitiesMap);
		}

		if($maxLength) {
			// Cut the string to the allowed length:
			$str = $this->subString($str, 0, $maxLength);
		}

		return $str;
	}

	public function convertToUnicode($str, $sourceEncoding = null)
	{
		if($sourceEncoding === null) {
			$sourceEncoding = $this->getConfig('sourceEncoding');
		}

		return $this->convertEncoding($str, $sourceEncoding, 'UTF-8');
	}

	public function convertFromUnicode($str, $contentEncoding = null)
	{
		if($contentEncoding === null) {
			$contentEncoding = $this->getConfig('contentEncoding');
		}

		return $this->convertEncoding($str, 'UTF-8', $contentEncoding);
	}

	public function convertEncoding($str, $charsetFrom, $charsetTo)
	{
		return Encoding::convertEncoding($str, $charsetFrom, $charsetTo);
	}

	public function encodeEntities($str, $encoding = 'UTF-8', $convmap = null)
	{
		return Encoding::encodeEntities($str, $encoding, $convmap);
	}

	public function decodeEntities($str, $encoding = 'UTF-8', $htmlEntitiesMap = null)
	{
		return Encoding::decodeEntities($str, $encoding, $htmlEntitiesMap);
	}

	public function htmlEncode($str)
	{
		return Encoding::htmlEncode($str, $this->getConfig('contentEncoding'));
	}

	public function encodeSpecialChars($str)
	{
		return Encoding::encodeSpecialChars($str);
	}

	public function decodeSpecialChars($str)
	{
		return Encoding::decodeSpecialChars($str);
	}

	public function ipToStorageFormat($ip)
	{
		if(function_exists('inet_pton')) {
			// ipv4 & ipv6:
			return @inet_pton($ip);
		}

		// Only ipv4:
		return @pack('N', @ip2long($ip));
	}

	public function ipFromStorageFormat($ip)
	{
		if(function_exists('inet_ntop')) {
			// ipv4 & ipv6:
			return @inet_ntop($ip);
		}
		// Only ipv4:
		$unpacked = @unpack('Nlong', $ip);

		if(isset($unpacked['long'])) {
			return @long2ip($unpacked['long']);
		}

	}

	public function getConfig($key, $subkey = null)
	{
		if($subkey) {
			return $this->_config[$key][$subkey];
		}

			return $this->_config[$key];
	}

	public function setConfig($key, $subkey, $value)
	{
		if($subkey) {
			if(!isset($this->_config[$key])) {
				$this->_config[$key] = [];
			}
			$this->_config[$key][$subkey] = $value;
		} else {
			$this->_config[$key] = $value;
		}
	}

	public function getLang($key = null)
	{
		if(!$this->_lang) {
			// Include the language file:
			$lang = null;

			require AJAX_CHAT_PATH . 'src/lang/' . $this->getLangCode() . '.php';
			$this->_lang = &$lang;
		}

		if($key === null) {
			return $this->_lang;
		}

		if(isset($this->_lang[$key])) {
			return $this->_lang[$key];
		}

	}

	public function getChatURL()
	{
		if(defined('AJAX_CHAT_URL')) {
			return AJAX_CHAT_URL;
		}

		return
			(isset($_SERVER['HTTPS']) ? 'https://' : 'http://') .
			(isset($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] . '@' : '') .
			($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] .
			(isset($_SERVER['HTTPS']) && $_SERVER['SERVER_PORT'] == 443 || $_SERVER['SERVER_PORT'] == 80 ? '' : ':' . $_SERVER['SERVER_PORT']))) .
			substr($_SERVER['SCRIPT_NAME'], 0, strrpos($_SERVER['SCRIPT_NAME'], '/') + 1);
	}

	public function getIDFromName($userName)
	{
		$userDataArray = $this->getOnlineUsersData(null, 'userName', $userName);

		if($userDataArray && isset($userDataArray[0])) {
			return $userDataArray[0]['userID'];
		}

	}

	public function getNameFromID($userID)
	{
		$userDataArray = $this->getOnlineUsersData(null, 'userID', $userID);

		if($userDataArray && isset($userDataArray[0])) {
			return $userDataArray[0]['userName'];
		}

	}

	public function getChannelFromID($userID)
	{
		$userDataArray = $this->getOnlineUsersData(null, 'userID', $userID);

		if($userDataArray && isset($userDataArray[0])) {
			return $userDataArray[0]['channel'];
		}

	}

	public function getIPFromID($userID)
	{
		$userDataArray = $this->getOnlineUsersData(null, 'userID', $userID);

		if($userDataArray && isset($userDataArray[0])) {
			return $userDataArray[0]['ip'];
		}

	}

	public function getRoleFromID($userID)
	{
		$userDataArray = $this->getOnlineUsersData(null, 'userID', $userID);

		if($userDataArray && isset($userDataArray[0])) {
			return $userDataArray[0]['userRole'];
		}

	}

	public function getChannelNames()
	{
		return array_flip($this->getChannels());
	}

	public function getChannelIDFromChannelName($channelName)
	{
		if(!$channelName) {
			return;
		}
		$channels = $this->getAllChannels();

		if(array_key_exists($channelName, $channels)) {
			return $channels[$channelName];
		}
		$channelID = null;

		// Check if the requested channel is the own private channel:
		if($channelName == $this->getPrivateChannelName()) {
			return $this->getPrivateChannelID();
		}
		// Try to retrieve a private room ID:
		$strlenChannelName = $this->stringLength($channelName);
		$strlenPrefix = $this->stringLength($this->getConfig('privateChannelPrefix'));
		$strlenSuffix = $this->stringLength($this->getConfig('privateChannelSuffix'));

		if($this->subString($channelName, 0, $strlenPrefix) == $this->getConfig('privateChannelPrefix')
			&& $this->subString($channelName, $strlenChannelName - $strlenSuffix) == $this->getConfig('privateChannelSuffix')) {
			$userName = $this->subString(
				$channelName,
				$strlenPrefix,
				$strlenChannelName - ($strlenPrefix + $strlenSuffix),
			);
			$userID = $this->getIDFromName($userName);

			if($userID !== null) {
				$channelID = $this->getPrivateChannelID($userID);
			}
		}

		return $channelID;
	}

	public function getChannelNameFromChannelID($channelID)
	{
		foreach($this->getAllChannels() as $key => $value) {
			if($value == $channelID) {
				return $key;
			}
		}

		// Try to retrieve a private room name:
		if($channelID == $this->getPrivateChannelID()) {
			return $this->getPrivateChannelName();
		}
		$userName = $this->getNameFromID($channelID - $this->getConfig('privateChannelDiff'));

		if($userName === null) {
			return;
		}

		return $this->getPrivateChannelName($userName);
	}

	public function getChannelName()
	{
		return $this->getChannelNameFromChannelID($this->getChannel());
	}

	public function getPrivateChannelName($userName = null)
	{
		if($userName === null) {
			$userName = $this->getUserName();
		}

		return $this->getConfig('privateChannelPrefix') . $userName . $this->getConfig('privateChannelSuffix');
	}

	public function getPrivateChannelID($userID = null)
	{
		if($userID === null) {
			$userID = $this->getUserID();
		}

		return $userID + $this->getConfig('privateChannelDiff');
	}

	public function getPrivateMessageID($userID = null)
	{
		if($userID === null) {
			$userID = $this->getUserID();
		}

		return $userID + $this->getConfig('privateMessageDiff');
	}

	public function isAllowedToSendPrivateMessage()
	{
		return (bool) ($this->getConfig('allowPrivateMessages') || $this->getUserRole() == AJAX_CHAT_ADMIN);
	}

	public function isAllowedToCreatePrivateChannel()
	{
		if($this->getConfig('allowPrivateChannels')) {
			switch($this->getUserRole()) {
				case AJAX_CHAT_USER:
					return true;

				case AJAX_CHAT_MODERATOR:
					return true;

				case AJAX_CHAT_ADMIN:
					return true;

				default:
					return false;
			}
		}

		return false;
	}

	public function isAllowedToListHiddenUsers()
	{
		// Hidden users are users within private or restricted channels:
		switch($this->getUserRole()) {
			case AJAX_CHAT_MODERATOR:
				return true;

			case AJAX_CHAT_ADMIN:
				return true;

			default:
				return false;
		}
	}

	public function isUserOnline($userID = null)
	{
		$userID = ($userID === null) ? $this->getUserID() : $userID;
		$userDataArray = $this->getOnlineUsersData(null, 'userID', $userID);

		return (bool) ($userDataArray && count($userDataArray) > 0);
	}

	public function isUserNameInUse($userName = null)
	{
		$userName = ($userName === null) ? $this->getUserName() : $userName;
		$userDataArray = $this->getOnlineUsersData(null, 'userName', $userName);

		return (bool) ($userDataArray && count($userDataArray) > 0);
	}

	public function isUserBanned($userName, $userID = null, $ip = null)
	{
		if($userID !== null) {
			$bannedUserDataArray = $this->getBannedUsersData('userID', $userID);

			if($bannedUserDataArray && isset($bannedUserDataArray[0])) {
				return true;
			}
		}

		if($ip !== null) {
			$bannedUserDataArray = $this->getBannedUsersData('ip', $ip);

			if($bannedUserDataArray && isset($bannedUserDataArray[0])) {
				return true;
			}
		}
		$bannedUserDataArray = $this->getBannedUsersData('userName', $userName);

		return (bool) ($bannedUserDataArray && isset($bannedUserDataArray[0]));
	}

	public function isMaxUsersLoggedIn()
	{
		return (bool) (count($this->getOnlineUsersData()) >= $this->getConfig('maxUsersLoggedIn'));
	}

	public function validateChannel($channelID)
	{
		if($channelID === null) {
			return false;
		}

		// Return true for normal channels the user has acces to:
		if(in_array($channelID, $this->getChannels())) {
			return true;
		}

		// Return true if the user is allowed to join his own private channel:
		if($channelID == $this->getPrivateChannelID() && $this->isAllowedToCreatePrivateChannel()) {
			return true;
		}

		// Return true if the user has been invited to a restricted or private channel:
		return (bool) (in_array($channelID, $this->getInvitations()));


		// No valid channel, return false:
	}

	public function createGuestUserName()
	{
		$maxLength =	$this->getConfig('userNameMaxLength')
						- $this->stringLength($this->getConfig('guestUserPrefix'))
						- $this->stringLength($this->getConfig('guestUserSuffix'));

		// seed with microseconds since last "whole" second:
		mt_srand();

		// Create a random userName using numbers between 100000 and 999999:
		$userName = substr(mt_rand(100000, 999999), 0, $maxLength);

		return $this->getConfig('guestUserPrefix') . $userName . $this->getConfig('guestUserSuffix');
	}

	// Guest userIDs must not interfere with existing userIDs and must be lower than privateChannelDiff:
	public function createGuestUserID()
	{
		// seed with microseconds since last "whole" second:
		mt_srand();

		return mt_rand($this->getConfig('minGuestUserID'), $this->getConfig('privateChannelDiff') - 1);
	}

	public function getGuestUser()
	{
		if(!$this->getConfig('allowGuestLogins')) {
			return;
		}

		if($this->getConfig('allowGuestUserName')) {
			$maxLength =	$this->getConfig('userNameMaxLength')
							- $this->stringLength($this->getConfig('guestUserPrefix'))
							- $this->stringLength($this->getConfig('guestUserSuffix'));

			// Trim guest userName:
			$userName = $this->trimString($this->getRequestVar('userName'), null, $maxLength, true, true);

			// If given userName is invalid, create one:
			if(!$userName) {
				$userName = $this->createGuestUserName();
			} else {
				// Add the guest users prefix and suffix to the given userName:
				$userName = $this->getConfig('guestUserPrefix') . $userName . $this->getConfig('guestUserSuffix');
			}
		} else {
			$userName = $this->createGuestUserName();
		}

		$userData = [];
		$userData['userID'] = $this->createGuestUserID();
		$userData['userName'] = $userName;
		$userData['userRole'] = AJAX_CHAT_GUEST;

		return $userData;
	}

	public function getCustomVar($key)
	{
		if(!isset($this->_customVars)) {
			$this->_customVars = [];
		}

		if(!isset($this->_customVars[$key])) {
			return;
		}

		return $this->_customVars[$key];
	}

	public function setCustomVar($key, $value)
	{
		if(!isset($this->_customVars)) {
			$this->_customVars = [];
		}
		$this->_customVars[$key] = $value;
	}

	// Override to replace custom template tags:
	// Return the replacement for the given tag (and given tagContent)
	public function replaceCustomTemplateTags($tag, $tagContent)
	{

	}

	// Override to initialize custom configuration settings:
	public function initCustomConfig()
	{
	}

	// Override to add custom request variables:
	// Add values to the request variables array: $this->_requestVars['customVariable'] = null;
	public function initCustomRequestVars()
	{
	}

	// Override to add custom session code right after the session has been started:
	public function initCustomSession()
	{
	}

	// Override, to parse custom info requests:
	// $infoRequest contains the current info request
	// Add info responses using the method addInfoMessage($info, $type)
	public function parseCustomInfoRequest($infoRequest)
	{
	}

	// Override to replace custom text:
	// Return replaced text
	// $text contains the whole message
	public function replaceCustomText(&$text)
	{
		return $text;
	}

	// Override to add custom commands:
	// Return true if a custom command has been successfully parsed, else false
	// $text contains the whole message, $textParts the message split up as words array
	public function parseCustomCommands($text, $textParts)
	{
		return false;
	}

	// Override to perform custom actions on new messages:
	// Return true if message may be inserted, else false
	// $text contains the whole message
	public function onNewMessage($text)
	{
		return true;
	}

	// Override to perform custom actions on new messages:
	// Method to set the style cookie depending on user data
	public function setStyle()
	{
	}

	// Override:
	// Returns true if the userID of the logged in user is identical to the userID of the authentication system
	// or the user is authenticated as guest in the chat and the authentication system
	public function revalidateUserID()
	{
		return true;
	}

	// Override:
	// Returns an associative array containing userName, userID and userRole
	// Returns null if login is invalid
	public function getValidLoginUserData()
	{
		// Check if we have a valid registered user:
		if(false) {
			// Here is the place to check user authentication
		} else {
			// Guest users:
			return $this->getGuestUser();
		}
	}

	// Override:
	// Store the channels the current user has access to
	// Make sure channel names don't contain any whitespace
	public function getChannels()
	{
		if($this->_channels === null) {
			$this->_channels = $this->getAllChannels();
		}

		return $this->_channels;
	}

	// Override:
	// Store all existing channels
	// Make sure channel names don't contain any whitespace
	public function getAllChannels()
	{
		if($this->_allChannels === null) {
			$this->_allChannels = [];

			// Default channel, public to everyone:
			$this->_allChannels[$this->trimChannelName($this->getConfig('defaultChannelName'))] = $this->getConfig('defaultChannelID');
		}

		return $this->_allChannels;
	}
}
