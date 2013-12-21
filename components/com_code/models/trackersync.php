<?php
/**
 * @version		$Id: trackersync.php 458 2010-10-07 18:06:31Z louis $
 * @package		Joomla.Site
 * @subpackage	com_code
 * @copyright	Copyright (C) 2009 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

// Include dependencies
jimport('joomla.utilities.arrayhelper');

// Include the GForge connector classes.
require JPATH_COMPONENT . '/helpers/gforge.php';
require JPATH_COMPONENT . '/helpers/gforgelegacy.php';

/**
 * Tracker Synchronization Model for Joomla Code
 *
 * @package		Joomla.Code
 * @subpackage	com_code
 * @since		1.0
 */
class CodeModelTrackerSync extends JModelLegacy
{
	/**
	 * @var    GForge  The GForge SOAP connector object.
	 * @since  1.0
	 */
	protected $gforge;

	/**
	 * @var    GForgeLegacy  The GForge legacy SOAP connector object.
	 * @since  1.0
	 */
	protected $gforgeLegacy;

	/**
	 * @var    array  Associative array of tracker issue status values.
	 * @since  1.0
	 */
	protected $status = array();

	/**
	 * @var    array  Associative array of tracker fields.
	 * @since  1.0
	 */
	protected $fields = array();

	/**
	 * @var    array  Associative array of tracker field data values.
	 * @since  1.0
	 */
	protected $fieldValues = array();

	/**
	 * @var    array  Associative array of processing statistics
	 * @since  1.0
	 */
	protected $processingTotals = array('issues' => 0, 'changes' => 0, 'files' => 0, 'messages' => 0, 'users' => 0);

	/**
	 * @var    array  Array of trackers to snapshot
	 * @since  1.0
	 */
	protected $syncTrackers = array();

	/**
	 * Fixes file data for issues
	 *
	 * @return  void
	 */
	public function filefix()
	{
		// Initialize variables.
		$db = $this->getDbo();

		$db->setQuery(
		   $db->getQuery(true)
				->select('DISTINCT issue_id')
				->from($db->quoteName('#__code_tracker_issue_files'))
		);

		$issues = $db->loadColumn();

		foreach ($issues as $issue)
		{
			$this->fixFilesForIssue($issue);
		}

		return true;
	}

	/**
	 * Fixes file data for issues
	 *
	 * @param   string  $issueId  Issue ID
	 *
	 * @return  mixed  Boolean false on failure, void otherwise
	 */
	private function fixFilesForIssue($issueId)
	{
		// Initialize variables.
		$db = $this->getDBO();

		// Get some important issue data.
		$db->setQuery(
			$db->getQuery(true)
				->select('DISTINCT issue_id, created_by, created_date, modified_date')
				->from($db->quoteName('#__code_tracker_issues'))
				->where($db->quoteName('issue_id') . ' = ' . (int) $issueId)
		);
		$issue = $db->loadObject();

		// Get the list of comments for this issue.
		$db->setQuery(
		   $db->getQuery(true)
				->select('created_date, created_by, body')
				->from($db->quoteName('#__code_tracker_issue_responses'))
				->where($db->quoteName('issue_id') . ' = ' . (int) $issue->issue_id)
				->order('created_date DESC')
		);
		$comments = (array) $db->loadObjectList();

		// Get the list of status changes for this issue.
		$db->setQuery(
		   $db->getQuery(true)
				->select('change_date, change_by')
				->from($db->quoteName('#__code_tracker_issue_changes'))
				->where($db->quoteName('issue_id') . ' = ' . (int) $issue->issue_id)
				->order('change_date DESC')
		);
		$changes = (array) $db->loadObjectList();

		// Get the list of files for this issue.
		$db->setQuery(
		   $db->getQuery(true)
				->select('file_id, created_by, name')
				->from($db->quoteName('#__code_tracker_issue_files'))
				->where($db->quoteName('issue_id') . ' = ' . (int) $issue->issue_id)
				->order('jc_file_id DESC')
		);
		$files = (array) $db->loadObjectList();

		foreach ($files as &$file)
		{
			$found = false;

			// First we look for a comment.
			foreach ($comments as & $comment)
			{
				if (empty($comment->used) && ($comment->created_by == $file->created_by))
				{
					$found              = true;
					$comment->used      = true;
					$file->created_date = $comment->created_date;
					break;
				}
			}

			// If not found, next we look for a change.
			if (!$found)
			{
				foreach ($changes as & $change)
				{
					if (empty($change->used) && ($change->change_by == $file->created_by))
					{
						$found              = true;
						$change->used       = true;
						$file->created_date = $change->change_date;
						break;
					}
				}
			}

			// Last we look to see if the issue was created by the person who posted the file
			if (!$found)
			{
				if ($issue->created_by == $file->created_by)
				{
					$found              = true;
					$file->created_date = $issue->created_date;
				}
			}

			if ($found)
			{
				// Fix the row in the database.
				$db->setQuery(
					$db->getQuery(true)
						->update($db->quoteName('#__code_tracker_issue_files'))
						->set($db->quoteName('created_date') . ' = ' . $db->quote($file->created_date))
						->where($db->quoteName('file_id') . ' = ' . (int) $file->file_id)
				);

				// Check for an error.
				if (!$this->_db->query())
				{
					$this->setError($this->_db->getErrorMsg());

					return false;
				}
			}
			else
			{
				// Fix the row in the database.
				$this->_db->setQuery(
					$db->getQuery(true)
						->update($db->quoteName('#__code_tracker_issue_files'))
						->set($db->quoteName('created_date') . ' = ' . $db->quote($issue->modified_date))
						->where($db->quoteName('file_id') . ' = ' . (int) $file->file_id)
				);

				// Check for an error.
				if (!$this->_db->query())
				{
					$this->setError($this->_db->getErrorMsg());

					return false;
				}
			}
		}
	}

