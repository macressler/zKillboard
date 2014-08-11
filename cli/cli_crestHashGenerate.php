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

class cli_crestHashGenerate implements cliCommand
{
	public function getDescription()
	{
		return "Creates the CREST hash for killmails with no CREST hash yet";
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
		$unprocessed = Db::queryField("select count(*) count from zz_crest_killmail where processed = 0", "count", array(), 0);
		if ($unprocessed > 10000) return;
		$timer = new Timer();
		while ($timer->stop() < 65000)
		{
			$killID = $db::queryField("select k.killID from zz_killmails k left join zz_crest_killmail c on (k.killID = c.killID) where k.processed > 0 and k.killID > 0 and c.killID is null order by k.killID desc limit 1", "killID", array(), 0);
			$hash = Killmail::getCrestHash($killID);
			$db::execute("insert ignore into zz_crest_killmail (killID, hash) values (:killID, :hash)", array(":killID" => $killID, ":hash" => $hash));
			usleep(100000);
		}
	}
}
