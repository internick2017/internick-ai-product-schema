<?php
/**
 * Builds a complete schema.org Product node for a WooCommerce product.
 *
 * All product data is read through the WooCommerce CRUD API. AI attributes
 * (Q&A, accessories, substitutes) are mapped to verified schema.org properties:
 *   - Q&A          -> subjectOf / FAQPage / Question+Answer
 *   - substitutes  -> isSimilarTo   (functionally similar products)
 *   - accessories  -> isRelatedTo   (schema.org has no "hasAccessory"; the
 *                     inverse `isAccessoryOrSparePartFor` would wrongly claim
 *                     THIS product is an accessory of the others)
 *
 * @package ShopGraph
 */

namespace ShopGraph\Schema;

use ShopGraph\ProductFields\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Pure builder: turns a WC_Product into a schema.org Product array.
 */
class ProductSchema {

	/**
	 * Build the schema.org Product array for a product.
	 *
	 * @param \WC_Product $product Product to describe.
	 * @return array<string, mixed>
	 */
	public function build( \WC_Product $product ): array {
		$permalink = get_permalink( $product->get_id() );

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'name'     => $product->get_name(),
			'url'      => $permalink,
		);

		$description = $product->get_short_description() ? $product->get_short_description() : $product->get_description();
		$description = trim( wp_strip_all_tags( $description ) );
		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		$sku = $product->get_sku();
		if ( '' !== $sku ) {
			$schema['sku'] = $sku;
		}

		$image_url = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'full' ) : '';
		if ( $image_url ) {
			$schema['image'] = $image_url;
		}

		$brand = $this->brand( $product );
		if ( '' !== $brand ) {
			$schema['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);
		}

		$schema['offers'] = array(
			'@type'         => 'Offer',
			'price'         => $product->get_price(),
			'priceCurrency' => get_woocommerce_currency(),
			'availability'  => $this->availability( $product ),
			'url'           => $permalink,
		);

		if ( $product->get_rating_count() > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $product->get_average_rating(),
				'reviewCount' => (string) $product->get_review_count(),
			);
		}

		$faq = $this->faq_node( Fields::get_qa( $product ) );
		if ( array() !== $faq ) {
			$schema['subjectOf'] = $faq;
		}

		$related = $this->product_refs( Fields::get_accessories( $product ) );
		if ( array() !== $related ) {
			$schema['isRelatedTo'] = $related;
		}

		$similar = $this->product_refs( Fields::get_substitutes( $product ) );
		if ( array() !== $similar ) {
			$schema['isSimilarTo'] = $similar;
		}

		return $schema;
	}

	/**
	 * Map WooCommerce stock status to a schema.org availability URL.
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	private function availability( \WC_Product $product ): string {
		switch ( $product->get_stock_status() ) {
			case 'outofstock':
				return 'https://schema.org/OutOfStock';
			case 'onbackorder':
				return 'https://schema.org/BackOrder';
			default:
				return 'https://schema.org/InStock';
		}
	}

	/**
	 * Resolve a brand name from the WooCommerce product brand taxonomy, if present.
	 *
	 * @param \WC_Product $product Product.
	 * @return string Brand name, or empty string when none.
	 */
	private function brand( \WC_Product $product ): string {
		if ( ! taxonomy_exists( 'product_brand' ) ) {
			return '';
		}
		$terms = get_the_terms( $product->get_id(), 'product_brand' );
		if ( is_array( $terms ) && ! empty( $terms ) ) {
			return $terms[0]->name;
		}
		return '';
	}

	/**
	 * Build the FAQPage node from Q&A rows.
	 *
	 * @param array<int, array{q?: string, a?: string}> $qa Q&A rows.
	 * @return array<string, mixed> Empty array when there is no Q&A.
	 */
	private function faq_node( array $qa ): array {
		$questions = array();
		foreach ( $qa as $row ) {
			$q = trim( (string) ( $row['q'] ?? '' ) );
			if ( '' === $q ) {
				continue;
			}
			$questions[] = array(
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => (string) ( $row['a'] ?? '' ),
				),
			);
		}

		if ( empty( $questions ) ) {
			return array();
		}

		return array(
			'@type'      => 'FAQPage',
			'mainEntity' => $questions,
		);
	}

	/**
	 * Turn a list of product IDs into minimal schema.org Product references.
	 *
	 * @param int[] $ids Product IDs.
	 * @return array<int, array<string, string>>
	 */
	private function product_refs( array $ids ): array {
		$refs = array();
		foreach ( $ids as $id ) {
			$linked = wc_get_product( $id );
			if ( $linked instanceof \WC_Product ) {
				$refs[] = array(
					'@type' => 'Product',
					'name'  => $linked->get_name(),
					'url'   => get_permalink( $linked->get_id() ),
				);
			}
		}
		return $refs;
	}
}
