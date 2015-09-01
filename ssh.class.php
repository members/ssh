<?php

/**
 * SSH Client Class
 * v 0.2
 *
 * Checking:
 * $ php -m | grep ssh
 *
 * Installing:
 * $ sudo apt-get install php5-dev php5-cli php-pear build-essential openssl-dev zlib1g-dev libssh2-1-dev
 * $ sudo pecl install -f ssh2
 * $ echo 'extension=ssh2.so' > /etc/php5/conf.d/ssh2.ini
 *
 * Using:
 * |
 * |- Connect:
 * |  __construct([$host = "localhost"[, $login = "root"[, $password = ""[, $port = 22]]]]);
 * |  | $ssh = new ssh('google.com', 'root', 'password'[, $port = 22]);
 * |
 * |- Exec command:
 * |  
 * |  | $result = $ssh("ls -la");
 * |    or
 * |  | $array_of_result = $ssh(array("ls -la", "uptime"));
 * |    or
 * |  | $array_of_result = $ssh("ls -la", "uptime");
 * |    or
 * |  | $array_of_result = $ssh(array("echo 1", "echo 2"), "echo 3", array(array("echo 4", "echo 5", "echo 6"), "echo 7"));
 * |
 * |- Tunnel:
 * |  | $f = $ssh->tunnel("10.0.0.100", 1234);
 * |
 * |- Download:
 * |  | $ssh->download("/remote/file", "/local/file");
 * |
 * |- Upload:
 * |  | $ssh->upload("/local/file", "/remote/file"[, $file_mode = 0777]);
 * |
 * |- Reconnect:
 * |  | $ssh->reconnect();
 * |
 * |- Disconnect is automatic;
 *
 * Thx 4 using;
 *
 */
 
class ssh {
	private $host      = "localhost";
	private $login     = "root";
	private $password  = "";
	private $port      = 22;
	private $connect   = "";
	public  $connected = FALSE;

	public function __construct($host = "localhost", $login = "root", $password = "", $port = 22) {
		$this->host     = $host;
		$this->login    = $login;
		$this->password = $password;
		$this->port     = $port;
	}

	public function __destruct() {
		$this->disconnect();
	}

	public function __invoke() {
		$out = array();
		if(func_num_args() === 1) {
			if(is_array(func_get_arg(0))) {
				return call_user_func_array(array($this, "__invoke"), func_get_arg(0));
			} else {
				return call_user_func(array($this, "exec"), func_get_arg(0));
			}
		} else {
			foreach(func_get_args() as $k => $arg) {
				if(is_array($arg)) {
					$out[$k] = call_user_func_array(array($this, "__invoke"), $arg);
				} else {
					$out[$k] = call_user_func(array($this, "exec"), $arg);
				}
			}
		}
		return $out;
	}
	
	/**
	 * @brief get list of files in a $path on remote host
	 * @param string $path 
	 * @return array (empty array on some failure)
	 */
	public function ls($path) {
		if (!$this->connect()) {
			return false;
		}
		
		$sftp = @ssh2_sftp($this->connect);
		if (!$sftp) {
			return array();
		}
		
	    // prepare the path for SSH
	    $path = str_replace(array('/', '\\'), '/', $path);
	    
		// some work-around for the root folder
		if ($path == '/') {
			$path = '/./';
		} else {
			$path = rtrim($path, '/').'/';
		}
		
		// the final files list array
		$files = array();
		
		// open the folder
		$path = "ssh2.sftp://{$sftp}{$path}";
		$dh = @opendir($path);
		if ($dh === false) {
			return $files;
		}
		
		// gather the information
		while (($file = @readdir($dh)) !== false) {
			if ($file == '.' || $file == '..') {
				continue;
			}
			
			$files[] = array(
				'type' => @filetype($path.$file) ?: 'file',
				'name' => $file,
			);
		}
		
		return $files;
	}

	public function tunnel($host = "localhost", $port = 22) {
		if (!$this->connect()) {
			return false;
		}
		return @ssh2_tunnel($this->connect, $host, $port);
	}

	public function reconnect() {
		$this->disconnect();
		return $this->connect();
	}

	public function download($remote_file = "/", $local_file = "/") {
		if (!$this->connect()) {
			return false;
		}
		return @ssh2_scp_recv($this->connect, $remote_file, $local_file);
	}

	public function upload($local_file = "/", $remote_file = "/", $file_mode = 0777) {
		if (!$this->connect()) {
			return false;
		}
		return @ssh2_scp_send($this->connect, $local_file, $remote_file, $file_mode);
	}

	public function mkdir($remote_folder, $mode = 0777) {
		if (!$this->connect()) {
			return false;
		}
		
		$sftp = @ssh2_sftp($this->connect);
		if ($sftp === false) {
			return false;
		}
		
		return @ssh2_sftp_mkdir($sftp, $remote_folder, $mode);
	}

	public function connect() {
		if (!$this->connected) {
			$this->connect = @ssh2_connect($this->host, $this->port);
			if (!$this->connect) {
				return false;
			}

			$this->connected = @ssh2_auth_password($this->connect, $this->login, $this->password);
			if (!$this->connected) {
				return false;
			}
		}
		return $this->connected;
	}

	private function exec($c) {
		if (!$this->connect()) {
			return false;
		}

		$d = "";
		$s = @ssh2_exec($this->connect, $c);
		if($s) {
			stream_set_blocking($s, TRUE);
			while($b = fread($s, 4096)) {
				$d  .= $b;
			}
			fclose($s);
		} else {
			// Fail: Unable to execute command
			return false;
		}
		
		return $d;
	}

	private function disconnect() {
		if($this->connected) {
			@ssh2_exec($this->connect, 'exit');
			$this->connected = FALSE;
		}
	}
}

?>
