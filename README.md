# WP Light Sessions

Lightweight, cache-flexible user sessions for WordPress

## Description

This plugin adds a light approach to frontend-facing user sessions. It aims to solve a few issues, the largest of which being full-page caching.

Many WordPress environments with full-page caching, like WordPress VIP, will bypass cache when user cookies are present. Objectively speaking, this is the correct approach, as there is considerable risk involved in caching any requests that might have permissions checks involved. Unfortunately, if a site allows users to sign in, those users do not get to enjoy the best-possible performance experience even on pages that don't have authorized features. Further, if a site has many thousands of users, this can have a cascading performance impact on the servers.

This plugin aims to help developers thread the needle of safe full-page caching with logged-in users. It does so by adding a separate cookie and authentication layer while removing the default WordPress cookies for specified users. By removing the default WordPress cookies, hosts can continue to operate as-is without needing to make any configuration changes, and by default, all requests will be cached as they would be for unauthenticated users. During one of these requests, if some code requests the authenticated user from this plugin, it will refuse and operate as if there is no authenticated user. However, if the request is first declared as _not cacheable_, then the plugin will authenticate the user using its cookie.

In essence, what this plugin provides is a mechanism to create a virtual "allowlist" of URLs on the site that are not cached and can access the currently authenticated user. If a request _is not_ on the list, then the currently authenticated user cannot be accessed, and the request can safely be cached. If the request _is_ on the list, the plugin knows it is safe to access the currently authenticated user without risk of user data being cached. It's important to consider that a request must be declared as "not cacheable" (e.g. send nocache headers) regardless of if a user is signed in or not.

This plugin is fully opt-in. Out-of-the-box, it changes nothing and has zero impact on a site's functioning.

## Instructions for Use

1. Install and activate the plugin.
2. Convert the session for an authenticated user. There are a few ways to do this:
   1. Hook into the filter `wp_light_sessions_convert_to_light_session`. This fires when the user is authenticated, and if it evaluates to `true`, then the rest of the process will be handled automatically.
   2. Fire the action `wp_light_sessions_convert_session` and optionally include a parameter with a redirect url. Note that this action must be called before headers are sent. It may be necessary to flush your rewrite rules before this can work.
   3. Make an authenticated request to `/convert-session/[nonce]`, where `[nonce]` is a valid nonce for this user (see `Alley\WP\Light_Sessions\Light_Sessions::get_nonce()`). Optionally, add the query param `?redirect_to=[uri]` where `[uri]` is the URI on the site you want the user redirected back to. If no redirect is specified, the user will be redirected to the homepage. As above, it may be necessary to flush your rewrite rules before this can work.
3. Before authenticating a user during a request, the request must first be configured to not be cacheable, by sending nocache headers or some other method. Then the plugin must be explicitly told that the request is safe to use session data. The simplest way to do both of these is to fire the action `wp_light_sessions_set_request_as_not_cacheable`, which will send nocache headers and flag the request as safe to use light sessions. If a request is already known to be uncacheable, you could instead fire the action `wp_light_sessions_request_is_session_safe` to explicitly flag the request as safe for light sessions. If the request is not explicitly declared as safe to use light sessions, the plugin will not authenticate the user; the plugin will trigger a "doing it wrong" notice, as well as fire the action `wp_light_sessions_caching_error`.
   * **IMPORTANT:** The request must remain uncacheable whether the user is signed in or not. If nocache headers are only sent for signed-in users, and an anonymous visitor accesses that URL, then subsequent authenticated users will receive the cached version of the request.
   * If you're looking for an appropriate action to hook into to determine if a request is cacheable or not, [`parse_request`](https://developer.wordpress.org/reference/hooks/parse_request/) is a good choice. At that point, headers have not been sent, the URL has been processed by WordPress, and query vars set.
4. To get the authenticated user during a request, call `apply_filters( 'wp_light_sessions_get_current_user', null );`. Alternatively, you may directly call the function `Alley\WP\Light_Sessions\get_current_user()`, though the filter approach is safer as it doesn't introduce a hard dependency between your code and this plugin, and will fail silently. Either method will return a `WP_User` on success, or `null` when there is a failure or no active session. In addition to `wp_light_sessions_caching_error` (as noted above), the plugin could also trigger the action `wp_light_sessions_authentication_error` if there was an authentication error. You could choose to throw an exception in your code from these actions.

## Q & A

> If a user is authenticated with a light session, can they access the WordPress admin? What about authenticated REST API endpoints?

When a session is converted from a full session to a light session, the WordPress cookies are destroyed, including the cookie that the WordPress admin uses to authenticate users. A user would need to sign back in to access the WordPress admin area.

Protected WordPress routes can be accessible to a user authenticated using light sessions, as long as that request has been marked as uncacheable and flagged as safe to use light sessions.

> Every page of my site has features that change based on whether the user is signed in or not. Is this plugin for me?

No, at least not without refactoring your site. This plugin only benefits site where a _subset_ of URLs requires or benefits from authentication, and the majority do not. One way to refactor a site to work this way is to load those features asynchronously using JavaScript. Then the main payload for the page can benefit from full-page caching and load more quickly, while the uncached request made via JavaScript can load customized content in asynchronously.

> Can I allow a URL to be cacheable to anonymous visitors and uncacheable for signed-in users?

Not without some custom development or edge cache configuration. Theoretically you could configure your edge cache to segment requests into "has a `wpls_logged_in` cookie" vs "doesn't have a `wpls_logged_in` cookie" and then anonymous users could get a cached response to that URL while users with the cookie would always get an uncached response.
