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

$query = "";
$results = array();
//get the query value
if ($app->request()->isPost())
{
	$query = $app->request()->post('query');
}

//declare the base data/sql etc
$entities = array(
		array('type' => 'ship',        'query' => 'select i.typeID as id, i.typeName as name from ccp_zship_search c left join ccp_invTypes i on (c.typeID = i.typeID) where i.typeName like :query', 'image' => 'Type/%1$d_32.png'),
		array('type' => 'region',      'query' => 'SELECT regionID AS id, regionName AS name FROM ccp_regions WHERE regionName LIKE :query LIMIT 9',                       'image' => ''),
		array('type' => 'system',      'query' => 'SELECT solarSystemID  AS id, solarSystemName AS name FROM ccp_systems WHERE solarSystemName LIKE :query LIMIT 9',       'image' => ''),
		array('type' => 'faction',     'query' => 'SELECT factionID as id, name from ccp_zfactions where name like :query or ticker like :query limit 9','image' => 'Alliance/%1$d_32.png'),
		array('type' => 'alliance',    'query' => 'SELECT allianceID AS id, name FROM zz_alliances WHERE name LIKE :query OR ticker LIKE :query LIMIT 9',                  'image' => 'Alliance/%1$d_32.png'),
		array('type' => 'corporation', 'query' => 'SELECT corporationID AS id, name FROM zz_corporations WHERE name LIKE :query OR ticker LIKE :query LIMIT 9',            'image' => 'Corporation/%1$d_32.png'),
		array('type' => 'character',   'query' => 'SELECT characterID AS id, name FROM zz_characters WHERE name LIKE :query LIMIT 9',                                      'image' => 'Character/%1$d_32.jpg'),
		array('type' => 'item',        'query' => 'SELECT typeID AS id, typeName AS name, groupID FROM ccp_invTypes WHERE published = 1 AND typeName LIKE :query LIMIT 9', 'image' => 'Type/%1$d_32.png'),
		);

//define our array for the results
$search_results = array();

$ids = array();
//for each entity type, get any matches and process them
foreach ($entities as $key => $entity)
{
	$results1 = Db::query($entity['query'], array(":query" => $query), 30); //see if we have any things that exactly matches the thing
	$results2 = Db::query($entity['query'], array(":query" => $query . '%'), 30); //see if we have any things that matches the thing
	$results = array_merge($results1, $results2);
	if (sizeof($results) > 10) $results = array_slice($results, 0, 10);
	

	//merge the reults into an single array to throw back to the browser
	foreach ($results as $result)
	{
		if (!in_array($result['id'], $ids))
		{
			$search_results[] = array_merge($result, array('type' => $entity['type'], 'image' => sprintf($entity['image'], $result['id'])));
			$ids[] = $result['id'];
		}
	}
}

// Declare out json return type
$app->contentType('application/json; charset=utf-8');

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

//return the top 15 results as a json object
echo json_encode(array_slice($search_results, 0, 15));
