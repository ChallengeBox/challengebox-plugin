<?php

class Test_CB extends WP_UnitTestCase {
	function test_any() {
		$this->assertTrue(CB::any(array(1)));
		$this->assertTrue(CB::any(array(0,NULL,'','hi')));
		$this->assertFalse(CB::any(array()));
		$this->assertFalse(CB::any(array(0,NULL,'',array())));
	}
	function test_all() {
		$this->assertTrue(CB::all(array()));
		$this->assertFalse(CB::all(array(0,NULL,'','hi')));
		$this->assertFalse(CB::all(array(1,2,'',3,4)));
		$this->assertTrue(CB::all(array(1,2,3,4)));
		$this->assertTrue(CB::all(array('hi',array('t','h','e','r','e'))));
	}
}

