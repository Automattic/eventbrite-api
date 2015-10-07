=== Eventbrite API ===
Contributors: jkudish, kwight
Tags: eventbrite, events, api, WordPress.com
Requires at least: 3.8
Tested up to: 4.3
Stable tag: 1.0.10
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Display Eventbrite events right in your WordPress site. Developers get easy-to-use tools for creating powerful, in-depth Eventbrite integrations.

== Description ==

The Eventbrite API plugin brings the power of Eventbrite to WordPress, for both users and developers.

By connecting to your [Eventbrite account](http://eventbrite.com/), events can be displayed directly on your WordPress.org website, complete with event details and ticket information. Events will blend in with the design of any theme, and events can be filtered by organizer or venue, just like post archives.

As a theme or plugin developer, you get simple-to-use tools for making any theme or plugin Eventbrite-compatible. Use the helper functions to remove the complexity and heavy-lifting of API calls, and take advantage of assorted template tags for displaying various event information.

*Eventbrite logo used by permission. Banner photo by [Daniel Robert Dinu](http://www.concertphotography.ro/), licensed under [CC0](http://creativecommons.org/choose/zero/).*

== Installation ==

The Eventbrite API plugin also requires the [Keyring plugin](https://wordpress.org/plugins/keyring/), which is used for managing the user's connection to eventbrite.com. Keyring needs to be installed, activated, and using a connection to eventbrite.com for any events to display on the website.

If the eventbrite API plugin is installed but missing Keyring or an eventbrite.com connection, admin notices will prompt the user with helpful links.

1. Upload `eventbrite-api.zip` or search for it from the Plugins > Add New admin page.
2. Activate the plugin through the Plugins menu.
3. Repeat these steps for the [Keyring plugin](https://wordpress.org/plugins/keyring/).
4. [Assign](http://codex.wordpress.org/Page_Templates#Selecting_a_Page_Template) the Eventbrite Events page template to a page. This will be the page that shows your Eventbrite events.

For more detailed instructions, see the Eventbrite API [user assistance page](http://automattic.github.io/eventbrite-api/users.html).

== Frequently Asked Questions ==

= Are there more detailed instructions for Keyring and getting connected to Eventbrite? =

Yes! Check out the [user assistance page](http://automattic.github.io/eventbrite-api/users.html) on the [Eventbrite API website](http://automattic.github.io/eventbrite-api/), and [post to the forums](https://wordpress.org/support/plugin/eventbrite-api/) if you need any further help.

= Events don't quite look like the rest of the theme – how can I fix it? =

While a theme doesn't need to know about the plugin to display events, it's always best if the theme developer optimizes their theme for Eventbrite. You can post on the [theme's forum](https://wordpress.org/support/) and send them [this link](https://github.com/Automattic/eventbrite-api). You can also send an email to themes@wordpress.com and we'll see what we can do to help. The following themes have already been optimized:

* Twenty Fifteen
* Twenty Fourteen
* Twenty Thirteen
* Twenty Twelve
* Twenty Eleven
* Twenty Ten
* [Bosco](https://wordpress.org/themes/bosco/)
* [Chunk](https://wordpress.org/themes/chunk/)
* [Coraline](https://wordpress.org/themes/coraline/)
* [Edin](https://wordpress.org/themes/edin/)
* [Fictive](https://wordpress.org/themes/fictive/)
* [Goran](https://wordpress.org/themes/goran/)
* [Ryu](https://wordpress.org/themes/ryu/)
* [Sketch](https://wordpress.org/themes/sketch/)
* [Sidekick](https://wordpress.org/themes/sidekick/)
* [Singl](https://wordpress.org/themes/singl/)
* [Superhero](https://wordpress.org/themes/superhero/)
* [Sorbet](https://wordpress.org/themes/sorbet/)
* [Tonal](https://wordpress.org/themes/tonal/)
* [Writr](https://wordpress.org/themes/writr/)

= I'm a theme developer; how can I make my theme Eventbrite-optimized? =

Assuming your theme is based on [Underscores](http://underscores.me/), most of the work is already done for you. Just load the theme, and compare your markup to that of the plugin's [included templates](https://github.com/Automattic/eventbrite-api/tree/master/tmpl). Make your own copies, adjusting the markup as needed, and then assign your templates in an `add_theme_support` call. Most themes can be done in under ten minutes. More details can be found at the [Eventbrite API GitHub repo](https://github.com/Automattic/eventbrite-api/).

= What Eventbrite endpoints are supported? =

The following endpoints are currently supported, with more on the way. Open an issue on GitHub to request support for others.

* `user_owned_events`: [Eventbrite documentation](http://developer.eventbrite.com/docs/user-owned-events/).
* `event_details`: [Eventbrite documentation](http://developer.eventbrite.com/docs/event-details/).
* `event_search`: [Eventbrite documentation](http://developer.eventbrite.com/docs/event-search/).

= Where can I get detailed documentation for working with the plugin? =

All development for Eventbrite API plugin is done through the [GitHub repo](https://github.com/Automattic/eventbrite-api/), and detailed documentation can be found on the repo's [GitHub page](http://automattic.github.io/eventbrite-api/developers.html).

= Who made this plugin? =

This plugin was developed by [Automattic](http://automattic.com/), in direct partnership with [Eventbrite](http://eventbrite.com). The Eventbrite name and logo are used by permission.

== Changelog ==

= 1.0.10 - October 7, 2015 =
* Fix bug from when `Eventbrite_Query` args conflict with the loading URL.
* Fix bugs involving organizer events not owned by the user.
* Add filters for transient names and API responses.
* Fix PHP warnings when certain ticket information is not available.

= 1.0.9 - October 4, 2015 =
* Increase timeout for Eventbrite API calls.
* Add a filter for expansions.

= 1.0.8 - August 18, 2015 =
* Fix bug where Eventbrite would not load for logged-in users, other than the user that created the Eventbrite connection.

= 1.0.7 - August 9, 2015 =
* Add support for the `nopaging` query parameter (props @otterly).
* Add support for the `category_id`, `subcategory_id`, and `format_id` query parameters (props @moust).

= 1.0.6 - August 2, 2015 =
* Avoid caching and filtering on invalid API responses.
* Improve rewrite rules flushing on page saves and template changes.

= 1.0.5 - April 23, 2015 =
* Add expansions, to handle breaking changes to the API planned for May 13, 2015.

= 1.0.4 - April 2, 2015 =
* Update to logo handling; Eventbrite announced a sudden breaking change to happen April 7th, 2015.
* Fix bug affecting detection of logos in events.

= 1.0.3 - January 31, 2015 =
* Display Edit link only if user is logged in with appropriate capabilities.
* Only output event logo markup if one exists. Corrects broken image icon in Firefox.

= 1.0.2 - January 21, 2015 =
* Add filter for transient expiry.

= 1.0.1 - December 12, 2014 =
* Add an anonymous referral code to OAuth connections so Eventbrite can gauge adoption on WordPress.

= 1.0 - December 1, 2014 =
* Initial release.
