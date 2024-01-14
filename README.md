Blueimp's AJAX Chat
====================

forked from Frug/AJAX-Chat [Branch: psr-4](https://github.com/Frug/AJAX-Chat/tree/psr-4)

==========================================================================================

INFO
----
Since Frug has not been active for months, I have created this repository. Should he become active again,
I will transfer changes from here to his repository.
The psr-4 branch is version 0.9.0 according to the change log.
I will not assign a new version number so that if Frug becomes active again, there will be no confusion.

There will only be changes for the standalone version. **Integration into existing authentication systems is not discussed here**.


Optional
--------
If you do not want to use Composer, you can use the autoloader from the bootstrap directory. This autoloader is equivalent to PSR-4.

Open public/index.php and public/install.php and change:

with Composer:
````
// Include Class libraries:
// if you don't want to use Composer then commented without // the autoloader to vendor and use the bootstrap to bootstrap

// with Composer
require(AJAX_CHAT_PATH.'vendor/autoload.php');

// without Composer. Autoloader is equivalent to PSR-4
// require(AJAX_CHAT_PATH.'bootstrap/autoload.php');
````
**without** Composer:
````
// Include Class libraries:
// if you don't want to use Composer then commented without // the autoloader to vendor and use the bootstrap to bootstrap

// with Composer
// require(AJAX_CHAT_PATH.'vendor/autoload.php');

// without Composer. Autoloader is equivalent to PSR-4
require(AJAX_CHAT_PATH.'bootstrap/autoload.php');
````

.htacces
--------
for root directory
(you may have to add RewriteBase depending on the server configuration)
````
<IfModule mod_rewrite.c>
	RewriteEngine On
	#
	# Uncomment the statement below if URL rewriting doesn't
	# work properly. If you installed AjaxChat in a subdirectory
	# of your site, properly set the argument for the statement.
	# e.g.: if your domain is test.com and you installed AjaxChat
	# in http://www.test.com/chat/index.php you have to set
	# the statement RewriteBase /chat/
	#
	#RewriteBase /
	RewriteCond %{REQUEST_URI} !^public
	RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
````
for public directory
````
<IfModule mod_rewrite.c>
	<IfModule mod_negotiation.c>
		Options -MultiViews -Indexes
	</IfModule>

	RewriteEngine On

	# Handle Authorization Header
	RewriteCond %{HTTP:Authorization} .
	RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

	# Redirect Trailing Slashes If Not A Folder...
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_URI} (.+)/$
	RewriteRule ^ %1 [L,R=301]

	# Send Requests To Front Controller...
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteRule ^ index.php [L]
</IfModule>
````

==========================================================================================

AJAX stands for "Asynchronous JavaScript and XML".
The AJAX Chat clients (the user browsers) use JavaScript to query the web server for updates.
Instead of delivering a complete HTML page only updated data is sent in XML format.

By using JavaScript the chat page can be updated without having to reload the whole page.

Requirements
------------

| *Server-Side*          | *Client-Side*                |
| ---------------------- | ---------------------------- |
| PHP >= 7*              | Enabled JavaScript           |
| MySQL >= 4             | Enabled Cookies              |
| Ruby >= 1.8 (optional) | Flash Plugin >= 9 (optional) |

\* For PHP 5 support use a release older than `0.9`

Features
--------
- Easy installation
- Usable as shoutbox
- Multiple channels
- Private messaging
- Private channels
- Invitation system
- Kick/Ban or Ignore offending Users
- Online users list with user menu
- Emoticons/Smilies
- Easy way to add custom emoticons
- BBCode support
- Optional Flash based sound support
- Optional visual update information (changing window title)
- Clickable Hyperlinks
- Splitting of long words to preserve chat layout
- Flood control
- Possibility to delete messages inside the chat
- IRC style commands
- Easy interface to add custom commands
- Possibility to define opening hours for the chat
- Possibility to enable/disable guest users
- Persistent client-side settings
- Multiple languages (auto-detection of ACCEPT_LANGUAGE browser setting)
- Multiple styles with easy layout customization through stylesheets (CSS) and templates
- Automatic adjustment of displayed time to local client timezone
- Standards compliance (XHTML 1.0 strict)
- Accepts any text input, including code and special characters
- Multiline input field with the possibility to enter line breaks
- Message length counter
- Realtime monitoring and logs viewer
- Support for unicode (UTF-8) and non-unicode content types
- Bandwidth saving update calls (only updated data is sent)
- Optional support to push updates over a Flash based socket connection (increased performance and responsiveness)
- Survives connection timeouts
- Easy integration into existing authentication systems
- ~~(Sample phpBB3, MyBB, PunBB, SMF and vBulletin integrations available)~~ **not used in this repository**
- Separation of layout and code
- Well commented Source Code
- Developed with Security as integral part - built to prevent Code injections, SQL injections, Cross-site scripting (XSS), Session stealing and other attacks

Help
----
Essential documentation is contained in the attached readme files

For more documentation consult the github wiki: https://github.com/Frug/AJAX-Chat/wiki

For support questions use google groups: https://groups.google.com/forum/#!forum/ajax-chat

To report bugs use github issues: https://github.com/Frug/AJAX-Chat

Planned changes in this repository
----------------------------------

- Fixed: Issue [Typing errors mo3](https://github.com/Frug/AJAX-Chat/issues/293)
- Fixed: Deprecated elements in Javascript
- Fixed: Chat Logs (?view=logs), new Layout, fixed Logout, added Button return to chat
- New: Optional use Composer
- New: Cookie [Samesite](http://www.sjoerdlangkemper.nl/2016/04/14/preventing-csrf-with-samesite-cookie-attribute/)
    - new setting in src/config.php "$config['sessioncookieSamesite']". Default set "Lax"
    - new setting in public/js/config.js "cookieSamesite". Default set "Lax"
- New: Set Cookies after login and delete Cookies after logout
- New: Language strings:
    - public/js/lang/*.js  "userMenuLogsview: 'Switch to the chat protocol',"
    - src/lang/*.php       "$lang['returnToChat'] = 'Return to chat';"
    - Translators of your language **have yet to add this**. In english and german it is inserted

<sub>(* your language)</sup>
