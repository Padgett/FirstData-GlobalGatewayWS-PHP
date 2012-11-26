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
  public $oid;
  private $sharedKey;
  private $store;
  private $postingURL;
  private $config;
  private $options;
  private $totals;
  private $cardInfo;
  private $billingInfo;
  
  /* function: __construct
   * Constructs the object.
   * Options array will be appended to the charge request. Add your custom fields here.
   * Required Params: sharedKey, store, oid(public - can be set later)
   * Optional Params: config(array), options(array)
   */
  public function __construct($sharedKey,$store,$postingURL,$oid = '',$config = array(),$options = array()) {
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
    if (!$postingURL) {
      throw new Exception('POST URL Required.');
    } else {
      $this->postingURL = $postingURL;
    }
    
    if ($oid) $this->oid = $oid;
    
    $this->config = array(
        'txtntype'    => $config['txtntype'] || 'sale',
        'timezone'    => $config['timezone'] || date('T'),
        'mode'        => $config['mode'] || 'payonly',
        'trxOrigin'   => $config['trxOrigin'] || 'ECI'
    );
    
    foreach ($options as $key => $val) {
      $this->options[$key] = $val;
    }
  }
  
  public function __destruct() {
    $this->sharedKey = null;
    unset($this->sharedKey);
    $this->store = null;
    unset($this->store);
    $this->cardinfo = null;
    unset($this->cardInfo);
  }
  
  /* function: setTotals
   * 
   */
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
  
  /* function: setCardInfo
   * Input: cardType(M, V, A, C, J, D), cardNum, expMonth, expYear, cvv, billingInfo(array)
   * M = Mastercard, V = Visa, A = Amex, C = Diners, J = JCB, D = Discover
   */
  public function setCardInfo($cardType,$cardNum,$expMonth,$expYear,$cvv,$billingInfo = array()) {
    $cardTypes = array('M','V','A','C','J','D');
    if (!$cardType || !$cardNum || !$expMonth || !$expYear || !$cvv) {
      throw new Exception('Complete card info required.');
    }
    if (!in_array(strtoupper($cardType), $cardTypes)) {
      throw new Exception('Card type invalid.');
    }
    
    $this->cardInfo = array(
        'cardnumber'  => $cardNum,
        'expmonth'    => $expMonth,
        'expyear'     => $expYear,
        'cvm'         => $cvv
    );
    
    //Setup the billing info if it's been passed in.
    if ($billingInfo) {
      //TODO: This.
    }
  }
  
  /* function: setBillingInfo
   * 
   */
  public function setBillingInfo($info) {
    //TODO: This.
  }
  
  /* function: chargeIt
   * Charges the card
   */
  public function chargeIt() {
    if (empty($this->oid)) {
      throw new Exception('Order ID (oid) is not set.');
    }
    
    $txndatetime = date('%Y:%m:%d-%H:%i:%s');
    $hash = createHash($txndatetime);
    
    /*** Send to First Data ***/
    
    /*** Handle Response ***/
    
    //Let's wipe the card info from memory, just to be safe.
    $this->cardInfo = null;
    unset($this->cardInfo);
  }
  
  /* function: setOptions
   * Replaces or clears existing options
   */
  public function setOptions($options = array()) {
    $this->options = array();
    foreach ($options as $key => $val) {
      $this->options[$key] = $val;
    }
  }
  
  /* function: addOptions
   * Appends to or updates existing options
   */
  public function addOptions($options = array()) {
    foreach ($options as $key => $val) {
      $this->options[$key] = $val;
    }
  }
  
  /* function: createHash
   * Creates SHA2 hash for authentication to gateway
   */
  private function createHash($dateTime) {
    $str = $this->store.$dateTime.$this->totals['chargetotal'].$this->sharedKey;
    for ($i = 0; $i < strlen($str); $i++){ 
      $hex_str.=dechex(ord($str[$i]));
    }
    return hash('sha256', $hex_str);
  }
   
}

?>
