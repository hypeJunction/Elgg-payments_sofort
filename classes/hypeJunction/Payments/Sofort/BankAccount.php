<?php

namespace hypeJunction\Payments\Sofort;

use hypeJunction\Payments\FundingSourceInterface;

class BankAccount implements FundingSourceInterface {

	public $country_code;
	public $last4;
	public $bic;
	public $holder;

	public function serialize() {
		return serialize(get_object_vars($this));
	}

	public function unserialize($serialized) {
		$data = unserialize($serialized);
		foreach ($data as $key => $value) {
			$this->$key = $value;
		}
	}

	public function format() {
		return "{$this->country_code}xxxx{$this->last4}";
	}

}
