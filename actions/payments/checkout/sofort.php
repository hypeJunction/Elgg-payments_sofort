<?php

use hypeJunction\Payments\Transaction;
use hypeJunction\Payments\Sofort\Adapter;

$ia = elgg_set_ignore_access(true);

$transaction_id = get_input('transaction_id');
$transaction = Transaction::getFromId($transaction_id);

$error = false;
if ($transaction) {
	$sofort_adapter = new Adapter();

	$merchant = $transaction->getMerchant();
	if ($merchant->sofort_config_key) {
		$config_key = $merchant->sofort_config_key;
	}
	$config_key = elgg_trigger_plugin_hook('payee_config_key', 'sofort', [
		'transction' => $transaction,
	], $config_key);

	if ($config_key) {
		$sofort_adatapter->setConfigKey($config_key);
	}
	
	$response = $sofort_adapter->pay($transaction);
} else {
	$error = elgg_echo('payments:error:not_found');
	$status_code = ELGG_HTTP_NOT_FOUND;
	$forward_url = REFERRER;
}

elgg_set_ignore_access($ia);

if ($error) {
	return elgg_error_response($error, $forward_url, $status_code);
}

return $response;

