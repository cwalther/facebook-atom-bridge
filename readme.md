# Facebook to Atom Bridge
by Christian Walther <cwalther@gmx.ch>

In June 2015, [Facebook disabled RSS/Atom feeds](https://developers.facebook.com/docs/apps/changelog#v2_3_90_day_deprecations), with a suggested replacement of the JSON-based Graph API. My feed reader doesn’t read JSON, so to restore my ability to follow people’s Facebook updates, I wrote this bridge script that uses the Graph API to read a page feed and builds an Atom feed from the result.

How to use:

1. Create a new app on [the Facebook developer page](https://developers.facebook.com/quickstarts/?platform=web) and obtain its App ID and App Secret (detailed instructions [here](https://facebook-atom.appspot.com/)).

2. Combine App ID and App Secret with a vertical bar character | in between to form a access token and insert that into the script where it says so near the top.

3. Call it like http://example.com/facebook.php?id=123456789012345&count=20 with the ID of the page (if all you have is the human-readable URL of the page, look at its HTML source code and search for “page_id”, it should appear numerous times) and the desired number of posts.
