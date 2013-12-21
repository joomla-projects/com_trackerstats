<?php
/**
 * Bootstrap for the cron job which synchronizes the Joomlacode data
 */

// We are a valid entry point.
const _JEXEC = 1;

// Load system defines
if (file_exists(dirname(__DIR__) . '/defines.php'))
{
	require_once dirname(__DIR__) . '/defines.php';
}

if (!defined('_JDEFINES'))
{
	define('JPATH_BASE', dirname(__DIR__));
	require_once JPATH_BASE . '/includes/defines.php';
}

// Joomla system checks.
@ini_set('magic_quotes_runtime', 0);
@ini_set('zend.ze1_compatibility_mode', '0');

// Get the framework.
require_once JPATH_LIBRARIES . '/import.legacy.php';

// Bootstrap the CMS libraries.
require_once JPATH_LIBRARIES . '/cms.php';

ini_set('display_errors', 1);

// Set error handling levels
JError::setErrorHandling(E_ERROR, 'echo');
JError::setErrorHandling(E_WARNING, 'echo');
JError::setErrorHandling(E_NOTICE, 'echo');

/**
 * A command line cron job to synchronize the Joomlacode data.
 */
class Code extends JApplicationCli
{
	/**
	 * Class constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		// Set the error reporting level
		$error_reporting = (int) JFactory::getConfig()->get('error_reporting');

		// Configure error reporting
		if ($error_reporting == 0)
		{
			error_reporting(0);
		}
		elseif ($error_reporting > 0)
		{
			// Verbose error reporting.
			error_reporting($error_reporting);
		}
	}

	/**
	 * Method to run the application routines.
	 *
	 * @return  void
	 */
	protected function doExecute()
	{
		try
		{
			$args = $this->input->args;

			$command = strtolower(array_shift($args));

			// Get the ID of the tracker to sync (unused)
			$trackerId = $this->input->get('tracker', null);

			switch ($command)
			{
				case 'sync' :
				case 'filefix' :
					// Define the component path.
					defined('JPATH_COMPONENT') or define('JPATH_COMPONENT', realpath(JPATH_BASE . '/components/com_code'));

					// Set the include paths for com_code models and tables.
					JModelLegacy::addIncludePath(realpath(JPATH_BASE . '/components/com_code/models'));
					JTable::addIncludePath(realpath(JPATH_BASE . '/administrator/components/com_code/tables'));

					// Get the tracker sync model.
					$model = JModelLegacy::getInstance('TrackerSync', 'CodeModel');

					// Run the syncronization routine.
					$result = $model->$command();

					if ($result === false)
					{
						$this->out('The command did not complete successfully, please check the log for details.');
					}

					break;

				case 'cleanup' :
					// Define the component path.
					defined('JPATH_COMPONENT') or define('JPATH_COMPONENT', realpath(JPATH_BASE . '/components/com_code'));

					// Set the include paths for com_code models and tables.
					JModelLegacy::addIncludePath(realpath(JPATH_BASE . '/components/com_code/models'));
					JTable::addIncludePath(realpath(JPATH_BASE . '/administrator/components/com_code/tables'));

					// Run the cleanup routine
					$this->cleanup();

					break;

				default :
					$this->out('A valid command was not specified.');

					break;
			}
		}
		catch (Exception $e)
		{
			$this->out('Exception caught: ' . $e->getMessage(), true);
			$this->out('Stack trace: ' . $e->getTraceAsString(), true);
			$this->close($e->getCode());
		}
	}

