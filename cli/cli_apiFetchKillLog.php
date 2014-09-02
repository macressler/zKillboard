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

class cli_apiFetchKillLog implements cliCommand
{
	public function getDescription()
	{
		return "Fetches the kill logs from the CCP API. |g|Usage: apiFetchKillLog";
	}

	public function getAvailMethods()
	{
		return ""; // Space seperated list
	}

	public function execute($parameters, $db)
	{
		if (Util::isMaintenanceMode()) return;
		@$apiRowID = $parameters[0];

		$notRecentKillID = Storage::retrieve("notRecentKillID", 0);
		$apiRow = $db->queryRow("select * from zz_api_characters where apiRowID = :id", array(":id" => $apiRowID), 0);
		$maxKillID = $apiRow["maxKillID"];
		$beforeKillID = 0;

		if (!$apiRow) CLI::out("|r|No such apiRowID: $apiRowID", true);

		$keyID = trim($apiRow["keyID"]);
		$vCode = $db->queryField("select vCode from zz_api where keyID = :keyID", "vCode", array(":keyID" => $keyID));
		$isDirector = $apiRow["isDirector"];
		$charID = $apiRow["characterID"];

		if ($keyID == "" || $vCode == "") die("no keyID or vCode");

		$pheal = null;
		try {
			$firstIteration = true;
			do {
				$pheal = Util::getPheal($keyID, $vCode);
				$charCorp = ($isDirector == "T" ? 'corp' : 'char');
				$pheal->scope = $charCorp;
				$result = null;

				// Update last checked
				//$db->execute("update zz_api_characters set errorCode = 0, lastChecked = now() where apiRowID = :id", array(":id" => $apiRowID));

				$params = array();
				if ($isDirector != "T") $params['characterID'] = $charID;

				if ($beforeKillID > 0) $params['beforeKillID'] = $beforeKillID;

				if ($isDirector == "T") $result = $pheal->KillMails($params);
				else $result = $pheal->KillMails($params);

				$cachedUntil = $result->cached_until;
				if ($cachedUntil == "" || !$cachedUntil) $cachedUntil = date("Y-m-d H:i:s", time()+3600);
				$keyID = trim($keyID);

				$aff = Api::processRawApi($keyID, $charID, $result);
				if ($aff > 0) {
					$keyID = "$keyID";
					while (strlen($keyID) < 8) $keyID = " " . $keyID;
					Log::log("KeyID: $keyID ($charCorp) added $aff kill" . ($aff == 1 ? "" : "s"));
				}
				$beforeKillID = 0;
				foreach ($result->kills as $kill) {
					$killID = $kill->killID;
					if ($beforeKillID == 0) $beforeKillID = $killID;
					else $beforeKillID = min($beforeKillID, $killID);
				}
				if ($firstIteration && sizeof($result->kills) == 0) $db->execute("update zz_api_characters set lastChecked = now(), errorCount = 0, errorCode = 0, cachedUntil = date_add(:cachedUntil, interval 2 hour) where apiRowID = :id", array(":id" => $apiRowID, ":cachedUntil" => $cachedUntil));
				else $db->execute("update zz_api_characters set lastChecked = now(), cachedUntil = :cachedUntil, errorCount = 0, errorCode = 0 where apiRowID = :id", array(":id" => $apiRowID, ":cachedUntil" => $cachedUntil));

				$firstIteration = false;
			} while ($aff > 24 || ($beforeKillID > 0 && $maxKillID == 0));
		} catch (Exception $ex) {
			$errorCode = $ex->getCode();
			$db->execute("update zz_api_characters set cachedUntil = date_add(now(), interval 1 hour), errorCount = errorCount + 1, errorCode = :code where apiRowID = :id", array(":id" => $apiRowID, ":code" => $errorCode));
			switch($errorCode) {
				case 119:
				case 120:
					// Don't log it
					break;	
				case 201: // Character does not belong to account.
				case 222: // API has expired
				case 221: // Invalid access, delete the toon from the char list until later re-verification
				case 220: // Invalid Corporation Key. Key owner does not fullfill role requirements anymore.
				case 403: // New error code for invalid API
					$db->execute("delete from zz_api_characters where apiRowID = :id", array(":id" => $apiRowID));
					break;
				case 1001:
					$db->execute("update zz_api_characters set cachedUntil = date_add(now(), interval 10 minute) where apiRowID = :id", array(":id" => $apiRowID));
					break;
				default:
					Log::log($keyID . " " . $ex->getCode() . " " . $ex->getMessage());
			}
			return;
		}
	}
}
