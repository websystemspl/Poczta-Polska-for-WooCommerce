<?php
/**
 * Definitions of the settings fields.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce;

use PocztaPolska\WooCommerce\Support\Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Tabs, sections and fields of the settings screen.
 *
 * The connection tab (environment, logins, passwords) has its own handling in Settings
 * and SettingsPage, because passwords are encrypted and never rendered.
 *
 * Field definition keys:
 * - key:         setting key; with "per_env" the stored key is "{key}_{environment}",
 * - tab, section, type (text, number, select, radio, checkbox, textarea), label,
 * - description: help text,
 * - options:     choices for select and radio (value => label),
 * - max:         maximum length in characters,
 * - validate:    callable( string ): ?string, returns the normalized value or null when invalid,
 * - error:       message shown when validation fails,
 * - default:     default value,
 * - per_env:     whether the value is stored separately for the test and production environments.
 *
 * @phpstan-type Field array{
 *     key: string,
 *     tab: string,
 *     section: string,
 *     type: string,
 *     label: string,
 *     description?: string,
 *     options?: array<string, string>,
 *     max?: int,
 *     validate?: callable(string): ?string,
 *     error?: string,
 *     default: string,
 *     per_env?: bool,
 *     placeholder?: string
 * }
 */
final class SettingsSchema {

	public const TAB_CONNECTION = 'connection';

	public const TAB_CONTRACT = 'contract';

	public const TAB_SENDER = 'sender';

	public const TAB_SHIPMENTS = 'shipments';

	public const TAB_COD = 'cod';

	public const TAB_LOGS = 'logs';

	/**
	 * Pocztex parcel formats.
	 *
	 * @var list<string>
	 */
	public const POCZTEX_FORMATS = array( 'S', 'M', 'L', 'XL', '2XL' );

	/**
	 * Returns the tabs (slug => label) in display order.
	 *
	 * @return array<string, string>
	 */
	public static function tabs(): array {
		return array(
			self::TAB_CONNECTION => __( 'Connection', 'poczta-polska-for-woocommerce' ),
			self::TAB_CONTRACT   => __( 'Contract and posting', 'poczta-polska-for-woocommerce' ),
			self::TAB_SENDER     => __( 'Pickup address', 'poczta-polska-for-woocommerce' ),
			self::TAB_SHIPMENTS  => __( 'Shipment defaults', 'poczta-polska-for-woocommerce' ),
			self::TAB_COD        => __( 'Cash on delivery', 'poczta-polska-for-woocommerce' ),
			self::TAB_LOGS       => __( 'Logs and data', 'poczta-polska-for-woocommerce' ),
		);
	}

	/**
	 * Returns the sections of a tab (slug => title).
	 *
	 * @param string $tab Tab slug.
	 * @return array<string, string>
	 */
	public static function sections( string $tab ): array {
		$sections = array(
			self::TAB_CONTRACT  => array(
				'ppwc_contract' => __( 'Contract card and posting office', 'poczta-polska-for-woocommerce' ),
				'ppwc_posting'  => __( 'Where Pocztex parcels are posted', 'poczta-polska-for-woocommerce' ),
			),
			self::TAB_SENDER    => array(
				'ppwc_sender' => __( 'Courier pickup address', 'poczta-polska-for-woocommerce' ),
			),
			self::TAB_SHIPMENTS => array(
				'ppwc_pocztex'   => __( 'Pocztex', 'poczta-polska-for-woocommerce' ),
				'ppwc_ecommerce' => __( 'eCommerce parcel', 'poczta-polska-for-woocommerce' ),
			),
			self::TAB_COD       => array(
				'ppwc_cod' => __( 'Cash on delivery payout', 'poczta-polska-for-woocommerce' ),
			),
			self::TAB_LOGS      => array(
				'ppwc_logs' => __( 'Logs', 'poczta-polska-for-woocommerce' ),
				'ppwc_data' => __( 'Data', 'poczta-polska-for-woocommerce' ),
			),
		);

		return $sections[ $tab ] ?? array();
	}

