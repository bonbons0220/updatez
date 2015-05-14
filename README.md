idies-content-tracker
===============

This plugin displays tracking information on the frontend of a wordpress site.


The tracking data will only be displayed if the constant WP_ENV is set to 'development'. Set this in the wp-config.php file.

The plugin depends on installation and activation of the WordPress-Creative-Kit Plugin and needs the Pro version (which allows for the field type userid).

The plugin looks for meta data associated with a post, called 'Tracking' (slug: tracking), with the fields
Editor, Reviewer, Status and Comments. Status must have one of the following values: 
Needs Update,Update in Progress,Needs Review,Update Completed,Do not Publish.
Editor and Reviewer are wordpress users. Other fields can be added and will be listed in the tracking info on each page.

The most recent panel is displayed in context sensitive colors depending on the value of Status.