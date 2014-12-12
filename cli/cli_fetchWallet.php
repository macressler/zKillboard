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

class cli_fetchWallet implements cliCommand
{
	public function getDescription()
	{
		return "";
	}

	public function getAvailMethods()
	{
		return ""; // Space seperated list
	}

        public function getCronInfo()
        {
                return array(2100 => ""); // Run every 35 minutes
        }

	public function execute($parameters, $db)
	{
		if (Util::isMaintenanceMode()) return;
		if (Util::is904Error()) return;
		global $walletApis;

		if (!is_array($walletApis)) return;

		foreach ($walletApis as $api)
		{
			$type = $api["type"];
			$keyID = $api["keyID"];
			$vCode = $api["vCode"];
			$charID = $api["charID"];

			$pheal = Util::getPheal($keyID, $vCode);
			$arr = array("characterID" => $charID, "rowCount" => 1000);

			if ($type == "char") $q = $pheal->charScope->WalletJournal($arr);
			else if ($type == "corp") $q = $pheal->corpScope->WalletJournal($arr);
			else continue;

			//$cachedUntil = $q->cached_until;
			if (count($q->transactions)) $this->insertRecords($charID, $q->transactions, $db);
		}
		$db->execute("replace into zz_storage values ('NextWalletFetch', date_add(now(), interval 35 minute))");

		$this->applyBalances($db);
	}

	protected function applyBalances($db)
	{
		global $walletCharacterID, $baseAddr;
		$toBeApplied = $db->query("select * from zz_account_wallet where paymentApplied = 0", array(), 0);
		foreach($toBeApplied as $row)
		{
			if ($row["ownerID2"] != $walletCharacterID) continue;
			$userID = null;

			$reason = $row["reason"];
			if (strpos($reason, ".{$baseAddr}") !== false) {
				global $adFreeMonthCost;
				$months = $row["amount"] / $adFreeMonthCost;
				$bonusMonths = floor($months / 6);
				$months += $bonusMonths;
				$subdomain = trim(str_replace("DESC: ", "", $reason));
				$subdomain = str_replace("http://", "", $subdomain);
				$subdomain = str_replace("https://", "", $subdomain);
				$subdomain = str_replace("/", "", $subdomain);

				$aff = $db->execute("insert into zz_subdomains (subdomain, adfreeUntil) values (:subdomain, date_add(now(), interval $months month)) on duplicate key update adfreeUntil = date_add(if(adfreeUntil is null, now(), adfreeUntil), interval $months month)", array(":subdomain" => $subdomain));
				if ($aff) $db->execute("update zz_account_wallet set paymentApplied = 1 where refID = :refID", array(":refID" => $row["refID"]));
				continue;
			}
			if ($reason)
			{
				$reason = trim(str_replace("DESC: ", "", $reason));
				$userID = $db->queryField("select id from zz_users where username = :reason", "id", array(":reason" => $reason));
			}

			if ($userID == null) 
			{
				$charID = $row["ownerID1"];
				$keyIDs = $db->query("select keyID from zz_api_characters where characterID = :charID", array(":charID" => $charID), 1);
				foreach($keyIDs as $keyIDRow) {
					if ($userID) continue;
					$keyID = $keyIDRow["keyID"];
					$userID = $db->queryField("select userID from zz_api where keyID = :keyID", "userID", array(":keyID" => $keyID), 1);
				}
			}

			if ($userID)
			{
				$db->execute("insert into zz_account_balance values (:userID, :amount) on duplicate key update balance = balance + :amount", array(":userID" => $userID, ":amount" => $row["amount"]));
				$db->execute("update zz_account_wallet set paymentApplied = 1 where refID = :refID", array(":refID" => $row["refID"]));
			}
		}
	}

	protected function insertRecords($charID, $records, $db) {
		foreach ($records as $record) {
			$db->execute("insert ignore into zz_account_wallet (characterID, dttm, refID, refTypeID, ownerName1, ownerID1, ownerName2, ownerID2, argName1, argID1,amount, balance, reason, taxReceiverID, taxAmount) values (:charID, :dttm , :refID, :refTypeID, :ownerName1, :ownerID1, :ownerName2, :ownerID2, :argName1, :argID1, :amount, :balance, :reason, :taxReceiverID, :taxAmount)",
					array(
						":charID"        => $charID,
						":dttm"          => $record["date"],
						":refID"         => $record["refID"],
						":refTypeID"     => $record["refTypeID"],
						":ownerName1"    => $record["ownerName1"],
						":ownerID1"      => $record["ownerID1"],
						":ownerName2"    => $record["ownerName2"],
						":ownerID2"      => $record["ownerID2"],
						":argName1"      => $record["argName1"],
						":argID1"        => $record["argID1"],
						":amount"        => $record["amount"],
						":balance"       => $record["balance"],
						":reason"        => $record["reason"],
						":taxReceiverID" => $record["taxReceiverID"],
						":taxAmount"     => $record["taxAmount"]
					     )
				    );
		}
	}
}
