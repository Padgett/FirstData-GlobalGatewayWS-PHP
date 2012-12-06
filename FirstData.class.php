<?php
/**
 * First Data Global Gateway Web Service API for PHP
 * Supports Credit Cards only
 *
 * @author Joshua Padgett
 * 2012-11-21
 * 
 */
class FirstData {
  private $postingURL;
	private $store;
	private $userId;
	private $pass;
	private $sslCert;
	private $sslKey;
	private $sslKeyPass;
  public $oid;
  private $config;
  private $totals;
  private $cardInfo;
  private $billingInfo;
  
  /* function: __construct
   * Constructs the object.
   * Required Params: postingURL, store, userID, pass, sslCert, sslKey, sslKeyPass
   * Optional Params: oid, config(array)
   */
  public function __construct($postingURL,$store,$userID,$pass,$sslCert,$sslKey,$sslKeyPass,$oid = '',$config = array()) {
    if (!$postingURL) {
      throw new Exception('POST URL Required.');
    } else {
      $this->postingURL = $postingURL;
    }
		if (!$store) {
      throw new Exception('Store Required.');
    } else {
      $this->store = $store;
    }
		if (!$userID || !$pass || !$sslCert || !$sslKey || !$sslKeyPass) {
      throw new Exception('User ID, Password, SSL Cert, SSL Key, and SSL Key Password Required.');
    } else {
			$this->userId = $userID;
			$this->pass = $pass;
			$this->sslCert = $sslCert;
			$this->sslKey = $sslKey;
			$this->sslKeyPass = $sslKeyPass;
		}
    
    if ($oid) $this->oid = $oid;
    
    $this->config = array(
        'txtntype'    => (empty($config['txtntype'])) ? 'sale' : $config['txtntype'],
        'timezone'    => (empty($config['timezone'])) ? date('T') : $config['timezone'],
        'mode'        => (empty($config['mode'])) ? 'payonly' : $config['mode'],
        'trxOrigin'   => (empty($config['trxOrigin'])) ? 'ECI' : $config['trxOrigin']
    );
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
      $this->setBillingInfo($billingInfo);
    }
  }
  
  /* function: setBillingInfo
   * 
   */
  public function setBillingInfo($billingInfo) {
    $this->billingInfo = array(
        'bcompany'  => $billingInfo['company'],
        'bname'     => $billingInfo['name'],
        'baddr1'    => $billingInfo['addr1'],
        'baddr2'    => $billingInfo['addr2'],
        'bcity'     => $billingInfo['city'],
        'bstate'    => $billingInfo['state'],
        'bstate2'   => $billingInfo['state2'],
        'bcountry'  => $billingInfo['country'],
        'bzip'      => $billingInfo['zip'],
        'phone'     => $billingInfo['phone'],
        'fax'       => $billingInfo['fax'],
        'email'     => $billingInfo['email']
    );
  }
  
  /* function: chargeIt
   * Charges the card
   */
  public function chargeIt() {    
		/*** Let's build our curl request ***/
		$soapBody = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">';
		$soapBody .= '<SOAP-ENV:Header /><SOAP-ENV:Body>';
		
		$soapBody .= '</SOAP-ENV:Body></SOAP-ENV:Envelope>';
		/*** ***/
		
		try {
			$response = $this->curlIt($soapBody);
			
			return $response;
		} catch (Exception $e) {
			die($e->getMessage());
		}
  }
  
	public function systemCheckAPI() {
		/*** Generate our Soap Request ***/
		$soapBody = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">';
		$soapBody .= '<SOAP-ENV:Header /><SOAP-ENV:Body>';
		$soapBody .= '
			<fdggwsapi:FDGGWSApiActionRequest xmlns:fdggwsapi=
			"http://secure.linkpt.net/fdggwsapi/schemas_us/fdggwsapi" xmlns:a1=
			"http://secure.linkpt.net/fdggwsapi/schemas_us/a1" xmlns:v1=
			"http://secure.linkpt.net/fdggwsapi/schemas_us/v1">
				<a1:Action>
					<a1:SystemCheck/>
				</a1:Action>
			</fdggwsapi:FDGGWSApiActionRequest>';
		$soapBody .= '</SOAP-ENV:Body></SOAP-ENV:Envelope>';
		/*** ***/
		
		try {
			$response = $this->curlIt($soapBody);
			if ($response->Success == 'true') {
				return true;
			} else {
				return false;
			}
		} catch (Exception $e) {
			die($e->getMessage());
		}
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
  public function addOptions($options) {
    foreach ($options as $key => $val) {
      $this->options[$key] = $val;
    }
  }
	
	/* Function: curlIt
	 * 
	 */
	private function curlIt($body) {
		$ch = curl_init($this->postingURL);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, 'WS'.$this->store.'._.1:'.$this->pass);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSLCERT, $this->sslCert);
		curl_setopt($ch, CURLOPT_SSLKEY, $this->sslKey);
		curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $this->sslKeyPass);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec($ch);
		
		if($response === false) {
			throw new Exception('Curl error: '.curl_error($ch));
		}
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		
		return $this->parseResponse($response);
	}
	
	/* Function: parseResponse
	 * 
	 */
	private function parseResponse($string) {
		// SimpleXML seems to have problems with the mixed namespaces, so take them out.
		$string = str_replace('fdggwsapi:', '', $string);
		$string = str_replace('SOAP-ENV:', '', $string);

		//Now strip down to just the Body contents.
		//$matches[0] includes Body tags, $matches[1] does not.
		preg_match('/<Body>(.*)<\/Body>/m', $string, $matches);
		//remove the xmlns reference.
		$string = preg_replace('/( xmlns:.*")/', '', $matches[1]);

		//Finally, generate our simplexml object.
		return simplexml_load_string($string);
	}
  
  /* function: createHash
   * Creates SHA2 hash for authentication to gateway
   */
  private function createHash($dateTime) {
    $str = $this->store.$dateTime.$this->totals['chargetotal'].$this->sharedKey;
    $hex_str = '';
    for ($i = 0; $i < strlen($str); $i++){ 
      $hex_str .= dechex(ord($str[$i]));
    }
    return hash('sha256', $hex_str);
  }
   
}

?>
