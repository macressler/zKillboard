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
class Util
{
	public static function isMaintenanceMode()
	{
		return "true" == Db::queryField("select contents from zz_storage where locker = 'maintenance'", "contents", array(), 0);
	}

	public static function getMaintenanceReason()
	{
		return Storage::retrieve("MaintenanceReason", "");
	}

	public static function getNotification()
	{
		return Storage::retrieve("notification", null);
	}

	public static function is904Error()
	{
		$stop904 = Db::queryField("select count(*) count from zz_storage where locker = 'ApiStop904' and contents > now()", "count", array(), 1);
		return $stop904 > 0;
	}

	public static function getCrest($url)
	{
		\Perry\Setup::$fetcherOptions = ["connect_timeout" => 15, "timeout" => 30];
		return \Perry\Perry::fromUrl($url);
	}

	/**
	 * @param integer $keyID
	 * @param string $vCode
	 */
	public static function getPheal($keyID = null, $vCode = null)
	{
		global $phealCacheLocation, $apiServer, $baseAddr, $ipsAvailable;

		if (static::is904Error()) 
		{
			if (php_sapi_name() == 'cli') exit();
			return null; // Web requests shouldn't be hitting the API...
		}

		\Pheal\Core\Config::getInstance()->http_method = "curl";
		\Pheal\Core\Config::getInstance()->http_user_agent = "API Fetcher for http://$baseAddr";
		if(!empty($ipsAvailable))
		{
			$max = count($ipsAvailable)-1;
			$ipID = mt_rand(0, $max);
			\Pheal\Core\Config::getInstance()->http_interface_ip = $ipsAvailable[$ipID];
		}
		\Pheal\Core\Config::getInstance()->http_post = false;
		\Pheal\Core\Config::getInstance()->http_keepalive = true; // default 15 seconds
		\Pheal\Core\Config::getInstance()->http_keepalive = 10; // KeepAliveTimeout in seconds
		\Pheal\Core\Config::getInstance()->http_timeout = 30;
		if ($phealCacheLocation != null) \Pheal\Core\Config::getInstance()->cache = new \Pheal\Cache\FileStorage($phealCacheLocation);
		\Pheal\Core\Config::getInstance()->log = new PhealLogger();
		\Pheal\Core\Config::getInstance()->api_customkeys = true;
		\Pheal\Core\Config::getInstance()->api_base = $apiServer;

		if ($keyID != null && $vCode != null) $pheal = new \Pheal\Pheal($keyID, $vCode);
		else $pheal = new \Pheal\Pheal();
		return $pheal;
	}

	public static function pluralize($string)
	{
		if (!self::endsWith($string, "s")) return $string . "s";
		else return $string . "es";
	}

	/**
	 * @param string $haystack
	 * @param string $needle
	 */
	public static function startsWith($haystack, $needle)
	{
		$length = strlen($needle);
		return (substr($haystack, 0, $length) === $needle);
	}

	public static function endsWith($haystack, $needle)
	{
		return substr($haystack, -strlen($needle)) === $needle;
	}

	public static function getKillHash($killID = null, $kill = null)
	{
		if ($killID != null) {
			$json = Killmail::get($killID);
			if ($json === null) throw new Exception("Cannot find kill $killID");
			$kill = json_decode($json);
			if ($kill === null) throw new Exception("Cannot json_decode $killID");
		}
		if ($kill === null) throw new Exception("Can't hash an empty kill");

		$hashStr = "";
		$hashStr .= ":$kill->killTime:$kill->solarSystemID:$kill->moonID:";
		$victim = $kill->victim;
		$hashStr .= ":$victim->characterID:$victim->shipTypeID:$victim->damageTaken:";

		return hash("sha256", $hashStr);
	}

	public static function calcX($slot, $size)
	{
		$angle = $slot * (360 / 32) - 4;
		$rad = deg2rad($angle);
		$radius = $size / 2;
		return (int)(($radius * cos($rad)));
	}

	public static function calcY($slot, $size)
	{
		$angle = $slot * (360 / 32) - 4;
		$rad = deg2rad($angle);
		$radius = $size / 2;
		return (int)(($radius * sin($rad)));
	}

	private static $formatIskIndexes = array("", "k", "m", "b", "t", "tt", "ttt");

	public static function formatIsk($value)
	{
		$numDecimals = (((int)$value) == $value) && $value < 10000 ? 0 : 2;
		if ($value == 0) return number_format(0, $numDecimals);
		if ($value < 10000) return number_format($value, $numDecimals);
		$iskIndex = 0;
		while ($value > 999.99) {
			$value /= 1000;
			$iskIndex++;
		}
		return number_format($value, $numDecimals) . self::$formatIskIndexes[$iskIndex];
	}

