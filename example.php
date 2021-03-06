<?php
require_once('config.php');
require_once('FirstData.class.php');

$cc = new FirstData($postingURL, $store, $userID, $password, $sslCert, $sslKey, $sslKeyPass);

$cc->setCardInfo('V', '4111111111111111', '12', '2014', '123');
$cc->setTotals(1.01);
$cc->setBillingInfo(array(
    'name'    => 'Test Name',
    'addr1'   => '123 Fake St.',
    'city'    => 'City',
    'state'   => 'AZ',
    'country' => 'US',
    'zip'     => '85080'
));

if ($cc->systemCheckAPI()) {
	echo "System Alive<br/><br/>\n\n";
} else {
	die('The System Is Down: http://youtu.be/hXhjQn_gssI');
}

$charged = $cc->chargeIt();
if ($charged['approved']) {
	echo "Sale Approved!<br /><br/>\n\n";
	echo 'Transaction Time: '.$charged['transactionTime']."<br/>\n";
	echo 'Order ID: '.$charged['oid']."<br/>\n";
	echo 'Reference: '.$charged['reference']."<br/>\n";
	echo 'Approval Code: '.$charged['approvalCode'];
} else {
	echo "Sale Declined: ".$charged['errorMessage']."<br/><br/>\n\n";
	echo 'Transaction Time: '.$charged['transactionTime'];
}
?>