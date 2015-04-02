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

class cli_wars implements cliCommand
{
	public function getDescription()
	{
		return "Obtain war information and killmails related to the war.";
	}

	public function getAvailMethods()
	{
		return ""; // Space seperated list
	}

        public function getCronInfo()
        {
                return array(0 => "");
        }

	public function execute($parameters, $db)
	{
		if (Util::isMaintenanceMode()) return;
		$added = 0;
		$timer = new Timer();
		while ($timer->stop() < 65000)
		{
			$now = $timer->stop();
			$warRows = $db->query("select * from zz_wars where lastChecked < date_sub(now(), interval 1 hour) and (timeFinished is null or timeFinished > date_sub(now(), interval 36 hour)) order by lastChecked limit 100", array(), 0);

			if (count($warRows) == 0) return;

			foreach ($warRows as $warRow)
			{
				if ($timer->stop() > 65000) continue;
				$id = $warRow["warID"];

				$href = "https://public-crest.eveonline.com/wars/$id/";
				$warInfo = Util::getCrest($href);

				$prevKills = $warRow["agrShipsKilled"] + $warRow["dfdShipsKilled"];
				$currKills = $warInfo->aggressor->shipsKilled + $warInfo->defender->shipsKilled;

				// Don't fetch killmail api for wars with no kill count change
				if ($prevKills != $currKills)
				{
					$kmHref = $warInfo->killmails;
					$warKillCount = Db::queryField("select count(*) count from zz_warmails where warID = :id", "count", array(":id" => $id), 0);
					$page = floor(($warKillCount / 2000) - 1);
					if ($page > 1) $kmHref .= "?page=$page";
					while ($kmHref != null)
					{
						if ($timer->stop() > 65000) continue;
						$killmails = Util::getCrest($kmHref);

						foreach($killmails->items as $kill)
						{
							if ($timer->stop() > 65000) continue;
							$href = $kill->href;
							$exploded = explode("/", $href);
							$killID = $exploded[4];
							$hash = $exploded[5];

							$aff = $db->execute("insert ignore into zz_crest_killmail (killID, hash) values (:killID, :hash)", array(":killID" => $killID, ":hash" => $hash));
							$db->execute("insert ignore into zz_warmails values (:killID, :warID)", array(":killID" => $killID, ":warID" => $id));
							$added += $aff;
						}
						$next = @$killmails->next->href;
						if ($next != $kmHref) $kmHref = $next;
						else $kmHref = null;
					}
				}

				if ($timer->stop() < 65000) $db->execute("update zz_wars set defender = :defender, aggressor = :aggressor, timeDeclared = :timeDeclared, timeStarted = :timeStarted, timeFinished = :timeFinished, agrShipsKilled = :agrShipsKilled, dfdShipsKilled = :dfdShipsKilled, mutual = :mutual, openForAllies = :openForAllies, agrIskKilled = :agrIskKilled, dfdIskKilled = :dfdIskKilled, lastChecked = now() where warID = :warID", array(
							":aggressor" => $warInfo->aggressor->id,
							":defender" => $warInfo->defender->id,
							":timeDeclared" => $warInfo->timeDeclared,
							":timeStarted" => $warInfo->timeStarted,
							":timeFinished" => $warInfo->timeFinished,
							":agrShipsKilled" => $warInfo->aggressor->shipsKilled,
							":dfdShipsKilled" => $warInfo->defender->shipsKilled,
							":agrIskKilled" => $warInfo->aggressor->iskKilled,
							":dfdIskKilled" => $warInfo->defender->iskKilled,
							":mutual" => $warInfo->mutual,
							":openForAllies" => $warInfo->openForAllies,
							":warID" => $id,
							));
			}
		}
		if ($added > 0) Log::log("CREST (war): Added $added killmails");
	}
}