	public static function convertUriToParameters($additionalParameters = array(), $addExtraParameters = true)
	{
		$parameters = array();
		@$uri = $_SERVER["REQUEST_URI"];
		$split = explode("/", $uri);
		$currentIndex = 0;
		foreach ($split as $key)
		{
			$value = $currentIndex + 1 < count($split) ? $split[$currentIndex + 1] : null;
			switch ($key) {
				case "kills":
				case "losses":
				case "w-space":
				case "lowsec":
				case "nullsec":
				case "highsec":
				case "solo":
					$parameters[$key] = true;
					break;
				case "character":
				case "characterID":
				case "corporation":
				case "corporationID":
				case "alliance":
				case "allianceID":
				case "faction":
				case "factionID":
				case "ship":
				case "shipID":
				case "shipTypeID":
				case "group":
				case "groupID":
				case "system":
				case "solarSystemID":
				case "systemID":
				case "region":
				case "regionID":
					if ($value != null) {
						if (strpos($key, "ID") === false) $key = $key . "ID";
						if ($key == "systemID") $key = "solarSystemID";
						else if ($key == "shipID") $key = "shipTypeID";
						$exploded = explode(",", $value);
						if (self::endsWith("ID", $key)) foreach($exploded as $aValue) {
							if ($aValue != (int) $aValue || ((int) $aValue) == 0) throw new Exception("Invalid ID passed: $aValue");
						}
						if (sizeof($exploded) > 10) throw new Exception("Too many IDs! Max: 10");
						$parameters[$key] = $exploded;
					}
				break;
				case "page":
					$value = (int)$value;
					if ($value < 1) $value = 1;
					$parameters[$key] = $value;
				break;
				case "orderDirection":
					if (!($value == "asc" || $value == "desc")) throw new Exception("Invalid orderDirection!  Allowed: asc, desc");
					$parameters[$key] = "desc";
					$parameters[$key] = $value;
				break;
				case "pastSeconds":
					$value = (int) $value;
					if (($value / 86400) > 7) throw new Exception("pastSeconds is limited to a max of 7 days");
					$parameters[$key] = $value;
				break;
				case "startTime":
				case "endTime":
					$time = strtotime($value);
					if($time < 0) throw new Exception("$value is not a valid time format");
					$parameters[$key] = $value;
				break;
				case "limit":
					$value = (int) $value;
					if ($value < 200) $parameters["limit"] = $value;
					elseif($value > 200) $parameters["limit"] = 200;
					elseif($value <= 0) $parameters["limit"] = 1;
				break;
				case "beforeKillID":
				case "afterKillID":
				case "killID":
					if (!is_numeric($value)) throw new Exception("$value is not a valid entry for $key");
					$parameters[$key] = (int) $value;
				break;
				case "iskValue":
					if (!is_numeric($value)) throw new Exception("$value is not a valid entry for $key");
					$parameters[$key] = (int) $value;
				break;
				case "xml":
					$parameters[$key] = true;
				break;
				case "pretty":
					$parameters[$key] = true;
				break;
				default:
					if($addExtraParameters == true)
					{
						if (is_numeric($value) && $value < 0) continue; //throw new Exception("$value is not a valid entry for $key");
						if ($key != "" && $value != "") $parameters[$key] = $value;
					}

					// Add more parameters to the $parameters array
					if(!empty($additionalParameters))
					{
						foreach($additionalParameters as $extra)
							if($extra == $key)
								$parameters[$key] = $value;
					}
				break;
			}
			$currentIndex++;
		}

		if (isset($parameters["page"]) && $parameters["page"] > 10 && isset($parameters["api"])) {
			// Verify that the request is for a character, corporation, or alliance
			// This will prevent scrape attempts against regions, ships, systems, etc. which
			// are very hard against the database
			$legitEntities = array("characterID", "corporationID", "allianceID");
			$legit = false;
			foreach ($legitEntities as $entity) {
				$legit |= in_array($entity, array_keys($parameters));
			}
			if (!$legit) throw new Exception("page > 10 not allowed for this modifier type, please see API documentation");
		}
		return $parameters;
	}

	public static function shortString($string, $maxLength = 8)
	{
		if (strlen($string) <= $maxLength) return $string;
		return substr($string, 0, $maxLength - 3) . "...";
	}

	public static function truncate($str, $length = 200, $trailing = "...")
	{
		$length -= mb_strlen($trailing);
		if (mb_strlen($str) > $length) {
			// string exceeded length, truncate and add trailing dots
			return mb_substr($str, 0, $length) . $trailing;
		}
		else
		{
			// string was already short enough, return the string
			$res = $str;
		}
		return $res;
	}

	public static function pageTimer()
	{
		global $timer;
		return $timer->stop();
	}