	/**
	 * Method to run cleanup on the database
	 *
	 * This method will purge all records which are orphaned from the correct "tree" (project and tracker associations)
	 *
	 * @return  void
	 */
	protected function cleanup()
	{
		// Initialize the logger
		$options['format'] = '{DATE}\t{TIME}\t{LEVEL}\t{CODE}\t{MESSAGE}';
		$options['text_file'] = 'database_cleanup.php';
		JLog::addLogger($options);
		JLog::add('Starting database cleanup', JLog::INFO);

		$db = JFactory::getDbo();

		/*
		 * Step 1 - Query for all orphaned records
		 */

		// Query for all trackers where tracker_id > 5 and there is no project_id associated
		$db->setQuery(
			$db->getQuery(true)
				->select($db->quoteName('tracker_id'))
				->from($db->quoteName('#__code_trackers'))
				->where($db->quoteName('tracker_id') . ' > 5')
				->where($db->quoteName('project_id') . ' = 0')
		);

		try
		{
			$trackers = $db->loadColumn();
		}
		catch (RuntimeException $e)
		{
			JLog::add('Could not retrieve tracker data - ' . $e->getMessage(), JLog::ERROR, 'dbcleanup');
			$this->out('Failed to complete cleanup properly, please review the log for details.', true);
			$this->close($e->getCode());
		}

		// Query for all issues assigned to orphaned trackers
		$db->setQuery(
			$db->getQuery(true)
				->select($db->quoteName(array('issue_id', 'jc_issue_id')))
				->from($db->quoteName('#__code_tracker_issues'))
				->where($db->quoteName('tracker_id') . ' IN (' . implode(',', $trackers) . ')')
		);

		try
		{
			$issues = $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			JLog::add('Could not retrieve issue data - ' . $e->getMessage(), JLog::ERROR, 'dbcleanup');
			$this->out('Failed to complete cleanup properly, please review the log for details.', true);
			$this->close($e->getCode());
		}

		// Build unique arrays of each value
		$issueIds   = array();
		$issueJcIds = array();

		foreach ($issues as $issue)
		{
			$issueIds[]   = $issue->issue_id;
			$issueJcIds[] = $issue->jc_issue_id;
		}

		$issueIds   = array_unique($issueIds);
		$issueJcIds = array_unique($issueJcIds);

		/*
		 * Step 2 - Remove orphaned status records
		 */

		// Query for all status records which are orphaned
		$db->setQuery(
			$db->getQuery(true)
				->select($db->quoteName('status_id'))
				->from($db->quoteName('#__code_tracker_status'))
				->where($db->quoteName('tracker_id') . ' = 0')
		);

		try
		{
			$statuses = $db->loadColumn();
		}
		catch (RuntimeException $e)
		{
			JLog::add('Could not retrieve status data - ' . $e->getMessage(), JLog::ERROR, 'dbcleanup');
			$this->out('Failed to complete cleanup properly, please review the log for details.', true);
			$this->close($e->getCode());
		}

		foreach ($statuses as $status)
		{
			/* @type  CodeTableTrackerstatus  $statusTable */
			$statusTable = JTable::getInstance('Trackerstatus', 'CodeTable', array('dbo' => $db));

			if (!$statusTable->delete($status))
			{
				JLog::add('Could not delete status record ' . $status->status_id . ' - ' . $e->getMessage(), JLog::ERROR, 'dbcleanup');
				$this->out('Failed to complete cleanup properly, please review the log for details.', true);
				$this->close($e->getCode());
			}
		}

		/*
		 * Step 3 - Remove orphaned activity detail records
		 */

		$db->setQuery(
			$db->getQuery(true)
				->delete($db->quoteName('#__code_activity_detail'))
				->where($db->quoteName('jc_issue_id') . ' IN (' . implode(',', $issueJcIds) . ')')
		);

		try
		{
			$db->execute();
		}
		catch (RuntimeException $e)
		{
			JLog::add('Error deleting detail records - ' . $e->getMessage(), JLog::ERROR, 'dbcleanup');
			$this->out('Failed to complete cleanup properly, please review the log for details.', true);
			$this->close($e->getCode());
		}

		/*
		 * Step 4 - Remove orphaned issue assignment records
		 */

		$db->setQuery(
			$db->getQuery(true)
				->delete($db->quoteName('#__code_tracker_issue_assignments'))
				->where($db->quoteName('issue_id') . ' IN (' . implode(',', $issueIds) . ')')
		);

		try
		{
			$db->execute();
		}
		catch (RuntimeException $e)
		{
			JLog::add('Error deleting assignment records - ' . $e->getMessage(), JLog::ERROR, 'dbcleanup');
			$this->out('Failed to complete cleanup properly, please review the log for details.', true);
			$this->close($e->getCode());
		}

		/*
		 * Step 5 - Remove orphaned file records
		 */

		// Query for all file records which are orphaned
		$db->setQuery(
			$db->getQuery(true)
				->select($db->quoteName('file_id'))
				->from($db->quoteName('#__code_tracker_issue_files'))
				->where($db->quoteName('issue_id') . ' IN (' . implode(',', $issueIds) . ')')
		);

		try
		{
			$files = $db->loadColumn();
		}
		catch (RuntimeException $e)
		{
			JLog::add('Could not retrieve status data - ' . $e->getMessage(), JLog::ERROR, 'dbcleanup');
			$this->out('Failed to complete cleanup properly, please review the log for details.', true);
			$this->close($e->getCode());
		}

		foreach ($files as $file)
		{
			/* @type  CodeTableTrackerissuefile  $fileTable */
			$fileTable = JTable::getInstance('Trackerissuefile', 'CodeTable', array('dbo' => $db));

			if (!$fileTable->delete($file))
			{
				JLog::add('Could not delete file record ' . $file->file_id . ' - ' . $e->getMessage(), JLog::ERROR, 'dbcleanup');
				$this->out('Failed to complete cleanup properly, please review the log for details.', true);
				$this->close($e->getCode());
			}
		}

		/*
		 * Step 6 - Remove orphaned response records
		 */

		// Query for all response records which are orphaned
		$db->setQuery(
			$db->getQuery(true)
				->select($db->quoteName('response_id'))
				->from($db->quoteName('#__code_tracker_issue_responses'))
				->where($db->quoteName('issue_id') . ' IN (' . implode(',', $issueIds) . ')')
		);

		try
		{
			$responses = $db->loadColumn();
		}
		catch (RuntimeException $e)
		{
			JLog::add('Could not retrieve response data - ' . $e->getMessage(), JLog::ERROR, 'dbcleanup');
			$this->out('Failed to complete cleanup properly, please review the log for details.', true);
			$this->close($e->getCode());
		}

		foreach ($responses as $response)
		{
			/* @type  CodeTableTrackerissueresponse  $responseTable */
			$responseTable = JTable::getInstance('Trackerissueresponse', 'CodeTable', array('dbo' => $db));

			if (!$responseTable->delete($response))
			{
				JLog::add('Could not delete response record ' . $response->response_id . ' - ' . $e->getMessage(), JLog::ERROR, 'dbcleanup');
				$this->out('Failed to complete cleanup properly, please review the log for details.', true);
				$this->close($e->getCode());
			}
		}

		/*
		 * Step 7 - Remove orphaned change records
		 */

		// Query for all change records which are orphaned
		$db->setQuery(
			$db->getQuery(true)
				->select($db->quoteName('change_id'))
				->from($db->quoteName('#__code_tracker_issue_changes'))
				->where($db->quoteName('issue_id') . ' IN (' . implode(',', $issueIds) . ')')
		);

		try
		{
			$changes = $db->loadColumn();
		}
		catch (RuntimeException $e)
		{
			JLog::add('Could not retrieve change data - ' . $e->getMessage(), JLog::ERROR, 'dbcleanup');
			$this->out('Failed to complete cleanup properly, please review the log for details.', true);
			$this->close($e->getCode());
		}

		foreach ($changes as $change)
		{
			/* @type  CodeTableTrackerissuechange  $changeTable */
			$changeTable = JTable::getInstance('Trackerissuechange', 'CodeTable', array('dbo' => $db));

			if (!$changeTable->delete($change))
			{
				JLog::add('Could not delete change record ' . $change->change_id . ' - ' . $e->getMessage(), JLog::ERROR, 'dbcleanup');
				$this->out('Failed to complete cleanup properly, please review the log for details.', true);
				$this->close($e->getCode());
			}
		}

		/*
		 * Step 8 - Remove orphaned issue map records
		 */

		$db->setQuery(
			$db->getQuery(true)
				->delete($db->quoteName('#__code_tracker_issue_tag_map'))
				->where($db->quoteName('issue_id') . ' IN (' . implode(',', $issueIds) . ')')
		);

		try
		{
			$db->execute();
		}
		catch (RuntimeException $e)
		{
			JLog::add('Error deleting map records - ' . $e->getMessage(), JLog::ERROR, 'dbcleanup');
			$this->out('Failed to complete cleanup properly, please review the log for details.', true);
			$this->close($e->getCode());
		}

		/*
		 * Step 9 - Remove orphaned issue records
		 */

		foreach ($issueIds as $issueId)
		{
			/* @type  CodeTableTrackerissue  $issueTable */
			$issueTable = JTable::getInstance('Trackerissue', 'CodeTable', array('dbo' => $db));

			if (!$issueTable->delete($issueId))
			{
				JLog::add('Could not delete issue record ' . $issueTable->issue_id . ' - ' . $e->getMessage(), JLog::ERROR, 'dbcleanup');
				$this->out('Failed to complete cleanup properly, please review the log for details.', true);
				$this->close($e->getCode());
			}
		}

		/*
		 * Step 10 - Remove orphaned tracker records
		 */

		foreach ($trackers as $tracker)
		{
			/* @type  CodeTableTracker  $trackerTable */
			$trackerTable = JTable::getInstance('Tracker', 'CodeTable', array('dbo' => $db));

			if (!$trackerTable->delete($tracker))
			{
				JLog::add('Could not delete tracker record ' . $trackerTable->tracker_id . ' - ' . $e->getMessage(), JLog::ERROR, 'dbcleanup');
				$this->out('Failed to complete cleanup properly, please review the log for details.', true);
				$this->close($e->getCode());
			}
		}

		// Finished!
		$this->out('Successfully removed records.', true);
	}
}

// Instantiate the application object, passing the class name to JApplicationCli::getInstance and use chaining to execute the application.
JApplicationCli::getInstance('Code')->execute();
