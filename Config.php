<?php

class Config {

  // DATABASE
  const DB_USER = 'db.user';
  const DB_PASS = 'db.pass';
  const DB_HOST = 'db.host';
  const DB_NAME = 'db.name';
  //
  // MAIL
  const MAIL_RECIPIENT = 'mail.address';
  const MANDRILL_API_KEY = 'mail.mandrill-key';
  //
  // EXPERT
  const BALANCE_FACTOR = 'expert.balance-factor';
  const DEFAULT_BALANCE_FACTOR = 1.05;
  //
  const REQUIRED_OPPORTUNITIES = 'expert.required-opportunities';
  const DEFAULT_REQUIRED_OPPORTUNITIES = 3;
  //
  const MAX_TX_FEE_ALLOWED = 'expert.max-tx-fee-allowed';
  const DEFAULT_MAX_TX_FEE_ALLOWED = 0.00001;
  //
  const RATE_EMA_PERIOD = 'expert.rate-ema-period';
  const DEFAULT_RATE_EMA_PERIOD = 12;
  //
  const OPPORTUNITY_COUNT_AGE = 'expert.opportunity-count-age';
  const DEFAULT_OPPORTUNITY_COUNT_AGE = 120;
  //
  const OPPORTUNITY_SAVE_INTERVAL = 'expert.opportunity-save-interval';
  const DEFAULT_OPPORTUNITY_SAVE_INTERVAL = 60;
  //
  const MAX_LOG_AGE = 'expert.max-log-age';
  const DEFAULT_MAX_LOG_AGE = 300;
  //
  const QUERY_DELAY = 'expert.query-delay';
  const DEFAULT_QUERY_DELAY = 10;
  //
  const ORDER_CHECK_DELAY = 'expert.order-check-delay';
  const DEFAULT_ORDER_CHECK_DELAY = 60;
  //
  const MAX_PAIRS_PER_RUN = 'expert.max-pairs-per-run';
  const DEFAULT_MAX_PAIRS_PER_RUN = 10;
  //
  const SUSPICIOUS_PRICE_DIFFERENCE = 'expert.suspicious-price-difference';
  const DEFAULT_SUSPICIOUS_PRICE_DIFFERENCE = 33;
  //
  const INTERVAL_MANAGEMENT = 'expert.interval-management';
  const DEFAULT_INTERVAL_MANAGEMENT = 1;
  //
  const INTERVAL_TAKE_PROFIT = 'expert.interval-take-profit';
  const DEFAULT_INTERVAL_TAKE_PROFIT = 6;
  //
  const INTERVAL_STUCK_DETECTION = 'expert.interval-stuck-detection';
  const DEFAULT_INTERVAL_STUCK_DETECTION = 12;
  //
  const INTERVAL_UNUSED_COIN_DETECTION = 'expert.interval-unused-coin-detection';
  const DEFAULT_INTERVAL_UNUSED_COIN_DETECTION = 24;
  //
  const INTERVAL_DB_CLEANUP = 'expert.interval-db-cleanup';
  const DEFAULT_INTERVAL_DB_CLEANUP = 2;
  //
  //
  // GENERAL
  const CANCEL_STRAY_ORDERS = 'general.cancel-stray-orders';
  const DEFAULT_CANCEL_STRAY_ORDERS = true;
  //
  const MIN_BTC_XFER = 'general.min-btc-xfer';
  const DEFAULT_MIN_BTC_XFER = 0.02;
  //
  // TRADE MODULE
  const MAX_TRADE_SIZE = 'trade.max-trade-size';
  const DEFAULT_MAX_TRADE_SIZE = 0.01;
  //
  const MIN_PROFIT = 'trade.min-profit';
  const DEFAULT_MIN_PROFIT = 0.00000050;
  //
  const BUY_RATE_FACTOR = 'trade.buy-factor';
  const DEFAULT_BUY_RATE_FACTOR = 1.1;
  //
  const SELL_RATE_FACTOR = 'trade.sell-factor';
  const DEFAULT_SELL_RATE_FACTOR = 0.9;
  //
  // TAKEPROFIT MODULE
  const TAKE_PROFIT_ADDRESS = 'takeprofit.profit-address';
  const DEFAULT_TAKE_PROFIT_ADDRESS = null;
  //
  const TAKE_PROFIT_AMOUNT = 'takeprofit.profit-limit';
  const DEFAULT_TAKE_PROFIT_AMOUNT = null;
  //
  // AUTOBUY
  const MAX_BUY = 'autobuy.max-buy';
  const DEFAULT_MAX_BUY = 0.005;
  //
  // MODULES
  const MODULE_TRADE = 'modules.trade';
  const DEFAULT_MODULE_TRADE = true;
  //
  const MODULE_LIQUIDATE = 'modules.coin-liquidation';
  const DEFAULT_MODULE_LIQUIDATE = false;
  //
  const MODULE_AUTOBUY = 'modules.coin-autobuy';
  const DEFAULT_MODULE_AUTOBUY = false;
  //
  const MODULE_TAKE_PROFIT = 'modules.take-profit';
  const DEFAULT_MODULE_TAKE_PROFIT = false;
  //
  const MODULE_STUCK_DETECTION = 'modules.stuck-detection';
  const DEFAULT_MODULE_STUCK_DETECTION = true;
  //
  const MODULE_UNUSED_COINS_DETECTION = 'modules.unused-coins-detection';
  const DEFAULT_MODULE_UNUSED_COINS_DETECTION = true;

  //
  private static $config = [ ];
  //
  private static $defaultNotifications = [ ];
  //
  private static $blockList = [ ];
  //
  private static $tradedCoins = [ ];

  private function __construct() {

  }

  public static function isCurrency( $coin ) {

    return $coin == 'BTC';

  }

  public static function isBlocked( $coin ) {

    $blockedCoins = self::get( "general.blockedCoins" );
    if ( !is_null( $blockedCoins ) ) {
      $coins = explode( ',', $blockedCoins );
      foreach ( $coins as $block ) {
        if ( trim( $block ) == $coin ) {
          logg( "Skipping $coin (Blocked from config)" );
          return true;
        }
      }
    }

    if ( key_exists( $coin, self::$blockList ) && (self::$blockList[ $coin ] == 0 || self::$blockList[ $coin ] < time()) ) {
      logg( "Skipping $coin (Blocked during runtime)" );
      return true;
    }
    return false;

  }

  public static function isTraded( $coin ) {

    return key_exists( $coin, self::$tradedCoins );

  }

  public static function blockCoin( $coin, $expiration = 0 ) {

    self::$blockList[ $coin ] = time() + $expiration;

  }

  public static function setTradedCoins( $tradedCoins ) {

    self::$tradedCoins = $tradedCoins;

  }

  public static function exists( $key ) {

    $value = self::get( $key, null );
    return !is_null( $value ) && strlen( $value ) > 0;

  }

  public static function get( $key, $default = null ) {

    $config = self::$config;

    $value = $config;
    $keys = explode( '.', $key );
    foreach ( $keys as $k ) {
      if ( !key_exists( $k, $value ) ) {
        if ( !in_array( $key, self::$defaultNotifications ) ) {
          //logg( "INFO: Using default value for $key: \"" . (is_null( $default ) ? 'null' : $default) . "\"" );
          self::$defaultNotifications[] = $key;
        }
        return $default;
      }
      $value = $value[ $k ];
    }
    return $value;

  }

  public static function refresh() {

    $config = parse_ini_file( "config.ini", true );
    if ( !$config ) {
      throw new Exception( "Configuration not found or invalid!" );
    }
    self::$config = $config;

  }

}
