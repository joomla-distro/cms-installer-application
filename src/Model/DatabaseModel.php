<?php
/**
 *
 * @copyright  Copyright (C) 2012 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace JoomlaDistro\CmsInstallerApplication\Model;

use JoomlaDistro\CmsInstallerApplication\Model\DefaultModel;
use Joomla\Utilities\ArrayHelper as JArrayHelper;
use Joomla\Filesystem\Folder as JFolder;
use Joomla\Filesystem\File as JFile;
use Joomla\Language\Language as JLanguage;
use Joomla\Language\Text as JText;

/**
 * Database model for the tracker application.
 *
 * @since  1.0
 */
class DatabaseModel extends DefaultModel
{
	/**
	 * Method to initialise the database
	 *
	 * @param   array  $options  The options to use for configuration
	 *
	 * @return  boolean  True on success
	 *
	 * @since   3.1
	 */
	public function initialise($options)
	{
		// Get the application
		/* @var InstallationApplicationWeb $app */
		$app = $this->application;

		// Get the options as a object for easier handling.
		$options = JArrayHelper::toObject($options);

		// Load the back-end language files so that the DB error messages work
		$lang = $app->getLanguage();
		$currentLang = $lang->getTag();

		// Load the selected language
		if (defined('JPATH_APP_ADMINISTRATOR')) {
			if (JLanguage::exists($currentLang, JPATH_APP_ADMINISTRATOR))
			{
				$lang->load('joomla', JPATH_APP_ADMINISTRATOR, $currentLang, true);
			}
			// Pre-load en-GB in case the chosen language files do not exist
			else
			{
				$lang->load('joomla', JPATH_APP_ADMINISTRATOR, 'en-GB', true);
			}
		}
		
		// Ensure a database type was selected.
		if (empty($options->db_type))
		{
			$app->enqueueMessage(JText::_('INSTL_DATABASE_INVALID_TYPE'), 'notice');
			return false;
		}

		// Ensure that a hostname and user name were input.
		if (empty($options->db_host) || empty($options->db_user))
		{
			$app->enqueueMessage(JText::_('INSTL_DATABASE_INVALID_DB_DETAILS'), 'notice');
			return false;
		}

		// Ensure that a database name was input.
		if (empty($options->db_name))
		{
			$app->enqueueMessage(JText::_('INSTL_DATABASE_EMPTY_NAME'), 'notice');
			return false;
		}

		// Validate database table prefix.
		if (!preg_match('#^[a-zA-Z]+[a-zA-Z0-9_]*$#', $options->db_prefix))
		{
			$app->enqueueMessage(JText::_('INSTL_DATABASE_PREFIX_INVALID_CHARS'), 'notice');
			return false;
		}

		// Validate length of database table prefix.
		if (strlen($options->db_prefix) > 15)
		{
			$app->enqueueMessage(JText::_('INSTL_DATABASE_FIX_TOO_LONG'), 'notice');
			return false;
		}

		// Validate length of database name.
		if (strlen($options->db_name) > 64)
		{
			$app->enqueueMessage(JText::_('INSTL_DATABASE_NAME_TOO_LONG'), 'notice');
			return false;
		}

		// Get a database object.
		try
		{
			return \JoomlaDistro\CmsInstallerApplication\Helper\DatabaseHelper::getDbo(
				$options->db_type, $options->db_host, $options->db_user, $options->db_pass, $options->db_name, $options->db_prefix, $options->db_select
			);
		}
		catch (\RuntimeException $e)
		{
			$app->enqueueMessage(JText::sprintf('INSTL_DATABASE_COULD_NOT_CONNECT', $e->getMessage()), 'notice');
			return false;
		}
	}

