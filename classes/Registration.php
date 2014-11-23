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
class Registration
{
	public static function checkRegistration($email, $username)
	{
		$check = Db::query("SELECT username, email FROM zz_users WHERE email = :email OR username = :username", array(":email" => $email, ":username" => $username), 0);
		return $check;
	}

	public static function registerUser($username, $password, $email)
	{
		global $baseAddr;

		if (strtolower($username) == "evekill" || strtolower($username) == "eve-kill")
			return array("type" => "error", "message" => "Restrictd user name");

		$check = Db::queryField("SELECT count(*) count FROM zz_users WHERE email = :email OR username = :username", "count", array(":email" => $email, ":username" => $username), 0);
		if ($check == 0) {
			$hashedpassword = Password::genPassword($password);
			Db::execute("INSERT INTO zz_users (username, password, email) VALUES (:username, :password, :email)", array(":username" => $username, ":password" => $hashedpassword, ":email" => $email));
			$subject = "$baseAddr Registration";
			$message = "Thank you, $username, for registering at $baseAddr";
			//Email::send($email, $subject, $message);
			$message = "You have been registered, you should recieve a confirmation email in a moment, in the mean time you can click login and login!";
			return array("type" => "success", "message" => $message);
		}
		else
		{
			$message = "Username / email is already registered";
			return array("type" => "error", "message" => $message);
		}
	}
}
