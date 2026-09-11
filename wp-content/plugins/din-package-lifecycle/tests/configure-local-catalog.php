<?php
// Explicit, one-time setup for this local site's verified four products. Never runs on page loads.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'http://localhost:8883/' !== home_url( '/' ) || ! class_exists( 'DIN_Packages' ) ) { return; }
$pairs = array(
	91 => array( 'name'=>'Guestpost Annual', 'period'=>'annual', 'annual'=>91, 'lifetime'=>90 ),
	90 => array( 'name'=>'Guestpost Lifetime', 'period'=>'lifetime', 'annual'=>91, 'lifetime'=>90 ),
	83 => array( 'name'=>'Link Insertion Annual', 'period'=>'annual', 'annual'=>83, 'lifetime'=>51 ),
	51 => array( 'name'=>'Link Insertion Lifetime', 'period'=>'lifetime', 'annual'=>83, 'lifetime'=>51 ),
);
foreach ( $pairs as $id => $data ) {
	$product = wc_get_product( $id );
	if ( ! $product || $product->get_name() !== $data['name'] || ! $product->is_type( 'simple' ) ) { throw new RuntimeException( 'Catalog changed; stopped before writing.' ); }
	foreach ( array( '_din_package_period'=>$data['period'], '_din_package_annual_product_id'=>$data['annual'], '_din_package_lifetime_product_id'=>$data['lifetime'] ) as $key=>$value ) {
		$existing = $product->get_meta( $key );
		if ( '' !== $existing && (string) $existing !== (string) $value ) { throw new RuntimeException( 'Existing package configuration differs; stopped before writing.' ); }
	}
}
foreach ( $pairs as $id => $data ) {
	$product = wc_get_product( $id );
	$product->update_meta_data( '_din_package_period', $data['period'] );
	$product->update_meta_data( '_din_package_annual_product_id', $data['annual'] );
	$product->update_meta_data( '_din_package_lifetime_product_id', $data['lifetime'] );
	$product->save_meta_data();
	echo 'Configured #' . $id . ' ' . $product->get_name() . '; price unchanged: ' . $product->get_price() . "\n";
}
