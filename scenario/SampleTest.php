<?php
/**
 * This scenario test different case of a malfunctionning web application.
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
		$urlbase = 'http://localhost:80';
		
		/**
		$this->scenario->dummy();
		**/
		$this->scenario->setTimeout(5); // http timeout
		$this->scenario->clearCookie();
		$this->scenario->triggerASoftwareErrorIfBodyContains(['.*Notice.*on line.*', '.*Warning.*on line.*']);
		$this->scenario->startTransaction("Transac1");
		$this->scenario->get("$urlbase/index.php?speed=fast");
		$this->scenario->stopTransaction();
		$this->scenario->startTransaction("Transac2");
		$this->scenario->get("$urlbase/index.php?speed=slow_randomized&from=0.100&to=3.000");
		$this->scenario->get("$urlbase/index.php?reply=err_randomized");
		$this->scenario->stopTransaction();
		$this->scenario->get("$urlbase/index.html?NotInTransaction1");
		$this->scenario->get("$urlbase/index.html?NotInTransaction2");
		$this->scenario->pause(250);
	}
	
	public function workload()
	{
		$this->workload->singleBrowser(60); // Loop the scenario during 60s
		$this->workload->parallelBrowsers(10, 60); // Loop the scenario in 10 browsers during 60s
		//$this->workload->startRampingBrowsers(10, 20, 2, 60); // Start from 10 to 20 browsers (2 by 2) for a total duration of 60s (each level will have a duration of 60 / ((20 - 10) / 2) = 12s)
		$this->workload->startRampingBrowsersWithFixedStepDuration(
			10, 20, // Start from 10 to 20 browsers
			2, // 2 browsers by 2 browsers
			10 // each step will have a duration of 10s
		); // Total duration : ( 1 + (20 - 10) / 2 ) * 10s
		$this->workload->stopRampingBrowsersWithFixedStepDuration(
			20,10, // Start 20 browsers to 10
			2,    // 2 browsers by 2 browsers
			10    // each step will have a duration of 10s
		);
	}
}



