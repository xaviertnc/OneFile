<?php namespace F1;

/**
 * F1 - Security Class - 15 Oct 2022
 * 
 * "Security" issues encrypted tokens, similar to JWT, in secure HTTP Only
 * browser cookies for user authentication. These tokens are encrypted and not 
 * accessible via Javascript, containing the user's ID and a timestamp.
 * 
 * Server-side sessions are activated only for authenticated users. This 
 * optimizes server performance and minimizes DOS vulnerabilities by 
 * eliminating unnecessary database queries and guest session files.
 * 
 * The token-based method provides a more reliable session expiry mechanism
 * and allows user-specific logic (e.g. logs) before session initiation.
 * 
 *
 * @author  C. Moller <xavier.tnc@gmail.com>
 * 
 * @version 2.2 - FT - 13 May 2024
 *   - Improve decryptToken() method to handle both JSON and string tokens
 *     i.e. It now returns the decrypted string if json_decode fails.
 * 
 */

class Security
{
  const TOKEN_LIFE_SPAN = 3600;  // 1 hour in seconds
  const TOKEN_EXTEND_THRESHOLD = 600;  // 10 minutes

  /**
   * The current auth user object
   * @var array|null e.g. [ 'id' => $userId, 'username' => $username, 'role' => $role ]
   */
  private $user;

  /**
   * The decrypted token
   * u = user object, t = (int) timestamp
   * @var array|null e.g. [ 'u' => [ 'id' => $userId, ... ], 't' => $timestamp ]
   */
  private $token;

  private $secretKey = null;
  private $encryptedToken = null;
  private $loggedIn = false;


  public function __construct( $secretKey = null, $encryptedToken = null, $startSession = true ) {
    $this->secretKey = $secretKey;
    $this->encryptedToken = $encryptedToken ?? $this->getTokenFromCookie();
    $this->setToken( $this->encryptedToken, true );
    if ( $startSession ) $this->startSessionFromToken( $this->token );
  }


  public function getSession() {
    if ( session_status() === PHP_SESSION_NONE ) session_start();
    return $_SESSION;
  }


  public function writeToSession( $key, $value = null ) {
    if ( is_array( $key ) ) $_SESSION = $key;
    else $_SESSION[$key] = $value;
  }


  public function destorySession() {
    if ( session_status() === PHP_SESSION_NONE ) session_start();
    // Delete the session cookie
    if ( ini_get( 'session.use_cookies' ) ) {
      $params = session_get_cookie_params();
      setcookie( session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
      );
    }    
    $this->setTokenCookie( '' );
    $this->writeToSession( [] );
    session_destroy();
  }


  public function encrypt( $inputString, $secretKey = null ) {
    $secretKey = $secretKey ?: $this->secretKey;
    $encrypted = []; $keyLength = strlen( $secretKey );
    for ( $i = 0; $i < strlen( $inputString ); $i++ ) {
      $char = ord( $inputString[$i] ) - 32;
      $keyChar = ord( $secretKey[$i % $keyLength] ) - 32;
      $encryptedChar = chr( ( ( ( $char + $keyChar ) % 95 ) + 32 ) );
      $encrypted[] = $encryptedChar;
    }
    return implode( '', $encrypted );
  }


  public function decrypt( $encryptedString, $secretKey = null ) {
    $secretKey = $secretKey ?: $this->secretKey;
    $decrypted = []; $keyLength = strlen( $secretKey );
    for ( $i = 0; $i < strlen( $encryptedString ); $i++ ) {
      $char = ord( $encryptedString[$i] ) - 32;
      $keyChar = ord( $secretKey[$i % $keyLength] ) - 32;
      $decryptedChar = chr( ( ( ( $char - $keyChar + 95 ) % 95 ) + 32 ) );
      $decrypted[] = $decryptedChar;
    }
    return implode( '', $decrypted );
  }


  public function getToken() { return $this->token; }


  public function setToken( $token, $decrypt = false ) {
    $this->token = ( $decrypt and $token ) ? $this->decryptToken( $token ) : $token;
  }


  public function encryptToken( $token, $secret = null ) {
    return $token ? $this->encrypt( json_encode( $token ), $secret ) : null;
  }


  public function decryptToken( $encryptedToken, $secret = null ) {
    if ( ! $encryptedToken ) return;
    $decryptedTokenStr = $this->decrypt( $encryptedToken, $secret );
    $tokenObj = json_decode( $decryptedTokenStr, true ); // Returns null on error
    return $tokenObj ?: $decryptedTokenStr;
  }


  public function tokenExpired( array $token ) {
    $timeElapsed = time() - $token['t'];
    debug_log( $timeElapsed . ', max: ' . self::TOKEN_LIFE_SPAN, 
      'Security::tokenExpired(), token age: ', 3, 'security' );
    return $timeElapsed > self::TOKEN_LIFE_SPAN;
  }


  public function validateToken( $token ) {
    $logType = 'security';
    $logPrefix = 'Security::validateToken(), ';
    debug_log( $logPrefix . 'Says Hi!', '', 2, $logType );
    debug_log( $token, $logPrefix . 'token: ', 5, $logType );
    $formatValid = $token and isset( $token['u'], $token['t'] );
    if ( ! $formatValid ) return debug_log( 'Token Invalid', $logPrefix, 2, $logType );
    if ( $this->tokenExpired( $token ) ) return debug_log( 'Token Expired', $logPrefix, 2, $logType );
    return true;
  }


