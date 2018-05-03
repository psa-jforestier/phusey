<?php

class CURLHttpResult 
{
	//use \danog\Serializable;
	var $url; // Requested url. Can be != than info->url if redirection
	var $responseBody;
	var $responseHeaders; // CURL do not have a simple way to get headers.
	var $errno;
	var $errstr;
	var $info; // CurlInfo
	var $http_code;
	var $stepid;
	var $workloadid;
	var $threadnumber;
	var $http_method; // 'G'et 'P'ost
	var $starttime; // timestamp in s
	var $beforecall; // timestamp including ms
	var $aftercall;  // timestamp including ms
}
class CURL
{
	var $ch = null;
	var $curlopt;
	var $noproxyfor = array();
	var $proxyHostPort;
	var $proxyUserPass;
	var $cookiejarfile;

	var $result;
	public function __construct()
	{
		$this->ch = curl_init();
		$this->curlopt = array(
			CURLOPT_MAXREDIRS=>5,
			CURLOPT_RETURNTRANSFER=>true,
			CURLOPT_SSL_VERIFYHOST=>false,
			CURLOPT_SSL_VERIFYPEER=>false,
			CURLOPT_FOLLOWLOCATION=>true,
			CURLOPT_USERAGENT=>'Mozilla/5.0 (PHUSEY Load Tester)',
			CURLOPT_HTTPHEADER=>array(
				"Cache-Control: no-cache"
			)
		);
	}
	public function noProxyFor(array $hostnames) // array of preg expression to match with the requested hostname
	{
		$this->noproxyfor = $hostnames;
	}
	public function setAutoProxy()
	{
		// Guess for a proxy
		$p = NULL;
		if (getenv('HTTP_PROXY') != '')
			$p = getenv('HTTP_PROXY');
		else if (getenv('HTTPS_PROXY') != '')
			$p = getenv('HTTPS_PROXY');
		if ($p !== NULL)
		{
			$p = parse_url($p);
			$this->setProxy($p['host'].':'.$p['port'], $p['user'].':'.$p['pass']);
		}
		else
		{
			$this->unsetProxy();
		}
	}
	// host:port, user:pass
	public function setProxy($hostPort, $userPass)
	{
		$this->setProxyHostPort($hostPort);
		$this->setProxyUserPass($userPass);
	}
	public function setProxyHostPort($hostPort)
	{
		$this->proxyHostPort = $hostPort;
		$this->curlopt[CURLOPT_PROXY]=$hostPort;
	}
	public function setProxyUserPass($userPass)
	{
		$this->proxyUserPass = $userPass;
		$this->curlopt[CURLOPT_PROXYUSERPWD]=$userPass;
	}
	
	public function unsetProxy()
	{
		$this->curlopt[CURLOPT_PROXY] = '';
		unset($this->curlopt[CURLOPT_PROXYUSERPWD]);
	}
	public function setTimeout($t)
	{
		$this->curlopt[CURLOPT_TIMEOUT] = $t;
	}
	private function modifyProxyIfNeeded($url)
	{
		if (count($this->noproxyfor) === 0)
			return;
		$hostname = parse_url($url, PHP_URL_HOST);
		$this->setProxy($this->proxyHostPort, $this->proxyUserPass);
		foreach($this->noproxyfor as $noproxyfor)
		{
			if (preg_match($noproxyfor, $hostname))
			{
				//$this->curlopt[CURLOPT_HTTPPROXYTUNNEL] = false;
				$this->unsetProxy();
				return;
			}
		}
		
		
	}
	public function addOptions(array $opts)
	{
		$this->curlopt = $this->curlopt + $opts;
	}
	public function get($url)
	{
		global $VERBOSE;
		if ($VERBOSE === true)
		{
			echo "GET $url\n";
		}
		$this->modifyProxyIfNeeded($url);
		$this->result = new CURLHttpResult();
		$this->result->http_method = 'G';
		$this->result->url = $url;
		curl_setopt_array($this->ch, $this->curlopt);
		curl_setopt($this->ch, CURLOPT_URL, $url);
		$this->result->starttime = time();
		$this->result->beforecall = microtime(true);
		$r = curl_exec($this->ch);
		$this->result->aftercall = microtime(true);
		if ($r === false)
		{
			$this->result->errno = curl_errno($this->ch);
			$this->result->errstr = curl_error($this->ch);
		}
		else
			$this->result->responseBody = $r;
		$this->result->info = curl_getinfo($this->ch);
		$this->result->http_code = $this->result->info['http_code'];
		return $this->result;
	}
	
	/**
	 ** if $params is an array, post will be in multipart
	 **/
	public function post($url, $params)
	{
		global $VERBOSE;
		if ($VERBOSE === true)
		{
			echo "POST $url\n";
		}
		$this->modifyProxyIfNeeded($url);
		$this->result = new CURLHttpResult();
		$this->result->http_method = 'P';
		$this->result->url = $url;
		curl_setopt_array($this->ch, $this->curlopt);
		curl_setopt($this->ch, CURLOPT_POST, true);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS , $params);
		curl_setopt($this->ch, CURLOPT_URL, $url);
		$this->result->starttime = time();
		$this->result->beforecall = microtime(true);
		$r = curl_exec($this->ch);
		$this->result->aftercall = microtime(true);
		if ($r === false)
		{
			$this->result->errno = curl_errno($this->ch);
			$this->result->errstr = curl_error($this->ch);
		}
		else
			$this->result->responseBody = $r;
		$this->result->info = curl_getinfo($this->ch);
		$this->result->http_code = $this->result->info['http_code'];
		return $this->result;
	}
	
	public function setCookieJar($cookiejarfile)
	{
		$this->addOptions(
			array(CURLOPT_COOKIEJAR => $cookiejarfile, CURLOPT_COOKIEFILE => $cookiejarfile)
		);
		$this->cookiejarfile = $cookiejarfile;
	}
	
	public function clearCookie()
	{
		if (file_exists($this->cookiejarfile))
			unlink($this->cookiejarfile);
	}
	
	public function close()
	{
		curl_close($this->ch);
		$this->ch = null;
	}

}
/**
$r = new CURLHttpResult();
$r->responseBody = "HELLO WORLD";
$s = serialize($r);
var_dump($s);
$r2 = unserialize($s);
var_dump($r2);
echo "\n";
echo "r = ";
$r->errstr="err !!";
var_dump($r);
echo "r2 = ";var_dump($r2);
**/
/**
$c = new CURL();
$c->setTimeout(1);
$c->setCookieJar("cookie.txt");
	$c->clearCookie();
$c->setAutoProxy();
$c->noProxyFor(array('/localhost/'));
$c->get('http://localhost:80/session.php');
var_dump($c);
**/
