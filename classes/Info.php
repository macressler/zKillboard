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

class Info
{
	/**
	 * Retrieve the system id of a solar system.
	 *
	 * @static
	 * @param string $systemName
	 * @return int The solarSystemID
	 */
	public static function getSystemID($systemName)
	{
		return Db::queryField("select solarSystemID from ccp_systems where solarSystemName = :name", "solarSystemID",
				array(":name" => $systemName), 3600);
	}

	/**
	 * @static
	 * @param int $systemID
	 * @return array Returns an array containing the solarSystemName and security of a solarSystemID
	 */
	public static function getSystemInfo($systemID)
	{
		return Db::queryRow("select solarSystemName, security, sunTypeID from ccp_systems where solarSystemID = :systemID",
				array(":systemID" => $systemID), 3600);
	}

	/**
	 * Fetches information for a wormhole system
	 * @param  int $systemID
	 * @return array
	 */
	public static function getWormholeSystemInfo($systemID)
	{
		if ($systemID < 3100000) return;
		return Db::queryRow("select * from ccp_zwormhole_info where solarSystemID = :systemID",
				array(":systemID" => $systemID), 3600);
	}

	/**
	 * @static
	 * @param int $systemID
	 * @return string The system name of a solarSystemID
	 */
	public static function getSystemName($systemID)
	{
		$systemInfo = self::getSystemInfo($systemID);
		return $systemInfo['solarSystemName'];
	}

	/**
	 * @static
	 * @param int $systemID
	 * @return double The system secruity of a solarSystemID
	 */
	public static function getSystemSecurity($systemID)
	{
		$systemInfo = self::getSystemInfo($systemID);
		return $systemInfo['security'];
	}

	/**
	 * @static
	 * @param int $typeID
	 * @return string The item name.
	 */
	public static function getItemName($typeID)
	{
		$name = Db::queryField("select typeName from ccp_invTypes where typeID = :typeID", "typeName",
				array(":typeID" => $typeID), 3600);
		if ($name === null) {
			if ($typeID >= 500000) return "TypeID $typeID"; //throw new Exception("hey now");
			Db::execute("insert ignore into ccp_invTypes (typeID, typeName) values (:typeID, :typeName)",
					array(":typeID" => $typeID, ":typeName" => "TypeID $typeID"));
			$name = "TypeID $typeID";
		}
		return $name;
	}

	/**
	 * @param string $itemName
	 * @return int The typeID of an item.
	 */
	public static function getItemID($itemName)
	{
		return Db::queryField("select typeID from ccp_invTypes where typeName = :typeName", "typeID",
				array(":typeName" => $itemName), 3600);
	}

	/**
	 * Retrieves the effectID of an item.	This is useful for determining if an item is fitted into a low,
	 * medium, high, rig, or t3 slot.
	 *
	 * @param int $typeID
	 * @return int The effectID of an item.
	 */
	public static function getEffectID($typeID)
	{
		return Db::queryField("select effectID from ccp_dgmTypeEffects where typeID = :typeID and effectID in (11, 12, 13, 2663, 3772)", "effectID",
				array(":typeID" => $typeID), 3600);
	}

	/**
	 * Retrieves the name of a corporation ID
	 *
	 * @param string $name
	 * @return int The corporationID of a corporation
	 */
	public static function getCorpID($name)
	{
		$id = Db::queryField("select corporationID from zz_corporations where name = :name order by memberCount desc limit 1", "corporationID",
				array(":name" => $name), 3600);

		if ($id == null || $id == 0) {
			try {
				$pheal = Util::getPheal();
				$pheal->scope = "eve";
				$charInfo = $pheal->CharacterID(array("names" => $name));
				foreach ($charInfo->characters as $char) {
					$id = (int)$char->characterID;
					if ($id != 0) {
						// Verify that this is indeed a character
						$pheal->scope = "corp";
						$name = $charInfo->corporationName;
						// If not a corporation an error would have been thrown and caught by the catch
						Db::execute("insert ignore into zz_corporations (corporationID, name) values (:id, :name)",
								array(":id" => $id, ":name" => $name));
					}
				}
			}
			catch (Exception $ex) {
				$id = 0;
			}
		}
		return $id;
	}

