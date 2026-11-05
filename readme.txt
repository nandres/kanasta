=== Kanasta – Chat Orders for WooCommerce ===
Contributors: unandre
Tags: whatsapp, woocommerce, order, checkout, chat
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Customers place real WooCommerce orders and send them to you on WhatsApp in one tap. Works with the block checkout.

== Description ==

In many countries people prefer to buy through WhatsApp. Kanasta lets them do it **without losing your WooCommerce order management**: the order is created in WooCommerce (stock, statuses, emails, reports) and the customer lands in WhatsApp with every detail already written.

= What you get for free =

* **"Order via WhatsApp" checkout method** – the order is created in WooCommerce and the customer is sent to WhatsApp with the products, totals, shipping, name, phone, address and notes.
* **Works with the new block checkout and the classic checkout.**
* **WhatsApp button on product pages** – sends the product, chosen options (variations) and quantity.
* **WhatsApp button on the cart page** – sends the whole cart.
* **Floating WhatsApp chat button** on every page – visitors ask you anything and the message includes the page they were on.
* **Editable messages** with tags like `{order_number}`, `{items}`, `{total}`, `{customer_name}`, `{shipping_address}`. Empty lines are removed automatically.
* **Order received page and email** include a "Send my order via WhatsApp" button, and WhatsApp can open automatically.
* Choose the order status: on hold (reserves stock), pending payment or processing.
* Translated to Spanish and Portuguese.
* HPOS compatible.

= Kanasta Pro =

* **Sales team**: several WhatsApp numbers with automatic rotation (in turns or random). Each order keeps its agent.
* **Business hours**: show a "we are closed" notice with the next opening time, or hide the WhatsApp options while closed.
* **Messages to customers in one click** from the order screen: order confirmed, payment reminder, on its way, ready for pickup.

= Trademarks =

WhatsApp is a trademark of WhatsApp LLC and WooCommerce is a trademark of Automattic Inc. Kanasta is an independent plugin and is not affiliated with, endorsed or sponsored by either of them.

== Installation ==

1. Install and activate WooCommerce and Kanasta.
2. Go to **WooCommerce → Kanasta** and enter your WhatsApp number with the country code (for example 595981123456).
3. Check **WooCommerce → Settings → Payments** to rename or reorder the "Order via WhatsApp" method.

== Frequently Asked Questions ==

= Does it need the WhatsApp Business API? =

No. Kanasta uses WhatsApp links (wa.me), so it works with regular WhatsApp and WhatsApp Business. There is nothing to connect or pay to Meta.

= Is the order really created in WooCommerce? =

Yes. With "Order via WhatsApp" the order is created like with any payment method, so stock, emails, statuses and reports work as usual. The payment and delivery are agreed in the chat; mark the order as processing or completed when it is paid.

= Does it work with the block-based checkout? =

Yes, with both the Checkout block and the classic `[woocommerce_checkout]` shortcode, and with the Cart block and the classic cart.

= What if the customer does not send the message? =

The order is kept "on hold" (or the status you choose), and the order received page and the order email still show the WhatsApp button.

= Can I change the messages? =

Yes, in WooCommerce → Kanasta. Use *asterisks* for bold text in WhatsApp.

== Screenshots ==

1. WhatsApp button on the product page: sends the chosen options and quantity.
2. "Order via WhatsApp" in the block checkout.
3. Settings screen: number, messages and buttons.
4. WhatsApp box on the order screen with one-click customer messages (Pro).

== Changelog ==

= 1.2.0 =
* First public release.
