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

class cli_itemUpdate implements cliCommand
{
	public function getDescription()
	{
		return "Updates items description from CCP. |g|Usage: itemUpdate";
	}

	public function getAvailMethods()
	{
		return ""; // Space seperated list
	}

        public function getCronInfo()
        {
                return array(604800 => ""); // Every 7 days
        }

	public function execute($parameters, $db)
	{
		if (Util::isMaintenanceMode()) return;
		$rows = $db->query("select typeID from ccp_invTypes order by typeID", array(), 0);
		$ids = array();
		foreach($rows as $row) {
			if ($row["typeID"] > 0) $ids[] = $row["typeID"];
		}

		$size = sizeof($ids);
		$count = 0;

		$buckets = array();
		$bucketNumber = 0;
		$bucketSize = 50;
		do {
			$currentBucket = array();
			$start = $bucketNumber * $bucketSize;
			$end = $start + $bucketSize;
			for ($i = $start; $i < $end && $i < $size; $i++) {
				if ($ids[$i] != "") $currentBucket[] = $ids[$i];
			}

			$buckets[$bucketNumber] = $currentBucket;
			$bucketNumber++;
		} while ($bucketNumber * $bucketSize < sizeof($ids));

		foreach ($buckets as $bucket) {
			$exploded = implode(",", $bucket);
			$url = trim("https://api.eveonline.com/eve/typeName.xml.aspx?ids=$exploded");
			$raw = file_get_contents($url);
			try {
				$xml = new SimpleXmlElement($raw, null, false, "", false);
			} catch (Exception $ex) {
				print_r($ex);
				Log::log("There was a problem retrieving the XML from $url\nThis could be because of the network, local server settings, CCP, etc.");
				die();
			}
			foreach ($xml->result->rowset->row as $row) {
				$count++;
				$id = $row["typeID"];
				$currentName = trim($db->queryField("select typeName from ccp_invTypes where typeID = :typeID", "typeName", array(":typeID" => $id), 0));
				$name = trim($row["typeName"]);
				if ($currentName === $name && $currentName != "Unknown Type") continue;
				if (strlen($name) == 0) {
					continue;  // CCP removed an item and cleared the name, we'll keep the name around though
				}
				$db->execute("update ccp_invTypes set typeName = :name where typeID = :id", array(":name" => $name, ":id" => $id));
				if ($currentName != "" && $name != "Unknown Type") {
					Log::log("$count/$size $id $currentName -> $name");
					Log::ircAdmin("$count/$size $id $currentName -> $name");
				}
			}
		}
	}
}
