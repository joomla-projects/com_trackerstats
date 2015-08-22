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
 * JSON controller for Trackerstats -- Returns data array for rendering activity by person bar chart
 */
class TrackerstatsControllerBarcharts extends JControllerLegacy
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
		/** @var TrackerstatsModelDashboard $model */
		$model = $this->getModel('Dashboard', 'TrackerstatsModel');
		$items = $model->getItems();
		$state = $model->getState();

		$periodType   = $state->get('list.period');
		$activityType = $state->get('list.activity_type');

		$periodTitle = [1 => '7 Days', 2 => '30 Days', 3 => '90 Days', 4 => '12 Months', 5 => 'Custom'];
		$periodText  = $periodTitle[$periodType];

		$activityTypes = ['All', 'Tracker', 'Test', 'Code'];
		$activityText  = $activityTypes[$activityType];

		if ($periodType == 5)
		{
			$start = date('d M Y', strtotime($state->get('list.startdate')));
			$end   = date('d M Y', strtotime($state->get('list.enddate')));
			$title = $activityText . ' Points From ' . $start . ' Through ' . $end;
		}
		else
		{
			$title = "$activityText Points for Past $periodText";
		}

		$ticks         = [];
		$trackerPoints = [];
		$testPoints    = [];
		$codePoints    = [];

		// Build series arrays in reverse order for the chart
		$i = count($items);

		while ($i > 0)
		{
			$i--;
			$ticks[]         = $items[$i]->name;
			$trackerPoints[] = (int) $items[$i]->tracker_points;
			$testPoints[]    = (int) $items[$i]->test_points;
			$codePoints[]    = (int) $items[$i]->code_points;
		}

		$data          = [];
		$label1        = new stdClass;
		$label2        = new stdClass;
		$label3        = new stdClass;
		$label1->label = 'Tracker Points';
		$label2->label = 'Test Points';
		$label3->label = 'Code Points';

		switch ($activityText)
		{
			case 'Tracker':
				$data   = [$trackerPoints];
				$labels = [$label1];
				break;

			case 'Test':
				$data   = [$testPoints];
				$labels = [$label2];
				break;

			case 'Code':
				$data   = [$codePoints];
				$labels = [$label3];
				break;

			case 'All':
			default:
				$data   = [$trackerPoints, $testPoints, $codePoints];
				$labels = [$label1, $label2, $label3];
				break;
		}

		// assemble array
		$return = [$data, $ticks, $labels, $title];

		// Use the correct json mime-type
		JFactory::getApplication()->mimeType = 'application/json';

		// Send the response.
		echo json_encode($return);

		return $this;
	}
}
