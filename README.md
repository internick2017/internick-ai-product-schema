# ShopGraph for WooCommerce

Make WooCommerce products discoverable and purchasable by AI shopping agents: complete Product JSON-LD, AI product attributes, and an `llms.txt` catalog index.

> Portfolio case study by [Nick Granados](https://nickgranados.com). A production-oriented WooCommerce plugin built with modern PHP, PSR-4, a DDEV environment, and a PHPUnit + WooCommerce test suite.

## The problem

AI shopping agents (ChatGPT, Gemini, Perplexity, Claude) research and recommend products. To be recommended, a store's catalog has to be machine-readable and complete. Shopify is moving toward agentic commerce natively; WooCommerce has no equivalent, so Woo products are effectively invisible to AI buyers.

## What it does

| Feature | Detail |
| --- | --- |
| **Product JSON-LD** | Complete schema.org `Product` (offers, availability, ratings, brand, image) built from live WooCommerce CRUD data. |
| **SEO coexistence** | Detects Yoast / Rank Math and merges into their Product node instead of duplicating it. Standalone output when no SEO plugin is present. |
| **AI attributes** | Q&A, compatible accessories, and substitutes added in the classic product editor, mapped to verified schema.org properties (`subjectOf`/FAQ, `isRelatedTo`, `isSimilarTo`). |
| **`/llms.txt`** | Markdown product index following the [llms.txt](https://llmstxt.org/) spec. |
| **AI `robots.txt`** | Welcomes GPTBot, Google-Extended, ClaudeBot, PerplexityBot, and others; references `/llms.txt`. |
| **Settings** | Per-feature toggles under WooCommerce → Settings → ShopGraph. |

## Engineering notes

- **Architecture:** OOP PHP (PSR-4, `ShopGraph\` namespace), booted on `plugins_loaded` only when WooCommerce is active. Small single-responsibility services wired in `Plugin::boot()`.
- **Data access:** exclusively through the WooCommerce CRUD API (`wc_get_product()`, `$product->get_*()` / `update_meta_data()` / `save()`), never direct SQL or post meta.
- **Schema correctness:** every schema.org property and third-party filter was verified against official docs before use (for example, "compatible accessories" maps to `isRelatedTo`, not the inverse `isAccessoryOrSparePartFor`).
- **HPOS:** declares `custom_order_tables` compatibility.
- **Testing:** PHPUnit with `wp-phpunit` and the WooCommerce test bootstrap, run inside DDEV. The schema builder, coexistence/merge logic, `llms.txt` builder, robots directives, and settings toggles are all covered.

## Local development

```bash
ddev start
ddev composer install
ddev exec bash tests/run-tests.sh   # PHPUnit + WooCommerce
```

The store is served at `https://shopgraph.ddev.site`.

## Roadmap

AI auto-fill of attributes (OpenAI/Gemini), Merchant Center feed, block-editor fields, and agentic checkout (UCP) support.

## License

GPL-2.0-or-later.
