# ShopGraph Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build "ShopGraph for WooCommerce", a plugin that makes WooCommerce products discoverable/purchasable by AI shopping agents via complete Product JSON-LD, AI product attributes, `llms.txt`, and AI-crawler `robots.txt`.

**Architecture:** OOP PHP plugin (PSR-4), booted on `plugins_loaded` only when WooCommerce is active. Reads product data via the WooCommerce CRUD API. Emits JSON-LD on product pages (extending Yoast/Rank Math's graph when present to avoid duplicates), adds AI attribute fields to the classic product editor, and serves `/llms.txt` + AI-bot `robots.txt` directives. Settings toggle each feature.

**Tech Stack:** PHP 8.1+ · WooCommerce · WordPress · Composer (PSR-4 autoload) · PHPUnit + wp-phpunit + WooCommerce test helpers · DDEV (Docker) local env.

## Global Constraints

- **Verify every WooCommerce/WordPress API against official docs BEFORE implementing it** (each task lists the doc URL), and **manually test every hook/function in DDEV before shipping v1** (Task 8 checklist). This is a hard requirement.
- Product data access ALWAYS via WooCommerce CRUD (`wc_get_product()`, `$product->get_*()`, `$product->update_meta_data()`/`save()`). Never direct post meta or SQL.
- Declare HPOS compatibility (`custom_order_tables`).
- WP.org rules: GPLv2+, no obfuscation, no paid deps, plugin name must NOT start with "WooCommerce"/"Woo". Text domain `shopgraph`. All output escaped (`esc_*`), all input sanitized, nonces + capability checks on saves.
- Zero duplicate Product JSON-LD (coexist with Yoast/Rank Math).
- Classic product editor only in v1. No LLM calls in v1.
- Meta key prefix: `_shopgraph_` (e.g., `_shopgraph_qa`, `_shopgraph_accessories`, `_shopgraph_substitutes`).
- Option name: single option `shopgraph_settings` (array).

## File Structure

```
shopgraph/
├── shopgraph.php                 # header, guards, HPOS declare, bootstrap
├── composer.json                 # PSR-4 (ShopGraph\) + dev: phpunit, wp-phpunit
├── phpunit.xml.dist
├── src/
│   ├── Plugin.php                # wires services on init
│   ├── Settings/Options.php      # typed getter over shopgraph_settings
│   ├── Settings/SettingsPage.php # WooCommerce settings tab
│   ├── ProductFields/Fields.php  # AI attributes tab + CRUD save
│   ├── Schema/ProductSchema.php  # builds the JSON-LD array for a product
│   ├── Schema/SchemaOutput.php   # decides standalone vs extend; prints/injects
│   ├── Compat/SeoPlugins.php     # detect Yoast/Rank Math + their filters
│   ├── Llms/LlmsTxt.php          # /llms.txt route
│   └── Llms/RobotsTxt.php        # robots_txt filter for AI bots
├── tests/
│   ├── bootstrap.php
│   ├── ProductSchemaTest.php
│   ├── FieldsTest.php
│   ├── LlmsTxtTest.php
│   ├── RobotsTxtTest.php
│   └── SettingsTest.php
├── languages/shopgraph.pot
├── readme.txt
└── README.md
```

---

### Task 0: DDEV env + plugin skeleton + PHPUnit harness

**Files:** Create `shopgraph.php`, `composer.json`, `phpunit.xml.dist`, `src/Plugin.php`, `tests/bootstrap.php`, `.ddev/config.yaml`.
**Doc check:** HPOS declaration — https://developer.woocommerce.com/docs/features/high-performance-order-storage/ ; plugin header — https://developer.wordpress.org/plugins/plugin-basics/header-requirements/

**Interfaces:** Produces `ShopGraph\Plugin::instance()` booted on `plugins_loaded`; `composer test` runs PHPUnit with WooCommerce loaded.

- [ ] **Step 1: Create DDEV WordPress env**

Run:
```bash
cd /e/dev/02-wordpress/shopgraph
ddev config --project-type=wordpress --docroot=wp --php-version=8.2
ddev start
ddev wp core download --path=wp
# create wp-config + db, install
ddev wp config create --dbname=db --dbuser=db --dbpass=db --dbhost=db --path=wp
ddev wp core install --url=https://shopgraph.ddev.site --title=ShopGraph --admin_user=admin --admin_password=admin --admin_email=admin@example.com --path=wp
ddev wp plugin install woocommerce --activate --path=wp
```
Expected: `https://shopgraph.ddev.site` serves WordPress with WooCommerce active.

- [ ] **Step 2: Symlink the plugin repo into the WP install**

Run (from repo root; keeps the plugin repo separate from WP):
```bash
ddev exec ln -sf /var/www/html/../shopgraph /var/www/html/wp/wp-content/plugins/shopgraph 2>/dev/null || true
# If symlink is awkward on the mount, instead develop the plugin at wp/wp-content/plugins/shopgraph and keep git there.
```
Decision: keep the git repo AT `wp/wp-content/plugins/shopgraph`. Adjust `--docroot=wp` so the plugin lives inside. (Simplest reliable path on Windows/DDEV.)

- [ ] **Step 3: composer.json (PSR-4 + dev deps)**

```json
{
  "name": "internick2017/shopgraph",
  "description": "Make WooCommerce products discoverable by AI shopping agents.",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require": { "php": ">=8.1" },
  "require-dev": {
    "phpunit/phpunit": "^9",
    "wp-phpunit/wp-phpunit": "^6",
    "yoast/phpunit-polyfills": "^2",
    "woocommerce/woocommerce-sniffs": "^1"
  },
  "autoload": { "psr-4": { "ShopGraph\\": "src/" } },
  "scripts": { "test": "phpunit" }
}
```
Run: `ddev composer install`

- [ ] **Step 4: Main plugin file `shopgraph.php`**

```php
<?php
/**
 * Plugin Name: ShopGraph for WooCommerce
 * Description: Make your WooCommerce products discoverable and purchasable by AI shopping agents (complete Product schema, AI attributes, llms.txt).
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Nick Granados
 * License: GPL-2.0-or-later
 * Text Domain: shopgraph
 * WC requires at least: 8.0
 */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

// Declare HPOS compatibility.
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'ShopGraph requires WooCommerce.', 'shopgraph' ) . '</p></div>';
        } );
        return;
    }
    \ShopGraph\Plugin::instance()->boot( __FILE__ );
} );
```

- [ ] **Step 5: `src/Plugin.php` (skeleton)**

```php
<?php
namespace ShopGraph;

final class Plugin {
    private static ?Plugin $instance = null;
    public string $file = '';
    public static function instance(): Plugin { return self::$instance ??= new self(); }
    public function boot( string $file ): void {
        $this->file = $file;
        // services wired in later tasks:
        // (new ProductFields\Fields())->register();
        // (new Schema\SchemaOutput(new Schema\ProductSchema(), new Compat\SeoPlugins()))->register();
        // (new Llms\LlmsTxt())->register();
        // (new Llms\RobotsTxt())->register();
        // (new Settings\SettingsPage())->register();
    }
}
```

- [ ] **Step 6: PHPUnit harness (`phpunit.xml.dist` + `tests/bootstrap.php`)**

`tests/bootstrap.php` loads the wp-phpunit env and WooCommerce:
```php
<?php
$_tests_dir = getenv( 'WP_PHPUNIT__DIR' ) ?: dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $_tests_dir . '/includes/functions.php';
tests_add_filter( 'muplugins_loaded', function () {
    // load WooCommerce then this plugin
    require WP_CONTENT_DIR . '/plugins/woocommerce/woocommerce.php';
    require dirname( __DIR__ ) . '/shopgraph.php';
} );
require $_tests_dir . '/includes/bootstrap.php';
```

- [ ] **Step 7: Write a trivial passing test to prove the harness**

`tests/SettingsTest.php`:
```php
<?php
class HarnessTest extends WP_UnitTestCase {
    public function test_woocommerce_and_plugin_loaded(): void {
        $this->assertTrue( class_exists( 'WooCommerce' ) );
        $this->assertTrue( class_exists( \ShopGraph\Plugin::class ) );
    }
}
```

- [ ] **Step 8: Run tests (expect PASS)**

Run: `ddev exec -d /var/www/html/wp/wp-content/plugins/shopgraph vendor/bin/phpunit`
Expected: 1 passing test.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "chore: plugin skeleton, HPOS declare, PHPUnit+WooCommerce harness"
```

---

### Task 1: AI product attribute fields (classic editor, CRUD save)

**Files:** Create `src/ProductFields/Fields.php`, `tests/FieldsTest.php`.
**Doc check:** CRUD objects — https://developer.woocommerce.com/docs/best-practices/data-management/crud-objects/ ; custom fields — https://developer.woocommerce.com/docs/best-practices/data-management/adding-a-custom-field-to-variable-products/

**Interfaces:**
- Produces: meta `_shopgraph_qa` (array of {q,a}), `_shopgraph_accessories` (int[] product IDs), `_shopgraph_substitutes` (int[] product IDs). Getter helpers `Fields::get_qa($product)`, `Fields::get_accessories($product)`, `Fields::get_substitutes($product)`.

- [ ] **Step 1: Write failing test (save + read via CRUD)**

`tests/FieldsTest.php`:
```php
<?php
use ShopGraph\ProductFields\Fields;
class FieldsTest extends WP_UnitTestCase {
    public function test_saves_and_reads_ai_attributes_via_crud(): void {
        $product = WC_Helper_Product::create_simple_product();
        $product->update_meta_data( '_shopgraph_qa', [ [ 'q' => 'Waterproof?', 'a' => 'Yes, IP68.' ] ] );
        $product->update_meta_data( '_shopgraph_substitutes', [ 123, 456 ] );
        $product->save();

        $reloaded = wc_get_product( $product->get_id() );
        $this->assertSame( 'Yes, IP68.', Fields::get_qa( $reloaded )[0]['a'] );
        $this->assertSame( [ 123, 456 ], Fields::get_substitutes( $reloaded ) );
    }
}
```

- [ ] **Step 2: Run → FAIL** (`Fields` not found). `ddev exec ... vendor/bin/phpunit --filter FieldsTest`

- [ ] **Step 3: Implement `Fields.php`** — getters + a product-data tab + save via `woocommerce_admin_process_product_object`:
```php
<?php
namespace ShopGraph\ProductFields;

class Fields {
    public function register(): void {
        add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_tab' ] );
        add_action( 'woocommerce_product_data_panels', [ $this, 'render_panel' ] );
        add_action( 'woocommerce_admin_process_product_object', [ $this, 'save' ] );
    }
    public function add_tab( array $tabs ): array {
        $tabs['shopgraph'] = [ 'label' => __( 'AI Attributes', 'shopgraph' ), 'target' => 'shopgraph_data', 'priority' => 65 ];
        return $tabs;
    }
    public function render_panel(): void {
        global $post;
        $product = wc_get_product( $post->ID );
        $qa = self::get_qa( $product );
        echo '<div id="shopgraph_data" class="panel woocommerce_options_panel">';
        wp_nonce_field( 'shopgraph_save', 'shopgraph_nonce' );
        // repeatable Q&A + accessories/substitutes selectors (wc-product-search)
        // ... field markup using woocommerce_wp_* helpers and esc_* ...
        echo '</div>';
    }
    public function save( \WC_Product $product ): void {
        if ( ! isset( $_POST['shopgraph_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['shopgraph_nonce'] ) ), 'shopgraph_save' ) ) { return; }
        $qa = [];
        foreach ( (array) ( $_POST['shopgraph_q'] ?? [] ) as $i => $q ) {
            $q = sanitize_text_field( wp_unslash( $q ) );
            $a = sanitize_textarea_field( wp_unslash( $_POST['shopgraph_a'][ $i ] ?? '' ) );
            if ( $q !== '' ) { $qa[] = [ 'q' => $q, 'a' => $a ]; }
        }
        $product->update_meta_data( '_shopgraph_qa', $qa );
        $product->update_meta_data( '_shopgraph_accessories', array_map( 'absint', (array) ( $_POST['shopgraph_accessories'] ?? [] ) ) );
        $product->update_meta_data( '_shopgraph_substitutes', array_map( 'absint', (array) ( $_POST['shopgraph_substitutes'] ?? [] ) ) );
    }
    public static function get_qa( \WC_Product $p ): array { return (array) $p->get_meta( '_shopgraph_qa' ); }
    public static function get_accessories( \WC_Product $p ): array { return array_map( 'absint', (array) $p->get_meta( '_shopgraph_accessories' ) ); }
    public static function get_substitutes( \WC_Product $p ): array { return array_map( 'absint', (array) $p->get_meta( '_shopgraph_substitutes' ) ); }
}
```
Wire it in `Plugin::boot`: `( new \ShopGraph\ProductFields\Fields() )->register();`

- [ ] **Step 4: Run → PASS.**
- [ ] **Step 5: Commit** `feat(fields): AI product attributes tab with CRUD save`

---

### Task 2: Product JSON-LD builder

**Files:** Create `src/Schema/ProductSchema.php`, `tests/ProductSchemaTest.php`.
**Doc check:** schema.org Product — https://schema.org/Product ; Google product structured data — https://developers.google.com/search/docs/appearance/structured-data/product

**Interfaces:**
- Produces: `ProductSchema::build( \WC_Product $product ): array` returning a schema.org Product array (with `@context`, `@type`, name, offers, aggregateRating when reviews>0, and AI attributes when present).

- [ ] **Step 1: Failing test**
```php
<?php
use ShopGraph\Schema\ProductSchema;
class ProductSchemaTest extends WP_UnitTestCase {
    public function test_builds_product_offer_and_ai_attributes(): void {
        $product = WC_Helper_Product::create_simple_product();
        $product->set_regular_price( '19.99' ); $product->set_sku( 'SKU-1' );
        $product->update_meta_data( '_shopgraph_qa', [ [ 'q' => 'Color?', 'a' => 'Black' ] ] );
        $product->save();
        $schema = ( new ProductSchema() )->build( wc_get_product( $product->get_id() ) );
        $this->assertSame( 'Product', $schema['@type'] );
        $this->assertSame( '19.99', $schema['offers']['price'] );
        $this->assertSame( 'https://schema.org', $schema['@context'] );
        $this->assertNotEmpty( $schema['subjectOf'] ); // Q&A mapped here
    }
}
```

- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Implement `ProductSchema::build()`** using CRUD getters (`get_name`, `get_price`, `get_sku`, `get_stock_status`, `get_average_rating`, `get_rating_count`, `get_image_id`) + `Fields` getters for AI attributes; map Q&A to `subjectOf`/`Question`, accessories to `isAccessoryOrSparePartFor`, substitutes to `isSimilarTo` (verify exact props against schema.org doc in the doc-check step).
- [ ] **Step 4: Run → PASS.**
- [ ] **Step 5: Commit** `feat(schema): build complete Product JSON-LD from CRUD + AI attributes`

---

### Task 3: Schema output + coexistence with Yoast/Rank Math

**Files:** Create `src/Compat/SeoPlugins.php`, `src/Schema/SchemaOutput.php`, add cases to `tests/ProductSchemaTest.php`.
**Doc check:** Yoast schema API — https://developer.yoast.com/features/schema/api/ ; Rank Math filter `rank_math/json_ld`.

**Interfaces:**
- Consumes: `ProductSchema::build()`, `SeoPlugins::active()`.
- Produces: standalone `<script type="application/ld+json">` on `wp_footer` when no SEO plugin; otherwise merges AI attributes into the SEO plugin's Product node (no duplicate Product).

- [ ] **Step 1: Failing test** — `SeoPlugins::active()` returns `'yoast'|'rankmath'|null`; when null, `SchemaOutput::should_output_standalone()` is true.
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Implement** `SeoPlugins` (detect via `defined('WPSEO_VERSION')`, `class_exists('RankMath\\Rank_Math')`) and `SchemaOutput`:
  - No SEO plugin → `add_action('wp_footer', printStandalone)` guarded by `is_product()`.
  - Yoast → `add_filter('wpseo_schema_product', mergeAiAttributes)` (add subjectOf/isSimilarTo to the existing Product piece).
  - Rank Math → `add_filter('rank_math/json_ld', mergeAiAttributes, 99, 2)`.
  - Setting `schema_mode` can force `standalone`.
- [ ] **Step 4: Run → PASS.**
- [ ] **Step 5: Commit** `feat(schema): output with Yoast/RankMath coexistence (no duplicate)`

---

### Task 4: `/llms.txt` route

**Files:** Create `src/Llms/LlmsTxt.php`, `tests/LlmsTxtTest.php`.
**Doc check:** rewrite API — https://developer.wordpress.org/reference/functions/add_rewrite_rule/ ; llms.txt spec — https://llmstxt.org/

**Interfaces:** Produces a text/plain `/llms.txt` with store name, description, and a product index.

- [ ] **Step 1: Failing test** — call `LlmsTxt::render()` (pure builder returning the string) and assert it contains the site name and at least one product line.
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Implement** `LlmsTxt`: `register()` adds a rewrite rule for `^llms\.txt$` → query var `shopgraph_llms=1` (flush on activation), and `template_redirect` prints `render()` with `Content-Type: text/plain`. `render()` builds markdown from `get_bloginfo` + a `wc_get_products` loop (limited, cached via transient).
- [ ] **Step 4: Run → PASS** (+ manual: visit `https://shopgraph.ddev.site/llms.txt`).
- [ ] **Step 5: Commit** `feat(llms): serve /llms.txt store + product index for AI crawlers`

---

### Task 5: AI-bot `robots.txt` directives

**Files:** Create `src/Llms/RobotsTxt.php`, `tests/RobotsTxtTest.php`.
**Doc check:** `robots_txt` filter — https://developer.wordpress.org/reference/hooks/robots_txt/

**Interfaces:** Produces added lines for AI bots (GPTBot, Google-Extended, ClaudeBot, PerplexityBot, CCBot) + a `# llms.txt: <url>` reference, gated by settings.

- [ ] **Step 1: Failing test** — `RobotsTxt::filter( "existing", true )` returns a string containing `User-agent: GPTBot` and the llms.txt URL.
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Implement** `register()` → `add_filter( 'robots_txt', [ $this, 'filter' ], 10, 2 )`; `filter()` appends directives per the `allowed_bots` setting.
- [ ] **Step 4: Run → PASS** (+ manual: visit `/robots.txt`).
- [ ] **Step 5: Commit** `feat(robots): AI-crawler directives + llms.txt reference`

---

### Task 6: Settings page + wiring toggles

**Files:** Create `src/Settings/Options.php`, `src/Settings/SettingsPage.php`, `tests/SettingsTest.php`.
**Doc check:** WooCommerce settings API — https://developer.woocommerce.com/docs/how-to-add-a-section-to-a-settings-tab/

**Interfaces:** Produces `Options::get( string $key, $default )` over `shopgraph_settings`; a WooCommerce settings tab toggling schema/llms/robots + `schema_mode` + `allowed_bots`. Each feature checks `Options::get()` before acting.

- [ ] **Step 1: Failing test** — `Options::get('enable_schema', true)` returns the stored value; when `enable_schema=false`, `SchemaOutput::should_output_standalone()` is false.
- [ ] **Step 2: Run → FAIL.**
- [ ] **Step 3: Implement** `Options` + `SettingsPage` (`woocommerce_get_settings_pages` or a section under WooCommerce → Settings), and add `Options::get()` guards in Tasks 3/4/5 features.
- [ ] **Step 4: Run → PASS.**
- [ ] **Step 5: Commit** `feat(settings): WooCommerce settings tab + feature toggles`

---

### Task 7: i18n, readme.txt, Plugin Check

**Files:** Create `languages/shopgraph.pot`, `readme.txt`, `README.md`.
**Doc check:** WP.org readme — https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/ ; Plugin Check — https://wordpress.org/plugins/plugin-check/

- [ ] **Step 1: Generate POT** — `ddev wp i18n make-pot . languages/shopgraph.pot --domain=shopgraph`
- [ ] **Step 2: Write `readme.txt`** (WP.org format: header with Stable tag, Tested up to, Requires PHP, Tags; Description; Installation; FAQ; Changelog) and `README.md` (case study for portfolio, English).
- [ ] **Step 3: Run Plugin Check** — `ddev wp plugin install plugin-check --activate` then `ddev wp plugin check shopgraph`
Expected: 0 errors (warnings triaged).
- [ ] **Step 4: Commit** `docs: readme.txt, README case study, i18n POT`

---

### Task 8: Manual DDEV verification + WP.org prep (Nick's requirement)

**Files:** Create `docs/verification-checklist.md`.

- [ ] **Step 1: Seed sample data** — `ddev wp wc product create ...` (or import sample products) with prices, reviews, and AI attributes filled.
- [ ] **Step 2: Verify each hook/function fires** — for every feature, confirm in DDEV:
  - Product page emits ONE Product JSON-LD (view source). Validate with Google Rich Results Test (paste rendered HTML) and https://validator.schema.org/ → no errors.
  - With Yoast active: still only ONE Product node, now including the AI attributes.
  - AI Attributes tab saves + reloads correctly (Q&A, accessories, substitutes).
  - `https://shopgraph.ddev.site/llms.txt` returns the store + product index (text/plain).
  - `/robots.txt` includes the AI-bot directives + llms.txt reference.
  - Settings toggles actually disable/enable each feature.
- [ ] **Step 3: Record results** in `docs/verification-checklist.md` (pass/fail per item + screenshots).
- [ ] **Step 4: Verify slug + assets** — confirm `shopgraph` is free on WordPress.org; prepare icon/banner/screenshots.
- [ ] **Step 5: Commit** `docs: DDEV verification checklist (all hooks/functions verified)`
- [ ] **Step 6: Publish** — push public GitHub repo; submit to WordPress.org (Nick's account) once slug confirmed.

---

## Self-Review

**Spec coverage:** JSON-LD (T2/T3), AI product fields via CRUD (T1), coexistence no-duplicate (T3), llms.txt (T4), AI robots.txt (T5), settings toggles (T6), HPOS declare (T0), PHPUnit tests (T0-T6), manual DDEV verify + verify-against-docs (T8 + per-task doc-check lines), WP.org readiness + Plugin Check + readme (T7), publish (T8). Roadmap items (AI auto-fill, feed, block editor, UCP) are out of v1 scope per spec. No uncovered requirement.

**Placeholder scan:** Real code for bootstrap/HPOS, CRUD fields+save, schema builder, coexistence filters, llms/robots, tests. `render_panel()` field markup is described concretely (woocommerce_wp_* helpers + esc); exact schema.org property names for accessories/substitutes are flagged for doc verification in T2/T3 (per Nick's requirement) rather than guessed — intentional, not a gap.

**Type consistency:** meta keys (`_shopgraph_qa`/`_shopgraph_accessories`/`_shopgraph_substitutes`), `Fields::get_qa/get_accessories/get_substitutes`, `ProductSchema::build`, `SeoPlugins::active`, `SchemaOutput::should_output_standalone`, `Options::get`, option `shopgraph_settings`, text domain `shopgraph` — consistent across all tasks.
