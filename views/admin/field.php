<?php
/**
 * Settings field.
 *
 * @package PocztaPolska\WooCommerce
 *
 * @var array{type: string, id: string, name: string, value: string, options: array<int|string, string>, placeholder: string, maxlength: int, description: string, error: string, locker_button: array{address: string}|null} $args
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( '' !== $args['error'] ) : ?>
	<div class="notice notice-error inline"><p><?php echo esc_html( $args['error'] ); ?></p></div>
<?php endif; ?>

<?php if ( 'select' === $args['type'] ) : ?>
	<select id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>">
		<?php foreach ( $args['options'] as $ppwc_value => $ppwc_label ) : ?>
			<option value="<?php echo esc_attr( (string) $ppwc_value ); ?>" <?php selected( $args['value'], (string) $ppwc_value ); ?>><?php echo esc_html( $ppwc_label ); ?></option>
		<?php endforeach; ?>
	</select>
<?php elseif ( 'radio' === $args['type'] ) : ?>
	<fieldset>
		<?php foreach ( $args['options'] as $ppwc_value => $ppwc_label ) : ?>
			<label>
				<input type="radio" name="<?php echo esc_attr( $args['name'] ); ?>" value="<?php echo esc_attr( (string) $ppwc_value ); ?>" <?php checked( $args['value'], (string) $ppwc_value ); ?> />
				<?php echo esc_html( $ppwc_label ); ?>
			</label><br />
		<?php endforeach; ?>
	</fieldset>
<?php elseif ( 'checkbox' === $args['type'] ) : ?>
	<label>
		<input type="checkbox" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>" value="yes" <?php checked( $args['value'], 'yes' ); ?> />
		<?php echo esc_html( $args['description'] ); ?>
	</label>
<?php elseif ( 'number' === $args['type'] ) : ?>
	<input type="number" class="small-text" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>" value="<?php echo esc_attr( $args['value'] ); ?>" min="0" step="1" />
<?php else : ?>
	<input type="text" class="regular-text" id="<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['name'] ); ?>" value="<?php echo esc_attr( $args['value'] ); ?>" placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"<?php echo $args['maxlength'] > 0 ? ' maxlength="' . esc_attr( (string) $args['maxlength'] ) . '"' : ''; ?> />
<?php endif; ?>

<?php if ( null !== $args['locker_button'] ) : ?>
	<button type="button" class="button ppwc-settings-locker" data-target="<?php echo esc_attr( $args['id'] ); ?>" data-address="<?php echo esc_attr( $args['locker_button']['address'] ); ?>"><?php esc_html_e( 'Choose on the map', 'poczta-polska-for-woocommerce' ); ?></button>
<?php endif; ?>

<?php if ( '' !== $args['description'] && 'checkbox' !== $args['type'] ) : ?>
	<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
<?php endif; ?>
