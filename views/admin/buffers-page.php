<?php
/**
 * Buffers screen.
 *
 * @package PocztaPolska\WooCommerce
 *
 * @var array{
 *     title: string,
 *     env_label: string,
 *     buffers: list<array{id: int, date: string, past: bool, office: string, card: string, card_id: int, office_id: int, count: int, shipments: list<array{id: int, order: string, order_url: string, recipient: string, service: string, tracking: string, status: string}>}>,
 *     action: string,
 *     notice: string,
 *     notice_type: string,
 *     sent: list<array{id: int, sent_at: string, office: string, shipments: int, orders: list<array{number: string, url: string}>, status: string, error: bool}>,
 *     forgotten: list<int>,
 *     courier_hours: list<string>,
 *     courier_date: string,
 *     today: string
 * } $args
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap ppwc-buffers">
	<h1><?php echo esc_html( $args['title'] ); ?></h1>
	<p>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: environment name (test or production). */
				__( 'Buffers created by the plugin in the %s environment. A buffer collects the shipments posted on one day; send it to the post office when the parcels are ready.', 'poczta-polska-for-woocommerce' ),
				$args['env_label']
			)
		);
		?>
	</p>

	<?php if ( '' !== $args['notice'] ) : ?>
		<div class="notice notice-<?php echo esc_attr( $args['notice_type'] ); ?> is-dismissible"><p><?php echo esc_html( $args['notice'] ); ?></p></div>
	<?php endif; ?>

	<?php if ( array() !== $args['forgotten'] ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: buffer IDs. */
						_n(
							'Buffer %s no longer exists in Elektroniczny Nadawca (deleted or sent there). Its shipments are marked: check them in Elektroniczny Nadawca before creating them again.',
							'Buffers %s no longer exist in Elektroniczny Nadawca (deleted or sent there). Their shipments are marked: check them in Elektroniczny Nadawca before creating them again.',
							count( $args['forgotten'] ),
							'poczta-polska-for-woocommerce'
						),
						implode( ', ', $args['forgotten'] )
					)
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( array() === $args['buffers'] ) : ?>
		<p><?php esc_html_e( 'There are no buffers yet. A buffer is created with the first shipment of the day.', 'poczta-polska-for-woocommerce' ); ?></p>
	<?php endif; ?>

	<?php foreach ( $args['buffers'] as $ppwc_buffer ) : ?>
		<div class="postbox ppwc-buffer">
			<div class="postbox-header">
				<h2 class="hndle">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: buffer ID, 2: posting date, 3: number of shipments. */
							_n( 'Buffer %1$d, posting %2$s, %3$d shipment', 'Buffer %1$d, posting %2$s, %3$d shipments', $ppwc_buffer['count'], 'poczta-polska-for-woocommerce' ),
							$ppwc_buffer['id'],
							$ppwc_buffer['date'],
							$ppwc_buffer['count']
						)
					);
					?>
				</h2>
			</div>
			<div class="inside">
				<p>
					<?php
					/* translators: 1: posting office, 2: contract card. */
					echo esc_html( sprintf( __( 'Posting office: %1$s. Contract card: %2$s.', 'poczta-polska-for-woocommerce' ), $ppwc_buffer['office'], $ppwc_buffer['card'] ) );
					?>
				</p>
				<?php if ( $ppwc_buffer['past'] ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'The posting date has passed. Elektroniczny Nadawca sends a buffer only on its posting date, so change the date to today first (sending does it automatically). Shipments with printed labels get new tracking numbers.', 'poczta-polska-for-woocommerce' ); ?></p></div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( $args['action'] ); ?>" />
					<input type="hidden" name="buffer_id" value="<?php echo esc_attr( (string) $ppwc_buffer['id'] ); ?>" />
					<?php wp_nonce_field( $args['action'] ); ?>

					<?php if ( array() !== $ppwc_buffer['shipments'] ) : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<td class="check-column"><input type="checkbox" class="ppwc-buffer__all" aria-label="<?php esc_attr_e( 'Select all', 'poczta-polska-for-woocommerce' ); ?>" /></td>
									<th scope="col"><?php esc_html_e( 'Order', 'poczta-polska-for-woocommerce' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Recipient', 'poczta-polska-for-woocommerce' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Service', 'poczta-polska-for-woocommerce' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Tracking number', 'poczta-polska-for-woocommerce' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Status', 'poczta-polska-for-woocommerce' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $ppwc_buffer['shipments'] as $ppwc_shipment ) : ?>
									<tr>
										<th scope="row" class="check-column"><input type="checkbox" name="shipments[]" value="<?php echo esc_attr( (string) $ppwc_shipment['id'] ); ?>" /></th>
										<td>
											<?php if ( '' !== $ppwc_shipment['order_url'] ) : ?>
												<a href="<?php echo esc_url( $ppwc_shipment['order_url'] ); ?>">#<?php echo esc_html( $ppwc_shipment['order'] ); ?></a>
											<?php else : ?>
												#<?php echo esc_html( $ppwc_shipment['order'] ); ?>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( $ppwc_shipment['recipient'] ); ?></td>
										<td><?php echo esc_html( $ppwc_shipment['service'] ); ?></td>
										<td><?php echo esc_html( $ppwc_shipment['tracking'] ); ?></td>
										<td><?php echo esc_html( $ppwc_shipment['status'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="ppwc-buffer__actions">
							<button type="submit" name="operation" value="labels" class="button"><?php esc_html_e( 'Labels of selected (one PDF)', 'poczta-polska-for-woocommerce' ); ?></button>
							<?php
							$ppwc_targets = array_filter(
								$args['buffers'],
								static fn( array $ppwc_target ): bool => $ppwc_target['id'] !== $ppwc_buffer['id'] && $ppwc_target['card_id'] === $ppwc_buffer['card_id'] && $ppwc_target['office_id'] === $ppwc_buffer['office_id']
							);
							?>
							<?php if ( array() !== $ppwc_targets ) : ?>
								<label>
									<span class="screen-reader-text"><?php esc_html_e( 'Target buffer', 'poczta-polska-for-woocommerce' ); ?></span>
									<select name="target">
										<?php foreach ( $ppwc_targets as $ppwc_target ) : ?>
											<option value="<?php echo esc_attr( (string) $ppwc_target['id'] ); ?>"><?php echo esc_html( $ppwc_target['id'] . ' (' . $ppwc_target['date'] . ')' ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<button type="submit" name="operation" value="move" class="button"><?php esc_html_e( 'Move selected', 'poczta-polska-for-woocommerce' ); ?></button>
							<?php endif; ?>
							<button type="submit" name="operation" value="delete_shipments" class="button button-link-delete" data-ppwc-confirm="<?php esc_attr_e( 'Delete the selected shipments in Elektroniczny Nadawca?', 'poczta-polska-for-woocommerce' ); ?>"><?php esc_html_e( 'Delete selected', 'poczta-polska-for-woocommerce' ); ?></button>
						</p>
					<?php endif; ?>

					<p class="ppwc-buffer__actions ppwc-buffer__actions--buffer">
						<?php if ( $ppwc_buffer['past'] ) : ?>
							<button type="submit" name="operation" value="today" class="button"><?php esc_html_e( 'Change the date to today', 'poczta-polska-for-woocommerce' ); ?></button>
						<?php endif; ?>
						<?php if ( $ppwc_buffer['count'] > 0 ) : ?>
							<button type="submit" name="operation" value="send" class="button button-primary" data-ppwc-confirm="<?php esc_attr_e( 'Send the buffer to the post office? Its shipments cannot be changed afterwards.', 'poczta-polska-for-woocommerce' ); ?>"><?php esc_html_e( 'Send to the post office', 'poczta-polska-for-woocommerce' ); ?></button>
						<?php endif; ?>
						<button type="submit" name="operation" value="delete_buffer" class="button button-link-delete" data-ppwc-confirm="<?php esc_attr_e( 'Delete the buffer and all its shipments in Elektroniczny Nadawca?', 'poczta-polska-for-woocommerce' ); ?>"><?php esc_html_e( 'Delete buffer', 'poczta-polska-for-woocommerce' ); ?></button>
					</p>
				</form>
			</div>
		</div>
	<?php endforeach; ?>

	<?php if ( array() !== $args['sent'] ) : ?>
		<div class="postbox ppwc-buffer ppwc-envelopes">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Sent envelopes', 'poczta-polska-for-woocommerce' ); ?></h2>
			</div>
			<div class="inside">
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Envelope', 'poczta-polska-for-woocommerce' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Sent on', 'poczta-polska-for-woocommerce' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Posting office', 'poczta-polska-for-woocommerce' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Orders', 'poczta-polska-for-woocommerce' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'poczta-polska-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $args['sent'] as $ppwc_envelope ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $ppwc_envelope['id'] ); ?></td>
								<td><?php echo esc_html( $ppwc_envelope['sent_at'] ); ?></td>
								<td><?php echo esc_html( $ppwc_envelope['office'] ); ?></td>
								<td>
									<?php
									echo wp_kses(
										implode(
											', ',
											array_map(
												static fn( array $ppwc_order ): string => '' !== $ppwc_order['url']
													? '<a href="' . esc_url( $ppwc_order['url'] ) . '">#' . esc_html( $ppwc_order['number'] ) . '</a>'
													: '#' . esc_html( $ppwc_order['number'] ),
												$ppwc_envelope['orders']
											)
										),
										array( 'a' => array( 'href' => array() ) )
									);
									?>
									<?php if ( $ppwc_envelope['shipments'] > count( $ppwc_envelope['orders'] ) ) : ?>
										<br /><small>
										<?php
										/* translators: %d: number of shipments. */
										echo esc_html( sprintf( _n( '%d shipment', '%d shipments', $ppwc_envelope['shipments'], 'poczta-polska-for-woocommerce' ), $ppwc_envelope['shipments'] ) );
										?>
										</small>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $ppwc_envelope['error'] ) : ?>
										<strong class="ppwc-envelope--error"><?php echo esc_html( $ppwc_envelope['status'] ); ?></strong>
									<?php else : ?>
										<?php echo esc_html( $ppwc_envelope['status'] ); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( $args['action'] ); ?>" />
					<?php wp_nonce_field( $args['action'] ); ?>
					<p class="ppwc-buffer__actions">
						<button type="submit" name="operation" value="refresh_envelopes" class="button"><?php esc_html_e( 'Refresh statuses', 'poczta-polska-for-woocommerce' ); ?></button>
					</p>
				</form>
			</div>
		</div>
	<?php endif; ?>

	<div class="postbox ppwc-buffer ppwc-courier">
		<div class="postbox-header">
			<h2 class="hndle"><?php esc_html_e( 'Order a courier', 'poczta-polska-for-woocommerce' ); ?></h2>
		</div>
		<div class="inside">
			<p><?php esc_html_e( 'The courier collects the parcels from the pickup address in the plugin settings. Elektroniczny Nadawca confirms the order by email.', 'poczta-polska-for-woocommerce' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $args['action'] ); ?>" />
				<input type="hidden" name="operation" value="courier" />
				<?php wp_nonce_field( $args['action'] ); ?>
				<div class="ppwc-courier__fields">
					<p class="ppwc-courier__field">
						<label for="ppwc-courier-date"><?php esc_html_e( 'Date', 'poczta-polska-for-woocommerce' ); ?></label>
						<input type="date" id="ppwc-courier-date" name="date" min="<?php echo esc_attr( $args['today'] ); ?>" value="<?php echo esc_attr( $args['courier_date'] ); ?>" required />
					</p>
					<p class="ppwc-courier__field">
						<label for="ppwc-courier-hours"><?php esc_html_e( 'Hours', 'poczta-polska-for-woocommerce' ); ?></label>
						<select id="ppwc-courier-hours" name="hours">
							<?php foreach ( $args['courier_hours'] as $ppwc_hours ) : ?>
								<option value="<?php echo esc_attr( $ppwc_hours ); ?>"><?php echo esc_html( $ppwc_hours ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p class="ppwc-courier__field">
						<label for="ppwc-courier-count"><?php esc_html_e( 'Number of parcels', 'poczta-polska-for-woocommerce' ); ?></label>
						<input type="number" id="ppwc-courier-count" name="count" min="1" max="999" value="1" class="small-text" required />
					</p>
					<p class="ppwc-courier__field">
						<label for="ppwc-courier-weight"><?php esc_html_e( 'Total weight (kg)', 'poczta-polska-for-woocommerce' ); ?></label>
						<input type="number" id="ppwc-courier-weight" name="weight" min="1" max="10000" value="1" class="small-text" required />
					</p>
				</div>
				<p><button type="submit" class="button button-primary" data-ppwc-confirm="<?php esc_attr_e( 'Order the courier?', 'poczta-polska-for-woocommerce' ); ?>"><?php esc_html_e( 'Order the courier', 'poczta-polska-for-woocommerce' ); ?></button></p>
			</form>
		</div>
	</div>
</div>
