# zaracharm — Project Memory

Persian (RTL, `APP_LOCALE=fa`) e-commerce shop + page-builder CMS. Mobile/OTP auth, product variants & multi-warehouse inventory, discounts/campaigns/coupons, cart → order → invoice → payment (wallet / card-to-card / gateways). Legacy LMS/services/hospitals/team modules were removed (Oct 2026); blog remains.

## Stack
- Laravel 12, PHP 8.2, **Livewire 4 single-file components (SFC)** — no controllers, no `app/Livewire` classes.
- MySQL in `.env` (`.env.example` says sqlite). Session/cache/queue = database.
- spatie/laravel-permission (roles+permissions), morilog/jalali + verta (Jalali dates), S3 flysystem, SOAP SMS (Payamak, `config/sms.php`).
- Tailwind v4 via `@tailwindcss/vite` (config lives in `resources/css/app.css`, no tailwind.config.js). Alpine via Livewire. Swiper + SweetAlert loaded from `public/main/...` and `public/dashboard/...` (not npm).

## Architecture
- **Routing**: `routes/web.php` only. Every route is `Route::livewire(uri, 'pages::<dot.path>')`. `pages::` → `resources/views/pages/` (see `config/livewire.php` `component_namespaces`). Default page layout `layouts::main`; dashboard components set `#[Layout('layouts.dashboard')]`.
- **Component files**: PHP class (`new class extends Component {...}`) + Blade in one file. Filenames have no `⚡` prefix (it was removed from all files; `make_command.emoji` is `false`) — don't add it back.
- **Business logic lives inside components**, often raw `DB::table()` queries + `DB::transaction()` + `lockForUpdate()`. Only real services: `app/Services/Pricing/ProductPriceService.php`, `OtpService`, `SmsService`, `ViewService`, `FileUploadService` (rarely used).
- Route order matters: catch-all `/{slug}` (CMS page) and `/` come after specific front routes; dashboard group is `/dashboard/*` with middleware `auth` + `permission:settings.dashboard`.

