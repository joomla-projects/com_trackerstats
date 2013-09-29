<?php
/**
 * @package     Joomla.BugSquad
 * @subpackage  com_trackerstats
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Trackerstats Component Controller
 *
 * @package     Joomla.BugSquad
 * @subpackage  com_trackerstats
 * @since       2.5
 */
class TrackerstatsController extends JControllerLegacy
{
	/**
	 * Method to display a view.
	 *
	 * @param	boolean  $cachable   If true, the view output will be cached
	 * @param	array    $urlparams  An array of safe url parameters and their variable types, for valid values see {@link JFilterInput::clean()}.
	 *
	 * @return	JController  This object to support chaining.
	 *
	 * @since	1.5
	 */
	public function display($cachable = false, $urlparams = array())
	{
		$cachable = false;

		// Get the document object.
		$document = JFactory::getDocument();

		// Set the default view name and format from the Request.
		$vName = $this->input->getCmd('view', 'dashboard');
		$this->input->set('view', $vName);

		$safeurlparams = array(
			'catid' => 'INT', 'id' => 'INT', 'cid' => 'ARRAY', 'year' => 'INT', 'month' => 'INT', 'limit' => 'UINT', 'limitstart' => 'UINT',
			'showall' => 'INT', 'return' => 'BASE64', 'filter' => 'STRING', 'filter_order' => 'CMD', 'filter_order_Dir' => 'CMD',
			'filter-search' => 'STRING', 'print' => 'BOOLEAN', 'lang' => 'CMD'
		);

		return parent::display($cachable, $safeurlparams);
	}
}