	public function test()
	{
		// Get a tracker issue change table object.
		$table = $this->getTable('TrackerIssueChange', 'CodeTable');


		$table->load(42);
		var_dump(unserialize($table->data));
		var_dump($table);

	}

	/**
	 * Gets counts of issues in tracker by status code and store in #__code_tracker_snapshots table by date
	 *
	 * @return  void
	 */
	public function doStatusSnapshot()
	{
		// First get snapshot
		$cutoffDate = new DateTime;
		$cutoffDate->sub(new DateInterval('P2Y'));

		$today = new DateTime;
		$db    = $this->getDbo();

		foreach ($this->syncTrackers as $tracker_id)
		{
			$db->setQuery(
				$db->getQuery(true)
					->select('status_name, COUNT(*) as num_issues')
					->from($db->quoteName('#__code_tracker_issues'))
					->where($db->quoteName('tracker_id') . ' = ' . (int) $tracker_id)
					->where('DATE(modified_date) > ' . $db->quote($cutoffDate->format('Y-m-d')))
					->where('DATE(close_date) = ' . $db->quote('0000-00-00'))
					->group('status_name')
			);

			$dbArray = $db->loadObjectList();
			$jsonString = json_encode($dbArray);
			$this->writeSnapshot($tracker_id, $today, $jsonString);
		}
	}

	/**
	 * Writes a status snapshot
	 *
	 * @param   integer   $tracker_id  Tracker ID to write the snapshot for
	 * @param   DateTime  $date        DateTime object
	 * @param   string    $jsonString  JSON data
	 *
	 * @return  void
	 */
	public function writeSnapshot($tracker_id, $date, $jsonString)
	{
		// Update or insert row to table
		$db = $this->getDbo();

		$db->setQuery(
			$db->getQuery(true)
				->select('s.*')
				->from($db->quoteName('#__code_tracker_snapshots', 's'))
				->where($db->quoteName('s.tracker_id') . ' = ' . (int) $tracker_id)
				->where($db->quoteName('s.snapshot_day') . ' = ' . $db->quote($date->format('Y-m-d')))
		);

		try
		{
			$result = $db->loadObject();
		}
		catch (RuntimeException $e) {}

		if ($result)
		{
			// Update row with new timestamp and json string
			$db->setQuery(
				$db->getQuery(true)
					->update($db->quoteName('#__code_tracker_snapshots'))
					->set($db->quoteName('modified_date') . ' = ' . $db->quote($date->format('Y-m-d H:i:s')))
					->set($db->quoteName('status_counts') . ' = ' . $db->quote($jsonString))
					->where($db->quoteName('tracker_id') . ' = ' . (int) $tracker_id)
					->where($db->quoteName('snapshot_day') . ' = ' . $db->quote($date->format('Y-m-d')))
			);
		}
		else
		{
			// Insert a new row
			$db->setQuery(
				$db->getQuery(true)
					->insert($db->quoteName('#__code_tracker_snapshots'))
					->set($db->quoteName('modified_date') . ' = ' . $db->quote($date->format('Y-m-d H:i:s')))
					->set($db->quoteName('status_counts') . ' = ' . $db->quote($jsonString))
					->set($db->quoteName('tracker_id') . ' = ' . (int) $tracker_id)
					->set($db->quoteName('snapshot_day') . ' = ' . $db->quote($date->format('Y-m-d')))
			);
		}

		try
		{
			$db->execute();
		}
		catch (RuntimeException $e) {}
	}

	/**
	 * Synchronize the data from Joomlacode
	 *
	 * @return  bool  True on success
	 */
	public function sync()
	{
		// Initialize the logger
		$options['format'] = '{DATE}\t{TIME}\t{LEVEL}\t{CODE}\t{MESSAGE}';
		$options['text_file'] = 'gforge_sync.php';
		JLog::addLogger($options, JLog::INFO);
		JLog::add('Starting the GForge Sync', JLog::INFO);

		// Initialize variables.
		$username = JFactory::getConfig()->get('gforgeLogin');
		$password = JFactory::getConfig()->get('gforgePassword');
		$project  = 5; // Joomla project id.

		// Wrap the processing in try/catch to log errors
		try
		{
			// Connect to the main SOAP interface.
			$this->gforge = new GForge('http://joomlacode.org/gf');
			$this->gforge->login($username, $password);

			// Connect to the legacy SOAP interface.
			$this->gforgeLegacy = new GForgeLegacy('http://joomlacode.org/gf');
			$this->gforgeLegacy->login($username, $password);

			// Ensure we have project data in the database
			$this->checkProject($project);

			// Get the tracker data from the SOAP interface.
			$trackers = $this->gforge->getProjectTrackers($project);

			if (empty($trackers))
			{
				$this->setError('Unable to get trackers from the server.');
				return false;
			}

			// Sync each tracker.
			$trackers = array_reverse($trackers);

			foreach ($trackers as $tracker)
			{
				$currentTrackers = array(8103, 8549, 11410);

				if (in_array($tracker->tracker_id, $currentTrackers))
				{
					$this->populateTrackerFields($tracker->tracker_id);
					$this->syncTracker($tracker);
				}
			}

			$this->doStatusSnapshot();
		}
		catch (RuntimeException $e)
		{
			JLog::add('An error occurred during the sync: ' . $e->getMessage(), JLog::INFO);

			return false;
		}

		return true;
	}

	/**
	 * Check to verify the given project exists
	 *
	 * @param   integer  $id  Project ID
	 *
	 * @return  boolean
	 */
	private function checkProject($id)
	{
		// Get a tracker table object.
		$table = $this->getTable('Project', 'CodeTable');

		// Load any existing data by legacy id.
		$table->loadByLegacyId($id);

		// If the tracker ID is null, assume we're inserting a new record
		if ($table->project_id === null)
		{
			// Retrieve the project data
			$project = $this->gforge->getProjectById($id);

			$data = array(
				'title' => $project->project_name,
				'description' => $project->description,
				'jc_project_id' => $project->project_id
			);

			// Bind the data to the project object.
			$table->bind($data);

			// Attempt to store the project data.
			if (!$table->store())
			{
				$this->setError($table->getError());
				return false;
			}
		}
	}

