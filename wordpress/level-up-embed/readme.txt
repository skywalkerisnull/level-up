=== Level Up Embed ===
Contributors: toowoombayoungchamber
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later

Embeds the Level Up landing page as an auto-resizing iframe and collects
waitlist signups into WordPress.

== Description ==

The Level Up landing page is published as a static page and embedded here. This
plugin does three things a static page cannot do on its own:

* Resizes the iframe to its content, so there is no inner scrollbar.
* Renders the signup form and stores addresses in WordPress. No third-party
  account, no API keys anywhere.
* Optionally serves the page with no theme header or navigation.

Signups are listed under Settings -> Level Up Signups and can be exported as
CSV to import into whatever you send email from.

Everything is configured under Settings -> Level Up.

== Installation ==

1. Plugins -> Add New -> Upload Plugin, choose the ZIP, activate.
2. Go to Settings -> Level Up.
3. Paste the URL of the published Level Up page.
4. Press "Create page" to make a page for it, or pick an existing one.
5. Choose a layout.
6. Optionally tick "Email me whenever someone signs up".

== Frequently Asked Questions ==

= Where do the email addresses go? =

Into WordPress, under Settings -> Level Up Signups. Only the address and the
date are stored — no IP address, no user agent.

= How do I get them into a mailing tool? =

Settings -> Level Up -> Export CSV, then import that file wherever you send
from. Signups are not pushed anywhere automatically, so nothing breaks quietly
if a provider changes its API or an API key expires.

= Does the embedded page need changing? =

No. The plugin appends the switches it understands to the URL.

= Can I place it manually? =

Yes, use the [level_up_embed] shortcode and leave "Show it on" unset.

= Are signups deleted if I remove the plugin? =

No. They are the mailing list and exist nowhere else, so uninstalling removes
only the settings. Delete the entries by hand if you want them gone.

== Changelog ==

= 1.2.2 =
* Hide the embedded page's footer when something is rendered below the frame.
* Keep the crew pop-up on screen: an auto-height frame has no viewport of its
  own, so position:fixed put it in the middle of the whole page.

= 1.2.1 =
* Scoped the form styles so block themes stop overriding the button and input.

= 1.2.0 =
* Signups are stored in WordPress and exportable as CSV.
* Removed the Mailjet integration and all API credential handling.
* "Create page" button makes the page for you and selects it.
* Optional email notification per signup.

= 1.1.0 =
* Settings screen: page URL, layout, host page, signup handling.
* Standalone layouts no longer need a theme template file.

= 1.0.0 =
* First version: shortcode, auto-resizing iframe, signup endpoint.
