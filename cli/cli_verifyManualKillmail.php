<?php

class cli_verifyManualKillmail implements cliCommand
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
		$killID = $parameters[0];

		$killmail = json_decode(Killmail::get($killID), true);

		$hash = Killmail::getCrestHash($killID);
		$dttm = $killmail["killTime"];

		$minKillID = Db::queryField("select min(killID) killID from zz_participants where killID > 0 and dttm = date_sub(:dttm, interval 1 minute)", "killID", array(":dttm" => $dttm), 0);
		if ($minKillID == null) $minKillID = Db::queryField("select max(killID) killID from zz_participants where dttm < :dttm and dttm > date_sub(:dttm, interval 24 hour) and killID > 0", "killID", array(":dttm" => $dttm), 0);
		$maxKillID = Db::queryField("select max(killID) killID from zz_participants where killID > 0 and dttm = date_add(:dttm, interval 1 minute)", "killID", array(":dttm" => $dttm), 0);
		if ($maxKillID == null) $maxKillID = Db::queryField("select min(killID) killID from zz_participants where dttm > :dttm and dttm < date_add(:dttm, interval 24 hour) and killID > 0", "killID", array(":dttm" => $dttm), 0);
		$minKillID = Db::queryField("select max(killID) killID from zz_participants where killID > 0 and killID <= $minKillID", "killID", array(), 0);
		$maxKillID = Db::queryField("select min(killID) killID from zz_participants where killID > 0 and killID >= $maxKillID", "killID", array(), 0);

		echo "$killID $minKillID $maxKillID $dttm\n";
		for ($i = $minKillID ; $i <= $maxKillID; $i ++)
		{
			$c = Db::queryField("select count(1) count from zz_killmails where killID = $i", "count", array(), 0);
			if ($c > 0) continue;
			$url = "http://public-crest.eveonline.com/killmails/$i/$hash/";
echo "$url\n";

			$httpCode = self::getHttpCode($url);

			if ($httpCode == 200 || $httpCode == 415 || $httpCode == 500) 
			{
				Db::execute("insert ignore into zz_crest_killmail (killID, hash) values (:killID, :hash)", array(":killID" => $i, ":hash" => $hash));
				Stats::calcStats($killID, false);
				echo date("Ymd H:i:s") . " $killID => $i - $count/$limit ($minKillID $maxKillID $dttm)\n";
				break;
			}
			usleep(200000);
		}
	}

	private static function getHttpCode($url)
	{
		global $baseAddr;
retry:


		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, TRUE);
		curl_setopt($ch, CURLOPT_NOBODY, TRUE); // remove body 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_USERAGENT, "Manual mail to CREST conversion for zkillboard.com");
		$head = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($httpCode > 500)
		{
			sleep(15);
			goto retry;
		}
		echo "$url $httpCode\n";
		return $httpCode;
	}

	private static function getUrl($url)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_USERAGENT, "Manual mail to CREST conversion for zkillboard.com");
retry:
		$content = curl_exec($ch); 
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($httpCode != 200) { sleep(1); goto retry;}
		return $content;
	}
}
