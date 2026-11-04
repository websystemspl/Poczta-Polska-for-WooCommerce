<?php
/**
 * Main plugin class.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use PocztaPolska\WooCommerce\Admin\BuffersPage;
use PocztaPolska\WooCommerce\Admin\ConnectionTest;
use PocztaPolska\WooCommerce\Admin\MissingWeightNotice;
use PocztaPolska\WooCommerce\Admin\OrderBulkActions;
use PocztaPolska\WooCommerce\Admin\OrderShipmentsBox;
use PocztaPolska\WooCommerce\Admin\OrderTrackingColumn;
use PocztaPolska\WooCommerce\Admin\PasswordExpiry;
use PocztaPolska\WooCommerce\Admin\RequirementsNotice;
use PocztaPolska\WooCommerce\Admin\SettingsPage;
use PocztaPolska\WooCommerce\Checkout\Assets;
use PocztaPolska\WooCommerce\Checkout\BlocksIntegration;
use PocztaPolska\WooCommerce\Checkout\PickupPointCheckout;
use PocztaPolska\WooCommerce\Database\Schema;
use PocztaPolska\WooCommerce\Rest\PointsController;
use PocztaPolska\WooCommerce\Points\OrderPointDisplay;
use PocztaPolska\WooCommerce\Points\PointsSync;
use PocztaPolska\WooCommerce\Shipping\Methods\Registrar;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin into WordPress and WooCommerce.
 *
 * Feature modules (shipping methods, pickup point map, EN client, admin screens)
 * register their hooks from init() once WooCommerce is loaded.
 */
final class Plugin {

	/**
	 * Shared instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Returns the shared instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers the bootstrap hooks. Runs once, when the main plugin file loads.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Declares compatibility with WooCommerce features.
	 *
	 * HPOS: the plugin reads and writes orders only through the WooCommerce CRUD API.
	 * Cart and Checkout Blocks: the pickup point map integrates through the Store API.
	 */
	public function declare_compatibility(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}

		FeaturesUtil::declare_compatibility( 'custom_order_tables', PPWC_PLUGIN_FILE, true );
		FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', PPWC_PLUGIN_FILE, true );
	}

	/**
	 * Initialises the plugin after all plugins are loaded.
	 */
	public function init(): void {
		Schema::maybe_upgrade();

		if ( is_admin() ) {
			( new RequirementsNotice( new Requirements() ) )->register();
		}

		// "Requires Plugins: woocommerce" prevents activation without WooCommerce,
		// but WooCommerce can still be missing, e.g. after a manual file removal.
		if ( ! class_exists( \WooCommerce::class ) ) {
			return;
		}

		// Registered outside is_admin(): Action Scheduler runs the daily check from cron too,
		// and settings can change outside the admin screens (WP-CLI, REST).
		( new PasswordExpiry() )->register();
		( new AuditLog() )->register();
		( new Registrar() )->register();
		( new PointsSync() )->register();
		( new PointsController() )->register();
		( new PickupPointCheckout() )->register();
		( new Assets() )->register();
		BlocksIntegration::register();
		( new OrderPointDisplay() )->register();
		( new Privacy() )->register();

		if ( is_admin() ) {
			( new SettingsPage() )->register();
			( new ConnectionTest() )->register();
			( new MissingWeightNotice() )->register();
			( new OrderShipmentsBox() )->register();
			( new BuffersPage() )->register();
			( new OrderBulkActions() )->register();
			( new OrderTrackingColumn() )->register();
		}

		/**
		 * Fires when the plugin is ready and WooCommerce is available.
		 *
		 * Feature modules hook in here.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'ppwc_init', $this );
	}
}
