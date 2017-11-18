<?php namespace OneFile;

// NOTE: Log should not be here! For debug only!
use Log;

/**
 * App Authentication Service
 *
 * @author C. Moller <xavier.tnc@gmail.com> - 23 Nov 2016
 *
 */
class Auth
{
	protected $auth = null;
	protected $schema = array();
	protected $storeKey = 'auth.user';
	protected $defaultSchema = array (
		'uid'   => 'id',
		'activ' => 'active',
		'uname' => 'name',
 		'uauth' => 'username',
		'upass' => 'password',
		'utable'=> 'tblusers',
		'utime' => 'lastlogin',
		'phone' => 'phone',
		'rtime' => 'registrationtime',
		'rcode' => 'registrationcode',
 		'ptime' => 'pwdreqtime',
 		'pcode' => 'pwdreqcode',
		'acc'   => 'access', // E.g. 'super', 'admin', '!banned', '!unregistered', '!disabled,admin', '!failpass|2,admin'
		'fails' => 'loginfails',
		'ftime' => 'failed_on',
		'ip'    => 'ip_address',
		'agent' => 'browser_agent',
		'home'  => 'homelink',
		'cname' => 'created_by',
		'ctime' => 'created_on',
		'mname' => 'updated_by',
		'mtime' => 'updated_on',
		'dname' => 'deleted_by',
		'dtime' => 'deleted_on',
		'trash' => 'trashed',
	);


	/**
	 *
	 * @param array $schema The auth table-fields map for your database. Put the schema in your config file.
	 *
	 */
	public function __construct($storeKey = null, $schema = null)
	{
		if ($storeKey) { $this->storeKey = $storeKey; }
		if ($schema and is_array($schema)) { $this->schema = $schema;}
	}


	// Override me
	// Convert Auth struct to DB-User-Table struct
	public function db_export($auth, array $db_schema)
	{
		if ( ! $auth) { return $auth; }
		$db_user = array();
		foreach ($auth as $field => $value)
		{
			if (is_null($value)) { continue; }
			if ($value == 'NULL') { $value = null; }
			$db_user[$db_schema[$field]] = $value;
		}
		return $db_user;
	}


	// Override me
	// Convert DB-User-Table struct to Auth struct
	public function db_import($db_user, array $db_schema)
	{
		if ( ! $db_user) { return $db_user; }
		$auth = array();
		foreach ($db_schema as $auth_field => $db_field) { $auth[$auth_field] = isset($db_user[$db_field]) ? $db_user[$db_field] : null; }
		return $auth;
	}


	// Override me
	public function load()
	{
		return isset($_SESSION[$this->storeKey]) ? $_SESSION[$this->storeKey] : null;
	}


	// Override me
	public function save()
	{
		$_SESSION[$this->storeKey] = $this->auth;
	}


	// Override me
	public function forget()
	{
		unset($_SESSION[$this->storeKey]);
	}


	// Override me
	public function db_find($username)
	{
		// Example:
		// $db_schema = $this->db_get_schema();
		// $db_user = DB::first($db_schema['utable'], "WHERE {$db_schema['uauth']}=?", [$username]);
		// $auth = $db_user ? $this->db_import($db_user, $db_schema) : null;
		// return $auth;
		return null;
	}


	// Override me
	public function db_find_by_token($token, $type = null)
	{
		// Example:
		// $db_schema = $this->db_get_schema();
		// $db_user = DB::first($db_schema['utable'], "WHERE {$db_schema['rcode']}=?", [$token]);
		// $auth = $db_user ? $this->db_import($db_user, $db_schema) : null;
		// return $auth;
		return null;
	}


	// Override me
	public function db_create($auth)
	{
		// Example:
		// $db_schema = $this->db_get_schema();
		// $db_user = $this->db_export($auth, $db_schema);
		// DB::insertInto($db_schema['utable'], $db_user);
		// return DB::lastInsertId();
		return null;
	}


	// Override me
	public function db_update($auth)
	{
		// Example:
		// $db_schema = $this->db_get_schema();
		// $db_user = $this->db_export($auth, $db_schema);
		// return DB::update($db_schema['utable'], 'WHERE {$auth['uid']}=:id', $db_user, [$auth['uid']]);
		return null;
	}


	// Override me
	protected function home_uri()
	{
		// Example:
		// return Config::get('default_user_home_url');
		return null;
	}


	// Override me
	protected function request_ip()
	{
		return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
	}


	// Override me
	protected function request_agent()
	{
		return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
	}


	// Override me.
	public function encode_password($password)
	{
		return password_hash($password, PASSWORD_DEFAULT);
		//return $password;
	}


	// Override me
	public function reduce($auth)
	{
		return array(
			'uid'		=> $auth['uid'],
			'uname'		=> $auth['uname'],
			'uauth'		=> $auth['uauth'],
			'phone'		=> $auth['phone'],
			'home'		=> $auth['home'],
			'acc'		=> $auth['acc']
		);
	}


	public function db_set_schema(array $schema)
	{
		$this->schema = $schema;
	}


