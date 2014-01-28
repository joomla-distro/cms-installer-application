<?php
/**
 *
 * @copyright  Copyright (C) 2012 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace App\Model;

use App\Model\DefaultModel;

use Joomla\Utilities\ArrayHelper as JArrayHelper;
use Joomla\Registry\Registry as JRegistry;
use Joomla\Filesystem\Path as JPath;
use Joomla\Language\Text as JText;
use Joomla\Crypt\Crypt as JCrypt;
use Hautelook\Phpass\PasswordHash;

/**
 * Database model for the tracker application.
 *
 * @since  1.0
 */
class ConfigurationModel extends DefaultModel
{
    private $userId = 0;

    /**
     * Generates the user ID
     *
     * @return  integer  The user ID
     *
     * @since   3.1
     */
    protected function generateRandUserId()
    {
        $session = $this->application->getSession();
        $randUserId = $session->get('randUserId');

        if (empty($randUserId))
        {
            // Create the ID for the root user only once and store in session
            $randUserId = mt_rand(1, 1000);
            $session->set('randUserId', $randUserId);
        }

        return $randUserId;
    }

    /**
     * Resets the user ID
     *
     * @return  void
     *
     * @since   3.1
     */
    public function resetRandUserId()
    {
        $session = $this->application->getSession();
        $session->set('randUserId', $this->userId);
    }

    /**
     * Retrieves the default user ID and sets it if necessary
     *
     * @return  integer  The user ID
     *
     * @since   3.1
     */
    public function getUserId()
    {
        if (!$this->userId)
        {
            $this->userId = $this->generateRandUserId();
        }

        return $this->userId;
    }

	/**
	 * Method to setup the configuration file
	 *
	 * @param   array  $options  The session options
	 *
	 * @return  boolean  True on success
	 *
	 * @since   3.1
	 */
	public function setup($options)
	{
		// Get the options as an object for easier handling.
		$options = JArrayHelper::toObject($options);

		// Attempt to create the root user.
		if (!$this->_createConfiguration($options))
		{
			return false;
		}

		// Attempt to create the root user.
		if (!$this->_createRootUser($options))
		{
			return false;
		}

		return true;
	}

    public function genRandomPassword($length = 8)
    {
        $salt = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $base = strlen($salt);
        $makepass = '';

        /*
         * Start with a cryptographic strength random string, then convert it to
         * a string with the numeric base of the salt.
         * Shift the base conversion on each character so the character
         * distribution is even, and randomize the start shift so it's not
         * predictable.
         */
        $random = JCrypt::genRandomBytes($length + 1);
        $shift = ord($random[0]);

        for ($i = 1; $i <= $length; ++$i)
        {
            $makepass .= $salt[($shift + ord($random[$i])) % $base];
            $shift += ord($random[$i]);
        }

        return $makepass;
    }

