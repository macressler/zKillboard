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

class cli_feedzKB implements cliCommand
{
	public function getDescription()
	{
		return "Fetches feeds from other zKillboards";
	}

	public function getAvailMethods()
	{
		return "";
	}

	public function execute($parameters, $db)
	{
		$totalCount = 0;
		$data = "";
		// Build the feeds from Admin's tracker list
		$adminID = $db->queryField("select id from zz_users where username = 'admin'", "id", array());
		$trackers = $db->query("select locker, content from zz_users_config where locker like 'tracker_%' and id = :id", array(":id" => $adminID), 0);
		$feeds = array();
		foreach ($trackers as $row) {
			$entityType = str_replace("tracker_", "", $row["locker"]);
			$entities = json_decode($row["content"], true);
			foreach($entities as $entity) {
				$id = (int) $entity["id"];
				$feed = array();
				$feed["id"] = $id;
				$feed["entityType"] = $entityType;

				$locker = "feed.$entityType.$id.lastFetchTime";
				$dontFetchThis = $db->queryField("select count(*) count from zz_users_config where locker = :locker and id = :adminID and content >= date_sub(now(), interval 1 hour)", "count", array(":locker" => $locker, ":adminID" => $adminID), 0);
				if ($dontFetchThis) continue;

				$locker = "feed.$entityType.$id.lastKillTime";
				$lastKillTime = $db->queryField("select content from zz_users_config where locker = :locker and id = :adminID", "content", array(":locker" => $locker, ":adminID" => $adminID), 0);

				if ($lastKillTime == "") $lastKillTime = null;
				$feed["lastKillTime"] = $lastKillTime;

				$feed["url"] = "https://zkillboard.com/api/{$entityType}ID/$id/";
				$feeds[] = $feed;
			}
		}
		if (sizeof($feeds) == 0) return; // Nothing to fetch...

		foreach($feeds as $feed)
		{
			$id = $feed["id"];
			$baseurl = $feed["url"];
			$entityType = $feed["entityType"];
			CLI::out("Fetching for |g|$baseurl|n|");
			$lastKillTime = $feed["lastKillTime"];

			do {
				$insertCount = 0;
				$url = "{$baseurl}orderDirection/asc/";
				if ($lastKillTime != null && $lastKillTime != 0) $url .= "startTime/" . preg_replace( '/[^0-9]/', '', $lastKillTime ) . "/";
				CLI::out($url);
				$fetchedData = self::fetchUrl($url);
				if ($fetchedData == "")
				{
					CLI::out("|r|Remote server returned an invalid response, moving along after 15 seconds...|n|");
					sleep(15);
					continue;
				}

				$data = json_decode($fetchedData);
				$insertCount = 0;

				foreach($data as $kill)
				{
					if(isset($kill->_stringValue))
						unset($kill->_stringValue);

					if ($kill == "") continue;
					$hash = Util::getKillHash(null, $kill);
					$json = json_encode($kill);
					$killID = $kill->killID;
					$source = "zKB Feed Fetch";
					$lastKillTime = $kill->killTime;
					//echo "$killID $lastKillTime\n";

					$insertCount += $db->execute("INSERT IGNORE INTO zz_killmails (killID, hash, source, kill_json) VALUES (:killID, :hash, :source, :kill_json)", array(":killID" => $killID, ":hash" => $hash, ":source" => $source, ":kill_json" => $json));
				}

				$locker = "feed.$entityType.$id.lastKillTime";
				$db->execute("replace into zz_users_config values (:adminID, :locker, :content)", array(":adminID" => $adminID, ":locker" => $locker, ":content" => $lastKillTime));
				$locker = "feed.$entityType.$id.lastFetchTime";
				$db->execute("replace into zz_users_config values (:adminID, :locker, now())", array(":adminID" => $adminID, ":locker" => $locker));

				$totalCount += $insertCount;
				CLI::out("Inserted |g|$insertCount|n|/|g|" . sizeof($data) . "|n| kills...");
				Log::log("Inserted $insertCount new kills from $url");
			} while ($insertCount > 0 || sizeof($data) >= 50);

		}

		if ($totalCount > 0) CLI::out("Inserted a total of |g|" . number_format($totalCount, 0) . "|n| kills.");
	}

	private static function fetchUrl($url)
	{
		global $baseAddr;
		$userAgent = "Feed Fetcher for $baseAddr";

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_USERAGENT, $userAgent);
		curl_setopt($curl, CURLOPT_TIMEOUT, 30);
		curl_setopt($curl, CURLOPT_POST, false);
		curl_setopt($curl, CURLOPT_FORBID_REUSE, false);
		curl_setopt($curl, CURLOPT_ENCODING, "");
		$headers = array();
		$headers[] = "Connection: keep-alive";
		$headers[] = "Keep-Alive: timeout=10, max=1000";
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($curl);

		return $result;
	}
}