	/**
	 * Returns all field definitions.
	 *
	 * @return list<Field>
	 */
	public static function fields(): array {
		$length_error = static function ( int $max ): string {
			/* translators: %d: maximum number of characters. */
			return sprintf( __( 'The value is too long, the maximum is %d characters.', 'poczta-polska-for-woocommerce' ), $max );
		};

		return array(
			// Contract and posting.
			array(
				'key'         => 'card_id',
				'tab'         => self::TAB_CONTRACT,
				'section'     => 'ppwc_contract',
				'type'        => 'select',
				'label'       => __( 'Default contract card', 'poczta-polska-for-woocommerce' ),
				'description' => __( 'Used by shipping methods that do not select their own card.', 'poczta-polska-for-woocommerce' ),
				'default'     => '',
				'per_env'     => true,
				'validate'    => static fn( string $value ): ?string => '' === $value || ctype_digit( $value ) ? $value : null,
			),
			array(
				'key'         => 'posting_office_id',
				'tab'         => self::TAB_CONTRACT,
				'section'     => 'ppwc_contract',
				'type'        => 'select',
				'label'       => __( 'Default posting office', 'poczta-polska-for-woocommerce' ),
				'description' => __( 'The office that receives the shipment data when a buffer is sent.', 'poczta-polska-for-woocommerce' ),
				'default'     => '',
				'per_env'     => true,
				'validate'    => static fn( string $value ): ?string => '' === $value || ctype_digit( $value ) ? $value : null,
			),
			array(
				'key'         => 'profile_id',
				'tab'         => self::TAB_CONTRACT,
				'section'     => 'ppwc_contract',
				'type'        => 'select',
				'label'       => __( 'Sender profile', 'poczta-polska-for-woocommerce' ),
				'description' => __( 'Elektroniczny Nadawca prints the sender address of this profile on the labels. Profiles are managed in Elektroniczny Nadawca.', 'poczta-polska-for-woocommerce' ),
				'default'     => '',
				'per_env'     => true,
				'validate'    => static fn( string $value ): ?string => '' === $value || ctype_digit( $value ) ? $value : null,
			),
			array(
				'key'     => 'posting_channel',
				'tab'     => self::TAB_CONTRACT,
				'section' => 'ppwc_posting',
				'type'    => 'radio',
				'label'   => __( 'Posting method', 'poczta-polska-for-woocommerce' ),
				'options' => array(
					'COURIER' => __( 'A courier collects the parcels', 'poczta-polska-for-woocommerce' ),
					'PP'      => __( 'I post them at the posting office', 'poczta-polska-for-woocommerce' ),
					'APM'     => __( 'I post them in a Pocztex parcel locker', 'poczta-polska-for-woocommerce' ),
				),
				'default' => 'COURIER',
			),
			array(
				'key'         => 'posting_locker_pni',
				'tab'         => self::TAB_CONTRACT,
				'section'     => 'ppwc_posting',
				'type'        => 'text',
				'label'       => __( 'Parcel locker for posting (PNI)', 'poczta-polska-for-woocommerce' ),
				'description' => __( 'Required when you post parcels in a parcel locker.', 'poczta-polska-for-woocommerce' ),
				'default'     => '',
				'validate'    => static fn( string $value ): ?string => '' === $value || ctype_digit( $value ) ? $value : null,
				'error'       => __( 'The parcel locker ID (PNI) must contain digits only.', 'poczta-polska-for-woocommerce' ),
			),

			// Sender. Empty fields fall back to the store address from WooCommerce.
			self::text( 'sender_name', self::TAB_SENDER, 'ppwc_sender', __( 'Name', 'poczta-polska-for-woocommerce' ), 30, $length_error( 30 ) ),
			self::text( 'sender_name2', self::TAB_SENDER, 'ppwc_sender', __( 'Name (continued)', 'poczta-polska-for-woocommerce' ), 30, $length_error( 30 ) ),
			self::text( 'sender_street', self::TAB_SENDER, 'ppwc_sender', __( 'Street', 'poczta-polska-for-woocommerce' ), 35, $length_error( 35 ) ),
			self::text( 'sender_house_number', self::TAB_SENDER, 'ppwc_sender', __( 'House number', 'poczta-polska-for-woocommerce' ), 11, $length_error( 11 ) ),
			self::text( 'sender_flat_number', self::TAB_SENDER, 'ppwc_sender', __( 'Flat number', 'poczta-polska-for-woocommerce' ), 11, $length_error( 11 ) ),
			array(
				'key'      => 'sender_postcode',
				'tab'      => self::TAB_SENDER,
				'section'  => 'ppwc_sender',
				'type'     => 'text',
				'label'    => __( 'Postcode', 'poczta-polska-for-woocommerce' ),
				'default'  => '',
				'validate' => static fn( string $value ): ?string => '' === $value ? '' : Validator::postcode( $value ),
				'error'    => __( 'Enter a Polish postcode, e.g. 00-001.', 'poczta-polska-for-woocommerce' ),
			),
			self::text( 'sender_city', self::TAB_SENDER, 'ppwc_sender', __( 'City', 'poczta-polska-for-woocommerce' ), 35, $length_error( 35 ) ),
			array(
				'key'      => 'sender_mobile',
				'tab'      => self::TAB_SENDER,
				'section'  => 'ppwc_sender',
				'type'     => 'text',
				'label'    => __( 'Mobile phone', 'poczta-polska-for-woocommerce' ),
				'default'  => '',
				'validate' => static fn( string $value ): ?string => '' === $value ? '' : Validator::mobile( $value ),
				'error'    => __( 'Enter a Polish mobile number (9 digits).', 'poczta-polska-for-woocommerce' ),
			),
			array(
				'key'      => 'sender_phone',
				'tab'      => self::TAB_SENDER,
				'section'  => 'ppwc_sender',
				'type'     => 'text',
				'label'    => __( 'Phone', 'poczta-polska-for-woocommerce' ),
				'default'  => '',
				'validate' => static fn( string $value ): ?string => '' === $value ? '' : Validator::phone( $value ),
				'error'    => __( 'Enter a valid phone number.', 'poczta-polska-for-woocommerce' ),
			),
			array(
				'key'      => 'sender_email',
				'tab'      => self::TAB_SENDER,
				'section'  => 'ppwc_sender',
				'type'     => 'text',
				'label'    => __( 'Email', 'poczta-polska-for-woocommerce' ),
				'default'  => '',
				'max'      => 50,
				'validate' => static fn( string $value ): ?string => '' === $value || ( is_email( $value ) && Validator::fits( $value, 50 ) ) ? $value : null,
				'error'    => __( 'Enter a valid email address of up to 50 characters.', 'poczta-polska-for-woocommerce' ),
			),

			// Shipment defaults.
			array(
				'key'     => 'pocztex_format',
				'tab'     => self::TAB_SHIPMENTS,
				'section' => 'ppwc_pocztex',
				'type'    => 'select',
				'label'   => __( 'Format', 'poczta-polska-for-woocommerce' ),
				'options' => array(
					'S'   => __( 'S (up to 9 x 40 x 65 cm)', 'poczta-polska-for-woocommerce' ),
					'M'   => __( 'M (up to 20 x 40 x 65 cm)', 'poczta-polska-for-woocommerce' ),
					'L'   => __( 'L (up to 42 x 40 x 65 cm)', 'poczta-polska-for-woocommerce' ),
					'XL'  => __( 'XL (up to 60 x 60 x 70 cm, not for parcel lockers)', 'poczta-polska-for-woocommerce' ),
					'2XL' => __( '2XL (courier only)', 'poczta-polska-for-woocommerce' ),
				),
				'default' => 'S',
			),
			array(
				'key'         => 'pocztex_weight',
				'tab'         => self::TAB_SHIPMENTS,
				'section'     => 'ppwc_pocztex',
				'type'        => 'number',
				'label'       => __( 'Default weight (g)', 'poczta-polska-for-woocommerce' ),
				'description' => __( 'Used when products in the order have no weight. Pocztex always requires a weight.', 'poczta-polska-for-woocommerce' ),
				'default'     => '1000',
				'validate'    => static fn( string $value ): ?string => ctype_digit( $value ) && (int) $value >= 1 && (int) $value <= 50000 ? (string) (int) $value : null,
				'error'       => __( 'Enter a weight from 1 to 50000 g.', 'poczta-polska-for-woocommerce' ),
			),
			array(
				'key'         => 'pocztex_fragile',
				'tab'         => self::TAB_SHIPMENTS,
				'section'     => 'ppwc_pocztex',
				'type'        => 'checkbox',
				'label'       => __( 'Fragile', 'poczta-polska-for-woocommerce' ),
				'description' => __( 'Not available for parcel lockers, Elektroniczny Nadawca rejects it there.', 'poczta-polska-for-woocommerce' ),
				'default'     => '',
			),
			self::text( 'pocztex_content', self::TAB_SHIPMENTS, 'ppwc_pocztex', __( 'Content', 'poczta-polska-for-woocommerce' ), 50, $length_error( 50 ), __( 'Required by Pocztex, e.g. "Goods".', 'poczta-polska-for-woocommerce' ), __( 'Goods', 'poczta-polska-for-woocommerce' ) ),
			array(
				'key'         => 'pocztex_insurance',
				'tab'         => self::TAB_SHIPMENTS,
				'section'     => 'ppwc_pocztex',
				'type'        => 'number',
				'label'       => __( 'Insurance amount (PLN)', 'poczta-polska-for-woocommerce' ),
				'description' => __( '0 means no insurance.', 'poczta-polska-for-woocommerce' ),
				'default'     => '0',
				'validate'    => static fn( string $value ): ?string => ctype_digit( $value ) ? (string) (int) $value : null,
				'error'       => __( 'Enter the insurance amount in whole złoty.', 'poczta-polska-for-woocommerce' ),
			),
			self::text( 'pocztex_description', self::TAB_SHIPMENTS, 'ppwc_pocztex', __( 'Description', 'poczta-polska-for-woocommerce' ), 250, $length_error( 250 ), __( 'Printed on the label (first 40 characters). Use {order_number} for the order number.', 'poczta-polska-for-woocommerce' ), __( 'Order {order_number}', 'poczta-polska-for-woocommerce' ) ),
			array(
				'key'     => 'ecommerce_size',
				'tab'     => self::TAB_SHIPMENTS,
				'section' => 'ppwc_ecommerce',
				'type'    => 'select',
				'label'   => __( 'Size', 'poczta-polska-for-woocommerce' ),
				'options' => array(
					'GABARYT_A' => __( 'Size A', 'poczta-polska-for-woocommerce' ),
					'GABARYT_B' => __( 'Size B', 'poczta-polska-for-woocommerce' ),
				),
				'default' => 'GABARYT_A',
			),
			array(
				'key'     => 'ecommerce_category',
				'tab'     => self::TAB_SHIPMENTS,
				'section' => 'ppwc_ecommerce',
				'type'    => 'select',
				'label'   => __( 'Category', 'poczta-polska-for-woocommerce' ),
				'options' => array(
					'EKONOMICZNA'  => __( 'Economy', 'poczta-polska-for-woocommerce' ),
					'PRIORYTETOWA' => __( 'Priority', 'poczta-polska-for-woocommerce' ),
				),
				'default' => 'EKONOMICZNA',
			),

			// Cash on delivery.
			array(
				'key'         => 'cod_account',
				'tab'         => self::TAB_COD,
				'section'     => 'ppwc_cod',
				'type'        => 'text',
				'label'       => __( 'Bank account number', 'poczta-polska-for-woocommerce' ),
				'description' => __( 'Collected amounts are transferred to this account (26 digits).', 'poczta-polska-for-woocommerce' ),
				'default'     => '',
				'validate'    => static fn( string $value ): ?string => '' === $value ? '' : Validator::nrb( $value ),
				'error'       => __( 'The bank account number is invalid. Check the 26 digits.', 'poczta-polska-for-woocommerce' ),
			),
			array(
				'key'     => 'cod_method',
				'tab'     => self::TAB_COD,
				'section' => 'ppwc_cod',
				'type'    => 'select',
				'label'   => __( 'Payout method', 'poczta-polska-for-woocommerce' ),
				'options' => array(
					'RACHUNEK_BANKOWY' => __( 'Bank transfer', 'poczta-polska-for-woocommerce' ),
					'PRZEKAZ'          => __( 'Postal order', 'poczta-polska-for-woocommerce' ),
				),
				'default' => 'RACHUNEK_BANKOWY',
			),
			self::text( 'cod_title', self::TAB_COD, 'ppwc_cod', __( 'Transfer title', 'poczta-polska-for-woocommerce' ), 55, $length_error( 55 ), __( 'Use {order_number} for the order number. Up to 55 characters after replacing it.', 'poczta-polska-for-woocommerce' ), __( 'Order {order_number}', 'poczta-polska-for-woocommerce' ) ),

			// Logs and data.
			array(
				'key'     => 'log_level',
				'tab'     => self::TAB_LOGS,
				'section' => 'ppwc_logs',
				'type'    => 'select',
				'label'   => __( 'Log level', 'poczta-polska-for-woocommerce' ),
				'options' => array(
					'error' => __( 'Errors only', 'poczta-polska-for-woocommerce' ),
					'info'  => __( 'Errors and operations', 'poczta-polska-for-woocommerce' ),
					'debug' => __( 'Everything, including Elektroniczny Nadawca calls (troubleshooting)', 'poczta-polska-for-woocommerce' ),
				),
				'default' => 'error',
			),
			array(
				'key'         => 'delete_data_on_uninstall',
				'tab'         => self::TAB_LOGS,
				'section'     => 'ppwc_data',
				'type'        => 'checkbox',
				'label'       => __( 'Delete all data on uninstall', 'poczta-polska-for-woocommerce' ),
				'description' => __( 'Also deletes shipments (tracking numbers, buffers, label status) and the audit log when the plugin is deleted. Settings and the pickup point copy are always deleted.', 'poczta-polska-for-woocommerce' ),
				'default'     => '',
			),
		);
	}

