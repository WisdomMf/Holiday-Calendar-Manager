=== Holiday Calendar ===
Contributors: wisdommf
Tags: calendar, holidays, weekends, shortcode
Requires at least: 5.3
Tested up to: 6.5
Stable tag: 1.0.20
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An admin-editable interactive calendar that highlights weekends and any dates you mark.

== Description ==

Holiday Calendar lets a site admin mark arbitrary dates (each with a label and a
colour) from a simple settings screen. Weekends are detected and highlighted
automatically. Display the calendar anywhere with the [holiday_calendar]
shortcode. The front-end calendar has month navigation, hover tooltips, and a
click-to-view detail bar, with no JavaScript dependencies.

== Usage ==

1. Go to "Holiday Calendar" in the admin menu.
2. Add dates (date + label + colour) and choose which days count as the weekend.
3. Put [holiday_calendar] into any page or post.

== Deployment ==

After uploading or updating this plugin on a live site:

* Re-upload the full plugin folder so `assets/calendar.css` and `assets/calendar.js` are present.
* Clear any page, asset, and CDN cache (WP Rocket, LiteSpeed, Cloudflare, etc.).
* If marked dates show on localhost but not live, export or re-enter dates in **Holiday Calendar** admin — they live in the `hc_dates` option and are not copied with plugin files alone.

If the calendar appears as plain unstyled text, open the browser Network tab and confirm `calendar.css` returns HTTP 200 from `/wp-content/plugins/holiday-calendar/assets/calendar.css`.

== Changelog ==

= 1.0.1 =
* Enqueue calendar CSS in the document head (fixes unstyled calendar on cached/optimized live sites).
* Use filemtime-based asset versions for reliable cache busting after deploy.

= 1.0.0 =
* Initial release.
