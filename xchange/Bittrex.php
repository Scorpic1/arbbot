<?php

require_once __DIR__ . '/../Config.php';

class Bittrex extends Exchange {

  const ID = 3;
  //
  const PUBLIC_URL = 'https://bittrex.com/api/v1.1/public/';

  function __construct() {
    parent::__construct( Config::get( "bittrex.key" ), Config::get( "bittrex.secret" ) );

  }

  public function addFeeToPrice( $price ) {
    return $price * 1.0025;

  }

  public function deductFeeFromAmountBuy( $amount ) {

    return parent::deductFeeFromAmountBuy( $amount );

  }

  public function deductFeeFromAmountSell( $amount ) {
    return $amount * 0.9975;

  }

  public function getTickers( $currency ) {

    $ticker = [ ];

    $markets = $this->queryMarketSummary();

    foreach ( $markets as $market ) {

      $split = explode( "-", $market[ 'MarketName' ] );
      if ( $split[ 0 ] != $currency ) {
        continue;
      }

      $ticker[ $split[ 1 ] ] = $market[ 'Last' ];
    }

    return $ticker;

  }

  public function withdraw( $coin, $amount, $address ) {

    try {
      $this->queryWithdraw( $coin, $amount, $address );
      return true;
    }
    catch ( Exception $ex ) {
      echo( $this->prefix() . "Withdrawal error: " . $ex->getMessage() );
      return false;
    }

  }

  public function getDepositAddress( $coin ) {

    return $this->queryDepositAddress( $coin );

  }

  public function buy( $tradeable, $currency, $rate, $amount ) {

    return $this->queryOrder( $tradeable, $currency, 'buy', $rate, $amount );

  }

  public function sell( $tradeable, $currency, $rate, $amount ) {

    return $this->queryOrder( $tradeable, $currency, 'sell', $rate, $amount );

  }

  public function getFilledOrderPrice( $type, $tradeable, $currency, $id ) {
    $market = $currency . '-' . $tradeable;
    $result = $this->queryAPI( 'account/getorderhistory', [ 'market' => $market ] );
    $id = trim( $id, '{}' );

    foreach ($result as $order) {
      if ($order[ 'OrderUuid' ] == $id) {
        if ($order[ 'QuantityRemaining' ] != 0) {
          logg( $this->prefix() . "Order " . $id . " assumed to be filled but " . $order[ 'QuantityRemaining' ] . " still remaining" );
        }
        return $order[ 'Price' ];
      }
    }
    return null;
  }

  protected function fetchOrderbook( $tradeable, $currency ) {

    $orderbook = $this->queryOrderbook( $tradeable, $currency );
    if ( count( $orderbook ) == 0 ) {
      return null;
    }

    $ask = $orderbook[ 'sell' ];
    if ( count( $ask ) == 0 ) {
      return null;
    }

    $bestAsk = $ask[ 0 ];

    $bid = $orderbook[ 'buy' ];
    if ( count( $bid ) == 0 ) {
      return null;
    }

    $bestBid = $bid[ 0 ];


    return new Orderbook( //
            $this, $tradeable, //
            $currency, //
            new OrderbookEntry( $bestAsk[ 'Quantity' ], $bestAsk[ 'Rate' ] ), //
            new OrderbookEntry( $bestBid[ 'Quantity' ], $bestBid[ 'Rate' ] ) //
    );

  }

  public function cancelOrder( $orderID ) {

    logg( $this->prefix() . "Cancelling order $orderID" );
    try {
      $this->queryCancelOrder( $orderID );
      return true;
    }
    catch ( Exception $ex ) {
      if ( strpos( $ex->getMessage(), 'ORDER_NOT_OPEN' ) !== false ) {
        return false;
      }
      throw $ex;
    }

  }

  public function cancelAllOrders() {

    $orders = $this->queryOpenOrders();
    foreach ( $orders as $order ) {
      $uuid = $order[ 'OrderUuid' ];
      $this->cancelOrder( $uuid );
    }

  }

  public function refreshExchangeData() {

    $pairs = [ ];
    $markets = $this->queryMarkets();
    $currencies = $this->queryCurrencies();

    // This is a list of tradeables that have a market. Used to filter the
    // tx-fee list, which is later used to seed the wallets
    $tradeables = [ ];
    foreach ( $markets as $market ) {

      $tradeable = $market[ 'MarketCurrency' ];
      $currency = $market[ 'BaseCurrency' ];

      if ( !Config::isCurrency( $currency ) ) {
        continue;
      }

      if ( !$market[ 'IsActive' ] ) {
        continue;
      }

      $tradeables[] = $tradeable;
      $pairs[] = $tradeable . '_' . $currency;
    }

    $names = [ ];
    $txFees = [ ];

    foreach ( $currencies as $data ) {

      $coin = strtoupper( $data[ 'Currency' ] );
      $type = strtoupper( $data[ 'CoinType' ] );

      if ( $type != 'BITCOIN' && $type != 'BITCOIN_PERCENTAGE_FEE' ) {
        //logg( $this->prefix() . "Autoblocking coin $coin as it has wrong type: $type" );
        //Config::blockCoin( $coin );
        continue;
      }

      if ( array_search( $coin, $tradeables ) !== false ) {
        $names[ $coin ] = strtoupper( $data[ 'CurrencyLong' ] );
        $txFees[ $coin ] = $data[ 'TxFee' ] + ($type == 'BITCOIN_PERCENTAGE_FEE' ? '%' : '');
      }
    }

    $this->pairs = $pairs;
    $this->names = $names;
    $this->transferFees = $txFees;

  }

