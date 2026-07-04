# ShopGraph — Manual DDEV Verification Checklist

Every hook/function verified against the live DDEV site (`https://shopgraph.ddev.site`,
WordPress 7.0 + WooCommerce 10.9.1) with seeded demo products, per the project's
"verify every hook in DDEV before v1" requirement.

Seed: `tests/seed-demo.php` creates a "ShopGraph Demo Phone" with Q&A, one accessory
("Rugged Phone Case"), and one substitute ("Rival Phone X").

## Results

| # | Item | Method | Result |
|---|------|--------|--------|
| 1 | Product page emits exactly ONE Product JSON-LD node | `curl` product page, count `application/ld+json` + Product nodes | ✅ 1 node (WooCommerce's), no duplicate |
| 2 | AI attributes merged into that node | grep node for `subjectOf` / `isRelatedTo` / `isSimilarTo` | ✅ all three present |
| 3 | JSON-LD is well-formed | pipe extracted script through `python -m json.tool` | ✅ valid JSON |
| 4 | Q&A → `subjectOf` / FAQPage; substitutes → `isSimilarTo`; accessories → `isRelatedTo` | inspect rendered node | ✅ correct schema.org mapping |
| 5 | AI attributes saved + read via WooCommerce CRUD | PHPUnit `FieldsTest` + seed uses `update_meta_data`/`save` | ✅ round-trips |
| 6 | `/llms.txt` serves the store + product index | `curl /llms.txt` | ✅ 200 `text/plain`, H1 + `## Products` list with prices |
| 7 | `/llms.txt` served at the exact path (no trailing-slash 301) | `curl -o /dev/null -w %{http_code}` | ✅ 200 (fixed: `template_redirect` priority 0 beats `redirect_canonical`) |
| 8 | Prices render as plain text (no HTML entities) | grep llms.txt output | ✅ `$19.90` (fixed: `html_entity_decode`) |
| 9 | robots.txt AI-crawler directives + llms.txt reference | `wp eval` `apply_filters('robots_txt', …)` | ✅ GPTBot/Google-Extended/ClaudeBot… `Allow: /` + `# llms.txt:` line, coexists with WooCommerce's own rules |
| 10 | Settings toggle disables a feature live | set `enable_llms=no` → `/llms.txt` stops serving; delete option → serves again | ✅ toggled on/off |
| 11 | Plugin Check (WordPress.org linter) | `wp plugin check` on a clean `shopgraph` folder | ✅ "No errors found" |
| 12 | Full PHPUnit + WooCommerce suite | `tests/run-tests.sh` | ✅ 20 tests, 55 assertions green |

## Notes / environment caveats

- **`/robots.txt` returns nginx 404 in DDEV.** This is a DDEV nginx routing quirk (it
  does not pass `/robots.txt` through to WordPress), not a plugin bug: the `robots_txt`
  filter is registered and produces the correct output (item 9). On standard hosting
  WordPress serves the virtual robots.txt and the filter fires.
- **Yoast / Rank Math coexistence** is covered by unit tests (`SchemaOutputTest`,
  `inject_into_graph` + `filter_wc_product`) rather than a live run, since neither SEO
  plugin is installed in this DDEV env. The default path (merge into WooCommerce Core's
  own Product node via `woocommerce_structured_data_product`) is verified live (items 1-2).
- **AI Attributes editor tab** save/read is unit-tested (`FieldsTest`); the tab markup
  uses the standard WooCommerce product-data-panel API.
- External validators (Google Rich Results Test, validator.schema.org) were not reachable
  from this environment; JSON validity was confirmed programmatically (item 3).

## WordPress.org readiness

- Slug `shopgraph` is free on WordPress.org (directory search returns no match).
- `readme.txt` present and valid (Plugin Check passes).
- Shipped ZIP contains only: `shopgraph.php`, `readme.txt`, `README.md`, `src/`,
  `languages/` (no `vendor/`, `wp/`, `tests/`, `.ddev/`). Build with `tests/build-dist.sh`;
  the ZIP root folder must be named `shopgraph` (matching the text domain).
- **Pending before submission:** plugin assets (icon-256, banner-772x250, screenshots)
  and confirming the WordPress.org contributor username.
