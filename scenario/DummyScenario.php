<?php
/**
 * This is a dummy scenario, it doesnt do anything interesting. Use it as a skeleton to start a new scenario.
 */

if (!class_exists('PhuseyTest'))
	include_once(dirname(__FILE__).'/../app/phusey.php');

PhuseyTest::registerTest(new TestCase());
class TestCase extends PhuseyTest
{
	/**
	 * Return the name of the test
	 */
	public function getTestName()
	{
		return basename(__FILE__);
	}
	
	/**
	 * Return a string containing the version of the test. 
	 */
	public function getTestVersion()
	{
		// Return version based on save date
		//return date('Ymd-His', filemtime(__FILE__));

		// Return version based on file content
		// return md5(file_get_contents(__FILE__)); 

		// Return a static version number
		return 'V1.0';
	}

	/**
	 * Define scenario steps
	 */
	public function scenario()
	{
		//$this->scenario->get("http://www.thissitedonotexists.com/");
		$this->scenario->dummy();
	}
	
	/**
	 * Define scenario workload
	 */
	public function workload()
	{
		$this->workload->dummy();
	}
}



