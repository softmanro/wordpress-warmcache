=== Warm Cache ===
Contributors: Ramon Fincken, Liviu Andreicut
Tags: cache, warm, keep, xml, sitemap, load, speed, quick, tag, w3tc, optimize, page cache, preload, google, pagespeed, webmaster, sitemap, generator, warmup, cold, expire, expired, nginx, varnish, microcaching, microcache
Requires at least: 3.5
Tested up to: 6.2
Stable tag: 4.1.0

Crawls your website-pages based on google XML sitemap. If you have a caching plugin this will keep your cache warm. Speeds up your site.

== Description ==

Crawls your website-pages based on any XML sitemap. If you have a caching plugin this will keep your cache warm. 
Speeds up your site.<br>
Compatible with following elements: < sitemap > < url ><br>
All urls in your sitemap will be visited by the plugin to keep the cache up to date.<br>
Will show average page load times and pages visited.<br>

Needs google XML sitemap to read the generated XML file.<br>
Needs a cronjob (wget or curl) to call the plugin. You need to setup the cronjob yourself! (Or ask your sysadmin to help you).<br>
* Coding by: <a href="https://www.mijnpress.nl">MijnPress.nl</a> <a href="https://mastodon.social/@ramonfincken">Mastodon profile</a> <a href="https://profiles.wordpress.org/ramon-fincken/">More plugins</a><br>
* Crawl script idea by <a href="http://blogs.tech-recipes.com/johnny/2006/09/17/handling-the-digg-effect-with-wordpress-caching/">http://blogs.tech-recipes.com/johnny/2006/09/17/handling-the-digg-effect-with-wordpress-caching/</a>

<br>Upgrade option: Save you all the hassle and use our <a href="https://www.autowarmcache.com/">Auto Warm cache service</a>

== Installation ==

1. Upload directory `warm-cache` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Visit Plugins menu to view your Warm cache options.
4. Run a cronjob. See the menu for the cronjob URL.<br>Run it as often as you like, preferably more often then your 
page cache expiration time. <br>Example: Page cache expiration = 1 hour, so set your cronjob at 30 minutes.
<br>Set it higher when you have lots of pages


== Frequently Asked Questions ==
= I have a BIG sitemap that takes over 5 seconds to load =
>  use a filter called 'http_request_timeout'

= I have multiple (language / domain) sitemaps, but I can only add one sitemap! =
> Use a sitemap index, pointing to all your (custom/sub)-sitemaps. Only add the sitemap main index file to the plugin. Do NOT have this in the custom file name:
warmcache-sitemap.xml OR warmcache. Use this for example: supersitemap.xml or my_cache_sitemap.xml

= How to disable logging completely (for very large sites)? =
> Set a define in your wp-config (at the top, newline, after <?php ) with name MP_WARM_CACHE_NO_LOGGING_AT_ALL and value "yes"
Example<br>
Please check https://plugins.trac.wordpress.org/browser/warm-cache/trunk/readme.txt#L38 DO not copy directly below due to the "curly quotes"<br>
define('MP_WARM_CACHE_NO_LOGGING_AT_ALL', 'yes');

= How to run a cronjob? =
> Ask your webhost how to set up a get call using wget or curl. See Installation for instructions howmany times you should call the cronjob.

= The plugin shows an error, it cannot read the XML sitemap =
> The fact that you are able to "see" the XML in your browser does not guarantee that PHP is able to read it too.<br> 
Consult your host about this, if that fails.
<br>https://wordpress.org/support/topic/error-with-google-xml-sitemaps/

= Sucuri / Audit log notifies about new posts beeing created =
> We use the hidden post type warmcache to store statistics. We also remove them upon each crawl.<br>
Feel free to ignore creation of posts with warmcache.<br>
See https://wordpress.org/support/topic/29-september-2016-update-causing-constant-post-update-notifications-from-secur/

= I have set up the cronjob but the stats table on the plugin page remains empty. =
> @see The plugin shows an error, it cannot read the XML sitemap

