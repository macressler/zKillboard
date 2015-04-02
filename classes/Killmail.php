<?php

class Killmail
{
	public static function get($killID)
	{
		$kill = Cache::get("Kill$killID");
		if ($kill != null) return $kill;

		$kill = Db::queryField("select kill_json from zz_killmails where killID = :killID", "kill_json", array(":killID" => $killID));
		if ($kill != '') {
			Cache::set("Kill$killID", $kill);
			return $kill;
		}
		return null; // No such kill in database
	}

	// https://forums.eveonline.com/default.aspx?g=posts&m=4900335#post4900335
	public static function getCrestHash($killID)
	{
		$killmail = json_decode(Killmail::get($killID), true);

		$victim = $killmail["victim"];
		$victimID = $victim["characterID"] == 0 ? "None" : $victim["characterID"];

		$attackers = $killmail["attackers"];
		$attacker = null;
		foreach($attackers as $att)
		{
			if ($att["finalBlow"] != 0) $attacker = $att;
		}
		if ($attacker == null) $attacker = $attackers[0];
		$attackerID = $attacker["characterID"] == 0 ? "None" : $attacker["characterID"];

		$shipTypeID = $victim["shipTypeID"];

		$dttm = (strtotime($killmail["killTime"]) * 10000000) + 116444736000000000;

		$string = "$victimID$attackerID$shipTypeID$dttm";

		$sha = sha1($string);
		return $sha;
	}
}
