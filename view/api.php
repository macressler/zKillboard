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

global $apiWhiteList;

//make sure the requester is not being a naughty boy
Util::scrapeCheck();

$parameters = Util::convertUriToParameters();

// Enforcement
if (sizeof($parameters) < 2) die("Invalid request.  Must provide at least two request parameters");

// At least one of these modifiers is required
$requiredM = array("characterID", "corporationID", "allianceID", "factionID", "shipTypeID", "groupID", "solarSystemID", "solo", "w-space", "warID", "killID");
$hasRequired = false;
$hasRequired |= in_array(IP::get(), $apiWhiteList);
foreach($requiredM as $required) {
	$hasRequired |= array_key_exists($required, $parameters);
}
if (!isset($parameters["killID"]) && !$hasRequired) 
{
	header("Error: Must pass at least two required modifier.  Please read API Information.");
	http_response_code(406);
	exit;
}

$exploded = explode("?", $_SERVER["REQUEST_URI"]);
$uri = $exploded[0];
$key = md5("related:$uri");
$return = Cache::get($key);
if (!$return) {
	$return = Feed::getKills($parameters);
	Cache::set($key, $return, 3600);
}

$array = array();
foreach($return as $json) $array[] = json_decode($json, true);
$app->etag(md5(serialize($return)));
$app->expires("+1 hour");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

if(isset($parameters["xml"]))
{
	$app->contentType("text/xml; charset=utf-8");
	echo XmlWrapper::xmlOut($array, $parameters);
}
elseif(isset($_GET["callback"]) && Util::isValidCallback($_GET["callback"]) )
{
	$app->contentType("application/javascript; charset=utf-8");
	header("X-JSONP: true");
	echo $_GET["callback"] . "(" . json_encode($array) .")";
}
else
{
	$app->contentType("application/json; charset=utf-8");
	if(isset($parameters["pretty"]))
		echo json_encode($array, JSON_PRETTY_PRINT);
	else
		echo json_encode($array);
}
