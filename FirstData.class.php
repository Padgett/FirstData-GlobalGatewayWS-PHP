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
	private $shippingInfo;
  
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
    if (!$cardType || !$cardNum || !$expMonth || !$expYear || !$cvv) {
      throw new Exception('Complete card info required.');
    }
		
		$cardTypes = array('M','V','A','C','J','D');
    if (!in_array(strtoupper($cardType), $cardTypes)) {
      throw new Exception('Card type invalid.');
    }
		
		$cardNum = preg_replace('/[^0-9]/s', '', $cardNum);
		if (strlen($cardNum) < 15 || strlen($cardNum) > 16) {
			throw new Exception('Card number invalid. Wrong Length.');
		} else {
			if (!$this->luhn_check()) {
				throw new Exception('Card number invalid.');
			}
		}
		
		$expMonth = (int)$expMonth;
		$expYear = (strlen($expYear) > 2) ? (int)substr($expYear, -2) : (int)$expYear;
		if ($expMonth == 0 || $expYear == 0) {
			throw new Exception('Invalid values for Expiration.');
		} else {
			$expMonth = str_pad($expMonth, 2, "0", STR_PAD_LEFT);
			$expYear = str_pad($expYear, 2, "0", STR_PAD_LEFT);
		}
    
    $this->cardInfo = array(
        'cardnumber'  => $cardNum,
        'expmonth'    => $expMonth,
        'expyear'     => $expYear,
        'cvm'         => (int)$cvv
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
        'company'  => $billingInfo['company'],
        'name'     => $billingInfo['name'],
        'addr1'    => $billingInfo['addr1'],
        'addr2'    => $billingInfo['addr2'],
        'city'     => $billingInfo['city'],
        'state'    => $billingInfo['state'],
        'country'  => $billingInfo['country'],
        'zip'      => $billingInfo['zip'],
        'phone'     => $billingInfo['phone'],
        'fax'       => $billingInfo['fax']
    );
  }
	
	/* function: setShippingInfo
   * 
   */
  public function setShippingInfo($shippingInfo) {
    $this->shippingInfo = array(
        'name'     => $shippingInfo['name'],
        'addr1'    => $shippingInfo['addr1'],
        'addr2'    => $shippingInfo['addr2'],
        'city'     => $shippingInfo['city'],
        'state'    => $shippingInfo['state'],
        'country'  => $shippingInfo['country'],
        'zip'      => $shippingInfo['zip'],
    );
  }
  
  /* function: chargeIt
   * Charges the card
   */
  public function chargeIt() {
		/*** Let's build our curl request ***/
		$soapBody = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">';
		$soapBody .= '<SOAP-ENV:Header /><SOAP-ENV:Body>';
		$soapBody .= '
			<fdggwsapi:FDGGWSApiOrderRequest xmlns:v1="http://secure.linkpt.net/fdggwsapi/schemas_us/v1" xmlns:fdggwsapi="http://secure.linkpt.net/fdggwsapi/schemas_us/fdggwsapi">
				<v1:Transaction>
					<v1:CreditCardTxType>
						<v1:Type>sale</v1:Type>
					</v1:CreditCardTxType>
					<v1:CreditCardData>
						<v1:CardNumber>'.$this->cardInfo['cardnumber'].'</v1:CardNumber>
						<v1:ExpMonth>'.$this->cardInfo['expmonth'].'</v1:ExpMonth>
						<v1:ExpYear>'.$this->cardInfo['expyear'].'</v1:ExpYear>
						<v1:CardCodeValue>'.$this->cardInfo['cvm'].'</v1:CardCodeValue>
					</v1:CreditCardData>
					<v1:Payment>
						<v1:ChargeTotal>'.$this->totals['chargetotal'].'</v1:ChargeTotal>
						<v1:SubTotal>'.$this->totals['subtotal'].'</v1:SubTotal>
						<v1:VATTax>'.$this->totals['tax'].'</v1:VATTax>
						<v1:Shipping>'.$this->totals['shipping'].'</v1:Shipping>
					</v1:Payment>
					<v1:TransactionDetails>
						<v1:OrderId>'.$this->oid.'</v1:OrderId>
						<v1:TransactionOrigin>ECI</v1:TransactionOrigin>
					</v1:TransactionDetails>
					<v1:Billing>
						<v1:Name>'.$this->billingInfo['name'].'</v1:Name>
						<v1:Company>'.$this->billingInfo['company'].'</v1:Company>
						<v1:Address1>'.$this->billingInfo['addr1'].'</v1:Address1>
						<v1:Address2>'.$this->billingInfo['addr2'].'</v1:Address2>
						<v1:City>'.$this->billingInfo['city'].'</v1:City>
						<v1:State>'.$this->billingInfo['state'].'</v1:State>
						<v1:Zip>'.$this->billingInfo['zip'].'</v1:Zip>
						<v1:Country>'.$this->billingInfo['country'].'</v1:Country>
						<v1:Phone>'.$this->billingInfo['phone'].'</v1:Phone>
						<v1:Fax>'.$this->billingInfo['fax'].'</v1:Fax>
					</v1:Billing>
					<v1:Shipping>
						<v1:Name>'.$this->shippingInfo['name'].'</v1:Name>
						<v1:Address1>'.$this-> shippingInfo['addr1'].'</v1:Address1>
						<v1:Address2>'.$this->shippingInfo['addr2'].'</v1:Address2>
						<v1:City>'.$this->shippingInfo['city'].'</v1:City>
						<v1:State>'.$this->shippingInfo['state'].'</v1:State>
						<v1:Zip>'.$this->shippingInfo['zip'].'</v1:Zip>
						<v1:Country>'.$this->shippingInfo['country'].'</v1:Country>
					</v1:Shipping>
				</v1:Transaction>
			</fdggwsapi:FDGGWSApiOrderRequest>';
		$soapBody .= '</SOAP-ENV:Body></SOAP-ENV:Envelope>';
		/*** ***/
		//echo $soapBody;exit;
		
		try {
			$response = $this->curlIt($soapBody);
		} catch (Exception $e) {
			//Handle this gracefully. Overload errorMessage as you desire
			return array(
					'approved'					=> false,
					'errorMessage'			=> 'Internal Server Error. If this persists, please contact the administrator.',
					'exception'					=> true,
					'exceptionMessage'	=> $e->getMessage()
			);
		}
		
		$result = array(
				'exception' => false
		);
		//Was the transaction approved?
		if ($response->ProcessorResponseCode == 'A' && $response->TransactionResult == 'APPROVED') {
			$result['approved'] = true;
			$this->oid = (string)$response->OrderId;
		} else {
			$result['approved'] = false;
		}
		$result['errorMessage'] = (string)$response->ErrorMessage;
		$result['transactionTime'] = (string)$response->TransactionTime;
		$result['tDate'] = (string)$response->TDate;
		$result['oid']	= $this->oid;
		$result['reference'] = (string)$response->ProcessorReferenceNumber;
		$result['approvalCode'] = (string)$response->ApprovalCode;
		
		return $result;
  }
  
	/* Function: systemCheckAPI
	 * Checks the First Data Web Service for status
	 */
	public function systemCheckAPI() {
		/*** Generate our Soap Request ***/
		$soapBody = '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">';
		$soapBody .= '<SOAP-ENV:Header /><SOAP-ENV:Body>';
		$soapBody .= '
			<fdggwsapi:FDGGWSApiActionRequest xmlns:fdggwsapi="http://secure.linkpt.net/fdggwsapi/schemas_us/fdggwsapi" xmlns:a1="http://secure.linkpt.net/fdggwsapi/schemas_us/a1" xmlns:v1="http://secure.linkpt.net/fdggwsapi/schemas_us/v1">
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
			//TODO: Proper Exception handling
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
	 * Perform the curl action
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
	 * Parse the returned xml
	 */
	private function parseResponse($stringOrig) {
		// SimpleXML seems to have problems with the mixed namespaces, so take them out.
		$string = str_replace('fdggwsapi:', '', $stringOrig);
		$string = str_replace('SOAP-ENV:', '', $string);

		//Now strip down to just the Body contents.
		//$matches[0] includes Body tags, $matches[1] does not.
		preg_match('/<Body>(.*)<\/Body>/m', $string, $matches);
		//remove the xmlns reference.
		$string = preg_replace('/( xmlns:.*")/', '', $matches[1]);

		//Finally, generate our simplexml object.
		$return = simplexml_load_string($string);
		if ($return === false) {
			throw new Exception('XML Error: Could not parse: '.$stringOrig);
		}
		
		return $return;
	}
	
	private function luhn_check() {
		$cardnumber = $this->cardInfo['cardnumber'];

		// Set the string length and parity
		$cardnumber_length = strlen($cardnumber);
		$cardnumber_parity = $cardnumber_length % 2;

		// Loop through each digit and do the maths
		$total=0;
		for ($i=0; $i<$cardnumber_length; $i++) {
			$digit = $cardnumber[$i];
			// Multiply alternate digits by two
			if ($i % 2 == $cardnumber_parity) {
				$digit *= 2;
				// If the sum is two digits, add them together (in effect)
				if ($digit > 9) {
					$digit -= 9;
				}
			}
			// Total up the digits
			$total += $digit;
		}

		// If the total mod 10 equals 0, the number is valid
		return ($total % 10 == 0) ? true : false;
	}
   
}

?>
