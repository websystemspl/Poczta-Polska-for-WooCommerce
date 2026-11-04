<?php
/**
 * Shipment box on the order screen.
 *
 * @package PocztaPolska\WooCommerce
 *
 * @var array{
 *     order_id: int,
 *     shipments: list<array{id: int, envelope: string, service: string, tracking: list<string>, sub_unknown: bool, status: string, buffer_id: int, environment: string, error: string, labels_url: string, editable: bool, deletable: bool, pending: bool}>,
 *     can_create: bool,
 *     services: array<string, string>,
 *     point_types: array<string, string>,
 *     service: string,
 *     formats: list<string>,
 *     format: string,
 *     parcels: int,
 *     weight: int,
 *     mobile: string,
 *     phone: string,
 *     offices: array<int, string>,
 *     office_id: int,
 *     pni: int,
 *     point: string,
 *     point_type: string,
 *     problems: list<string>,
 *     cod: string,
 *     cod_amount: float,
 *     address: string,
 *     environment: string
 * } $args
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ppwc-shipments" data-order-id="<?php echo esc_attr( (string) $args['order_id'] ); ?>" data-point-types="<?php echo esc_attr( (string) wp_json_encode( $args['point_types'] ) ); ?>" data-cod-amount="<?php echo esc_attr( (string) $args['cod_amount'] ); ?>" data-address="<?php echo esc_attr( $args['address'] ); ?>">
	<div class="ppwc-shipments__message" role="status" aria-live="polite"></div>

	<?php if ( array() !== $args['shipments'] ) : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Tracking number', 'poczta-polska-for-woocommerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Service', 'poczta-polska-for-woocommerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'poczta-polska-for-woocommerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'poczta-polska-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $args['shipments'] as $ppwc_shipment ) : ?>
					<tr data-shipment-id="<?php echo esc_attr( (string) $ppwc_shipment['id'] ); ?>">
						<td>
							<?php echo esc_html( array() !== $ppwc_shipment['tracking'] ? implode( ', ', $ppwc_shipment['tracking'] ) : '—' ); ?>
							<?php if ( $ppwc_shipment['sub_unknown'] ) : ?>
								<br /><small><?php esc_html_e( 'The other parcels got new numbers: they are on the new label and appear here after the buffer is sent.', 'poczta-polska-for-woocommerce' ); ?></small>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $ppwc_shipment['service'] ); ?></td>
						<td>
							<?php echo esc_html( $ppwc_shipment['status'] ); ?>
							<?php if ( '' !== $ppwc_shipment['envelope'] ) : ?>
								<br /><small><?php echo esc_html( $ppwc_shipment['envelope'] ); ?></small>
							<?php elseif ( $ppwc_shipment['buffer_id'] > 0 ) : ?>
								<br /><small>
								<?php
								/* translators: 1: buffer ID, 2: environment (test or production). */
								echo esc_html( sprintf( __( 'Buffer %1$d (%2$s)', 'poczta-polska-for-woocommerce' ), $ppwc_shipment['buffer_id'], $ppwc_shipment['environment'] ) );
								?>
								</small>
							<?php endif; ?>
							<?php if ( '' !== $ppwc_shipment['error'] ) : ?>
								<br /><span class="ppwc-shipments__error"><?php echo esc_html( $ppwc_shipment['error'] ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( '' !== $ppwc_shipment['labels_url'] ) : ?>
								<a class="button button-small" href="<?php echo esc_url( $ppwc_shipment['labels_url'] ); ?>"><?php esc_html_e( 'Label', 'poczta-polska-for-woocommerce' ); ?></a>
							<?php endif; ?>
							<?php if ( $ppwc_shipment['pending'] ) : ?>
								<button type="button" class="button button-small" data-ppwc-operation="check"><?php esc_html_e( 'Check in EN', 'poczta-polska-for-woocommerce' ); ?></button>
							<?php endif; ?>
							<?php if ( $ppwc_shipment['editable'] ) : ?>
								<button type="button" class="button button-small" data-ppwc-operation="recreate"><?php esc_html_e( 'Save changes', 'poczta-polska-for-woocommerce' ); ?></button>
							<?php endif; ?>
							<?php if ( $ppwc_shipment['deletable'] ) : ?>
								<button type="button" class="button button-small button-link-delete" data-ppwc-operation="delete"><?php esc_html_e( 'Delete', 'poczta-polska-for-woocommerce' ); ?></button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( array() !== $args['problems'] ) : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'Fix this before creating the shipment:', 'poczta-polska-for-woocommerce' ); ?></p>
			<ul>
				<?php foreach ( $args['problems'] as $ppwc_problem ) : ?>
					<li><?php echo esc_html( $ppwc_problem ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<fieldset class="ppwc-shipments__form">
		<legend class="screen-reader-text"><?php esc_html_e( 'Shipment details', 'poczta-polska-for-woocommerce' ); ?></legend>
		<div class="ppwc-shipments__fields">
			<p class="ppwc-shipments__field">
				<label for="ppwc-service-<?php echo esc_attr( (string) $args['order_id'] ); ?>"><?php esc_html_e( 'Service', 'poczta-polska-for-woocommerce' ); ?></label>
				<select id="ppwc-service-<?php echo esc_attr( (string) $args['order_id'] ); ?>" name="ppwc_service">
					<?php foreach ( $args['services'] as $ppwc_id => $ppwc_label ) : ?>
						<option value="<?php echo esc_attr( $ppwc_id ); ?>" <?php selected( $args['service'], $ppwc_id ); ?>><?php echo esc_html( $ppwc_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="ppwc-shipments__field">
				<label for="ppwc-format-<?php echo esc_attr( (string) $args['order_id'] ); ?>"><?php esc_html_e( 'Format', 'poczta-polska-for-woocommerce' ); ?></label>
				<select id="ppwc-format-<?php echo esc_attr( (string) $args['order_id'] ); ?>" name="ppwc_format">
					<?php foreach ( array( 'S', 'M', 'L', 'XL', '2XL', 'GABARYT_A', 'GABARYT_B' ) as $ppwc_format ) : ?>
						<option value="<?php echo esc_attr( $ppwc_format ); ?>" <?php selected( $args['format'], $ppwc_format ); ?>><?php echo esc_html( $ppwc_format ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="ppwc-shipments__field">
				<label for="ppwc-parcels-<?php echo esc_attr( (string) $args['order_id'] ); ?>"><?php esc_html_e( 'Parcels', 'poczta-polska-for-woocommerce' ); ?></label>
				<input type="number" id="ppwc-parcels-<?php echo esc_attr( (string) $args['order_id'] ); ?>" name="ppwc_parcels" min="1" max="20" step="1" value="<?php echo esc_attr( (string) $args['parcels'] ); ?>" />
			</p>
			<p class="ppwc-shipments__field">
				<label for="ppwc-weight-<?php echo esc_attr( (string) $args['order_id'] ); ?>"><?php esc_html_e( 'Total weight (g)', 'poczta-polska-for-woocommerce' ); ?></label>
				<input type="number" id="ppwc-weight-<?php echo esc_attr( (string) $args['order_id'] ); ?>" name="ppwc_weight" min="1" max="1000000" step="1" value="<?php echo esc_attr( (string) $args['weight'] ); ?>" />
			</p>
			<p class="ppwc-shipments__field">
				<label for="ppwc-mobile-<?php echo esc_attr( (string) $args['order_id'] ); ?>"><?php esc_html_e( 'Recipient mobile', 'poczta-polska-for-woocommerce' ); ?></label>
				<input type="tel" id="ppwc-mobile-<?php echo esc_attr( (string) $args['order_id'] ); ?>" name="ppwc_mobile" value="<?php echo esc_attr( $args['mobile'] ); ?>" />
			</p>
			<p class="ppwc-shipments__field">
				<label for="ppwc-phone-<?php echo esc_attr( (string) $args['order_id'] ); ?>"><?php esc_html_e( 'Recipient phone (landline)', 'poczta-polska-for-woocommerce' ); ?></label>
				<input type="tel" id="ppwc-phone-<?php echo esc_attr( (string) $args['order_id'] ); ?>" name="ppwc_phone" value="<?php echo esc_attr( $args['phone'] ); ?>" />
			</p>
			<?php if ( array() !== $args['offices'] ) : ?>
				<p class="ppwc-shipments__field">
					<label for="ppwc-office-<?php echo esc_attr( (string) $args['order_id'] ); ?>"><?php esc_html_e( 'Posting office', 'poczta-polska-for-woocommerce' ); ?></label>
					<select id="ppwc-office-<?php echo esc_attr( (string) $args['order_id'] ); ?>" name="ppwc_office_id" aria-describedby="ppwc-office-help-<?php echo esc_attr( (string) $args['order_id'] ); ?>">
						<?php foreach ( $args['offices'] as $ppwc_office_id => $ppwc_office ) : ?>
							<option value="<?php echo esc_attr( (string) $ppwc_office_id ); ?>" <?php selected( $args['office_id'], $ppwc_office_id ); ?>><?php echo esc_html( $ppwc_office ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="description" id="ppwc-office-help-<?php echo esc_attr( (string) $args['order_id'] ); ?>"><?php esc_html_e( 'The shipment goes to the buffer of this office and is posted there.', 'poczta-polska-for-woocommerce' ); ?></span>
				</p>
			<?php endif; ?>
		</div>
		<p class="ppwc-shipments__point"<?php echo '' === ( $args['point_types'][ $args['service'] ] ?? '' ) ? ' hidden' : ''; ?>>
			<strong><?php esc_html_e( 'Pickup point', 'poczta-polska-for-woocommerce' ); ?>:</strong>
			<input type="hidden" name="ppwc_pickup_pni" value="<?php echo esc_attr( $args['pni'] > 0 ? (string) $args['pni'] : '' ); ?>" data-point-type="<?php echo esc_attr( $args['point_type'] ); ?>" />
			<span class="ppwc-shipments__point-text" data-empty="<?php esc_attr_e( 'Not chosen.', 'poczta-polska-for-woocommerce' ); ?>">
				<?php
				echo esc_html(
					$args['pni'] > 0
						/* translators: 1: point name and address, 2: point ID (PNI). */
						? sprintf( __( '%1$s (PNI %2$d)', 'poczta-polska-for-woocommerce' ), $args['point'], $args['pni'] )
						: __( 'Not chosen.', 'poczta-polska-for-woocommerce' )
				);
				?>
			</span>
			<button type="button" class="button button-small ppwc-shipments__choose-point"><?php esc_html_e( 'Choose on the map', 'poczta-polska-for-woocommerce' ); ?></button>
		</p>
		<?php if ( '' !== $args['cod'] ) : ?>
			<p>
				<?php
				/* translators: %s: cash on delivery amount. */
				echo esc_html( sprintf( __( 'Cash on delivery: %s', 'poczta-polska-for-woocommerce' ), $args['cod'] ) );
				?>
			</p>
		<?php endif; ?>
		<?php if ( $args['can_create'] ) : ?>
			<p><button type="button" class="button button-primary" data-ppwc-operation="create"><?php esc_html_e( 'Create shipment in Elektroniczny Nadawca', 'poczta-polska-for-woocommerce' ); ?></button></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Before the label is printed you can change the service, format, parcels, weight, phone numbers or pickup point and click "Save changes": the shipment gets a new tracking number. A new pickup point is also saved in the order.', 'poczta-polska-for-woocommerce' ); ?></p>
		<?php endif; ?>
	</fieldset>
</div>
