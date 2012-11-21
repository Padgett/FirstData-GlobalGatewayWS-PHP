<?php
/**
 * First Data Global Gateway Connect 2.0 API for PHP
 * Supports Credit Cards only
 *
 * @author Joshua Padgett
 * 2012-11-21
 * 
 */
class FirstData {
  private $sharedKey;
  private $store;
  private $config;
  private $totals;
  
  /* function: __construct
   * Required Params: sharedKey(string)
   * Optional Params: config(array)
   */
  public function __construct($sharedKey,$store,$config = array(),$options = array()) {
    if (!$sharedKey) {
      throw new Exception('Shared Key Required.');
    } else {
      $this->sharedKey = $sharedKey;
    }
    if (!$store) {
      throw new Exception('Store Required.');
    } else {
      $this->store = $store;
    }
    $this->config = array(
        'txtntype'    => $config['txtntype'] || 'sale',
        'timezone'    => $config['timezone'] || date('T'),
        'mode'        => $config['mode'] || 'payonly',
        'trxOrigin'   => $config['trxOrigin'] || 'ECI'
    );
  }
  
  public function __destruct() {
    $this->sharedKey = null;
    unset($this->sharedKey);
    $this->store = null;
    unset($this->store); 
    $this->config = null;
    unset($this->config);
  }
  
  public function setTotals($subtotal,$tax = 0,$shipping = 0) {
    $this->totals = array(
        'subtotal'    => number_format($subtotal, 2, '.', ''),
        'tax'         => number_format($tax, 2, '.', ''),
        'shipping'    => number_format($shipping, 2, '.', '')
    );
    $chargetotal = $this->totals['subtotal'] + $this->totals['tax'] + $this->totals['shipping'];
    $chargetotal = number_format($chargetotal, 2, '.', '');
    $this->totals['chargetotal'] = $chargetotal;
  }
  
  /* function: chargeIt
   * Input: paymentMethod (M, V, A, C, J, D)
   * M = Mastercard, V = Visa, A = Amex, C = Diners, J = JCB, D = Discover
   */
  public function chargeIt($paymentMethod) {
    $cardTypes = array('M','V','A','C','J','D');
    if (!in_array(strtoupper($paymentMethod), $cardTypes)) {
      throw new Exception('Card type invalid.');
    }
    $txndatetime = date('%Y:%m:%d-%H:%i:%s');
    $hash = createHash($txndatetime);
    
  }
  
  /* function: createHash
   * Creates SHA2 hash for authentication to gateway
   */
  private function createHash($dateTime) { 
    $str = $this->store.$dateTime().$this->totals['chargetotal'].$this->sharedKey;
    for ($i = 0; $i < strlen($str); $i++){ 
      $hex_str.=dechex(ord($str[$i]));
    }
    return hash('sha256', $hex_str);
  }
   
}

?>
