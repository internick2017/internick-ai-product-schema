# ShopGraph — Diseño (spec)

- **Fecha:** 2026-07-01
- **Estado:** Aprobado (brainstorming)
- **Autor:** Nick Granados (con Claude)

> Nota: este spec está en español para revisión de Nick. El `readme.txt` y el `README.md` públicos del plugin se escriben en inglés (WP.org + portfolio).

## 1. Propósito y contexto

**ShopGraph** es un plugin de WooCommerce que hace que los productos de una tienda sean **descubribles, entendibles y comprables por agentes de IA** (Google AI Mode, ChatGPT shopping, etc.).

Es el **proyecto #2 de la campaña WordPress de Nick** (después del tema Kindly; headless será el #3). Doble objetivo:
1. **Pieza de portfolio** para el posicionamiento de WordPress Engineer, mostrando prácticas modernas de WooCommerce.
2. **Producto real** que llena un gap de mercado, para **publicar en WordPress.org** (como el plugin de alt-text de Nick).

## 2. El gap (por qué esto)

Investigación web (2026): Shopify lanzó **UCP (Universal Commerce Protocol) / Agentic Storefronts** en marzo 2026, dejando a cada tienda Shopify lista para compras mediadas por agentes IA **gratis**. **WooCommerce no tiene soporte nativo**: el merchant debe construir a mano las ~6 capas que los agentes IA necesitan (schema JSON-LD completo, atributos IA nuevos, `llms.txt`/`robots.txt`, feed, tracking, checkout API). Los productos WooCommerce quedan **"invisibles para los compradores IA"** por schema gaps, y Google agregó campos (Q&A, accesorios compatibles, substitutos) que los merchants ni saben que existen.

Necesidad enorme + creciente + no cubierta en Woo + **no saturada** (los plugins IA existentes son chatbots/generadores de contenido, no infraestructura de descubrimiento agentic). Encaja con el diferencial de IA de Nick.

Fuentes: seresa.io (agentic-commerce-readiness / schema gaps), woocommerce.com (prepare store for AI-driven commerce), wearepresta.com (UCP guide 2026).

## 3. Objetivos v1 y no-objetivos

**Objetivos (v1):**
- Hacer una tienda "AI-shopping-ready" con: JSON-LD completo, campos IA de producto, `llms.txt`, `robots.txt` para bots IA, y settings.
- Calidad publicable en WP.org (Plugin Check 0 errores) + repo público.
- Arquitectura **simple pero escalable** (deja lugar a IA/feed/UCP sin rehacer).

**No-objetivos (v1 → roadmap):**
- Auto-completado con IA (OpenAI/Gemini) de los atributos → primera mejora / Pro.
- Feed de Merchant Center, editor de bloques nuevo, tracking server-side, checkout API/UCP completo, multi-idioma avanzado.

## 4. Nombre

**ShopGraph** (público: "ShopGraph for WooCommerce" — WP.org prohíbe empezar con "WooCommerce"/"Woo"). Slug tentativo `shopgraph`. **Verificar disponibilidad del slug en WP.org + dominio antes de publicar.**

## 5. Stack y entorno

- **Plugin:** PHP (OOP, namespaces, PSR-4 autoload vía Composer).
- **Entorno local:** **DDEV** (Docker) con WordPress + WooCommerce; el plugin montado en `wp-content/plugins/shopgraph`. `ddev start` + setup script (instala WP, Woo, activa, siembra productos).
- **Tests:** PHPUnit con el scaffolding de test de WordPress/WooCommerce.
- **Repo:** git, rama de trabajo aparte; público en GitHub (`internick2017`) al publicar.

## 6. Arquitectura

```
shopgraph/
├── shopgraph.php            # header, guards (WooCommerce activo), bootstrap
├── composer.json            # PSR-4 autoload + PHPUnit (dev)
├── src/
│   ├── Plugin.php           # bootstrap / wiring
│   ├── Schema/              # generación JSON-LD (ProductSchema, OfferSchema...)
│   ├── ProductFields/       # metabox de atributos IA + guardado vía CRUD
│   ├── Llms/                # /llms.txt + robots.txt
│   ├── Settings/            # página de ajustes
│   └── Compat/              # detección Yoast/RankMath + declaración HPOS
├── assets/                  # CSS/JS admin (mínimo)
├── languages/               # .pot (i18n)
├── tests/                   # PHPUnit + scaffolding WooCommerce
├── readme.txt               # formato WP.org
└── README.md
```

**Prácticas modernas (el showcase):**
- OOP + namespaces + autoload PSR-4 (sin funciones globales sueltas).
- **Datos de producto vía la API CRUD de WooCommerce** (`wc_get_product()`, `$product->get_*()`, `update_meta_data()`/`save()`), NUNCA meta directa ni SQL.
- Hooks correctos de WooCommerce/WordPress (sin tocar core).
- **Declaración de compatibilidad HPOS** (`FeaturesUtil::declare_compatibility('custom_order_tables', ...)`).
- Seguridad: nonces, `sanitize_*`, `esc_*`, capabilities.
- i18n (translation-ready), GPL, semantic versioning, `readme.txt` válido WP.org.
- PHPUnit + listo para CI.
- **Extensible por sus propios hooks/filtros** (para enchufar IA/feed/Pro después = lo escalable).

