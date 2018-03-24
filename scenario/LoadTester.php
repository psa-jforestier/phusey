<?php
/**
 * This scenario test a malfunctionning application.
 * Start a webserver and point it to the folder in app/www-root
 */

if (!class_exists('PhuseyTest'))
	include_once(dirname(__FILE__).'/../app/phusey.php');

PhuseyTest::registerTest(new TestCase());
class TestCase extends PhuseyTest
{

	public function getTestName()
	{
		return basename(__FILE__);
	}
	
	public function getTestVersion()
	{
		return date('Ymd-His', filemtime(__FILE__));
	}
	public function scenario()
	{
		
		
		/**
		$this->scenario->dummy();
		**/
		$this->scenario->setTimeout(5); // http timeout
		$this->scenario->clearCookie();
		$this->scenario->triggerASoftwareErrorIfBodyContains(['.*Notice.*on line.*', '.*Warning.*on line.*']);
		$this->scenario->startTransaction("ProblematicTransaction");
		$this->scenario->get("http://localhost/loadtester.php");
		$this->scenario->stopTransaction();
		$this->scenario->pause(100);

	}
	
	public function workload()
	{
		$this->workload->singleBrowser(10);
		$this->workload->startRampingBrowsersWithFixedStepDuration(
			1, 20, // Start from 1 to 2à browsers
			1, // 1 browsers by 1 browsers
			10 // each step will have a duration of 10s
		); // 
		$this->workload->stopRampingBrowsersWithFixedStepDuration(
			20, 1, // Start 20 browsers to 1
			1,    // 1 browsers by 1 browsers
			1    // each step will have a duration of 1s
		);

	}
}