	/**
	 * @param int $allianceID
	 * @return array
	 */
	public static function getCorps($allianceID)
	{
		$corpList = Db::query("select * from zz_corporations where allianceID = :alliID order by name",
				array(":alliID" => $allianceID));

		$retList = array();
		foreach ($corpList as $corp) {
			$count = Db::queryField("select count(1) count from zz_api_characters where isDirector = 'T' and corporationID = :corpID", "count", array(":corpID" => $corp["corporationID"]));
			$corp["apiVerified"] = $count > 0 ? 1 : 0;

			if ($count) {
				$errors = Db::query("select errorCode from zz_api_characters where isDirector = 'T' and corporationID = :corpID",
						array(":corpID" => $corp["corporationID"]));
				$corp["keyCount"] = sizeof($errors);
				$errorValues = array();
				foreach ($errors as $error) $errorValues[] = $error["errorCode"];
				$corp["errors"] = implode(", ", $errorValues);
				$corp["cachedUntil"] = Db::queryField("select min(cachedUntil) cachedUntil from zz_api_characters where isDirector = 'T' and corporationID = :corpID", "cachedUntil",
						array(":corpID" => $corp["corporationID"]));
				$corp["lastChecked"] = Db::queryField("select max(lastChecked) lastChecked from zz_api_characters where isDirector = 'T' and corporationID = :corpID", "lastChecked",
						array(":corpID" => $corp["corporationID"]));
			}
			else {
				$count = Db::queryField("select count(*) count from zz_api_characters where corporationID = :corpID", "count",
						array(":corpID" => $corp["corporationID"]));
				$percentage = $corp["memberCount"] == 0 ? 0 : $count / $corp["memberCount"];
				if ($percentage == 1) $corp["apiVerified"] = 1;
				else if ($percentage > 0) $corp["apiPercentage"] = number_format($percentage * 100, 1);
			}
			self::addInfo($corp);
			$retList[] = $corp;
		}
		return $retList;
	}

	/**
	 * Gets corporation stats
	 * @param  int $allianceID
	 * @param  array $parameters
	 * @return array
	 */
	public static function getCorpStats($allianceID, $parameters)
	{
		$corpList = Db::query("SELECT * FROM zz_corporations WHERE allianceID = :alliID ORDER BY name", array(":alliID" => $allianceID));
		$statList = array();
		foreach($corpList as $corp)
		{
			$parameters["corporationID"] = $corp["corporationID"];
			$data = self::getCorpDetails($corp["corporationID"], $parameters);
			$statList[$corp["name"]]["corporationName"] = $data["corporationName"];
			$statList[$corp["name"]]["corporationID"] = $data["corporationID"];
			$statList[$corp["name"]]["ticker"] = $data["cticker"];
			$statList[$corp["name"]]["members"] = $data["memberCount"];
			$statList[$corp["name"]]["ceoName"] = $data["ceoName"];
			$statList[$corp["name"]]["ceoID"] = $data["ceoID"];
			$statList[$corp["name"]]["kills"] = $data["shipsDestroyed"];
			$statList[$corp["name"]]["killsIsk"] = $data["iskDestroyed"];
			$statList[$corp["name"]]["killPoints"] = $data["pointsDestroyed"];
			$statList[$corp["name"]]["losses"] = $data["shipsLost"];
			$statList[$corp["name"]]["lossesIsk"] = $data["iskLost"];
			$statList[$corp["name"]]["lossesPoints"] = $data["pointsLost"];
			if($data["iskDestroyed"] != 0 || $data["iskLost"] != 0)
				$statList[$corp["name"]]["effeciency"] = $data["iskDestroyed"] / ($data["iskDestroyed"] + $data["iskLost"]) * 100;
			else $statList[$corp["name"]]["effeciency"] = 0;
		}
		return $statList;
	}

	/**
	 * Adds an alliance
	 * @param int $id
	 * @param string $name
	 */
	public static function addAlli($id, $name)
	{
		if ($id <= 0) return;
		$exists = Db::queryField("select count(1) count from zz_alliances where allianceID = :id", "count", array(":id" => $id));
		if ($exists == 0) Db::execute("insert ignore into zz_alliances (allianceID, name) values (:id, :name)", array(":id" => $id, ":name" => $name));
	}

	/**
	 * Gets an alliance name
	 * @param  int $id
	 * @return string
	 */
	public static function getAlliName($id)
	{
		return Db::queryField("select name from zz_alliances where allianceID = :id order by memberCount desc limit 1", "name",
				array(":id" => $id), 3600);
	}

