<?php

namespace hypeJunction\Payments\Sofort;

use hypeJunction\Payments\Amount;
use hypeJunction\Payments\GatewayInterface;
use hypeJunction\Payments\Payment;
use hypeJunction\Payments\Refund;
use hypeJunction\Payments\Sofort\BankAccount;
use hypeJunction\Payments\Transaction;
use hypeJunction\Payments\TransactionInterface;
use Sofort\SofortLib\Notification;
use Sofort\SofortLib\Refund as SofortRefund;
use Sofort\SofortLib\Sofortueberweisung;
use Sofort\SofortLib\TransactionData;

class Adapter implements GatewayInterface {

	private $config_key;

	/**
	 * {@inheritdoc}
	 */
	public function pay(TransactionInterface $transaction) {
		$forward_url = $this->getPaymentUrl($transaction);

		if (!$forward_url) {
			$transaction->setStatus(Transaction::STATUS_FAILED);
			$error = elgg_echo('payments:sofort:payment_error');
			$status_code = ELGG_HTTP_INTERNAL_SERVER_ERROR;
			$forward_url = $transaction->getURL();
			return elgg_error_response($error, $forward_url, $status_code);
		}

		return elgg_redirect_response($forward_url);
	}

	/**
	 * Get payment URL
	 *
	 * @param TransactionInterface $transaction Transaction object
	 * @return string
	 */
	public function getPaymentUrl(TransactionInterface $transaction) {

		$transaction->setStatus(TransactionInterface::STATUS_PAYMENT_PENDING);

		$merchant = $transaction->getMerchant();
		$customer = $transaction->getCustomer();

		$amount = $transaction->getAmount();

		$sofort = new Sofortueberweisung($this->getConfigKey());
		$sofort->setAmount($amount->getConvertedAmount());
		$sofort->setCurrencyCode($amount->getCurrency());
		$sofort->setReason($merchant->getDisplayName(), "$transaction->guid");
		if ($customer->email) {
			$sofort->setEmailCustomer($customer->email);
		}
		if ($customer->phone) {
			$sofort->setPhoneCustomer($customer->phone);
		}
		if ($merchant->email) {
			$sofort->setNotificationEmail($merchant->email);
		}
		$sofort->setUserVariable([
			'transaction_id' => $transaction->transaction_id,
			'invoice_id' => $transaction->guid,
		]);

		$success = elgg_normalize_url(elgg_http_add_url_query_elements('payments/sofort/success', [
			'transaction_id' => $transaction->transaction_id,
			'forward_url' => $merchant->getURL(),
		]));
		$sofort->setSuccessUrl($success, true);

		$cancel = elgg_normalize_url(elgg_http_add_url_query_elements('payments/sofort/cancel', [
			'transaction_id' => $transaction->transaction_id,
			'forward_url' => $merchant->getURL(),
		]));
		$sofort->setAbortUrl($cancel);

		$timeout = elgg_normalize_url(elgg_http_add_url_query_elements('payments/sofort/timeout', [
			'transaction_id' => $transaction->transaction_id,
			'forward_url' => $merchant->getURL(),
		]));
		$sofort->setTimeoutUrl($timeout);

//		$notify = elgg_normalize_url(elgg_http_add_url_query_elements('payments/sofort/notify', [
//			'transaction_id' => $transaction->transaction_id,
//		]));
//		$sofort->setNotificationUrl($notify);

		$sofort->sendRequest();
		if ($sofort->isError()) {
			var_dump($this->getConfigKey());
			var_dump($sofort->getErrors());
			die();
			elgg_log($sofort->getError(), 'ERROR');
			return false;
		} else {
			$transaction->sofort_transaction_id = $sofort->getTransactionId();
			return $sofort->getPaymentUrl();
		}
	}

	/**
	 * Cancel payment
	 * @return bool
	 */
	public function cancelPayment(TransactionInterface $transaction) {
		$transaction->setStatus(TransactionInterface::STATUS_FAILED);
		return true;
	}

