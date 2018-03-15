<?php
error_reporting(E_ALL);
if (@$_REQUEST['posted'] == 'true')
{
	if (count($_POST) == 0)
	{
		http_response_code(500);
		echo "<h1>500</h1>";
		echo "<pre>\n";
		throw new Exception("You indicate posted=true in the url, but this request was sent with a ".$_SERVER['REQUEST_METHOD']);
	}
}

$speed = @$_REQUEST['speed'];
if ($speed == 'fast' )
{
	echo "<h1>200</h1>Damn fast !";
	die;
}
elseif ($speed == 'slow_randomized' )
{
	$from = @$_REQUEST['from'] + 0.0;
	$to = @$_REQUEST['to'] + 0.0;
	echo "<h1>200</h1>A little slow randomized !";
	$t = rand(1000 * $from, 1000 * $to) / 1000;
	echo "<br>Wait $t s";
	usleep(1000 * 1000 * $t);
	die;
}
$reply = @$_REQUEST['reply'];
if ($reply == 'redirect')
{
	header('Location: index.php?speed=fast');
	die;
}
if ($reply == 'err_randomized')
{
	$codes = [401,403,404,500,503];
	$code = $codes[rand(0, count($codes) - 1)];
	http_response_code($code);
	echo "<h1>$code</h1>KO !<pre>";
	throw new Exception("This page intentionnaly crashed !");
}
if ($reply == 'exception')
{
	http_response_code(200);
	echo "<h1>200</h1>OK but ...<pre>";
	throw new Exception("This page intentionnaly crashed !");
}
if ($reply == 'warning')
{
	http_response_code(200);
	echo "<h1>200</h1>OK but ...<pre>";
	echo "This page should display a Notice and a Warning.<br/>";
	echo "Use triggerAnErrorIfBodyContains method with ['.*Notice.*on line.*', '.*Warning.*on line.*'] to detect them during the test";
	if ($ThisVariableIsUnkown != 0)
		echo "!";
	trigger_error("Run-time warnings (non-fatal errors). Execution of the script is not halted.",  E_USER_WARNING);

	die;
}
?>
<html>
<h1>OK</h1>
<pre>
Params :
?posted=true : to test POST verb
?speed=fast : answer as fast as possible in php
?speed=slow_randomized&from=n.nnn&to=n.nnn : answer with a randomize time (in second)
?reply=redirect : reply with a 302 to ?speed=fast
?reply=err_randomized : reply with a random error (401,403,404,500,503)
?reply=exception : reply with a 200 but with a PHP exception
?reply=warning : reply with a 200 but with a PHP warning and notice
</html>
