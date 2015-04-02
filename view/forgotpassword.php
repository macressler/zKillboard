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

if($_POST)
{
    $email = Util::getPost("email");
    if(isset($email))
    {
        $exists = Db::queryField("SELECT username FROM zz_users WHERE email = :email", "username", array(":email" => $email), 0);
        if($exists != NULL)
        {
            // Generate a random hash to use for the reset token
            $random = new RandomGenerator();
	    $hash = substr($random->randomToken(), 0, 32);

            $alreadySent = Db::queryField("SELECT change_hash FROM zz_users WHERE email = :email", "change_hash", array(":email" => $email), 0);
            if($alreadySent != NULL)
            {
                $message = "A request to reset the password for this email, has already been sent";
                $messagetype = "error";
                $app->render("forgotpassword.html", array("message" => $message, "messagetype" => $messagetype));
            }
            else
            {
                global $baseAddr;
                $username = Db::queryField("SELECT username FROM zz_users WHERE email = :email", "username", array(":email" => $email));
                $subject = "It seems you might have forgotten your password, so here is a link, that'll allow you to reset it: $baseAddr/changepassword/$hash/ ps, your username is: $username";
                $header = "Password change for $email";
                Db::execute("UPDATE zz_users SET change_hash = :hash, change_expiration = date_add(now(), interval 3 day) WHERE email = :email", array(":hash" => $hash, ":email" => $email));
                Email::send($email, $header, $subject);
                $message = "Sending password change email to: $email";
                $messagetype = "success";
                $app->render("forgotpassword.html", array("message" => $message, "messagetype" => $messagetype));
            }
        }
        else
        {
            $message = "No user with that email exists, try again";
            $messagetype = "error";
            $app->render("forgotpassword.html", array("message" => $message, "messagetype" => $messagetype));
        }
    }
    else
    {
        $message = "An error occured..";
        $messagetype = "error";
        $app->render("forgotpassword.html", array("message" => $message, "messagetype" => $messagetype));
    }
}
else
    $app->render("forgotpassword.html");