	/**
	 * Method to create the configuration file
	 *
	 * @param   array  $options  The session options
	 *
	 * @return  boolean  True on success
	 *
	 * @since   3.1
	 */
	function _createConfiguration($options)
	{
		// Create a new registry to build the configuration options.
		$registry = new JRegistry;

		/* Site Settings */
		$registry->set('offline', $options->site_offline);
		$registry->set('offline_message', JText::_('INSTL_STD_OFFLINE_MSG'));
		$registry->set('display_offline_message', 1);
		$registry->set('offline_image', '');
		$registry->set('sitename', $options->site_name);
		$registry->set('editor', 'tinymce');
		$registry->set('captcha', '0');
		$registry->set('list_limit', 20);
		$registry->set('access', 1);

		/* Debug Settings */
		$registry->set('debug', 0);
		$registry->set('debug_lang', 0);

		/* Database Settings */
		$registry->set('dbtype', $options->db_type);
		$registry->set('host', $options->db_host);
		$registry->set('user', $options->db_user);
		$registry->set('password', $options->db_pass);
		$registry->set('db', $options->db_name);
		$registry->set('dbprefix', $options->db_prefix);

		/* Server Settings */
		$registry->set('live_site', '');
		$registry->set('secret', $this->genRandomPassword(16));
		$registry->set('gzip', 0);
		$registry->set('error_reporting', 'default');
		$registry->set('helpurl', $options->helpurl);
		$registry->set('ftp_host', isset($options->ftp_host) ? $options->ftp_host : '');
		$registry->set('ftp_port', isset($options->ftp_host) ? $options->ftp_port : '');
		$registry->set('ftp_user', (isset($options->ftp_save) && $options->ftp_save && isset($options->ftp_user)) ? $options->ftp_user : '');
		$registry->set('ftp_pass', (isset($options->ftp_save) && $options->ftp_save && isset($options->ftp_pass)) ? $options->ftp_pass : '');
		$registry->set('ftp_root', (isset($options->ftp_save) && $options->ftp_save && isset($options->ftp_root)) ? $options->ftp_root : '');
		$registry->set('ftp_enable', isset($options->ftp_host) ? $options->ftp_enable : '');

		/* Locale Settings */
		$registry->set('offset', 'UTC');

		/* Mail Settings */
		$registry->set('mailonline', 1);
		$registry->set('mailer', 'mail');
		$registry->set('mailfrom', $options->admin_email);
		$registry->set('fromname', $options->site_name);
		$registry->set('sendmail', '/usr/sbin/sendmail');
		$registry->set('smtpauth', 0);
		$registry->set('smtpuser', '');
		$registry->set('smtppass', '');
		$registry->set('smtphost', 'localhost');
		$registry->set('smtpsecure', 'none');
		$registry->set('smtpport', '25');

		/* Cache Settings */
		$registry->set('caching', 0);
		$registry->set('cache_handler', 'file');
		$registry->set('cachetime', 15);

		/* Meta Settings */
		$registry->set('MetaDesc', isset($options->site_metadesc) ? $options->site_metadesc : '' );
		$registry->set('MetaKeys', '');
		$registry->set('MetaTitle', 1);
		$registry->set('MetaAuthor', 1);
		$registry->set('MetaVersion', 0);
		$registry->set('robots', '');

		/* SEO Settings */
		$registry->set('sef', 1);
		$registry->set('sef_rewrite', 0);
		$registry->set('sef_suffix', 0);
		$registry->set('unicodeslugs', 0);

		/* Feed Settings */
		$registry->set('feed_limit', 10);
		$registry->set('log_path', dirname(JPATH_ROOT) . '/logs');
		$registry->set('tmp_path', dirname(JPATH_ROOT) . '/tmp');

		/* Session Setting */
		$registry->set('lifetime', 15);
		$registry->set('session_handler', 'database');

		// Generate the configuration class string buffer.
		$buffer = $registry->toString('PHP', array('class' => 'JConfig', 'closingtag' => false));

		// Build the configuration file path.
		$path = dirname(JPATH_ROOT) . '/configuration.php';

		// Determine if the configuration file path is writable.
		if (file_exists($path))
		{
			$canWrite = is_writable($path);
		}
		else
		{
			$canWrite = is_writable(dirname(JPATH_ROOT) . '/');
		}

		/*
		 * If the file exists but isn't writable OR if the file doesn't exist and the parent directory
		 * is not writable we need to use FTP
		 */
		$useFTP = false;

		if ((file_exists($path) && !is_writable($path)) || (!file_exists($path) && !is_writable(dirname($path) . '/')))
		{
			$useFTP = true;
		}

		// Check for safe mode
		if (ini_get('safe_mode'))
		{
			$useFTP = true;
		}

		// Enable/Disable override
		if (!isset($options->ftpEnable) || ($options->ftpEnable != 1))
		{
			$useFTP = false;
		}

		if ($useFTP == true)
		{
			// Connect the FTP client
			$ftp = JClientFtp::getInstance($options->ftp_host, $options->ftp_port);
			$ftp->login($options->ftp_user, $options->ftp_pass);

			// Translate path for the FTP account
			$file = JPath::clean(str_replace(JPATH_CONFIGURATION, $options->ftp_root, $path), '/');

			// Use FTP write buffer to file
			if (!$ftp->write($file, $buffer))
			{
				// Set the config string to the session.
				$session = $this->application->getSession();
				$session->set('setup.config', $buffer);
			}

			$ftp->quit();
		}
		else
		{
			if ($canWrite)
			{
				file_put_contents($path, $buffer);
				$session = $this->application->getSession();
				$session->set('setup.config', null);
			}
			else
			{
				// Set the config string to the session.
				$session = $this->application->getSession();
				$session->set('setup.config', $buffer);
			}
		}

		return true;
	}

