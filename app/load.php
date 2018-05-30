<?php



class Load
{
	var $duration;
	var $nbbrowsers;
	
	protected static $loadidcounter = 1;
	var $loadid;
	
	
	public function execute($a, $b)
	{
		throw new PhuseyException('execute() must be implemented on class '.get_class($this));
	}
	public function explain()
	{
		throw new PhuseyException('explain() must be implemented on class '.get_class($this));
	}
	
	public function saveResults($testId)
	{
		echo "LOAD SAVE RESULT $testId\n";
	}
}

class LoadSingleBrowser extends LoadParallelBrowsers
{
	public function __construct($duration)
	{
		$this->duration = $duration;
		$this->nbbrowsers = 1;
		$this->loadid = (Load::$loadidcounter++);
	}

	public function execute($testidentifier, $scenario)
	{
		parent::execute($testidentifier, $scenario);
	}
}

class LoadParallelBrowsers extends Load
{
	public function __construct($nbbrowsers, $duration)
	{
		$this->duration = $duration;
		$this->nbbrowsers = $nbbrowsers;
		$this->loadid = (Load::$loadidcounter++);
	}
	public function execute($testidentifier, $scenario)
	{
		printf("Main program is thread %d\n", Thread::getCurrentThreadId());
		printf("Start %d threads\n", $this->nbbrowsers);
		$threads = array();
		for ($i=1; $i <= $this->nbbrowsers; $i++)
		{
			$t = new BrowserThreaded(
				new BrowserTask($testidentifier, $scenario, $this, $i)
			);
			$t->start();
			$threads[] = $t;
		}
		printf("Let the threads run for %d seconds...\n", $this->duration);
		$begining = microtime(true);
		if ($this->duration > 120)
			$sleep = USLEEP_10S; // If test duration > 2mn, update stats every 10s
		else
			$sleep = USLEEP_1S; // else update test every second
		printf("%3d %% | Status is updated every %d seconds ... Waiting for data to be collected.\r", 0, $sleep / 1000 / 1000);
		while(true)
		{
			usleep($sleep);
			$elapsed = microtime(true) - $begining;
			// Collect stats from threads
			$nbrunnedscenario = 0;
			$nbhttptr = 0;
			$nbneterr = 0;
			$nb4xx5xxerr = 0;
			$nb2xx3xx = 0;
			$nb0xx = 0;
			foreach ($threads as $t) 
			{
				$r = $t->getHttpReturnedCode();
				$nbhttptr = $nbhttptr + @$r['tot'] + 0;
				$nbneterr = $nbneterr + @$r['net'] + 0;
				$nb4xx5xxerr = $nb4xx5xxerr + @$r['4XX'] + @$r['5XX'];
				$nb2xx3xx = $nb2xx3xx + @$r['2XX'] + @$r['3XX'];
				$nb0xx = $nb0xx + @$r['0XX']; // Software triggered error
				$nbrunnedscenario += $t->getNbRunnedScenario();
			}
			printf("%3d %% | Running %4d scenario at %6.3f hits/s | % 4d http transaction (%4d ok, %4d failed)\r", 
				100 * ($elapsed / $this->duration),
				$nbrunnedscenario,
				$nbhttptr / $elapsed,
				$nbhttptr,
				$nb2xx3xx,
				$nbneterr + $nb4xx5xxerr + $nb0xx
			);
			if ( $this->duration - $elapsed < 2 && $sleep != USLEEP_100MS )
				// Only 2 seconds lefts !
				$sleep = USLEEP_100MS; // force 100ms sleep to have better accuracy at the end
			if ( $elapsed >= $this->duration)
				break;
		}

		printf("\nSend a stop signal to the thread\n", $this->duration);
		foreach ($threads as $t) {
			$t->stop = true;
			$t->callStop();
		}

		// Attendre que tous les threads aient fini:
		echo "Wait for thread to finish...\n";
		foreach ($threads as $i=>$t) {
		  $t->join();
		}
		$loadDuration = microtime(true) - $begining;
		// wait for the browser to finish
		printf("Duration of this load : %.3f seconds\n", $loadDuration);
	}	
}

