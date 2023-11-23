<?php
$plugins = [
	'MailPoet'                        => 'https://wordpress.org/plugins/mailpoet/',
	'WooPayments'                        => 'https://wordpress.org/plugins/woocommerce-payments/',
	'Stripe'                             => 'https://wordpress.org/plugins/woocommerce-gateway-stripe/',
	'WooCommerce Google Analytics'       => 'https://wordpress.org/plugins/woocommerce-google-analytics-integration/',
	'WooCommerce Tax'                    => 'https://wordpress.org/plugins/woocommerce-services/',
	'Facebook for WooCommerce'           => 'https://wordpress.org/plugins/facebook-for-woocommerce/',
	'Square'                             => 'https://wordpress.org/plugins/woocommerce-square/',
	'PayPal Braintree'                   => 'https://wordpress.org/plugins/woocommerce-gateway-paypal-powered-by-braintree/',
	'ShipStation for WooCommerce'        => 'https://wordpress.org/plugins/woocommerce-shipstation-integration/',
	'WooCommerce Block'                  => 'https://wordpress.org/plugins/woo-gutenberg-products-block/',
	'Pinterest for WooCommerce'          => 'https://wordpress.org/plugins/pinterest-for-woocommerce/',
	'WooCommerce Accommodation Bookings' => 'https://wordpress.org/plugins/woocommerce-accommodation-bookings/',
	'Payfast Payment Gateway'            => 'https://wordpress.org/plugins/woocommerce-payfast-gateway/',
	'Eway'                               => 'https://wordpress.org/plugins/woocommerce-gateway-eway/',
	'Google Listings & Ads'              => 'https://wordpress.org/plugins/google-listings-and-ads/',
];

foreach ( $plugins as $name => $url ) {
	$slug = substr( $url, strrpos( trim( $url, '/' ), '/' ) + 1, - 1 );

	$cache = __DIR__ . "/cache/wporg-$slug.json";

	if ( ! file_exists( $cache ) ) {
		sleep( 1 );
		$response = file_get_contents( "https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=$slug" );
		if ( ! $response ) {
			throw new \RuntimeException( "Could not get plugin info for $slug" );
		}
		if ( ! file_put_contents( $cache, $response ) ) {
			throw new \RuntimeException( "Could not write plugin info for $slug" );
		}
	} else {
		$response = file_get_contents( $cache );
	}

	$response = json_decode( $response, true );
	$rating = number_format( $response['rating'] / 20, 1, '.' );
	$num_ratings = $response['num_ratings'];
	$active_installs = $response['active_installs'];

	echo $slug . ' Num ratings: ' . $num_ratings .  ' Active Installs: ' . $active_installs . PHP_EOL;
}