	/**
	 * [getFactionTicker description]
	 * @param  string $ticker
	 * @return string|null
	 */
	public static function getFactionTicker($ticker)
	{
		$data = array(
			"caldari"	=> array("factionID" => "500001", "name" => "Caldari State"), 
			"minmatar"	=> array("factionID" => "500002", "name" => "Minmatar Republic"), 
			"amarr"		=> array("factionID" => "500003", "name" => "Amarr Empire"), 
			"gallente"	=> array("factionID" => "500004", "name" => "Gallente Federation")
			);

		if (isset($data[$ticker])) return $data[$ticker];
		return null;
	}

	/**
	 * [getFactionID description]
	 * @param  string $name
	 * @return string|bool
	 */
	public static function getFactionID($name)
	{
		$data = Db::queryRow("select * from ccp_zfactions where name = :name", array(":name" => $name));
		return isset($data["factionID"]) ? $data["factionID"] : null;
	}

	/**
	 * [getFactionName description]
	 * @param  int $id
	 * @return string|false
	 */
	public static function getFactionName($id)
	{
		$data = Db::queryRow("select * from ccp_zfactions where factionID = :id", array(":id" => $id));
		return isset($data["name"]) ? $data["name"] : "Faction $id";
	}

	/**
	 * [getRegionName description]
	 * @param  int $id
	 * @return string
	 */
	public static function getRegionName($id)
	{
		$data = Db::queryField("select regionName from ccp_regions where regionID = :id", "regionName",
				array(":id" => $id), 3600);
		return $data;
	}

	/**
	 * [getRegionID description]
	 * @param  string $name
	 * @return string
	 */
	public static function getRegionID($name)
	{
		return Db::queryField("select regionID from ccp_regions where regionName = :name", "regionID",
				array(":name" => $name), 3600);
	}

	/**
	 * [getRegionIDFromSystemID description]
	 * @param  int $systemID
	 * @return int
	 */
	public static function getRegionIDFromSystemID($systemID)
	{
		$regionID = Db::queryField("select regionID from ccp_systems where solarSystemID = :systemID", "regionID",
				array(":systemID" => $systemID), 3600);
		return $regionID;
	}

	/**
	 * [getRegionInfoFromSystemID description]
	 * @param  int $systemID
	 * @return array
	 */
	public static function getRegionInfoFromSystemID($systemID)
	{
		$regionID = Db::queryField("select regionID from ccp_systems where solarSystemID = :systemID", "regionID",
				array(":systemID" => $systemID), 3600);
		return Db::queryRow("select * from ccp_regions where regionID = :regionID", array(":regionID" => $regionID), 3600);
	}

	/**
	 * [getShipId description]
	 * @param  string $name
	 * @return int
	 */
	public static function getShipId($name)
	{
		$shipID = Db::queryField("select typeID from ccp_invTypes where typeName = :name", "typeID",
				array(":name" => $name), 3600);
		return $shipID;
	}

	/**
	 * Attempt to find the name of a corporation in the corporations table.	If not found then attempt to pull the name via an API lookup.
	 *
	 * @static
	 * @param int $id
	 * @return string The name of the corp if found, null otherwise.
	 */
	public static function getCorpName($id)
	{
		$name = Db::queryField("select name from zz_corporations where corporationID = :id", "name",
				array(":id" => $id), 3600);
		if ($name != null) return $name;
		return "Corporation $id";
	}

	public static function getCorporationTicker($corporationID)
	{
		$pheal = Util::getPheal();
		$pheal->scope = "corp";

		$data = $pheal->CorporationSheet(array("corporationID" => $corporationID));
		return $data->ticker;
	}

	/**
	 * [getAlliID description]
	 * @param  string $name
	 * @return string
	 */
	public static function getAlliID($name)
	{
		return Db::queryField("select allianceID from zz_alliances where name = :name order by memberCount desc limit 1", "allianceID",
				array(":name" => $name), 3600);
	}

	public static function getAllianceTicker($allianceID)
	{
		return Db::queryField("SELECT ticker FROM zz_alliances WHERE allianceID = :allianceID", "ticker", array(":allianceID" => $allianceID));
	}

	/**
	 * [getCharID description]
	 * @param  string $name
	 * @return int
	 */
	public static function getCharID($name)
	{
		if (Bin::get("s:$name", null) != null) return Bin::get("s:$name", null);
		$id = (int)Db::queryField("select characterID from zz_characters where name = :name order by corporationID desc", "characterID",
				array(":name" => $name), 3600);
		if ($id == 0 || $id == NULL) {
			try {
					$pheal = Util::getPheal();
					$pheal->scope = "eve";
					$charInfo = $pheal->CharacterID(array("names" => $name));
					foreach ($charInfo->characters as $char) {
						$id = $char->characterID;
						if ($id != 0) {
							// Verify that this is indeed a character
							$charInfo = $pheal->CharacterInfo(array("characterid" => $id));
							// If not a character an error would have been thrown and caught by the catch
							$name = $charInfo->characterName;
							Db::execute("insert ignore into zz_characters (characterID, name) values (:id, :name)",
									array(":id" => $id, ":name" => $name));
						}
					}
			}
			catch (Exception $ex) {
				$id = 0;
			}
		}
		Bin::set("s:$name", $id);
		return $id;
	}

