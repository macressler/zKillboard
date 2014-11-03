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

class cli_crest_users implements cliCommand
{
	public function getDescription()
	{
		return "Checks all the characters in the zz_users_crest table, and updates their corp/alliance information.";
	}

	public function getAvailMethods()
	{
		return ""; // Space seperated list
	}

	public function getCronInfo()
	{
		return array(21600 => "");
	}

	public function execute($parameters, $db)
	{
		$users = Db::query("SELECT * FROM zz_users_crest");

		foreach($users as $user)
		{
			$characterID = $user["characterID"];
			$affilliations = Info::getCharacterAffiliations($characterID);

			if($affilliations["corporationID"] != $user["corporationID"])
			{
				Db::execute("UPDATE zz_users_crest SET corporationID = :corporationID WHERE userID = :userID", array(":corporationID" => $affiliations["corporationID"], ":userID" => $user["userID"]));
				Db::execute("UPDATE zz_users_crest SET corporationName = :corporationName WHERE userID = :userID", array(":corporationName" => $affiliations["corporationName"], ":userID" => $user["userID"]));
				Db::execute("UPDATE zz_users_crest SET corporationTicker = :corporationTicker WHERE userID = :userID", array(":corporationTicker" => $affiliations["corporationTicker"], ":userID" => $user["userID"]));
			}

			if($affiliations["allianceID"] != $user["allianceID"])
			{
				Db::execute("UPDATE zz_users_crest SET allianceID = :allianceID WHERE userID = :userID", array(":allianceID" => $affiliations["allianceID"], ":userID" => $user["userID"]));
				Db::execute("UPDATE zz_users_crest SET allianceName = :allianceName WHERE userID = :userID", array(":allianceName" => $affiliations["allianceName"], ":userID" => $user["userID"]));
				Db::execute("UPDATE zz_users_crest SET allianceTicker = :allianceTicker WHERE userID = :userID", array(":allianceTicker" => $affiliations["allianceTicker"], ":userID" => $user["userID"]));
			}
		}
	}
}
