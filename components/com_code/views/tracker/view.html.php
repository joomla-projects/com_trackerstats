<?php
/**
 * @version		$Id: view.html.php 421 2010-06-25 02:50:14Z louis $
 * @package		Joomla.Site
 * @subpackage	com_code
 * @copyright	Copyright (C) 2009 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * The HTML Joomla Code tracker view.
 *
 * @package		Joomla.Code
 * @subpackage	com_code
 * @since		1.0
 */
class CodeViewTracker extends JViewLegacy
{
	/**
	 * Execute and display a template script.
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  mixed  A string if successful, otherwise a Error object.
	 *
	 * @since   1.0
	 */
	public function display($tpl = null)
	{
		$this->state  = $this->get('State');
		$this->item   = $this->get('Item');
		$this->items  = $this->get('Items');
		$this->page   = $this->get('Pagination');
		$this->user   = JFactory::getUser();
		$this->params = JFactory::getApplication()->getParams('com_code');

		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}

		$this->page->setAdditionalUrlParam('tracker_alias', $this->item->alias);

		return parent::display($tpl);
	}
}
