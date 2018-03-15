<?php

/**
* Code from Symfony/Component/Console/Output/StreamOutput.php
*/
function hasColorSupport()
{
    if (DIRECTORY_SEPARATOR == '\\') {
        return 0 >= version_compare('10.0.10586', PHP_WINDOWS_VERSION_MAJOR.'.'.PHP_WINDOWS_VERSION_MINOR.'.'.PHP_WINDOWS_VERSION_BUILD)
        || false !== getenv('ANSICON')
        || 'ON' === getenv('ConEmuANSI')
        || 'xterm' === getenv('TERM');
    }
    return function_exists('posix_isatty') && @posix_isatty(STDOUT);
}


/**
 ** @params : optionName is a sting or an array of string ; needValue if option have to be followed by a value
 ** Return default if option is not on the command line of the app
 ** Return true if option is set on the command line and needValue = false (like "-opt")
 ** Return a string with the option value if needValue = true (like "-opt value")
 ** Return null if the option do not have a value and if needValue = true
 **/
function console_arg_get_option($optionName, $needValue = false, $default = null)
{
	global $argv;
	$value = $default;
	if (is_string($optionName))
		$optionName = array($optionName);
	foreach($optionName as $opt_name)
	{
		$i = 1; // ignore arg0 (exe)
		while($i < count($argv))
		{
			$o = $argv[$i];
			if ($o == $opt_name)
			{  // option on the cmd line
				if ($needValue === true)
				{
					//$value = $argv[$i + 1];
					//$i++;
					if (isset($argv[$i+1]))
						return $argv[$i+1];
					else
						return null;
				}
				else
				{
					//$value = true;
					return true;
				}
			}
			$i++;
		}
	}
	return $value;
}

function console_print_if_verbose($str, $verbose)
{
	if ($verbose === true || $verbose >=1)
		echo $str;
}


if (!hasColorSupport())
{
	function console_red_start()
	{
		//echo "\033[91m";
		fwrite(STDOUT, "[");
	}
	function console_normal()
	{
		//echo "\033[0m";
		fwrite(STDOUT, "]");
	}
	function console_error($e, $errorCode = null)
	{
		//echo "\033[91m", $e, "\033[0m";
		fwrite(STDERR, "!!!:$e"); 
		if ($errorCode != null)
			exit($errorCode);
	}

	function console_warning($e)
	{
		fwrite(STDERR, "/!\:$e"); // ⚠
	}

	function console_ok($msg)
	{
		fwrite(STDOUT, "OK :$e"); // ✓
	}
}
else
{
	function console_red_start()
	{
		//echo "\033[91m";
		fwrite(STDOUT, "\033[91m");
	}
	function console_normal()
	{
		//echo "\033[0m";
		fwrite(STDOUT, "\033[0m");
	}
	function console_error($e, $errorCode = null)
	{
		//echo "\033[91m", $e, "\033[0m";
		fwrite(STDERR, "\033[91m$e\033[0m");
		if ($errorCode != null)
			exit($errorCode);
	}

	function console_warning($e)
	{
		fwrite(STDERR, "\033[91m$e\033[0m");
	}

	function console_ok($msg)
	{
		fwrite(STDOUT, "\033[92$e\033[0m");
	}

}