	/**
	 * [addChar description]
	 * @param int $id
	 * @param string $name
	 */
	public static function addChar($id, $name)
	{
		if ($id <= 0) return;
		$exists = Db::queryField("select count(1) count from zz_characters where characterID = :id", "count", array(":id" => $id));
		if ($exists == 0) Db::execute("insert ignore into zz_characters (characterID, name) values (:id, :name)", array(":id" => $id, ":name" => $name));
	}

	/**
	 * Attempt to find the name of a character in the characters table.	If not found then attempt to pull the name via an API lookup.
	 *
	 * @static
	 * @param int $id
	 * @return string The name of the corp if found, null otherwise.
	 */
	public static function getCharName($id)
	{
		$name = Db::queryField("select name from zz_characters where characterID = :id", "name", array(":id" => $id), 3600);
		if ($name != null) return $name;
		if ($id < 39999999) return ""; // Don't try to look up invalid characterID's

		try {
			$pheal = Util::getPheal();
			$pheal->scope = "eve";
			$charInfo = $pheal->CharacterInfo(array("characterid" => $id));
			$name = $charInfo->characterName;
			if ($name != null) { //addName($id, $name, 1, 1, null);
				Db::execute("insert ignore into zz_characters (characterID, name) values (:id, :name)",
						array(":id" => $id, ":name" => $name));
			}
		} catch (Exception $ex) {
			return $id;
		}
		return $name;
	}

	/**
	 * Character affiliation
	*/
	public static function getCharacterAffiliations($characterID)
	{
		$pheal = Util::getPheal();
		$pheal->scope = "eve";

		$affiliations = $pheal->CharacterAffiliation(array("ids" => $characterID));

		$corporationID = $affiliations->characters[0]->corporationID;
		$corporationName = $affiliations->characters[0]->corporationName;
		$allianceID = $affiliations->characters[0]->allianceID;
		$allianceName = $affiliations->characters[0]->allianceName;

		// Get the ticker for corp and alliance
		$corporationTicker = Info::getCorporationTicker($corporationID);
		$allianceTicker = Info::getAllianceTicker($allianceID);

		return array("corporationID" => $corporationID, "corporationName" => $corporationName, "corporationTicker" => $corporationTicker, "allianceID" => $allianceID, "allianceName" => $allianceName, "allianceTicker" => $allianceTicker);

	}

	/**
	 * [getGroupID description]
	 * @param  int $id
	 * @return int
	 */
	public static function getGroupID($id)
	{
		$groupID = Db::queryField("select groupID from ccp_invTypes where typeID = :id", "groupID",
				array(":id" => $id), 3600);
		if ($groupID === null) return 0;
		return $groupID;
	}

	/**
	 * [getGroupIdFromName description]
	 * @param  int $id
	 * @return int
	 */
	public static function getGroupIdFromName($id)
	{
		$groupID = Db::queryField("select groupID from ccp_invGroups where groupName = :id", "groupID",
				array(":id" => $id), 3600);
		if ($groupID === null) return 0;
		return $groupID;
	}

	/**
	 * Get the name of the group
	 *
	 * @static
	 * @param int $groupID
	 * @return string
	 */
	public static function getGroupName($groupID)
	{
		$name = Db::queryField("select groupName from ccp_invGroups where groupID = :id", "groupName",
				array(":id" => $groupID), 3600);
		return $name;
	}

	/**
	 * @param string $search
	 */
	private static function findEntitySearch(&$resultArray, $type, $query, $search)
	{
		$results = Db::query("${query}", array(":search" => $search), 3600);
		self::addResults($resultArray, $type, $results);
	}

	/**
	 * [addResults description]
	 * @param array $resultArray
	 * @param string $type
	 * @param array|null $results
	 */
	private static function addResults(&$resultArray, $type, $results)
	{
		if ($results != null) foreach ($results as $result) {
			$keys = array_keys($result);
			$result["type"] = $type;
			$value = $result[$keys[0]];
			$resultArray["$type|$value"] = $result;
		}
	}

