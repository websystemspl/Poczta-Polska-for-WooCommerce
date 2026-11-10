=== Poczta Polska for WooCommerce ===
Contributors: webSystems
Tags: poczta polska, pocztex, shipping, pickup point, cash on delivery
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pocztex and eCommerce shipping with the Poczta Polska pickup point map, cash on delivery and shipments in Elektroniczny Nadawca.

== Description ==

Poczta Polska for WooCommerce connects your store with Elektroniczny Nadawca (EN), the Poczta Polska shipping system.

Customers choose Poczta Polska delivery at checkout. Store managers create shipments, print labels and send them to the post office straight from the WordPress admin, without retyping order data into EN.

The store talks to EN directly over the EN WebAPI. There is no intermediate server, no license key and no telemetry.

= Shipping methods =

* **Pocztex Kurier**: door-to-door courier delivery.
* **Pocztex PUNKT**: delivery to a post office or a partner pickup point (for example Żabka), chosen on the Poczta Polska map.
* **Pocztex AUTOMAT**: delivery to a parcel locker, chosen on the map.
* **Przesyłka eCommerce**: registered letter up to 2000 g, without cash on delivery.

Each method is added to WooCommerce shipping zones like any other method, with its own title, price, free shipping threshold, weight limit, contract card and parcel format. You can add a method many times, for example two courier options with different prices. The methods are offered for addresses in Poland only.

= Checkout =

* Works with the Cart and Checkout blocks and with the classic `[woocommerce_checkout]` shortcode.
* The official Poczta Polska map for choosing a pickup point or a parcel locker, with a search over a local copy of the points when the map cannot load.
* Server-side validation: the order cannot be placed without a point, with a point of the wrong type, or with cash on delivery at a point that does not accept it or above its limit.
* Cash on delivery is hidden for the eCommerce letter.
* The chosen point is shown in the order confirmation, the customer emails, "My account" and the order screen, where the store manager can change it.

= Shipments =

* Create a shipment from an order with one click, or for many orders at once from the orders list.
* Change the service, format, number of parcels, weight, phone or pickup point before the label is printed.
* Labels as PDF, one file for many shipments.
* Buffers per day, contract card and posting office; move shipments between buffers, change the posting date and send a buffer to the post office.
* Order a courier pickup.
* Tracking numbers in the orders list and the order screen.
* Protection against duplicate shipments after a lost connection.

= Administration =

* Separate WebAPI credentials for the test (INT) and the production environment, with a connection test. Passwords are stored encrypted.
* A warning before the EN password expires.
* Contract cards, posting offices and the sender profile loaded from EN.
* Default parcel settings, cash on delivery bank account and transfer title.
* Logs in WooCommerce → Status → Logs with personal data masked, and an audit log of administrative changes.

= Requirements =

* WooCommerce with High-Performance Order Storage or the legacy order storage. Both are supported.
* PHP 8.2 or newer with the SOAP extension.
* An agreement with Poczta Polska and an Elektroniczny Nadawca account with WebAPI access.

== Installation ==

1. Install and activate WooCommerce.
2. Install the plugin from the WordPress plugin directory, or upload it to the `/wp-content/plugins/` directory, and activate it.
3. Recommended: before entering any password, add a random key to `wp-config.php`, for example `define( 'PPWC_ENCRYPTION_KEY', '...' );` with at least 32 random characters. The plugin uses it to encrypt the Elektroniczny Nadawca passwords. Without it the WordPress keys and salts are used, and changing them invalidates the stored passwords.
4. Go to **Poczta Polska → Settings** and enter the Elektroniczny Nadawca WebAPI login and password. Start with the test environment (INT), save and click "Test the test connection".
5. On the **Contract and posting** tab choose the contract card, the posting office and the sender profile.
6. Fill in the **Pickup address** (used by the courier), the **Shipment defaults** and, if you use cash on delivery, the bank account.
7. Add the Poczta Polska methods to a shipping zone in **WooCommerce → Settings → Shipping**.
8. When everything works in the test environment, switch the plugin to production.

== Frequently Asked Questions ==

= Do I need an agreement with Poczta Polska? =

Yes. The plugin uses your Elektroniczny Nadawca account. Prices, services and the contract card come from your agreement.

= Where do I get the WebAPI login and password? =

From Poczta Polska, together with access to Elektroniczny Nadawca. The test (INT) and the production environment have separate credentials.

= How are the Elektroniczny Nadawca passwords stored? =

Encrypted with libsodium. The key comes from the `PPWC_ENCRYPTION_KEY` constant in `wp-config.php`, or from the WordPress keys and salts when the constant is not set. Set the constant before entering the passwords: changing the WordPress salts (or the constant) invalidates the stored passwords and you have to enter them again.

= Why is the eCommerce letter not offered for some carts? =

It is limited to 2000 g and needs the weight of every product. The plugin warns you about products without a weight. Pocztex methods use the default weight from the settings for such products.

= Why is cash on delivery not available? =

