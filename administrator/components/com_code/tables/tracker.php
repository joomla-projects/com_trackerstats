<?php
/**
 * @version		$Id: tracker.php 417 2010-06-25 01:01:45Z louis $
 * @package		Joomla.Administrator
 * @subpackage	com_code
 * @copyright	Copyright (C) 2009 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 * @since		1.6
 */

defined('_JEXEC') or die;

/**
 * Code tracker table object.
 *
 * @package		Joomla.Code
 * @subpackage	com_code
 * @since		1.0
 */
class CodeTableTracker extends JTable
{
	/**
	 * @var int Primary key
	 */
	public $tracker_id;

	/**
	 * @var	int	Foreign key to #__users.id
	 */
	public $project_id;

	/**
	 * @var	string	The URI path to the branch.
	 */
	public $title;

	/**
	 * @var	string	The name of the branch.
	 */
	public $alias;

	/**
	 * @var	string	A description of the branch purpose.
	 */
	public $summary;

	/**
	 * @var	int	The publishing state of the branch.
	 */
	public $description;

	/**
	 * @var	string	The date/time when the branch was last updated.
	 */
	public $state;

	/**
	 * @var	string	The date/time when the branch was created.
	 */
	public $access;

	/**
	 * @var	int	Foreign key to #__code_builds.build_id
	 */
	public $item_count;

	/**
	 * @var	int	Foreign key to #__code_builds.build_id
	 */
	public $open_item_count;

	/**
	 * @var	int	Foreign key to #__code_builds.build_id
	 */
	public $created_date;

	/**
	 * @var	int	Foreign key to #__code_builds.build_id
	 */
	public $created_by;

	/**
	 * @var	int	Foreign key to #__code_builds.build_id
	 */
	public $modified_date;

	/**
	 * @var	int	Foreign key to #__code_builds.build_id
	 */
	public $modified_by;

	/**
	 * @var	int	Foreign key to #__code_builds.build_id
	 */
	public $jc_tracker_id;

	/**
	 * @var	int	Foreign key to #__code_builds.build_id
	 */
	public $jc_project_id;

	/**
	 * Class constructor.
	 *
	 * @param	JDatabaseDriver  $db  A database connector object.
	 *
	 * @since	1.0
	 */
	public function __construct($db)
	{
		parent::__construct('#__code_trackers', 'tracker_id', $db);

		$this->access = (int) JFactory::getConfig()->get('access');
	}

	/**
	 * Method to load a data object by its legacy ID
	 *
	 * @param   integer  $legacyId  The tracker ID to load
	 *
	 * @return  boolean  True on success
	 */
	public function loadByLegacyId($legacyId)
	{
		// Load the database object
		$db = $this->getDbo();

		// Look up the tracker ID based on the legacy ID.
		$db->setQuery(
			$db->getQuery(true)
				->select($this->_tbl_key)
				->from($this->_tbl)
				->where('jc_tracker_id = ' . (int) $legacyId)
		);

		$issueId = (int) $db->loadResult();

		if ($issueId)
		{
			return $this->load($issueId);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Method to compute the default name of the asset.
	 * The default name is in the form `table_name.id`
	 * where id is the value of the primary key of the table.
	 *
	 * @return	string
	 */
	protected function _getAssetName()
	{
		$k = $this->_tbl_key;

		return 'com_code.tracker.' . (int) $this->$k;
	}

	/**
	 * Method to return the title to use for the asset table.
	 *
	 * @return	string
	 *
	 * @since	1.6
	 */
	protected function _getAssetTitle()
	{
		return $this->title;
	}

	/**
	 * Method to get the parent asset under which to register this one.
	 * By default, all assets are registered to the ROOT node with ID,
	 * which will default to 1 if none exists.
	 * The extended class can define a table and id to lookup.  If the
	 * asset does not exist it will be created.
	 *
	 * @param   JTable   $table  A JTable object for the asset parent.
	 * @param   integer  $id     Id to look up
	 *
	 * @return  integer
	 */
	protected function _getAssetParentId(JTable $table = null, $id = null)
	{
		// Initialise variables.
		$assetId = null;
		$db      = $this->getDbo();

		// This is a tracker under a project.
		if ($this->project_id > 0)
		{
			// Get the asset ID from the database.
			$db->setQuery(
				$db->getQuery(true)
					->select('asset_id')
					->from('#__code_projects')
					->where('project_id = ' . (int) $this->project_id)
			);

			if ($result = $db->loadResult())
			{
				$assetId = (int) $result;
			}
		}
		// This is a tracker that needs to parent with the extension.
		elseif ($assetId === null)
		{
			// Get the asset ID from the database.
			$db->setQuery(
				$db->getQuery(true)
					->select('id')
					->from('#__assets')
					->where('name = ' . $db->quote('com_code'))
			);

			if ($result = $db->loadResult())
			{
				$assetId = (int) $result;
			}
		}

		// Return the asset id.
		if ($assetId)
		{
			return $assetId;
		}
		else
		{
			return parent::_getAssetParentId($table, $id);
		}
	}
}
