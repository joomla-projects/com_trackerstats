<?php
/**
 * @version		$Id: gforge.php 455 2010-10-07 17:51:44Z louis $
 * @package		Joomla.Site
 * @subpackage	com_code
 * @copyright	Copyright (C) 2009 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Connector class to a GForge Advanced Server SOAP API.
 *
 * @package  Joomla.Code
 * @see      http://joomlacode.org/gf/xmlcompatibility/soap5/
 * @since    1.0
 */
class GForge
{
	/**
	 * @var    SoapClient  The client object connected to the GForge instance.
	 * @since  1.0
	 */
	protected $client;

	/**
	 * @var    string  The session hash for the SOAP session.
	 * @since  1.0
	 */
	protected $sessionhash;

	/**
	 * @var    string  The username for the signed in session.
	 * @since  1.0
	 */
	protected $username;

	/**
	 * @var    string  The URI for the API
	 * @since  1.0
	 */
	protected $apiUri = '/xmlcompatibility/soap5/?wsdl';

	/**
	 * Object constructor.  Creates the connection to the GForge site instance.
	 *
	 * @param   string  $site     The URL to the gforge instance.
	 * @param   array   $options  The SOAP options for the connection.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function __construct($site, $options = array())
	{
		// Attempt to connect to the SOAP gateway.
		$this->client = new SoapClient($site . $this->apiUri, $options);

		// Check for an error.
		if (!$this->client)
		{
			throw new RuntimeException('Unable to connect to GForge instance at ' . $site);
		}
	}

	/**
	 * Object destructor.  Signs out and closes the connection.
	 *
	 * @since   1.0
	 */
	public function __destruct()
	{
		// Check to see if the connection is live.
		if ($this->client)
		{
			// Check to see if we are signed in.
			if ($this->sessionhash)
			{
				$this->logout();
			}

			// Kill the connection.
			unset($this->client);
		}
	}