Cash on delivery is available with Pocztex only. For PUNKT and AUTOMAT the chosen point must accept it, and the order total must not exceed the point limit.

= Can I change the templates? =

Yes. Copy a file from the plugin `templates/` folder to `your-theme/poczta-polska-for-woocommerce/` and edit the copy.

= What happens to my data when I delete the plugin? =

Settings, the pickup point copy and scheduled tasks are always deleted. Shipments, buffers and the audit log are kept unless you enable "Delete all data on uninstall" on the **Logs and data** tab, because tracking numbers belong to the order history.

== External services ==

This plugin connects to two services of Poczta Polska S.A. Both are needed for its main features.

= Elektroniczny Nadawca WebAPI =

Elektroniczny Nadawca is the Poczta Polska shipping system. The plugin uses its WebAPI to create and manage shipments.

* **When:** when a store manager tests the connection, creates, edits, moves or deletes shipments, downloads labels, sends a buffer or orders a courier; once a day to copy the list of pickup points and to check when the EN password expires; at checkout, only when a chosen pickup point is missing from the local copy.
* **What is sent:** the WebAPI login and password; the sender profile and pickup address; for each shipment the recipient name, delivery address, phone number, email address, pickup point, parcel weight and format, and for cash on delivery the amount, bank account and transfer title.
* **Where:** `https://e-nadawca.api.poczta-polska.pl/websrv/` (production) or `https://int-e-nadawca.api.poczta-polska.pl/websrv/` (test environment), depending on the settings.

Poczta Polska terms of service: https://www.poczta-polska.pl/regulaminy/
Poczta Polska privacy policy: https://www.poczta-polska.pl/polityka-prywatnosci/

= Poczta Polska pickup point map =

The map lets customers choose a pickup point or a parcel locker. Its script is loaded from `https://mapa.ecommerce.poczta-polska.pl/widget/scripts/ppwidget.js`, as Poczta Polska requires.

* **When:** the script is loaded when the checkout page opens (also before a Poczta Polska method is chosen), and in the admin on the order screen and the plugin settings. The map itself and its connections start only when the customer or the shop manager opens it.
* **What is sent:** the browser connects to Poczta Polska, which receives its IP address. To centre the map, the delivery address from the checkout form (street, postcode and city) is passed to the map, together with any address searched on the map. Nothing else is sent to the map service.

Poczta Polska terms of service: https://www.poczta-polska.pl/regulaminy/
Poczta Polska privacy policy: https://www.poczta-polska.pl/polityka-prywatnosci/

== Privacy ==

The plugin adds a suggested paragraph to **Settings → Privacy → Policy Guide**. It supports the WordPress personal data export and erasure tools: the pickup point and tracking numbers of an order are exported, and the pickup point is erased with the order data. Recipient data stays in the order; the plugin keeps only phone numbers that the store corrected for a shipment, and erases them with the order data.

== Changelog ==

= 0.2.3 =
* Label PDF downloads send the `X-Content-Type-Options: nosniff` header.
* Installation steps recommend setting `PPWC_ENCRYPTION_KEY` in `wp-config.php` before entering the passwords.

= 0.2.2 =
* The limit of Elektroniczny Nadawca lookups for pickup points missing from the local copy applies per visitor address.
* The Buffers screen no longer contacts Elektroniczny Nadawca when opened; the check for buffers deleted outside the plugin runs from a button.
* A password value with the `ppwc1:` prefix sent from the settings form is kept only when it decrypts.
* FAQ: how the passwords are stored and why to set `PPWC_ENCRYPTION_KEY`.

= 0.2.1 =
* The Poczta Polska and Pocztex logos are no longer bundled or shown.
* The warning about an unpatched PHP version appears only on the dashboard, the plugins list and the WooCommerce and plugin screens.
* The daily pickup point refresh is not scheduled twice.
* External services section states when the map script is loaded.

= 0.2.0 =
* Settings screen: Elektroniczny Nadawca connection per environment (test and production) with encrypted passwords and a connection test, contract card and posting office, sender profile, shipment defaults, cash on delivery, logs and audit log.
* Shipping methods Pocztex Kurier, Pocztex PUNKT, Pocztex AUTOMAT and Przesyłka eCommerce for shipping zones, with price, free shipping threshold, weight limit, contract card per environment and parcel format.
* Poczta Polska pickup point map in the block and the classic checkout, with a fallback search in the local copy of points; the chosen point is validated on the server and saved in the order, shown on the order page, in emails and in the admin.
* Cash on delivery only for Pocztex and only in points that accept it, within the point's limit.
* Shipments in Elektroniczny Nadawca from the order screen and in bulk: labels, buffers per posting office, sending to the post office, envelope status, courier orders.
* Shipment changes before the label: service, format, parcels, weight, mobile and landline, pickup point on the map, posting office.
* Tracking number column in the orders list, privacy tools, security hardening of public endpoints.

= 0.1.0 =
* Plugin skeleton: requirements check, WooCommerce HPOS and Cart/Checkout Blocks compatibility.
