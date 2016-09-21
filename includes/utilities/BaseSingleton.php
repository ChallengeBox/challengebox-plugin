<?php
namespace ChallengeBox\Includes\Utilities;

class BaseSingleton
{
    static $instance = array();
	
	/**
	 * clear instances
	 */
	public static function clearInstances()
	{
		static::$instance = array();
	}
	
    /**
     * getInstance
     *
     * @return AdminUserMapper
     */
    public static function getInstance()
    {
    	$className = get_called_class();
    	
        if (!array_key_exists($className, self::$instance)) {
             self::$instance[$className] = new $className();
        }
        
        return self::$instance[$className];
    }

    /**
     * setInstance
     *
     * @param mixed $mapper
     */
    public static function setInstance($singleton)
    {
    	$className = get_called_class();
    	
    	if (is_null($singleton)) {
    		unset(self::$instance[$className]);
    	} else {
        	self::$instance[$className] = $singleton;
    	}
    }
}