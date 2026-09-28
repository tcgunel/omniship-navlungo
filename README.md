# Omniship Navlungo Domestic

PHP 8.2+ carrier driver for the **Navlungo Domestic v2.1 API**, built for the [Omniship](https://github.com/tcgunel/omniship) multi-carrier shipping library.

Navlungo is a shipping aggregator: merchants connect their own cargo agreements (Sürat, HepsiJet, Kolay Gelsin, Aras, PTT, Yurtiçi, İstanbul Route 34, Turkish Cargo, ...) in the Navlungo panel, and this driver books through whichever agreement the `carrierId` points at. `carrierId = 1` means "automatic" — Navlungo picks the agreement that covers the destination (only when the account has a matching price list).

---

## Table of contents

- [Setup checklist](#setup-checklist)
- [Quick start](#quick-start)
- [API reference](#api-reference)
  - [createShipment](#createshipment)
  - [createReturnShipment](#createreturnshipment)
  - [getTrackingStatus](#gettrackingstatus)
  - [cancelShipment](#cancelshipment)
  - [getBarcode](#getbarcode)
  - [getMyCarriers / getAllCarriers](#getmycarriers--getallcarriers)
  - [getAddresses / createAddress](#getaddresses--createaddress)
- [Token caching](#token-caching)
- [Status mapping](#status-mapping)
- [Sandbox vs production](#sandbox-vs-production)
- [Gotchas, traps, and lessons learned](#gotchas-traps-and-lessons-learned)
- [Testing](#testing)

---

## Setup checklist

**1. Create an account on the panel.**

- QA: <https://domestic-qa.navlungo.com> · API: `https://domestic-api-qa.navlungo.com/v2.1`
- Production: <https://panel.navlungo.com> · API: `https://domestic-api.navlungo.com/v2.1`

**2. Mint an API user.** On *Entegrasyonlar* (Integrations), add a user under a partner integration — the auto-generated **username/password** is shown exactly once. These are the credentials this package needs (the panel login does *not* work on the API).

**3. Connect at least one cargo agreement** under *Kargo Şirketleri* and make sure a **price list** is defined for the company. A missing price list fails at create with `Seçtiğiniz taşıyıcı için firmanıza ait fiyat listesi tanımı bulunmamaktadır.`

**4. Create a sender address** under *Adres Defteri* (address type **Depo Konumları / sender**). Its id is the `senderAddressId` every standard post is booked from. Return pickups also need a recipient address (İade Konumları).

**5. (Optional) Configure the status webhook URL** in the panel (*Bildirim URL*). Navlungo then pushes status changes to your endpoint; the payload format is not documented — the `post/check` endpoint in this package is the reliable source of truth.

---

## Quick start

```php
use Omniship\Omniship;
use Omniship\Common\Address;
use Omniship\Common\Package;

$navlungo = Omniship::create('Navlungo');
$navlungo->initialize([
    'username'        => 'api-user-from-panel',
    'password'        => 'api-password-from-panel',
    'platform'        => 'My Shop',        // echoed on the waybill
    'senderAddressId' => 56027,            // address book entry (sender)
    'carrierId'       => 1,                // 1 = automatic, or an id from getMyCarriers()
    'postType'        => 2,                // 1 = same-day, 2 = standard
    'barcodeFormat'   => 'pdf-A5',
    'testMode'        => true,
    'tokenCache'      => Cache::store(),   // optional PSR-16 cache, see below
]);

$response = $navlungo->createShipment([
    'referenceId' => 'OMN-12345',          // must be unique per account
    'shipTo' => new Address(
        name:     'Ad Soyad',
        street1:  'Örnek mah. 123. sok. No:5 Daire:7',
        city:     'İstanbul',
        district: 'Kadıköy',
        phone:    '+90 532 123 45 67',
        email:    'alici@example.com',
    ),
    'packages' => [
        new Package(weight: 1.0, desi: 1.0, quantity: 1, description: 'Test'),
    ],
])->send();

if ($response->isSuccessful()) {
    echo $response->getShipmentId();    // "5TV84M7G7DPO" — Navlungo post number
    echo $response->getTrackingLink(); // hosted tracking page
    echo $response->getBarcodeUrl();    // label PDF (may 404 until generated)
} else {
    echo $response->getMessage();       // Navlungo's own wording, already flattened
    echo $response->getCode();          // HTTP status (422 validation, 401 auth, ...)
}
```

---

## API reference

### createShipment

`POST /post/create`

Sends a single post (the API supports arrays; this package books one at a time). Required: `username`, `password`, `senderAddressId`, `shipTo`. `carrierId` defaults to 1, `postType` to 2, `barcodeFormat` to `pdf-A5`.

Optional fields:

| Option | Meaning |
|---|---|
| `referenceId` | Your own unique id; echoed back and usable for tracking. Duplicate values are rejected (`Bu gönderi numarası zaten mevcuttur.`). |
| `cashOnDelivery` + `codAmount` + `codCollectionType` | COD mapping: `'0'` (cash) → `cod_payment_type 1`; `'1'` (card) → `2`. Amount limits: cash 7.000 TL, card 29.999 TL. Needs the COD permission on the account and a COD-capable carrier. |
| `note`, `customData1..4` | Passed through to `post.note` / `custom_data_*`. |
| `carrierId` | A concrete agreement id, or 1 for automatic routing. |

Package summarisation: `desi` is the sum of each package's volumetric desi (falling back to weight) × quantity; `package_count` is the total piece count.

### createReturnShipment

`POST /post/return` — return pickup (`post_type = 3`). The consumer is the shipper and their address travels inline; the merchant's return warehouse must exist in the address book.

```php
$navlungo->createReturnShipment([
    'referenceId'        => 'RTN-12345',
    'recipientAddressId' => 158,           // address book entry (recipient)
    'shipFrom'           => $consumerAddress,
    'packages'           => [...],
])->send();
```

### getTrackingStatus

`GET /post/check/{post_number|reference_id}`

Pass any identifier under the usual keys:

```php
$info = $navlungo->getTrackingStatus(['trackingNumber' => '5TV84M7G7DPO'])->send()->getTrackingInfo();
// or 'shipmentId' / 'referenceId'

$info->trackingNumber; // carrier tracking code once issued, else the post number
$info->status;         // ShipmentStatus enum, mapped from Navlungo's status codes
$info->events;         // TrackingEvent[] from the logs array
```

Response helpers: `getStatusName()`, `getTrackingLink()`, `getCarrierTrackingCode()`, `getCarrierTrackingUrl()`, `getCarrierBarcodes()`, `getReferenceId()`.

### cancelShipment

`POST /post/cancel` with `{post_number}`. Cancellation works while the post is **Hazırlanıyor** or **Teslim Alınacak**; a post with `inProgress = 1` must finish first.

```php
$navlungo->cancelShipment(['postNumber' => '5TV84M7G7DPO'])->send()->isCancelled();
```

`postNumber`, `shipmentId` and `trackingNumber` are accepted as aliases.

### getBarcode

`POST /barcode/getBarcode` — fetches the label. `pdf` works for every carrier; `zpl`, `zpl-10` and `zpl-pure` are carrier-specific (zpl-pure needs an account permission).

```php
$response = $navlungo->getBarcode(['postNumber' => '5TV84M7G7DPO', 'barcodeType' => 'pdf'])->send();

$response->getBarcodeUrl();                        // hosted PDF
$label = $response->getLabel();                    // Omniship Label (PDF) when inline base64 is returned
$label?->content;                                  // raw PDF bytes
```

Creation is asynchronous: right after `createShipment` the label may not be generated yet and the API answers `500` with a `file_get_contents(...) 404` error. Retry once `barcode_status` turns 1 on the tracking response.

### getMyCarriers / getAllCarriers

```php
$my = $navlungo->getMyCarriers(['limit' => 50])->send()->getCarrierOptions();
// [9 => 'Sürat Kargo', 10 => 'Hepsijet', 11 => 'Kolay Gelsin', ...]

$all = $navlungo->getAllCarriers()->send()->getCarriers();
```

Each carrier row carries `id`, `carrier_name`, `short_name`, `tracking_url`, `post_type[]` (1 same-day / 2 standard / 3 return) and `cod`.

### getAddresses / createAddress

```php
$addresses = $navlungo->getAddresses(['addressType' => 'sender'])->send()->getAddressOptions();
// [56027 => 'Teknokent']

$created = $navlungo->createAddress([
    'addressType' => 'sender',
    'locationName' => 'Merkez Depo',
    'address' => $shopAddress,          // Omniship Address, or explicit address* fields
    'isMainWarehouse' => true,
])->send();

$created->getAddressId();
```

Sender addresses require `locationName`; recipient entries do not. Phone numbers are normalised to `+90 5XX XXX XX XX`.

---

## Token caching

Navlungo Bearer tokens are valid for **8 hours** (`POST /auth/api`). Pass any [PSR-16](https://www.php-fig.org/psr/psr-16/) cache as `tokenCache` to mint one token per window instead of one per request:

```php
$navlungo->initialize([
    // ...
    'tokenCache' => Cache::store(),   // Laravel
]);
```

Cache key: `omniship_navlungo_token_{env}_{sha1(username)}` — scoped per environment and account. TTL is derived from the API's `expires_in` datetime minus a 5-minute buffer (fallback: 8h). Cache write failures are non-fatal.

---

## Status mapping

`status.status_code` from `post/check` → `Omniship\Common\Enum\ShipmentStatus`:

| Code | Navlungo | ShipmentStatus |
|---|---|---|
| 1 | Teslim Alınacak | `PRE_TRANSIT` |
| 2 | Teslim Edildi | `DELIVERED` |
| 3 | Teslim Edilecek | `OUT_FOR_DELIVERY` |
| 4 | Dağıtıma Çıktı | `OUT_FOR_DELIVERY` |
| 5 | Tekrar Sevk | `IN_TRANSIT` |
| 6 | Dağıtım Planlandı | `IN_TRANSIT` |
| 7 | İade Edilecek | `RETURNED` |
| 9 | İade Edildi | `RETURNED` |
| 10 | İptal | `CANCELLED` |
| 14 | Hazırlanıyor | `PRE_TRANSIT` |
| 16 | Teslim Alındı | `PICKED_UP` |
| 17 | Transfer Aşamasında | `IN_TRANSIT` |
| 18 | Şubede Beklemede | `IN_TRANSIT` |
| 19, 20 | Tazmin süreçleri | `FAILURE` |
| 21 | Depoya İade Edildi | `RETURNED` |

Unknown codes map to `UNKNOWN`. Event descriptions come from the log rows' `action_result` (falling back to `action`).

---

## Sandbox vs production

| | QA | Production |
|---|---|---|
| Panel | domestic-qa.navlungo.com | panel.navlungo.com |
| API | domestic-api-qa.navlungo.com/v2.1 | domestic-api.navlungo.com/v2.1 |
| `testMode` | `true` | `false` |
| Tracking pages | `domestic-track-qa.navlungo.com/...-dev` | `domestic-track.navlungo.com/...` |

The package picks the host from `testMode`; QA and production are separate accounts with separate API users.

---

## Gotchas, traps, and lessons learned

### 1. Create answers wrap the posts in a `data` array
The published example shows a flat `{post_number, reference_id, ...}` object. The live API answers `{status: true, data: [{post_number, barcode, ...}]}` and names the label URL `barcode` instead of `barcode_url`. The response class accepts both shapes — don't parse the raw body yourself.

### 2. Post creation is asynchronous and wallet-gated
Success means *"Gönderiniz, cüzdan bakiyenizin yeterli olması durumunda başarılı bir şekilde oluşturulacaktır."* The post is queued with `inProgress = 1`; the carrier agreements resolve shortly after. Give the barcode endpoint time before retrying.

### 3. A price list is mandatory per carrier
`carrierId = 1` (automatic) only works when the account has a matching price list; otherwise create fails with `Seçtiğiniz taşıyıcı için firmanıza ait fiyat listesi tanımı bulunmamaktadır.` Pick a concrete carrier id or have Navlungo define the price list.

### 4. `sender.addressId` must come from the address book
Inline sender addresses are rejected for standard posts; only return pickups use inline sender details. Create the warehouse address first (panel or `createAddress`).

### 5. The panel login is not the API login
API users live under *Entegrasyonlar*; their password is displayed once at creation. Invalid credentials answer `422 {"error": "Geçersiz kullanıcı bilgileri."}`, and the same 401 wording applies to expired/revoked tokens.

### 6. COD has hard limits and needs permissions
Cash at the door max 7.000 TL, card at the door max 29.999 TL; both need the COD permission on the account and a COD-capable carrier. The API validates and rejects with a field error.

### 7. `reference_id` must be unique per account
Reusing a reference (for example when retrying a create that actually succeeded) fails with `Bu gönderi numarası zaten mevcuttur.` Use the order's unique id, not a timestamp you might reuse.

### 8. Tracking falls back to the post number
`carrier_tracking_code` is only issued once the underlying carrier accepts the parcel. Until then the package reports the Navlungo post number so merchants and customers always have something to search.

---

## Testing

```bash
# From the omniship test bed root
docker compose run --rm php vendor/bin/pest omniship-navlungo/tests

# Or from the package directory
docker compose run --rm php bash -c "cd omniship-navlungo && vendor/bin/pest"
```

Mock HTTP fixtures live in `tests/Message/TokenFixtures.php`; the PSR-18 helper client in `tests/Helpers.php` returns one response per call and captures every request so tests can assert URL, method, headers and body. `createInMemoryCache()` exercises the token-caching path.

## License

MIT
