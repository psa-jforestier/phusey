<?php

class BrowserTask extends Threaded
{
	
	var $scenario; // 
	var $threadid; 
	var $threadnumber;
	var $load; // Current load
	var $stop; // true if scenario must be stopped
	var $delayedtimebefore ; // delay before starting the thread (in s, can be a float)
	var $delayedtimeafter;// delay after running the thread
	var $runningtime; // time to run the thread
	var $testidentifier;
	
	
	var $resultsToSave = array();
	var $_currentStep; // can be '?', '.' (wait to start), 'r'eady, 'W'orking, 'S'topping, '-'Stopped, '#'Saving
	var $_currentStepShort;
	var $nbhttp_returned_code = array(
			'tot'=>0, // number of HTTP transaction (including network error)
			'net'=>0, // number of network error
			'0XX'=>0, // number of software error
			'1XX'=>0, 
			'2XX'=>0,
			'3XX'=>0,
			'4XX'=>0,
			'5XX'=>0,
			'Unk'=>0  // number of unkown http code
	);
	var $nbrunnedscenario = 0;
	
	public function getHttpReturnedCode()
	{
		return $this->nbhttp_returned_code;
	}

	/**
	 ** Increment the returned code array
	 ** $code can be an http code (200, 404...)
	 ** or 0 for a network error
	 ** or -1 for unkown code
	 ** or 1..99 for software error (use constant PHUSEY_ERROR_TRIGGERED), recorded as '0XX'.
	 **/
	public function incHttpReturnedCode($code)
	{
		$this->nbhttp_returned_code['tot'] = $this->nbhttp_returned_code['tot'] + 1;
		if ($code === 0)
		{
			$this->nbhttp_returned_code['net'] = $this->nbhttp_returned_code['net'] + 1;
		} 
		else if ($code >= 1 && $code < 100)
			$this->nbhttp_returned_code['0XX'] = $this->nbhttp_returned_code['0XX'] + 1;
		else if ($code >= 100 && $code < 200)
			$this->nbhttp_returned_code['1XX'] = $this->nbhttp_returned_code['1XX'] + 1;
		else if ($code >= 200 && $code < 300)
			$this->nbhttp_returned_code['2XX'] = $this->nbhttp_returned_code['2XX'] + 1;
		else if ($code >= 300 && $code < 400)
			$this->nbhttp_returned_code['3XX'] = $this->nbhttp_returned_code['3XX'] + 1;
		else if ($code >= 400 && $code < 500)
			$this->nbhttp_returned_code['4XX'] = $this->nbhttp_returned_code['4XX'] + 1;
		else if ($code >= 500 && $code < 600)
			$this->nbhttp_returned_code['5XX'] = $this->nbhttp_returned_code['5XX'] + 1;
		else
			$this->nbhttp_returned_code['unk'] = $this->nbhttp_returned_code['unk'] + 1;
		if ($code > 1 && $code < 600)
			$this->nbhttp_returned_code[''.$code] = @$this->nbhttp_returned_code[''.$code] + 1;
	}
	
