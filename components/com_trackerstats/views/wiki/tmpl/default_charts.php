<?php
/**
 * @package     Joomla.BugSquad
 * @subpackage  com_trackerstats
 *
 * @copyright   Copyright (C) 2011 Mark Dexter. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// no direct access
defined('_JEXEC') or die;

JHtml::addIncludePath(JPATH_COMPONENT . '/helpers/html');
JHtml::_('behavior.tooltip');

// $jsonSource = $this->baseurl . "/components/com_trackerstats/json/getbarchartdata.php";
$jsonSource = $this->baseurl . '/index.php?option=com_trackerstats&amp;task=wiki.display&amp;format=json';
JHtml::_('barchart.barchart', 'barchart', 'barchart', true);
?>

<h2>Wiki Activity</h2>
<div id="barchart" style="width:700px; height:600px;" data-href="<?php echo $jsonSource; ?>"></div>