= How to override the 20 pages crawl limit =
> Set a define in your wp-config (at the top, newline, after <?php ) with name MP_WARM_CACHE_FILTER_LIMIT and value INTEGER
Example<br>
Please check https://plugins.trac.wordpress.org/browser/warm-cache/trunk/readme.txt#L58 DO not copy directly below due to the "curly quotes"<br>
define('MP_WARM_CACHE_FILTER_LIMIT', 25);

= I have a lot of questions and I want support where can I go? =

The support forums over here, drop me a toot to notify me of your support topic over here.<br>
I always check my toots, so mention my name with https://mastodon.social/@ramonfincken and your problem.

== Changelog ==
= 4.1.0 =
Added: merge 3.1.0 and 4.0.0 to account for the PHP fatal countable

= 4.0.0 =
Bugfix: PHP fatal on countable in the crawler

= 3.1.0 =
Added: Tranlations<br>
Added: Link to autowarmcache.com

= 3.0.0 =
Bugfix: PHP static notice

= 2.2.3 =
Added: Complete rewrite of the crawler, better compatibility with W3TC (permalink)<br>
Bugfix: Statistics<br>
Bugfix: PHP static notice


= 2.2.2 =
Bugfix: Fixed skipping the first item in sitemap(s). Thanks Jeff (@upekshapriya) for reporting<br>
Added: simplexml_load_string function check

= 2.2.1 =
Bugfix: PHP notices

= 2.2 =
Added: Option to disable logging completely (for very large sites) see FAQ how to do so

= 2.1 =
Bugfix: No gzip compression in ob_start filter<br>
Bugfix: Devision by zero fixed in wp-admin statistics page<br>
Changed: Added lock transient to prevent multiple warm cache calls. You can now call the warm cache every minute :)<br>
Added: Override of 20 pages crawl limit via define. See the FAQ on WordPress.org how to do that.

= 2.0.1 =
Bugfix: The crawl key was re-set on every view of the admin page<br>
Changed: Caching of results of XML sitemap check is now active


= 2.0 =
Added: Staggered crawl, so if you have thousands of posts, the crawl will walk/slide over those posts in groups of 20 each<br>
Changed: Deleted all transient options, in favour of custom post type warmcache storage

= 1.9.4 =
Added: Debug notices when a sitemap is incorrect

= 1.9.3 =
Needs re-work: Bugfix: Transients without expiration (if you have no external non-persistant storage your options table will grow), Props M. Bastian

= 1.9.2 =
Added: Toggle flush settings

= 1.9.1 =
Added: Transient check for correct syntax of sitemap
Added: Admin notices when sitemap is not present or currupt

= 1.9 =
Changed: Better handling of pre-checks<br>
Changed: Changed API key change message

= 1.8.1 =
Added: Flush to prevent loadbalancer/proxy timeout see https://wordpress.org/support/topic/needs-flush-to-write-buffers-to-prevent-timeouts

= 1.8 =
Added: Sitemap url override<br>
Changed: Info txt in plugin<br>
Changed: Refresh random token<br>
Added: Cron service link

= 1.7 =
Bugfix: Extra if/else for zero pages to fix x/0 errors. Thanks to khromov http://wordpress.org/support/topic/division-by-zero-2 http://wordpress.org/support/profile/khromov

= 1.6 =
Added: Support for sub-sitemaps using < sitemap > format (as used in Beta of Google XML sitemaps). Thanks to Pascal90.de!

= 1.1.2 =
Changed: Random password call as mentioned by swanzai http://wordpress.org/support/topic/plugin-warm-cache-how-to-call-this-plugin-correctly

= 1.1 =
First release

== Screenshots ==

1. Details
<a href="http://s.wordpress.org/extend/plugins/warm-cache/screenshot-1.png">Fullscreen Screenshot 1</a><br>
2. Overview
<a href="http://s.wordpress.org/extend/plugins/warm-cache/screenshot-2.png">Fullscreen Screenshot 2</a><br>
