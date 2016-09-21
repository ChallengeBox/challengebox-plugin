<?php
define('CHALLENGEBOX_PLUGIN_DIR', realpath(__dir__ . '/../../'));


require_once('./BaseTest.php');

function stripNamespaceFromClassName($classname)
{
        if (preg_match('@\\\\([\w]+)$@', $classname, $matches)) {
            $classname = $matches[1];
        }

        return $classname;
}

spl_autoload_register(function ($class) {

	echo $class . PHP_EOL;
	
	if (!class_exists($class)) {
		
		$class = stripNamespaceFromClassName($class);
		
		// utilities?
		$possibleFilePath = CHALLENGEBOX_PLUGIN_DIR . '/includes/utilities/' . $class . '.php';
		if (file_exists($possibleFilePath)) {
			require_once($possibleFilePath);
			return;
		}
		
		// includes "class-cb" files?
		$possibleFilename = 'class-' . preg_replace('/cb/', 'cb-', strtolower(preg_replace('/([^A-Z-])([A-Z])/', '$1-$2', $class)), 1);
		$possibleFilePath = CHALLENGEBOX_PLUGIN_DIR . '/includes/' . $possibleFilename . '.php';
		
		echo $possibleFilePath . PHP_EOL;
		if (file_exists($possibleFilePath)) {
			require_once($possibleFilePath);
			return;
		}
		
		// 
		$filename = null;
		switch ($class) {
			case 'CBCmd':
				$filename = CHALLENGEBOX_PLUGIN_DIR . '/includes/class-cb-commands.php';
				break;
		}
		
		if (!is_null($filename)) {
			require_once($filename);
		}
	}
	
});