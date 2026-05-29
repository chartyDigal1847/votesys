# VoteSys DEORIS Module Bridge Integration

VoteSys is an embedded DEORIS module. It is not an authentication provider and it must not create its own login, Fortify flow, Sanctum token, shared session, or SSO exchange endpoint.

## Current Architecture

```text
DEORIS portal
  -> embeds VoteSys in a sandboxed iframe
  -> https://deoris.test/module-bridge.js requests SSO with postMessage
  -> portal validates the portal session
  -> portal returns a single-use SSO token to the iframe
  -> bridge exchanges the token with https://deoris.test/api/sso/exchange
  -> bridge stores user identity in window.PORTAL_USER in memory only
  -> bridge dispatches module:ready
  -> public/js/votesys.js boots the module UI
```

## Security Rules

- Trust only `https://deoris.test` for iframe SSO messages.
- Store identity only in runtime memory: `window.PORTAL_USER`.
- Keep token lifetime short and clear `window.SSO_TOKEN` after exchange.
- Clear `window.SSO_TOKEN` and `window.PORTAL_USER` on page unload.
- Do not use `localStorage`, `sessionStorage`, `document.cookie`, Laravel sessions, or CSRF exclusions for module SSO.
- Do not expose a module-owned `/sso/exchange` endpoint.

## Files Involved

- `resources/views/votesys.blade.php` loads `https://deoris.test/module-bridge.js`.
- `public/js/votesys-bridge.js` is retained only as a local fallback copy of the centralized bridge.
- `public/js/votesys.js` listens for `module:ready` and `module:error`.
- `routes/web.php` exposes module routes and API routes only; SSO exchange belongs to the portal.

## Removed Legacy Flow

The old implementation described a local `SsoController`, `AUTH_SERVICE_URL`, `/sso/exchange`, `votesys:ready`, and `window.__SSO_USER__`. Those were module-owned authentication concerns and have been retired in favor of centralized portal identity.
