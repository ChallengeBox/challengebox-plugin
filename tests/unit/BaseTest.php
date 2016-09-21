<?php 
use PHPUnit\Framework\TestCase;

use ChallengeBox\Includes\Utilities\{
	BaseSingleton,
	Cache
};
	
if (!class_exists('WP_CLI_Command')) {
	class WP_CLI_Command {};
}

if (!class_exists('WP_CLI')) {
	class WP_CLI {
		public static function add_command() {}
	}
}

class BaseTest extends TestCase {

	private function mockWpClasses() {

		$cache = $this->getMockBuilder('ChallengeBox\Includes\Utilities\Cache')
			->disableOriginalConstructor()
			->setMethods(null)
			->getMock();
		
	}
	
	private function mockCache() {
		$cache = $this->getMockBuilder('ChallengeBox\Includes\Utilities\Cache')
			->disableOriginalConstructor()
			->setMethods(null)
			->getMock();
		Cache::setInstance($cache);
	}
	
	protected function setUp() {
		parent::setUp();
		BaseSingleton::clearInstances();
		$this->mockCache();
	}

	protected function tearDown() {
		parent::tearDown();
		BaseSingleton::clearInstances();
	}
	
}
?>