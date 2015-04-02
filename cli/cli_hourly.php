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

class cli_hourly implements cliCommand
{
	public function getDescription()
	{
		return "Tasks that needs to run every hour. |g|Usage: hourly";
	}

	public function getAvailMethods()
	{
		return "";
	}

	public function getCronInfo()
	{
		return array(0 => ""); // Run every minute but let the code decide the top of the hour
	}

	public function execute($parameters, $db)
	{
		$minute = date("i");
		if ($minute != 0 && !in_array('-f', $parameters)) return;

		global $enableAnalyze;

		$actualKills = Storage::retrieve("ActualKillCount");
		$iteration = 0;
		while ($actualKills > 0) {
			$iteration++;
			$actualKills -= 1000000;
			if ($actualKills > 0 && Storage::retrieve("{$iteration}mAnnounced", null) == null) {
				Storage::store("{$iteration}mAnnounced", true);
				$message = "|g|Woohoo!|r| $iteration million kills surpassed!";
				Log::irc($message);
				Log::ircAdmin($message);
			}
		}

		$highKillID = $db->queryField("select max(killID) highKillID from zz_killmails", "highKillID");
		if ($highKillID > 1000000) Storage::store("notRecentKillID", ($highKillID - 1000000));

		self::apiPercentage($db);

		$db->execute("delete from zz_api_log where requestTime < date_sub(now(), interval 2 hour)");
		//$db->execute("update zz_killmails set kill_json = '' where processed = 2 and killID < 0 and kill_json != ''");
		$db->execute("delete from zz_errors where date < date_sub(now(), interval 1 day)");

		// Ensure char/corp tables know about all char/corps from API
		$db->execute("insert ignore into zz_characters (characterID) select distinct characterID from zz_api_characters");
		$db->execute("insert ignore into zz_corporations (corporationID) select distinct corporationID from zz_api_characters where corporationID > 0");

		// Ensure ship search links to the ship itself
		$db->execute("insert ignore into ccp_zship_search select distinct shipTypeID from zz_participants where shipTypeID != 0");

		$fileCache = new FileCache();
		$fileCache->cleanup();

		$tableQuery = $db->query("show tables", array(), 0, false);
		$tables = array();
		foreach($tableQuery as $row) {
			foreach($row as $column) $tables[] = $column;
		}

		if($enableAnalyze)
		{
			$tableisgood = array("OK", "Table is already up to date", "The storage engine for the table doesn't support check");
			$count = 0;
			foreach ($tables as $table)
			{
				$count++;

				$result = $db->queryRow("analyze table $table", array(), 0, false);
				if (!in_array($result["Msg_text"], $tableisgood)) Log::ircAdmin("|r|Error analyzing table |g|$table|r|: " . $result["Msg_text"]);
else Log::log("Analyzed $table");
			}
		}

	}

	private static function apiPercentage($db)
	{
		$percentage = Storage::retrieve("LastHourPercentage", 10);
		$row = $db->queryRow("select sum(if(errorCode = 0, 1, 0)) good, sum(if(errorCode != 0, 1, 0)) bad from zz_api_characters");
		$good = $row["good"];
		$bad = $row["bad"];
		if ($bad > (($bad + $good) * ($percentage / 100))) {
			if($percentage > 15)
				Log::irc("|r|API gone haywire?  Over $percentage% of API's reporting an error atm.");
			$percentage += 5;
		} else if ($bad < (($bad + $good) * (($percentage - 5) / 100))) $percentage -= 5;
		if ($percentage < 10) $percentage = 10;
		Storage::store("LastHourPercentage", $percentage);
	}
}
