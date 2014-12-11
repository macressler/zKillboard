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

class Stats
{

	public static function getTopPilots($parameters = array(), $allTime = false)
	{
		$parameters["cacheTime"] = 3600;
		return self::getTop("characterID", $parameters, $allTime);
	}

	public static function getTopPointsPilot($parameters = array(), $allTime = false)
	{
		$parameters["cacheTime"] = 3600;
		return self::getTopPoints("characterID", $parameters, $allTime);
	}

	public static function getTopCorps($parameters = array(), $allTime = false)
	{
		$parameters["cacheTime"] = 3600;
		return self::getTop("corporationID", $parameters, $allTime);
	}

	public static function getTopPointsCorp($parameters = array(), $allTime = false)
	{
		$parameters["cacheTime"] = 3600;
		return self::getTopPoints("corporationID", $parameters, $allTime);
	}

	public static function getTopAllis($parameters = array(), $allTime = false)
	{
		$parameters["cacheTime"] = 3600;
		return self::getTop("allianceID", $parameters, $allTime);
	}

	public static function getTopFactions($parameters = array(), $allTime = false)
	{
		$parameters["cacheTime"] = 3600;
		return self::getTop("factionID", $parameters, $allTime);
	}

	public static function getTopPointsAlli($parameters = array(), $allTime = false)
	{
		$parameters["cacheTime"] = 3600;
		return self::getTopPoints("allianceID", $parameters, $allTime);
	}

	public static function getTopShips($parameters = array(), $allTime = false)
	{
		$parameters["cacheTime"] = 3600;
		return self::getTop("shipTypeID", $parameters, $allTime);
	}

	public static function getTopGroups($parameters = array(), $allTime = false)
	{
		$parameters["cacheTime"] = 3600;
		return self::getTop("groupID", $parameters, $allTime);
	}

	public static function getTopWeapons($parameters = array(), $allTime = false)
	{
		$parameters["cacheTime"] = 3600;
		return self::getTop("weaponTypeID", $parameters, $allTime);
	}

	public static function getTopSystems($parameters = array(), $allTime = false)
	{
		$parameters["cacheTime"] = 3600;
		return self::getTop("solarSystemID", $parameters, $allTime);
	}

	public static function getTopRegions($parameters = array(), $allTime = false)
	{
		$parameters["cacheTime"] = 3600;
		return self::getTop("regionID", $parameters, $allTime);
	}

	/**
	 * @param string $groupByColumn
	 */
	public static function getTopPoints($groupByColumn, $parameters = array(), $allTime = false)
	{
		$whereClauses = array();
		$tables = array();
		Filters::buildFilters($tables, $whereClauses, $whereClauses, $parameters, $allTime);
		$whereClauses[] = "characterID != 0";

		// Remove 0 values
		$whereClauses[] = "$groupByColumn != 0";
		if ($groupByColumn == "corporationID") $whereClauses[] = "$groupByColumn > 6000000";

		$limit = array_key_exists("limit", $parameters) ? (int)$parameters["limit"] : 10;

		$query = "select $groupByColumn, sum(kills) kills from (select killID gg, $groupByColumn, points kills from ";
		if (sizeof(array_unique($tables)) > 1) die("Multiple table joins not ready in Stats just yet");
		$query .= implode(",", array_unique($tables));
		if (sizeof($whereClauses) > 0) $query .= " where " . implode(" and ", $whereClauses);

		$npcFilter = $groupByColumn == "corporationID" ? "where $groupByColumn > 6000000" : "";
		$query .= " group by killID, $groupByColumn) as f $npcFilter group by $groupByColumn order by 2 desc limit $limit";

		$result = Db::query($query, array(), 3600);
		$data = array();
		foreach ($result as $row) $data[] = Info::addInfo($row);
		unset($result);
		return $data;
	}

	public static function getTopIsk($parameters = array(), $allTime = false)
	{
		$parameters["orderBy"] = "p.total_price";
		if (!isset($parameters["limit"])) $parameters["limit"] = 5;
		return Kills::getKills($parameters);
	}

	private static $extendedGroupColumns = array("characterID"); //, "corporationID"); //, "allianceID");

	/**
	 * @param string $groupByColumn
	 */
	private static function getTop($groupByColumn, $parameters = array(), $allTime = false)
	{
		$whereClauses = array();
		$tables = array();
		$tables[] = "zz_participants p";
		Filters::buildFilters($tables, $whereClauses, $whereClauses, $parameters, $allTime);
		$whereClauses[] = "characterID != 0";

		// Remove 0 values
		$whereClauses[] = "$groupByColumn != 0";
		if ($groupByColumn == "corporationID") $whereClauses[] = "$groupByColumn > 6000000";

		$limit = array_key_exists("limit", $parameters) ? (int)$parameters["limit"] : 10;

		$tablePrefixes = array();
		if (sizeof($tables) > 1) {
			foreach($tables as $table) $tablePrefixes[] = substr($table, strlen($table) - 1, 1) . ".killID";
		}

		$query = "select $groupByColumn, count(distinct p.killID) kills from ";
		$query .= implode(" left join ", array_unique($tables));
		if (sizeof($tables) > 1) $query .= " on (" . implode(" = ", $tablePrefixes) . ") ";
		if (sizeof($whereClauses) > 0) $query .= " where " . implode(" and ", $whereClauses);

		$query .= " group by 1 order by 2 desc limit $limit";

		$cacheTime = isset($parameters["cacheTime"]) ? (int)$parameters["cacheTime"] : 3600;
		if ($cacheTime < 30) $cacheTime = 30;
		$result = Db::query($query, array(), $cacheTime);
		$data = array();
		foreach ($result as $row) $data[] = Info::addInfo($row);
		unset($result);
		//if (sizeof($data) <= 1) return self::getExtendedTop($groupByColumn, $parameters, $allTime);
		return $data;
	}