	/**
	 * [$entities description]
	 * @var array
	 */
	private static $entities = array(
			array("faction", "SELECT factionID, factionID id FROM ccp_zfactions WHERE name "),
			array("faction", "SELECT factionID, factionID id FROM ccp_zfactions WHERE ticker "),
			array("alliance", "SELECT allianceID, allianceID id FROM zz_alliances WHERE name "),
			array("alliance", "SELECT allianceID, allianceID id FROM zz_alliances WHERE ticker ", true),
			array("corporation", "SELECT corporationID, corporationID id FROM zz_corporations WHERE name "),
			array("corporation", "SELECT corporationID, corporationID id FROM zz_corporations WHERE ticker ", true),
			array("character", "SELECT characterID, characterID id FROM zz_characters WHERE name "),
			array("item", "select typeID, typeID id from ccp_invTypes where published = 1 and typeName ", true),
			array("system", "select solarSystemID, solarSystemID id from ccp_systems where solarSystemName "),
			array("region", "select regionID, regionID id from ccp_regions where regionName "),
			);

	/**
	 * Search for an entity
	 *
	 * @static
	 * @param string $search
	 * @return string
	 */
	public static function findEntity($search, $exact = false)
	{
		$search = trim($search);
		if (!isset($search)) return "";

		$names = array();
		for ($i = 0; $i <= 1; $i++) {
			if ($i == 1 && $exact) continue;
			$match = $i == 0 ? " = " : " like ";
			foreach (self::$entities as $entity) {
				$type = $entity[0];
				$query = $entity[1];
				if (isset($entity[2]) && $entity[2] == true) continue;
				self::findEntitySearch($names, $type, "$query $match :search limit 9", $search . ($i == 0 ? "" : "%"));
			}
		}
		$retValue = array();
		foreach ($names as $id => $value) $retValue[] = $value;
		self::addInfo($retValue);
		return $retValue;
	}

	/**
	 * [findNames description]
	 * @param  string $search
	 * @return array
	 */
	public static function findNames($search)
	{
		$array = self::findEntity($search);
		$retValue = array();
		foreach ($array as $row) {
			if (isset($row["characterName"])) $retValue[] = $row["characterName"];
			else if (isset($row["corporationName"])) $retValue[] = $row["corporationName"];
			else if (isset($row["allianceName"])) $retValue[] = $row["allianceName"];
			else if (isset($row["factionName"])) $retValue[] = $row["factionName"];
			else if (isset($row["typeName"])) $retValue[] = $row["typeName"];
			else if (isset($row["solarSystemName"])) $retValue[] = $row["solarSystemName"];
			else if (isset($row["regionName"])) $retValue[] = $row["regionName"];
			else if (isset($row["factionName"])) $retValue[] = $row["factionName"];
		}
		return $retValue;
	}

	/**
	 * Gets a pilots details
	 * @param  int $id
	 * @return array
	 */
	public static function getPilotDetails($id, $parameters = array())
	{
		$data = Db::queryRow("select characterID, corporationID, allianceID, factionID from zz_participants where characterID = :id and dttm >= date_sub(now(), interval 7 day) order by killID desc limit 1", array(":id" => $id), 3600);
		if (sizeof($data) == 0)
		{
			$data = Db::queryRow("select characterID, corporationID, allianceID, 0 factionID from zz_characters where characterID = :id", array(":id" => $id));
		}
		if (sizeof($data) == 0)
		{
			$data["characterID"] = $id;
			$data["valid"] = false;
			return $data;
		}
		self::addInfo($data);
		if (isset($data["corporationID"])) $data["isCEO"] = Db::queryField("select count(*) count from zz_corporations where ceoID = :charID and corporationID = :corpID", "count", array(":charID" => $id, ":corpID" => $data["corporationID"]));
		else $data["isCEO"] = false;
		if ($data["isCEO"] && $data["allianceID"] != 0) {
			$data["isExecutorCEO"] = Db::queryField("select count(*) count from zz_alliances where executorCorpID = :corpID and allianceID = :alliID", "count", array(":corpID" => $data["corporationID"], ":alliID" => $data["allianceID"]));
		} else $data["isExecutorCEO"] = 0;
		return $parameters == null ? $data : Summary::getPilotSummary($data, $id, $parameters);
	}