	/**
	 * Method to create a new database.
	 *
	 * @param   array  $options  The configuration options
	 *
	 * @return    boolean    True on success.
	 *
	 * @since    3.1
	 */
	public function createDatabase($options)
	{
		// Get the application
		/* @var InstallationApplicationWeb $app */
		$app = $this->application;

		// Disable autoselect database before it's created
		$tmpSelect = true;

		if (isset($options['db_select']))
		{
			$tmpSelect = $options['db_select'];
		}
		$options['db_select'] = false;

		if (!$db = $this->initialise($options))
		{
            die('erro no initialise');
			return false;
		}

		// Get the options as a object for easier handling.
		$options = JArrayHelper::toObject($options);

		// Check database version.
		$type = strtolower($options->db_type);

		try
		{
			$db_version = $db->getVersion();
		}
		catch (\RuntimeException $e)
		{
			$app->enqueueMessage(JText::sprintf('INSTL_DATABASE_COULD_NOT_CONNECT', $e->getMessage()), 'notice');
			return false;
		}

		if (!$db->isMinimumVersion())
		{
			$app->enqueueMessage(JText::sprintf('INSTL_DATABASE_INVALID_' . strtoupper($type) . '_VERSION', $db_version), 'notice');
			return false;
		}

		if (($type == 'mysql') || ($type == 'mysqli'))
		{
			// @internal MySQL versions pre 5.1.6 forbid . / or \ or NULL
			if ((preg_match('#[\\\/\.\0]#', $options->db_name)) && (!version_compare($db_version, '5.1.6', '>=')))
			{
				$app->enqueueMessage(JText::sprintf('INSTL_DATABASE_INVALID_NAME', $db_version), 'notice');
				return false;
			}
		}

		// @internal Check for spaces in beginning or end of name
		if (strlen(trim($options->db_name)) <> strlen($options->db_name))
		{
			$app->enqueueMessage(JText::_('INSTL_DATABASE_NAME_INVALID_SPACES'), 'notice');
			return false;
		}

		// @internal Check for asc(00) Null in name
		if (strpos($options->db_name, chr(00)) !== false)
		{
			$app->enqueueMessage(JText::_('INSTL_DATABASE_NAME_INVALID_CHAR'), 'notice');
			return false;
		}

		// PostgreSQL database older than version 9.0.0 needs to run 'CREATE LANGUAGE' to create function.
		if (($options->db_type == 'postgresql') && (version_compare($db_version, '9.0.0', '<')))
		{
			$db->setQuery("CREATE LANGUAGE plpgsql");
			$db->execute();
		}

		// Get database's UTF support
		$utfSupport = $db->hasUTFSupport();

		// Try to select the database
		try
		{
			$db->select($options->db_name);

		}
		catch (\RuntimeException $e)
		{
			// If the database could not be selected, attempt to create it and then select it.
			if ($this->createDB($db, $options, $utfSupport))
			{
				$db->select($options->db_name);
			}
			else
			{
				$app->enqueueMessage(JText::sprintf('INSTL_DATABASE_ERROR_CREATE', $options->db_name), 'notice');
				return false;
			}
		}

		$options = (array) $options;

		// Remove *_errors value
		foreach ($options as $i => $option)
		{
			if (isset($i['1']) && $i['1'] == '*')
			{
				unset($options[$i]);
				break;
			}
		}
		$options = array_merge(array('db_created' => 1), $options);

		// Restore autoselect value after database creation
		$options['db_select'] = $tmpSelect;

		$session = $this->application->getSession();
		$session->set('setup.options', $options);

		return true;
	}