  public function setTokenCookie( $encryptedToken ) {
    setcookie( 'token', $encryptedToken, [
      'httponly' => true,
      'samesite' => 'Strict',
      'secure' => true,
      'path' => '/',
    ] );
  }


  public function getTokenFromCookie() {
    debug_log( json_encode( $_COOKIE ), 'Security::getTokenFromCookie(), $_COOKIE: ', 3 );
    return $_COOKIE['token'] ?? '';
  }


  public function startSessionFromToken( $token = null ) {
    debug_log( 'Security::startSessionFromToken(), Says Hi!', '', 3, 'security' );
    if ( ! $this->validateToken( $token ) ) return $this->logout();
    $tokenUser = $token['u'];
    $timeElapsed = time() - $token['t'];
    $refreshToken = $timeElapsed > self::TOKEN_EXTEND_THRESHOLD;
    // NOTE: Session will fail if the `token user` does not match the `session user`!
    $this->loggedIn = $this->startUserSession( $tokenUser );
    if ( $refreshToken and $this->loggedIn ) {
      $newToken = [ 'u' => $tokenUser, 't' => time() ];
      $this->setTokenCookie( $this->encryptToken( $newToken ) );
    }
    debug_log( $this->loggedIn ? 'Yes' : 'No', 'Security::startSessionFromToken(), loggedIn: ', 2, 'security' );
    return $this->loggedIn;    
  }


  public function startUserSession( $asUser ) {
    $logType = 'security';
    $logPrefix = 'Security::startUserSession(), ';
    debug_log( json_encode( $asUser ), $logPrefix . 'user: ', 3, $logType );
    if ( ! $asUser ) return false;
    $session = $this->getSession();
    debug_log( json_encode( $session ), $logPrefix . '$_SESSION: ', 3, $logType );
    // If there is no session user, set the provided user as the session user
    $this->user = $session['user'] ?? $asUser;
    $this->writeToSession( 'user', $this->user );
    $sessionUserId = $this->user['id'] ?? '';
    $asUserId = $asUser['id'] ?? '';
    debug_log( $sessionUserId, $logPrefix .'sessionUserId: ', 3, $logType );
    debug_log( $asUserId, $logPrefix . 'loginAsUserId: ', 3, $logType );
    return $sessionUserId === $asUserId;
  }


  public function getUser() { return $this->user; }
  public function getUserId() { return $this->user['id'] ?? null; }
  public function getUsername() { return $this->user['username'] ?? null; }
  public function getUserRole() { return $this->user['role'] ?? null; }


  public function denyIfNot( $condition, $message = null ) {
    if ( ! $condition ) {
      http_response_code( 403 );
      exit( $message ?: 'Access denied' );
    }
  }


  public function denyIfRoleNot( $roles, $message = null ) {
    if ( ! is_array( $roles ) ) $roles = [ $roles ];
    debug_log( json_encode( $roles ), 'Security::denyIfRoleNot(), allowed roles: ', 3, 'security' );
    $currentRole = $this->getUserRole();
    debug_log( "Security::denyIfRoleNot(), auth user's role: \"$currentRole\"", '', 3, 'security' );
    if ( ! in_array( $currentRole, $roles ) ) {
      http_response_code( 403 );
      debug_log( "***Access Denied!*** role: \"$currentRole\"", '', 1, 'security' );
      exit( $message ?: 'Access denied' );
    }
  }


  public function renderLogin( $feedback = null ) {
    echo '<form method="POST" onsubmit="document.body.className=\'busy\'">';
    if ( $feedback ) echo '<p id="generalFeedback" class="feedback">' . $feedback . '</p>';
    echo '<label for="username">Username:</label>';
    echo '<input type="text" id="username" name="username" placeholder="Username" required>';
    echo '<p id="usernameFeedback" class="feedback"></p>';
    echo '<label for="password">Password:</label>';
    echo '<input type="password" id="password" name="password" placeholder="Password" required>';
    echo '<p id="passwordFeedback" class="feedback"></p>';
    echo '<button type="submit"><span class="spinner"></span>Login</button>';
    echo '</form>' . PHP_EOL;
  }


  public function isLoggedOut() { return ! $this->loggedIn; }
  public function isLoggedIn() { return $this->loggedIn; }


  public function logout() {
    $this->user = null;
    $this->loggedIn = false;
    $this->destorySession();
  }


  public function login( $dbUser, $username, $password ) {
    if ( $dbUser and password_verify( $password, $dbUser->password ) )
    {
      $this->startUserSession( [
        'id' => $dbUser->id,
        'username' => $username,
        'role' => $dbUser->role ?? ''
      ] );

      $this->loggedIn = true;

      $token = [ 'u' => $this->user, 't' => time() ];
      $tokenJson = json_encode( $token );

      debug_log( $tokenJson, 'Security::login(), Token: ', 3, 'security' );

      $newEncryptedToken = $this->encrypt( $tokenJson );
      $this->setTokenCookie( $newEncryptedToken );
    }
    else
    {
      $this->logout();
      sleep( 3 ); // Slow down brute force attacks
    }

    return $this->user;
  }

}