class LoadStartRampingBrowsersWithFixedStepDuration extends Load
{
	var $fromnbbrowser, $tonbbrowser, $step, $stepduration;
	public function __construct($fromnbbrowser, $tonbbrowser, $step, $stepduration)
	{
		$this->fromnbbrowser = $fromnbbrowser;
		$this->tonbbrowser = $tonbbrowser;
		$this->step = $step;
		$this->stepduration = $stepduration;
		$this->duration = (1 + ($tonbbrowser - $fromnbbrowser)/$step) * $stepduration;
		$this->nbbrowsers = max($tonbbrowser,$fromnbbrowser);
		if ($this->duration < 0)
			throw new PhuseyException("Computed and invalid load duration");
		$this->loadid = (Load::$loadidcounter++);
	}
	public function execute($testidentifier, $scenario)
	{
		printf("Preparing %d threads\n", $this->nbbrowsers);
		for($i = 1; $i <= $this->tonbbrowser; $i ++)
		{
			if ($i <= $this->fromnbbrowser)
			{
				// This thread have to start right now
				$delay = 0;
			}
			else
			{
                $n = round(($i - $this->fromnbbrowser) / $this->step);
                $delay = $n * $this->stepduration;
			}
			$t = new BrowserThreaded(
				new BrowserTask($testidentifier, $scenario, $this, $i, $delay, 0)
			);
			$t->start();
			$threads[] = $t;
		}
		printf("Let the threads run for %d seconds...\n", $this->duration);
		$begining = microtime(true);
		$sleep = USLEEP_1S;
		while(true)
		{
			usleep($sleep);
			$elapsed = microtime(true) - $begining;
			// Collect statistics
			printf("%3d %% | ", 
				100 * ($elapsed / $this->duration)
			);
			foreach($threads as $i=>$t)
			{
				echo $t->getStatusShort();
			}
			echo "\r";
			if ( $this->duration - $elapsed < 2 && $sleep != USLEEP_100MS )
				// Only 2 seconds left !
				$sleep = USLEEP_100MS; // force 100ms sleep to have better accuracy at the end
			if ( $elapsed >= $this->duration)
				break;
		}
		printf("\nSend a stop signal to the thread\n", $this->duration);
		foreach ($threads as $t) {
			$t->stop = true;
			$t->callStop();
		}

		// Attendre que tous les threads aient fini:
		echo "Wait for thread to finish...\n";
		foreach ($threads as $i=>$t) {
		  $t->join();
		}
		$loadDuration = microtime(true) - $begining;
		// wait for the browser to finish
		printf("Duration of this load : %.3f seconds\n", $loadDuration);
	}
}

class LoadStopRampingBrowsersWithFixedStepDuration extends Load
{
	var $fromnbbrowser, $tonbbrowser, $step, $stepduration;
	public function __construct($fromnbbrowser, $tonbbrowser, $step, $stepduration)
	{
		$this->fromnbbrowser = $fromnbbrowser;
		$this->tonbbrowser = $tonbbrowser;
		$this->step = $step;
		$this->stepduration = $stepduration;
		$this->duration = (1 + ($fromnbbrowser - $tonbbrowser)/$step) * $stepduration;
		$this->nbbrowsers = max($tonbbrowser,$fromnbbrowser);
		if ($this->duration < 0)
			throw new PhuseyException("Computed and invalid load duration");
		$this->loadid = (Load::$loadidcounter++);
	}

	public function execute($testidentifier, $scenario)
	{
        printf("Preparing %d threads\n", $this->nbbrowsers);
        $threads = array();
		for($i = $this->fromnbbrowser; $i >= 1 ; $i --)
		{
			$delay = 4;
			if ($i <= $this->tonbbrowser)
			{
				$delay = $this->duration;
			}
			else
			{	
				$n = round((1 + $this->fromnbbrowser - $i) / $this->step);
				$delay = $n * $this->stepduration;
			}
			$t = new BrowserThreaded(
				new BrowserTask($testidentifier, $scenario, $this, $i, 0, 0, $delay)
			);
			//echo "Thread $i start now and last $delay\n";
			$t->start();
			$threads[] = $t;
		}
		printf("Let the threads run for %d seconds...\n", $this->duration);
		$begining = microtime(true);
		$sleep = USLEEP_1S;
		while(true)
		{
			usleep($sleep);
			$elapsed = microtime(true) - $begining;
			// Collect statistics
			
			printf("%3d %% | ", 
				100 * ($elapsed / $this->duration)
			);
			foreach($threads as $i=>$t)
			{
				echo $t->getStatusShort();
			}
			echo "\r";
			if ( $this->duration - $elapsed < 2 && $sleep != USLEEP_100MS )
				// Only 2 seconds left !
				$sleep = USLEEP_100MS; // force 100ms sleep to have better accuracy at the end
			if ( $elapsed >= $this->duration)
				break;
		}
		printf("\nSend a stop signal to the thread\n", $this->duration);
		foreach ($threads as $t) {
			$t->stop = true;
			$t->callStop();
		}

		// Attendre que tous les threads aient fini:
		echo "Wait for thread to finish...\n";
		foreach ($threads as $i=>$t) {
		  $t->join();
		}
		$loadDuration = microtime(true) - $begining;
		// wait for the browser to finish
		printf("Duration of this load : %.3f seconds\n", $loadDuration);
	}
}

/**
class LoadRampingBrowsers extends Load
{
	var $fromnbbrowser, $tonbbrowser, $step, $duration;
	var $stepduration;
	public function __construct($fromnbbrowser, $tonbbrowser, $step, $duration)
	{
		$this->nbbrowsers = max($tonbbrowser,$fromnbbrowser);
		$this->fromnbbrowser = $fromnbbrowser;
		$this->tonbbrowser = $tonbbrowser;
		$this->step = $step;
		$this->duration = $duration;
		$this->stepduration = $duration / ( ($tonbbrowser - $fromnbbrowser) / $step );
	}
	
	public function execute($scenario)
	{
		printf("Not implemented yet\n");
		printf("Main program is thread %d\n", Thread::getCurrentThreadId());
		printf("Start %d threads\n", $this->nbbrowsers);
		$threads = array();
		for ($i=$this->fromnbbrowser; $i < $this->tonbbrowser; $i = $i + $this->step)
		{
			printf("%d during %f\n", $i, $this->stepduration);
			
		}
	}
}
**/
