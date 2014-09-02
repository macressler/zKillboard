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

class cli_crestapi implements cliCommand
{
	public function getDescription()
	{
		return "Processes and converts external killmail links";
	}

	/**
	 * @return string|array
	 */
	public function getAvailMethods()
	{
		return ""; // Space seperated list
	}

	public function getCronInfo()
	{
		return array(0 => "");
	}

	/**
	 * @param array $parameters
	 * @param Database $db
	 */
	public function execute($parameters, $db)
	{
		global $debug, $baseAddr;
		$count = 0;
		$timer = new Timer();

		$db::execute("update zz_crest_killmail set processed = 0 where processed < -500");
		do {
			// Put priority on unknown kills first
			$crests = $db->query("select c.* from zz_crest_killmail c left join zz_killmails k on (c.killID = k.killID) where c.processed = 0 and k.killID is null order by killID desc limit 30", array(), 0);
			// If no unknown kills, then check the rest
			if (count($crests) == 0) $crests = $db->query("select * from zz_crest_killmail where processed = 0 order by killID desc limit 1", array(), 0);
			foreach ($crests as $crest) {
				try {
					$now = $timer->stop();
					$killID = $crest["killID"];
					$hash = trim($crest["hash"]);

					$url = "http://public-crest.eveonline.com/killmails/$killID/$hash/";
					if ($debug) Log::log($url);

					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $url);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_USERAGENT, "API Fetcher for http://$baseAddr");
					$body = curl_exec($ch);
					$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

					if ($httpCode > 500) return; // Server is rejecting us, bail for now
					if ($httpCode != 200)
					{
						Db::execute("update zz_crest_killmail set processed = :i where killID = :killID", array(":i" => (-1 * $httpCode), ":killID" => $killID));
						usleep(250000);
						continue;
					}

					$perrymail = json_decode($body, false);

					$killmail = array();
					$killmail["killID"] = (int) $killID;
					$killmail["solarSystemID"] = (int) $perrymail->solarSystem->id;
					$killmail["killTime"] = str_replace(".", "-", $perrymail->killTime);
					$killmail["moonID"] = (int) @$perrymail->moon->id;

					$victim = array();
					$killmail["victim"] = self::getVictim($perrymail->victim);
					$killmail["attackers"] = self::getAttackers($perrymail->attackers);
					$killmail["items"] = self::getItems($perrymail->victim->items);

					$json = json_encode($killmail);
					$killmailHash = Util::getKillHash(null, json_decode($json));

					$c = $db->execute("insert ignore into zz_killmails (killID, hash, source, kill_json) values (:killID, :hash, :source, :json)", array(":killID" => $killID, ":hash" => $killmailHash, ":source" => "crest:$killID", ":json" => $json));
					$db->execute("update zz_crest_killmail set processed = 1 where killID = :killID", array(":killID" => $killID));
					// Share with zKillboard
					if ($baseAddr != "zkillboard.com") file_get_contents("https://zkillboard.com/crestmail/$killID/$hash/");

					if ($c > 0) $count++;
					$diff = $timer->stop() - $now;
					if ($diff < 100)
					{
						$sleepTime = 30 - $diff;
						//usleep(1000 * $sleepTime);
					}
				} catch (Exception $ex) {
					Log::log("CREST exception: $killID - " . $ex->getMessage());
					$db->execute("update zz_crest_killmail set processed = -1 where killID = :killID", array(":killID" => $killID));
				}
			}
			if (count($crests) == 0) sleep(1);
		} while ($timer->stop() < 65000);
		if ($count) Log::log("CREST: Added $count kills");
	}

	/**
	 * @param object $perrymail
	 * @return array
	 */
	private static function getVictim($pvictim)
	{
		$victim = array();
		$victim["shipTypeID"] = (int) $pvictim->shipType->id;
		$victim["characterID"] = (int) @$pvictim->character->id;
		$victim["characterName"] = (string) @$pvictim->character->name;
		$victim["corporationID"] = (int) $pvictim->corporation->id;
		$victim["corporationName"] = (string) @$pvictim->corporation->name;
		$victim["allianceID"] = (int) @$pvictim->alliance->id;
		$victim["allianceName"] = (string) @$pvictim->alliance->name;
		$victim["factionID"] = (int) @$pvictim->faction->id;
		$victim["factionName"] = (string) @$pvictim->faction->name;
		$victim["damageTaken"] = (int) @$pvictim->damageTaken;
		return $victim;
	}

	/**
	 * @param array $attackers
	 * @return array
	 */
	private static function getAttackers($attackers)
	{
		$aggressors = array();
		foreach($attackers as $attacker) {
			$aggressor = array();
			$aggressor["characterID"] = (int) @$attacker->character->id;
			$aggressor["characterName"] = (string) @$attacker->character->name;
			$aggressor["corporationID"] = (int) @$attacker->corporation->id;
			$aggressor["corporationName"] = (string) @$attacker->corporation->name;
			$aggressor["allianceID"] = (int) @$attacker->alliance->id;
			$aggressor["allianceName"] = (string) @$attacker->alliance->name;
			$aggressor["factionID"] = (int) @$attacker->faction->id;
			$aggressor["factionName"] = (string) @$attacker->faction->name;
			$aggressor["securityStatus"] = $attacker->securityStatus;
			$aggressor["damageDone"] = (int) @$attacker->damageDone;
			$aggressor["finalBlow"] = (int) @$attacker->finalBlow;
			$aggressor["weaponTypeID"] = (int) @$attacker->weaponType->id;
			$aggressor["shipTypeID"] = (int) @$attacker->shipType->id;
			$aggressors[] = $aggressor;
		}
		return $aggressors;
	}

	/**
	 * @param array $items
	 * @return array
	 */
	private static function getItems($items)
	{
		$retArray = array();
		foreach($items as $item) {
			$i = array();
			$i["typeID"] = (int) @$item->itemType->id;
			$i["flag"] = (int) @$item->flag;
			$i["qtyDropped"] = (int) @$item->quantityDropped;
			$i["qtyDestroyed"] = (int) @$item->quantityDestroyed;
			$i["singleton"] = (int) @$item->singleton;
			if (isset($item->items)) $i["items"] = self::getItems($item->items);
			$retArray[] = $i;
		}
		return $retArray;
	}
}
