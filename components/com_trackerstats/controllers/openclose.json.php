<?php
/**
 * @package     Joomla.BugSquad
 * @subpackage  com_trackerstats
 *
 * @copyright   Copyright (C) 2005 - 2015 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * JSON controller for Trackerstats -- Returns data array for rendering total open and closed issues bar charts
 */
class TrackerstatsControllerOpenclose extends JControllerLegacy
{
	/**
	 * Method to display a view.
	 *
	 * @param	boolean  $cachable   If true, the view output will be cached
	 * @param	array    $urlparams  An array of safe url parameters and their variable types, for valid values see {@link JFilterInput::clean()}.
	 *
	 * @return	$this
	 */
	public function display($cachable = false, $urlparams = [])
	{
		/** @var TrackerstatsModelOpenclose $model */
		$model = $this->getModel('Openclose', 'TrackerstatsModel');
		$items = $model->getIssueCounts();
		$state = $model->getState();

		$periodType = $state->get('list.period');

		$periodTitle   = [1 => 'Weeks', 2 => 'Months', 3 => 'Quarters'];
		$axisLabels    = ['None', 'Week', '30 Days', '90 Days'];
		$periodText    = $periodTitle[$periodType];
		$axisLableText = $axisLabels[$periodType];

		$title = "Issues Opened and Closed for Past Four $periodText";

		$ticks  = [];
		$counts = [];

		$counts['Opened'][] = (int) $items[0]->opened4;
		$counts['Opened'][] = (int) $items[0]->opened3;
		$counts['Opened'][] = (int) $items[0]->opened2;
		$counts['Opened'][] = (int) $items[0]->opened1;

		$counts['Fixed'][] = (int) $items[1]->fixed4;
		$counts['Fixed'][] = (int) $items[1]->fixed3;
		$counts['Fixed'][] = (int) $items[1]->fixed2;
		$counts['Fixed'][] = (int) $items[1]->fixed1;

		$counts['Other Closed'][] = (int) $items[1]->closed4;
		$counts['Other Closed'][] = (int) $items[1]->closed3;
		$counts['Other Closed'][] = (int) $items[1]->closed2;
		$counts['Other Closed'][] = (int) $items[1]->closed1;

		$counts['Total Closed'][] = $counts['Other Closed'][0] + $counts['Fixed'][0];
		$counts['Total Closed'][] = $counts['Other Closed'][1] + $counts['Fixed'][1];
		$counts['Total Closed'][] = $counts['Other Closed'][2] + $counts['Fixed'][2];
		$counts['Total Closed'][] = $counts['Other Closed'][3] + $counts['Fixed'][3];

		$endDate     = $items[0]->end_date;
		$periodDays  = [7, 7, 30, 90];
		$dayInterval = $periodDays[$periodType];

		$ticks[] = date('d M', strtotime($endDate . '-' . (($dayInterval * 4) - 1) . ' day')) . ' - ' . date('d M', strtotime($endDate . '-' . ($dayInterval * 3) . ' day'));
		$ticks[] = date('d M', strtotime($endDate . '-' . (($dayInterval * 3) - 1) . ' day')) . ' - ' . date('d M', strtotime($endDate . '-' . ($dayInterval * 2) . ' day'));
		$ticks[] = date('d M', strtotime($endDate . '-' . (($dayInterval * 2) - 1) . ' day')) . ' - ' . date('d M', strtotime($endDate . '-' . ($dayInterval * 1) . ' day'));
		$ticks[] = date('d M', strtotime($endDate . '-' . (($dayInterval * 1) - 1) . ' day')) . ' - ' . date('d M', strtotime($endDate . '-' . ($dayInterval * 0) . ' day'));

		$data          = [];
		$label1        = new stdClass;
		$label2        = new stdClass;
		$label3        = new stdClass;
		$label4        = new stdClass;
		$types         = array_keys($counts);
		$label1->label = $types[0];
		$label2->label = $types[1];
		$label3->label = $types[2];
		$label4->label = 'Total Closed';
		$data          = [$counts[$types[0]], $counts[$types[1]], $counts[$types[2]], $counts['Total Closed']];
		$labels        = [$label1, $label2, $label3, $label4];

		// Assemble array
		$return = [$data, $ticks, $labels, $title];

		// Use the correct json mime-type
		JFactory::getApplication()->mimeType = 'application/json';

		// Send the response.
		echo json_encode($return);

		return $this;
	}
}
