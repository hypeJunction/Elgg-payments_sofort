<?php

namespace hypeJunction\Payments\Sofort;

use Elgg\Http\ResponseBuilder;
use hypeJunction\Payments\Sofort\Adapter;
use hypeJunction\Payments\Transaction;
use hypeJunction\Payments\TransactionInterface;

class Payments {


	/**
	 * Initiate a refund
	 *
	 * @param string $hook   "refund"
	 * @param string $type   "payments"
	 * @param bool   $return Success
	 * @param array  $params Hook params
	 * @return bool|ResponseBuilder
	 */
	public static function refundTransaction($hook, $type, $return, $params) {
		if ($return) {
			return;
		}
		
		$transaction = elgg_extract('entity', $params);
		if (!$transaction instanceof Transaction) {
			return;
		}

		if ($transaction->payment_method == 'sofort') {
			$adapter = new Adapter();
			$result = $adapter->refund($transaction);

			if (!$result) {
				return false;
			}

			$transaction->setStatus(TransactionInterface::STATUS_REFUND_PENDING);

			return $result;
		}
	}

}