	public static function isActive($pageType, $currentPage, $retValue = "active")
	{
		return strtolower($pageType) == strtolower($currentPage) ? $retValue : "";
	}

	private static $months = array("", "JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC");

	public static function getMonth($month)
	{
		return self::$months[$month];
	}

	private static $longMonths = array("", "January", "February", "March", "April", "May", "June", "July", "August",
			"September", "October", "November", "December");

	public static function getLongMonth($month)
	{
		return self::$longMonths[$month];
	}

	public static function scrapeCheck()
	{
		global $apiWhiteList, $maxRequestsPerHour;
		$maxRequestsPerHour = isset($maxRequestsPerHour) ? $maxRequestsPerHour : 360;

		$uri = substr($_SERVER["REQUEST_URI"], 0, 256);
        	$ip = substr(IP::get(), 0, 64);

		if(!in_array($ip, $apiWhiteList))
		{
			// Did this IP already fetch this URI in the last hour?
			$c = Db::queryField("select count(*) count from zz_scrape_prevention where ip = :ip and uri = :uri and dttm > date_sub(now(), interval 1 hour)", "count", array(":ip" => $ip, ":uri" => $uri), 0);
			if ($c > 3)
			{
				header("HTTP/1.1 304 Not Modified");
				die();
			}

			$count = Db::queryField("select count(*) count from zz_scrape_prevention where ip = :ip and dttm >= date_sub(now(), interval 1 hour)", "count", array(":ip" => $ip), 0);

			if($count > $maxRequestsPerHour)
			{
				$date = date("Y-m-d H:i:s");
				$cachedUntil = date("Y-m-d H:i:s", time() + 3600);
				if(stristr($_SERVER["REQUEST_URI"], "xml"))
				{
					$data = "<?xml version=\"1.0\" encoding=\"UTF-8\"?" . ">"; // separating the ? and > allows vi to still color format code nicely
					$data .= "<eveapi version=\"2\" zkbapi=\"1\">";
					$data .= "<currentTime>$date</currentTime>";
					$data .= "<result>";
					$data .= "<error>You have too many API requests in the last hour.  You are allowed a maximum of $maxRequestsPerHour requests.</error>";
					$data .= "</result>";
					$data .= "<cachedUntil>$cachedUntil</cachedUntil>";
					$data .= "</eveapi>";
					header("Content-type: text/xml; charset=utf-8");
				}
				else
				{
					header("Content-type: application/json; charset=utf-8");
					$data = json_encode(array("Error" => "You have too many API requests in the last hour.  You are allowed a maximum of $maxRequestsPerHour requests.", "cachedUntil" => $cachedUntil));
				}
				header("X-Bin-Request-Count: ". $count);
				header("X-Bin-Max-Requests: ". $maxRequestsPerHour);
				header("Retry-After: " . $cachedUntil . " GMT");
				header("HTTP/1.1 429 Too Many Requests");
				header("Etag: ".(md5(serialize($data))));
				echo $data;
				die();
			}
			header("X-Bin-Request-Count: ". $count);
			header("X-Bin-Max-Requests: ". $maxRequestsPerHour);
		}
        	Db::execute("insert into zz_scrape_prevention values (:ip, :uri, now())", array(":ip" => $ip, ":uri" => $uri));
	}

	public static function isValidCallback($subject)
	{
		$identifier_syntax = '/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}]*+$/u';

		$reserved_words = array('break', 'do', 'instanceof', 'typeof', 'case',
				'else', 'new', 'var', 'catch', 'finally', 'return', 'void', 'continue', 
				'for', 'switch', 'while', 'debugger', 'function', 'this', 'with', 
				'default', 'if', 'throw', 'delete', 'in', 'try', 'class', 'enum', 
				'extends', 'super', 'const', 'export', 'import', 'implements', 'let', 
				'private', 'public', 'yield', 'interface', 'package', 'protected', 
				'static', 'null', 'true', 'false');

		return preg_match($identifier_syntax, $subject) && ! in_array(mb_strtolower($subject, 'UTF-8'), $reserved_words);
	}

	public static function deleteKill($killID)
	{
		if($killID < 0)
		{
			// Verify the kill exists
			$count = Db::execute("select count(*) count from zz_killmails where killID = :killID", array(":killID" => $killID));
			if ($count == 0) return false;
			// Remove it from the stats
			Stats::calcStats($killID, false);
			// Remove it from the kill tables
			Db::execute("delete from zz_participants where killID = :killID", array(":killID" => $killID));
			// Mark the kill as deleted
			Db::execute("update zz_killmails set processed = 2 where killID = :killID", array(":killID" => $killID));
			return true;
		}
		return false;
	}

	public static function themesAvailable()
	{
		$dir = "themes/";
		$avail = scandir($dir);
		foreach($avail as $key => $val)
			if($val == "." || $val == "..")
				unset($avail[$key]);
		return $avail;
	}

