<?php

/**
 * Simple script for backup MySQL database on remote server
 */

include("ssh.class.php");

$server_host = "ssh.example.com";
$server_login = "root";
$server_password = "123456"; // Didn't use simple passwords

$mysql_user = "root";
$mysql_password = "123456"; // Didn't use simple passwords
$mysql_database = "base";

// Connect:
$ssh = new ssh($server_host, $server_login, $server_password);

// Make backup:
$ssh("mysqldump -u $mysql_user -p{$mysql_password} $mysql_database > /tmp/bases", "gzip -9 /tmp/bases");

// Download backup:
$ssh->download("/tmp/bases.gz", "/my/backups/dir/bases.gz");

// Delete backup on server
$ssh("rm /tmp/bases.gz");


?>
