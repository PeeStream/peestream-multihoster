# Changelog

## 0.3.3 - Documentation scope clarification
- Clarified that the multi-hoster plugin is not an AutoPublish V2 webhook receiver.
- Documented that `X-PeeStream-Signature`, HMAC-SHA256 webhook secrets and `?secret=` webhook URLs do not apply to this plugin's optional PeeStream adapter API calls.

## 0.1.0 - Phase 1 MVP
- Added per-post multi-hoster source metadata and manual editor controls.
- Added multi-source player with first-alive default, selector and shortcode.
- Added validation, nonce/capability checks, tests and documentation.
- Excluded uploads, API credentials and automated health/self-healing per Phase 1 scope.

## 0.2.0 - Phase 2 status engine
- Added six-hour WP-Cron batch checks (maximum 20 source URLs per run).
- Added HEAD with Range-limited GET fallback, 404/410 immediate dead verdict, and three-failure transient backoff.
- Added editor status/check time and nonce/capability-protected manual check action.
- No rendering-time probes, automatic re-upload or iframe-error-driven DB updates.

## 0.3.0 - Phase 3 foundation
- Added encrypted credential storage and masked settings UI.
- Added hoster adapter interface/registry and guarded PeeStream adapter scaffold.
- Remote API calls remain disabled pending confirmation of auth, payload and response contract; healing queue not included yet.

## 0.3.1 - PeeStream adapter and gated healing worker
- Added documented remote URL ingest and file-info polling adapter calls.
- Added durable idempotent queue schema and opt-in worker that updates target metadata only after ready+HTTPS embed.
- Healing remains disabled by default; mock API tests only, no live API interaction.

## 0.3.2 - Queue observability
- Added admin totals for pending, processing, polling, failed and healed jobs.
- Added nonce/capability-protected one-off worker scheduling from settings.
- Claimed jobs now expose processing state while a lease is active.
