<?php
/**
 * Admin menu and settings screen.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Admin;

use PocztaPolska\WooCommerce\AuditLog;
use PocztaPolska\WooCommerce\En\Dictionary;
use PocztaPolska\WooCommerce\Logger;
use PocztaPolska\WooCommerce\Points\PointsRepository;
use PocztaPolska\WooCommerce\Points\PointsSync;
use PocztaPolska\WooCommerce\Sender;
use PocztaPolska\WooCommerce\Settings;
use PocztaPolska\WooCommerce\SettingsSchema;
use PocztaPolska\WooCommerce\Support\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the "Poczta Polska" admin menu with the settings screen.
 */
final class SettingsPage {

	public const MENU_SLUG = 'ppwc';

	public const SETTINGS_SLUG = 'ppwc-settings';

	private const OPTION_GROUP = 'ppwc_settings_group';

	public const CAPABILITY = 'manage_woocommerce';

	/**
	 * Action (admin-post.php) that clears the cached EN dictionaries.
	 */
	private const REFRESH_ACTION = 'ppwc_refresh_dictionaries';

	/**
	 * Action (admin-post.php) that queues the refresh of the pickup points copy.
	 */
	private const REFRESH_POINTS_ACTION = 'ppwc_refresh_points';

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_' . self::REFRESH_ACTION, array( $this, 'refresh_dictionaries' ) );
		add_action( 'admin_post_' . self::REFRESH_POINTS_ACTION, array( $this, 'refresh_points' ) );
		add_action( 'ppwc_settings_after_form', array( $this, 'render_logs_tab_extras' ) );
		add_filter( 'option_page_capability_' . self::OPTION_GROUP, array( $this, 'capability' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PPWC_PLUGIN_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Adds the top-level menu and the settings submenu.
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'Poczta Polska', 'poczta-polska-for-woocommerce' ),
			__( 'Poczta Polska', 'poczta-polska-for-woocommerce' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-email-alt',
			56
		);

		// Replaces the auto-generated first submenu item, so the menu opens the settings.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Poczta Polska settings', 'poczta-polska-for-woocommerce' ),
			__( 'Settings', 'poczta-polska-for-woocommerce' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Lets store managers save the settings (options.php requires manage_options by default).
	 */
	public function capability(): string {
		return self::CAPABILITY;
	}

	/**
	 * Adds a "Settings" link on the Plugins screen.
	 *
	 * @param array<int|string, string> $links Existing links.
	 * @return array<int|string, string>
	 */
	public function action_links( array $links ): array {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( self::url() ),
			esc_html__( 'Settings', 'poczta-polska-for-woocommerce' )
		);

		return array_merge( array( 'settings' => $settings ), $links );
	}

	/**
	 * Returns the settings screen URL.
	 *
	 * @param string $tab Tab slug, the first tab by default.
	 */
	public static function url( string $tab = '' ): string {
		$url = admin_url( 'admin.php?page=' . self::MENU_SLUG );

		return '' === $tab ? $url : add_query_arg( 'tab', $tab, $url );
	}

	/**
	 * Returns the settings page slug of a tab (used by add_settings_section()).
	 *
	 * @param string $tab Tab slug.
	 */
	private static function page_slug( string $tab ): string {
		return self::SETTINGS_SLUG . '-' . $tab;
	}

	/**
	 * Returns the tab shown on the current request.
	 */
	private static function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects which tab to display.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : SettingsSchema::TAB_CONNECTION;

		return array_key_exists( $tab, SettingsSchema::tabs() ) ? $tab : SettingsSchema::TAB_CONNECTION;
	}

	/**
	 * Registers the setting, sections and fields.
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
				'show_in_rest'      => false,
			)
		);

		$connection = self::page_slug( SettingsSchema::TAB_CONNECTION );

		add_settings_section(
			'ppwc_en',
			__( 'Elektroniczny Nadawca connection', 'poczta-polska-for-woocommerce' ),
			array( $this, 'render_en_section' ),
			$connection
		);

		$this->register_schema_fields();
	}

	/**
	 * Registers the sections and fields defined in SettingsSchema.
	 */
	private function register_schema_fields(): void {
		foreach ( array_keys( SettingsSchema::tabs() ) as $tab ) {
			foreach ( SettingsSchema::sections( $tab ) as $section => $title ) {
				add_settings_section( $section, $title, array( $this, 'render_section_intro' ), self::page_slug( $tab ) );
			}
		}

		foreach ( SettingsSchema::fields() as $field ) {
			$key = SettingsSchema::storage_key( $field, Settings::environment() );

			add_settings_field(
				'ppwc_' . $key,
				$field['label'],
				array( $this, 'render_field' ),
				self::page_slug( $field['tab'] ),
				$field['section'],
				array(
					'field'     => $field,
					'key'       => $key,
					'label_for' => in_array( $field['type'], array( 'radio' ), true ) ? '' : 'ppwc_' . $key,
				)
			);
		}
	}