	/**
	 * Synchronize the given tracker
	 *
	 * @param   object  $tracker  Tracker data object
	 *
	 * @return  boolean
	 */
	private function syncTracker($tracker)
	{
		// Get a tracker table object.
		$table = $this->getTable('Tracker', 'CodeTable');

		// Load any existing data by legacy id.
		$table->loadByLegacyId($tracker->tracker_id);

		$data = array();

		// If the tracker ID is null, assume we're inserting a new record
		if ($table->tracker_id === null)
		{
			$data = array(
				'jc_tracker_id' => $tracker->tracker_id,
				'jc_project_id' => $tracker->project_id,
				'title' => $tracker->tracker_name,
				'description' => $tracker->description
			);
		}

		// Populate the appropriate fields from the server data object.
		$data['item_count'] = $tracker->item_total;
		$data['open_item_count'] = $tracker->open_count;

		// Bind the data to the tracker object.
		$table->bind($data);

		// Attempt to store the tracker data.
		if (!$table->store())
		{
			$this->setError($table->getError());
			return false;
		}

		// Get the tracker item data from the SOAP interface.
		$items = $this->gforge->getTrackerItems($tracker->tracker_id);

		if (empty($items))
		{
			$this->setError('Unable to get tracker items from the server for tracker: ' . $tracker->summary);
			return false;
		}

		// Date for testing whether to sync or not
		$cutoffDate = new DateTime;
		$cutoffDate->sub(new DateInterval('P1Y'));

		$totalCount     = count($items);
		$skippedCount   = 0;
		$processedCount = 0;

		// Sync each tracker item.
		for ($i = 0; $i < $totalCount; $i++)
		{
			$total = $i + 1;
			$item  = $items[$i];

			// Exclude items closed > 1 year
			$closeDate = new DateTime($item->close_date);

			if (isset($item->close_date) && $closeDate < $cutoffDate)
			{
				$skippedCount++;
			}
			else
			{
				$this->syncTrackerItem($item, $tracker->tracker_id, $tracker->project_id, $table->tracker_id, $table->project_id);
				$processedCount++;
			}
		}

		JLog::add('Tracker: ' . $tracker->tracker_id . '; Skipped: ' . $skippedCount . ';  Processed issues: ' . $processedCount . ';  Total: ' . $total);
		$logMessage = 'Issues: ' . $this->processingTotals['issues'] . ';  Changes: ' . $this->processingTotals['changes'] . ';';
		$logMessage .= '  Files: ' . $this->processingTotals['files'] . ';  Messages: ' . $this->processingTotals['messages'] . ' ;';
		$logMessage .= '  Users: ' . $this->processingTotals['users'] . ' ;';
		JLog::add($logMessage);

		$this->syncTrackers[] = $tracker->tracker_id;

		return true;
	}

	public function syncIssue($issueId, $trackerId)
	{
		// Initialize variables.
		$username = JFactory::getConfig()->get('gforgeLogin');
		$password = JFactory::getConfig()->get('gforgePassword');
		$project  = 5; // Joomla project id.

		// Connect to the main SOAP interface.
		$this->gforge = new GForge('http://joomlacode.org/gf');
		$this->gforge->login($username, $password);

		// Connect to the legacy SOAP interface.
		$this->gforgeLegacy = new GForgeLegacy('http://joomlacode.org/gf');
		$this->gforgeLegacy->login($username, $password);

		/*
		 * Get the tracker from the GForge server.
		 */
		$tracker = $this->gforge->getTracker($trackerId);

		// If a tracker wasn't found return false.
		if (!is_object($tracker)) {
			return false;
		}

		// Synchronize the tracker fields.
		$this->populateTrackerFields($tracker->tracker_id);

		// Get a tracker table object.
		$table = $this->getTable('Tracker', 'CodeTable');

		// Load any existing data by legacy id.
		$table->loadByLegacyId($tracker->tracker_id);

		// Populate the appropriate fields from the server data object.
		$data = array(
			'item_count' => $tracker->item_total,
			'open_item_count' => $tracker->open_count,
		);

		// Bind the data to the tracker object.
		$table->bind($data);

		// Attempt to store the tracker data.
		if (!$table->store()) {
			$this->setError($table->getError());
			return false;
		}

		// Create the mock item object for use in the
		$item = (object) array('tracker_item_id' => $issueId);

		return $this->syncTrackerItem($item, $trackerId, $tracker->project_id, $table->tracker_id, $table->project_id);
	}

