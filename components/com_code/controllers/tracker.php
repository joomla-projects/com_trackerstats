<?php
/**
 * @version		$Id: tracker.php 408 2010-06-18 18:06:48Z louis $
 * @package		Joomla.Site
 * @subpackage	com_code
 * @copyright	Copyright (C) 2009 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * The Joomla Code Tracker Controller
 *
 * @package		Joomla.Code
 * @subpackage	com_code
 * @since		1.0
 */
class CodeControllerTracker extends JControllerLegacy
{
	/**
	 * The display method should never be requested from the extended
	 * controller.  Throw an error page and exit gracefully.
	 *
	 * @return	void
	 * @since	1.0
	 */
	public function display()
	{
		JError::raiseError(404, 'Resource Not Found');
	}

	public function test()
	{
		// Verify the request token.
		$token = $this->input->getString('token', null, 'method');
		if ($token != '1q2w3e4r')
		{
			JError::raiseError(403, 'Access Forbidden');
		}

		$model = $this->getModel('TrackerSync');

		$model->test();
	}

	public function sync()
	{
		// Verify the request token.
		$token = $this->input->getString('token', null, 'method');
		if ($token != '1q2w3e4r')
		{
			JError::raiseError(403, 'Access Forbidden');
		}

		// Attempt to set some PHP runtime configuration options.
		ini_set('memory_limit', '128M');
		set_time_limit(0);

		$model = $this->getModel('TrackerSync');

		$model->sync();
		var_dump($model->getErrors());
	}
}