	/**
	 * Method to sign into GForge using password authentication.
	 *
	 * @param   string   $username  The username for the account to login.
	 * @param   string   $password  The password for the account to login.
	 *
	 * @return	boolean  True on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function login($username, $password)
	{
		try
		{
			// Attempt to sign into the account and get the session hash.
			$sessionhash = $this->client->login($username, $password);

			// Cache the session hash and username for later use.
			$this->sessionhash = $sessionhash;
			$this->username = $username;

			return true;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Login Failed: ' . $e->faultstring);
		}
	}

	/**
	 * Method to sign out of GForge.
	 *
	 * @return	boolean  True on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function logout()
	{
		try
		{
			// Attempt to sign out.
			$this->client->logout($this->sessionhash);
			$this->sessionhash = null;
			$this->username = null;

			return true;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Logout Failed: ' . $e->faultstring);
		}
	}

	/**
	 * Method to get user data by username.
	 *
	 * @param   string  $username  The optional username to get user data for, defaults to the user
	 *                             signed into the current session.
	 *
	 * @return  object   User data object on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getUser($username = null)
	{
		try
		{
			// Attempt to get the user object by the username or "unix name" in GForge speak.
			$user = $this->client->getUserByUnixName($this->sessionhash, $username ? $username : $this->username);

			return $user;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Failed to get user ' . ($username ? $username : $this->username) . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get user data by username.
	 *
	 * @param   array  $ids  The optional user IDs to get user data for, defaults to the user
	 *                       signed into the current session.
	 *
	 * @return  object  User data object on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getUsersById($ids = array())
	{
		try
		{
			// Attempt to get the user object by the username or "unix name" in GForge speak.
			$users = $this->client->getUserArray($this->sessionhash, $ids);

			return $users;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Failed to get users (' . implode(',', $ids) . '): ' . $e->faultstring);
		}
	}

	/**
	 * Method to get the projects a user belongs to by username.
	 *
	 * @param   string  $username  The optional username to get the project list for, defaults to
	 *                             the user signed into the current session.
	 *
	 * @return  object  Project data array on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getUserProjects($username = null)
	{
		try
		{
			// Attempt to get the project data array by the username or "unix name" in GForge speak.
			$projects = $this->client->getUserProjects($this->sessionhash, $username ? $username : $this->username);

			return $projects;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get projects for user ' . ($username ? $username : $this->username) . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get a project object by name.
	 *
	 * @param   string  $name  The name of the project for which to get the data object.
	 *
	 * @return  object  Project data object on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getProject($name)
	{
		try
		{
			// Attempt to get the project data object by the name or "unix name" in GForge speak.
			$project = $this->client->getProjectByUnixName($this->sessionhash, $name);

			return $project;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get project ' . $name . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get a project members by project id.
	 *
	 * @param   integer  $projectId  The project id.
	 *
	 * @return  array Project members data array on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getProjectMembers($projectId)
	{
		try
		{
			$members = $this->client->getProjectMembers($this->sessionhash, $projectId);

			return $members;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get members : ' . $e->faultstring);
		}
	}
	

	/**
	 * Method to get a project object by id.
	 *
	 * @param   integer  $id  The name of the project for which to get the data object.
	 *
	 * @return  object  Project data object on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getProjectById($id)
	{
		try
		{
			// Attempt to get the project data object by the ID.
			$project = $this->client->getProject($this->sessionhash, $id);

			return $project;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get project ' . $name . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get an array of file Systems by section and ref_id
	 * Section and ref_id are parts of download url : download/{section}/{ref_id}
	 *
	 * @param   string   $section  The section name.
	 * @param   integer  $fileId  The file id.
	 *
	 * @return  array File systems data array on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getFilesystems($section, $refId)
	{
		try
		{
			$systems = $this->client->getFilesystems($this->sessionhash, $section, $refId);

			return $systems;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get file systems for section ' . $section . ' and ref id ' . $refId . ' : ' . $e->faultstring);
		}
	}

	/**
	 * Method to get the project trackers by project name or id.
	 *
	 * @param   mixed  $project  Either the project name or numeric id for the project to get a list
	 *                           of tracker data objects.
	 *
	 * @return  object  Tracker data array on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getProjectTrackers($project)
	{
		// If a project name was given go find the project id based on the name.
		if (!is_numeric($project))
		{
			// Attempt to get the project object from the name.
			$project = $this->getProject($project);

			// Assign the project id based on the returned project or return false if not found.
			if ($project)
			{
				$projectId = $project->project_id;
			}
			else
			{
				return false;
			}
		}
		// Easy peasy...
		else
		{
			$projectId = $project;
		}

		try
		{
			// Attempt to get the project tracker array by the project id.
			$trackers = $this->client->getTrackers($this->sessionhash, $projectId, true, -1);

			return $trackers;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get trackers for project ' . $project . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get a tracker object by id.
	 *
	 * @param   integer  $trackerId  The tracker id for which to get the data object.
	 *
	 * @return  object  Tracker data object on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getTracker($trackerId)
	{
		try
		{
			// Attempt to get the tracker data object by id.
			$tracker = $this->client->getTracker($this->sessionhash, $trackerId);

			return $tracker;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get tracker ' . $trackerId . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get a list of tracker fields from a specific tracker by tracker id.
	 *
	 * @param   integer  $trackerId  The numeric id of the tracker for which to get a list of fields.
	 *
	 * @return  array  Tracker field data array on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getTrackerFields($trackerId)
	{
		try
		{
			// Attempt to get a list of tracker field data by tracker id.
			$fields = $this->client->getTrackerExtraFields($this->sessionhash, $trackerId, -1);

			return $fields;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get fields for tracker ' . $trackerId . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get a list of tracker field values from a specific field by field id.
	 *
	 * @param   integer  $fieldId  The numeric id of the field for which to get a list of values.
	 *
	 * @return  mixed  Boolean false on failure, tracker field value data array on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getTrackerFieldValues($fieldId)
	{
		try
		{
			// Attempt to get a list of tracker field values by field id.
			$fields = $this->client->getTrackerExtraFieldElements($this->sessionhash, $fieldId, '', -1, -1);

			return $fields;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get values for tracker field ' . $fieldId . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get a list of tracker items from a specific tracker by tracker id.
	 *
	 * @param   integer  $trackerId  The numeric id of the tracker for which to get a list of items.
	 *
	 * @return  array  Tracker item data array on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getTrackerItems($trackerId)
	{
		try
		{
			// Attempt to get a list of tracker item data by tracker id.
			// Get in batches to avoid errors
			$increment = 1000;
			$limit     = 1 + (int) (20000 / $increment);
			$itemArray = array();
			$items     = array();

			for ($i = 0; $i < 20; $i++)
			{
				$start         = $i * $increment;
				$itemArray[$i] = $this->client->getTrackerItemsShort($this->sessionhash, $trackerId, -1, -1, $increment, $start);
				$items         = array_merge($items, $itemArray[$i]);
			}

			return $items;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get tracker items for tracker ' . $trackerId . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get a tracker item object by id.
	 *
	 * @param   integer  $itemId  The tracker item id for which to get the data object.
	 *
	 * @return  object  Tracker item data object on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getTrackerItem($itemId)
	{
		try
		{
			// Attempt to get the item data object by item id.
			$item = $this->client->getTrackerItemFull($this->sessionhash, $itemId);

			return $item;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get tracker item ' . $itemId . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get an array of tracker item changes by id.
	 *
	 * @param   integer  $itemId  The tracker item id for which to get the changes array.
	 *
	 * @return  array  Tracker item changes data array on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getTrackerItemChanges($itemId)
	{
		try
		{
			// Attempt to get the changes data array by the tracker item id.
			$changes = $this->client->getAuditTrails($this->sessionhash, $itemId);

			return $changes;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get changes for tracker item ' . $itemId . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get an array of tracker item messages by id.
	 *
	 * @param   integer  $itemId  The tracker item id for which to get the messages array.
	 *
	 * @return  array  Tracker item messages data array on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getTrackerItemMessages($itemId)
	{
		try
		{
			// Attempt to get the messages data array by the tracker item id.
			$messages = $this->client->getTrackerItemMessages($this->sessionhash, $itemId, -1);

			return $messages;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get messages for tracker item ' . $itemId . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get an array of Docman folders by project Id
	 *
	 * @param   integer  $projectId  The project id.
	 *
	 * @return  array Docman folders data array on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getDocmanFolders($projectId)
	{
		try
		{
			$folders = $this->client->getDocmanFolders($this->sessionhash, $projectId);

			return $folders;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get folders for project id ' . $projectId . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get an array of Docman folder files by folder Id
	 *
	 * @param   integer  $folderId  The folder id.
	 *
	 * @return  array Folder files data array on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getDocmanFiles($folderId)
	{
		try
		{
			$files = $this->client->getDocmanFiles($this->sessionhash, $folderId);

			return $files;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get files for folder id ' . $folderId . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get an array of file Versions by file Id
	 *
	 * @param   integer  $fileId  The file id.
	 *
	 * @return  array File versions data array on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getDocmanFileVersions($fileId)
	{
		try
		{
			$versions = $this->client->getDocmanFileVersions($this->sessionhash, $fileId);

			return $versions;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get versions for file id ' . $fileId . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get an array of forum Threads by forum Id
	 *
	 * @param   integer  $forumId  The forum id for.
	 *
	 * @return  array  Forum threads data array on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getForumThreads($forumId)
	{
		try
		{
			$threads = $this->client->getForumThreads($this->sessionhash, $forumId);

			return $threads;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get threads for forum id ' . $forumId . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get an array of thread messages by thread Id
	 *
	 * @param   integer  $threadId  The forum thread id.
	 *
	 * @return  array  Thread messages data array on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	public function getForumMessages($threadId)
	{
		try
		{
			$messages = $this->client->getForumMessages($this->sessionhash, $threadId);

			return $messages;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Unable to get messages for thread id ' . $threadId . ': ' . $e->faultstring);
		}
	}

	/**
	 * Method to get a list of client functions.
	 *
	 * @return  array  Functions array on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	protected function getClientFunctions()
	{
		try
		{
			// Attempt to get the client functions.
			$functions = $this->client->__getFunctions();

			return $functions;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Failed to get functions: ' . $e->faultstring);
		}
	}

	/**
	 * Method to get a list of client types.
	 *
	 * @return  array  Array of types on success.
	 *
	 * @since   1.0
	 * @throws  RuntimeException
	 */
	protected function getClientTypes()
	{
		try
		{
			// Attempt to get the client types.
			$functions = $this->client->__getTypes();

			return $functions;
		}
		catch (SoapFault $e)
		{
			throw new RuntimeException('Failed to get types: ' . $e->faultstring);
		}
	}
}
