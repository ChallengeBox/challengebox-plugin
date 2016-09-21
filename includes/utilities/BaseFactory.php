<?php
namespace ChallengeBox\Includes\Utilities;

class BaseFactory extends BaseSingleton
{
	/**
	 * generate
	 */
	public function generate($className, $params = array())
	{
		return new $className(... $params);
	}
}