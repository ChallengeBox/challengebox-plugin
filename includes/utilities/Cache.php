<?php
namespace ChallengeBox\Includes\Utilities;

class Cache extends BaseSingleton {
	
	public function get($key) {
		return \get_transient($key);
	}
	
	public function set($key, $value, $ttl = 86400) {
		return \set_transient($key, $value, $ttl);
	}
	
	public function clear($key) {
		return \delete_transient($key);
	}
}