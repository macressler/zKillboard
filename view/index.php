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

$page = 1;
$pageTitle = "";
$pageType = "index";
$requestUriPager = "";
$serverName = $_SERVER["SERVER_NAME"];
global $baseAddr, $fullAddr;
if ($serverName != $baseAddr) {
	$numDays = 7;
	$p = Subdomains::getSubdomainParameters($serverName);
	$page = max(1, min(25, $page));
	$p["page"] = $page;

	$columnName = key($p);
	$id = reset($p);

	if (sizeof($p) <= 1) $app->redirect($fullAddr, 302);

	$topPoints = array();
	$topPods = array();

	$p["kills"] = true;
	$p["pastSeconds"] = ($numDays*86400);

	$top = array();
	$top[] = Info::doMakeCommon("Top Characters", "characterID", Stats::getTopPilots($p));
	$top[] = ($columnName != "corporationID" ? Info::doMakeCommon("Top Corporations", "corporationID", Stats::getTopCorps($p)) : array());
	$top[] = ($columnName != "corporationID" && $columnName != "allianceID" ? Info::doMakeCommon("Top Alliances", "allianceID", Stats::getTopAllis($p)) : array());
	$top[] = Info::doMakeCommon("Top Ships", "shipTypeID", Stats::getTopShips($p));
	$top[] = Info::doMakeCommon("Top Systems", "solarSystemID", Stats::getTopSystems($p));

	$requestUriPager = str_replace("ID", "", $columnName) . "/$id/";

	$p["limit"] = 5;
	$topIsk = Stats::getTopIsk($p);
	unset($p["pastSeconds"]);
	unset($p["kills"]);

	// get latest kills
	$killsLimit = 50;
	$p["limit"] = $killsLimit;
	$kills = Kills::getKills($p);

	$kills = Kills::mergeKillArrays($kills, array(), $killsLimit, $columnName, $id);

	Info::addInfo($p);
	$pageTitle = array();
	foreach($p as $key=>$value) 
	{
		if (strpos($key, "Name") !== false) $pageTitle[] = $value;
	}
	$pageTitle = implode(",", $pageTitle);
	$pageType = "subdomain";
} else {
	$topPoints = array();
	$topIsk = json_decode(Storage::retrieve("TopIsk"), true);
	$topPods = json_decode(Storage::retrieve("TopPods"), true);
	$topPointList = json_decode(Storage::retrieve("TopPoints"), true);

	if(is_array($topPointList)) $topPoints = Kills::getKillsDetails($topPointList);

	$top = array();
	$top[] = json_decode(Storage::retrieve("TopChars"), true);
	$top[] = json_decode(Storage::retrieve("TopCorps"), true);
	$top[] = json_decode(Storage::retrieve("TopAllis"), true);
	$top[] = json_decode(Storage::retrieve("TopShips"), true);
	$top[] = json_decode(Storage::retrieve("TopSystems"), true);

	// get latest kills
	$killsLimit = 50;
	$kills = Kills::getKills(array("limit" => $killsLimit));
}

$app->render("index.html", array("topPods" => $topPods, "topIsk" => $topIsk, "topPoints" => $topPoints, "topKillers" => $top, "kills" => $kills, "page" => $page, "pageType" => $pageType, "pager" => true, "pageTitle" => $pageTitle, "requestUriPager" => $requestUriPager));
