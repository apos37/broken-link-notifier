=== Broken Link Notifier ===
Contributors: apos37
Donate link: https://paypal.com/donate/?business=3XHJUEHGTMK3N
Tags: broken, link, links, checker, notify
Requires at least: 5.9.0
Tested up to: 6.5.3
Requires PHP: 7.4
Stable tag: 1.0.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Get notifications when a visitor loads a page with broken links

== Description ==
The "Broken Link Notifier" WordPress plugin is a vigilant guardian for your website's links, monitoring and alerting you to broken or dead links as users visit your site. This ensures a seamless user experience and helps prevent search engine ranking penalties. Unlike other broken link checker plugins that can cause performance and timeout issues with full site scans, this plugin focuses on notification, making it a great complement to offsite services that handle full site scans.

This plugin:

* Scans content when users visit a page after the page fully loads, preventing performance lag
* Identifies broken links, including 404 errors, timeouts, images, and embedded YouTube videos
* Notifies you via dashboard notifications, email, Discord, and/or Microsoft Teams
* Provides a list of broken links for easy review and correction

With "Broken Link Notifier", you can:

* Ensure accurate and functional links for your users
* Prevent search engines from indexing broken links
* Maintain a professional and trustworthy website image
* Save time and effort in manual link checking
* Scan multiple pages at a time on the back-end from your WP List Tables

This plugin is a must-have for website owners, developers, and SEO enthusiasts who want to guarantee a smooth and error-free browsing experience for their audience!

== Installation ==
1. Install the plugin from your website's plugin directory, or upload the plugin to your plugins folder. 
2. Activate it.
3. Go to `Broken Link Notifier > Settings` in your admin menu.
4. Update your notification method(s) and post types.
5. Go to `Broken Link Notifier > Omitted Pages`, and add any pages that you don't want to scan, such as pages you know won't have any links on them. This will speed up the multi-scan option.
5. Page load scans are enabled automatically, so it's recommended that you test it out by deliberately making some broken links on a test page and then visiting the page. The results should show up on the `Broken Link Notifier > Results` page, and notify you if you have enabled email, Discord, or Microsoft Teams notifications. Reloading the page will not submit them twice. For testing, you should delete them from the results so they get reported again.
6. It is suggested to run a Multi-Scan on each of your public-facing post types to quickly -see if there are any broken links before others encounter them. Also to omit some links that will be reported as false positives. You can omit individual links quickly from the results, or you can go to `Broken Link Notifier > Omitted Links` to add a domain with a wildcard (*), which will omit all links starting with that domain. See screenshots for examples.
7. If you have any questions, please reach out to me on my [Discord support server](https://discord.gg/3HnzNEJVnR). I am happy to assist you or fix any issues you might have with the plugin.

== Frequently Asked Questions == 
= Where can I request features and get further support? =
Join my [Discord support server](https://discord.gg/3HnzNEJVnR)

= Why do some links show as broken when they are not? =
If the link works fine and it's still being flagged as broken, then it is either redirecting to another page or there is an issue with the page's response headers and there's nothing we can do about it. If it is a redirect on your own site due to permalink modification, then it's better to fix the link instead of unnecessarily redirecting. You may use the Omit option to omit false positives from future scans as well. If you are seeing a pattern with a multiple links from the same domain, you can go to `Broken Link Notifier > Omitted Links` to add a domain with a wildcard (*), which will omit all links starting with that domain.

= What causes a link to give a warning? =
Warnings mean the link was found, but they may be unsecure or slow to respond. If you are getting too many warnings due to timeouts, try increasing your timeout in Settings. This will just result in longer wait times, but with more accuracy. You can also disable warnings if you have too many of them.

= What is status code 666? =
A status code of `666` is a code we use to force invalid URL `code 0` to be a broken link in case warnings are disabled. It is not an official status code.

= Can I omit links and pages from scans? =
Yes, you can omit links from being checked for validity by using the "Omit" link in the scan results, or by entering them in manually under Omitted Links. Likewise, you can omit pages from being scanned from the results page or Omitted Pages. Wildcards (*) are accepted.

= When I click on "Find On Page," I cannot find the link. Where is it? =
Sometimes links are hidden with CSS or inside modals/popups. To find hidden links, go to the page and either open your developer console or view the page source and search for the link. This will show you where it is and which element to look in. Then you can edit the page accordingly. This is more advanced and may require some assistance, so feel free to reach out to me for help.

= Why does the dev console show more links that what is scanned on the Multi-Scan? =
The Multi-Scan link count does not include links that are filtered out from the pre-check.

= What pre-checks are used to filter out broken links? =
We skip links that start with `#` (anchor tags and JavaScript) or `?` (query strings), non-http url schemes (such as `mailto:`, `tel:`, `data:`, etc. ), and any links you have omitted.

= What can I do if I have the same broken link on a lot of pages? =
There are other plugins such as [Better Search Replace by WP Engine](https://wordpress.org/plugins/better-search-replace/) that will quickly replace URLs on your entire site at once.

== Screenshots ==
1. Page load scan results on back-end
2. Page load scan results on front-end in dev console
3. Omitted links
4. Omitted pages
5. Detailed single page scan
6. Multi-Scan running scans on multiple pages in WP List Tables
7. Find broken links easily on front-end with a glowing animation and red border
8. Settings
9. Developer hooks on Help tab

== Changelog ==
= 1.0.5 =
* Update: Added a re-scan verification when loading results page as some false-positives occur with poor connections
* Tweak: Updated plugin tags
* Update: Added other plugins to bottom of help page
* Update: Added an option in settings to include/exclude image src links
* Tweak: Changed default timeout to 5 seconds

= 1.0.4.2 =
* Fix: Emailing empty content if already added to results; stopped duplicate emails

= 1.0.4.1 =
* Fix: MS Teams integration error with logging

= 1.0.4 =
* Fix: Undefined variable on help page
* Update: Added support for checking YouTube video links (props shirtguy72)
* Update: Added support for iframe links (props shirtguy72)
* Update: Added support for image source links

= 1.0.3.1 =
* Tweak: Fix minor issues found by WP repo plugin reviewer

= 1.0.3 =
* Tweak: Updates recommended by WP repo plugin reviewer
* Fix: Settings saved notification not echoing
* Tweak: Removed links post type and help docs post types if installed

= 1.0.2 =
* Tweak: Added nonce to Page Scan JS
* Update: Added Multi-Scan and Page Scan results to results page
* Tweak: Changed "Full Scan" references to "Multi-Scan"

= 1.0.1 =
* Deployment