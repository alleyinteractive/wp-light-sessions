# WP Light Sessions

Lightweight, cache-flexible user sessions for WordPress

## Description

This plugin adds a light approach to frontend-facing user sessions. It aims to solve a few issues, the largest of
which being full-page caching.

Many WordPress environments with full-page caching, like WordPress VIP, will bypass cache when user cookies are
present. Objectively speaking, this is the correct approach, as there is considerable risk involved in caching any
requests that might have permissions checks involved. Unfortunately, if a site allows users to sign in, those users do
not get to enjoy the best-possible performance experience even on pages that don't have authorized features. Further,
if a site has many thousands of users, this can have a cascading performance impact on the servers.

This plugin aims to help developers thread the needle of safe full-page caching with logged-in users.

## Instructions for Use

1. Install and activate the plugin.
2. Convert the session for an authenticated user. There are a few ways to do this:
   1. Hook into the filter `wp_light_sessions_auth_as_light_session`. This fires when the user is authenticated, and if
      it evaluates to `true`, then the rest of the process will be handled automatically.
   2. Fire the action `wp_light_sessions_convert_session` and optionally include a parameter with a redirect url. Note
      that this action must be called before headers are sent.
   3. Make an authenticated request to `/convert-session/[nonce]`, where `[nonce]` is a valid nonce for this user.
      Optionally add the query param `?redirect_to=[uri]` where `[uri]` is the URI on the site you want the user
      redirected
      back to. If no redirect is specified, the user will be redirected to the homepage.
3. Before getting an authenticated user during a request, the request must first be declared as uncached. To do so, fire
   the action `wp_light_sessions_is_uncached_request`. If the request is not explicitly
   declared as uncached, the plugin will not get the user. The plugin will trigger a "doing it wrong" notice, as well as
   fire the action `wp_light_sessions_caching_error`.
4. To get the authenticated user during a request, call `apply_filters( 'wp_light_sessions_get_current_user', null );`.
   Alternatively, you may directly call the function `Alley\WP\Light_Sessions\get_current_user()`, though the filter
   approach is safer as it doesn't introduce a hard dependency between your code and this plugin, and will fail
   silently. Either method will return a `WP_User` on success, or `null` when there is a failure or no active session.
   In addition to `wp_light_sessions_caching_error`, the plugin could also
   trigger `wp_light_sessions_authentication_error` if there was an authentication error. You could choose to throw an
   exception in your code from these actions.
5. To check capabilities for a user, this plugin provides a filtered allowlist (`wp_light_sessions_cap_allowlist`) of
   capabilities that can be checked against a user with a light session. By default, this only
   includes `use_light_sessions`. Note that if you call `current_user_can( 'use_light_sessions' )` (or another
   capability on the allowlist), the request must be declared uncached per #3.

## Q & A

> If a user is authenticated with a light session, can they access the WordPress admin? What about authenticated REST
> API endpoints?

No, when a session is converted from a full session to a light session, the WordPress cookies are destroyed and
WordPress core believes the user is not authenticated. Only the allowed capabilities can be checked for the user. If a
user has any capabilities that allow them to use the WordPress admin, protected REST API routes, etc., they will need to
re-authenticate using the WordPress login.
