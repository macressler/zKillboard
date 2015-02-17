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

/**
 * Parser for raw killmails from ingame EVE.
 */

class Parser
{
	public static function parseKills()
	{
		global $debug, $parseAscending;
		if (Util::isMaintenanceMode()) return;
		if (!isset($parseAscending)) $parseAscending = true;

		$timer = new Timer();

		$maxTime = 65 * 1000 ;

		Db::execute("set session wait_timeout = 120000");
		Db::execute("create temporary table if not exists zz_participants_temporary select * from zz_participants where 1 = 0");

		$numKills = 0;

		if ($debug) Log::log("Fetching kills for processing...");
		while ($timer->stop() < $maxTime) {
			if (Util::isMaintenanceMode()) {
				self::removeTempTables();
				return;
			}
			Db::execute("delete from zz_participants_temporary");

			$minMax = $parseAscending ? "min" : "max";
			if (date("Gi") < 105) $minMax = "min"; // Override during CREST cache interval
			$id = Db::queryField("select $minMax(killID) killID from zz_killmails where processed = 0 and killID > 0", "killID", array(), 0);

			if ($id === null) $id = Db::queryField("select min(killID) killID from zz_killmails where processed = 0", "killID", array(), 0);
			if ($id === null) {
				sleep(1);
				continue;
			}

			$result = array();
			$result[] = Db::queryRow("select * from zz_killmails where killID = :killID", array(":killID" => $id), 0);

			$processedKills = array();
			$cleanupKills = array();
			foreach ($result as $row) {
				$kill = json_decode(Killmail::get($row["killID"]), true);
				if (!isset($kill["killID"])) {
					if ($debug) Log::log("Problem with kill " . $row["killID"]);
					Db::execute("update zz_killmails set processed = 2 where killid = :killid", array(":killid" => $row["killID"]));
					continue;
				}
				$killID = $kill["killID"];
				$hash = $row["hash"];

				// Because of CREST caching and the want for accurate prices, don't process the first hour
				// of kills until after 01:05 each day
				if (date("Gi") < 105 && $kill["killTime"] >= date("Y-m-d 00:00:00"))
				{
					sleep(1);
					continue;
				}

				// Cleanup if we're reparsing
				$cleanupKills[] = $killID;
				$numKills++;
				if ($debug) Log::log("Processing kill $killID");

				if ($killID < 0) { // Manual mail, make sure we aren't duping an api verified mail
					$apiVerified= Db::queryField("select count(1) count from zz_killmails where hash = :hash and killID > 0", "count", array(":hash" => $hash), 0);
					if ($apiVerified) {
						Stats::calcStats($killID, false);
						Db::execute("delete from zz_killmails where killID = :killID", array(":killID" => $killID));
						continue;
					}
				}
				if ($killID > 0) { // Check for manual mails to remove
					$manualMailIDs = Db::query("select killID from zz_killmails where hash = :hash and killID < 0", array(":hash" => $hash), 0);
					foreach($manualMailIDs as $row) {
						$manualMailID = $row["killID"];
						Stats::calcStats($manualMailID, false);
						Db::execute("delete from zz_killmails where killID = :killID", array(":killID" => $manualMailID));
					}

				}

				// Do some validation on the kill
				if (!self::validKill($kill)) {
					Db::execute("update zz_killmails set processed = 3 where killid = :killid", array(":killid" => $row["killID"]));
					continue;
				}

				$totalCost = 0;
				$itemInsertOrder = 0;

				$totalCost += self::processItems($kill, $killID, $kill["items"], $itemInsertOrder);
				$totalCost += self::processVictim($kill, $killID, $kill["victim"], false);
				foreach ($kill["attackers"] as $attacker) self::processAttacker($kill, $killID, $attacker, $kill["victim"]["shipTypeID"], $totalCost);
				$points = Points::calculatePoints($killID, true);
				Db::execute("update zz_participants_temporary set points = :points, number_involved = :numI, total_price = :tp where killID = :killID", array(":killID" => $killID, ":points" => $points, ":numI" => sizeof($kill["attackers"]), ":tp" => $totalCost));

				$processedKills[] = $killID;
			}

			if (sizeof($cleanupKills)) {
				Db::execute("delete from zz_participants where killID in (" . implode(",", $cleanupKills) . ")");
			}
			Db::execute("insert ignore into zz_participants select * from zz_participants_temporary");
			$numProcessed = sizeof($processedKills);
			if ($numProcessed) {
				Db::execute("insert ignore into zz_stats_queue values (" . implode("), (", $processedKills) . ")");
				Db::execute("update zz_killmails set processed = 1 where killID in (" . implode(",", $processedKills) . ")");
			}
		}
		if ($numKills > 0)
		{
			Log::log("Processed $numKills kills");
			Db::execute("insert into zz_storage (locker, contents) values ('KillsAdded', :num) on duplicate key update contents = contents + :num", array(":num" => $numKills));
		}
		self::removeTempTables();
	}

