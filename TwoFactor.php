<?php namespace F1;


use Exception;


/**
 * F1 - TwoFactor Class - 19 Jan 2025
 *
 * @author  C. Moller <xavier.tnc@gmail.com>
 * 
 * @version 1.0 - INIT - 19 Jan 2025
 *   - Extract common 2FA code into this abstract lib class.
 * 
 * @version 1.1 - DEV - 26 Jan 2025
 *   - Rename generateTimeBasedOTPLink() to generateTimeBasedOTPUri()
 * 
 * @version 2.0 - DEV - 19 Feb 2025
 *   - Improve base32_decode(), base32_encode() and generateTimeBasedOTPUri() 
 *     methods to work with more TOPT apps, including "Twillo Authy"
 * 
 * PS: This class still needs a lot of work. NM 20 Jan 25 
 *
 */

abstract class TwoFactor {


  abstract public function check( $user );


  abstract public function sendOTPEmail( $user, $otpTokenLifetime, $otp = '' );


  public function base32_decode( $data ) 
  {
    $base32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $data = strtoupper( str_replace( '=', '', $data ) ); // Remove padding and normalize case
    $binary = '';

    for ( $i = 0; $i < strlen( $data ); $i++ ) {
      $charIndex = strpos( $base32, $data[$i] );
      if ( $charIndex === false ) throw new Exception( 'Invalid Base32 character found' );
      $binary .= str_pad( decbin( $charIndex ), 5, '0', STR_PAD_LEFT );
    }

    $result = '';
    for ( $i = 0; $i < strlen( $binary ); $i += 8 ) {
      $byte = substr( $binary, $i, 8 );
      if ( strlen( $byte ) == 8 ) {
        $result .= chr( bindec( $byte ) );
      }
    }

    return $result;
  }


  public function base32_encode( $data ) 
  {
    $base32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';

    for ( $i = 0; $i < strlen( $data ); $i++ ) {
      $binary .= str_pad( decbin( ord( $data[$i] ) ), 8, '0', STR_PAD_LEFT );
    }

    $result = '';
    for ( $i = 0; $i < strlen( $binary ); $i += 5 ) {
      $chunk = substr( $binary, $i, 5 );
      $result .= $base32[bindec( str_pad( $chunk, 5, '0', STR_PAD_RIGHT ) )];
    }

    return rtrim( $result, '=' ); // Remove unnecessary padding
  }


  // OTP: One Time Pin Number
  public function generateSimpleOTP( $length = 6 ) {
    $min = pow( 10, $length - 1 );   // e.g. for 6 digits, 100000
    $max = pow( 10, $length ) - 1;   // e.g. for 6 digits, 999999
    return random_int( $min, $max );
  }


  public function generateTimeBasedOTPSecret( $length = 16 ) {
    return $this->base32_encode( random_bytes( $length ) );
  }


  public function generateTimeBasedOTP( $secret ) {
    $time = floor( time() / 30 );
    $key = $this->base32_decode( $secret );
    $msg = pack( 'N*', 0 ) . pack( 'N*', $time );
    $hash = hash_hmac( 'sha1', $msg, $key, true );
    $offset = ord( substr( $hash, -1 ) ) & 0x0F;
    $code = ( ord( substr( $hash, $offset, 1 ) ) & 0x7F ) << 24 |
            ( ord( substr( $hash, $offset + 1, 1 ) ) & 0xFF ) << 16 |
            ( ord( substr( $hash, $offset + 2, 1 ) ) & 0xFF ) << 8 |
            ( ord( substr( $hash, $offset + 3, 1 ) ) & 0xFF );
    return str_pad( $code % 1000000, 6, '0', STR_PAD_LEFT );
  }


  public function generateTimeBasedOTPUri( $secret, $issuer, $accountName ) {
    return sprintf( 'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
      urlencode( $issuer ), urlencode( $accountName ), urlencode( $secret ), urlencode( $issuer ) );
  }


  public function validateTimeBasedOTP( $userInput, $userSecret ) {
    $otp = $this->generateTimeBasedOTP( $userSecret );
    return $userInput === $otp;
  }

} // TwoFactor