	public function handleOldDatabase($options)
	{
		if (!isset($options['db_created']) || !$options['db_created'])
		{
			return $this->createDatabase($options);
		}

		if (!$db = $this->initialise($options))
		{
			return false;
		}

		// Get the options as a object for easier handling.
		$options = JArrayHelper::toObject($options);

		// Set the character set to UTF-8 for pre-existing databases.
		$this->setDatabaseCharset($db, $options->db_name);

		// Should any old database tables be removed or backed up?
		if ($options->db_old == 'remove')
		{
			// Attempt to delete the old database tables.
			if (!$this->deleteDatabase($db, $options->db_prefix))
			{
				// Message queued by method, simply return
				return false;
			}
		}
		else
		{
			// If the database isn't being deleted, back it up.
			if (!$this->backupDatabase($db, $options->db_prefix))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Method to create the database tables
	 *
	 * @param   array  $options  The options array
	 *
	 * @return  boolean  True on success
	 *
	 * @since   3.1
	 */
	public function createTables($options)
	{
		// Get the application
		/* @var InstallationApplicationWeb $app */
		$app = $this->application;

		if (!isset($options['db_created']) || !$options['db_created'])
		{
			return $this->createDatabase($options);
		}

		if (!$db = $this->initialise($options))
		{
			return false;
		}

		// Get the options as a object for easier handling.
		$options = JArrayHelper::toObject($options);

		// Check database type.
		$type = strtolower($options->db_type);

		// Set the character set to UTF-8 for pre-existing databases.
		$this->setDatabaseCharset($db, $options->db_name);

		// Set the appropriate schema script based on UTF-8 support.
		if ($type == 'mysqli' || $type == 'mysql')
		{
			$schema = 'sql/mysql/joomla.sql';
		}
		elseif ($type == 'sqlsrv' || $type == 'sqlazure')
		{
			$schema = 'sql/sqlazure/joomla.sql';
		}
		else
		{
			$schema = 'sql/' . $type . '/joomla.sql';
		}

		$schema = JPATH_ROOT .'/'. $schema;

		// Check if the schema is a valid file
		if (!is_file($schema))
		{
			$app->enqueueMessage(JText::sprintf('INSTL_ERROR_DB', JText::_('INSTL_DATABASE_NO_SCHEMA')), 'notice');
			return false;
		}

		// Attempt to import the database schema.
		if (!$this->populateDatabase($db, $schema))
		{
			return false;
		}

		// Attempt to update the table #__schema.
		$pathPart = JPATH_ROOT . '/etc/sql/updates/';

		if ($type == 'mysqli' || $type == 'mysql')
		{
			$pathPart .= 'mysql/';
		}
		elseif ($type == 'sqlsrv' || $type == 'sqlazure')
		{
			$pathPart .= 'sqlazure/';
		}
		else
		{
			$pathPart .= $type . '/';
		}
		$files = JFolder::files($pathPart, '\.sql$');

		if (empty($files))
		{
			$app->enqueueMessage(JText::_('INSTL_ERROR_INITIALISE_SCHEMA'), 'notice');
			return false;
		}
		$version = '';

		foreach ($files as $file)
		{
			if (version_compare($version, JFile::stripExt($file)) < 0)
			{
				$version = JFile::stripExt($file);
			}
		}
		$query = $db->getQuery(true)
			->insert($db->quoteName('#__schemas'))
			->columns(
				array(
					$db->quoteName('extension_id'),
					$db->quoteName('version_id')
				)
			)
			->values('700, ' . $db->quote($version));
		$db->setQuery($query);

		try
		{
			$db->execute();
		}
		catch (\RuntimeException $e)
		{
			$app->enqueueMessage($e->getMessage(), 'notice');
			return false;
		}

		// Attempt to refresh manifest caches
		$query->clear()
			->select('*')
			->from('#__extensions');
		$db->setQuery($query);

		$return = true;

		try
		{
			$extensions = $db->loadObjectList();
		}
		catch (\RuntimeException $e)
		{
			$app->enqueueMessage($e->getMessage(), 'notice');
			$return = false;
		}

        /*
		$installer = JInstaller::getInstance();

		foreach ($extensions as $extension)
		{
			if (!$installer->refreshManifestCache($extension->extension_id))
			{
				$app->enqueueMessage(JText::sprintf('INSTL_DATABASE_COULD_NOT_REFRESH_MANIFEST_CACHE', $extension->name), 'notice');
				return false;
			}
		}
        */

		// Load the localise.sql for translating the data in joomla.sql
		if ($type == 'mysqli' || $type == 'mysql')
		{
			$dblocalise = 'sql/mysql/localise.sql';
		}
		elseif ($type == 'sqlsrv' || $type == 'sqlazure')
		{
			$dblocalise = 'sql/sqlazure/localise.sql';
		}
		else
		{
			$dblocalise = 'sql/' . $type . '/localise.sql';
		}

		if (is_file($dblocalise))
		{
			if (!$this->populateDatabase($db, $dblocalise))
			{
				return false;
			}
		}

		// Handle default backend language setting. This feature is available for localized versions of Joomla.
		$languages = $app->getLocaliseAdmin($db);
		if (in_array($options->language, $languages['admin']) || in_array($options->language, $languages['site']))
		{
			// Build the language parameters for the language manager.
			$params = array();

			// Set default administrator/site language to sample data values:
			$params['administrator'] = 'en-GB';
			$params['site'] = 'en-GB';

			if (in_array($options->language, $languages['admin']))
			{
				$params['administrator'] = $options->language;
			}

			if (in_array($options->language, $languages['site']))
			{
				$params['site'] = $options->language;
			}
			$params = json_encode($params);

			// Update the language settings in the language manager.
			$query->clear()
				->update($db->quoteName('#__extensions'))
				->set($db->quoteName('params') . ' = ' . $db->quote($params))
				->where($db->quoteName('element') . ' = ' . $db->quote('com_languages'));
			$db->setQuery($query);

			try
			{
				$db->execute();
			}
			catch (\RuntimeException $e)
			{
				$app->enqueueMessage($e->getMessage(), 'notice');
				$return = false;
			}
		}

		return $return;
	}

	/**
	 * Method to install the sample data
	 *
	 * @param   array  $options  The options array
	 *
	 * @return  boolean  True on success
	 *
	 * @since   3.1
	 */
	public function installSampleData($options)
	{
		// Get the application
		/* @var InstallationApplicationWeb $app */
		$app = $this->application;

		if (!isset($options['db_created']) || !$options['db_created'])
		{
			return $this->createDatabase($options);
		}

		if (!$db = $this->initialise($options))
		{
			return false;
		}

		// Get the options as a object for easier handling.
		$options = JArrayHelper::toObject($options);

		// Build the path to the sample data file.
		$type = strtolower($options->db_type);

		if ($type == 'mysqli')
		{
			$type = 'mysql';
		}
		elseif ($type == 'sqlsrv')
		{
			$type = 'sqlazure';
		}

		$data = JPATH_ROOT . '/etc/sql/' . $type . '/' . $options->sample_file;

		// Attempt to import the database schema if one is chosen.
		if ($options->sample_file != '')
		{
			if (!file_exists($data))
			{
				$app->enqueueMessage(JText::sprintf('INSTL_DATABASE_FILE_DOES_NOT_EXIST', $data), 'notice');
				return false;
			}
			elseif (!$this->populateDatabase($db, $data))
			{
				return false;
			}

			$this->postInstallSampleData($db);
		}

		return true;
	}

	/**
	 * Method to update the user id of the sample data content to the new rand user id
	 *
	 * @param   JDatabaseDriver  $db  Database connector object $db*
	 *
	 * @return  void
	 *
	 * @since   3.1
	 */
	protected function postInstallSampleData($db)
	{
		// Create the ID for the root user
		$userId = self::getUserId();

		// Update all created_by field of the tables with the random user id
		// categories (created_user_id), contact_details, content, newsfeeds, weblinks
		$updates_array = array(
			'categories' => 'created_user_id',
			'contact_details' => 'created_by',
			'content' => 'created_by',
			'newsfeeds' => 'created_by',
			'tags' => 'created_user_id',
			'ucm_content' => 'core_created_user_id',
			'ucm_history' => 'editor_user_id',
			'weblinks' => 'created_by'
		);

		foreach ($updates_array as $table => $field)
		{
			$db->setQuery(
				'UPDATE ' . $db->quoteName('#__' . $table) .
					' SET ' . $db->quoteName($field) . ' = ' . $db->quote($userId)
			);
			$db->execute();
		}
	}

	/**
	 * Method to backup all tables in a database with a given prefix.
	 *
	 * @param   JDatabaseDriver  $db      JDatabaseDriver object.
	 * @param   string           $prefix  Database table prefix.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since    3.1
	 */
	public function backupDatabase($db, $prefix)
	{
		$return = true;
		$backup = 'bak_' . $prefix;

		// Get the tables in the database.
		$tables = $db->getTableList();

		if ($tables)
		{
			foreach ($tables as $table)
			{
				// If the table uses the given prefix, back it up.
				if (strpos($table, $prefix) === 0)
				{
					// Backup table name.
					$backupTable = str_replace($prefix, $backup, $table);

					// Drop the backup table.
					try
					{
						$db->dropTable($backupTable, true);
					}
					catch (\RuntimeException $e)
					{
						$this->application->enqueueMessage(JText::sprintf('INSTL_DATABASE_ERROR_BACKINGUP', $e->getMessage()), 'notice');
						$return = false;
					}

					// Rename the current table to the backup table.
					try
					{
						$db->renameTable($table, $backupTable, $backup, $prefix);
					}
					catch (\RuntimeException $e)
					{
						$this->application->enqueueMessage(JText::sprintf('INSTL_DATABASE_ERROR_BACKINGUP', $e->getMessage()), 'notice');
						$return = false;
					}
				}
			}
		}

		return $return;
	}

	/**
	 * Method to create a new database.
	 *
	 * @param   JDatabaseDriver  $db       JDatabase object.
	 * @param   JObject          $options  JObject coming from "initialise" function to pass user
	 *                                     and database name to database driver.
	 * @param   boolean          $utf      True if the database supports the UTF-8 character set.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   3.1
	 */
	public function createDB($db, $options, $utf)
	{
		// Build the create database query.
		try
		{
            $utf_string = $utf ? ' CHARACTER SET `utf8`' : '';
			// Run the create database query.
			$db->setQuery('CREATE DATABASE '.$options->db_name.$utf_string);
            $db->execute();
		}
		catch (\RuntimeException $e)
		{
			// If an error occurred return false.
			return false;
		}

		return true;
	}

	/**
	 * Method to delete all tables in a database with a given prefix.
	 *
	 * @param   JDatabaseDriver  $db      JDatabaseDriver object.
	 * @param   string           $prefix  Database table prefix.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   3.1
	 */
	public function deleteDatabase($db, $prefix)
	{
		$return = true;

		// Get the tables in the database.
		$tables = $db->getTableList();

		if ($tables)
		{
			foreach ($tables as $table)
			{
				// If the table uses the given prefix, drop it.
				if (strpos($table, $prefix) === 0)
				{
					// Drop the table.
					try
					{
						$db->dropTable($table);
					}
					catch (\RuntimeException $e)
					{
						$this->application->enqueueMessage(JText::sprintf('INSTL_DATABASE_ERROR_DELETE', $e->getMessage()), 'notice');
						$return = false;
					}
				}
			}
		}

		return $return;
	}

	/**
	 * Method to import a database schema from a file.
	 *
	 * @param   JDatabaseDriver  $db      JDatabase object.
	 * @param   string           $schema  Path to the schema file.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   3.1
	 */
	public function populateDatabase($db, $schema)
	{
		// Get the application
		/* @var InstallationApplicationWeb $app */
		$app = $this->application;

		$return = true;

		// Get the contents of the schema file.
		if (!($buffer = file_get_contents($schema)))
		{
			$app->enqueueMessage($db->getErrorMsg(), 'notice');
			return false;
		}

		// Get an array of queries from the schema and process them.
		$queries = $this->_splitQueries($buffer);

		foreach ($queries as $query)
		{
			// Trim any whitespace.
			$query = trim($query);

			// If the query isn't empty and is not a MySQL or PostgreSQL comment, execute it.
			if (!empty($query) && ($query{0} != '#') && ($query{0} != '-'))
			{
				// Execute the query.
				$db->setQuery($query);

				try
				{
					$db->execute();
				}
				catch (\RuntimeException $e)
				{
					$app->enqueueMessage($e->getMessage(), 'notice');
					$return = false;
				}
			}
		}

		return $return;
	}

	/**
	 * Method to set the database character set to UTF-8.
	 *
	 * @param   JDatabaseDriver  $db    JDatabaseDriver object.
	 * @param   string           $name  Name of the database to process.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   3.1
	 */
	public function setDatabaseCharset($db, $name)
	{
		// Run the create database query.
		$db->setQuery($db->getAlterDbCharacterSet($name));

		try
		{
			$db->execute();
		}
		catch (\RuntimeException $e)
		{
			return false;
		}

		return true;
	}

	/**
	 * Method to split up queries from a schema file into an array.
	 *
	 * @param   string  $query  SQL schema.
	 *
	 * @return  array  Queries to perform.
	 *
	 * @since   3.1
	 */
	protected function _splitQueries($query)
	{
		$buffer = array();
		$queries = array();
		$in_string = false;

		// Trim any whitespace.
		$query = trim($query);

		// Remove comment lines.
		$query = preg_replace("/\n\#[^\n]*/", '', "\n" . $query);

		// Remove PostgreSQL comment lines.
		$query = preg_replace("/\n\--[^\n]*/", '', "\n" . $query);

		// Find function
		$funct = explode('CREATE OR REPLACE FUNCTION', $query);

		// Save sql before function and parse it
		$query = $funct[0];

		// Parse the schema file to break up queries.
		for ($i = 0; $i < strlen($query) - 1; $i++)
		{
			if ($query[$i] == ";" && !$in_string)
			{
				$queries[] = substr($query, 0, $i);
				$query = substr($query, $i + 1);
				$i = 0;
			}

			if ($in_string && ($query[$i] == $in_string) && $buffer[1] != "\\")
			{
				$in_string = false;
			}
			elseif (!$in_string && ($query[$i] == '"' || $query[$i] == "'") && (!isset ($buffer[0]) || $buffer[0] != "\\"))
			{
				$in_string = $query[$i];
			}
			if (isset ($buffer[1]))
			{
				$buffer[0] = $buffer[1];
			}
			$buffer[1] = $query[$i];
		}

		// If the is anything left over, add it to the queries.
		if (!empty($query))
		{
			$queries[] = $query;
		}

		// Add function part as is
		for ($f = 1; $f < count($funct); $f++)
		{
			$queries[] = 'CREATE OR REPLACE FUNCTION ' . $funct[$f];
		}

		return $queries;
	}
}
