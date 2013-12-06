<?php
/**
 * @version		$Id: code.php 398 2010-06-13 17:53:03Z louis $
 * @package		Joomla.Site
 * @subpackage	com_code
 * @copyright	Copyright (C) 2009 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

// Include dependancies.

$controller = JControllerLegacy::getInstance('Code');
$controller->execute(JFactory::getApplication()->input->getCmd('task'));
$controller->redirect();
