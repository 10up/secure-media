=== Secure Media ===
Contributors:      10up, tlovett1
Tags:              AWS, S3, secure, private, media
Requires at least: 
Tested up to:      5.6
Requires PHP:      5.6
Stable tag:        1.0.5
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html

Store private media securely in WordPress.

== Description ==

This plugins stores media associated with non-public posts in S3 with private visibility. Image requests for private media are proxied through WordPress to ensure proper user capabilities. Once a post a published, all it's media is made public and transfered to the WordPress public uploads folder. Media uploaded outside of posts e.g. the media library are made private as well.

== Installation ==

1. Install the plugin via the plugin installer, either by searching for it or uploading a .ZIP file.
1. Activate the plugin.
1. Configure accesss to your AWS S3 bucket.
1. Use Secure Media and rejoice!

== Changelog ==
 
= 1.0.5 =
* Initial public release 🎉.
* **Added:** Plugin banner and icon assets (props [@McCallumDillon](https://github.com/McCallumDillon), [@cgoozen](https://profiles.wordpress.org/cgoozen/)).
* **Added:** Documentation and GitHub Action updates (props [@jeffpaul](https://profiles.wordpress.org/jeffpaul/), [@dinhtungdu](https://profiles.wordpress.org/dinhtungdu/)).
* **Changed:** Code spacing, documentation, translated strings, formatting, and other code cleanup tasks (props [@dkotter](https://profiles.wordpress.org/dkotter/)).
* **Security:** Bump `ini` from 1.3.5 to 1.3.8 (props [@dependabot](https://github.com/apps/dependabot)).
* **Security:** Update NPM packages for `axios` and `socket.io` to fix vulnerabilities (props [@joshuaabenazer](https://profiles.wordpress.org/joshuaabenazer/)).

= 1.0.4 =
* **Fixed:** Better S3 error logging (props [@tlovett1](https://profiles.wordpress.org/tlovett1/)).

= 1.0.3 =
* **Fixed:** Don't break old media and ensure new media has the correct visibility (props [@tlovett1](https://profiles.wordpress.org/tlovett1/)).
* **Fixed:** Create upload sub dir if it doesn't exist (props [@tlovett1](https://profiles.wordpress.org/tlovett1/)).
* **Fixed:** Fix public srcset urls (props [@tlovett1](https://profiles.wordpress.org/tlovett1/)).
* **Fixed:** Fix missing setting; only delete file if it exists (props [@tlovett1](https://profiles.wordpress.org/tlovett1/)).
* **Fixed:** Check if file exists before doing mkdir (props [@tlovett1](https://profiles.wordpress.org/tlovett1/)).

= 1.0.2 =
* **Fixed:** Set default bucket and make sure there's always an S3 bucket (props [@tlovett1](https://profiles.wordpress.org/tlovett1/)).
* **Fixed:** Assorted bugs (props [@tlovett1](https://profiles.wordpress.org/tlovett1/)).

= 1.0.1 =
* **Fixed:** Redirect single attachment page for private media if not authorized (props [@tlovett1](https://profiles.wordpress.org/tlovett1/)).
* **Fixed:** Assorted errors (props [@tlovett1](https://profiles.wordpress.org/tlovett1/)).

= 1.0.0 =
* Initial private release of Secure Media plugin.
