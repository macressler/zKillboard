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

class cli_every15 implements cliCommand
{
	public function getDescription()
	{
		return "Tasks that needs to run every 15 minutes. |g|Usage: every15";
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
		if (Util::isMaintenanceMode()) return;
		$minute = date("i");
                if (!in_array("-f", $parameters) && $minute != 15) return;

		global $baseDir;

		$p = array();
		$numDays = 7;
		$p["limit"] = 10;
		$p["pastSeconds"] = $numDays * 86400;
		$p["kills"] = true;

		Storage::store("Kills5b+", json_encode(Kills::getKills(array("iskValue" => 5000000000), true, false)));
		Storage::store("Kills10b+", json_encode(Kills::getKills(array("iskValue" => 10000000000), true, false)));

		Storage::store("TopChars", json_encode(Info::doMakeCommon("Top Characters", "characterID", Stats::getTopPilots($p))));
		Storage::store("TopCorps", json_encode(Info::doMakeCommon("Top Corporations", "corporationID", Stats::getTopCorps($p))));
		Storage::store("TopAllis", json_encode(Info::doMakeCommon("Top Alliances", "allianceID", Stats::getTopAllis($p))));
		Storage::store("TopShips", json_encode(Info::doMakeCommon("Top Ships", "shipTypeID", Stats::getTopShips($p))));
		Storage::store("TopSystems", json_encode(Info::doMakeCommon("Top Systems", "solarSystemID", Stats::getTopSystems($p))));
		Storage::store("TopIsk", json_encode(Stats::getTopIsk(array("pastSeconds" => ($numDays*86400), "limit" => 5))));
		Storage::store("TopPods", json_encode(Stats::getTopIsk(array("groupID" => 29, "pastSeconds" => ($numDays*86400), "limit" => 5))));
		Storage::store("TopPoints", json_encode(Stats::getTopPoints("killID", array("losses" => true, "pastSeconds" => ($numDays*86400), "limit" => 5))));

                // Clean up the related killmails cache
                $cache = new FileCache($baseDir . "/cache/related/");
                $cache->cleanUp();

		// Cleanup the overall file cache
		$fc = new FileCache();
		$fc->cleanup();

	}
}
