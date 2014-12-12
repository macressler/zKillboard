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

class cli_parseKills implements cliCommand
{
	public function getDescription()
	{
		return "Parses killmails which have not yet been parsed. |w|Beware, this is a semi-persistent script.|n| |g|Usage: parseKills";
	}

	public function getAvailMethods()
	{
		return ""; // Space seperated list
	}

	public function ggetCronInfo()
	{
		return array(0 => ""); // Always run
	}

	public function execute($parameters, $db)
	{
		if (Util::isMaintenanceMode()) return;
		Parser::parseKills();
	}
}
