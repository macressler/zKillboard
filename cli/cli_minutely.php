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

class cli_minutely implements cliCommand
{
	public function getDescription()
	{
		return "Tasks that needs to run every minute. |g|Usage: minutely <task>";
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
		if (Util::isMaintenanceMode()) return;
		global $base;
		chdir($base);

		// Cleanup old sessions
		$db->execute("delete from zz_users_sessions where validTill < now()");

		// Cleanup deleted manual mails
		$db->execute("delete from zz_killmails where processed = 2 and kill_json = '' and killID < 0 limit 10000");

		// Keep the account balance table clean
		$db->execute("delete from zz_account_balance where balance = 0");

		$killsLastHour = $db->queryField("select count(*) count from zz_killmails where insertTime > date_sub(now(), interval 1 hour)", "count");
		Storage::store("KillsLastHour", $killsLastHour);
		$db->execute("delete from zz_analytics where dttm < date_sub(now(), interval 1 hour)");
		$db->execute("delete from zz_scrape_prevention where dttm < date_sub(now(), interval 1 hour)");

		$fc = new FileCache("$base/cache/queryCache/");
		$fc->cleanUp();

		// Cleanup subdomain stuff
		$db->execute("update zz_subdomains set adfreeUntil = null where adfreeUntil < now()");
		$db->execute("update zz_subdomains set banner = null where banner = ''");
		$db->execute("delete from zz_subdomains where adfreeUntil is null and banner is null and (alias is null or alias = '')");

		// Expire change expirations
		$db->execute("update zz_users set change_expiration = null, change_hash = null where change_expiration < date_sub(now(), interval 3 day)");
	}
}
