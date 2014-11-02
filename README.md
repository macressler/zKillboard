[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/EVE-KILL/zKillboard/badges/quality-score.png?s=b2d7078f70f6d3bd691e47db89eb455ec4c6002b)](https://scrutinizer-ci.com/g/EVE-KILL/zKillboard/)

# zKillboard
zKillboard is a killboard created for EVE-Online, for use on EVE-KILL.net, but can also be used for single entities.

# WARNING
This is BETA, which means it is a work in progress.  It lacks complete documentation and is currently not meant for use in production.

Since zKillboard is a beta product, it has a code base that is far from complete and enjoys numerous updates, deletions, and modifications to the code and accompanying tables. Please feel free to attempt to install zKillboard on your own server, however, we are not responsible for any difficulties you come across during installation and continuing execution of the product.

# Credits
zKillboard is released under the GNU Affero General Public License, version 3. The full license is available in the `AGPL.md` file.
zKillboard also uses data and images from EVE-Online, which is covered by a seperate license from _[CCP](http://www.ccpgames.com/en/home)_. You can see the full license in the `CCP.md` file.
It also uses various 3rd party libraries, which all carry their own licensing. Please refer to them for more info.

# Contact
`#esc` on `irc.coldfront.net`
Mibbit link incase you're lazy: _http://chat.mibbit.com/?channel=%23esc&server=irc.coldfront.net_

# LICENSE
see `LICENSE.md` file

# Minimum requirements
- PHP 5.4+ / HHVM 3.0+
- Apache + mod_rewrite, Nginx or any other httpd you prefer that supports php via mod_php or fastcgi.
- Linux, Mac OS X or Windows
- MariaDB 5.5+
- Composer
- cURL and php5-curl

# Recommended requirements
- PHP 5.5+ / HHVM 3.0+
- Linux
- MariaDB 5.5+
- Composer
- APC / Redis / Memcached (Doesn't matter which one)
- cURL and php5-curl

# Nginx Config
```
upstream php-upstream {
  server unix:/tmp/php-fpm.sock;
  server 127.0.0.1:9000;
}

server {
  server_name example.com www.example.com;
  listen      80;
  root        /path/to/zkb_install;

  location    / {
    try_files $uri $uri/ /index.php$is_args$args;
  }

  location    ~ \.php$ {
    try_files $uri = 404;
    include   fastcgi_params;
    fastcgi_index index.php;
    fastcgi_pass php-upstream;
  }
}

```

# Apache rewrite
Apache rewrite is handled by the .htaccess.

# Apache Config
```
<VirtualHost *:80>
        ServerAlias yourdomain.tld

        DocumentRoot /path/to/zkb_install/
        <Directory /path/to/zkb_install/>
          Require all granted
          Options FollowSymLinks MultiViews
          AllowOverride All
          Order allow,deny
          Allow from all
        </Directory>
</VirtualHost>
```

# Other webservers
Other webservers are supported, aslong as they can run PHP, they should work.
But other webservers have other ways to write rewrites, so from our side of things, they are unsupported.
Feel free to issue pull requests to amend this.

# Installation
Installation is handled via command line. Other methods are currently not supported.

1. `cd` to a dir where you want zKillboard to reside.
2. Do `git clone git@github.com:EVE-KILL/zKillboard.git`.
3. `cd` into `zKillboard` dir.
4. Get composer. `curl -s https://getcomposer.org/installer | php`
5. Install vendor files with composer. `php composer.phar install`
6. `cd` into `install` dir.
7. Execute the installation script. `php5 install.php`
8. Follow the instructions and fill in the prompts
9. Setup stomp (Follow guide further down)
10. Setup the CLI system.
11. Setup cronjobs

# CLI System
1. Symlink `cli.php` to `/usr/bin/zkillboard` `ln -s /path/to/zkb/cli.php /usr/bin/zkillboard`
2. Install `bash-completion`. Under Debian this can be done like so `apt-get install bash-completion`
3. Move `bash_complete_zkillboard` to `/etc/bash_completion.d/zkillboard`
4. Restart your shell session
5. Issue `zkillboard list` and enjoy the zkillboard cli interface, with full tab completion

# Cronjobs
zKillboard comes with a script that automates the cron execution.
It keeps track of when each job has been run and how frequently it needs to be executed.
Just run it every minute via cron or a similar system:

```
* * * * * /var/killboard/zkillboard.com/cron.php >/whatever/log/you/like.txt 2>&1
```

If you're not happy with the default timeouts, or want to disable/enable some jobs entirely, you can use the cron.overrides file.
The cron.overrides file has to be placed into the zKB root dir, next to the cron.php script. It's a simple json file, with the following format:

```json
{
    "commandName":{
        "timeoutInSeconds":"arguments"
    }
}
```

For example the following would disable stompReceive entirely, and increase the timeout for apiFetch and parseKills to 5 minutes:
```json
{
    "stompReceive":{},
    "apiFetch":{
        "300":""
    },
    "parseKills":{
        "300":""
    }
}
```

All cronjobs can be launched manually with the cli interface.

# Stomp
Stomp uses the STOMP PHP plugin which you can get via git.
Here is a quick one liner: `cd /tmp/ && git clone https://github.com/php/pecl-tools-stomp.git && cd pecl-tools-stomp && phpize && ./configure && make && make install`
After that you make a `20-stomp.ini` in `/etc/php5/conf.d/` and add `extension=stomp.so` to it, and save. And restart your php stuff.

The stomp service is read only. If you need to send data via it, come by IRC and have a chat with us.

- Stomp server: `tcp://eve-kill.net:61613`
- Stomp user: `guest`
- Stomp pass: `guest`

# HHVM
zKillboard runs perfectly under HHVM (HipHop Virtual Machine).<br>
To get HHVM look at _https://github.com/facebook/hhvm/wiki#installing-pre-built-packages-for-hhvm_

# HHVM Config
```
hhvm.server.type = fastcgi
hhvm.server.file_socket = /run/shm/hhvm.sock

hhvm.server.apc.enable_apc = true
hhvm.server.apc.table_type = concurrent
hhvm.server.apc.expire_on_sets = true
hhvm.server.apc.purge_frequency = 4096

hhvm.eval.jit = true
hhvm.eval.jit_warmup_requests = 50

hhvm.repo.central.path = /var/log/hhvm/.hhvm.hhbc

hhvm.mysql.readOnly = false
hhvm.mysql.connect_timeout = 1000
hhvm.mysql.read_timeout = 2000
hhvm.mysql.slow_query_threshold = 4000
hhvm.mysql.kill_on_timeout = true
hhvm.mysql.wait_timeout = -1
hhvm.mysql.typed_results = true
```

# CREST SSO
zKillboard has support for logging in with CREST.

To get it setup, you must first get a CREST clientID/Secret from CCP. Currently only sisi login is available.
You can get it from _https://developers.testeveonline.com/applications_

The rest is self explanitory

# Admin account

Every clean zKillboard installation comes with an admin account, default username and password is `admin`, it is highly recommended that you immediately change this password after you finish your installation.

Current special features to the admin account:

1) Any entities (pilots, corporations, etc.) added to the Admin's tracker will automatically be fetched from _https://zkillboard.com_ up to and including a full fetch of all kills, and maintaining a fetch of said kills on an hourly basis. This of course depends on the cronjob being setup.