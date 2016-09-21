<?php
namespace ChallengeBox\Includes\Utilities;

use \Carbon\Carbon;

class Time extends BaseSingleton {
	
	const ONE_DAY = 86400;
	const ONE_HOUR = 3600;
	
	/**
	 * convert to Carbon
	 * 
	 * @param unknown $dateString
	 * @param string $format
	 */
	public function convertToCarbon($dateString, $format = 'Y-m-d H:i:s') {
		return Carbon::createFromFormat($format, $dateString);
	}
	
	/**
	 * getDateRange
	 * 
	 * @param unknown $startDate
	 * @param unknown $endDate
	 */
	public function getDateRange($startDate, $endDate) {

		$startDate = (is_string($startDate)) ? $this->convertToCarbon($startDate, 'Y-m-d') : $startDate;
		$endDate = (is_string($endDate)) ? $this->convertToCarbon($endDate, 'Y-m-d') : $endDate;
		
		$dates = [];
		for($date = $startDate; $date->lte($endDate); $date->addDay()) {
			$dates[] = $date->format('Y-m-d');
		}
		
		return $dates;
	}
	
	/**
	 * now
	 */
	public function now($format = 'Y-m-d H:i:s')
	{
		return date($format);
	}
	
}