	/**
	 * [addCorp description]
	 * @param int $id
	 * @param string $name
	 */
	public static function addCorp($id, $name)
	{
		if ($id <= 0) return;
		$exists = Db::queryField("select count(1) count from zz_corporations where corporationID = :id", "count", array(":id" => $id));
		if ($exists == 0) Db::execute("insert ignore into zz_corporations (corporationID, name) values (:id, :name)", array(":id" => $id, ":name" => $name));
	}

	/**
	 * [getCorpDetails description]
	 * @param  int $id
	 * @param  array  $parameters
	 * @return array
	 */
	public static function getCorpDetails($id, $parameters = array())
	{
		$data = Db::queryRow("select corporationID, allianceID, factionID from zz_participants where corporationID = :id  and dttm >= date_sub(now(), interval 7 day) order by killID desc limit 1",  array(":id" => $id), 3600);
		if (sizeof($data) == 0) $data = Db::queryRow("select corporationID, allianceID, 0 factionID from zz_corporations where corporationID = :id", array(":id" => $id), 3600);
		if (sizeof($data) == 0) $data["corporationID"] = $id;
		$moreData = Db::queryRow("select * from zz_corporations where corporationID = :id", array(":id" => $id), 3600);
		if ($moreData) {
			$data["memberCount"] = $moreData["memberCount"];
			$data["cticker"] = $moreData["ticker"];
			$data["ceoID"] = $moreData["ceoID"];
		}
		self::addInfo($data);
		return Summary::getCorpSummary($data, $id, $parameters);
	}

	/**
	 * [getAlliDetails description]
	 * @param  int $id
	 * @param  array  $parameters
	 * @return array
	 */
	public static function getAlliDetails($id, $parameters = array())
	{
		$data = Db::queryRow("select allianceID, factionID from zz_participants where allianceID = :id and dttm >= date_sub(now(), interval 7 day) order by killID desc limit 1", array(":id" => $id), 3600);
		if (sizeof($data) == 0) $data["allianceID"] = $id;
		// Add membercount, etc.
		$moreData = Db::queryRow("select * from zz_alliances where allianceID = :id", array(":id" => $id), 3600);
		if ($moreData) {
			$data["memberCount"] = $moreData["memberCount"];
			$data["aticker"] = $moreData["ticker"];
			$data["executorCorpID"] = $moreData["executorCorpID"];
		}
		self::addInfo($data);
		return Summary::getAlliSummary($data, $id, $parameters);
	}

	/**
	 * [getFactionDetails description]
	 * @param  int $id
	 * @return array
	 */
	public static function getFactionDetails($id, $parameters = array())
	{
		$data["factionID"] = $id;
		self::addInfo($data);
		return Summary::getFactionSummary($data, $id, $parameters);
	}

	/**
	 * [getSystemDetails description]
	 * @param  int $id
	 * @return array
	 */
	public static function getSystemDetails($id, $parameters = array())
	{
		$data = array("solarSystemID" => $id);
		self::addInfo($data);
		return Summary::getSystemSummary($data, $id, $parameters);
	}

	/**
	 * [getRegionDetails description]
	 * @param  int $id
	 * @return array
	 */
	public static function getRegionDetails($id, $parameters = array())
	{
		$data = array("regionID" => $id);
		self::addInfo($data);
		return Summary::getRegionSummary($data, $id, $parameters);
	}

	/**
	 * [getGroupDetails description]
	 * @param  int $id
	 * @return array
	 */
	public static function getGroupDetails($id)
	{
		$data = array("groupID" => $id);
		self::addInfo($data);
		return Summary::getGroupSummary($data, $id);
	}

	/**
	 * [getShipDetails description]
	 * @param  int $id
	 * @return array
	 */
	public static function getShipDetails($id)
	{
		$data = array("shipTypeID" => $id);
		self::addInfo($data);
		$data["shipTypeName"] = $data["shipName"];
		return Summary::getShipSummary($data, $id);
	}

	/**
	 * [getSystemsInRegion description]
	 * @param  int $id
	 * @return array
	 */
	public static function getSystemsInRegion($id)
	{
		$result = Db::query("select solarSystemID from ccp_systems where regionID = :id", array(":id" => $id), 3600);
		$data = array();
		foreach ($result as $row) $data[] = $row["solarSystemID"];
		return $data;
	}

