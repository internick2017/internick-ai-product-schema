<?php
/**
 * Seed demo products with ShopGraph AI attributes for manual DDEV verification.
 * Run: ddev exec wp eval-file tests/seed-demo.php --path=wp
 *
 * @package ShopGraph
 */

$make_simple = static function ( string $name, string $price, string $sku ): int {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_status( 'publish' );
	$p->set_regular_price( $price );
	$p->set_sku( $sku );
	return $p->save();
};

$case_id  = $make_simple( 'Rugged Phone Case', '19.90', 'SG-CASE-1' );
$rival_id = $make_simple( 'Rival Phone X', '499.00', 'SG-RIVAL-1' );

$phone = new WC_Product_Simple();
$phone->set_name( 'ShopGraph Demo Phone' );
$phone->set_status( 'publish' );
$phone->set_regular_price( '699.00' );
$phone->set_sku( 'SG-PHONE-1' );
$phone->set_short_description( 'A demo product showcasing ShopGraph AI attributes.' );
$phone->update_meta_data(
	'_shopgraph_qa',
	array(
		array(
			'q' => 'Is it waterproof?',
			'a' => 'Yes, rated IP68.',
		),
		array(
			'q' => 'What is the battery life?',
			'a' => 'About 2 days of typical use.',
		),
	)
);
$phone->update_meta_data( '_shopgraph_accessories', array( $case_id ) );
$phone->update_meta_data( '_shopgraph_substitutes', array( $rival_id ) );
$phone_id = $phone->save();

// Clear the cached llms.txt so the new products show immediately.
delete_transient( 'shopgraph_llms_txt' );

WP_CLI::success( "Seeded phone #{$phone_id} (case #{$case_id}, rival #{$rival_id})" );
WP_CLI::line( 'PHONE_URL=' . get_permalink( $phone_id ) );
