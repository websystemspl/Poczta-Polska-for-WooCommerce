<?php
/**
 * Templates and views.
 *
 * @package PocztaPolska\WooCommerce
 */

declare( strict_types=1 );

namespace PocztaPolska\WooCommerce\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Renders HTML from template files instead of PHP classes.
 *
 * Storefront and email templates (templates/) go through wc_get_template(), so a theme
 * can override them in "yourtheme/poczta-polska-for-woocommerce/". Admin views (views/)
 * are plain includes that themes cannot replace.
 */
final class Template {

	/**
	 * Folder in the theme that overrides the plugin templates.
	 */
	public const THEME_PATH = 'poczta-polska-for-woocommerce/';

	/**
	 * Allowed HTML of the logo images.
	 *
	 * @var array<string, array<string, bool>>
	 */
	public const KSES_IMG = array(
		'img' => array(
			'class' => true,
			'src'   => true,
			'width' => true,
			'alt'   => true,
		),
	);

	/**
	 * Renders a storefront or email template.
	 *
	 * @param string               $name Template path relative to templates/, e.g. checkout/pickup-point.php.
	 * @param array<string, mixed> $args Variables available in the template.
	 */
	public static function render( string $name, array $args = array() ): void {
		wc_get_template( $name, $args, self::THEME_PATH, PPWC_PLUGIN_DIR . 'templates/' );
	}

	/**
	 * Returns a rendered storefront or email template.
	 *
	 * @param string               $name Template path relative to templates/.
	 * @param array<string, mixed> $args Variables available in the template.
	 */
	public static function capture( string $name, array $args = array() ): string {
		ob_start();
		self::render( $name, $args );

		return (string) ob_get_clean();
	}

	/**
	 * Renders an admin view. The view reads its data from $args.
	 *
	 * @param string               $name View path relative to views/, e.g. admin/settings-page.php.
	 * @param array<string, mixed> $args Data for the view.
	 */
	public static function view( string $name, array $args = array() ): void {
		$file = PPWC_PLUGIN_DIR . 'views/' . $name;

		if ( ! is_readable( $file ) ) {
			return;
		}

		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $args is read by the included view.
		( static function ( string $ppwc_file, array $args ): void {
			include $ppwc_file;
		} )( $file, $args );
	}
}
