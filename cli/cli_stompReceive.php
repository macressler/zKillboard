<?php
/* zKillboard
 * Copyright (C) 2012-2013 EVE-KILL Team and EVSCO.
 *nding
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *nding
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class cli_stompReceive implements cliCommand
{
	public function getDescription()
	{
		return "Receives data from the STOMP server. |w|Beware, this is a persistent script. It's run and forget!.|n| Usage: |g|stompReceive";
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
		global $stompServer, $stompUser, $stompPassword, $baseAddr, $debug;

		// Ensure the class exists
		if (!class_exists("Stomp")) {
			die("ERROR! Stomp not installed!  Check the README to learn how to install Stomp...\n");
		}

		// Build the topic from Admin's tracker list
		$adminID = $db->queryField("select id from zz_users where username = 'admin'", "id", array(), 0);
		$trackers = $db->query("select locker, content from zz_users_config where locker like 'tracker_%' and id = :id", array(":id" => $adminID), 0);
		$topics = array();
		foreach ($trackers as $row) {
			$entityType = str_replace("tracker_", "", $row["locker"]);
			$entities = json_decode($row["content"], true);
			foreach($entities as $entity) {
				$id = $entity["id"];
				$topic = "/topic/involved.$entityType.$id";
				$topics[] = $topic;
			}
		}
		if (sizeof($topics) == 0) $topics[] =  "/topic/kills";

		try {
			$stomp = new Stomp($stompServer, $stompUser, $stompPassword);

			$stomp->setReadTimeout(1);
			foreach($topics as $topic) {
				$stomp->subscribe($topic, array("id" => "zkb-".$baseAddr, "persistent" => "true", "ack" => "client", "prefetch-count" => 1));
			}

			$stompCount = 0;
			$timer = new Timer();
			while($timer->stop() < 65000)
			{
				$frame = $stomp->readFrame();
				if(!empty($frame))
				{
					$killdata = json_decode($frame->body, true);
					if(!empty($killdata))
					{
						$killID = $killdata["killID"];
						$count = $db->queryField("SELECT count(1) AS count FROM zz_killmails WHERE killID = :killID LIMIT 1", "count", array(":killID" => $killID), 0);
						if($count == 0 && $killID > 0)
						{
							$hash = Util::getKillHash(null, json_decode($frame->body));
							$aff = $db->execute("INSERT IGNORE INTO zz_killmails (killID, hash, source, kill_json) values (:killID, :hash, :source, :json)",
									array("killID" => $killID, ":hash" => $hash, ":source" => "stompQueue", ":json" => json_encode($killdata)));
							$stompCount++;
							if ($debug && $aff) Log::log("Added kill $killID");
						}
					}
					$stomp->ack($frame->headers["message-id"]);
				}
			}
			if ($stompCount > 0) Log::log("StompReceive Ended - Received $stompCount kills");
		} catch (Exception $ex) {
			$e = print_r($ex, true);
			Log::log("StompReceive ended with the error:\n$e\n");
		}
	}
}
