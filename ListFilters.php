<?php namespace F1;

/**
 * ListFilters.php
 *
 * F1 List Filters — advanced DataTable af → SQL — 25 Jul 2026
 *
 * Purpose: Server-side apply for DataTable advancedFilters. Whitelist + aliasMap only;
 * unknown field/op ignored. Pair with JS DataTable advancedFilters wire format.
 *
 * @package F1
 * @author Senpai
 *
 * Last 3 version commits:
 * @version 1.0 - INIT - 25 Jul 2026 - Moved from MyHub AppListFilters (lib-level SQL apply)
 */


class ListFilters
{

  /** Allowed ops per column filter type. */
  public static function opsForType( $type )
  {
    static $map = [
      'number' => [ 'EQ', 'NE', 'GT', 'GE', 'LT', 'LE', 'BETWEEN', 'IN', 'NOT_IN', 'EMPTY', 'NOT_EMPTY' ],
      'date' => [ 'EQ', 'NE', 'GT', 'GE', 'LT', 'LE', 'BETWEEN', 'EMPTY', 'NOT_EMPTY' ],
      'enum' => [ 'IN', 'NOT_IN', 'EQ', 'NE', 'EMPTY', 'NOT_EMPTY' ],
      'text' => [ 'CONTAINS', 'STARTS', 'EQ', 'NE', 'IN', 'NOT_IN', 'EMPTY', 'NOT_EMPTY' ],
      'boolean' => [ 'EQ', 'NE', 'EMPTY', 'NOT_EMPTY' ],
    ];
    return $map[ $type ] ?? [];
  } // opsForType


  /**
   * Apply advanced list filters → [ $andParts, $params ].
   * $aliasMap values must be trusted SQL idents / expressions.
   */
  public static function apply( array $af, array $whitelist, array $aliasMap = [] )
  {
    $parts = [];
    $params = [];

    foreach ( $whitelist as $field => $type ) {
      if ( ! isset( $af[ $field ] ) || ! is_array( $af[ $field ] ) ) continue;

      $spec = $af[ $field ];
      $op = strtoupper( trim( (string) ( $spec['op'] ?? '' ) ) );
      if ( $op === '' ) continue;
      if ( ! in_array( $op, self::opsForType( $type ), true ) ) continue;

      if ( isset( $aliasMap[ $field ] ) ) {
        $col = $aliasMap[ $field ];
      } else {
        $col = $field;
        if ( ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $col ) ) continue;
      }

      $clause = self::clause( $col, $type, $op, $spec, $params );
      if ( $clause !== null ) $parts[] = $clause;
    }

    return [ $parts, $params ];
  } // apply


  public static function parseSet( $raw )
  {
    if ( is_array( $raw ) ) $chunks = $raw;
    else $chunks = preg_split( '/[\n,]+/', (string) $raw ) ?: [];
    $out = [];
    foreach ( $chunks as $c ) {
      $c = trim( (string) $c );
      if ( $c !== '' ) $out[] = $c;
    }
    return $out;
  } // parseSet


  public static function scalar( $type, $v )
  {
    if ( $v === null || $v === '' ) return null;
    if ( $type === 'number' ) return is_numeric( $v ) ? $v : null;
    if ( $type === 'date' ) {
      $s = (string) $v;
      return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ? $s : null;
    }
    if ( $type === 'boolean' ) {
      if ( is_bool( $v ) ) return $v ? '1' : '0';
      $s = strtolower( trim( (string) $v ) );
      if ( in_array( $s, [ '1', 'true', 'yes', 'y' ], true ) ) return '1';
      if ( in_array( $s, [ '0', 'false', 'no', 'n' ], true ) ) return '0';
      return null;
    }
    return (string) $v;
  } // scalar


  public static function clause( $col, $type, $op, array $spec, array &$params )
  {
    if ( $op === 'EMPTY' ) {
      return $type === 'number' || $type === 'date' || $type === 'boolean'
        ? "$col IS NULL"
        : "( $col IS NULL OR $col = '' )";
    }
    if ( $op === 'NOT_EMPTY' ) {
      return $type === 'number' || $type === 'date' || $type === 'boolean'
        ? "$col IS NOT NULL"
        : "( $col IS NOT NULL AND $col <> '' )";
    }

    if ( $type === 'boolean' ) {
      $v = self::scalar( 'boolean', $spec['v'] ?? null );
      if ( $v === null ) return null;
      if ( $op === 'EQ' ) { $params[] = $v; return "$col = ?"; }
      if ( $op === 'NE' ) { $params[] = $v; return "$col <> ?"; }
      return null;
    }

    if ( $op === 'IN' || $op === 'NOT_IN' ) {
      $set = self::parseSet( $spec['set'] ?? ( $spec['v'] ?? '' ) );
      if ( ! $set ) return null;
      if ( $type === 'number' ) {
        $set = array_values( array_filter( $set, 'is_numeric' ) );
        if ( ! $set ) return null;
      }
      if ( $type === 'date' ) {
        $set = array_values( array_filter( $set, function( $s ) {
          return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s );
        } ) );
        if ( ! $set ) return null;
      }
      $place = implode( ',', array_fill( 0, count( $set ), '?' ) );
      $params = array_merge( $params, $set );
      return ( $op === 'IN' ? "$col IN ($place)" : "$col NOT IN ($place)" );
    }

    if ( $op === 'BETWEEN' ) {
      $a = self::scalar( $type, $spec['v'] ?? null );
      $b = self::scalar( $type, $spec['v2'] ?? null );
      if ( $a === null || $b === null ) return null;
      $params[] = $a;
      $params[] = $b;
      return "$col BETWEEN ? AND ?";
    }

    $v = self::scalar( $type, $spec['v'] ?? null );
    if ( $v === null ) return null;

    if ( $op === 'CONTAINS' ) {
      $params[] = '%' . $v . '%';
      return "$col LIKE ?";
    }
    if ( $op === 'STARTS' ) {
      $params[] = $v . '%';
      return "$col LIKE ?";
    }

    static $cmp = [ 'EQ' => '=', 'NE' => '<>', 'GT' => '>', 'GE' => '>=', 'LT' => '<', 'LE' => '<=' ];
    if ( ! isset( $cmp[ $op ] ) ) return null;
    $params[] = $v;
    return "$col {$cmp[ $op ]} ?";
  } // clause


} // ListFilters
