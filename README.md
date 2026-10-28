# Poczta Polska for WooCommerce

WooCommerce integration with Poczta Polska Elektroniczny Nadawca (EN): Pocztex shipping methods with the pickup point map and cash on delivery, shipment buffers, labels, dispatch and courier pickup.

Status: in development.

## What the plugin does

Customers choose Poczta Polska delivery at checkout. Merchants create and send shipments straight from the WordPress admin, without retyping order data into EN. The shop talks to EN directly over WebAPI (SOAP). There is no intermediate server, licence key or telemetry.

### Checkout

- Shipping methods configured in standard WooCommerce shipping zones:
  - **Pocztex Kurier**: door-to-door courier delivery.
  - **Pocztex PUNKT**: pickup at a post office or a partner point (Żabka, Lewiatan, ABC and others).
  - **Pocztex AUTOMAT**: pickup from a parcel locker.
  - **eCommerce shipment**: registered business letter (symbol 845), up to 2000 g.
- Each method has its own price. The merchant can add any number of methods.
- Each method can have a weight limit and allowed destination countries. A domestic service is never offered for a foreign address.
- Pickup points are chosen on the official Poczta Polska map widget. The widget lists only points that match the method (PUNKT or AUTOMAT), and an order cannot be placed without a selected point.
- The map works in both the classic checkout and the Cart/Checkout Blocks.
- The selected point is stored in the order and shown in the admin, in emails and in My Account.
- Cash on delivery:
  - Pocztex methods only, never eCommerce.
  - Only at points that accept it (`maksymalnaKwotaPobrania` > 0), with the map limited to those points and the order value capped at the point's limit.
  - The checks also run on the server.
- The pickup point list is refreshed daily (around 05:00). It is used for server-side COD checks and as a fallback when the map is unavailable.

### Admin settings

- EN credentials for production and the test environment (INT), with an environment switch. The settings include a connection test, a warning before the EN password expires and clear login error messages.
- Contract cards from EN. The merchant picks a default card and can assign a different card per shipping method.
- Default parcel parameters per method:
  - Pocztex: size S to 2XL, weight, fragile, contents, insurance and description.
  - eCommerce: size A or B.
- How Pocztex parcels are posted: collected by courier, dropped off at a post office, or dropped off at a parcel locker. The post office and the locker are chosen on the map.
- The posting office list from EN, with a default office.
- Sender details taken from the store settings, with an override.

### Shipments and buffers

- Create shipments from orders. Recipient and sender data are filled in from the order and validated before they are sent to EN.
- Edit a shipment until its label is printed: phone, pickup point and shipment type.
- Move shipments between buffers, and delete them (in EN as well).
- Print labels. After the first label is printed, the shipment is locked and can only be deleted.
- Send the buffer to EN and order a courier pickup.
- Multi-parcel Pocztex shipments.
- Bulk actions for many orders at once: create shipments, print labels as one PDF, send, delete and move.
- A buffer from a previous day is moved to today before sending, because EN only accepts the current date.
- No duplicate shipments after a timeout. With partial failures, the error is shown for each shipment.
- EN errors are shown as readable messages.

### Logging

- EN requests and responses are written to the WooCommerce log. Passwords, bank account numbers and personal data are masked.
- Admin operations (settings changes, deleting a shipment or a buffer) are recorded together with the WordPress user who performed them.

## Technical notes

- Full support for HPOS (custom order tables) and Cart/Checkout Blocks. WordPress and WooCommerce core files are not modified.
- EN passwords are stored encrypted in the database and are never echoed back to the form. The code contains no secrets.
- TLS 1.2 or newer, with certificate verification.
- Personal data stays in the store and in EN. WordPress privacy tools (export and erase) are supported.
- Source strings are in English. A complete Polish (pl_PL) translation is included.

## Out of scope for the first release

Pallet shipments, international services and customs declarations, tracking and customer notifications, automatic order status updates, posting registers, return addresses and return labels, OPNA (pickup from sender addresses) and complaints. The architecture leaves room to add these later.

## Requirements

- WordPress and WooCommerce versions currently supported by their vendors
- PHP 8.2 or newer, patched for CVE-2026-109618 and CVE-2026-109619 (8.2.35, 8.3.36, 8.4.27, 8.5.12 or later)
- PHP `soap` extension

## License

GPL-2.0-or-later