	/**
	 * Method to create the root user for the site
	 *
	 * @param   object  $options  The session options
	 *
	 * @return  boolean  True on success
	 *
	 * @since   3.1
	 */
	private function _createRootUser($options)
	{
		// Get the application
		/* @var InstallationApplicationWeb $app */
		$app = $this->application;

		// Get a database object.
		try
		{
			$db = \App\Helper\DatabaseHelper::getDBO($options->db_type, $options->db_host, $options->db_user, $options->db_pass, $options->db_name, $options->db_prefix);
		}
		catch (\RuntimeException $e)
		{
			$app->enqueueMessage(JText::sprintf('INSTL_ERROR_CONNECT_DB', $e->getMessage()), 'notice');

			return false;
		}

        // Use PHPass's portable hashes with a cost of 10.
        $phpass = new PasswordHash(10, true);
		$cryptpass = $phpass->HashPassword($options->admin_password);

		// Take the admin user id
		$userId = $this->getUserId();

		// We don't need the randUserId in the session any longer, let's remove it
        $this->resetRandUserId();

		// Create the admin user
		date_default_timezone_set('UTC');
		$installdate = date('Y-m-d H:i:s');
		$nullDate    = $db->getNullDate();

		// Sqlsrv change
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__users'))
			->where($db->quoteName('id') . ' = ' . $db->quote($userId));

		$db->setQuery($query);

		if ($db->loadResult())
		{
			$query->clear()
				->update($db->quoteName('#__users'))
				->set($db->quoteName('name') . ' = ' . $db->quote('Super User'))
				->set($db->quoteName('username') . ' = ' . $db->quote(trim($options->admin_user)))
				->set($db->quoteName('email') . ' = ' . $db->quote($options->admin_email))
				->set($db->quoteName('password') . ' = ' . $db->quote($cryptpass))
				->set($db->quoteName('block') . ' = 0')
				->set($db->quoteName('sendEmail') . ' = 1')
				->set($db->quoteName('registerDate') . ' = ' . $db->quote($installdate))
				->set($db->quoteName('lastvisitDate') . ' = ' . $db->quote($nullDate))
				->set($db->quoteName('activation') . ' = ' . $db->quote('0'))
				->set($db->quoteName('params') . ' = ' . $db->quote(''))
				->where($db->quoteName('id') . ' = ' . $db->quote($userId));
		}
		else
		{
			$columns = array($db->quoteName('id'), $db->quoteName('name'), $db->quoteName('username'),
							$db->quoteName('email'), $db->quoteName('password'),
							$db->quoteName('block'),
							$db->quoteName('sendEmail'), $db->quoteName('registerDate'),
							$db->quoteName('lastvisitDate'), $db->quoteName('activation'), $db->quoteName('params'));
			$query->clear()
				->insert('#__users', true)
				->columns($columns)
				->values(
				$db->quote($userId) . ', ' . $db->quote('Super User') . ', ' . $db->quote(trim($options->admin_user)) . ', ' .
				$db->quote($options->admin_email) . ', ' . $db->quote($cryptpass) . ', ' .
				$db->quote('0') . ', ' . $db->quote('1') . ', ' . $db->quote($installdate) . ', ' . $db->quote($nullDate) . ', ' .
				$db->quote('0') . ', ' . $db->quote('')
			);
		}

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

		// Map the super admin to the Super Admin Group
		$query->clear()
			->select($db->quoteName('user_id'))
			->from($db->quoteName('#__user_usergroup_map'))
			->where($db->quoteName('user_id') . ' = ' . $db->quote($userId));

		$db->setQuery($query);

		if ($db->loadResult())
		{
			$query->clear()
				->update($db->quoteName('#__user_usergroup_map'))
				->set($db->quoteName('user_id') . ' = ' . $db->quote($userId))
				->set($db->quoteName('group_id') . ' = 8');
		}
		else
		{
			$query->clear()
				->insert($db->quoteName('#__user_usergroup_map'), false)
				->columns(array($db->quoteName('user_id'), $db->quoteName('group_id')))
				->values($db->quote($userId) . ', 8');
		}

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

		return true;
	}
}