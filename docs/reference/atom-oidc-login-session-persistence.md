# AtoM OIDC (arOidcPlugin): login succeeds but user is not logged in

**Summary:** A common AtoM SSO failure with `arOidcPlugin` (Azure AD / Entra, or any OIDC provider): the user passes the identity provider's login pages, is redirected back, and lands on the AtoM homepage as an anonymous user - as if never authenticated. With `QubitCacheSessionStorage` (Memcache/Redis-backed sessions) this is almost always a session-persistence problem, not an OIDC-config problem. The single diagnostic tell is that the user lands on the **homepage**, not on `admin/secure`. Applies to AtoM 2.9.x and 2.10.x.

## The diagnostic tell (from the plugin code)

In `plugins/arOidcPlugin/modules/oidc/actions/loginAction.class.php`, a **failed** `authenticate()` redirects to `admin/secure`. Landing on `@homepage` instead means `authenticate()` **succeeded and `signIn()` ran** - so the OIDC token exchange, user matching, and sign-in all worked. The authenticated state is only lost on the *next* request. That isolates the fault to session persistence (the Memcache session layer), not OIDC configuration.

The OIDC `state`/`nonce` are also held in the session (by the underlying Jumbojett OpenID-Connect client), so the whole flow depends on the session surviving both the redirect to the IdP and the callback.

## Fastest isolation test

Temporarily switch session storage in `config/factories.yml` from `QubitCacheSessionStorage` back to the default file-based `QubitSessionStorage`, then `php symfony cc` and restart php-fpm, and retry login. If login now sticks, the OIDC plugin is fine and the problem is entirely the Memcache session layer.

## The two Memcache gotchas that cause it

1. **Legacy extension.** `sfMemcacheCache` instantiates `new Memcache()` - the legacy `memcache` PECL extension, **not** `Memcached`. Confirm: `php -r 'var_dump(class_exists("Memcache"));'` must be `true` on the php-fpm host. Having only `php-memcached` installed is a frequent cause.
2. **Silent write failure.** `Memcache::addServer()` returns `true` **without connecting**. If php-fpm cannot resolve or reach the configured host (e.g. a Docker service name `memcached`), session writes fail silently while the rest of the site appears to work. Verify it is really storing: `printf "stats\r\n" | nc memcached 11211` and watch `curr_items` / `cmd_set` climb across a login attempt.

## Other checks

- **Log.** Tail `log/qubit.log` during the callback. `oidcUser` logs `OIDC exception: ...` on a state/nonce mismatch. Exception present => session lost *before* the callback (cookie/Memcache round-trip). No exception + sign-in logged => lost *after* sign-in (session regeneration not written to Memcache) - matches the homepage symptom.
- **Proxy / HTTPS.** `QubitCacheSessionStorage::initialize()` drops the secure-cookie flag when `request->isSecure()` is false. Behind a TLS-terminating reverse proxy, ensure AtoM sees HTTPS (trust `X-Forwarded-Proto`) so the session cookie and generated redirect_uri stay consistent.
- **Email claim (Azure).** With `user_matching_source: oidc-email`, Entra does not always emit an `email` claim unless the user has a mail attribute or it is added as an optional claim in the app registration.
- **Token expiry vs never-logged-in.** AtoM 2.10 moved the plugin to `expires_in` for access-token handling; 2.9.x differs and can make sessions drop *after a while*. But "never logged in from the start" is the Memcache/session-persistence issue above, not expiry.

## Most likely cause

A silent Memcache write failure (wrong extension or unreachable host). Start with the isolation test and the two Memcache checks.

## Related

- Redirect URI for Azure: `https://<atom-domain>/index.php/oidc/login` (the route arOidcPlugin registers - not `/login` or `/user/login`).
- Activation requires an `activate-oidc-plugin` marker file, `login_module: oidc` in settings.yml, `user: oidcUser` in factories.yml, and provider config in `plugins/arOidcPlugin/config/app.yml`.
- Open upstream issue: activating OIDC removes normal local login (no parallel SSO + local login) - affects 2.9.x and 2.10.x.
