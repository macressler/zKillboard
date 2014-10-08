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

class cli_updateCorporations implements cliCommand
{
	public function getDescription()
	{
		return "Updates the corporation Name and IDs. |g|Usage: updateCorporations <type>";
	}

	public function getAvailMethods()
	{
		return "";
	}

	public function ggetCronInfo()
	{
		return array(0 => "");
	}

	public function execute($parameters, $db)
	{
		self::updateCorporations($db);
	}

	private static function updateCorporations($db)
	{
		$db->execute("delete from zz_corporations where corporationID = 0");
		//$db->execute("insert ignore into zz_corporations (corporationID) select executorCorpID from zz_alliances where executorCorpID > 0");
		$result = $db->query("select corporationID, name, memberCount, ticker from zz_corporations where lastUpdated < date_sub(now(), interval 1 week) and corporationID >= 1000001 order by lastUpdated limit 1000", array(), 0);
		foreach($result as $row) {
			if (Util::isMaintenanceMode()) return;
			if (Util::is904Error()) return;
			$id = $row["corporationID"];

			// Make sure this corp has kills
			$hasKills = $db->queryField("select killID from zz_participants where corporationID = :id limit 1", "killID", array(":id" => $id));
			if ($hasKills === null)
			{
				$db->execute("delete from zz_corporations where corporationID = :id", array(":id" => $id));
				continue;
			}
print_r($row);

			$pheal = Util::getPheal();
			$pheal->scope = "corp";
			try {
				$corpInfo = $pheal->CorporationSheet(array("corporationID" => $id));
				$name = $corpInfo->corporationName;
				$ticker = $corpInfo->ticker;
				$memberCount = $corpInfo->memberCount;
				$ceoID = $corpInfo->ceoID;
				if ($ceoID == 1) $ceoID = 0;
				$dscr = $corpInfo->description;

				if ($name != "") 
				{
					$db->execute("update zz_corporations set name = :name, ticker = :ticker, memberCount = :memberCount, ceoID = :ceoID, description = :dscr, lastUpdated = now() where corporationID = :id", array(":id" => $id, ":name" => $name, ":ticker" => $ticker, ":memberCount" => $memberCount, ":ceoID" => $ceoID, ":dscr" => $dscr));
				}
			} catch (Exception $ex) {
				$db->execute("update zz_corporations set lastUpdated = now() where corporationID = :id", array(":id" => $id));
				$db->execute("update zz_corporations set name = :name where corporationID = :id and name = ''", array(":id" => $id, ":name" => "Corporation $id"));
				if ($ex->getCode() != 503) Log::log("ERROR Validating Corp $id: " . $ex->getMessage());
			}
			usleep(100000); // Try not to spam the API servers (pauses 1/10th of a second)
		}
	}
}