  public function detectStuckTransfers() {

    // TODO: Detect stuck transfers!

  }

  public function dumpWallets() {

    logg( $this->prefix() . print_r( $this->queryBalances(), true ) );

  }

  public function refreshWallets() {

    $wallets = [ ];

    // Create artifical wallet balances for all traded coins:
    foreach ( array_keys( $this->transferFees ) as $coin ) {
      $wallets[ $coin ] = 0;
    }

    $balances = $this->queryBalances();
    foreach ( $balances as $balance ) {
      $wallets[ strtoupper( $balance[ 'Currency' ] ) ] = $balance[ 'Available' ];
    }

    $this->wallets = $wallets;

  }

  public function testAccess() {

    $this->queryBalances();

  }

  public function getSmallestOrderSize() {

    return '0.00050000';

  }

  public function getID() {

    return Bittrex::ID;

  }

  public function getName() {

    return "BITTREX";

  }

  // Internal functions for querying the exchange

  private function queryDepositAddress( $coin ) {

    $error = null;
    for ( $i = 0; $i < 3; $i++ ) {
      try {
        //
        $data = $this->queryAPI( 'account/getdepositaddress', ['currency' => $coin ] );
        return $data[ 'Address' ];
        //
      }
      catch ( Exception $ex ) {
        // Ignore error messages here...
        // (API call fails if address is still generating...)
        $error = $ex;
        sleep( 30 );
      }
    }
    throw $error;

  }

  private function queryOrderbook( $tradeable, $currency ) {
    return $this->xtractResponse( $this->queryPublicJSON( Bittrex::PUBLIC_URL . 'getorderbook?depth=1&type=both&market=' . $currency . '-' . $tradeable ) );

  }

  private function queryMarkets() {
    return $this->xtractResponse( $this->queryPublicJSON( Bittrex::PUBLIC_URL . 'getmarkets' ) );

  }

  private function queryCurrencies() {
    return $this->xtractResponse( $this->queryPublicJSON( Bittrex::PUBLIC_URL . 'getcurrencies' ) );

  }

  private function queryMarketSummary() {
    return $this->xtractResponse( $this->queryPublicJSON( Bittrex::PUBLIC_URL . 'getmarketsummaries' ) );

  }

  private function queryCancelOrder( $uuid ) {
    return $this->queryAPI( 'market/cancel', //
                    [
                'uuid' => $uuid
                    ]
    );

  }

  private function queryOpenOrders() {
    return $this->queryAPI( 'market/getopenorders' );

  }

  private function queryOrder( $tradeable, $currency, $orderType, $rate, $amount ) {

    $result = $this->queryAPI( 'market/' . strtolower( $orderType ) . 'limit', //
            [
        'market' => $currency . '-' . $tradeable,
        'quantity' => formatBTC( $amount ),
        'rate' => formatBTC( $rate )
            ]
    );

    return $result[ 'uuid' ];

  }

  private function queryBalances() {
    return $this->queryAPI( 'account/getbalances' );

  }

  private function queryWithdraw( $coin, $amount, $address ) {
    return $this->queryAPI( 'account/withdraw', //
                    [
                'currency' => $coin,
                'quantity' => formatBTC( $amount ),
                'address' => $address
                    ]
    );

  }

  private function xtractResponse( $response ) {

    $data = json_decode( $response, true );

    if ( !$data ) {
      throw new Exception( "Invalid data received: (" . $response . ")" );
    }

    if ( !key_exists( 'result', $data ) || !key_exists( 'success', $data ) || $data[ 'success' ] != 1 ) {

      if ( key_exists( 'message', $data ) ) {
        throw new Exception( "API error response: " . $data[ 'message' ] );
      }

      throw new Exception( "API error: " . print_r( $data, true ) );
    }

    return $data[ 'result' ];

  }

  private function queryAPI( $method, $req = [ ] ) {

    $key = $this->apiKey;
    $secret = $this->apiSecret;
    $nonce = $this->nonce();

    $req[ 'apikey' ] = $key;
    $req[ 'nonce' ] = $nonce;

    $uri = 'https://bittrex.com/api/v1.1/' . $method . '?' . http_build_query( $req );
    $sign = hash_hmac( 'sha512', $uri, $secret );

    static $ch = null;
    if ( is_null( $ch ) ) {
      $ch = curl_init();
      curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
      curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
      curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; Cryptsy API PHP client; ' . php_uname( 's' ) . '; PHP/' . phpversion() . ')' );
    }
    curl_setopt( $ch, CURLOPT_URL, $uri );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, ["apisign: $sign" ] );
    curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 10 );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 180 );

    $error = null;
    for ( $i = 0; $i < 5; $i++ ) {
      try {
        $data = curl_exec( $ch );
        //
      }
      catch ( Exception $ex ) {
        $error = $ex->getMessage();
        logg( $this->prefix() . $error );
        sleep( 1 );
        continue;
      }

      if ( $data === false ) {
        $error = $this->prefix() . "Could not get reply: " . curl_error( $ch );
        logg( $error );
        continue;
      }

      return $this->xtractResponse( $data );
    }
    throw new Exception( $error );

  }

}