	/**
	 * Returns the fields of a tab.
	 *
	 * @param string $tab Tab slug.
	 * @return list<Field>
	 */
	public static function fields_for_tab( string $tab ): array {
		return array_values( array_filter( self::fields(), static fn( array $field ): bool => $field['tab'] === $tab ) );
	}

	/**
	 * Returns the stored key of a field for an environment.
	 *
	 * @param array{key: string, per_env?: bool} $field       Field definition.
	 * @param string                             $environment Environment.
	 */
	public static function storage_key( array $field, string $environment ): string {
		return empty( $field['per_env'] ) ? $field['key'] : $field['key'] . '_' . $environment;
	}

	/**
	 * Default values of all fields, with per-environment keys for both environments.
	 *
	 * @return array<string, string>
	 */
	public static function defaults(): array {
		$defaults = array();

		foreach ( self::fields() as $field ) {
			foreach ( array_keys( Settings::EN_ENDPOINTS ) as $environment ) {
				$defaults[ self::storage_key( $field, $environment ) ] = $field['default'];
			}
		}

		return $defaults;
	}

	/**
	 * Sanitizes the submitted value of a field.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $raw   Submitted value (null when missing, e.g. an unchecked checkbox).
	 * @return string|null Clean value, or null when the value is invalid.
	 */
	public static function sanitize_value( array $field, $raw ): ?string {
		if ( 'checkbox' === $field['type'] ) {
			return empty( $raw ) ? '' : 'yes';
		}

		$value = is_scalar( $raw ) ? trim( sanitize_text_field( (string) $raw ) ) : '';

		if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
			return array_key_exists( $value, $field['options'] ) ? $value : null;
		}

		if ( isset( $field['max'] ) && is_int( $field['max'] ) && ! Validator::fits( $value, $field['max'] ) ) {
			return null;
		}

		if ( isset( $field['validate'] ) && is_callable( $field['validate'] ) ) {
			$validated = call_user_func( $field['validate'], $value );

			return is_string( $validated ) ? $validated : null;
		}

		return $value;
	}

	/**
	 * Builds a text field definition with a length limit.
	 *
	 * @param string $key         Setting key.
	 * @param string $tab         Tab slug.
	 * @param string $section     Section slug.
	 * @param string $label       Label.
	 * @param int    $max         Maximum length.
	 * @param string $error       Error shown when the value is too long.
	 * @param string $description Help text.
	 * @param string $default_value Default value.
	 * @return Field
	 */
	private static function text( string $key, string $tab, string $section, string $label, int $max, string $error, string $description = '', string $default_value = '' ): array {
		$field = array(
			'key'     => $key,
			'tab'     => $tab,
			'section' => $section,
			'type'    => 'text',
			'label'   => $label,
			'max'     => $max,
			'error'   => $error,
			'default' => $default_value,
		);

		if ( '' !== $description ) {
			$field['description'] = $description;
		}

		return $field;
	}
}
