# Omniship Navlungo — agent guide

Read this before changing the driver. It captures the **entire** Navlungo
Domestic v2.1 API surface (all documentation pages reviewed 2026-09-28, plus
live QA probes), the live-vs-docs differences, and the conventions of this
package. If a fact below looks stale, re-check against the docs first:
<https://domestic-docs.navlungo.com/v2-1> (page URLs are listed at the bottom)
and the Postman collection (`Domestic-v2.1.json`, linked from the docs root).

## Repository facts

- Package: `tcgunel/omniship-navlungo`, namespace `Omniship\Navlungo`.
- Carrier resolution is by convention: `Omniship::create('Navlungo')` →
  `Omniship\Navlungo\Carrier` (see `omniship-common`'s `CarrierFactory`).
- Sibling packages to mirror: `omniship-aras` (labels/label generation),
  `omniship-mng` (token caching, request wrappers). Most developed packages win
  when conventions differ.
- PHP 8.2+, PSR-12/PER-CS, `declare(strict_types=1)`, PHPStan level 8, Pest 3.
- Never run `composer update` inside this package expecting the `../omniship`
  path repo to resolve unless `omniship-common`'s branch alias is `0.1.x-dev`
  (it is since 2026-09-28). The reliable local flow is the monorepo root:
  `cd /Volumes/CrucialP3/Projects/omniship && docker compose run --rm php vendor/bin/pest omniship-navlungo/tests`.

## Environments and authentication

| | QA | Production |
|---|---|---|
| Panel | domestic-qa.navlungo.com | panel.navlungo.com |
| API base | `https://domestic-api-qa.navlungo.com/v2.1` | `https://domestic-api.navlungo.com/v2.1` |
| `testMode` flag | `true` | `false` |
| Tracking pages | `domestic-track-qa.navlungo.com/...-dev` | `domestic-track.navlungo.com/...` |

- `POST /auth/api` with `{username, password}` → `data.access_token` (Bearer)
  and `data.expires_in` as an **absolute datetime** (not seconds); tokens live
  8h. The panel login is **not** an API login — create API users under
  *Entegrasyonlar* (the password is shown once).
- Every other endpoint needs `Authorization: Bearer …` and
  `X-localization: tr`. Without `Accept: application/json` some servers
  redirect; always send it.
- Token caching: pass any PSR-16 cache as `tokenCache`; key is
  `omniship_navlungo_token_{env}_{sha1(username)}`, TTL = expiry minus 5 min.
- The account needs: a **price list per carrier** (missing → 400 on create),
  a **sender address** in the address book, and the **COD permission** if COD
  is used.

## Endpoint reference (complete)

### Auth
`POST /auth/api` — body `{username, password}`. Responses: 200 with token,
422 `{"status":false,"error":"Geçersiz kullanıcı bilgileri."}`,
422 field map when username/password missing.

### Posts
`POST /post/create` — standard (2) and same-day (1) shipments.
Body: `platform` (optional, sender company name) + `posts[]` with:

| field | type | required | notes |
|---|---|---|---|
| `reference_id` | string | no | unique per account; duplicate → 422 `Bu gönderi numarası zaten mevcuttur.` |
| `carrier_id` | int | yes | `1` = automatic routing; or an id from `carrier/my-carriers` |
| `post_type` | int | yes | `1` same-day, `2` standard |
| `cod_payment_type` | int\|"" | no | `1` cash at door, `2` card at door |
| `sender.addressId` | int | yes | address book entry, `address_type=sender` |
| `recipient.name/phone/email/address/country/city/district/post_code` | | name+phone+address+country+city+district yes | phone format `+90 532 123 45 67` |
| `post.desi` | decimal | yes | volumetric weight |
| `post.package_count` | int | yes | piece count |
| `post.price` | decimal | no | COD amount; > 0; max 7.000 TL cash / 29.999 TL card |
| `post.note` | string | no | |
| `barcode_format` | string | no | `html`, `pdf-A5`, `zpl` |
| `custom_data_1..4` | string | no | |

Response (documented): flat `{post_number, reference_id, tracking_url, barcode_url, post:{…}}`.
Response (live): `{status, message, data:[{post_number, reference_id, tracking_url, barcode, post:{…}}]}` —
**posts are wrapped in a list and the label URL is named `barcode`**. Success
message is informational: "Gönderiniz, cüzdan bakiyenizin yeterli olması
durumunda başarılı bir şekilde oluşturulacaktır." Creation is asynchronous
(`post.inProgress = 1`); the carrier agreement resolves shortly after.

`POST /post/return` — return pickup (`post_type` must be `3`).
Body: `platform` + `posts[]` with `recipient.addressId` (the merchant's
warehouse, an `address_type=sender` book entry), `sender` inline fields
(name/phone/address/country/city/district required), `post.desi`,
`post.package_count`, optional `cod_payment_type`/`price`/`note`,
`barcode_format`, `custom_data_*`.

`POST /post/update` — update while status is *Hazırlanıyor* or *Teslim
Alınacak* and `inProgress = 0`. Body: `post_number` (required) + only the
fields to change: `sender.addressId` **or** inline sender (not both),
`recipient.addressId` (returns) or inline recipient, `post.desi`,
`post.package_count`, `post.note`, `barcode_format`, `custom_data_1..4`.
Response: `{status, message, data:{post_number, reference_id, tracking_url,
barcode_url|barcode, post:{…}}}` (single object under `data`).

`GET /post/check/{post_number|reference_id}` — single tracking lookup.
Response `data`: `post_number`, `reference_id`, `tracking_url`,
`carrier_post_number`, `carrier_tracking_code`, `carrier_tracking_url`,
`barcode`, `barcode_status`, `post{carrier_id, carrier_name, carrier_status,
geo_status, geo_bad_address, post_type, post_type_name, cod_payment_type,
cod_payment_type_name, cod_comission, sender{…}, recipient{…}, post{desi,
package_count, price, post_price, calculated_price, measured_dominant_weight,
measured_box_count, note}, custom_data_1..4, created_at, updated_at}`,
`status{status_code, status_name, picked_up_date, delivered_person_name,
delivered_person_phone, delivery_note, delivery_cancel_reason, delivered_date,
cancel_date}`, `logs[{status_code, action, action_result, updated_by,
created_at}]`, `carrierBarcodes[{barcode_number}]`.

`POST /post/check` — detailed search. Body: `{post:{post_number, reference_id,
sender_name, sender_phone, sender_email, recipient_name, recipient_phone,
recipient_email}, limit}`; at least one filter required; max 50 results, newest
first. Same row shape as `GET /post/check` inside `data[]`.

`POST /post/cancel` — body `{post_number}`. Cancellable while *Hazırlanıyor* or
*Teslim Alınacak*; rejected while `inProgress = 1`. Success message: "Gönderi
başarıyla iptal edilmiştir."

> `POST /post/create/bulk` appears in the docs only inside an HTML comment
> ("Seçenek 2 — Asenkron Bildirim") and returns 404 on the live API. Not
> implemented; re-check with Navlungo before building if a customer needs it.

### Barcode
`POST /barcode/getBarcode` — body `{post_number, barcode_type}`.

| `barcode_type` | behaviour |
|---|---|
| `pdf` | base64 (`barcode_pdf`) + URL (`barcode_url`), all carriers |
| `zpl` | PDF output; Aras and HepsiJet only (10x15 for HepsiJet) |
| `zpl-10` | 10x10 PDF; HepsiJet only |
| `zpl-pure` | raw `.zpl` URL, empty PDF field; Aras only; needs an account permission, otherwise 404 |

Response `data`: `{barcode_type, barcode_url, barcode_pdf, barcode_html}`.
Label generation is asynchronous: right after create the API can answer 500
with `file_get_contents(…): 404` until the carrier produces the PDF —
retry once `barcode_status = 1` on the tracking response.

### Carriers
`GET /carrier/my-carriers` and `GET /carrier/getAll` — body `{limit}`
(required; 20 typical). Rows: `{id, carrier_name, tracking_url, short_name,
post_type:[1|2|3], cod:0|1}`. `post_type`: 1 same-day, 2 standard, 3 return.

### Address book
`GET /address-book/getAll` — body `{limit, page, filters:{address_type}}`
(`sender` or `recipient`). Rows include `id, customer_id, customer_name,
address_type, location_name, address_name, address_email, address_phone,
address_line, address_country, address_city, address_district,
address_post_code, address_latitude, address_longitude, is_main_warehouse,
is_return_address, created_at, updated_at`.

`GET /address-book/get/{address_id}` — detail. Docs show the row inside a
list, the live API returns an object; accept both.

`POST /address-book/create` — body: `address_type` (yes), `location_name`
(required when sender; e.g. "Ana Depo"), `address_name` (yes), `address_email`,
`address_phone` (yes, `+90 532 123 45 67`), `address_line` (yes),
`address_country` (yes, `tr`), `address_city` (yes), `address_district` (yes),
`address_post_code`, `is_main_warehouse` (sender only; only one main warehouse).
Response `data` = created row.

`PUT /address-book/update/{address_id}` — same fields as create. The docs page
URL omits the id; the Postman collection and the live API require it in the
path. Aras posts reject address changes with 422.

`DELETE /address-book/delete/{address_id}` — success `{status:true,
message:"Adres kaydınız başarıyla silinmiştir.", data:null}`.

## Status codes (tracking + webhooks)

| Code | Meaning | Omniship `ShipmentStatus` | App `MovementStatus` |
|---|---|---|---|
| 1 | Teslim Alınacak | PRE_TRANSIT | created |
| 2 | Teslim Edildi | DELIVERED | delivered |
| 3 | Teslim Edilecek | OUT_FOR_DELIVERY | at_branch |
| 4 | Dağıtıma Çıktı | OUT_FOR_DELIVERY | out_for_delivery |
| 5 | Tekrar Sevk | IN_TRANSIT | in_transit |
| 6 | Dağıtım Planlandı | IN_TRANSIT | in_transit |
| 7 | İade Edilecek | RETURNED | returned |
| 9 | İade Edildi | RETURNED | returned |
| 10 | İptal | CANCELLED | failed |
| 14 | Hazırlanıyor | PRE_TRANSIT | created |
| 16 | Teslim Alındı | PICKED_UP | picked_up |
| 17 | Transfer Aşamasında | IN_TRANSIT | at_hub |
| 18 | Şubede Beklemede | IN_TRANSIT | at_branch |
| 19/20 | Tazmin süreçleri | FAILURE | failed |
| 21 | Depoya İade Edildi | RETURNED | returned |

Webhook action → status code: `webhook_delivered` 2, `webhook_to_be_delivered`
3, `webhook_out_for_delivery` 4, `webhook_redispatch` 5,
`webhook_delivery_planned` 6, `webhook_to_returned` 7, `webhook_returned` 9,
`webhook_picked_up` 16, `webhook_in_transit` 17, `webhook_waiting_at_branch`
18, `webhook_return_to_warehouse` 21. The webhook **payload format is not
documented**; the merchant sets the URL in the Navlungo panel ("Bildirim URL").
The KolaySiparis backend accepts flat or nested shapes and matches the shipment
by post number/reference — tighten it when a real payload is captured.

## HTTP error codes

400 bad JSON/rule failure (no price list, card-COD unsupported by carrier, COD
on returns) · 401 missing/invalid token or permission · 402 wallet balance
(barcode) · 404 unknown route/record, also `zpl-pure` without permission ·
405 wrong method · 422 validation (field map), also mid-operation posts and
address-number changes on Aras posts · 429 rate limit · 500 unexpected
(body has `#eventId`) · 502/503 external service (ZPL barcode service) down.

## Design decisions in this driver

- `createShipment` collapses the API's array support into a single post; desi is
  the sum of package desi (fallback weight) × quantity; package count the sum of
  quantities.
- Responses accept both documented flat and live `data[0]` / `data` object
  shapes (`CreateShipmentResponse::source()`); keep that tolerance.
- `Package` handling and tracking mapping live in `Support\Packages` and
  `Support\TrackingData` so update/search share them.
- `CancelShipmentRequest` and `GetBarcodeRequest` accept `postNumber`,
  `shipmentId` or `trackingNumber` interchangeably.
- `GetTrackingStatusResponse::mapStatus()` is public and asserted by tests;
  delegate changes to `TrackingData::mapStatus()`.
- Phones are normalised to `+90 5XX XXX XX XX` (`Support\Phone`); unknown
  formats pass through untouched so the API's own validation explains itself.

## Testing

- Fixtures/helpers: `tests/Helpers.php` (PSR-18 mock client, in-memory cache),
  `tests/Message/TokenFixtures.php` (auth/create/tracking payloads).
- Run from the monorepo root:
  `docker compose run --rm php vendor/bin/pest omniship-navlungo/tests`
  plus `… vendor/bin/phpstan analyse` from inside the package directory.
- QA credentials are not committed. Create an API user in the QA panel under
  *Entegrasyonlar*; the QA account also needs a price list to use `carrierId=1`.

## Host app wiring (KolaySiparis)

- `backend/app/Services/Carriers/Navlungo.php` (prefix `navlungo`),
  `config/shipment.php`, `CarrierResolver::$requiredKeys`.
- Kolay Market page: `components/merchant/services/navlungo.blade.php` +
  `ActionController::navlungo/navlungoAddresses/navlungoCarriers`.
- Shipment flow goes through `OmnishipProvider`; Navlungo-specific branches:
  cancel, `postType`/`note` carrier fields, best-effort label fetch on create,
  `refreshLabel()` on the tracking view, `tracking()` for the customer code.
- Webhook: `NavlungoWebhookController` →
  `POST /api/incoming-webhooks/navlungo`.

## Doc page index (source material)

`/v2-1`, `/v2-1/create-token`, `/v2-1/posts`, `/v2-1/posts/create-post`,
`/v2-1/posts/return-post`, `/v2-1/posts/update-post`, `/v2-1/posts/check-post`,
`/v2-1/posts/check-post-multiple`, `/v2-1/posts/cancel-post`,
`/v2-1/address-book`, `/v2-1/address-book/create-address`,
`/v2-1/address-book/update-address`, `/v2-1/address-book/list-address`,
`/v2-1/address-book/address-detail`, `/v2-1/address-book/delete-address`,
`/v2-1/carriers`, `/v2-1/carriers/my-carriers`, `/v2-1/carriers/list-carriers`,
`/v2-1/barcode`, `/v2-1/barcode/get-barcode`, `/error-codes`.