	public function db_get_schema()
	{
		return $this->schema  ?: $this->defaultSchema;
	}


	public function db_set_password($new_password)
	{
		$ip = $this->request_ip();
		$dtime = date('Y-m-d H:i:s');
		$username = strtolower(explode(' ', $this->getAuthName())[0]);
		$message = $username . ': ' . $ip;

		$authUser = ['uid' => $this->getAuthId()];

		$authUser['ptime'] = $dtime;
		$authUser['pcode'] = $message;
		$authUser['mtime'] = $dtime;
		$authUser['mname'] = $username;
		$authUser['upass'] = $this->encode_password($new_password);
		$authUser['agent'] = $this->request_agent();
		$authUser['ip']    = $ip;

		if ($this->db_update($authUser)) return $message;
	}


	public function test_password($password, $authUser)
	{
		//$logPrefix = 'OneFile.Auth::test_password(), ';
		if ( ! $authUser) { return false; }
		//Log::auth($logPrefix . 'hash = ' . password_hash($password, PASSWORD_DEFAULT));
		$pass = password_verify($password, $authUser['upass']);
		//Log::auth($logPrefix . 'password_verify = ' . ($pass ? 'PASS' : 'FAIL'));
		return $pass;
	}


	public function fetchAuthUser($username = null)
	{
		$logPrefix = 'OneFile.Auth::fetchAuthUser(), ';
		Log::auth($logPrefix . 'username = ' . $username);
		return $username ? $this->db_find($username) : null;
	}


	public function fetchAuthUserByToken($token = null, $type = null)
	{
		$logPrefix = 'OneFile.Auth::fetchAuthUserByToken(), ';
		Log::auth($logPrefix . 'token = ' . $token . ', type = ' . $type);
		return $token ? $this->db_find_by_token($token, $type) : null;
	}


	public function getAuthUser()
	{
		return $this->auth;
	}


	public function getAuthId()
	{
		return isset($this->auth['uid']) ? $this->auth['uid'] : 0;
	}


	public function getAuthName()
	{
		return isset($this->auth['uname']) ? $this->auth['uname'] : null;
	}


	public function getAuthPhone()
	{
		return isset($this->auth['phone']) ? $this->auth['phone'] : null;
	}


	public function getAuthLoginName()
	{
		return isset($this->auth['uauth']) ? $this->auth['uauth'] : null;
	}


	public function getAuthAccess()
	{
		return isset($this->auth['acc']) ? $this->auth['acc'] : null;
	}


	public function check($allowed_access, $options = null)
	{
		if ( ! $allowed_access) {
			// No access control required.
			// We don't return a string, so it's NOT an error!
			return;
		}

		if (empty($this->auth)) {
			// The auth session expired or a guest user tried to access a protected page!
			// Don't use $this->logout() here, it writes to the journal + logfile which we don't want.
			//$this->forget(); // Redundant - NM 20 Dec 2016
			$this->auth = null;
			return 'guestsession';
		}

		$access = $this->getAuthAccess();

		if ($access == 'super') { return $this->auth; }

		$user_access_types = explode(',', $access);
		$allowed_access_types = explode(',', $allowed_access);
		foreach	($allowed_access_types as $access_type)
		{
			if ( ! in_array(trim($access_type), $user_access_types))
			{
				//$this->forget();
				$this->auth = null;
				return 'accessdenied';
			}
		}

		return $this->auth;
	}


