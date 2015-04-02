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

class cli_updateCharacters implements cliCommand
{
	public function getDescription()
	{
		return "Updates character Name and IDs. |g|Usage: updateCharacters <type>";
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
		self::updateCharacters($db);
	}

	private static function updateCharacters($db)
	{
		$minute = (int) date("i");
		if ($minute == 0) {
			$db->execute("insert ignore into zz_characters (characterID) select ceoID from zz_corporations");
			$db->execute("insert ignore into zz_characters (characterID) select characterID from zz_api_characters where characterID != 0");
		}
		$timer = new Timer();
		while ($timer->stop() < 65000) 
		{
			$result = $db->query("select characterID, name, corporationID, allianceID from zz_characters where lastUpdated < date_sub(now(), interval 7 day) order by lastUpdated limit 100", array(), 0);
			foreach ($result as $row)
			{
				if (Util::is904Error()) return;
				$id = $row["characterID"];

				// Remove characters with no kills
				$hasKills = $db->queryField("select killID from zz_participants where characterID = :id limit 1", "killID", array(":id" => $id), 0);
				if ($hasKills === null)
				{
					$db->execute("delete from zz_characters where characterID = :id", array(":id" => $id));
					continue;
				}

				$db->execute("update zz_characters set lastUpdated = now() where characterID = :id", array(":id" => $id));
				if ($id >= 2100000000 && $id < 2199999999) continue; // Dust Characters
				if ($id >= 30000000 && $id <= 31004590) continue; // NPC's
				if ($id >= 40000000 && $id <= 41004590) continue; // NPC's
				$isTypeID = (0 < $db::queryField("select count(*) count from ccp_invTypes where typeID = :id", "count", array(":id" => $id)));
				if ($isTypeID) continue;

				$pheal = Util::getPheal();
				$pheal->scope = "eve";
				try
				{
					$charInfo = $pheal->CharacterInfo(array("characterid" => $id));
					$name = $charInfo->characterName;
					$corpID = $charInfo->corporationID;
					$alliID = $charInfo->allianceID;

					if ($name != $row["name"] || ((int) $corpID) != $row["corporationID"] || ((int) $alliID) != $row["allianceID"])
					{
						$db->execute("update zz_characters set name = :name, corporationID = :corpID, allianceID = :alliID where characterID = :id", array(":id" => $id, ":name" => $name, ":corpID" => $corpID, ":alliID" => $alliID));
					}
				}
				catch (Exception $ex)
				{
					Log::log("ERROR Validating Character $id" . $ex->getMessage());
				}
				usleep(100000); // Try not to spam the API servers (pauses 1/10th of a second)
			}
		}
	}
}