	/**
	 * [addInfo description]
	 * @param mixed $element
	 * @return array|null
	 */
	public static function addInfo(&$element)
	{
		if ($element == null) return;
		foreach ($element as $key => $value) {
			if (is_array($value)) $element[$key] = self::addInfo($value);
			else if ($value != 0) switch ($key) {
				case "lastChecked":
					$element["lastCheckedTime"] = $value;
					break;
				case "cachedUntil":
					$element["cachedUntilTime"] = $value;
					break;
				case "dttm":
					$dttm = strtotime($value);
					$element["ISO8601"] = date("c", $dttm);
					$element["killTime"] = date("Y-m-d H:i", $dttm);
					$element["MonthDayYear"] = date("F j, Y", $dttm);
					break;
				case "shipTypeID":
					if (!isset($element["shipName"])) $element["shipName"] = self::getItemName($value);
					if (!isset($element["groupID"])) $element["groupID"] = self::getGroupID($value);
					if (!isset($element["groupName"])) $element["groupName"] = self::getGroupName($element["groupID"]);
					break;
				case "groupID":
					global $loadGroupShips; // ugh
					if (!isset($element["groupName"])) $element["groupName"] = self::getGroupName($value);
					if ($loadGroupShips && !isset($element["groupShips"]) && !isset($element["noRecursion"])) $element["groupShips"] = Db::query("select typeID as shipTypeID, typeName as shipName, raceID, 1 as noRecursion from ccp_invTypes where groupID = :id and (groupID = 29 or (published = 1 and marketGroupID is not null)) order by raceID, marketGroupID, typeName", array (":id" => $value), 3600);
					break;
				case "executorCorpID":
					$element["executorCorpName"] = self::getCorpName($value);
					break;
				case "ceoID":
					$element["ceoName"] = self::getCharName($value);
					break;
				case "characterID":
					$element["characterName"] = self::getCharName($value);
					break;
				case "corporationID":
					$element["corporationName"] = self::getCorpName($value);
					break;
				case "allianceID":
					$element["allianceName"] = self::getAlliName($value);
					break;
				case "factionID":
					$element["factionName"] = self::getFactionName($value);
					break;
				case "weaponTypeID":
					$element["weaponTypeName"] = self::getItemName($value);
					break;
				case "typeID":
					if (!isset($element["typeName"])) $element["typeName"] = self::getItemName($value);
					$groupID = self::getGroupID($value);
					if (!isset($element["groupID"])) $element["groupID"] = $groupID;
					if (!isset($element["groupName"])) $element["groupName"] = self::getGroupName($groupID);
					if (Util::startsWith($element["groupName"], "Infantry ")) $element["fittable"] = true;
					else if (!isset($element["fittable"])) $element["fittable"] = self::getEffectID($value) != null;
					break;
				case "solarSystemID":
					$info = self::getSystemInfo($value);
					if (sizeof($info)) {
						$element["solarSystemName"] = $info["solarSystemName"];
						$element["sunTypeID"] = $info["sunTypeID"];
						$securityLevel = number_format($info["security"], 1);
						if ($securityLevel == 0 && $info["security"] > 0) $securityLevel = 0.1;
						$element["solarSystemSecurity"] = $securityLevel;
						$element["systemColorCode"] = self::getSystemColorCode($securityLevel);
						$regionInfo = self::getRegionInfoFromSystemID($value);
						$element["regionID"] = $regionInfo["regionID"];
						$element["regionName"] = $regionInfo["regionName"];
						$wspaceInfo = self::getWormholeSystemInfo($value);
						if ($wspaceInfo) {
							$element["systemClass"] = $wspaceInfo["class"];
							$element["systemEffect"] = $wspaceInfo["effectName"];
						}
					}
					break;
				case "regionID":
					$element["regionName"] = self::getRegionName($value);
					break;
				case "flag":
					$element["flagName"] = self::getFlagName($value);
					break;
				case "addDetail":
					$info = Db::queryRow("select corporationID, allianceID from zz_participants where characterID = :c order by killID desc limit 1",
							array(":c" => $value), 3600);
					if (sizeof($info)) {
						$element["corporationID"] = $info["corporationID"];
						if ($info["allianceID"]) $element["allianceID"] = $info["allianceID"];
					}
					break;
			}
		}
		return $element;
	}

	/**
	 * [getSystemColorCode description]
	 * @param  int $securityLevel
	 * @return string
	 */
	public static function getSystemColorCode($securityLevel)
	{
		$sec = number_format($securityLevel, 1);
		switch ($sec) {
			case 1.0:
				return "#33F9F9";
			case 0.9:
				return "#4BF3C3";
			case 0.8:
				return "#02F34B";
			case 0.7:
				return "#00FF00";
			case 0.6:
				return "#96F933";
			case 0.5:
				return "#F5F501";
			case 0.4:
				return "#E58000";
			case 0.3:
				return "#F66301";
			case 0.2:
				return "#EB4903";
			case 0.1:
				return "#DC3201";
			default:
			case 0.0:
				return "#F30202";
		}
		return "";
	}