	// Override me. Add more bells and wistles if you need.
	//
	// NOTE: AuthUser == Full user record vs. Auth == Only session essential parts of user record
	//       with no sensitive info like passwords etc.
	public function login($username, $password, $options = null)
	{
		$logPrefix = 'OneFile.Auth::login(), ';

		$authUser = null;

		$passedOnToken = false;

		$rtoken = isset($options['rcode']) ? $options['rcode'] : null;
		$ptoken = isset($options['pcode']) ? $options['pcode'] : null;
		$token = $rtoken ?: $ptoken;

		Log::auth($logPrefix . "token=$token, rtoken=$rtoken, ptoken=$ptoken");

		// Token login
		if ($token)
		{
			// IMPORTANT: Check token length to
			// prevent matching meta or empty values.
			if (strlen($token) > 30)
			{
				$authUser = $this->fetchAuthUserByToken($token, $rtoken ? 'register' : 'lostpsw');
				$passedOnToken = isset($authUser);
			}

			if ( ! $passedOnToken) {
				$this->auth = null;
				return $rtoken ? 'badrtoken' : 'badptoken';
			}

			$registered_at = strtotime($rtoken ? $authUser['rtime'] : $authUser['ptime']);
			$age = time() - $registered_at;
			$max_age = 60*60*48;  // seconds i.e. 48hrs
			if ($age > $max_age)
			{
				$this->auth = null;
				return $rtoken ? 'expiredrtoken' : 'expiredptoken';
			}
		}
		// Normal login
		elseif( $username and $password)
		{
			$authUser = $this->fetchAuthUser($username);
		}
		else
		{
			// Don't use $this->logout() here, it writes to the journal + logfile which we don't want.
			$this->auth = null;
			return 'invalid';
		}

		if (empty($authUser))
		{
			// Don't use $this->logout()
			$this->auth = null;
			return 'notfound';
		}

		$now = time();
		$banInterval = 60*10;				// seconds
		$throttleInterval = 3;				// seconds
		$failsResetInterval = 60*60*24;		// seconds

		$lastFail = strtotime($authUser['ftime']);

		$failInterval = $now - $lastFail;	// seconds

		//dd($now, $lastFail, $failInterval, $minBanInterval);

		if ($failInterval <= $throttleInterval)
		{
			// Don't use $this->logout()
			$this->auth = null;
			return 'throttled';
		}

		$failsDiff = 0;
		$failState = null;
		$maxFailsCount = 5;
		$failsCount = $authUser['fails'];

		$accParts = []; // e.g. ['super', '!banned'] or ['!failpass|3', 'admin'] etc.
		foreach (explode(',', $authUser['acc']) as $index => $accPart)
		{
			if ($accPart[0] == '!') { $failState = ltrim($accPart, '!'); } else { $accParts[] = trim($accPart); }
		}

		if ($failState)
		{
			$failStateParts = explode('|', $failState);
			$failsDiff = isset($failStateParts[1]) ? $failStateParts[1] : 0;
			switch ($failState)
			{
				case 'banned'		: if ($failInterval <= $banInterval) { return $failState; } break; // Must be BEFORE 'failpass'!
				case 'failpass'		: if ($failInterval >= $failsResetInterval) { $failsDiff = 0; } break;
				case 'unregistered'	: if ( ! $passedOnToken) { return $failState; } break;
				case 'disabled'		: return $failState;
			}
		}

		if ($passedOnToken or $this->test_password($password, $authUser))
		{
			$ip = $this->request_ip();
			$dtime = date('Y-m-d H:i:s');
			$username = strtolower(explode(' ', $authUser['uname'])[0]);
			$message = $username . ': ' . $ip;
			$authUser = $this->reduce($authUser);
			$authUser['acc'] = implode(',', $accParts);
			$authUser['utime'] = $dtime;
			$authUser['ip'] = $ip;
			$authUser['agent'] = $this->request_agent();
			if ($passedOnToken)
			{
				$authUser['mtime'] = $dtime;
				$authUser['mname'] = $username;
				if ($rtoken) { $authUser['rcode'] = $message; $authUser['rtime'] = $dtime; $authUser['home'] = $this->home_uri(); } // confirmed registration
				elseif ($ptoken) { $authUser['pcode'] = $message; $authUser['ptime'] = $dtime; } // setting lost password
			}
			$this->db_update($authUser);
			$this->auth = $authUser;
			$this->save(); // Save $auth to state
			return $this->auth;
		}

		$authUser = $this->reduce($authUser);

		// Only add info that needs to be updated
		$authUser['fails'] = $failsCount+1;
		$authUser['ftime'] = date('Y-m-d H:i:s');
		$authUser['agent'] = $this->request_agent();
		$authUser['ip'] = $this->request_ip();

		// Determine and set: authUser['acc'] = failState + $accParts
		$failsDiff++;
		if ($failsDiff > $maxFailsCount) { $failState = 'banned'; $accParts = array_merge(["!$failState"], $accParts); }
		else { $failState = 'failpass'; $accParts = array_merge(["!$failState|$failsDiff"], $accParts);	}
		$authUser['acc'] = implode(',', $accParts);

		// Write fail info to database
		$this->db_update($authUser);

		return $failState;
	}


	// Override me. Add more bells and wistles if you need.
	public function register(array $userInfo, $options = null)
	{
		$authInfo = $this->db_import($userInfo, $this->db_get_schema());
		$authUser = $this->fetchAuthUser($authInfo['uauth']);
		if ( ! $authUser) { $authUser = $authInfo; }

		$authUser['rtime'] = date('Y-m-d H:i:s');
		$authUser['rcode'] = md5($authUser['rtime']);
		$authUser['cname'] = strtolower(explode(' ', $authUser['uname'])[0]);
		$authUser['upass'] = $this->encode_password($authUser['upass']);
		$authUser['agent'] = $this->request_agent();
		$authUser['ip'] = $this->request_ip();

		if (isset($authUser['acc']))
		{	// Only allow updating an unregistered user! Otherwise complain that the user exists.
			if (strpos($authUser['acc'], '!unreg') === false) { return 'userexists'; }
			$authUser['acc'] = '!unregistered,' . $options['access'];
			$this->db_update($authUser);
		}
		else
		{
			$authUser['acc'] = '!unregistered,' . $options['access'];
			$this->db_create($authUser);
			$authUser = $this->fetchAuthUser($authUser['uauth']);
		}

		$this->auth = $this->reduce($authUser);
		$this->save(); // Save $auth to state

		return $authUser;
	}


	public function logged_in()
	{
		return empty($this->auth) ? false : true;
	}


	// Override me. Add more bells and wistles if you like.
	// NB: Remember to delete the auth user from the SESSION afterwards also!
	public function logout()
	{
		$this->auth = null;
		$this->forget();
	}
}