**Sub-decisión tomada:** los campos IA van en el **editor clásico de producto** (`woocommerce_product_data_panels`) en v1 (compat amplia, simple); el editor de bloques nuevo queda para roadmap.

## 7. Features detalladas

### 7a. JSON-LD de producto (core)
En cada página de producto, `<script type="application/ld+json">` con schema.org `Product`:
- Base: name, description, sku, gtin/mpn, brand, image(s), category.
- `Offer`: price, priceCurrency, availability, priceValidUntil, url, itemCondition, básicos de envío/devoluciones (`OfferShippingDetails`/`MerchantReturnPolicy`).
- `aggregateRating` + reviews (de las reseñas de Woo).
- Atributos IA (de 7b): Q&A, accesorios compatibles (`isAccessoryOrSparePartFor`/`isRelatedTo`), substitutos (`isSimilarTo`).

> Las propiedades EXACTAS de schema.org se verifican contra la doc oficial en el plan/implementación.

**Coexistencia con Yoast/Rank Math:** detectarlos y **extender su grafo** (agregar solo los atributos IA que faltan, vía los filtros que ellos exponen); si no hay SEO plugin, emitir el `Product` schema completo propio. Setting para forzar modo standalone. Objetivo: **cero schema Product duplicado**.

### 7b. Campos IA de producto (metabox, editor clásico)
Panel nuevo en los datos de producto para:
- Q&A del producto (pares pregunta/respuesta, repetibles).
- Accesorios compatibles (selector que linkea a otros productos).
- Substitutos (selector de productos).
Guardado vía CRUD (`update_meta_data`/`save`). Alimentan el JSON-LD de 7a.

### 7c. `llms.txt` + `robots.txt`
- **`/llms.txt`:** ruta generada (rewrite/`template_redirect`) con resumen de la tienda + índice de productos/categorías, en el formato emergente `llms.txt`, para crawlers IA.
- **`robots.txt`:** vía el filtro `robots_txt` de WordPress, directivas para bots IA (GPTBot, Google-Extended, ClaudeBot, PerplexityBot...) con setting para permitir/bloquear + referencia al `llms.txt`.

### 7d. Página de settings
Bajo el menú de WooCommerce: toggles por feature (schema on/off, llms.txt on/off, qué bots permitir, modo de coexistencia auto/standalone).

## 8. Testing (requisito de Nick: probar cada hook/función antes de v1)

- **PHPUnit** (scaffolding WP/WooCommerce): schema output correcto; coexistencia sin duplicados; guardar/leer atributos IA vía CRUD; `llms.txt`/`robots.txt`; toggles de settings.
- **Pasada manual en DDEV antes de shippear:** cargar página de producto, validar JSON-LD con el **Rich Results Test de Google**/validador de schema, pegarle a `/llms.txt` y `/robots.txt`, probar toggles, probar con Yoast activo. Checklist "cada hook dispara, cada función responde".
- **Verificar cada API contra la doc oficial** (WooCommerce/WordPress) ANTES de usarla.

## 9. WP.org readiness + publicación

- `readme.txt` válido (description, installation, FAQ, changelog, stable tag, tested up to, requires, tags).
- **Plugin Check oficial → 0 errores** (como Kindly y el alt-text).
- GPL, sin dependencias pagas, review de seguridad, i18n.
- Verificar slug `shopgraph` + assets (icono/banner/screenshots).
- Submit a WP.org (cuenta de Nick) + repo público en GitHub.

## 10. Roadmap escalable

- Auto-completado con IA (OpenAI/Gemini) de los atributos → diferencial / Pro.
- Feed de Merchant Center, soporte editor de bloques nuevo, tracking server-side, UCP/checkout API para agentes, multi-idioma.
- Se enchufan por los hooks propios del plugin, sin rehacer el core.

## 11. Constraints globales

- **Verificar cada API contra la doc oficial y probar cada hook/función en DDEV antes de la v1.**
- WP.org: GPL, sin ofuscación, sin deps pagas, nombre no empieza con "WooCommerce"/"Woo".
- Datos de producto SIEMPRE vía CRUD de WooCommerce, nunca meta directa/SQL.
- Editor clásico de producto en v1.
- Cero schema Product duplicado (coexistencia con SEO plugins).

## 12. Decisiones tomadas

- Artefacto: extensión/plugin custom (vs tienda con tema de bloques / bloque suelto).
- Idea: "AI Shopping Ready" (vs gap clásico pago / contenido-IA saturado).
- v1 sin IA (solo datos estructurados), escalable a IA (vs IA desde el arranque).
- Nombre: ShopGraph.
- Entorno: DDEV (vs Laragon).
- Editor clásico v1 (vs editor de bloques nuevo).
