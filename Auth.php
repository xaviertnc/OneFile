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
  protected $auth = null;              // {Array} A mapped / normalized subset of the  $authUser array.
  protected $authUser = null;          // {Array}
  protected $authMap = array();
  protected $storeKey = 'auth.user';
  protected $defaultAuthMap = array (
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
   * @param array $authMap The auth table-fields map for your database. Put the authMap in your config file.
   *
   */
  public function __construct($storeKey = null, $authMap = null)
  {
    if ($storeKey) { $this->storeKey = $storeKey; }
    if ($authMap and is_array($authMap)) { $this->authMap = $authMap;}
  }


  // Override me
  // Convert Auth struct to DB-User-Table struct
  public function db_export($auth, array $db_authMap)
  {
    if ( ! $auth) { return $auth; }
    $db_user = array();
    foreach ($auth as $field => $value)
    {
      if (is_null($value)) { continue; }
      if ($value == 'NULL') { $value = null; }
      $db_user[$db_authMap[$field]] = $value;
    }
    return $db_user;
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
    // $db_authMap = $this->db_get_authmap();
    // $uauth = $db_authMap['uauth'];
    // $utable = $db_authMap['utable'];
    // $db_auth_user = DB::first("$utable/array", "WHERE {$uauth}=?", [$username]);
    // return $auth_user ?: null;
    return null;
  }


  // Override me
  public function db_find_by_token($token, $type = null)
  {
    // Example:
    // $db_authMap = $this->db_get_authmap();
    // $db_user = DB::first($db_authMap['utable'], "WHERE {$db_authMap['rcode']}=?", [$token]);
    // $auth = $db_user ? $this->normalizeAuthUser($db_user, $db_authMap) : null;
    // return $auth;
    return null;
  }


  // Override me
  public function db_create($auth)
  {
    // Example:
    // $db_authMap = $this->db_get_authmap();
    // $db_user = $this->db_export($auth, $db_authMap);
    // DB::insertInto($db_authMap['utable'], $db_user);
    // return DB::lastInsertId();
    return null;
  }


  // Override me
  public function db_update($auth)
  {
    // Example:
    // $db_authMap = $this->db_get_authmap();
    // $db_user = $this->db_export($auth, $db_authMap);
    // return DB::update($db_authMap['utable'], 'WHERE {$auth['uid']}=:id', $db_user, [$auth['uid']]);
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
      'uid'   => $auth['uid'],
      'uname' => $auth['uname'],
      'uauth' => $auth['uauth'],
      'phone' => $auth['phone'],
      'home'  => $auth['home'],
      'acc'   => $auth['acc']
    );
  }


  public function db_set_authmap(array $authMap)
  {
    $this->authMap = $authMap;
  }


  public function db_get_authmap()
  {
    return $this->authMap ?: $this->defaultAuthMap;
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


  // Override me
  // Converts DbAuthUser struct to Auth struct via the AuthMap
  public function normalizeAuthUser($db_user, array $authMap)
  {
    if ( ! $db_user) { return $db_user; }
    $auth = array();
    foreach ($authMap as $auth_field => $db_user_field)
    {
      $auth[$auth_field] = array_key_exists($db_user_field, $db_user) ? $db_user[$db_user_field] : null;
    }
    return $auth;
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


  // A normalized and more secure version of $authUser.
  // Contains only AUTH SESSION related fields and
  // ommits any sensitive fields like passwords.
  public function getAuth()
  {
    return $this->auth;
  }


  public function getAuthUser()
  {
    if (isset($this->authUser)) { return $this->authUser; }
    $this->authUser = $this->auth ? $this->fetchAuthUser($this->auth['uname']) : null;
    return $this->authUser;
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

    if (strpos($access,'super') !== false) { return $this->auth; }

    // Note: TRIM is important!
    $user_access_types = array_map('trim', explode(',', $access));
    $allowed_access_types = array_map('trim', explode(',', $allowed_access));
    $shared_access_types = array_intersect($user_access_types, $allowed_access_types);

    if ( ! $shared_access_types)
    {
      // $this->forget();
      $this->auth = null;
      return 'accessdenied';
    }

    return $this->auth;
  }


  public function inGroup($group)
  {
    if (empty($this->auth)) { return false; }
    $access = $this->getAuthAccess();
    $user_access_groups = explode(',', $access);
    return in_array($group, $user_access_groups);
  }


  // Override me. Add more bells and wistles if you need.
  //
  // NOTE: AuthUser == Full user record vs. Auth == Only session essential parts of user record
  //       with no sensitive info like passwords etc.
  public function login($username, $password, $options = null)
  {
    $logPrefix = 'OneFile.Auth::login(), ';

    if ( ! $options) { $options = []; }

    $authUser = null;
    $authInfo = null;
    
    $passedOnToken = false;

    $nopassword = isset($options['nopassword']);
    $rtoken = isset($options['rcode']) ? $options['rcode'] : null;
    $ptoken = isset($options['pcode']) ? $options['pcode'] : null;
    $token = $rtoken ?: $ptoken;

    Log::auth($logPrefix . "token=$token, rtoken=$rtoken, ptoken=$ptoken");

    // Token login
    if ($token)
    {
      // IMPORTANT: Check token length to
      // prevent matching meta or empty values.
      if (strlen($token) > 9)
      {
        $authUser = $this->fetchAuthUserByToken($token, $rtoken ? 'register' : 'lostpsw');
        $passedOnToken = isset($authUser);
        $this->authUser = $authUser;
      }

      if ( ! $passedOnToken) {
        $this->auth = null;
        return $rtoken ? 'badrtoken' : 'badptoken';
      }

      $authInfo = $this->normalizeAuthUser($authUser, $this->db_get_authmap());
      $registered_at = strtotime($rtoken ? $authInfo['rtime'] : $authInfo['ptime']);
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
      if ($authUser) { $authInfo = $this->normalizeAuthUser($authUser, $this->db_get_authmap()); }
      $this->authUser = $authUser;
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
    
    if ( ! $nopassword or ! $passedOnToken)
    {
      $now = time();
      $banInterval = 60*10;       // seconds
      $throttleInterval = 3;        // seconds
      $failsResetInterval = 60*60*24;   // seconds

      $lastFail = strtotime($authInfo['ftime']);

      $failInterval = $now - $lastFail; // seconds

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
      $failsCount = $authInfo['fails'];

      $accParts = []; // e.g. ['super', '!banned'] or ['!failpass|3', 'admin'] etc.
      foreach (explode(',', $authInfo['acc']) as $index => $accPart)
      {
        if ($accPart[0] == '!') { $failState = ltrim($accPart, '!'); } else { $accParts[] = trim($accPart); }
      }

      if ($failState)
      {
        $failStateParts = explode('|', $failState);
        $failsDiff = isset($failStateParts[1]) ? $failStateParts[1] : 0;
        switch ($failState)
        {
          case 'banned'   : if ($failInterval <= $banInterval) { return $failState; } break; // Must be BEFORE 'failpass'!
          case 'failpass'   : if ($failInterval >= $failsResetInterval) { $failsDiff = 0; } break;
          case 'unregistered' : if ( ! $passedOnToken) { return $failState; } break;
          case 'disabled'   : return $failState;
        }
      }
    }
    
    // TEST PASSWORD
    if ($nopassword or $passedOnToken or $this->test_password($password, $authInfo))
    {
      $ip = $this->request_ip();
      $dtime = date('Y-m-d H:i:s');
      $username = strtolower(explode(' ', $authInfo['uname'])[0]);
      $message = $username . ': ' . $ip;
      $auth = $this->reduce($authInfo);
      $auth['acc'] = implode(',', $accParts);
      $auth['utime'] = $dtime;
      $auth['ip'] = $ip;
      $auth['agent'] = $this->request_agent();
      if ($passedOnToken)
      {
        $auth['mtime'] = $dtime;
        $auth['mname'] = $username;
        if ($rtoken) { $auth['rcode'] = $message; $auth['rtime'] = $dtime; $auth['home'] = $this->home_uri(); } // confirmed registration
        elseif ($ptoken) { $auth['pcode'] = $message; $auth['ptime'] = $dtime; } // setting lost password
      }
      $this->db_update($auth);
      $this->auth = $auth;
      $this->save(); // Save $auth to state

      // ---------------------
      // Yay! We logged in OK.
      // ---------------------

      // WARNING: The following log exposes the user's password!
      // Log::auth($logPrefix . 'LOGIN SUCCESSFUL! AuthUser: ' . print_r($this->getAuthUser(), true));

      return $authInfo;
    }

    $auth = $this->reduce($authInfo);

    // Only add info that needs to be updated
    $auth['fails'] = $failsCount+1;
    $auth['ftime'] = date('Y-m-d H:i:s');
    $auth['agent'] = $this->request_agent();
    $auth['ip'] = $this->request_ip();

    // Determine and set: auth['acc'] = failState + $accParts
    $failsDiff++;
    if ($failsDiff > $maxFailsCount) { $failState = 'banned'; $accParts = array_merge(["!$failState"], $accParts); }
    else { $failState = 'failpass'; $accParts = array_merge(["!$failState|$failsDiff"], $accParts); }
    $auth['acc'] = implode(',', $accParts);

    // Write fail info to database
    $this->db_update($auth);

    // -----------------
    // Oops, BAD login!
    // -----------------
    return $failState;
  }


  // Override me. Add more bells and wistles if you need.
  public function register(array $guestInfo, $options = null)
  {
    $guestAuthInfo = $this->normalizeAuthUser($guestInfo, $this->db_get_authmap());

    $authUser = $this->fetchAuthUser($guestAuthInfo['uauth']);
    if ($authUser) // Guest is an existing user!
    {
      $authInfo = $this->normalizeAuthUser($authUser, $this->db_get_authmap());
      $this->authUser = $authUser;
    }
    else
    {
      $authInfo = $guestAuthInfo;
    }

    $authInfo['rtime'] = date('Y-m-d H:i:s');
    $authInfo['rcode'] = md5($authInfo['rtime']);
    $authInfo['cname'] = strtolower(explode(' ', $authInfo['uname'])[0]); // created-by name
    $authInfo['upass'] = $this->encode_password($authInfo['upass']);
    $authInfo['agent'] = $this->request_agent();
    $authInfo['ip'] = $this->request_ip();

    if (isset($authInfo['acc']))
    { // Only allow updating an unregistered user! Otherwise complain that the user exists.
      if (strpos($authInfo['acc'], '!unreg') === false) { return 'userexists'; }
      $authInfo['acc'] = '!unregistered,' . $options['access'];
      // Re-register an existing + unconfirmed user.
      $this->db_update($authInfo);
    }
    else
    {
      $authInfo['acc'] = '!unregistered,' . $options['access'];
      $authInfo['uid'] = $this->db_create($authInfo);
      $authUser = $this->fetchAuthUser($authInfo['uauth']);
      $this->authUser = $authUser;
    }

    $auth = $this->reduce($authInfo);
    $this->auth = $auth;
    $this->save(); // Save $auth to state

    return $authInfo;
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
