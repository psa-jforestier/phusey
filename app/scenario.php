<?php
include_once('step.php');
class Scenario
{
	var $steps = array();
	var $timeout = 10;
	var $triggerASoftwareErrorIfBodyContains = array();

	/**
	 * Set an expiration timeout, in second. Any http request will be aborted when timeout occur. 
	 * Steps failed by timeout will have a result code of "0" (network error)
	 */
	public function setTimeout($t)
	{
		$this->timeout = $t;
	}

	/**
	 * Clear cookies of the virtual browser. Only the cookies of the current running browser are cleared, all parallel browsers 
	 * keep their cookies.
	 */
	public function clearCookie()
	{
		$this->steps[] = new StepClearCookie();
	}
	
	/**
	 * Do an HTTP GET on the provided URL, and record response time and other HTTP metrics.
     * If headers is present, replace all HTTP headers by them.
	 */
	public function get($url, $headers = array())
	{
		$this->steps[] = new StepHttpGet($url, $headers);
	}
	
	/**
	 * Do an HTTP POST to the provided URL, with eventully some parameters. Record response time and other HTTP metrics.
	 * Parameters can be a string (like "key=val&" or "{k:v}" or an array (will be converted to "key=val&").
     * If headers is present, replace all HTTP headers by them.
	 */
	public function post($url, $params = array(), $headers = array())
	{
		$this->steps[] = new StepHttpPost($url, $params, $headers);
	}
	
	/**
	 * Start a new transaction with a given name. Transaction are only here to classify or group your steps. 
	 * Starting a transaction do not send any HTTP request. Normally, you should not emebed transaction into
	 * an already open transaction. Any started transaction must be stopped by calling stopTransaction().
	 * When statistics are computed, all response time of steps inside a transaction are cumulated.
	 */
	public function startTransaction($name)
	{
		$this->steps[] = new StepStartTransaction($name);
	}
	
	/**
	 * Stop the current transaction. It doesnt send any HTTP request.
	 */
	public function stopTransaction()
	{
		$this->steps[] = new StepStopTransaction();
	}
	
	/**
	 * Pause the scenario for a given amount of milliseconds. The pause can be an integer or a float, but 
	 * accuracy is not guaranted under 1 ms.
	 */
	public function pause($timems)
	{
		$this->steps[] = new StepPause($timems);
	}
	
	/**
	 * Do nothing. Do not send any HTTP request. Use only for demonstration purpose.
	 */
	public function dummy()
	{
		$this->steps[] = new StepHttpGetDummy();
	}
	
	/**
	 ** Will trigger a software error if a line (delimited by \n) of the body response
	 ** match one of the regexps. Match do not take care of char case.
	 ** Each regexps of the array can use PHP regexp pattern http://php.net/manual/function.preg-match.php
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
	 ** Return false if no error, or number (started from 0) of matched regexp.
	 ** This method is internal use only, and should not be used in a scenario.
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
