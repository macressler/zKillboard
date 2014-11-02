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
use Perry\Perry;

class cli_warFetcher implements cliCommand
{
	public function getDescription()
	{
		return "Prepopulates the warID into the zz_wars table for processing.";
	}

	public function getAvailMethods()
	{
		return ""; // Space seperated list
	}

	public function execute($parameters, $db)
	{
		$page = Db::queryField("select floor(count(*) / 2000) page from zz_wars", "page", array(), 0);
		if ($page == 0) $page = 1;

		$next = "http://public-crest.eveonline.com/wars/?page=$page";
		do {
			$perrywars = Perry::fromUrl($next);
			$next = @$perrywars->next->href;
			foreach($perrywars->items as $war)
			{
				$id = $war->id;
				if ($id > 0) Db::execute("insert ignore into zz_wars (warID) values (:warID)", array(":warID" => $id));
			}
			sleep(1);
		} while ($next != null);
	}
}
