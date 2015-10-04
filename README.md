# Eventbrite API WordPress Plugin

The Eventbrite API plugin brings the power of Eventbrite to WordPress, for both users and developers.

## Overview

#### Users

By connecting to your [Eventbrite account](http://eventbrite.com/), events can be displayed directly on your WordPress.org website, complete with event details and ticket information. Events will blend in with the design of any theme, and events can be filtered by organizer or venue, just like post archives.

#### Developers

As a developer, you get simple-to-use tools for integrating Eventbrite into your themes or plugins. Use the helper functions to remove the complexity and heavy-lifting of API calls, and take advantage of assorted template tags for displaying various event information.

## Requirements

#### Keyring

[Keyring](https://wordpress.org/plugins/keyring/) is required for the Eventbrite API plugin to work. Once it's installed with an active connection to Eventbrite, the Eventbrite API plugin will be able to display events.

If needed, admin notices will give helpful links and prompting to get Keyring and the Eventbrite API plugin up and running. These notices will appear on the Dashboard, PLugins, Tools, and Settings admin pages until issues are resolved.

## Classes

![Eventbrite API Classes Overview](https://raw.githubusercontent.com/Automattic/eventbrite-api/master/eventbrite-api-overview.png)


**`Eventbrite_API`**

* makes calls to the API
* handles option for the token
* defines supported endpoints


**`Eventbrite_Requirements`**

* ensures Keyring is installed, activated
* ensures an active Eventbrite connection
* provides admin notification prompts


**`Eventbrite_Manager`**

* uses `Eventbrite_API` to make API calls
* validates all query args and values to ensure no wasted API calls
* handles storing and returning transients
* prepares API results into the format needed by `Eventbrite_Event`


**`Eventbrite_Query`**

* like `WP_Query` for Eventbrite events (it extends `WP_Query`)
* creates a secondary loop
* supported arguments, passed as an array
  * `display_private`: (*boolean*) Include user events marked as Private. Default is `false`.
  * `limit`: (*integer*) Return a maximum number of results.
  * `organizer_id`: (*integer*) Return only events for a certain organizer.
  * `p`: (*integer*) Get a single event.
  * `post__not_in`: (*array*) Remove certain events by ID from the results.
  * `venue_id`: (*integer*) Return only events for a certain venue.
  * `category_id`: (*integer*) Return only events for a certain category.
  * `subcategory_id`: (*integer*) Return only events for a certain subcategory.
  * `format_id`: (*integer*) Return only events for a certain format.


**`Eventbrite_Event`**

* like `WP_Post` for Eventbrite events
* does not extend `WP_Post`, as that class is marked `final`
* object properties
  * `ID` (*integer*)
  * `post_title` (*string*)
  * `post_content` (*string*)
  * `post_date` (*string*)
  * `post_date_gmt` (*string*)
  * `url` (*string*)
  * `logo_url` (*string*)
  * `start` (*object*)
  * `end` (*object*)
  * `organizer` (*object*)
  * `venue` (*object*)
  * `public` (*boolean*)
  * `tickets` (*object*)

**`Eventbrite_Templates`**

* extends the Template Hierarchy for handling Eventbrite templates
* adds rewrite rules for templates

## Functions

#### Helper Functions

**`eventbrite_get_events( $params, $force )`**

* Get user-owned events (both public and private are returned by default).
* `$params`: (*array, optional*) [Accepted parameters and values](http://developer.eventbrite.com/docs/user-owned-events/)
* `$force`: (*boolean, optional*) Force a fresh API call, ignoring any available transient. Default is `false`.

**`eventbrite_get_event( $id, $force )`**

* Retrieve a single user-owned event.
* `$id`: (*integer, required*) Eventbrite event id
* `$force`: (*boolean, optional*) Force a fresh API call, ignoring any available transient. Default is `false`.

**`eventbrite_search( $params, $force )`**

* Search all public Eventbrite events.
* `$params`: (*array, optional*) [Accepted parameters and values](http://developer.eventbrite.com/docs/event-search/). Note that not passing any parameters, while technically valid, will usually result in timeout errors. Limiting the search to user-owned events can be done by passing `user.id => Eventbrite_API::$instance->get_token()->get_meta( 'user_id' )`.
* `$force`: (*boolean, optional*) Force a fresh API call, ignoring any available transient. Default is `false`.

#### Template Tags

**`eventbrite_is_single( $query )`**

* Determine if we on an Eventbrite single view.
* `$query`: (*object, optional*) Accepts an `Eventbrite_Query` object.
* Returns: (*boolean*) `true` if the passed or current query is for an event single view, `false` otherwise.

**`eventbrite_is_event( $post )`**

* Check if a given or current post is an Eventbrite event.
* `$post`: (*object or integer, optional*) Accepts a post/event object, or an ID.
* Returns: (*boolean*) `true` if it's an Eventbrite_Event object or the ID of a valid event, `false` otherwise.

**`eventbrite_paging_nav( $events )`**

* Output pagination HTML for the index views.
* Based on `wp_paginate_links()`.
* `$events`: (*object, required*) Requires a valid `Eventbrite_Query` object. This avoids having to mess with the `$wp_query` object.

**`eventbrite_event_meta()`**

* Outputs meta information for an event: event time, venue, organizer, and a Details link to the event single view.
* On the single view, the Details link goes to the event's page on eventbrite.com.

**`eventbrite_event_time()`**

* Output an event's local time, with date, starting, and end time.
* Example: `December 8 2014, 7:00 PM - 10:00 PM`

**`eventbrite_event_venue()`**

* Access the current event's venue properties: `address`, `resource_uri`, `id`, `name`, `latitude`, `longitude`

**`eventbrite_event_organizer()`**

* Access the current event's organizer properties: `description`, `logo`, `resource_uri`, `id`, `name`, `url`, `num_past_events`, `num_future_events`

**`eventbrite_event_category()`**

* Access the current event's category properties: `resource_uri`, `id`, `name`, `name_localized`, `short_name`, `short_name_localized`

**`eventbrite_event_subcategory()`**

* Access the current event's subcategory properties: `resource_uri`, `id`, `name`, `name_localized`, `short_name`, `short_name_localized`

**`eventbrite_event_format()`**

* Access the current event's format properties: `resource_uri`, `id`, `name`, `name_localized`, `short_name`, `short_name_localized`

**`eventbrite_event_start()`**

* Access the current event's start time properties: `timezone`, `local`, `utc`

**`eventbrite_event_end()`**

* Access the current event's end time properties: `timezone`, `local`, `utc`

**`eventbrite_ticket_form_widget()`**

* Output ticket information by `<iframe>` with eventbrite.com's ticket form widget.
* [Eventbrite widgets documentation](http://help.eventbrite.com/customer/en_us/portal/articles/428470-how-to-sell-eventbrite-tickets-registrations-on-your-website-using-embeddable-widgets)

**`eventbrite_ticket_form_widget_height()`**

* Calculates what height an event's ticket form `<iframe>` should be (pretty rough).
* Height is calculated with a rough assortment of variables; [see here for details](https://github.com/Automattic/eventbrite-api/blob/master/inc/functions.php#L452).

**`eventbrite_event_eb_url()`**

* Get the URL to the current event on eventbrite.com.
* Returns: (*string*) The eventbrite.com URL

**`eventbrite_is_multiday_event()`**

* Checks if the current event spans two or more calendar days (based on UTC time).
* Returns: (*boolean*) `true` if the date is different for the start and end times, `false` if they're the same.


## Filters

**`eventbrite_templates`**

* Adjust the array of valid Eventbrite templates, used for determining body classes.

**`eventbrite_meta_separator`**

* Define the markup used to separate event meta.
* Default: `<span class="sep"> &middot; </span>`

**`eventbrite_event_meta`**

* Filter the final HTML for an event's meta info.

**`eventbrite_paginate_links_args`**

* Adjust the arguments passed to the `paginate_links()` template tag (used by `eventbrite_paging_nav()`).

**`eventbrite_event_eb_url`**

* Filter an event's URL to its eventbrite.com page.

**`eventbrite_event_venue`**

* Modify the current event's venue properties (see template tag above).

**`eventbrite_event_organizer`**

* Modify the current event's organizer properties (see template tag above).

**`eventbrite_event_start`**

* Modify the current event's start time properties (see template tag above).

**`eventbrite_event_end`**

* Modify the current event's end time properties (see template tag above).

**`eventbrite_ticket_form_widget`**

* Filter the final HTML for the ticket form widgets.

**`eventbrite_ticket_form_widget_height`**

* Adjust the `<iframe>` height used when outputting a ticket form widget.

**`eventbrite_api_expansions`**

* Adjust the [expansions](https://www.eventbrite.com/developer/v3/reference/expansions/) requested on all API calls to Eventbrite.
