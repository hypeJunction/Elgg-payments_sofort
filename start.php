<?php

/**
 * Sofort Payments
 *
 * @author Ismayil Khayredinov <info@hypejunction.com>
 * @copyright Copyright (c) 2016, Ismayil Khayredinov
 * @copyright Copyright (c) 2016, Social Business World
 */
require_once __DIR__ . '/autoloader.php';

use hypeJunction\Payments\Sofort\Payments;
use hypeJunction\Payments\Sofort\Router;

elgg_register_event_handler('init', 'system', function() {
	
	elgg_register_plugin_hook_handler('route', 'payments', [Router::class, 'controller'], 100);
	elgg_register_plugin_hook_handler('public_pages', 'walled_garden', [Router::class, 'setPublicPages']);

	elgg_register_plugin_hook_handler('refund', 'payments', [Payments::class, 'refundTransaction']);

	elgg_register_action('payments/checkout/sofort', __DIR__ . '/actions/payments/checkout/sofort.php', 'public');
});

