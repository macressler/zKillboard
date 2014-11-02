#!/usr/bin/env php
<?php

if(php_sapi_name() != "cli")
	die("This is a cli script!");

if(!extension_loaded('pcntl'))
	die("This script needs the pcntl extension!");

$base = __DIR__;
require_once( "config.php" );
require_once( "init.php" );

// Don't run the cronjobs if maintenance mode is on.
if (Util::isMaintenanceMode())
{
	Log::log("Maintenance Mode, cron will not run till it's over.");
	die();
}

interface cliCommand {
	/**
	 * @return string
	 */
	public function getDescription();

	/**
	 * @return string
	 */
	public function getAvailMethods();

	/**
	 * @return void
	 */
	public function execute($parameters, $db);
}

$cronInfo = array();

$files = scandir("$base/cli");
foreach($files as $file)
{
	if(!preg_match("/^cli_(.+)\\.php$/", $file, $match))
		continue;

	$command = $match[1];
	$className = "cli_$command";
	require_once "$base/cli/$file";

	if(!is_subclass_of($className, "cliCommand"))
		continue;

	if(!method_exists($className, "getCronInfo"))
		continue;

	$class = new $className();
	$cronInfo[$command] = $class->getCronInfo();
	unset($class);
}

if(file_exists("$base/cron.overrides"))
{
	$overrides = file_get_contents("$base/cron.overrides");
	$overrides = json_decode($overrides, true);

	foreach($overrides as $command => $info)
	{
		foreach($info as $f)
			if($f != "disabled")
				$cronInfo[$command] = $info;
	}
}

foreach($cronInfo as $command => $info)
	foreach($info as $interval => $arguments)
		runCron($command, $interval, $arguments);

function runCron($command, $interval, $args)
{
	global $base;
	$curTime = time();

	if(is_array($args))
		array_unshift($args, $command);
	else if($args != "")
		$args = explode(" ", "$command $args");
	else
		$args = array($command);

	$cronName = implode(".", $args);
	$locker = "lastCronRun.$cronName";
	$lastRun = (int)Storage::retrieve($locker, 0);
	$dateFormat = "D M j G:i:s T Y";

	if($curTime - $lastRun < $interval)
		return;

	global $debug;
	if ($debug)
		Log::log("Cron $cronName running at ".date($dateFormat, $curTime));

	Storage::store($locker, $curTime);

	$pid = pcntl_fork();
	if($pid < 0)
	{
		Storage::store($locker, $lastRun);
		return;
	}

	if($pid != 0)
		return;

	putenv("SILENT_CLI=1");
	pcntl_exec("$base/cliLock.sh", $args);
	Storage::store($locker, $lastRun);
	die("Executing $command failed!");
}
