<?php namespace F1;

use DateTimeZone;
use DateTime as BaseDateTime;

/**
 * F1 DateTime Class - 26 Dec 2023
 * 
 * Replace all built-in PHP date and time functions with
 * this class to ensure consistent time handling and to 
 * mock the application time for testing purposes.
 * 
 * @author Neels Moller <xavier.tnc@gmail.com>
 * 
 * @version 1.4 - FT - 08 Jan 2025
 *   - Add SECONDS_IN_* constants
 *   - Add modifyDateTimeStr() method
 *   - Rename $timeString to $dateTimeStr
 *   - Add comments
 * 
 * @version 2.0 - INIT - 26 Jan 2025
 *   - Move from App\Services\AppDateTime to F1 namespace
 *   - $dateTimeStr defaults to 'now'
 *   - Remove isLeapYear()
 *   - Remove isWeekend()
 * 
 * @version 2.1 - FIX - 27 Jan 2025
 *   - Change $timezone parameter to accept string or DateTimeZone in __construct()
 * 
 * @version 3.0 - FT - 27 Feb 2025
 *   - Add year() method
 */

class DateTime extends BaseDateTime {

  private const SECONDS_IN_MINUTE = 60;
  private const SECONDS_IN_HOUR = 3600;       // 60 * 60
  private const SECONDS_IN_DAY = 86400;       // 24 * 3600
  private const SECONDS_IN_MONTH = 2592000;   // 30 * 86400
  private const SECONDS_IN_YEAR = 31536000;   // 365 * 86400


  /**
   * $dateTimeStr Examples:
   *  'now' - Current date and time
   *  '2023-12-26' - Date only
   *  '2023-12-26 12:34:56' - Date and time
   *  '+1 day' - Relative to the current system date
   *  'first day of next month' - Relative to the current system date
   *   null - Defaults to 'now'
   */ 
  public function __construct( ?string $dateTimeStr = null, $timezone = null ) {
    if ( is_string( $timezone ) ) $timezone = new DateTimeZone( $timezone );
    parent::__construct( $dateTimeStr?:'now', $timezone );
  }


  public function modifyDateTimeStr( string $dateTimeStr, int $years = 0, int $months = 0, int $days = 0, 
    int $hours = 0, int $minutes = 0, int $seconds = 0, string $format = '' ): string {
    $timestamp = $this->strtotime( $dateTimeStr );
    $offset = ( $years * self::SECONDS_IN_YEAR ) + 
              ( $months * self::SECONDS_IN_MONTH ) + 
              ( $days * self::SECONDS_IN_DAY ) + 
              ( $hours * self::SECONDS_IN_HOUR ) + 
              ( $minutes * self::SECONDS_IN_MINUTE ) +
              $seconds;
    if ( ! $format ) { $format = ( $hours + $minutes + $seconds ) > 0 ? 'Y-m-d H:i:s' : 'Y-m-d'; }
    return date( $format, $timestamp + $offset );
  }


  public function strtotime( string $dateTimeStr, ?int $baseTimestamp = null, ?string $timezoneString = null ): ?int {
    $baseTimestamp ??= $this->getTimestamp(); // Use null coalescing for brevity
    $timezone = $timezoneString ? new DateTimeZone( $timezoneString ) : $this->getTimezone();
    $baseTime = new DateTime( "@$baseTimestamp", $timezone );
    $baseTime->modify( $dateTimeStr );
    return $baseTime->getTimestamp();
  }


  public function date( string $format, ?int $timestamp = null ): string {
    $dateTime = $timestamp ? new DateTime( "@$timestamp", $this->getTimezone() ) : $this;
    return $dateTime->format( $format );
  }


  public function mock( string $dateTimeStr, ?string $timezoneString = null ): self {
    $this->modify( $dateTimeStr );
    if ( $timezoneString ) $this->setTimezone( new DateTimeZone( $timezoneString ) );
    return $this;
  }


  public function reset(): self {
    $this->modify( 'now' ); // Resets the current instance time to 'now'
    $this->setTimezone( new DateTimeZone( date_default_timezone_get() ) );
    return $this;
  }


  public function time(): int { return $this->getTimestamp(); }
  public function today( string $format = 'Y-m-d' ): string { return $this->format( $format ); }
  public function now( string $format = 'Y-m-d H:i:s' ): string { return $this->format( $format ); }
  public function year(): int { return (int) $this->format( 'Y' ); }

} // DateTime
