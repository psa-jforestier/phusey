<?php

include_once('load.php');
class Workload
{
	var $loads = array();

	/**
	 ** Do nothing. Do not send any HTTP request. Use only for demonstration purpose.
	 **/
	public function dummy()
	{
		;
	}

	/**
	 ** Start a single browser, with scenario run for $duration seconds
	 **/
	public function singleBrowser($duration)
	{
		$this->loads[] = new LoadSingleBrowser($duration);
	}

	/**
	 ** Start $nbbrowsers at the same time,
	 ** each browser run the scenario for $duration seconds.
	 ** So all browser should stop almost at the same time
	 **/
	public function parallelBrowsers($nbbrowsers, $duration)
	{
		if ($nbbrowsers < 1)
			throw new PhuseyException("Invalid number of browsers");
		$this->loads[] = new LoadParallelBrowsers($nbbrowsers, $duration);
	}
	
	/**
	 ** Start $fromnbbrowser to $tonbbrowser, $step at a time.
	 ** The whole ramp will last for $duration seconds.
	 ** every step will have a duration of 
	 ** $duration / ( ($to - $from) / $step )
	 **/
	public function startRampingBrowsers($fromnbbrowser, $tonbbrowser, $step, $duration)
	{
		$this->loads[] = new LoadRampingBrowsers($fromnbbrowser, $tonbbrowser, $step, $duration);
	}
	
	/**
	 ** Start $fromnbbrowser to $tonbbrowser, $step at a time.
	 ** The whole ramp will last for $duration seconds.
	 ** every step will have a duration of 
	 ** $duration / ( ($to - $from) / $step )
	 **/
	public function startRampingBrowsersWithFixedStepDuration($fromnbbrowser, $tonbbrowser, $step, $stepduration)
	{
		if (($fromnbbrowser > $tonbbrowser) ||
			$fromnbbrowser < 1 || $tonbbrowser < 2 || $step === 0 
			)
			{
				throw new PhuseyException("Invalid ramp definition");
			}
		$this->loads[] = new LoadStartRampingBrowsersWithFixedStepDuration($fromnbbrowser, $tonbbrowser, $step, $stepduration);
	}
	
	/**
	 ** Start $fromnbbrowser in parallel, and stop them $step at a time
	 ** to arrive to $tonbbrowser.
	 ** The whole ramp will last for $duration seconds.
	 ** every step will have a duration of 
	 ** $duration / ( ($to - $from) / $step )
	 **/
	public function stopRampingBrowsersWithFixedStepDuration($fromnbbrowser, $tonbbrowser, $step, $stepduration)
	{
		if (($fromnbbrowser < $tonbbrowser) ||
			$fromnbbrowser < 1 || $tonbbrowser < 1 || $step === 0 
			)
			{
				throw new PhuseyException("Invalid ramp definition");
			}
		$this->loads[] = new LoadStopRampingBrowsersWithFixedStepDuration($fromnbbrowser, $tonbbrowser, $step, $stepduration);
	}
}