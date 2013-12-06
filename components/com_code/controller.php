<?php
/**
 * @version		$Id: controller.php 456 2010-10-07 17:56:30Z louis $
 * @package		Joomla.Site
 * @subpackage	com_code
 * @copyright	Copyright (C) 2009 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Code Component Controller
 *
 * @package		Joomla.Site
 * @subpackage	com_code
 * @since		1.6
 */
class CodeController extends JControllerLegacy
{
	/**
	 * Display the view
	 *
	 * @param   boolean  $cachable   If true, the view output will be cached
	 * @param   array    $urlparams  An array of safe url parameters and their variable types, for valid values see {@link JFilterInput::clean()}.
	 *
	 * @return  CodeController
	 */
	public function display($cachable = false, $urlparams = array())
	{
		// Set the default view name and format from the Request.
		$vName = $this->input->getWord('view', 'summary');
		$this->input->setVar('view', $vName);

		$cachable = true;

		$safeurlparams = array(
			'catid' => 'INT',
			'id' => 'INT',
			'cid' => 'ARRAY',
			'year' => 'INT',
			'month' => 'INT',
			'limit' => 'INT',
			'limitstart' => 'INT',
			'showall' => 'INT',
			'return' => 'BASE64',
			'filter' => 'STRING',
			'filter_order' => 'CMD',
			'filter_order_Dir' => 'CMD',
			'filter-search' => 'STRING',
			'print' => 'BOOLEAN',
			'lang' => 'CMD'
		);

		return parent::display($cachable, $safeurlparams);
	}

	public function tracker_change_notification()
	{
		// Verify the request token.
		$token = $this->input->getString('token', null, 'method');

		if ($token != '1q2w3e4r')
		{
			JError::raiseError(403, 'Access Forbidden');
		}

		// Get some values from the request.
		$trackerId	= $this->input->getInt('tracker_id');
		$issueId	= $this->input->getInt('tracker_item_id');

		// Get the tracker sync model.
		$model = $this->getModel('TrackerSync');

		// Attempt to scan for available builds.
		if (!($success = $model->syncIssue($issueId, $trackerId)))
		{
			JError::raiseError(500, JText::sprintf('COM_CODE_ISSUE_SYNC_FAILURE', $model->getError()));
		}

		$this->setRedirect(JRoute::_('index.php?option=com_code'), JText::_('COM_CODE_ISSUE_SYNC_SUCCESS'));
	}
}
