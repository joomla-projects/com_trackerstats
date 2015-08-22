<?php
/**
 * @package     Joomla.BugSquad
 * @subpackage  com_trackerstats
 *
 * @copyright   Copyright (C) 2011 Mark Dexter. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Gets the data for the bug squad total activity by time period bar chart.
 */
class TrackerstatsModelActivity extends JModelList
{
	/**
	 * Method to get a JDatabaseQuery object for retrieving the data set from a database.
	 *
	 * @return  JDatabaseQuery   A JDatabaseQuery object to retrieve the data set.
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$db    = $this->getDbo();
		$query = $db->getQuery(true);
		$query->select('t.activity_group');

		$periodList  = [1 => 7, 2 => 30, 3 => 90];
		$periodNames = [1 => 'Weeks', 2 => 'Months', 3 => 'Quarters'];
		$periodName  = $periodNames[$this->state->get('list.period')];
		$periodValue = $periodList[$this->state->get('list.period')];

		// Get 12 columns
		for ($i = 4; $i > 0; $i--)
		{
			$startDay = ($i * $periodValue) - 1;
			$endDay   = ($i - 1) * $periodValue;
			$query->select(
				'SUM(CASE WHEN DATE(a.activity_date) BETWEEN '
				. 'Date(DATE_ADD(now(), INTERVAL -' . $startDay . ' DAY)) '
				. ' AND Date(DATE_ADD(now(), INTERVAL -' . $endDay . ' DAY)) THEN t.activity_points ELSE 0 END)'
				. ' AS p' . $i
			);
		}

		$query->select('DATE(NOW()) AS end_date');

		$typeList = ['All', 'Tracker', 'Test', 'Code'];
		$type     = $typeList[$this->state->get('list.activity_type')];

		// Select required fields from the categories.
		$query->from($db->quoteName('#__code_activity_detail') . ' AS a');
		$query->join('INNER', $db->quoteName('#__code_activity_types') . ' AS t ON a.activity_type = t.activity_type');
		$query->where('date(a.activity_date) > Date(DATE_ADD(now(), INTERVAL -' . ($periodValue * 4) . ' DAY))');
		$query->group('t.activity_group');

		if ($this->state->get('list.activity_type') > 0)
		{
			$query->where('t.activity_group = ' . $db->quote($type));
		}

		$query->order('t.activity_group DESC');

		return $query;
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   An optional ordering field.
	 * @param   string  $direction  An optional direction (asc|desc).
	 *
	 * @return  void
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		// Initialise variables.
		$input  = JFactory::getApplication()->input;

		$this->setState('list.limit', 25);
		$this->setState('list.start', 0);
		$this->setState('list.period', $input->getInt('period', 1));
		$this->setState('list.activity_type', $input->getInt('activity_type', 0));
	}
}
