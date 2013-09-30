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
					$model->$command();

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
}

// Instantiate the application object, passing the class name to JApplicationCli::getInstance and use chaining to execute the application.
JApplicationCli::getInstance('Code')->execute();