	private static function getExtendedTop($groupByColumn, $parameters = array(), $allTime = false)
	{
		$whereClauses = array();
		$tables = array();
		$tables[] = "zz_participants p";
		Filters::buildFilters($tables, $whereClauses, $whereClauses, $parameters, $allTime);

		// Remove 0 values
		$whereClauses[] = "p.$groupByColumn != 0";
		$whereClauses[] = "x.$groupByColumn != 0";

		$limit = array_key_exists("limit", $parameters) ? (int)$parameters["limit"] : 10;

		$query = "select x.$groupByColumn, count(distinct x.killID) kills from zz_participants x left join zz_participants p on (x.killID = p.killID)";
		$whereClauses[] = "x.killID = p.killID";
		$whereClauses[] = "x.isVictim = 'F'";
		$query .= " where " . implode(" and ", $whereClauses);

		$query .= " group by 1 order by 2 desc limit $limit";

		$result = Db::query($query, array(), 3600);
		$data = array();
		foreach ($result as $row) $data[] = Info::addInfo($row);
		unset($result);
		return $data;
	}

	public static function calcStats($killID, $adding = true)
	{
		$modifier = $adding ? 1 : -1;

		$victim = Db::queryRow("select * from zz_participants where isVictim != 0 and killID = :killID", array(":killID" => $killID));
		$chars = Db::query("select characterID, shipTypeID, groupID from zz_participants where isVictim = 0 and killID = :killID", array(":killID" => $killID));
		$corps = Db::query("select distinct corporationID from zz_participants where isVictim = 0 and killID = :killID", array(":killID" => $killID));
		$allis = Db::query("select distinct allianceID from zz_participants where isVictim = 0 and killID = :killID", array(":killID" => $killID));
		$factions = Db::query("select distinct factionID from zz_participants where isVictim = 0 and killID = :killID", array(":killID" => $killID));

		$groupID = isset($victim["groupID"]) ? $victim["groupID"] : 0;
		$points = isset($victim["points"]) ? $modifier * $victim["points"] : 0;
		$isk = isset($victim["total_price"]) ? $modifier * $victim["total_price"] : 0;

		if ($victim) {
			self::statLost("pilot", $victim["characterID"], $groupID, $modifier, $points, $isk);
			self::statLost("corp", $victim["corporationID"], $groupID, $modifier, $points, $isk);
			self::statLost("alli", $victim["allianceID"], $groupID, $modifier, $points, $isk);
			self::statLost("faction", $victim["factionID"], $groupID, $modifier, $points, $isk);
			self::statLost("ship", $victim["shipTypeID"], $groupID, $modifier, $points, $isk);
			self::statLost("group", $victim["groupID"], $groupID, $modifier, $points, $isk);
			self::statLost("system", $victim["solarSystemID"], $groupID, $modifier, $points, $isk);
			self::statLost("region", $victim["regionID"], $groupID, $modifier, $points, $isk);
		}

		$shipTypes = array();
		$groups = array();
		foreach($chars as $char) {
			self::statDestroyed("pilot", $char["characterID"], $groupID, $modifier, $points, $isk);
			if (!in_array($char["shipTypeID"], $shipTypes)) {
				self::statDestroyed("ship", $char["shipTypeID"], $groupID, $modifier, $points, $isk);
				$shipTypes[] = $char["shipTypeID"];
			}
			if (!in_array($char["groupID"], $groups)) {
				self::statDestroyed("group", $char["groupID"], $groupID, $modifier, $points, $isk);
				$groups[] = $char["groupID"];
			}
		}
		foreach($corps as $corp) self::statDestroyed("corp", $corp["corporationID"], $groupID, $modifier, $points, $isk);
		foreach($allis as $alli) self::statDestroyed("alli", $alli["allianceID"], $groupID, $modifier, $points, $isk);
		foreach($factions as $faction) self::statDestroyed("faction", $faction["factionID"], $groupID, $modifier, $points, $isk);

		if ($modifier == -1) {
			Db::execute("delete from zz_participants where killID = :killID", array(":killID" => $killID));
		}
	}

	/**
	 * @param string $type
	 */
	private static function statLost($type, $typeID, $groupID, $modifier, $points, $isk)
	{
		if ($typeID == 0) return;
		Db::execute("insert into zz_stats (type, typeID, groupID, lost, pointsLost, iskLost) values (:type, :typeID, :groupID, :modifier, :points, :isk) on duplicate key update lost = lost + :modifier, pointsLost = pointsLost + :points, iskLost = iskLost + :isk", array(":type" => $type, ":typeID" => $typeID, ":groupID" => $groupID, ":modifier" => $modifier, ":points" => $points, ":isk" => $isk));
	}

	/**
	 * @param string $type
	 */
	private static function statDestroyed($type, $typeID, $groupID, $modifier, $points, $isk)
	{
		if ($typeID == 0) return;
		Db::execute("insert into zz_stats (type, typeID, groupID, destroyed, pointsDestroyed, iskDestroyed) values (:type, :typeID, :groupID, :modifier, :points, :isk) on duplicate key update destroyed = destroyed + :modifier, pointsDestroyed = pointsDestroyed + :points, iskDestroyed = iskDestroyed + :isk", 	array(":type" => $type, ":typeID" => $typeID, ":groupID" => $groupID, ":modifier" => $modifier, ":points" => $points, ":isk" => $isk));
	}
}
