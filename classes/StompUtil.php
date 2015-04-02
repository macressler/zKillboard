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

class StompUtil
{
	private static $stomp = null;

	public static function getStomp()
	{
		if (self::$stomp === null)
		{
			global $stompServer, $stompUser, $stompPassword;

			// Ensure the class exists
			if (!class_exists("Stomp"))
				die("ERROR! Stomp not installed!  Check the README to learn how to install Stomp...\n");

			self::$stomp = new Stomp($stompServer, $stompUser, $stompPassword);	

		}
		return self::$stomp;
	}

	public static function sendKill($killID)
	{
		if ($killID < 0) return;
		$stomp = self::getStomp();

		$json = Killmail::get($killID);
		if(!empty($json))
		{
			$destinations = self::getDestinations($json);
			foreach ($destinations as $destination)
			{
				$stomp->send($destination, $json);
			}
			$data = json_decode($json, true);
			//$map = json_encode(array("solarSystemID" => $data["solarSystemID"], "killID" => $data["killID"], "characterID" => $data["victim"]["characterID"], "corporationID" => $data["victim"]["corporationID"], "allianceID" => $data["victim"]["allianceID"], "shipTypeID" => $data["victim"]["shipTypeID"], "killTime" => $data["killTime"], "involved" => count($data["attackers"]), "totalValue" => $data["zkb"]["totalValue"], "pointsPrInvolved" => $data["zkb"]["points"]));
			//$stomp->send("/topic/starmap.systems.active", $map);
		}	
	}

	private static function getDestinations($kill)
	{
		$kill = json_decode($kill, true);
		$destinations = array();

		$destinations[] = "/topic/kills";
		$destinations[] = "/topic/location.solarsystem.".$kill["solarSystemID"];

		// victim
		if($kill["victim"]["characterID"] > 0) $destinations[] = "/topic/involved.character.".$kill["victim"]["characterID"];
		if($kill["victim"]["corporationID"] > 0) $destinations[] = "/topic/involved.corporation.".$kill["victim"]["corporationID"];
		if($kill["victim"]["factionID"] > 0) $destinations[] = "/topic/involved.faction.".$kill["victim"]["factionID"];
		if($kill["victim"]["allianceID"] > 0) $destinations[] = "/topic/involved.alliance.".$kill["victim"]["allianceID"];

		// attackers
		foreach($kill["attackers"] as $attacker)
		{
			if($attacker["characterID"] > 0) $destinations[] = "/topic/involved.character." . $attacker["characterID"];
			if($attacker["corporationID"] > 0) $destinations[] = "/topic/involved.corporation." . $attacker["corporationID"];
			if($attacker["factionID"] > 0) $destinations[] = "/topic/involved.faction." . $attacker["factionID"];
			if($attacker["allianceID"] > 0) $destinations[] = "/topic/involved.alliance." . $attacker["allianceID"];
		}

		return $destinations;
	}
}