	/**
	 * Update transaction status via an API call
	 * 
	 * @param TransactionInterface $transaction Transaction
	 * @return TransactionInterface
	 */
	public function updateTransactionStatus(TransactionInterface $transaction) {

		if (!$transaction->sofort_transaction_id) {
			return $transaction;
		}

		$sofort_transaction = new TransactionData($this->getConfigKey());
		$sofort_transaction->addTransaction($transaction->sofort_transaction_id);
		$sofort_transaction->setApiVersion('2.0');
		$sofort_transaction->sendRequest();

		if ($sofort_transaction->isError()) {
			elgg_log($sofort_transaction->getError(), 'ERROR');
			return $transaction;
		}

		$funding_source = new BankAccount();
		$funding_source->holder = $sofort_transaction->getSenderHolder();
		$funding_source->bic = $sofort_transaction->getSenderBic();
		$funding_source->last4 = substr($sofort_transaction->getSenderIban(), -4);
		$funding_source->country_code = $sofort_transaction->getSenderCountryCode();

		$transaction->setFundingSource($funding_source);

		$status = $sofort_transaction->getStatus();

		switch ($status) {
			case 'untraceable' :
			case 'pending' :
			case 'received' :
				if ($transaction->status != TransactionInterface::STATUS_PAID) {
					$payment = new Payment();
					$payment->setTimeCreated(time())
							->setAmount(Amount::fromString((string) $sofort_transaction->getAmount(), $sofort_transaction->getCurrency()))
							->setPaymentMethod('sofort')
							->setDescription(elgg_echo('payments:payment'));
					$transaction->addPayment($payment);
					$transaction->setStatus(TransactionInterface::STATUS_PAID);
				}
				$transaction->setProcessorFee(new Amount((int) $sofort_transaction->getCostsFees(), $sofort_transaction->getCostsCurrencyCode()));
				break;

			case 'loss' :
				if ($transaction->status != TransactionInterface::STATUS_FAILED) {
					$transaction->setStatus(TransactionInterface::STATUS_FAILED);
				}
				break;

			case 'refunded' :
				$transaction->setProcessorFee(new Amount((int) $sofort_transaction->getCostsFees(), $sofort_transaction->getCostsCurrencyCode()));

				if ($sofort_transaction->getStatusReason() == 'refunded') {
					if ($transaction->status != TransactionInterface::STATUS_REFUNDED) {
						$transaction->setStatus(TransactionInterface::STATUS_REFUNDED);
					}
				} else {
					if ($transaction->status != TransactionInterface::STATUS_PARTIALLY_REFUNDED) {
						$transaction->setStatus(TransactionInterface::STATUS_PARTIALLY_REFUNDED);
					}
				}

				$sofort_refunded = Amount::fromString((string) $sofort_transaction->getAmountRefunded(), $sofort_transaction->getCurrency())->getAmount();

				$payments = $transaction->getPayments();

				$paid_amount = 0;
				$refunded_amount = 0;
				foreach ($payments as $payment) {
					$payment_amount = $payment->getAmount()->getAmount();
					if ($payment_amount > 0) {
						$paid_amount += $payment_amount;
					} else {
						$refunded_amount += $payment_amount;
					}
				}

				$refunded_amount = -$refunded_amount;
				
				if ($sofort_refunded > $refunded_amount) {
					$refund = new Refund();
					$refund->setTimeCreated(time())
							->setAmount(new Amount(-($sofort_refunded - $refunded_amount), $transaction->getAmount()->getCurrency()))
							->setPaymentMethod('sofort')
							->setDescription(elgg_echo('payments:refund'));
					$transaction->addPayment($refund);
				}
				break;
		}

		return $transaction;
	}

	/**
	 * {@inheritdoc}
	 */
	public function refund(TransactionInterface $transaction) {

		if (!$transaction->sofort_transaction_id) {
			return false;
		}

		if ($transaction->getStatus() !== TransactionInterface::STATUS_PAID) {
			return;
		}

		$refund = new SofortRefund($this->getConfigKey());
		//$refund->setSenderSepaAccount('SFRTDE20XXX', 'DE11888888889999999999', 'Max Mustermann');
		$refund->addRefund($transaction->sofort_transaction_id, $transaction->getAmount()->getConvertedAmount());
		$refund->sendRequest();

		if ($refund->isError()) {
			var_dump($refund->getErrors());
			die();
			elgg_log($refund->getError(), 'ERROR');
			return false;
		}

		$url = $refund->getPaymentUrl();

		if (!$url) {
			return false;
		}
		return elgg_redirect_response($url);
	}

	/**
	 * Digest Sofort notification
	 * @return bool
	 */
	public function digestNotification() {

		$request_content = _elgg_services()->request->getContent();

		$notification = new Notification();
		$sofort_transaction_id = $notification->getNotification($request_content);

		if (!$sofort_transaction_id) {
			return;
		}

		$transactions = elgg_get_entities_from_metadata([
			'types' => 'object',
			'metadata_name_value_pairs' => [
				'sofort_transaction_id' => $sofort_transaction_id,
			],
			'limit' => 0,
		]);

		foreach ($transactions as $transaction) {
			if ($transaction instanceof TransactionInterface) {
				$this->updateTransactionStatus($transaction);
			}
		}
	}

	/**
	 * Set the config key
	 * 
	 * @param string $config_key Config key
	 * @return void
	 */
	public function setConfigKey($config_key) {
		$this->config_key = $config_key;
	}

	/**
	 * Returns Sofort project config key
	 * @return string
	 */
	public function getConfigKey() {
		if (isset($this->config_key)) {
			return $this->config_key;
		}

		$mode = elgg_get_plugin_setting('environment', 'payments', 'sandbox');

		if ($mode == 'production') {
			$config_key = elgg_get_plugin_setting('live_config_key', 'payments_sofort');
		} else {
			$config_key = elgg_get_plugin_setting('test_config_key', 'payments_sofort');
		}

		return $config_key;
	}

}
