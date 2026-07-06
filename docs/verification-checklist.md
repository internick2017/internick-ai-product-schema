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

## Live SEO coexistence + edge cases (2026-07-06)

Re-verified on the live DDEV site with a real Rank Math install (`seo-by-rank-math`
1.0.273), analyzing every `application/ld+json` block on the page and counting only
top-level Product nodes (graph members / root objects, never the nested Product
references inside `isRelatedTo` / `isSimilarTo`).

| # | Scenario | Method | Result |
|---|----------|--------|--------|
| 13 | Rank Math active, auto mode | Rank Math takes over and emits its own `@graph` (BreadcrumbList + Product); WooCommerce's separate node is gone | ✅ exactly **1** Product node, AI attrs merged into Rank Math's node via `rank_math/json_ld`, no duplicate |
| 14 | Rank Math active, standalone mode | Rank Math's Product is stripped from its graph; ShopGraph prints its own | ✅ exactly **1** Product node (ShopGraph's), Rank Math's block left with BreadcrumbList only |
| 15 | Yoast (free) active, standalone mode | WC node suppressed, Yoast free emits no Product, ShopGraph prints its own | ✅ exactly **1** Product node (ShopGraph's) |
| 16 | `/llms.txt` with `enable_llms=no` | `curl` status of `/llms.txt` | ✅ **404**; flips back to **200** the moment the toggle is removed (transient invalidation on `update_option` works) |
| 17 | Product with no price, standalone mode | inspect the Product node for an `offers` block | ✅ **no `offers`** (guard `'' !== get_price()` in `ProductSchema::build`) |
| 18 | "Add Q&A row" button JS | `curl` the enqueued asset + review handler | ✅ asset **200**; event-delegated add/remove on `#shopgraph_product_data` handles rows added after load |

## Notes / environment caveats

- **`/robots.txt` returns nginx 404 in DDEV.** This is a DDEV nginx routing quirk (it
  does not pass `/robots.txt` through to WordPress), not a plugin bug: the `robots_txt`
  filter is registered and produces the correct output (item 9). On standard hosting
  WordPress serves the virtual robots.txt and the filter fires.
- **Yoast / Rank Math coexistence is now verified live** (items 13-15) with a real Rank
  Math install, in addition to the unit tests (`SchemaOutputTest`, `inject_into_graph`
  + `filter_wc_product`). The default path (merge into WooCommerce Core's own Product
  node via `woocommerce_structured_data_product`) is verified live (items 1-2). Free
  Yoast without the WooCommerce SEO addon emits no Product node, so WC Core's node
  covers the page (merge-only, no appended duplicate).
- **AI Attributes editor tab** save/read is unit-tested (`FieldsTest`); the tab markup
  uses the standard WooCommerce product-data-panel API. The Q&A repeater button JS is
  verified reachable + logically correct (item 18).
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
