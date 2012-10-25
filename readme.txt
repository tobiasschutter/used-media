=== Used Media ===
Contributors: codepress, tschutter, davidmosterd
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=ZDZRSYLQ4Z76J
Tags: plugins, wordpress, admin, column, columns, custom columns, custom fields, image, dashboard, sortable, filters, posts, media, users, pages, posttypes, manage columns, wp-admin
Requires at least: 3.1
Tested up to: 3.4.2
Stable tag: 0.1

See where all you media is being used througout your website. This plugin will find your media item if it is used inside the editor, as featured image or attached.

== Description ==

Do you want to know where your media is being used in your website? This plugin will scan your entire site for used media ( images etc. ). It will bundle this information and display it in the added Media Column called 'Used By'.

Now when you look up an image you will see at a glance where is has been used. 

It will find media items used in the Editor of a Post, if it is used as a Featured Image and even in Custom Fields.

Leave any feedback or questions in the Support section.

= Translations = 

If you like to contribute a language, please send them to <a href="mailto:info@codepress.nl">info@codepress.nl</a>.

**Related Links:**

* http://www.codepress.nl/plugins/codepress-admin-columns/

== Installation ==

1. Upload used-media to the /wp-content/plugins/ directory
2. Activate Used Media through the 'Plugins' menu in WordPress
3. Configure the plugin by going to the Used Media settings that appears under the Settings menu.

== Frequently Asked Questions ==

= How can I add my own custom fields to the scan? =

You can use the build in filter to add custom fields. Just add this piece of code to your
theme's  functions.php.

The custom field needs to contain a media library ID in order to work.

`
<?php
function cpum_custom_fields( $custom_field_keys )
{
	// edit here: fill in your custom field keys
	$custom_field_keys = array(
		'media_library_id',      // example key 1
		'attachment_id',         // example key 2
		'another_custom_field',  // example key 3
	);
	// stop editing
	
	return $custom_field_keys;
}
add_filter('cpum-custom-fields', 'cpum_custom_fields');
?>
`

== Screenshots ==

1. Settings page for Used Media.
2. Media Library Screen with added column.
3. Media Item with added information.

== Changelog ==

= 0.1 =

* Initial release.