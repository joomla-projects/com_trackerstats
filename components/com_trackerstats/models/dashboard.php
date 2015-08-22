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
 * Gets the data for the bug squad activity by person bar chart
 */
class TrackerstatsModelDashboard extends JModelList
{
	/**
	 * Method to get a JDatabaseQuery object for retrieving the data set from a database.
	 *
	 * @return  JDatabaseQuery   A JDatabaseQuery object to retrieve the data set.
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$db          = $this->getDbo();
		$query       = $db->getQuery(true);
		$periodList  = [1 => '-7 DAY', 2 => '-30 Day', 3 => '-90 DAY', 4 => '-1 YEAR', 5 => 'Custom'];
		$periodValue = $periodList[$this->state->get('list.period')];

		$typeList = ['All', 'Tracker', 'Test', 'Code'];
		$type     = $typeList[$this->state->get('list.activity_type')];

		// Select required fields from the categories.
		$query->select('CONCAT(u.first_name, " ", u.last_name) AS name');
		$query->select('SUM(t.activity_points) AS total_points');
		$query->select("SUM(CASE WHEN t.activity_group = 'Tracker' THEN t.activity_points ELSE 0 END) AS tracker_points");
		$query->select("SUM(CASE WHEN t.activity_group = 'Test' THEN t.activity_points ELSE 0 END) AS test_points");
		$query->select("SUM(CASE WHEN t.activity_group = 'Code' THEN t.activity_points ELSE 0 END) AS code_points");

		$query->from($db->quoteName('#__code_activity_detail') . ' AS a');
		$query->join('LEFT', $db->quoteName('#__code_users') . 'AS u ON u.jc_user_id = a.jc_user_id');
		$query->join('LEFT', $db->quoteName('#__code_activity_types') . ' AS t ON a.activity_type = t.activity_type');

		if ($periodValue == 'Custom')
		{
			$query->where('DATE(a.activity_date) BETWEEN ' . $db->quote($this->state->get('list.startdate')) . ' AND ' . $db->quote($this->state->get('list.enddate')));
		}
		else
		{
			$query->where('DATE(a.activity_date) > DATE(DATE_ADD(NOW(), INTERVAL ' . $periodValue . '))');
		}

		if ($this->state->get('list.activity_type') > 0)
		{
			$query->where('t.activity_group = ' . $db->quote($type));
			$query->order("SUM(CASE WHEN t.activity_group = " . $db->quote($type) . " THEN t.activity_points ELSE 0 END) DESC, SUM(t.activity_points) DESC");
		}
		else
		{
			$query->order("SUM(t.activity_points) DESC");
		}

		$query->group('a.jc_user_id');

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
		$jinput = JFactory::getApplication()->input;
		$params = JComponentHelper::getParams('com_trackerstats');
		$this->setState('list.limit', 25);
		$this->setState('list.start', 0);
		$this->setState('list.period', $jinput->getInt('period', 1));
		$this->setState('list.activity_type', $jinput->getInt('activity_type', 0));
		$enteredPeriod = $jinput->getInt('period', 1);

		if ($enteredPeriod == 5)
		{
			$startDate = $jinput->getCmd('startdate');
			$endDate   = $jinput->getCmd('enddate');

			if ($this->datesValid($startDate, $endDate))
			{
				$this->setState('list.startdate', $startDate);
				$this->setState('list.enddate', $endDate);
			}
			else
			{
				$enteredPeriod = 1;
			}
		}

		$this->setState('list.period', $enteredPeriod);
	}

	/**
	 * Method to check that custom dates are valid
	 *
	 * @param   string  $date1  The first date.
	 * @param   string  $date2  The second date.
	 *
	 * @return  boolean
	 */
	private function datesValid($date1, $date2)
	{
		// check that they are dates and that $date1 <= $date2
		if (($date1 == date('Y-m-d', strtotime($date1))) && ($date2 == date('Y-m-d', strtotime($date2))) && ($date1 <= $date2))
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}