	/**
	 * @param string $haystack
	 */
	public static function strposa($haystack, $needles=array(), $offset=0)
	{
			$chr = array();
			foreach($needles as $needle) {
					$res = strpos($haystack, $needle, $offset);
					if ($res !== false) $chr[$needle] = $res;
			}
			if(empty($chr)) return false;
			return min($chr);
	}

	/**
	 * @param string $url
	 * @return string|null $result
	 */
	public static function getData($url, $cacheTime = 3600)
	{
		global $ipsAvailable, $baseAddr;

		$md5 = md5($url);
		$result = $cacheTime > 0 ? Cache::get($md5) : null;

		if(!$result)
		{
			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_USERAGENT 			=> "zKillboard dataGetter for site: {$baseAddr}",
				CURLOPT_TIMEOUT 			=> 30,
				CURLOPT_POST 				=> false,
				CURLOPT_FORBID_REUSE 		=> false,
				CURLOPT_ENCODING 			=> "",
				CURLOPT_URL 				=> $url,
				CURLOPT_HTTPHEADER 			=> array("Connection: keep-alive", "Keep-Alive: timeout=10, max=1000"),
				CURLOPT_RETURNTRANSFER 		=> true,
				CURLOPT_FAILONERROR			=> true
				)
			);

			if(count($ipsAvailable) > 0)
			{
				$ip = $ipsAvailable[time() % count($ipsAvailable)];
				curl_setopt($curl, CURLOPT_INTERFACE, $ip);
			}
			$result = curl_exec($curl);
			if ($cacheTime > 0) Cache::set($md5, $result, $cacheTime);
		}

		return $result;
	}

	/**
	 * @param string $url
	 * @param array
	 * @param array
	 * @return array $result
	 */
	public static function postData($url, $postData = array(), $headers = array())
	{
		global $ipsAvailable, $baseAddr;
		$userAgent = "zKillboard dataGetter for site: {$baseAddr}";
		if(!isset($headers))
			$headers = array("Connection: keep-alive", "Keep-Alive: timeout=10, max=1000");

		$curl = curl_init();
		$postLine = "";

		if(!empty($postData))
			foreach($postData as $key => $value)
				$postLine .= $key . "=" . $value . "&";

		rtrim($postLine, "&");

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_USERAGENT, $userAgent);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		if(!empty($postData))
		{
			curl_setopt($curl, CURLOPT_POST, count($postData));
			curl_setopt($curl, CURLOPT_POSTFIELDS, $postLine);
		}

		if(count($ipsAvailable) > 0)
		{
			$ip = $ipsAvailable[time() % count($ipsAvailable)];
			curl_setopt($curl, CURLOPT_INTERFACE, $ip);
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

		$result = curl_exec($curl);

		curl_close($curl);
		return $result;
	}

	/**
	 * Gets post data, and returns it
	 * @param  string $var The variable you can to return
	 * @return string|null
	 */
	public static function getPost($var)
	{
		return isset($_POST[$var]) ? $_POST[$var] : null;
	}

	public static function informationPages()
	{
		global $baseDir, $theme;
		$mdDir = $baseDir . "information/";
		$data = scandir($mdDir);

		foreach($data as $key =>  $file)
		{
			if($file == "." || $file == "..")
				continue;

			if(is_dir($mdDir . $file))
			{
				$subData = scandir($mdDir . $file);
				foreach($subData as $key => $subDir)
				{
					if($subDir == "." || $subDir == "..")
						continue;

					$pages[$file][] = array("name" => strtolower(str_replace(".md", "", $subDir)), "path" => "$mdDir$file/$subDir");
				}
			}
			else
				$pages[strtolower(str_replace(".md", "", $file))][] = array("name" => strtolower(str_replace(".md", "", $file)), "path" => "$mdDir$file");
		}

		// Look if the theme has any information pages it wants to present
		$theme = UserConfig::get("theme", $theme);
		$tDir = $baseDir . "themes/" . $theme . "/information/";
		$data = null;
		if(is_dir($tDir))
			$data = scandir($tDir);

		if($data)
		{
			foreach($data as $key =>  $file)
			{
				if($file == "." || $file == "..")
					continue;

				if(is_dir($tDir . $file))
				{
					$subData = scandir($tDir . $file);
					foreach($subData as $key => $subDir)
					{
						if($subDir == "." || $subDir == "..")
							continue;

						$pages[$file][] = array("name" => strtolower(str_replace(".md", "", $subDir)), "path" => "$tDir$file/$subDir");
					}
				}
				else
					$pages[strtolower(str_replace(".md", "", $file))][] = array("name" => strtolower(str_replace(".md", "", $file)), "path" => "$tDir$file");
			}
		}
		return $pages;
	}
}
