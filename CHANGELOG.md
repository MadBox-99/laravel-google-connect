# Changelog

## v0.2.0 — 2026-10-01

### Security

- The OAuth `state` is now a single-use nonce bound to the browser session. The callback reads the
  team from the session, so a forged or replayed callback can no longer attach a Google account to
  another team.
- `redirect`, `callback` and `disconnect` (routes and the Livewire action) check that the logged-in
  user may access the team. By default `canAccessTenant($team)` decides. Override it with the
  `google-connect.authorize` config callable. Unauthorized requests get a 403.
- The connect component only saves an Ads account, Search Console site or GA4 property that the
  connected Google account can actually see.

### Added

- `google-connect.resources` config (default `['ads', 'search_console', 'ga4']`) limits which
  selectors are listed and rendered.

### Fixed

- Clearing a selector stores `null` instead of an empty string.

### Breaking

- `GoogleOAuthService::getAuthorizationUrl(Model $team, string $state)` takes the state from
  `OAuthState::issue()`. Callers that built the URL themselves must pass it.
- A callback without the session that started the flow (other browser, expired session) is now
  rejected. Users simply click "Connect Google" again.
