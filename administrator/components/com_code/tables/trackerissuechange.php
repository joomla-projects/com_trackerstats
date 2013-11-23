<?php
/**
 * @version		$Id: trackerissuechange.php 398 2010-06-13 17:53:03Z louis $
 * @package		Joomla.Administrator
 * @subpackage	com_code
 * @copyright	Copyright (C) 2009 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 * @since		1.6
 */

defined('_JEXEC') or die;

/**
 * Code tracker issue change table object.
 *
 * @package		Joomla.Code
 * @subpackage	com_code
 * @since		1.0
 */
class CodeTableTrackerIssueChange extends JTable
{
	/**
	 * @var int Primary key
	 */
	public $change_id;

	/**
	 * @var int Primary key
	 */
	public $issue_id;

	/**
	 * @var int Primary key
	 */
	public $tracker_id;

	/**
	 * @var	int	Foreign key to #__code_builds.build_id
	 */
	public $change_date;

	/**
	 * @var	int	Foreign key to #__code_builds.build_id
	 */
	public $change_by;

	/**
	 * @var	int	Foreign key to #__code_builds.build_id
	 */
	public $data;

	/**
	 * @var	int	Foreign key to #__code_builds.build_id
	 */
	public $jc_change_id;

	/**
	 * @var	int	Foreign key to #__code_builds.build_id
	 */
	public $jc_issue_id;

	/**
	 * @var	int	Foreign key to #__code_builds.build_id
	 */
	public $jc_tracker_id;

	/**
	 * @var	int	Foreign key to #__code_builds.build_id
	 */
	public $jc_change_by;

	/**
	 * Class constructor.
	 *
	 * @param	JDatabaseDriver  $db  A database connector object.
	 *
	 * @since	1.0
	 */
	public function __construct($db)
	{
		parent::__construct('#__code_tracker_issue_changes', 'change_id', $db);
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

		// Look up the change ID based on the legacy ID.
		$db->setQuery(
			$db->getQuery(true)
				->select($this->_tbl_key)
				->from($this->_tbl)
				->where('jc_change_id = ' . (int) $legacyId)
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
}
