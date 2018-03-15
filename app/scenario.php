<?php
include_once('step.php');
class Scenario
{
	var $steps = array();
	var $timeout = 10;
	var $triggerASoftwareErrorIfBodyContains = array();
	public function setTimeout($t)
	{
		$this->timeout = $t;
	}
	public function clearCookie()
	{
		$this->steps[] = new StepClearCookie();
	}
	
	public function get($url)
	{
		$this->steps[] = new StepHttpGet($url);
	}
	
	public function post($url, $params = array())
	{
		$this->steps[] = new StepHttpPost($url, $params);
	}
	
	public function startTransaction($name)
	{
		$this->steps[] = new StepStartTransaction($name);
	}
	
	public function stopTransaction()
	{
		$this->steps[] = new StepStopTransaction();
	}
	
	public function pause($timems)
	{
		$this->steps[] = new StepPause($timems);
	}
	
	public function dummy()
	{
		$this->steps[] = new StepHttpGetDummy();
	}
	
	/**
	 ** Will trigger a software error if a line (delimited by \n) of the body response
	 ** match one of the regexps. Match do not take care of char case.
	 ** Each regexps of the array can use PHP regexp pattern http://php.net/manual/fr/function.preg-match.php
	 ** When doing pattern matching, we use # as the delimiter ("#pattern#modifier").
	 ** Modifiers are "imU" : caseless, multiline, ungreedy
	 **/
	public function triggerASoftwareErrorIfBodyContains(array $regexps)
	{
		$this->triggerASoftwareErrorIfBodyContains += $regexps;
		if (count($this->triggerASoftwareErrorIfBodyContains) >= 100)
		{
			throw new PhuseyException("Unable to handle more than 100 regexp to trigger a software error");
		}
	}
	
	/**
	 ** return false if no error, or number (started from 0) of matched regexp
	 **/
	public function isThereASoftwareError($contents)
	{
		foreach($this->triggerASoftwareErrorIfBodyContains as $i=>$regex)
		{
			if (preg_match("#$regex#imU", $contents, $arr))
			{
				return $i;
			}
		}
		return false;
	}
	

}