	public function work($threadid)
	{
		$this->threadid = $threadid;
		if ($this->delayedtimebefore > 0)
		{
			//printf("Thread %d : wait %.3f s before starting\n", $this->threadid, $this->delayedtime );
			$this->_currentStep = "Waiting to start";
			$this->_currentStepShort = '.'; // Waiting
			usleep($this->delayedtimebefore * USLEEP_1S);
		}
		$this->_currentStepShort = 'r'; // Ready
		$startingtime = microtime(true);
		while(true)
		{
			if ($this->stop === true)
				break;
			$this->nbrunnedscenario++;
			$curl = new CURL();
			$curl->setTimeout($this->scenario->timeout);
			$curl->setCookieJar(PHUSEY_TMP."/cookie.".$this->threadnumber.".txt");
			$resultToSave = array();
			foreach($this->scenario->steps as $i=>$step)
			{
				$this->_currentStep = "Step ".($i+1).' '.get_class($step);
				$this->_currentStepShort = 'W'; // Working
				$result = $step->execute($curl);
				$this->_currentStepShort = 'r'; // Ready
				$this->_currentStep = $this->_currentStep ." done";
				if ($result instanceof CURLHttpResult)
				{
					$softerr = $this->scenario->isThereASoftwareError($result->responseBody);
					if ($softerr !== false)
					{
						$result->responseBody = substr($result->responseBody, 0, PHUSEY_MAX_BODY_SIZE);
						$this->incHttpReturnedCode(
							$result->http_code = PHUSEY_ERROR_TRIGGERED + $softerr // Override http code returned by Curl with a soft error code.
						);
					}
					else
					{ // Free some memory ;)
						//$result->responseBody = substr($this->responseBody, PHUSEY_MAX_BODY_SIZE);
						$result->responseBody = 'Not recorded in this version';
						$this->incHttpReturnedCode($result->http_code);
					}
					$result->stepid = $i+1;
					$result->workloadid = $this->load->loadid;
					$result->threadnumber = $this->threadnumber;
					$this->resultsToSave[] = $result;
				}
				else if ($result === true)
					$this->incHttpReturnedCode(-1); // returned code is not handled
				if ($this->stop === true)
					break;
				if ($this->runningtime !== -1)
				{
					if ((microtime(true) - $startingtime) >= $this->runningtime)
					{
						$this->stop = true;
						$this->_currentStepShort = 'S';
						$this->_currentStep = 'Stopping';
						break;
					}
				}
			}
			$curl->close();
		}
		$this->_currentStep = "Saving results";
		$this->_currentStepShort = '#'; // Waiting
		$this->saveResults();
		$this->_currentStep = "Delay after stop";
		$this->_currentStepShort = '-'; // Waiting
		if ($this->delayedtimeafter > 0)
		{
			usleep($this->delayedtimeafter * USLEEP_1S);
		}
		//var_dump($this->nbhttp_returned_code);
	}
	
	private function saveResults()
	{
		$r = new ResultSaver();
		$r->setResults($this->resultsToSave);
		$r->saveToSerialize(PHUSEY_TMP.'/results/'.$this->testidentifier.'/result_'.$this->load->loadid."_".$this->threadnumber.".ser");
		$r->saveToJson(PHUSEY_TMP.'/results/'.$this->testidentifier.'/result_'.$this->load->loadid."_".$this->threadnumber.".json");
		
	}
	
	public function getStatus()
	{
		return sprintf("%3d | thread %6d\t %s", $this->threadnumber, $this->threadid, $this->_currentStep);
	}
	
	public function getStatusShort()
	{
		return $this->_currentStepShort;
	}
	
	public function getNbRunnedScenario()
	{
		return $this->nbrunnedscenario;
	}
	/**
	 ** $scenario to run
	 ** $load workload to apply to the scenario
	 ** $threadnumber number of this thread (or identifier)
	 ** $delayedtimebefore s to wait before starting the main action (can be a float)
	 ** $delayedtimeafter s to wait after the end of the main action (can be a float)
	 ** $runningtime s to run the scenario. If -1 : run infinite and have to be stopped by a stop signal
	 ** Note : total run time = $before + $run + $after.
	 **/
	public function __construct($testidentifier, Scenario $scenario, Load $load, $threadnumber, $delayedtimebefore = 0.0, $delayedtimeafter = 0.0, $runningtime = -1)
	{
		$this->scenario = $scenario;
		$this->threadid = -1;
		$this->load = $load;
		$this->stop = false;
		$this->threadnumber = $threadnumber;
		$this->delayedtimebefore = $delayedtimebefore;
		$this->delayedtimeafter = $delayedtimeafter;
		$this->runningtime = $runningtime;
		$this->testidentifier = $testidentifier;
	}
}

class BrowserThreaded extends Thread 
{
	private $task;
	public function __construct(Threaded $task)
	{
		$this->task = $task;
		$this->task->_currentStepShort = '?';
	}
	public function getStatus()
	{
		return $this->task->getStatus();
	}
	public function getStatusShort()
	{
		return $this->task->getStatusShort();
	}
	
	public function getNbRunnedScenario()
	{
		return $this->task->getNbRunnedScenario();
	}
	
	public function getHttpReturnedCode()
	{
		return $this->task->getHttpReturnedCode();
	}
	public function callStop()
	{
		//printf("Stop thread %d\n", $this->getThreadId());
		$this->task->stop = true;
		$this->task->_currentStepShort = 'S';
		$this->task->_currentStep = 'Stopping';
	}
	public function run()
	{
		$this->task->work($this->getThreadId());
	}
}