	/**
	 * @param   object   $item             The tracker item to update
	 * @param   integer  $legacyTrackerId  The legacy tracker ID
	 * @param   integer  $legacyProjectId  The legacy project ID
	 * @param   integer  $trackerId        The system's tracker ID
	 * @param   integer  $projectId        The system's project ID
	 *
	 * @return  bool
	 */
	private function syncTrackerItem($item, $legacyTrackerId, $legacyProjectId, $trackerId, $projectId)
	{
		// Get the database object
		$db = $this->getDbo();

		// Build the query to see if the item already exists.
		$db->setQuery(
			$db->getQuery(true)
				->select($db->quoteName(array('issue_id', 'modified_date', 'status')))
				->from($db->quoteName('#__code_tracker_issues'))
				->where($db->quoteName('jc_issue_id') . ' = '. (int) $item->tracker_item_id)
		);

		// Execute the query to find out if the item exists.
		$exists = $db->loadObject();

		// Get full data on the tracker item from the GForge server.
		$item = $this->gforge->getTrackerItem($item->tracker_item_id);

		// If a tracker item wasn't found return false.
		if (!is_object($item))
		{
			return false;
		}

		// No need to process an issue that hasn't changed.
		if (!empty($exists->status) && !empty($exists->issue_id) && ($exists->modified_date == $item->last_modified_date))
		{
			return true;
		}

		// Get accessory data on the tracker item from the GForge server.
		$changes = $this->gforge->getTrackerItemChanges($item->tracker_item_id);
		$files   = $this->gforgeLegacy->getTrackerItemFiles($item->tracker_item_id, $legacyTrackerId, $legacyProjectId);

		/*
		 * Synchronize all users relevant to the tracker item.
		 */

		// Get a list of all of the user ids to look up.
		$usersToLookUp = array($item->submitted_by, $item->last_modified_by);

		// Add each user ID that submitted a response to the list.
		foreach ($item->messages as $message)
		{
			$usersToLookUp[] = $message->submitted_by;
		}

		// Add each user ID that committed a code change to the list.
		/* foreach ($item->scm_commits as $commit)
		{
			$usersToLookUp[] = $commit->user_id;
		}*/

		// Add each user ID that is assigned to the list.
		foreach ($item->assignees as $assignee)
		{
			$usersToLookUp[] = $assignee->assignee;
		}

		// Add each user ID that submitted a file to the list.
		foreach ($files as $file)
		{
			$usersToLookUp[] = $file->submitted_by;
		}

		// Add each user ID that made a change to the list.
		foreach ($changes as $change)
		{
			$usersToLookUp[] = $change->user_id;
		}

		// Remove any duplicates.
		$usersToLookUp = array_values(array_unique($usersToLookUp));

		// Get rid of user id 0
		sort($usersToLookUp);

		if ($usersToLookUp[0] == 0)
		{
			array_shift($usersToLookUp);
		}

		// Get the syncronized user ids.
		$users = $this->syncUsers($usersToLookUp);

		if ($users === false)
		{
			return false;
		}

		/*
		 * Synchronize the tracker issue table.
		 */

		// Get a tracker issue table object.
		$table = $this->getTable('TrackerIssue', 'CodeTable');

		// Load any existing data by legacy id.
		$table->loadByLegacyId($item->tracker_item_id);

		// Populate the appropriate fields from the server data object.
		$data = array(
			'tracker_id'     => $trackerId,
			'project_id'     => $projectId,
			'build_id'       => 0,
			'state'          => $item->status_id,
			'priority'       => $item->priority,
			'created_date'   => $item->open_date,
			'created_by'     => $users[$item->submitted_by],
			'modified_date'  => $item->last_modified_date,
			'modified_by'    => @$users[$item->last_modified_by],
			'close_date'     => $item->close_date,
			'title'          => $item->summary,
			'alias'          => '',
			'description'    => $item->details,
			'jc_issue_id'    => $item->tracker_item_id,
			'jc_tracker_id'  => $legacyTrackerId,
			'jc_project_id'  => $legacyProjectId,
			'jc_created_by'  => $item->submitted_by,
			'jc_modified_by' => $item->last_modified_by
		);

		// Only populate the close by data if necessary.
		if ($item->close_date && @$users[$item->last_modified_by])
		{
			$data['close_by'] = $users[$item->last_modified_by];
			$data['jc_close_by'] = $item->last_modified_by;
		}

		if (!isset($item->close_date))
		{
			$data['close_date'] = $db->getNullDate();
		}

		// Bind the data to the issue object.
		$table->bind($data);

		// Attempt to store the issue data.
		if (!$table->store(true))
		{
			$this->setError($table->getError());

			return false;
		}

		$this->processingTotals['issues']++;

		if (!isset($exists->status))
		{
			if (!$this->addCreateActivities($data))
			{
				return false;
			}
		}

		// Synchronize the assignees associated with the tracker item.
		if (is_array($item->assignees))
		{
			if (!$this->syncTrackerItemAssignments($item->assignees, $users, $table->issue_id, $table->jc_issue_id))
			{
				return false;
			}
		}

		// Synchronize the files associated with the tracker item.
		if (is_array($files))
		{
			if (!$this->syncTrackerItemFiles($files, $users, $table->issue_id, $table->tracker_id, $table->jc_issue_id, $table->jc_tracker_id))
			{
				return false;
			}
		}

		// Synchronize the messages associated with the tracker item.
		if (is_array($item->messages))
		{
			if (!$this->syncTrackerItemMessages($item->messages, $users, $table->issue_id, $table->tracker_id, $table->jc_issue_id, $table->jc_tracker_id))
			{
				return false;
			}
		}

		// Synchronize the changes associated with the tracker item.
		if (is_array($changes))
		{
			if (!$this->syncTrackerItemChanges($changes, $users, $table->issue_id, $table->tracker_id, $table->jc_issue_id, $table->jc_tracker_id))
			{
				return false;
			}
		}

		// Synchronize the commits associated with the tracker item.
		/* if (is_array($item->scm_commits))
		{
			if (!$this->syncTrackerItemCommits($item->scm_commits, $users, $table->issue_id, $table->tracker_id, $table->jc_issue_id, $table->jc_tracker_id))
			{
				return false;
			}
		}*/

		// Synchronize the extra fields for the tracker item.
		if (is_array($item->extra_field_data))
		{
			if (!$this->syncTrackerItemExtraFields($item->extra_field_data, $table->issue_id))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Synchronize a tracker item's extra fields
	 *
	 * @param   array    $fieldValues  Array of field data
	 * @param   integer  $issueId      Issue ID
	 *
	 * @return  boolean  True on success
	 */
	private function syncTrackerItemExtraFields($fieldValues, $issueId)
	{
		// Some GForge tracker fields we don't care about as far as tags are concerned.
		$ignore = array(
			'duration',
			'percentcomplete',
			'estimatedeffort',
			'build'
		);

		// Get the list of relevant tags.
		$db   = $this->getDbo();
		$tags = array();

		foreach ($fieldValues as $value)
		{
			// Ignore some fields we don't care about.
			if (in_array($this->fields[$value->tracker_extra_field_id]['alias'], $ignore))
			{
				continue;
			}
			// Special case for status.
			elseif ($this->fields[$value->tracker_extra_field_id]['alias'] == 'status')
			{
				// Make sure we have a status for it.
				if (isset($this->fieldValues[$value->field_data]) && isset($this->status[$this->fieldValues[$value->field_data]['value_id']]))
				{
					// Set the status value/name for the issue.
					$db->setQuery(
						$db->getQuery(true)
							->update($db->quoteName('#__code_tracker_issues'))
							->set($db->quoteName('status') . ' = ' . (int) $this->status[$this->fieldValues[$value->field_data]['value_id']])
							->set($db->quoteName('status_name') . ' = ' . $db->quote($this->fieldValues[$value->field_data]['name']))
							->where($db->quoteName('issue_id') . ' = ' . (int) $issueId)
					);

					// Check for an error.
					try
					{
						$db->execute();
					}
					catch (RuntimeException $e)
					{
						$this->setError($e->getMessage());

						return false;
					}
				}

				continue;
			}

			if (!empty($this->fieldValues[$value->field_data]))
			{
				$tags[] = $this->fieldValues[$value->field_data]['name'];
			}
		}

		// If there are no tags, move on.
		if (empty($tags))
		{
			return true;
		}

		// Make sure the tags we need are synced.
		if (!$tags = $this->syncTags($tags))
		{
			return false;
		}

		// Get the current tag maps for the issue.
		$db->setQuery(
			$db->getQuery(true)
				->select($db->quoteName('tag_id'))
				->from($db->quoteName('#__code_tracker_issue_tag_map'))
				->where($db->quoteName('issue_id') . ' = ' . (int) $issueId)
		);

		$existing = (array) $db->loadColumn();
		JArrayHelper::toInteger($existing);

		// Get the list of tag maps to add and delete.
		$add = array_diff(array_keys($tags), $existing);
		$del = array_diff($existing, array_keys($tags));

		// Delete the necessary tag maps.
		if (!empty($del))
		{
			$db->setQuery(
				$db->getQuery(true)
					->delete($db->quoteName('#__code_tracker_issue_tag_map'))
					->where($db->quoteName('issue_id') . ' = ' . (int) $issueId)
					->where($db->quoteName('tag_id') . ' IN (' . implode(',', $del) . ')')
			);

			// Check for an error.
			try
			{
				$db->execute();
			}
			catch (RuntimeException $e)
			{
				$this->setError($e->getMessage());

				return false;
			}
		}

		// Add the necessary tag maps.
		foreach ($add as $tag)
		{
			// Insert the new tag map.
			$db->setQuery(
				$db->getQuery(true)
					->insert($db->quoteName('#__code_tracker_issue_tag_map'))
					->columns(array($db->quoteName('issue_id'), $db->quoteName('tag_id'), $db->quoteName('tag')))
					->values((int) $issueId . ', ' . (int) $tag . ', ' . $db->quote($tags[$tag]))
			);

			// Check for an error.
			try
			{
				$db->execute();
			}
			catch (RuntimeException $e)
			{
				$this->setError($e->getMessage());

				return false;
			}
		}

		return true;
	}

	/**
	 * Synchronizes tracker item assignments
	 *
	 * @param   array    $assignments    Array of assignment information for an item
	 * @param   array    $users          Array of users
	 * @param   integer  $issueId        Issue ID
	 * @param   integer  $legacyIssueId  Legacy issue ID
	 *
	 * @return  boolean  True on success
	 */
	private function syncTrackerItemAssignments($assignments, $users, $issueId, $legacyIssueId)
	{
		$db = $this->getDbo();

		// Get the list of user assignments.
		$ids = array();

		foreach ($assignments as $assignment)
		{
			// Ignore the nobody user.
			if ($assignment->assignee == 100)
			{
				continue;
			}

			$ids[] = (int) $assignment->assignee;
		}

		// Remove assignments that don't belong.
		if (empty($ids))
		{
			$db->setQuery(
				$db->getQuery(true)
					->delete($db->quoteName('#__code_tracker_issue_assignments'))
					->where($db->quoteName('issue_id') . ' = ' . (int) $issueId)
			);
		}
		else
		{
			$db->setQuery(
				$db->getQuery(true)
					->delete($db->quoteName('#__code_tracker_issue_assignments'))
					->where($db->quoteName('issue_id') . ' = ' . (int) $issueId)
					->where($db->quoteName('jc_user_id') . ' NOT IN (' . implode(',', $ids) . ')')
			);
		}

		// Check for an error.
		try
		{
			$db->execute();
		}
		catch (RuntimeException $e)
		{
			$this->setError($e->getMessage());

			return false;
		}

		// Look up the existing local assignments.
		$db->setQuery(
			$db->getQuery(true)
				->select($db->quoteName('jc_user_id'))
				->from($db->quoteName('#__code_tracker_issue_assignments'))
				->where($db->quoteName('issue_id') . ' = ' . (int) $issueId)
		);

		$existing = $db->loadColumn();

		// Get the list of assignments to insert as a diff from what we need vs what we have.
		$inserts = array_diff($ids, $existing);

		foreach ($inserts as $insert)
		{
			// Insert the new assignment.
			$db->setQuery(
				$db->getQuery(true)
					->insert($db->quoteName('#__code_tracker_issue_assignments'))
					->columns(array($db->quoteName('issue_id'), $db->quoteName('user_id'), $db->quoteName('jc_issue_id'), $db->quoteName('jc_user_id')))
					->values((int) $issueId . ', ' . (int) @$users[$insert] . ', ' . (int) $legacyIssueId . ', ' . (int) $insert)
			);

			// Check for an error.
			try
			{
				$db->execute();
			}
			catch (RuntimeException $e)
			{
				$this->setError($e->getMessage());

				return false;
			}
		}

		return true;
	}

	/**
	 * Synchronize a tracker item's changes
	 *
	 * @param   array    $commits          Array of commit data
	 * @param   array    $users            Array of user IDs
	 * @param   string   $issueId          Issue ID
	 * @param   integer  $trackerId        Tracker ID
	 * @param   integer  $legacyIssueId    Legacy issue ID
	 * @param   integer  $legacyTrackerId  Legacy tracker ID
	 *
	 * @return  boolean  True on success
	 */
	private function syncTrackerItemCommits($commits, $users, $issueId, $trackerId, $legacyIssueId, $legacyTrackerId)
	{
		// Synchronize each commit.
		foreach ($commits as $commit)
		{
			// Get a tracker issue commit table object.
			$table = $this->getTable('TrackerIssueCommit', 'CodeTable');

			// Load any existing data by legacy id.
			$table->loadByLegacyId($commit->scm_commit_id);

			// Skip over rows that exist and haven't changed.
			if ($table->commit_id && $table->created_date == $commit->commit_date)
			{
				continue;
			}

			// Populate the appropriate fields from the server data object.
			$data = array(
				'issue_id'      => $issueId,
				'tracker_id'    => $trackerId,
				'created_date'  => $commit->commit_date,
				'created_by'    => $users[$commit->user_id],
				'message'       => $commit->message_log,
				'jc_commit_id'  => $commit->scm_commit_id,
				'jc_issue_id'   => $legacyIssueId,
				'jc_tracker_id' => $legacyTrackerId,
				'jc_created_by' => $commit->user_id
			);

			// Bind the data to the object.
			$table->bind($data);

			// Attempt to store the data.
			if (!$table->store())
			{
				$this->setError($table->getError());
				return false;
			}
		}

		return true;
	}

	/**
	 * Synchronize a tracker item's changes
	 *
	 * @param   array    $changes          Array of change data
	 * @param   array    $users            Array of user IDs
	 * @param   string   $issueId          Issue ID
	 * @param   integer  $trackerId        Tracker ID
	 * @param   integer  $legacyIssueId    Legacy issue ID
	 * @param   integer  $legacyTrackerId  Legacy tracker ID
	 *
	 * @return  boolean  True on success
	 */
	private function syncTrackerItemChanges($changes, $users, $issueId, $trackerId, $legacyIssueId, $legacyTrackerId)
	{
		// Synchronize each change.
		foreach ($changes as $change)
		{
			// Ignore non-status changes for now.
			if ($change->field_name != 'status')
			{
				continue;
			}

			// Get a tracker issue change table object.
			$table = $this->getTable('TrackerIssueChange', 'CodeTable');

			// Load any existing data by legacy id.
			$table->loadByLegacyId($change->audit_trail_id);

			// Skip over rows that exist and haven't changed.
			if ($table->change_id && $table->change_date == $change->change_date)
			{
				continue;
			}

			// Populate the appropriate fields from the server data object.
			$data = array(
				'issue_id'      => $issueId,
				'tracker_id'    => $trackerId,
				'change_date'   => $change->change_date,
				'change_by'     => $users[$change->user_id],
				'data'          => serialize($change),
				'jc_change_id'  => $change->audit_trail_id,
				'jc_issue_id'   => $legacyIssueId,
				'jc_tracker_id' => $legacyTrackerId,
				'jc_change_by'  => $change->user_id
			);

			// Bind the data to the object.
			$table->bind($data);

			// Attempt to store the data.
			if (!$table->store())
			{
				$this->setError($table->getError());
				return false;
			}

			if (!$this->addActivity(3, $data['jc_issue_id'], $data['jc_change_by'], $data['jc_issue_id'], $data['change_date']))
			{
				return false;
			}

			$this->processingTotals['changes']++;
		}

		return true;
	}

	/**
	 * Synchronize a tracker item's messages
	 *
	 * @param   array    $messages         Array of message data
	 * @param   array    $users            Array of user IDs
	 * @param   string   $issueId          Issue ID
	 * @param   integer  $trackerId        Tracker ID
	 * @param   integer  $legacyIssueId    Legacy issue ID
	 * @param   integer  $legacyTrackerId  Legacy tracker ID
	 *
	 * @return  boolean  True on success
	 */
	private function syncTrackerItemMessages($messages, $users, $issueId, $trackerId, $legacyIssueId, $legacyTrackerId)
	{
		// Synchronize each message.
		foreach ($messages as $message)
		{
			// Get a tracker issue response table object.
			$table = $this->getTable('TrackerIssueResponse', 'CodeTable');

			// Load any existing data by legacy id.
			$table->loadByLegacyId($message->tracker_item_message_id);

			// Skip over rows that exist and haven't changed.
			if ($table->response_id && $table->created_date == $message->adddate)
			{
				continue;
			}

			// Populate the appropriate fields from the server data object.
			$data = array(
				'issue_id'       => $issueId,
				'tracker_id'     => $trackerId,
				'created_date'   => $message->adddate,
				'created_by'     => $users[$message->submitted_by],
				'body'           => $message->body,
				'jc_response_id' => $message->tracker_item_message_id,
				'jc_issue_id'    => $legacyIssueId,
				'jc_tracker_id'  => $legacyTrackerId,
				'jc_created_by'  => $message->submitted_by
			);

			// Bind the data to the object.
			$table->bind($data);

			// Attempt to store the data.
			if (!$table->store())
			{
				$this->setError($table->getError());

				return false;
			}

			if (!$this->addCommentActivity($data))
			{
				return false;
			}

			$this->processingTotals['messages']++;
		}

		return true;
	}

	/**
	 * Synchronize a tracker item's files
	 *
	 * @param   array    $files            Array of file data
	 * @param   array    $users            Array of user IDs
	 * @param   string   $issueId          Issue ID
	 * @param   integer  $trackerId        Tracker ID
	 * @param   integer  $legacyIssueId    Legacy issue ID
	 * @param   integer  $legacyTrackerId  Legacy tracker ID
	 *
	 * @return  boolean  True on success
	 */
	private function syncTrackerItemFiles($files, $users, $issueId, $trackerId, $legacyIssueId, $legacyTrackerId)
	{
		// Synchronize each file.
		foreach ($files as $file)
		{
			// Get a tracker issue file table object.
			$table = $this->getTable('TrackerIssueFile', 'CodeTable');

			// Load any existing data by legacy id.
			$table->loadByLegacyId($file->id);

			// Skip over rows that exist and haven't changed.
			if ($table->file_id)
			{
				continue;
			}

			// Populate the appropriate fields from the server data object.
			$data = array(
				'issue_id'      => $issueId,
				'tracker_id'    => $trackerId,
				'created_date'  => $file->adddate ? $file->adddate : date('Y-m-d'),
				'created_by'    => $users[$file->submitted_by],
				'name'          => $file->name,
				'description'   => $file->description,
				'size'          => $file->filesize,
				'type'          => $file->filetype,
				'jc_file_id'    => $file->id,
				'jc_issue_id'   => $legacyIssueId,
				'jc_tracker_id' => $legacyTrackerId,
				'jc_created_by' => $file->submitted_by
			);

			// Bind the data to the object.
			$table->bind($data);

			// Attempt to store the data.
			if (!$table->store())
			{
				$this->setError($table->getError());

				return false;
			}

			if (!$this->addFileActivity($data))
			{
				return false;
			}

			$this->processingTotals['files']++;
		}

		return true;
	}

	/**
	 * Method to make sure a set of tag values are syncronized with the local system.  This
	 * method will return an associative array of tag_id => tag values.
	 *
	 * @param   array  $values  An array of tag values to make sure exist in the local system.
	 *
	 * @return  array  An array of tag_id => tag values.
	 *
	 * @since   1.0
	 */
	private function syncTags($values)
	{
		// Initialize variables.
		$tags = array();
		$ors  = array();
		$db   = $this->getDbo();

		foreach ($values as $k => $value)
		{
			$ors[$k] = $db->quote($value);
		}

		// Build the query to see if the items already exist.
		$db->setQuery(
			$db->getQuery(true)
				->select($db->quoteName(array('tag_id', 'tag')))
				->from($db->quoteName('#__code_tags'))
				->where($db->quoteName('tag') . ' = ' . implode(' OR tag = ', $ors))
		);

		// Execute the query to find out if the items exist.
		$exists = (array) $db->loadObjectList();

		// Build out the array of tags based on those that already exist.
		foreach ($exists as $exist)
		{
			$tags[(int) $exist->tag_id] = $exist->tag;
		}

		// Get the list of tags to store.
		$store = array_diff(array_values($values), array_values($tags));

		if (empty($store))
		{
			return $tags;
		}

		// Store the values.
		foreach ($store as $value)
		{
			// Insert the new tag.
			$db->setQuery(
				$db->getQuery(true)
					->insert($db->quoteName('#__code_tags'))
					->columns(array($db->quoteName('tag')))
					->values($db->quote($value))
			);

			// Check for an error.
			try
			{
				$db->execute();
			}
			catch (RuntimeException $e)
			{
				$this->setError($e->getMessage());

				return false;
			}

			$tags[(int) $db->insertid()] = $value;
		}

		return $tags;
	}

	/**
	 * Method to make sure a set of legacy user ids are syncronized with the GForge server.  This
	 * method will return an associative array of legacy => local user id values.
	 *
	 * @param   array  $ids  An array of legacy GForge user ids.
	 *
	 * @return  array  An array of legacy => local user ids.
	 *
	 * @since   1.0
	 */
	private function syncUsers($ids)
	{
		// Initialize variables.
		$db    = $this->getDbo();
		$users = array();

		// Ensure the ids are integers.
		JArrayHelper::toInteger($ids);

		// Build the query to see if the items already exist.
		$db->setQuery(
			$db->getQuery(true)
				->select($db->quoteName(array('user_id', 'jc_user_id')))
				->from($db->quoteName('#__code_users'))
				->where($db->quoteName('jc_user_id') . ' IN ('. implode(',', $ids) . ')')
		);

		// Execute the query to find out if the items exist.
		$exists = (array) $db->loadObjectList();

		// Build out the array of users based on those that already exist.
		foreach ($exists as $exist)
		{
			$users[$exist->jc_user_id] = (int) $exist->user_id;
		}

		// Get the list of user ids for user objects to extract data from the server.
		$get = array_diff($ids, array_keys($users));

		if (empty($get))
		{
			return $users;
		}

		// Get the list of user objects from the server.
		$got = $this->gforge->getUsersById($get);

		if (empty($got))
		{
			$this->setError('Unable to get users from the server.');
			return false;
		}

		// Sync each user.
		foreach ($got as $user)
		{
			// Get a user table object.
			$table = $this->getTable('User', 'CodeTable');

			// Load any existing data by email address.
			$table->loadByEmail($user->email);

			// Populate the appropriate fields from the server data object.
			$data = array(
				'jc_user_id'   => $user->user_id,
				'username'     => $user->unix_name,
				'email'        => $user->email,
				'registerDate' => $user->create_date,
				'first_name'   => $user->firstname,
				'last_name'    => $user->lastname
			);

			// Do a little state conversion.
			if ($user->status == 2)
			{
				$data['block'] = 1;
			}

			// Bind the data to the user object.
			$table->bind($data);

			// Attempt to store the user data.
			if (!$table->store())
			{
				$this->setError($table->getError());

				return false;
			}

			$this->processingTotals['users']++;

			$users[$table->jc_user_id] = (int) $table->id;
		}

		return $users;
	}

	/**
	 * Method to populate the tracker field array
	 *
	 * @param   integer  $trackerId  The tracker ID to populate
	 *
	 * @return  void
	 */
	private function populateTrackerFields($trackerId)
	{
		$fields = $this->gforge->getTrackerFields($trackerId);

		foreach ($fields as $field)
		{
			if (empty($this->fields[$field->tracker_extra_field_id]))
			{
				$this->fields[$field->tracker_extra_field_id] = array(
					'field_id'   => $field->tracker_extra_field_id,
					'name'       => $field->field_name,
					'alias'      => $field->alias,
					'tracker_id' => $field->tracker_id
				);

				if ($field->alias == 'status')
				{
					$this->populateTrackerStatus($this->fields[$field->tracker_extra_field_id], $trackerId);
				}
			}

			$this->populateTrackerFieldValues($this->fields[$field->tracker_extra_field_id], $trackerId);
		}
	}

	/**
	 * Populates the status table with data for the specified tracker
	 *
	 * @param   array    $field            The status field data
	 * @param   integer  $legacyTrackerId  The tracker ID being updated
	 *
	 * @return  boolean  True on success
	 */
	private function populateTrackerStatus($field, $legacyTrackerId)
	{
		// Get a tracker table object.
		$tracker = $this->getTable('Tracker', 'CodeTable');
		$tracker->loadByLegacyId($legacyTrackerId);

		$values = $this->gforge->getTrackerFieldValues($field['field_id']);

		foreach ($values as $value)
		{
			// Get a tracker issue file table object.
			$table = $this->getTable('TrackerStatus', 'CodeTable');

			// Load any existing data by legacy id.
			$table->loadByLegacyId($value->element_id);

			// Skip over rows that exist and haven't changed.
			if ($table->status_id && ($table->title == $value->element_name) && ($table->state_id == $value->status_id))
			{
				$this->status[(int) $value->element_id] = (int) $table->status_id;

				continue;
			}

			// Populate the appropriate fields from the server data object.
			$data = array(
				'tracker_id'    => $tracker->tracker_id,
				'state_id'      => $value->status_id,
				'title'         => $value->element_name,
				'jc_tracker_id' => $legacyTrackerId,
				'jc_status_id'  => $value->element_id
			);

			// Bind the data to the object.
			$table->bind($data);

			// Attempt to store the data.
			if (!$table->store())
			{
				$this->setError($table->getError());
				return false;
			}

			$this->status[(int) $value->element_id] = (int) $table->status_id;
		}

		return true;
	}

	/**
	 * Method to populate the field data array
	 *
	 * @param   array  $field  The field data to populate
	 *
	 * @return  void
	 */
	private function populateTrackerFieldValues($field)
	{
		$values = $this->gforge->getTrackerFieldValues($field['field_id']);

		foreach ($values as $value)
		{
			if (empty($this->fieldValues[$value->element_id]))
			{
				$this->fieldValues[$value->element_id] = array(
					'value_id' => $value->element_id,
					'field_id' => $value->tracker_extra_field_id,
					'name'     => $value->element_name
				);
			}
		}
	}

	/**
	 * Insert an activity record into the database
	 *
	 * @param   integer  $type     Activity ID
	 * @param   integer  $xref     Reference ID
	 * @param   integer  $userId   User ID
	 * @param   integer  $issueId  Issue ID
	 * @param   string   $date     Activity date
	 *
	 * @return  boolean
	 */
	private function addActivity($type, $xref, $userId, $issueId, $date)
	{
		$db = $this->getDbo();

		$query = 'INSERT IGNORE INTO #__code_activity_detail SET activity_type = ' . (int) $type
			. ', activity_xref_id = ' . (int) $xref
			. ', jc_user_id = ' . (int) $userId
			. ', jc_issue_id = ' . (int) $issueId
			. ', activity_date = ' . $db->quote($date);

		$db->setQuery($query);

		try
		{
			$db->execute();
		}
		catch (RuntimeException $e)
		{
			$this->setError($e->getMessage());

			return false;
		}

		return true;
	}

	/**
	 * Proxy to addActivity() for create activities
	 *
	 * @param   array  $data  Data array to process
	 *
	 * @return  boolean  True on success
	 */
	private function addCreateActivities($data)
	{
		if (!$this->addActivity(1, $data['jc_issue_id'], $data['jc_created_by'], $data['jc_issue_id'], $data['created_date']))
		{
			return false;
		}

		if (strpos($data['description'], "/pull/") !== false)
		{
			if (!$this->addActivity(7, $data['jc_issue_id'], $data['jc_created_by'], $data['jc_issue_id'], $data['created_date']))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Proxy to addActivity() for file activities
	 *
	 * @param   array  $data  Data array to process
	 *
	 * @return  boolean  True on success
	 */
	private function addFileActivity($data)
	{
		if (strpos($data['name'], 'diff') !== false || strpos($data['name'], 'patch') !== false)
		{
			if (!$this->addActivity(5, $data['jc_file_id'], $data['jc_created_by'], $data['jc_issue_id'], $data['created_date']))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Proxy to addActivity() for comment activities
	 *
	 * @param   array  $data  Data array to process
	 *
	 * @return  boolean  True on success
	 */
	private function addCommentActivity($data)
	{
		if (!$this->addActivity(2, $data['jc_response_id'], $data['jc_created_by'], $data['jc_issue_id'], $data['created_date']))
		{
			return false;
		}

		if (strpos($data['body'], "/pull/") !== false || strpos($data['body'], "/compare/") !== false || strpos($data['body'], ".diff") !== false)
		{
			if (!$this->addActivity(6, $data['jc_response_id'], $data['jc_created_by'], $data['jc_issue_id'], $data['created_date']))
			{
				return false;
			}
		}

		if (strpos($data['body'], "@test") !== false)
		{
			if (!$this->addActivity(4, $data['jc_response_id'], $data['jc_created_by'], $data['jc_issue_id'], $data['created_date']))
			{
				return false;
			}
		}

		return true;
	}
}