## Key directories
- `resources/views/pages/dashboard/<entity>/` — admin CRUD (index + trash + sub-screens like `products/{prices,settings,specifications}`, `campaign/{targets,conditions,rewards}`).
- `resources/views/pages/main/` — storefront (products/show, cart/*, blogs, user/dashboard, pages/show).
- `resources/views/components/main/sections/` — page-builder section components; `components/dashboard/forms/` — matching admin forms for section `data`.
- `resources/views/layouts/{main,dashboard}.blade.php` — global JS listeners (alerts, seo), asset includes.
- `app/Enums/` — int-backed enums with Persian `title()/label()` + `options()` for selects.
- `app/Traits/FileUploadTrait.php` (Media uploads), `HasSeoMeta.php`.

## Domain rules
- **Catalog**: `Product` (type = `ProductType` enum, `status`, `published_at`) → `ProductVariant` (price, compare_price, sku). Options/OptionValues attach to variants via `product_variant_option_values`. Specs via `product_specifications` pivot with typed value columns (`SpecificationType`). Products belong to many `categories` + one `brand`.
- **Stock**: `InventoryItem` per (inventory, variant). Available = `quantity - reserved_quantity`, only where item.status and inventory.status are true. Use `ProductVariant::availableStock()`. Checkout **reserves** (`increment reserved_quantity`) rather than decrementing quantity.
- **Pricing**: always use `$variant->priceData()` (→ `ProductPriceService::calculate`). Collects `Discount`s targeted (morph `discount_targets`) at product/category/brand plus active `Campaign`s (type Discount/FlashSale) via `campaign_targets`; picks ONE best: highest amount → campaign priority → target priority (ALL 4 > product 3 > category 2 > brand 1). No stacking.
  - Enum mismatch: `DiscountType` Percent=1/Fixed=2, but `CampaignReward.reward_type` uses raw 0=percent/1=fixed.
- **Coupons**: `Coupon` → `Discount`; limits `usage_limit`, `usage_per_user`, tracked in `coupon_usages`. Applied in `pages/main/cart/cart-item.blade.php`.
- **Order flow**: `cart-item::proceedToPayment()` (in a transaction) creates Order(`pending`/`unpaid`, `expires_at` +1800 min) + OrderItems + Invoice/InvoiceItems, sets cart `converted` → `checkout/{order_number}` lets the user pick address + `ShippingSlot` + payment method (`wallet` pays immediately → order `processing`/`paid`; `transfer` = card-to-card upload → payment `pending`; `cod` disabled; `gateway` redirects to undefined route). Cart statuses: `active` / `converted`. Statuses are plain strings, not enums.
- **Wallet/Transaction**: `Wallet.balance` int; `Transaction` credit/debit with `balance_after`, morph `reference`.
- **Media**: polymorphic `Media` (`mediable`), keyed by `collection` (`featured_image`, `banner_image`, `featured_video`, `avatar`, `logo`…), `purpose`, `sort`, or `external_url`. URLs built as `url('/storage/'.file_path)`. Replace = delete collection rows then `upload()`.
- **Page builder**: `Page` → `PageRow` (container/gap/padding) → `RowSection` (`layout` JSON grid/spacing → Tailwind classes, `data` JSON) → `Section` (`component`, `is_livewire`). Rendered in `pages/main/pages/⚡show.blade.php`. Section registry seeded by `SectionTableSeeder`.
- **Settings**: key/value `settings` table, cached under key `settings` (`Cache::remember`/`rememberForever`); `Cache::forget('settings')` after edits.
- **SEO**: `HasSeoMeta` (morph `seo_metas`), components dispatch `seo:update` with `getSeoPayload()`.
- **Views tracking**: `ViewService::record($model)` dedupes per session for 30 min.

## Auth & permissions
- Login: `pages/auth/login.blade.php` — mobile `09xxxxxxxxx`; OTP (4 digits, hashed, 5-min TTL, 120 s resend throttle, max 5 attempts) auto-creates user + assigns role `user`; or password login. `users.status=false` → 403.
- Roles seeded: `admin` (all perms), `user`. `User::getRoleLabelAttribute` still maps legacy `teacher`/`student` labels.
- Permission names `<area>.<action>` (view/create/edit/delete) — see `database/seeders/RoleTableSeeder.php`. Checked inside components via `abort_if(!auth()->user()->can('x.y'), 403)` in mount/actions, plus `@can` in Blade.
- ⚠ Many shop screens (brands, products, …) reuse `categories.*` permissions. Some checked perms are not seeded (`invoices.edit`, `tickets.create/edit`, `seo.delete`).

## Conventions (follow existing)
- Dashboard CRUD component shape: `$info` array (header/create/delete/personal labels + table headers), `$model`, `$data`, `$selectItem`, `loadData()`, `get_data($id)`, `save()` (create or `tap()->update`), `delete()`, `change_status($id)`, `resetData('create'|'close')`, `rules()` + `messages()` with **Persian** messages.
- UI feedback: `$this->dispatch('alert', type:, title:, text:)`; modals closed via `dispatch('close-modal')`; rich editor refresh via `dispatch('editor-update')`.
- Soft deletes + `/trash` screens for most admin entities. `status` boolean + `scopeActive()`; `sort` column for ordering.
- Slugs allow Persian: regex `^[a-zA-Z0-9\-_\p{Arabic}]+$`; generated from title by replacing whitespace with `-`.
- Dates: model accessors return **Jalali `Y/m/d` strings** and setters accept Jalali strings (Product.published_at, Discount.starts_at/ends_at, Campaign.start_at/end_at). Use `getRawOriginal()` for Carbon comparisons.
- Models mix `$guarded = []` and explicit `$fillable` — check before mass-assigning new columns.
- Prices/amounts are integers (Toman/Rial), no decimals.
- Comments in code are often Persian; UI text is Persian, RTL.

## Frontend
- Theme tokens in `resources/css/app.css` `@theme`: `primary-*`, `secondary-*`, `brown-*` (storefront accent), `custom-light/dark`; font `payda`. Dark mode = `.dark` class variant.
- Dynamic Tailwind classes from DB must be within the `@source inline(...)` safelist (col-span 1–12, gap 0–12, pt/pb 0–20). Extend the safelist if adding new dynamic utilities.
- Dashboard layout is a third-party admin template (assets in `public/dashboard/`); storefront uses Tailwind + Swiper.

## Commands
- `composer dev` — serve + queue:listen + pail + vite. `npm run build` for production assets.
- `composer test` / `php artisan test` (PHPUnit 11). `./vendor/bin/pint` for formatting (not enforced).
- ⚠ Seeders `TRUNCATE` roles/sections/etc. with MySQL-only `SET FOREIGN_KEY_CHECKS` — never run `db:seed` against real data without asking.

## Testing
- Only Laravel example tests exist; no project test suite. Verify changes by reasoning + running the app; add tests only if asked.

## Known pitfalls
- Route names used but not defined: `payment.gateway`, `order.success`, `register`, `admin.invoices.print`; checkout also redirects to `cart` (no such route; the cart is `cartItem`).
- Routes pointing to missing components: `dashboard.roles.trash`, `dashboard.pages.items`.
- Duplicate routes: `invoices.index` defined twice.
- The real cart page is `pages/main/cart/cart-item` (route `cartItem`); there is no `cart`/`cart.index` route.
- `Page::publishedSections()` references a non-existent `PageSection` model; `Admin`, `PageSectionItem` models are empty.
- `.env` sets `FILESYSTEM_DISK` twice (last = `s3`), but media is stored on the `public` disk by default.
- `OtpService` logs the plaintext OTP code (`logger()->info("CODE: ...")`).
- `Schema::defaultStringLength(191)` set in `AppServiceProvider`.
- Repo root contains large archives (`zaracharm.zip`, `public/*.zip`, `vendor.7z`) — ignore them when searching.

## Avoid changing unnecessarily
- `ProductPriceService` selection order, stock reservation logic, and the order/invoice transaction in `cart-item` / `checkout`.
- Route order in `routes/web.php` (catch-all last among front routes) and existing route names (views reference them).
- Permission names (seeded + referenced across many views), Section `key`/`component` values (stored in DB rows).
- Jalali accessors on date columns; `@source inline` safelist in `app.css`.
- Never edit `.env`, run migrations/seeds, or touch `vendor/`, `public/build`, or the zip archives without asking.
