<?php
/**
 * Settings screen.
 *
 * @package PocztaPolska\WooCommerce
 *
 * @var array{title: string, tabs: array<string, array{label: string, url: string}>, current: string, option: string, group: string, page: string} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php echo esc_html( $args['title'] ); ?></h1>
	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings sections', 'poczta-polska-for-woocommerce' ); ?>">
		<?php foreach ( $args['tabs'] as $ppwc_slug => $ppwc_tab ) : ?>
			<a href="<?php echo esc_url( $ppwc_tab['url'] ); ?>" class="nav-tab<?php echo $ppwc_slug === $args['current'] ? ' nav-tab-active' : ''; ?>"<?php echo $ppwc_slug === $args['current'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $ppwc_tab['label'] ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php settings_errors(); ?>
	<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
		<input type="hidden" name="<?php echo esc_attr( $args['option'] ); ?>[_tab]" value="<?php echo esc_attr( $args['current'] ); ?>" />
		<?php
		settings_fields( $args['group'] );
		do_settings_sections( $args['page'] );
		submit_button();
		?>
	</form>
	<?php
	/**
	 * Fires below the settings form of a tab.
	 *
	 * @param string $tab Tab slug.
	 */
	do_action( 'ppwc_settings_after_form', $args['current'] );
	?>
</div>
