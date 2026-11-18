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
 *     formats: array<string, list<string>>,
 *     format: string,
 *     fragile: bool,
 *     insurance: int,
 *     insurances: list<int>,
 *     value: int,
 *     envelope: bool,
 *     oversize: bool,
 *     confirmation: string,
 *     hour: string,
 *     distance: int,
 *     courier_services: list<string>,
 *     pocztex_services: list<string>,
 *     city_services: list<string>,
 *     channel: string,
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
<div class="ppwc-shipments" data-order-id="<?php echo esc_attr( (string) $args['order_id'] ); ?>" data-point-types="<?php echo esc_attr( (string) wp_json_encode( $args['point_types'] ) ); ?>" data-formats="<?php echo esc_attr( (string) wp_json_encode( $args['formats'] ) ); ?>" data-groups="
<?php
echo esc_attr(
	(string) wp_json_encode(
		array(
			'pocztex' => $args['pocztex_services'],
			'courier' => $args['courier_services'],
			'city'    => $args['city_services'],
		)
	)
);
?>
" data-cod-amount="<?php echo esc_attr( (string) $args['cod_amount'] ); ?>" data-address="<?php echo esc_attr( $args['address'] ); ?>">
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
					<?php // Only the formats of the chosen service; the script swaps the list when the service changes. ?>
					<?php foreach ( $args['formats'][ $args['service'] ] ?? array( $args['format'] ) as $ppwc_format ) : ?>
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
					<label for="ppwc-office-<?php echo esc_attr( (string) $args['order_id'] ); ?>"><?php 'PP' === $args['channel'] ? esc_html_e( 'Posting office', 'poczta-polska-for-woocommerce' ) : esc_html_e( 'Office of the buffer', 'poczta-polska-for-woocommerce' ); ?></label>
					<select id="ppwc-office-<?php echo esc_attr( (string) $args['order_id'] ); ?>" name="ppwc_office_id" aria-describedby="ppwc-office-help-<?php echo esc_attr( (string) $args['order_id'] ); ?>">
						<?php foreach ( $args['offices'] as $ppwc_office_id => $ppwc_office ) : ?>
							<option value="<?php echo esc_attr( (string) $ppwc_office_id ); ?>" <?php selected( $args['office_id'], $ppwc_office_id ); ?>><?php echo esc_html( $ppwc_office ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="description" id="ppwc-office-help-<?php echo esc_attr( (string) $args['order_id'] ); ?>">
						<?php
						if ( 'APM' === $args['channel'] ) {
							esc_html_e( 'The shipment goes to the buffer of this office; it is posted in a Pocztex parcel locker (plugin settings).', 'poczta-polska-for-woocommerce' );
						} elseif ( 'COURIER' === $args['channel'] ) {
							esc_html_e( 'The shipment goes to the buffer of this office; a courier collects it (plugin settings).', 'poczta-polska-for-woocommerce' );
						} else {
							esc_html_e( 'The shipment goes to the buffer of this office and is posted there.', 'poczta-polska-for-woocommerce' );
						}
						?>
					</span>
				</p>
			<?php endif; ?>
			<?php
			$ppwc_pocztex = in_array( $args['service'], $args['pocztex_services'], true );
			$ppwc_courier = in_array( $args['service'], $args['courier_services'], true );
			$ppwc_city    = in_array( $args['service'], $args['city_services'], true );
			$ppwc_id      = (string) $args['order_id'];
			?>
			<p class="ppwc-shipments__field" data-group="pocztex"<?php echo $ppwc_pocztex ? '' : ' hidden'; ?>>
				<label for="ppwc-insurance-<?php echo esc_attr( $ppwc_id ); ?>"><?php esc_html_e( 'Insurance', 'poczta-polska-for-woocommerce' ); ?></label>
				<select id="ppwc-insurance-<?php echo esc_attr( $ppwc_id ); ?>" name="ppwc_insurance" aria-describedby="ppwc-insurance-help-<?php echo esc_attr( $ppwc_id ); ?>">
					<option value="0" <?php selected( $args['insurance'], 0 ); ?>><?php esc_html_e( 'None', 'poczta-polska-for-woocommerce' ); ?></option>
					<?php foreach ( $args['insurances'] as $ppwc_amount ) : ?>
						<option value="<?php echo esc_attr( (string) $ppwc_amount ); ?>" <?php selected( $args['insurance'], $ppwc_amount ); ?>><?php echo esc_html( \PocztaPolska\WooCommerce\Admin\SettingsPage::format_pln( $ppwc_amount ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="description" id="ppwc-insurance-help-<?php echo esc_attr( $ppwc_id ); ?>"><?php esc_html_e( 'Cover up to PLN 1,000 is included in the service.', 'poczta-polska-for-woocommerce' ); ?></span>
			</p>
			<p class="ppwc-shipments__field" data-group="pocztex"<?php echo $ppwc_pocztex ? '' : ' hidden'; ?>>
				<label for="ppwc-value-<?php echo esc_attr( $ppwc_id ); ?>"><?php esc_html_e( 'Value (PLN)', 'poczta-polska-for-woocommerce' ); ?></label>
				<input type="number" id="ppwc-value-<?php echo esc_attr( $ppwc_id ); ?>" name="ppwc_value" min="0" max="1000000" step="1" value="<?php echo esc_attr( (string) $args['value'] ); ?>" aria-describedby="ppwc-value-help-<?php echo esc_attr( $ppwc_id ); ?>" />
				<span class="description" id="ppwc-value-help-<?php echo esc_attr( $ppwc_id ); ?>"><?php esc_html_e( 'Declared value of the parcel; 0: none.', 'poczta-polska-for-woocommerce' ); ?></span>
			</p>
			<p class="ppwc-shipments__field" data-group="courier"<?php echo $ppwc_courier ? '' : ' hidden'; ?>>
				<label for="ppwc-hour-<?php echo esc_attr( $ppwc_id ); ?>"><?php esc_html_e( 'Delivery hour', 'poczta-polska-for-woocommerce' ); ?></label>
				<select id="ppwc-hour-<?php echo esc_attr( $ppwc_id ); ?>" name="ppwc_hour">
					<?php foreach ( \PocztaPolska\WooCommerce\Admin\OrderShipmentsBox::hour_labels() as $ppwc_hour => $ppwc_hour_label ) : ?>
						<option value="<?php echo esc_attr( $ppwc_hour ); ?>" <?php selected( $args['hour'], $ppwc_hour ); ?>><?php echo esc_html( $ppwc_hour_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="ppwc-shipments__field" data-group="pocztex"<?php echo $ppwc_pocztex ? '' : ' hidden'; ?>>
				<label for="ppwc-confirmation-<?php echo esc_attr( $ppwc_id ); ?>"><?php esc_html_e( 'Delivery confirmation', 'poczta-polska-for-woocommerce' ); ?></label>
				<select id="ppwc-confirmation-<?php echo esc_attr( $ppwc_id ); ?>" name="ppwc_confirmation">
					<option value="" <?php selected( $args['confirmation'], '' ); ?>><?php esc_html_e( 'None', 'poczta-polska-for-woocommerce' ); ?></option>
					<option value="SMS" <?php selected( $args['confirmation'], 'SMS' ); ?>><?php esc_html_e( 'SMS to the recipient mobile', 'poczta-polska-for-woocommerce' ); ?></option>
					<option value="EMAIL" <?php selected( $args['confirmation'], 'EMAIL' ); ?>><?php esc_html_e( 'Email to the recipient', 'poczta-polska-for-woocommerce' ); ?></option>
				</select>
			</p>
			<p class="ppwc-shipments__field" data-group="city"<?php echo $ppwc_city ? '' : ' hidden'; ?>>
				<label for="ppwc-distance-<?php echo esc_attr( $ppwc_id ); ?>"><?php esc_html_e( 'Distance (km)', 'poczta-polska-for-woocommerce' ); ?></label>
				<input type="number" id="ppwc-distance-<?php echo esc_attr( $ppwc_id ); ?>" name="ppwc_distance" min="1" max="40" step="1" value="<?php echo esc_attr( $args['distance'] > 0 ? (string) $args['distance'] : '' ); ?>" aria-describedby="ppwc-distance-help-<?php echo esc_attr( $ppwc_id ); ?>" />
				<span class="description" id="ppwc-distance-help-<?php echo esc_attr( $ppwc_id ); ?>"><?php esc_html_e( 'Required for same-day delivery within the city, up to 40 km.', 'poczta-polska-for-woocommerce' ); ?></span>
			</p>
			<p class="ppwc-shipments__field ppwc-shipments__field--checkbox" data-group="pocztex"<?php echo $ppwc_pocztex ? '' : ' hidden'; ?>>
				<label for="ppwc-fragile-<?php echo esc_attr( $ppwc_id ); ?>">
					<input type="checkbox" id="ppwc-fragile-<?php echo esc_attr( $ppwc_id ); ?>" name="ppwc_fragile" value="yes" <?php checked( $args['fragile'] ); ?> />
					<?php esc_html_e( 'Fragile', 'poczta-polska-for-woocommerce' ); ?>
				</label>
				<span class="description"><?php esc_html_e( 'Not available in parcel lockers.', 'poczta-polska-for-woocommerce' ); ?></span>
			</p>
			<p class="ppwc-shipments__field ppwc-shipments__field--checkbox" data-group="pocztex"<?php echo $ppwc_pocztex ? '' : ' hidden'; ?>>
				<label for="ppwc-oversize-<?php echo esc_attr( $ppwc_id ); ?>">
					<input type="checkbox" id="ppwc-oversize-<?php echo esc_attr( $ppwc_id ); ?>" name="ppwc_oversize" value="yes" <?php checked( $args['oversize'] ); ?> />
					<?php esc_html_e( 'Non-standard parcel', 'poczta-polska-for-woocommerce' ); ?>
				</label>
				<span class="description"><?php esc_html_e( 'Exceeds the standard dimensions (oversize).', 'poczta-polska-for-woocommerce' ); ?></span>
			</p>
			<p class="ppwc-shipments__field ppwc-shipments__field--checkbox" data-group="courier"<?php echo $ppwc_courier ? '' : ' hidden'; ?>>
				<label for="ppwc-envelope-<?php echo esc_attr( $ppwc_id ); ?>">
					<input type="checkbox" id="ppwc-envelope-<?php echo esc_attr( $ppwc_id ); ?>" name="ppwc_envelope" value="yes" <?php checked( $args['envelope'] ); ?> />
					<?php esc_html_e( 'Pocztex envelope', 'poczta-polska-for-woocommerce' ); ?>
				</label>
				<span class="description"><?php esc_html_e( 'One parcel in format S up to 1 kg.', 'poczta-polska-for-woocommerce' ); ?></span>
			</p>
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
