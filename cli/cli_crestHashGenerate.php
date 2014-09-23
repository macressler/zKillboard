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
		$timer = new Timer();
		while ($timer->stop() <= 65000)
		{
			$kills = $db::query("select * from zz_crest_queue limit 1000", array(), 0);
			if (sizeof($kills) == 0)
			{
				sleep(3);
				continue;
			}
			foreach($kills as $row)
			{
				if ($timer->stop() > 65000) return;
				$killID = $row["killID"];
				$hash = Killmail::getCrestHash($killID);
				$db::execute("insert ignore into zz_crest_killmail (killID, hash) values (:killID, :hash)", array(":killID" => $killID, ":hash" => $hash));
				$db::execute("delete from zz_crest_queue where killID = :killID", array(":killID" => $killID));
			}
		}
	}
}
