
# Laravel BRI QRIS (MPM Dynamic)

A lightweight Laravel package for **Bank BRI QRIS Merchant Presented Mode (MPM) Dynamic**:
- B2B token (SNAP) with **RSA SHA256** header signature
- **Generate QR MPM Dynamic**
- **Inquiry Payment** status
- **Notification (webhook) endpoint** scaffold

> Docs reference: BRIAPI QRIS MPM Dynamic & Notification v1.1. See BRI’s docs for exact headers and signatures.

## Install

```bash
composer require elgibor-solution/laravel-bri-qris
php artisan vendor:publish --provider="Elgibor\BriQris\BriQrisServiceProvider" --tag=bri-qris-config
```

Add environment variables:

```env
BRI_QRIS_BASE_URL=https://sandbox.partner.api.bri.co.id
BRI_QRIS_CLIENT_ID=your_client_id   # X-CLIENT-KEY
BRI_QRIS_CLIENT_SECRET=your_client_secret  # for HMAC symmetric signature
BRI_QRIS_PRIVATE_KEY_PATH=storage/keys/bri-private.pem  # RSA private key (PKCS#8/PKCS#1)
BRI_QRIS_PUBLIC_KEY_PATH=storage/keys/bri-public.pem    # (optional) BRI public key for verifying notifications
BRI_QRIS_PARTNER_ID=your_partner_id
BRI_QRIS_CHANNEL_ID=95221
BRI_QRIS_MERCHANT_ID=00007100010926
BRI_QRIS_TERMINAL_ID=213141251124
BRI_QRIS_TIMEOUT=30
```

## Usage

```php
use Elgibor\BriQris\BriQris;

$qr = app(BriQris::class)->generateQr(
    partnerReferenceNo: 'INV-2025-0001',
    amount: '10000.00',
    currency: 'IDR'
);
// $qr->qrContent, $qr->referenceNo

$status = app(BriQris::class)->inquiryPayment(
    originalReferenceNo: $qr->referenceNo,
    terminalId: config('bri_qris.terminal_id')
);
// $status->latestTransactionStatus === '00' for success
```

### Webhook (Notification)

Package registers `POST /bri/qris/notify` route (configurable). Implement your own logic by listening to event:

```php
use Elgibor\BriQris\Events\QrisPaymentNotified;

Event::listen(QrisPaymentNotified::class, function($event) {
    // $event->payload (array), $event->headers (array), $event->validSignature (bool)
});
```

Or publish controller and override `handle()` method.

## Security Notes
- B2B token endpoint requires **RSA SHA256** signature in `X-SIGNATURE` and `X-CLIENT-KEY` headers, with timestamp.
- Business endpoints use **HMAC SHA512** symmetric signature with `client_secret` over the canonical string.
- Always ensure timestamps are ISO8601 with timezone offset.
- Never log secrets; rotate keys regularly.

## License
MIT
