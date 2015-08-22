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
 * JSON controller for Trackerstats -- Returns data array for rendering snapshot history bar charts
 */
class TrackerstatsControllerSnapshot extends JControllerLegacy
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
		/** @var TrackerstatsModelSnapshot $model */
		$model = $this->getModel('Snapshot', 'TrackerstatsModel');
		$items = $model->getItems();
		$state = $model->getState();

		$periodType = $state->get('list.period');

		$periodTitle   = [1 => 'Days', 2 => 'Weeks', 3 => 'Months'];
		$axisLabels    = ['None', 'Day', '7 Days', '30 Days'];
		$periodText    = $periodTitle[$periodType];
		$axisLableText = $axisLabels[$periodType];

		$title = "Total Open Issues by Status for Past Four $periodText";

		$ticks  = [];
		$counts = [];

		foreach ($items as $item)
		{
			$ticks[]     = $item->snapshot_day;
			$objectArray = json_decode($item->status_counts);

			foreach ($objectArray as $object)
			{
				$counts[$object->status_name][$item->snapshot_day] = (int) $object->num_issues;
			}
		}

		// Make sure we get zeros for missing values.
		$dataByStatus = array_values($counts);

		foreach ($dataByStatus as $dataForOneStatus)
		{
			foreach ($ticks as $day)
			{
				$dataForOneStatus[$day] = isset($dataForOneStatus[$day]) ? $dataForOneStatus[$day] : 0;
			}

			ksort($dataForOneStatus);
			$filledDataByStatus[] = $dataForOneStatus;
		}

		$data = [];

		foreach ($filledDataByStatus as $dataForOneStatus)
		{
			$data[] = array_values($dataForOneStatus);
		}

		$types  = array_keys($counts);
		$labels = [];

		foreach ($types as $type)
		{
			$object        = new stdClass;
			$object->label = $type;
			$labels[]      = $object;
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
