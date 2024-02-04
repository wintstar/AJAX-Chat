<?php

namespace AjaxChat;

/*
 * @package AJAX_Chat
 * @author Sebastian Tschan
 * @copyright (c) Sebastian Tschan
 * @license Modified MIT License
 * @link https://blueimp.net/ajax/
 */

// Class to provide static encoding methods
class Encoding
{
	public static $specialChars = ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;', "'" => '&#39;', '"' => '&quot;'];

	// Helper function to store Regular expression for NO-WS-CTL as we cannot use static class members in PHP4:
	public static function getRegExp_NO_WS_CTL()
	{
		static $regExp_NO_WS_CTL;
		if (!$regExp_NO_WS_CTL) {
			// Regular expression for NO-WS-CTL, non-whitespace control characters (RFC 2822), decimal 1–8, 11–12, 14–31, and 127:
			$regExp_NO_WS_CTL = '/[\x0\x1\x2\x3\x4\x5\x6\x7\x8\xB\xC\xE\xF\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F]/';
		}
		return $regExp_NO_WS_CTL;
	}

	public static function convertEncoding($str, $charsetFrom, $charsetTo)
	{
		if (function_exists('mb_convert_encoding')) {
			return mb_convert_encoding($str, $charsetTo, $charsetFrom);
		}
		if (function_exists('iconv')) {
			return iconv($charsetFrom, $charsetTo, $str);
		}
		if (($charsetFrom == 'UTF-8') && ($charsetTo == 'ISO-8859-1')) {
			return utf8_decode($str);
		}
		if (($charsetFrom == 'ISO-8859-1') && ($charsetTo == 'UTF-8')) {
			return utf8_encode($str);
		}
		return $str;
	}

	public static function htmlEncode($str, $contentCharset = 'UTF-8')
	{
		switch ($contentCharset) {
			case 'UTF-8':
				// Encode only special chars (&, <, >, ', ") as entities:
				return Encoding::encodeSpecialChars($str);
			case 'ISO-8859-1':
			case 'ISO-8859-15':
				// Encode special chars and all extended characters above ISO-8859-1 charset as entities, then convert to content charset:
				return Encoding::convertEncoding(Encoding::encodeEntities($str, 'UTF-8', array(
					0x26, 0x26, 0, 0xFFFF,	// &
					0x3C, 0x3C, 0, 0xFFFF,	// <
					0x3E, 0x3E, 0, 0xFFFF,	// >
					0x27, 0x27, 0, 0xFFFF,	// '
					0x22, 0x22, 0, 0xFFFF,	// "
					0x100, 0x2FFFF, 0, 0xFFFF	// above ISO-8859-1
				)), 'UTF-8', $contentCharset);
			default:
				// Encode special chars and all characters above ASCII charset as entities, then convert to content charset:
				return Encoding::convertEncoding(Encoding::encodeEntities($str, 'UTF-8', array(
					0x26, 0x26, 0, 0xFFFF,	// &
					0x3C, 0x3C, 0, 0xFFFF,	// <
					0x3E, 0x3E, 0, 0xFFFF,	// >
					0x27, 0x27, 0, 0xFFFF,	// '
					0x22, 0x22, 0, 0xFFFF,	// "
					0x80, 0x2FFFF, 0, 0xFFFF	// above ASCII
				)), 'UTF-8', $contentCharset);
		}
	}

	public static function encodeSpecialChars($str)
	{
		if (!empty($str)) {
			$return = strtr($str, self::$specialChars);
		} else {
			$return = '';
		}

		return $return;
	}

	public static function decodeSpecialChars($str)
	{
		if (!empty($str)) {
			$return = strtr($str, array_flip(self::$specialChars));
		} else {
			$return = '';
		}

		return $return;
	}

	public static function encodeEntities($str, $encoding = 'UTF-8', $convmap = null)
	{
		if ($convmap && function_exists('mb_encode_numericentity')) {
			return mb_encode_numericentity($str, $convmap, $encoding);
		}
		return htmlentities($str, ENT_QUOTES, $encoding);
	}

	public static function decodeEntities($str, $encoding = 'UTF-8', $htmlEntitiesMap = null)
	{
		// Replace numeric and literal entities:
		$str = html_entity_decode($str, ENT_QUOTES, $encoding);

		// Replace additional literal HTML entities if an HTML entities map is given:
		if ($htmlEntitiesMap) {
			$str = strtr($str, $htmlEntitiesMap);
		}
		return $str;
	}

	public static function removeUnsafeCharacters($str)
	{
		// Remove NO-WS-CTL, non-whitespace control characters (RFC 2822), decimal 1–8, 11–12, 14–31, and 127:
		return preg_replace(Encoding::getRegExp_NO_WS_CTL(), '', $str);
	}
}
