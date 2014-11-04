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

class cli_updateCCPData implements cliCommand
{
	public function getDescription()
	{
		return "Fetches the latest data from Fuzzysteve's dump, and imports the data";
	}

	public function getAvailMethods()
	{
		return ""; // Space seperated list
	}

	public function execute($parameters, $db)
	{
		global $baseDir, $dbUser, $dbPassword, $dbName, $dbHost, $dbSocket;

		$url = "https://www.fuzzwork.co.uk/dump/";
		$cacheDir = $baseDir . "cache/update";

		// If the cache dir doesn't exist, create it
		if(!file_exists($cacheDir))
			mkdir($cacheDir);

		// Fetch the md5 from Fuzzysteves dump
		$md5file = "mysql-latest.tar.bz2.md5";
		$data = file_get_contents($url . $md5file);
		$data = explode(" ", $data);

		$md5 = $data[0];

		$lastSeenMD5 = Storage::retrieve("ccpdataMD5", null);

		// The md5 hasn't been seen.. Time to update the db bro!
		if($lastSeenMD5 != $md5)
		{
			CLI::out("|g|Updating the ccp_ database tables with the latest release from fuzzwork.co.uk|n|");
			$dbFiles = array("dgmAttributeCategories", "dgmAttributeTypes", "dgmEffects", "dgmTypeAttributes", "dgmTypeEffects", "invFlags", "invGroups", "invTypes", "mapDenormalize", "mapRegions", "mapSolarSystems");
			$type = ".sql.bz2";

			// Now run through each db table, and insert them !
			foreach($dbFiles as $file)
			{
				CLI::out("|g|Importing:|n| $file");
				$dataURL = $url . "latest/" . $file . $type;

				// Get and extract, it's simpler to use execs for this, than to actually do it with php
				try
				{
					exec("wget -q $dataURL -O $cacheDir/$file$type");
					exec("bzip2 -q -d $cacheDir/$file$type");
				}
				catch(Exeception $e)
				{
					CLI::out("There was an error at $file: ". $e->getMessage(), true);
				}

				// Now get the sql so we can alter a few things
				$data = file_get_contents($cacheDir . "/" . $file . ".sql");

				// Systems and regions need to be renamed
				if($file == "mapRegions")
					$name = "regions";
				if($file == "mapSolarSystems")
					$name = "systems";

				if(isset($name))
					$data = str_replace($file, "ccp_$name", $data);
				else
					$data = str_replace($file, "ccp_$file", $data);

				// Store the data as an sql file
				file_put_contents($cacheDir . "/temporary.sql", $data);

				// Create the exec line
				if(isset($dbHost))
					$execLine = "mysql -u$dbUser -p$dbPassword -h $dbHost $dbName < $cacheDir/temporary.sql";
				elseif(isset($dbSocket))
					$execLine = "mysql -u$dbUser -p$dbPassword -S $dbSocket $dbName < $cacheDir/temporary.sql";
				// Now we execute the exec line.. It's not ideal, but it works..
				exec($execLine);

				// Delete the temporary file
				unlink("$cacheDir/temporary.sql");

				// Delete the .sql file
				unlink("$cacheDir/$file.sql");

				// Done
				CLI::out("Done with |g|$file|n|, moving on to the next table");
			}

			// Insert the md5 hash
			Storage::store("ccpdataMD5", $md5);
		}
	}
}
