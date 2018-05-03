<?php

include_once('console.php');
include_once('scenario.php');
include_once('workload.php');
include_once('utils.php');
include_once('browser.php');
include_once('curl.php');
include_once('result.php');
include_once('system.php');
include_once('reporting.php');

define('USLEEP_100MS', 1000 * 1000 * 0.100);
define('USLEEP_1S',    1000 * 1000 * 1);
define('USLEEP_10S',   1000 * 1000 * 10);

define('PHUSEY_TMP', dirname(__FILE__).'/../tmp');
define('PHUSEY_MAX_BODY_SIZE', 1024); // Maximum size of recorded body
define('PHUSEY_ERROR_TRIGGERED', 1);

/**
 * This is the ancestor class of a scenario and a workload. 
 * Extend this class and override some functions. 
 */
class PhuseyTest
{
	public static $tests = array();
	public $scenario;
	public $workload;
	
	private $testversion;
	
	public function __construct()
	{
		$this->scenario = new Scenario();
		$this->workload = new Workload();
	}
	
	protected function scenario()
	{
		throw new PhuseyException("You must implement scenario() function in your test class derivated from PhuseyTest");
	}
	
	protected function workload()
	{
		throw new PhuseyException("You must implement workload() function in your test class derivated from PhuseyTest");
	}
	
	/**
	 * Override this function in your test script.
	 * Return a string of the readable test name.
	 */
	protected function getTestName()
	{
		throw new PhuseyException("You must implement getTestName() function in your test class derivated from PHuseyTest");
	}
	
	/**
	 * Override this function in your test script.
	 * Return a string of the readable test name.
	 * The version can be based on save date of the scenario
	 *  return date('Ymd-His', filemtime(__FILE__));
	 * Return version based on file content
	 *  return md5(file_get_contents(__FILE__));
	 */
	protected function getTestVersion()
	{
		throw new PhuseyException("You must implement getTestVersion() function in your test class derivated from PhuseyTest");
	}
	
	/**
	 * Add a test into the test list. For the moment, only one test at a time is supported
	 */
	public static function registerTest(PhuseyTest $test)
	{
		if (count(self::$tests) >= 1)
		throw new PhuseyException("You cant register several test. Only one test is supported.");
		else
			self::$tests[] = $test;
	}
	public static function getTests()
	{
		return self::$tests;
	}
	
	public function prepareScenario()
	{
		$this->scenario(); // This method is defined in the test file
	}
	
	/**
	 * Print to stdout a readable description of the scenario
	 */
	public function printScenarioInfo()
	{
		$nbhttp = 0;
		$sumpause = 0.0;
		printf("* Test :\nName\t: %s\nVersion\t: %s\nUniqeId\t: %s\n",
			$this->getTestName(),
			$this->getTestVersion(),
			$this->getUniqueTestId()
		);
		printf("* Scenario :\n");
		foreach($this->scenario->steps as $i=>$step)
		{
			$info = '';
			if ($step instanceof  StepHttp)
			{
				$nbhttp++;
				$info = $step->url;
			}
			else if ($step instanceof StepPause)
			{
				$sumpause += $step->pausems;
				$info = $step->pausems;
			}
			printf("%3d\t%-12s\t%s\n", $i+1, $step->explain(), $info);
			
		}
		if ($sumpause == 0)
			$hitps = INF;
		else
			$hitps = (1000 * $nbhttp) / $sumpause;
		printf("Number of http step : \t%d\n", $nbhttp);
		printf("Minimum duration    : \t%.3f s\n", $sumpause/1000);
		printf("Maximum rate with 1 VU :\t %.3f hits/s\n", $hitps);
	}
	
	public function prepareWorkload()
	{
		$this->workload(); // Call this method defined in the test file
	}
	
	/**
	 * Print to stdout a readable description of the workload
	 */
	public function printWorkloadInfo()
	{
		$totalduration = 0;
		$totalbrowser = 0;
		printf("* Workload :\n");
		foreach($this->workload->loads as $i=>$load)
		{
			printf("%3d\t%s %.3fs\n", $load->loadid, get_class($load), $load->duration);
			$totalduration += $load->duration;
			$totalbrowser += $load->nbbrowsers;
		}
		printf("Load total duration : \t%.3f s (%s)\n", $totalduration, secondsToHMS($totalduration) );
		printf("Number of browsers  : \t%d\n", $totalbrowser);
	}
	
	public function getUniqueTestId()
	{
		$v = $this->getTestVersion();
		$n = $this->getTestName();
		$h = base_convert(sprintf("%u", crc32($v.$n)),
			10,
			36
		);
		return $h;
	}
		
	public function serializeScenario()
	{
		$testname = filter_filename($this->getTestName(), true);
		if ($testname == '')
		{
			console_warning("Test name is empty or invalid. Please implement a correct getTestName() function in your test.\nUse \"default\" as the test name\n");
			$testname = 'default';
		}
		file_put_contents(PHUSEY_TMP."/$testname.ser", serialize($this->scenario));
		return $testname;
	}
}

class PhuseyException extends Exception
{
}
