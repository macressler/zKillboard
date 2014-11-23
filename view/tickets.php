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

$message = array();

if($_POST)
{
	$tags = Util::getPost("hidden-tags");;
	$ticket = Util::getPost("ticket");;

	$info = User::getUserInfo();
	$name = $info["username"];
	$email = $info["email"];

	if(isset($name) && isset($email) && isset($tags) && isset($ticket))
	{
		$check = Db::query("SELECT * FROM zz_tickets WHERE ticket = :ticket AND email = :email", array(":ticket" => $ticket, ":email" => $email), 0);
		if(!$check)
		{
			Db::execute("INSERT INTO zz_tickets (userid, name, email, tags, ticket) VALUES (:userid, :name, :email, :tags, :ticket)", array(":userid" => User::getUserID(), ":name" => $name, ":email" => $email, ":tags" => $tags, ":ticket" => $ticket));
			$id = Db::queryField("SELECT id FROM zz_tickets WHERE userid = :userid AND name = :name AND tags = :tags AND ticket = :ticket", "id", array(":userid" => User::getUserID(), ":name" => $name, ":tags" => $tags, ":ticket" => $ticket));
			global $baseAddr;
			Log::irc("|g|New ticket from $name:|n| https://$baseAddr/moderator/tickets/$id/");
			$subject = "zKillboard Ticket";
			$message = "$name, you can find your ticket here, we will reply to your ticket asap. https://$baseAddr/tickets/view/$id/";
			//Email::send($email, $subject, $message);
			$app->redirect("/tickets/view/$id/");
		}
		else
			$message = array("type" => "error", "message" => "Ticket already posted");
	}
	else
	{
		$message = array("type" => "error", "message" => "Ticket was not posted, there was an error");
	}
}

$tickets = Db::query("SELECT * FROM zz_tickets WHERE userid = :userid ORDER BY datePosted DESC", array(":userid" => User::getUserID()), 0);
foreach($tickets as $key => $val)
{
	if($val["tags"])
		$tickets[$key]["tags"] = explode(",", $val["tags"]);
}

$userInfo = User::getUserInfo();
$app->render("tickets.html", array("userInfo" => $userInfo, "tickets" => $tickets, "message" => $message));