	/**
	 * Clears the cached contract cards and posting offices (admin-post handler).
	 */
	public function refresh_dictionaries(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'poczta-polska-for-woocommerce' ), 403 );
		}

		check_admin_referer( self::REFRESH_ACTION );

		Dictionary::flush();

		wp_safe_redirect( self::url( SettingsSchema::TAB_CONTRACT ) );
		exit;
	}

	/**
	 * Renders the settings screen.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$current = self::current_tab();
		$tabs    = array();

		foreach ( SettingsSchema::tabs() as $tab => $label ) {
			$tabs[ $tab ] = array(
				'label' => $label,
				'url'   => self::url( $tab ),
			);
		}

		Template::view(
			'admin/settings-page.php',
			array(
				'title'   => get_admin_page_title(),
				'tabs'    => $tabs,
				'current' => $current,
				'option'  => Settings::OPTION,
				'group'   => self::OPTION_GROUP,
				'page'    => self::page_slug( $current ),
			)
		);
	}

	/**
	 * Queues the refresh of all pickup points (admin-post handler).
	 */
	public function refresh_points(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'poczta-polska-for-woocommerce' ), 403 );
		}

		check_admin_referer( self::REFRESH_POINTS_ACTION );

		( new PointsSync() )->queue_all();
		AuditLog::record( 'points_refresh_queued', 'points' );

		wp_safe_redirect( add_query_arg( 'ppwc_points_queued', '1', self::url( SettingsSchema::TAB_LOGS ) ) );
		exit;
	}

	/**
	 * Renders the pickup points copy, the log link and the audit log below the "Logs and data" form.
	 *
	 * @param string $tab Current tab.
	 */
	public function render_logs_tab_extras( string $tab ): void {
		if ( SettingsSchema::TAB_LOGS !== $tab ) {
			return;
		}

		$status    = PointsSync::status();
		$times     = array_filter( array_column( $status, 'time' ) );
		$errors    = array_filter( array_column( $status, 'error' ) );
		$format    = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$labels    = array(
			AuditLog::ACTION_SETTINGS_UPDATED => __( 'Settings changed', 'poczta-polska-for-woocommerce' ),
			'order_point_changed'             => __( 'Pickup point changed', 'poczta-polska-for-woocommerce' ),
			'points_refresh_queued'           => __( 'Pickup points refresh started', 'poczta-polska-for-woocommerce' ),
		);
		$entries   = array();
		$last_sync = array() !== $times ? wp_date( $format, (int) min( $times ) ) : false;

		foreach ( AuditLog::latest() as $entry ) {
			$user      = $entry['user_id'] > 0 ? get_userdata( $entry['user_id'] ) : false;
			$date      = wp_date( $format, (int) strtotime( $entry['created_at'] . ' UTC' ) );
			$changed   = isset( $entry['details']['changed'] ) && is_array( $entry['details']['changed'] ) ? implode( ', ', array_map( 'strval', $entry['details']['changed'] ) ) : '';
			$entries[] = array(
				'date'      => false !== $date ? $date : $entry['created_at'] . ' UTC',
				'user'      => false !== $user ? $user->display_name : __( 'System', 'poczta-polska-for-woocommerce' ),
				'operation' => $labels[ $entry['action'] ] ?? $entry['action'],
				'details'   => trim( $entry['object_type'] . ' ' . $entry['object_id'] . ( '' !== $changed ? ': ' . $changed : '' ) ),
			);
		}

		Template::view(
			'admin/logs-tab.php',
			array(
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display flag after a redirect.
				'queued'       => isset( $_GET['ppwc_points_queued'] ),
				'points_text'  => sprintf(
					/* translators: 1: number of points, 2: date of the oldest refresh. */
					__( 'Points in the copy: %1$s. Oldest refreshed voivodeship: %2$s. The copy refreshes daily at about 5:00.', 'poczta-polska-for-woocommerce' ),
					number_format( ( new PointsRepository() )->count(), 0, '', wc_get_price_thousand_separator() ),
					false !== $last_sync ? $last_sync : __( 'never', 'poczta-polska-for-woocommerce' )
				),
				'points_error' => array() !== $errors ? sprintf(
					/* translators: %s: error message. */
					_n( 'One voivodeship failed to refresh: %s', 'Some voivodeships failed to refresh, e.g.: %s', count( $errors ), 'poczta-polska-for-woocommerce' ),
					(string) reset( $errors )
				) : '',
				'refresh_url'  => wp_nonce_url( admin_url( 'admin-post.php?action=' . self::REFRESH_POINTS_ACTION ), self::REFRESH_POINTS_ACTION ),
				'log_url'      => admin_url( 'admin.php?page=wc-status&tab=logs&source=' . Logger::SOURCE ),
				'entries'      => $entries,
			)
		);
	}

	/**
	 * Renders the introduction of a schema section.
	 *
	 * @param array{id: string} $section Section arguments.
	 */
	public function render_section_intro( array $section ): void {
		$args = array(
			'text'      => '',
			'link_url'  => '',
			'link_text' => '',
			'problems'  => array(),
		);

		if ( 'ppwc_contract' === $section['id'] ) {
			$args['text'] = sprintf(
				/* translators: %s: environment name. */
				__( 'Cards and offices come from Elektroniczny Nadawca and are saved separately for each environment. Active environment: %s.', 'poczta-polska-for-woocommerce' ),
				Settings::environment_labels()[ Settings::environment() ]
			);
			$args['link_url']  = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::REFRESH_ACTION ), self::REFRESH_ACTION );
			$args['link_text'] = __( 'Refresh the lists', 'poczta-polska-for-woocommerce' );
		} elseif ( 'ppwc_sender' === $section['id'] ) {
			$args['text']     = __( 'Where the courier collects the parcels. Empty fields use the store address from WooCommerce settings (shown in grey). The sender on labels comes from the sender profile in Elektroniczny Nadawca (tab "Contract and posting").', 'poczta-polska-for-woocommerce' );
			$args['problems'] = Sender::problems();
		} else {
			return;
		}

		Template::view( 'admin/section-intro.php', $args );
	}

	/**
	 * Renders a schema field.
	 *
	 * @param array{field: array<string, mixed>, key: string, label_for: string} $args Field arguments.
	 */
	public function render_field( array $args ): void {
		$field   = $args['field'];
		$key     = $args['key'];
		$value   = Settings::get( $key );
		$error   = '';
		$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : $this->dynamic_options( (string) $field['key'], $value, $error );

		$placeholder = (string) ( $field['placeholder'] ?? '' );

		if ( str_starts_with( (string) $field['key'], 'sender_' ) ) {
			$placeholder = Sender::store_defaults()[ substr( (string) $field['key'], 7 ) ] ?? '';
		}

		/**
		 * Filters the placeholder of a settings field.
		 *
		 * @param string $placeholder Placeholder.
		 * @param string $key         Setting key.
		 */
		$placeholder = (string) apply_filters( 'ppwc_settings_field_placeholder', $placeholder, (string) $field['key'] );

		$locker_button = null;

		if ( 'posting_locker_pni' === $field['key'] ) {
			$sender        = Sender::data();
			$locker_button = array( 'address' => trim( $sender['street'] . ' ' . $sender['house_number'] . ', ' . $sender['city'], ' ,' ) );
		}

		Template::view(
			'admin/field.php',
			array(
				'type'          => (string) $field['type'],
				'id'            => 'ppwc_' . $key,
				'name'          => Settings::OPTION . '[' . $key . ']',
				'value'         => $value,
				'options'       => array_map( 'strval', $options ),
				'placeholder'   => $placeholder,
				'maxlength'     => isset( $field['max'] ) ? (int) $field['max'] : 0,
				'description'   => (string) ( $field['description'] ?? '' ),
				'error'         => $error,
				'locker_button' => $locker_button,
			)
		);
	}

	/**
	 * Returns the choices of fields whose options come from Elektroniczny Nadawca.
	 *
	 * @param string $key   Setting key without the environment suffix.
	 * @param string $value Current value, kept as a choice when EN does not return it.
	 * @param string $error Set to the EN error when the list could not be loaded.
	 * @return array<int|string, string> Numeric IDs become integer keys.
	 */
	private function dynamic_options( string $key, string $value, string &$error ): array {
		$options = array( '' => __( '— Select —', 'poczta-polska-for-woocommerce' ) );

		if ( 'card_id' === $key ) {
			foreach ( Dictionary::cards() as $id => $card ) {
				/* translators: 1: card number, 2: card description. */
				$options[ (string) $id ] = sprintf( __( '%1$d — %2$s', 'poczta-polska-for-woocommerce' ), $id, $card['description'] );
			}
			$error = Dictionary::error( 'cards' );
		} elseif ( 'profile_id' === $key ) {
			foreach ( Dictionary::profiles() as $id => $profile ) {
				/* translators: 1: profile name, 2: profile address. */
				$options[ (string) $id ] = sprintf( __( '%1$s, %2$s', 'poczta-polska-for-woocommerce' ), $profile['name'], $profile['address'] );
			}
			$error = Dictionary::error( 'profiles' );
		} elseif ( 'posting_office_id' === $key ) {
			foreach ( Dictionary::posting_offices() as $id => $office ) {
				/* translators: 1: office name printed on labels, 2: office ID. */
				$options[ (string) $id ] = sprintf( __( '%1$s (%2$d)', 'poczta-polska-for-woocommerce' ), $office['print_name'], $id );
			}
			$error = Dictionary::error( 'offices' );
		} else {
			return $options;
		}

		if ( '' !== $value && ! isset( $options[ $value ] ) ) {
			/* translators: %s: saved ID that Elektroniczny Nadawca no longer returns. */
			$options[ $value ] = sprintf( __( '%s (not available in Elektroniczny Nadawca)', 'poczta-polska-for-woocommerce' ), $value );
		}

		return $options;
	}

	/**
	 * Renders the connection tab: a card per environment with the switch, the credentials and the test button.
	 */
	public function render_en_section(): void {
		$descriptions = array(
			Settings::ENV_TEST       => __( 'Poczta Polska integration environment for checking the plugin. Shipments are not posted for real.', 'poczta-polska-for-woocommerce' ),
			Settings::ENV_PRODUCTION => __( 'Real shipments posted under your agreement with Poczta Polska.', 'poczta-polska-for-woocommerce' ),
		);
		$buttons      = array(
			Settings::ENV_TEST       => __( 'Test the test connection', 'poczta-polska-for-woocommerce' ),
			Settings::ENV_PRODUCTION => __( 'Test the production connection', 'poczta-polska-for-woocommerce' ),
		);
		$expiry       = get_option( PasswordExpiry::OPTION, array() );
		$environments = array();

		foreach ( Settings::environment_labels() as $env => $label ) {
			$login_id = 'ppwc_en_' . $env . '_login';
			$date     = is_array( $expiry ) && isset( $expiry[ $env ]['date'] ) && is_string( $expiry[ $env ]['date'] ) ? strtotime( $expiry[ $env ]['date'] ) : false;

			$environments[] = array(
				'key'         => $env,
				'label'       => $label,
				'description' => $descriptions[ $env ],
				'active'      => Settings::environment() === $env,
				'login'       => array(
					'type'       => 'text',
					'id'         => $login_id,
					'name'       => Settings::OPTION . '[en_' . $env . '_login]',
					'value'      => Settings::get( 'en_' . $env . '_login' ),
					'stored'     => false,
					'clear_name' => '',
				),
				'password'    => array(
					'type'       => 'password',
					'id'         => 'ppwc_en_' . $env . '_password',
					'name'       => Settings::OPTION . '[en_' . $env . '_password]',
					'value'      => '',
					'stored'     => Settings::has_en_password( $env ),
					'clear_name' => Settings::OPTION . '[en_' . $env . '_password_clear]',
				),
				'expiry'      => false !== $date ? date_i18n( get_option( 'date_format' ), $date ) : '',
				'button'      => $buttons[ $env ],
			);
		}

		Template::view(
			'admin/connection-section.php',
			array(
				'name'         => Settings::OPTION . '[environment]',
				'environments' => $environments,
			)
		);
	}
}
