#!/usr/bin/env php
<?php
/* zKillboard
 * Copyright (C) 2012-2013 EVE-KILL Team and EVSCO.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

$cle = "cli" == php_sapi_name();
if (!$cle) return; // Prevent web execution

if(getenv("SILENT_CLI"))
{
    ob_start("obCallback");
    ob_implicit_flush();
}

$base = dirname(__FILE__);

require_once( "config.php" );

require_once( "init.php" );

array_shift($argv);
$command = array_shift($argv);

if($command == "bashList")
	listCommands();

try
{
	$fileName = "$base/cli/cli_$command.php";
	if ($command == "") CLI::out("|r|You haven't issued a command to execute.|n| Please use list to show all commands, or help <command> to see information on how to use the command", true);
	if(!file_exists($fileName)) CLI::out("|r|Error running $command|n|. Please use list to show all commands, or help <command> to see information on how to use the command", true);

	require_once $fileName;
	$className = "cli_$command";
	$class = new $className();

	if(!is_a($class, "cliCommand")) CLI::out("|r| Module $command does not implement interface cliCommand", true);
	Db::execute("set session wait_timeout = 600");
	$db = new Db();
	$class->execute($argv, $db);
}
catch (Exception $ex)
{
	CLI::out("$command ended with error: " . $ex->getMessage(), true);
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

function listCommands()
{
	$commands = array();
	$dir = __DIR__."/cli/";

	if($handle = opendir($dir))
	{
		while(false !== ($entry = readdir($handle)))
		{
			if($entry != "." && $entry != ".." && $entry != "base.php" && $entry != "cli_methods.php")
			{
				$s1 = explode("cli_", $entry);
				$s2 = explode(".php", @$s1[1]);
				if(sizeof($s2) == 2)
				{
					require_once "$dir/$entry";
					$command = $s2[0];
					$className = "cli_$command";
					$class = new $className();
					if(is_a($class, "cliCommand"))
					{
						$commands[] = $command;
					}
				}
			}
		}
		closedir($handle);
	}
	sort($commands);
	CLI::out(implode(" ", $commands), true);
}

function obCallback($buf)
{
    // Maybe log it somewhere, but for now just throw it away.
    return "";
}
