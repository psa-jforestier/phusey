<?php
include_once('curl.php');
class Step
{
	public function execute($p)
	{
		throw new Exception('execute() must be implemented on class '.get_class($this));
	}
	public function explain()
	{
		throw new Exception('explain() must be implemented on class '.get_class($this).' and must return a string of 12 char length max');
	}
	public function __toString()
	{
		return get_class($this);
	}
}

class StepClearCookie extends Step
{
	public function execute($p)
	{
		if ($p instanceof Curl)
		{
			$p->clearCookie();
		}
		return null;
	}
	
	public function explain()
	{
		return "Clear cookie";
	}
}

class StepHttp extends Step
{
	var $url = null;
	public function execute($p)
	{
	}
}

class StepHttpGetDummy extends StepHttp
{
	static $dummyResult = NULL;
	
	public function __construct()
	{
		if (StepHttpGetDummy::$dummyResult == NULL)
		{
			StepHttpGetDummy::$dummyResult = new CURLHttpResult();
			StepHttpGetDummy::$dummyResult->http_code = -1;
		}
	}
	public function execute($p)
	{
		return true;
		//return StepHttpGetDummy::$dummyResult;
		//return new CURLHttpResult();
	}
	public function explain()
	{
		return "Dummy";
	}
}

class StepHttpGet extends StepHttp
{
    var $headers = array();
	public function __construct($url, $headers)
	{
        $this->url = $url;
        $this->headers = $headers;
	}
	public function execute($p)
	{
		if ($p instanceof Curl)
		{
			return $p->get($this->url, $this->headers);
		}
		else
			return null;
	}
	
	public function explain()
	{
		return "HTTP Get";
	}
}

class StepHttpPost extends StepHttp
{
    var $params = array();
    var $headers = array();
	public function __construct($url, $params, $headers)
	{
		$this->url = $url;
        $this->params = $params;
        $this->headers = $headers;
	}
	public function execute($p)
	{
		if ($p instanceof Curl)
		{
			return $p->post($this->url, $this->params, $this->headers);
		}
		else
			return null;
	}
	public function explain()
	{
		return "HTTP Post";
	}
}

class StepStartTransaction extends Step
{
	var $name;
	public function __construct($name)
	{
		$this->name = $name;
	}
	public function execute($p)
	{
	}
	
	public function explain()
	{
		return "Start transaction [ ".$this->name;
	}
}

class StepStopTransaction extends Step
{
	public function __construct()
	{
	}
	public function execute($p)
	{
	}
	public function explain()
	{
		return "] Stop transaction";
	}
}

class StepPause extends Step
{
	var $pausems;
	public function __construct($pausems)
	{
		$this->pausems = $pausems;
	}
	public function execute($p)
	{
		usleep(1000 * $this->pausems); // convert ms to us
	}
	public function explain()
	{
		return "Pause";
	}
}
