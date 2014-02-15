<?php
/**
 * @copyright  Copyright (C) 2012 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaDistro\CmsInstallerApplication\Language;

use Joomla\Language\Language as JLanguage;

/**
 * Language class
 *
 * @since  1.0
 */
class Language extends JLanguage
{
	public static function exists($lang, $basePath = __DIR__)
	{
		return parent::exists($lang, $basePath);
	}

	public function load($extension = 'joomla', $basePath = __DIR__, $lang = null, $reload = false, $default = true)
	{
		return parent::load($extension, $basePath, $lang, $reload, $default);
	}

	public static function getKnownLanguages($basePath = __DIR__)
	{
		return parent::getKnownLanguages($basePath);
	}

	public static function getLanguagePath($basePath = __DIR__, $language = null)
	{
		return parent::getLanguagePath($basePath, $language);
	}

	/**
	 * Returns a associative array holding the metadata.
	 *
	 * @param   string  $lang  The name of the language.
	 *
	 * @return  mixed  If $lang exists return key/value pair with the language metadata, otherwise return NULL.
	 *
	 * @since   1.0
	 */
	public static function getMetadata($lang)
	{
		$path = self::getLanguagePath(__DIR__, $lang);
		$file = $lang . '.xml';

		$result = null;

		if (is_file("$path/$file"))
		{
			$result = self::parseXMLLanguageFile("$path/$file");
		}

		if (empty($result))
		{
			return null;
		}

		return $result;
	}
}
