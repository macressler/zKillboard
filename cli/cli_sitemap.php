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

class cli_sitemap implements cliCommand
{
	public function getDescription()
	{
		return "";
	}

	public function getAvailMethods()
	{
		return ""; // Space seperated list
	}

	public function execute($parameters, $db)
	{
		global $baseAddr, $baseDir, $generateSiteMaps;

		@mkdir("$baseDir/sitemaps/");
		$locations = array();
		$baseQuery = "select distinct :id from (select * from zz_participants group by killID order by killID desc limit 500000) as foo where :id != 0 limit 50000";
		$types = array("character", "corporation", "alliance", "faction");
		foreach ($types as $type)
		{
			$result = $db->query(str_replace(":id", "${type}ID", $baseQuery));
			$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"/>');
			foreach ($result as $row) {
				$url = $xml->addChild("url");
				$loc = $url->addChild("loc", "https://$baseAddr/${type}/" . $row["${type}ID"] . "/");
			}
			file_put_contents("$baseDir/sitemaps/${type}s.xml", $xml->asXML());
			$locations[] = "https://$baseAddr/sitemaps/${type}s.xml";
		}

		$killIDs = Db::query("select distinct killID from zz_participants order by killID desc limit 50000");
		$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"/>');
		foreach ($killIDs as $row) {
			$killID = $row["killID"];
			$url = $xml->addChild("url");
			$loc = $url->addChild("loc", "https://$baseAddr/kill/$killID/");
		}
		file_put_contents("$baseDir/sitemaps/kills.xml", $xml->asXML());
		$locations[] = "https://$baseAddr/sitemaps/kills.xml";

		$xml = new SimpleXmlElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><sitemapindex xmlns=\"http://www.google.com/schemas/sitemap/0.84\"/>");
		foreach ($locations as $location)
		{
			$sitemap = $xml->addChild("sitemap");
			$sitemap->addChild("loc", $location);
		}
		file_put_contents("$baseDir/sitemaps/sitemaps.xml", $xml->asXML());

		file_get_contents("http://www.google.com/webmasters/sitemaps/ping?sitemap=https://$baseAddr/sitemaps/sitemaps.xml");
	}
}
