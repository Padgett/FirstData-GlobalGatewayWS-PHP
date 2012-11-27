<?php
require_once('config.php');
require_once('FirstData.class.php');

$cc = new FirstData($sharedKey, $store, $postingURL, true, 1);

$cc->setCardInfo('V', '4111111111111111', '12', '2014', '123');
$cc->setTotals(1.00);
$cc->setBillingInfo(array(
    'name'    => 'Test Name',
    'addr1'   => '123 Fake St.',
    'city'    => 'City',
    'state'   => 'AZ',
    'country' => 'US',
    'zip'     => '85080'
));

echo $cc->chargeIt();
?>