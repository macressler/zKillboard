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

class cli_populateAlliances implements cliCommand
{
	public function getDescription()
	{
		return "Populates the Alliance tables. |g|Usage: populate";
	}

	public function getAvailMethods()
	{
		return "";
	}

	public function getCronInfo()
	{
		return array(
			28800 => ""
		);
	}

	public function execute($parameters, $db)
	{
		self::populateAlliances($db);
	}

	private static function populateAlliances($db)
	{
		if (Util::isMaintenanceMode()) return;
		if (Util::is904Error()) return;
		//CLI::out("Repopulating the alliance table");
		Log::log("Repopulating alliance tables.");
		$allianceCount = 0;
		$corporationCount = 0;

		$pheal = Util::getPheal();
		$pheal->scope = "eve";
		$list = null;
		$exception = null;
		try {
			$list = $pheal->AllianceList();
		} catch (Exception $ex) {
			$exception = $ex;
		}
		if ($list != null && sizeof($list->alliances) > 0) {
			$db->execute("update zz_alliances set memberCount = 0");
			$db->execute("update zz_corporations set allianceID = 0");
			foreach ($list->alliances as $alliance) {
				$allianceCount++;
				$allianceID = $alliance['allianceID'];
				$shortName = $alliance['shortName'];
				$name = $alliance['name'];
				$executorCorpID = $alliance['executorCorpID'];
				$memberCount = $alliance['memberCount'];
				$parameters = array(":alliID" => $allianceID, ":shortName" => $shortName, ":name" => $name,
						":execID" => $executorCorpID, ":memberCount" => $memberCount);
				$db->execute("insert into zz_alliances (allianceID, ticker, name, executorCorpID, memberCount, lastUpdated) values
						(:alliID, :shortName, :name, :execID, :memberCount, now())
						on duplicate key update memberCount = :memberCount, ticker = :shortName, name = :name,
						executorCorpID = :execID, lastUpdated = now()", $parameters);
				$corporationCount += sizeof($alliance->memberCorporations);
				foreach($alliance->memberCorporations as $corp) {
					$corpID = $corp->corporationID;
					$db->execute("update zz_corporations set allianceID = :alliID where corporationID = :corpID",
							array(":alliID" => $allianceID, ":corpID" => $corpID));
				}
			}

			$allianceCount = number_format($allianceCount, 0);
			$corporationCount = number_format($corporationCount, 0);
			//CLI::out("Alliance tables repopulated - $allianceCount active Alliances with a total of $corporationCount Corporations");
			Log::log("Alliance tables repopulated - $allianceCount active Alliances with a total of $corporationCount Corporations");
		} else {
			//CLI::out("Unable to pull Alliance XML from API.  Will try again later.");
			Log::log("Unable to pull Alliance XML from API.  Will try again later.");
			if ($exception != null) throw $exception;
			throw new Exception("Unable to pull Alliance XML from API.  Will try again later");
		}
	}
}