	public static $effectFitToSlot = array(
			"12" => "High Slots",
			"13" => "Mid Slots",
			"11" => "Low Slots",
			"2663" => "Rigs",
			"3772" => "SubSystems",
			"87" => "Drone Bay",);

	/**
	 * [$effectToSlot description]
	 * @var array
	 */
	public static $effectToSlot = array(
			"12" => "High Slots",
			"13" => "Mid Slots",
			"11" => "Low Slots",
			"2663" => "Rigs",
			"3772" => "SubSystems",
			"87" => "Drone Bay",
			"5" => "Cargo",
			"4" => "Corporate Hangar",
			"0" => "Corporate  Hangar", // Yes, two spaces, flag 0 is wierd and should be 4
			"89" => "Implants",
			"133" => "Fuel Bay",
			"134" => "Ore Hold",
			"136" => "Mineral Hold",
			"137" => "Salvage Hold",
			"138" => "Specialized Ship Hold",
			"90" => "Ship Hangar",
			"148" => "Command Center Hold",
			"149" => "Planetary Commodities Hold",
			"151" => "Material Bay",
			"154" => "Quafe Bay",
			"155" => "Fleet Hangar",
			);

	/**
	 * [$infernoFlags description]
	 * @var array
	 */
	private static $infernoFlags = array(
			4 => array(116, 121),
			12 => array(27, 34), // Highs
			13 => array(19, 26), // Mids
			11 => array(11, 18), // Lows
			2663 => array(92, 98), // Rigs
			3772 => array(125, 132), // Subs
			);

	/**
	 * [getFlagName description]
	 * @param  string $flag
	 * @return string
	 */
	public static function getFlagName($flag)
	{
		// Assuming Inferno Flags
		$flagGroup = 0;
		foreach (self::$infernoFlags as $infernoFlagGroup => $array) {
			$low = $array[0];
			$high = $array[1];
			if ($flag >= $low && $flag <= $high) $flagGroup = $infernoFlagGroup;
			if ($flagGroup != 0) return self::$effectToSlot["$flagGroup"];
		}
		if ($flagGroup == 0 && array_key_exists($flag, self::$effectToSlot)) return self::$effectToSlot["$flag"];
		if ($flagGroup == 0 && $flag == 0) return "Corporate  Hangar";
		if ($flagGroup == 0) return null;
		return self::$effectToSlot["$flagGroup"];
	}

	/**
	 * [getSlotCounts description]
	 * @param  int $shipTypeID
	 * @return array
	 */
	public static function getSlotCounts($shipTypeID)
	{
		$result = Db::query("select attributeID, valueInt, valueFloat from ccp_dgmTypeAttributes where typeID = :typeID and attributeID in (12, 13, 14, 1137)",
				array(":typeID" => $shipTypeID), 86400);
		$slotArray = array();
		foreach ($result as $row) {
			if($row["valueInt"] == NULL && $row["valueFloat"] != NULL)
				$value = $row["valueFloat"];
			elseif($row["valueInt"] != NULL && $row["valueFloat"] == NULL)
				$value = $row["valueInt"];
			else
				$value = NULL;

			if ($row["attributeID"] == 12) $slotArray["lowSlotCount"] = $value;
			else if ($row["attributeID"] == 13) $slotArray["midSlotCount"] = $value;
			else if ($row["attributeID"] == 14) $slotArray["highSlotCount"] = $value;
			else if ($row["attributeID"] == 1137) $slotArray["rigSlotCount"] = $value;
		}
		return $slotArray;
	}

	/**
	 * @param string $title
	 * @param string $field
	 * @param array $array
	 */
	public static function doMakeCommon($title, $field, $array) {
		$retArray = array();
		$retArray["type"] = str_replace("ID", "", $field);
		$retArray["title"] = $title;
		$retArray["values"] = array();
		foreach($array as $row) {
			$data = $row;
			$data["id"] = $row[$field];
			if (isset($row[$retArray["type"] . "Name"])) $data["name"] = $row[$retArray["type"] . "Name"];
			else if(isset($row["shipName"])) $data["name"] = $row["shipName"];
			$data["kills"] = $row["kills"];
			$retArray["values"][] = $data;
		}
		return $retArray;
	}
}
