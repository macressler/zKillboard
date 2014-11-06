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

class Disqus
{

	public static function init()
	{
		global $disqusSecretKey, $disqusPublicKey, $theme, $fullAddr;

		$userInfo = User::getUserInfo();
		$userID = $userInfo["id"];
		$username = $userInfo["username"];
		$email = $userInfo["email"];
		$characterID = (isset($userInfo["characterID"]) ? $userInfo["characterID"] : null);

		$data = array(
			"id" => $userID,
			"username" => $username,
			"email" => $email
		);

		if($characterID)
			$data["avatar"] = "https://image.eveonline.com/Character/{$characterID}_32.jpg";

		$message = base64_encode(json_encode($data));
		$timestamp = time();
		$hmac = hash_hmac("sha1", $message . ' ' . $timestamp, $disqusSecretKey);

		$js = "var disqus_config = function() {\n";
		$js .= "		this.page.remote_auth_s3 = '{$message} {$hmac} {$timestamp}';\n";
		$js .= "		this.page.api_key = '{$disqusPublicKey}';\n";
		$js .= "\n";
		$js .= "		this.sso = {\n";
		$js .= "			name: 'zKillboard',\n";
		$js .= "			button: '".$fullAddr."/themes/".$theme."/img/disqus_button.png',\n";
		$js .= "			icon: '".$fullAddr."/themes/".$theme."/favicon.ico',\n";
		$js .= "			url: '".$fullAddr."/dlogin/',\n";
		$js .= "			logout: '".$fullAddr."/logout',\n";
		$js .= "			width: '300',\n";
		$js .= "			height: '245'\n";
		$js .= "		};\n";
		$js .= "	};";

		return $js;
	}
}
