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
 * JSON controller for Trackerstats -- Returns data array for rendering total activity bar chart
 */
class TrackerstatsControllerActivity extends JControllerLegacy
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
		/** @var TrackerstatsModelActivity $model */
		$model = $this->getModel('Activity', 'TrackerstatsModel');
		$items = $model->getItems();
		$state = $model->getState();

		$periodType   = $state->get('list.period');
		$activityType = $state->get('list.activity_type');

		$periodTitle   = [1 => 'Weeks', 2 => 'Months', 3 => 'Quarters'];
		$axisLabels    = ['None', 'Week', '30 Days', '90 Days'];
		$periodText    = $periodTitle[$periodType];
		$axisLableText = $axisLabels[$periodType];

		$activityTypes = ['All', 'Tracker', 'Test', 'Code'];
		$activityText  = $activityTypes[$activityType];
		$title         = "$activityText Points for Past Four $periodText";

		$ticks  = [];
		$points = [];

		// Build series arrays in reverse order for the chart
		foreach ($items as $item)
		{
			$group            = $item->activity_group;
			$points[$group][] = (int) $item->p4;
			$points[$group][] = (int) $item->p3;
			$points[$group][] = (int) $item->p2;
			$points[$group][] = (int) $item->p1;
		}

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
		$types         = array_keys($points);
		$label1->label = $types[0] . ' Points';

		if ($activityType === 0)
		{
			$label2->label = $types[1] . ' Points';
			$label3->label = $types[2] . ' Points';
			$data          = [$points[$types[0]], $points[$types[1]], $points[$types[2]]];
			$labels        = [$label1, $label2, $label3];
		}
		else
		{
			$data   = [$points[$types[0]]];
			$labels = [$label1];
		}

		// Assemble array
		$return = [$data, $ticks, $labels, $title];

		// Use the correct json mime-type
		JFactory::getApplication()->mimeType = 'application/json';

		// Send the response.
		echo json_encode($return);

		return $this;
	}
}
