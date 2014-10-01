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

class cli_populateCharacters implements cliCommand
{
	public function getDescription()
	{
		return "Populates the Character tables. |g|Usage: populateCharacters";
	}

	public function getAvailMethods()
	{
		return "";
	}

	public function getCronInfo()
	{
		return array(0 => "");
	}

	public function execute($parameters, $db)
	{
		self::populateCharacters($db);
	}

	private static function populateCharacters($db)
	{
		global $baseDir;

		$timer = new Timer();
		$maxTime = 65 * 1000;

		// Reset 222's that are over a week old
		$db->execute("update zz_api set errorCode = 0 where errorCode = 222 and lastValidation <= date_sub(now(), interval 7 day)");

		$apiCount = $db->queryField("select count(*) count from zz_api where errorCode not in (203, 220, 222) and lastValidation <= date_add(now(), interval 1 minute)", "count", array(), 0);
		if ($apiCount == 0) return;

		$fetchesPerSecond = 10;
		$iterationCount = 0;

		while ($timer->stop() < $maxTime) {
			$keyIDs = $db->query("select distinct keyID from zz_api where errorCode not in (203, 220, 222) and lastValidation < date_sub(now(), interval 2 hour) order by lastValidation, dateAdded desc limit 100", array(), 0);

			foreach($keyIDs as $row) {
				if (Util::isMaintenanceMode()) return;
				$keyID = $row["keyID"];
				$m = $iterationCount % $fetchesPerSecond;
				$db->execute("update zz_api set lastValidation = date_add(lastValidation, interval 5 minute) where keyID = :keyID", array(":keyID" => $keyID));
				$command = "flock -w 60 $baseDir/cache/locks/populate.$m php5 $baseDir/cli.php apiFetchCharacters " . escapeshellarg($keyID);
				//Log::log("$command");
				exec("$command >/dev/null 2>/dev/null &");
				$iterationCount++;
				if ($iterationCount % $fetchesPerSecond == 0) sleep(1);
			}
			sleep(1);
		}
	}
}