	private static function removeTempTables()
	{
		Db::execute("drop table if exists zz_participants_temporary");
	}

	private static function validKill(&$kill)
	{
		// Show all pod kills
		$victimShipID = $kill["victim"]["shipTypeID"];
		if ($victimShipID == 670 || $victimShipID == 33328) return true;

		$npcOnly = true;
		$victimCorp = $kill["victim"]["corporationID"] < 1000999 ? 0 : $kill["victim"]["corporationID"];
		$victimAlli = $kill["victim"]["allianceID"];

		$blueOnBlue = true;
		foreach ($kill["attackers"] as $attacker) {
			$attackerGroupID = Info::getGroupID($attacker["shipTypeID"]);
			if ($attackerGroupID == 365) return true; // A tower is involved

			if (isset($attacker["factionID"]) && $attacker["factionID"] == 500021) return true;

			// Don't process the kill if it's NPC only
			$npcOnly &= $attacker["characterID"] == 0 && ($attacker["corporationID"] < 1999999 && $attacker["corporationID"] != 1000125);

			// Check for blue on blue
			if ($attacker["characterID"] != 0) $blueOnBlue &= $victimCorp == $attacker["corporationID"] && $victimAlli == $attacker["allianceID"];
		}
		if ($npcOnly /*|| $blueOnBlue*/) return false;

		return true;
	}

	/**
	 * @param boolean $isNpcVictim
	 */
	private static function processVictim(&$kill, $killID, &$victim, $isNpcVictim)
	{
		$dttm = (string) $kill["killTime"];

		$shipPrice = Price::getItemPrice($victim["shipTypeID"], $dttm, true);
		$groupID = Info::getGroupID($victim["shipTypeID"]);
		$regionID = Info::getRegionIDFromSystemID($kill["solarSystemID"]);

		if (!$isNpcVictim) Db::execute("
				insert into zz_participants_temporary
				(killID, solarSystemID, regionID, isVictim, shipTypeID, groupID, shipPrice, damage, factionID, allianceID,
				 corporationID, characterID, dttm, vGroupID)
				values
				(:killID, :solarSystemID, :regionID, 1, :shipTypeID, :groupID, :shipPrice, :damageTaken, :factionID, :allianceID,
				 :corporationID, :characterID, :dttm, :vGroupID)",
				(array(
				       ":killID" => $killID,
				       ":solarSystemID" => $kill["solarSystemID"],
				       ":regionID" => $regionID,
				       ":shipTypeID" => $victim["shipTypeID"],
				       ":groupID" => $groupID,
				       ":vGroupID" => $groupID,
				       ":shipPrice" => $shipPrice,
				       ":damageTaken" => $victim["damageTaken"],
				       ":factionID" => $victim["factionID"],
				       ":allianceID" => $victim["allianceID"],
				       ":corporationID" => $victim["corporationID"],
				       ":characterID" => $victim["characterID"],
				       ":dttm" => $dttm,
				      )));

		Info::addChar($victim["characterID"], $victim["characterName"]);
		Info::addCorp($victim["corporationID"], $victim["corporationName"]);
		Info::addAlli($victim["allianceID"], $victim["allianceName"]);

		return $shipPrice;
	}

