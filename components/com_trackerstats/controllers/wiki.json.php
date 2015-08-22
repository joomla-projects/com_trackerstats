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
 * JSON controller for Trackerstats -- Returns data array for rendering wiki activity bar charts
 */
class TrackerstatsControllerWiki extends JControllerLegacy
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
		// JSON URL which should be requested
		$json_url = 'https://docs.joomla.org/api.php?action=query&list=allusers&format=json&auexcludegroup=bot&aulimit=100&auprop=editcount&auactiveusers=';
		$ch       = curl_init($json_url);
		$options  = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => ['Content-type: application/json'],
			CURLOPT_POSTFIELDS     => ''
		);

		// Setting curl options
		curl_setopt_array($ch, $options);

		// Getting results
		$users = json_decode(curl_exec($ch)); // Getting jSON result string

		// Convert to array for processing
		$workArray       = [];
		$totalEditsArray = [];

		foreach ($users->query->allusers as $user)
		{
			if ($user->name == 'MediaWiki default')
			{
				continue;
			}

			$workArray[$user->name]       = $user->recenteditcount;
			$totalEditsArray[$user->name] = $user->editcount;
		}

		asort($workArray, SORT_NUMERIC);

		// Slice the last 25 entries
		$maxCount   = 25;
		$arrayCount = count($workArray);

		if ($arrayCount > $maxCount)
		{
			$sliceStart = $arrayCount - $maxCount;
			$workArray  = array_slice($workArray, $sliceStart, $maxCount);
		}

		$people = [];
		$edits  = [];
		$i      = 0;

		foreach ($workArray as $k => $v)
		{
			if ($v > 0 && $i++ < $maxCount)
			{
				$edits[]  = $v;
				$people[] = $k . ' (' . $totalEditsArray[$k] . ' total edits)';
			}
		}

		$label        = new stdClass;
		$label->label = 'Wiki Edits';

		JFactory::getApplication()->mimeType = 'application/json';

		// Send the response.
		echo json_encode([[$edits], $people, [$label], 'Wiki Edits by Contributor in Past 30 Days']);

		return $this;
	}
}
