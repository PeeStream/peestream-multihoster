# PeeStream Multi-Hoster (MVP)

Version 0.3.4 - Phase 1, Phase 2 status engine and Phase 3 adapter/queue foundation. A WordPress plugin for manually storing multiple video sources per public post and rendering a source selector/player.

## Included
- Per-post `_psmh_sources` metadata: `hoster`, `url`, `label`, `order`, `status` (`alive`, `dead`, `checking`).
- Editor meta box with add/remove/reorder controls, nonce verification, `edit_post` authorization, and input validation.
- `[peestream_sources post_id="123"]` shortcode and automatic append to singular post content when sources exist.
- First alive source in editor order is the default; dead/checking sources are not offered by the MVP player.

## Install and use
1. Copy this directory to `wp-content/plugins/peestream-multihoster` and activate **PeeStream Multi-Hoster**.
2. Edit a public post/page, add a Video sources row, set hoster, HTTPS URL/embed URL, label and status, then reorder rows using Up/Down.
3. The player is appended on singular content. To place it manually, use `[peestream_sources]` or `[peestream_sources post_id="123"]`.

The player uses WordPress oEmbed when a provider supports the URL; otherwise it uses a lazy iframe. Only HTTP(S) URLs are stored. Use trusted provider embed URLs; iframe origins should be reviewed by the site operator.

## Phase 2 health status engine
WP-Cron scans published posts and checks up to 20 due source URLs per batch on a six-hour schedule; it never probes during page rendering. A manual **Check sources now** action is available in the post editor. Requests use WordPress safe HTTP with an 8-second timeout; `HEAD` falls back to a Range-limited `GET` only for 405/501. HTTP 404/410 marks a source dead immediately. Other transient/network errors remain `checking`, with exponential retry delay and `dead` only after three consecutive failed cycles. Success restores `alive` and resets retries. Private engine fields include check timestamp, retry count, next check and last HTTP code. WP-CLI can invoke `PSMH_Health::cli_run()` from a site-specific command wrapper or system cron via `wp eval`.

This checks HTTP reachability, not playback. Cross-origin player errors do not change server-side status. The player offers manual source selection and starts with the first `alive` source.

## Remaining out of scope
No direct file upload/fetch adapters, local temporary-video staging, Action Scheduler, bulk migration, or other hoster upload integrations. The PeeStream remote URL ingest and opt-in healing queue are documented below. Hosters remain manually entered; allowlist is in `PSMH_Meta::HOSTERS`.

## AutoPublish V2 scope
This plugin does **not** receive PeeStream AutoPublish V2 webhooks and therefore does not use `X-PeeStream-Signature`, HMAC-SHA256 webhook secrets, or `?secret=` URLs. Its optional PeeStream adapter calls the existing documented API with the configured credential; that is separate from signed AutoPublish webhook delivery. Keep this plugin at least `0.3.3` so operators do not confuse it with the signed V2 AutoPublish receivers in `peestream-wp-dooplay` and `dle-peestream-addon`.

## Tests
Run `php tests/test-plugin.php` (requires PHP CLI). This is a lightweight unit harness with WordPress stubs, not a substitute for integration testing on supported WordPress/PHP versions. Before production use, test editor behavior, shortcode rendering, oEmbed and CSP on staging.


## Phase 3 adapter/credential foundation (pre-queue)
- Settings page: **Settings → PeeStream Host Accounts**. Requires `manage_options`; keys are never rendered back.
- Credentials use authenticated encryption (libsodium secretbox when available, otherwise AES-256-GCM). Unique `LOGGED_IN_KEY` and `NONCE_KEY` constants are required; there is no plaintext fallback. Changing either key makes old values undecryptable, so rotate only with an explicit migration plan.
- Adapter interface/registry is in `includes/interface-hoster-adapter.php` and `includes/class-adapter-registry.php`.
- PeeStream adapter is intentionally a guarded scaffold: the spec identifies POST `/add` and GET `/api/file/info`, but does not define auth transport, required fields, response schema, folder semantics, or remote-upload completion contract. Network operations remain disabled until these are verified. The browser add-on GET `/api/upload/url` is not used as a substitute.
- Durable healing queue, fetching, temporary-file staging, uploads and automatic link replacement are not implemented in this step.
- Run `php tests/test-credentials.php` to test crypto and adapter registration.


## Phase 3.2: PeeStream adapter and healing queue
- Adapter uses the verified `GET /api/upload/url` contract for remote URL ingest and `GET /api/file/info` polling. The API key is sent only in the documented `key` query parameter; redirect following is disabled and response bodies are size-limited.
- Configure the HTTPS instance URL and encrypted PeeStream credential in **Settings → PeeStream Host Accounts**.
- Durable queue schema is installed on plugin activation. Worker processes up to three jobs per hourly batch; idempotency is keyed by post/target source/hoster. A health-discovered dead PeeStream target is enqueued only when `psmh_healing_enabled` is explicitly set to `yes` and a different alive source exists.
- Worker replaces the dead target URL only after `/api/file/info` returns `state=ready` and an HTTPS embed URL. Pending/processing jobs are polled again later; failed jobs retry with backoff up to five attempts.
- This path uses remote URL ingest, so it does not download/store a local temporary video. Direct file upload/fetch adapter methods remain unsupported; no local temp staging was added. No other hoster has upload adapter yet.
- The queue is disabled by default. Do not set `psmh_healing_enabled=yes` until credentials and target behavior are validated on staging. API calls and real uploads have not been run in automated tests.
- Tests: `php tests/test-api-adapter.php` covers mocked response parsing; `php tests/test-queue.php` covers queue mechanics.


### Queue operations UI
The **Settings → PeeStream Host Accounts** page shows `pending` (queued + retry), `processing` (active lease), `polling`, `failed`, and `healed` (complete) totals. **Run healing worker now** schedules a one-off WP-Cron invocation protected by `manage_options` and a nonce; it does not run long network work in the admin request. This explicit manual run can process queued work even while recurring healing is disabled.

---

## Pricing & Early Adopter Promo

- **Multi-Source Player & Selector:** **100% Free Forever.** Managing multiple video sources per post and rendering responsive player tabs with `[peestream_sources]` requires no PeeStream account, no license keys, and zero fees.
- **Automated Dead-Link Healing (Early Adopter Promo):** The automated background healing engine (which health-checks external sources and automatically remote-ingests alive mirrors to PeeStream when primary sources fail) is currently **100% free and fully unlocked** for all beta testers with a free PeeStream API key.
- **Future Roadmap:** To sustainably cover remote transcode server infrastructure and continuous hoster resolver maintenance, high-volume automated background healing may later be accompanied by an optional, low-cost **VIP Webmaster Addon** (starting at ~$1/month), while standard multi-source playback remains permanently free.

---

## License & Support
- **License:** GNU General Public License v2 (GPLv2) - see `LICENSE`.
- **Security policy:** see `SECURITY.md`. Report vulnerabilities privately; never post credentials, keys or nonces in a public Issue.
- **Release integrity:** `SHA256SUMS` lists the SHA-256 of the release asset `peestream-multihoster.zip`. Download the asset, put `SHA256SUMS` beside it and run `sha256sum -c SHA256SUMS`. Rebuild locally with `scripts/build-release.sh` (byte-reproducible).
- **Official support:** `https://peestream.com` or the WJunction support thread.