	private static function processAttacker(&$kill, &$killID, &$attacker, $victimShipTypeID, $totalCost)
	{
		$victimGroupID = Info::getGroupID($victimShipTypeID);
		$attackerGroupID = Info::getGroupID($attacker["shipTypeID"]);
		$regionID = Info::getRegionIDFromSystemID($kill["solarSystemID"]);

		$dttm = (string) $kill["killTime"];

		Db::execute("
				insert into zz_participants_temporary
				(killID, solarSystemID, regionID, isVictim, characterID, corporationID, allianceID, total_price, vGroupID,
				 factionID, damage, finalBlow, weaponTypeID, shipTypeID, groupID, dttm)
				values
				(:killID, :solarSystemID, :regionID, 0, :characterID, :corporationID, :allianceID, :total, :vGroupID,
				 :factionID, :damageDone, :finalBlow, :weaponTypeID, :shipTypeID, :groupID, :dttm)",
				(array(
				       ":killID" => $killID,
				       ":solarSystemID" => $kill["solarSystemID"],
				       ":regionID" => $regionID,
				       ":characterID" => $attacker["characterID"],
				       ":corporationID" => $attacker["corporationID"],
				       ":allianceID" => $attacker["allianceID"],
				       ":factionID" => $attacker["factionID"],
				       ":damageDone" => $attacker["damageDone"],
				       ":finalBlow" => $attacker["finalBlow"],
				       ":weaponTypeID" => $attacker["weaponTypeID"],
				       ":shipTypeID" => $attacker["shipTypeID"],
				       ":groupID" => $attackerGroupID,
				       ":dttm" => $dttm,
				       ":total" => $totalCost,
				       ":vGroupID" => $victimGroupID,
				      )));
		Info::addChar($attacker["characterID"], $attacker["characterName"]);
		Info::addCorp($attacker["corporationID"], $attacker["corporationName"]);
		Info::addAlli($attacker["allianceID"], $attacker["allianceName"]);
	}

	/**
	 * @param integer $itemInsertOrder
	 */
	private static function processItems(&$kill, &$killID, &$items, &$itemInsertOrder, $isCargo = false, $parentFlag = 0)
	{
		$totalCost = 0;
		foreach ($items as $item) {
			$totalCost += self::processItem($kill, $killID, $item, $itemInsertOrder++, $isCargo, $parentFlag);
			if (@is_array($item["items"])) {
				$itemContainerFlag = $item["flag"];
				$totalCost += self::processItems($kill, $killID, $item["items"], $itemInsertOrder, true, $itemContainerFlag);
			}
		}
		return $totalCost;
	}

	/**
	 * @param integer $itemInsertOrder
	 */
	private static function processItem(&$kill, &$killID, &$item, $itemInsertOrder, $isCargo = false, $parentContainerFlag = -1)
	{
		$dttm = (string) $kill["killTime"];

		$itemName = Db::queryField("select typeName from ccp_invTypes where typeID = :typeID", "typeName", array(":typeID" => $item["typeID"]));
		if ($itemName == null) $itemName = "TypeID $typeID";
		$typeID = $item["typeID"];

		if ($item["typeID"] == 33329 && $item["flag"] == 89) $price = 0.01; // Golden pod implant can't be destroyed
		else $price = Price::getItemPrice($typeID, $dttm, true);
		if ($isCargo && strpos($itemName, "Blueprint") !== false) $item["singleton"] = 2;
		if ($item["singleton"] == 2) {
			$price = $price / 100;
		}

		return ($price * ($item["qtyDropped"] + $item["qtyDestroyed"]));
	}
}
