<?php

// Ugly memory locker
// Allow to lock ressource with a memory lock. Memory must be shared with all process that need a lock. 
class Lock
{
	private static $instance = null;
	private static $locked = array();
	
	private function __construct()
	{
	}
	public static function getInstance()
	{
		if (self::$instance === null)
			self::$instance = new Lock();
		return self::$instance;
	}
	
	public function lock($object, $timeout = 1)
	{
		$lockid = md5(serialize($object));
		if (isset(self::$locked[$lockid]))
		{
			$t = time();
			$locked = false;
			while(true)
			{
				usleep(1000 * 1000 * ($timeout / 10));
				if (!isset(self::$locked[$lockid]))
					continue;
				if (time() - $t > $timeout)
				{
					$locked = true;
					break;
				}
			}
			if ($locked)
				throw new Exception("Unable to aquire a lock");
		}
		self::$locked[$lockid] = true;
		return $lockid;
	}
	public function unlock($lockid)
	{
		unset(self::$locked[$lockid]);
	}
}

