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

class cli_apiFetchCharacters implements cliCommand
{
	public function getDescription()
	{
		return "Fetches and updates characterIDs and Names from the API, based on the API Keys in the database. |g|Usage: apiFetchCharacters";
	}

	public function getAvailMethods()
	{
		return ""; // Space seperated list
	}

	public function execute($parameters, $db)
	{
		if (Util::isMaintenanceMode()) return;
		if (Util::is904Error()) return;
		$keyID = (int) $parameters[0];
		$vCode = $db->queryField("select vCode from zz_api where keyID = :keyID", "vCode", array(":keyID" => $keyID), 0);

		if ($keyID == 0 && strlen($vCode) == 0) return;

		$pheal = Util::getPheal($keyID, $vCode);
		try {
			$apiKeyInfo = $pheal->ApiKeyInfo();
		} catch (Exception $ex) {
			$db->execute("update zz_api set lastValidation = now() where keyID = :keyID", array(":keyID" => $keyID));
			//Log::log("Error Validating $keyID: " . $ex->getCode() . " " . $ex->getMessage());
			Api::handleApiException($keyID, null, $ex);
			return;
		}

		// Clear the error code
		$db->execute("update zz_api set lastValidation = now(), errorCode = 0 where keyID = :keyID", array(":keyID" => $keyID));

		$key = $apiKeyInfo->key;
		$accessMask = $key->accessMask;
		$characterIDs = array();
		if (Api::hasBits($accessMask)) {
			foreach ($apiKeyInfo->key->characters as $character) {
				$characterID = $character->characterID;
				$characterIDs[] = $characterID;
				$corporationID = $character->corporationID;

				$isDirector = $apiKeyInfo->key->type == "Corporation" ? "T" : "F";
				$count = $db->queryField("select count(*) count from zz_api_characters where keyID = :keyID and isDirector = :isDirector and characterID = :characterID and corporationID = :corporationID", "count", array(":keyID" => $keyID, ":characterID" => $characterID, ":corporationID" => $corporationID, ":isDirector" => $isDirector), 0);

				if ($count == 0) {
					$db->execute("replace into zz_api_characters (keyID, characterID, corporationID, isDirector, cachedUntil) values (:keyID, :characterID, :corporationID, :isDirector, 0)", array(":keyID" => $keyID, ":characterID" => $characterID, ":corporationID" => $corporationID, ":isDirector" => $isDirector));

					$charName = Info::getCharName($characterID, true);
					$corpName = Info::getCorpName($corporationID, true);
					$allianceID = $db->queryField("select allianceID from zz_corporations where corporationID = :corpID", "allianceID", array(":corpID" => $corporationID));
					$alliName = $allianceID > 0 ? "/ " . Info::getAlliName($allianceID) : "";
					$type = $isDirector == "T" ? "corp" : "char";
					while (strlen($keyID) < 8) $keyID = " " . $keyID;
					Log::log("KeyID: $keyID ($type) Populating $charName / $corpName $alliName");
				}
			}
		}
		// Clear entries that are no longer tied to this account
		if (sizeof($characterIDs) == 0) $db->execute("delete from zz_api_characters where keyID = :keyID", array(":keyID" => $keyID));
		else $db->execute("delete from zz_api_characters where keyID = :keyID and characterID not in (" . implode(",", $characterIDs) . ")",
				array(":keyID" => $keyID));
	}
}
