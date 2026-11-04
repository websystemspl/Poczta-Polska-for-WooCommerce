<?php
/**
 * Minimal WC_Order for unit tests: ID and meta data only.
 *
 * @package PocztaPolska\WooCommerce\Tests
 */

declare( strict_types=1 );

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

if ( ! class_exists( 'WC_Abstract_Order' ) ) {
	/**
	 * Order base class.
	 */
	abstract class WC_Abstract_Order {

		/**
		 * Meta data.
		 *
		 * @var array<string, mixed>
		 */
		public array $meta = array();

		public int $saved = 0;

		public function __construct( private int $id ) {
		}

		public function get_id(): int {
			return $this->id;
		}

		/**
		 * Returns a meta value.
		 *
		 * @param string $key Meta key.
		 * @return mixed
		 */
		public function get_meta( string $key ) {
			return $this->meta[ $key ] ?? '';
		}

		public function delete_meta_data( string $key ): void {
			unset( $this->meta[ $key ] );
		}

		public function save(): int {
			++$this->saved;

			return $this->id;
		}
	}
}

if ( ! class_exists( 'WC_Order' ) ) {
	/**
	 * Order.
	 */
	class WC_Order extends WC_Abstract_Order {
	}
}
