<?php

namespace hypeJunction\Payments\Sofort;

use hypeJunction\Payments\Transaction;

class Router {

	/**
	 * Route payment pages
	 *
	 * @param string $hook   "route"
	 * @param string $type   "payments"
	 * @param mixed  $return New route
	 * @param array  $params Hook params
	 * @return array
	 */
	public static function controller($hook, $type, $return, $params) {

		if (!is_array($return)) {
			return;
		}

		$segments = (array) elgg_extract('segments', $return);

		if ($segments[0] !== 'sofort') {
			return;
		}

		$forward_url = false;

		$transaction_id = get_input('transaction_id');
		$ia = elgg_set_ignore_access(true);
		$transaction = Transaction::getFromID($transaction_id);

		$forward_reason = null;

		$adapter = new Adapter();

		if ($transaction) {
			switch ($segments[1]) {
				case 'success' :
					$adapter->updateTransactionStatus($transaction);
					system_message(elgg_echo('payments:sofort:transaction:successful'));
					$forward_url = get_input('forward_url');
					if (!$forward_url) {
						$forward_url = "payments/transaction/$transaction_id";
					}
					break;

				case 'cancel' :
					$adapter->cancelPayment($transaction);
					register_error(elgg_echo('payments:sofort:transaction:cancelled'));
					$forward_url = get_input('forward_url');
					if (!$forward_url) {
						$forward_url = "payments/transaction/$transaction_id";
					}
					break;

				case 'timeout' :
					$adapter->cancelPayment($transaction);
					register_error(elgg_echo('payments:sofort:transaction:timeout'));
					$forward_url = get_input('forward_url');
					if (!$forward_url) {
						$forward_url = "payments/transaction/$transaction_id";
					}
					break;

				case 'notify' :
					if ($adapter->digestNotification()) {
						echo 'Notification digested';
						return false;
					}
					$forward_url = '';
					$forward_reason = '400';
					break;
			}
		}

		elgg_set_ignore_access($ia);

		if ($forward_url) {
			forward($forward_url, $forward_reason);
		}
	}

	/**
	 * Add Sofort to public pages
	 *
	 * @param string $hook   "public_pages"
	 * @param string $type   "walled_garden"
	 * @param array  $return Public pages
	 * @param array  $params Hook params
	 * @return array
	 */
	public static function setPublicPages($hook, $type, $return, $params) {
		$return[] = 'payments/sofort/.*';
		return $return;
	}

}
