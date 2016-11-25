Sofort Payments for Elgg
============================
![Elgg 2.3](https://img.shields.io/badge/Elgg-2.3-orange.svg?style=flat-square)

## Features

 * API for handling payments via Sofort

## Acknowledgements

 * Plugin has been sponsored by [Social Business World] (https://socialbusinessworld.org "Social Business World")

## Notes

### Example

See actions/payments/checkout/sofort.php for usage example.

### Payment Status

You can use `'transaction:<status>', 'payments'` hooks to apply additional logic upon payment status changes.
Payments are not synchronous.

### Notification URL

Sofort requires that Notification URL be reachable via their DNS, so you will not be able to test notifications on localhost.
All payments on localhost will fail, unless you comment out notification URL setting in the `hypeJunction\Payments\Sofort\Adapter`

### Credentials

 * Login to your Sofort dashboard
 * Create a new project (you can omit Success/Abort/Notification URLs, they will be submitted with each payment)
 * Check the Test mode checkbox and save your project
 * Copy the Configuration key to the plugin settings

### Testing

 * Information on testing sort codes is available under the Testing tab of your project. Details may differ based on your country.

### Refunds

Refunds issued via the plugin will be prepared and available in your Sofort account under Refunds.
You will need to manually consolidate them and issue payments.