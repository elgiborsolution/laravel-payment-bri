<?php

namespace ESolution\BriPayments\Support;

use Illuminate\Support\Facades\Cache;
use ESolution\BriPayments\Models\BriClient;

class BriConfig
{
    /**
     * Get BRIVA/SNAP/Qris configuration for a tenant.
     */
    public static function for(string $tenantId): array
    {
        try {
            $client = Cache::remember(
                "bri_client_{$tenantId}",
                3600,
                fn () => BriClient::where('tenant_id', $tenantId)->first()
            );
        } catch (\Exception $e) {
            // Jika tabel tidak ada / migrasi belum jalan / koneksi error
            return config('bri');
        }

        if ($client) {
            return [
                'base_url' => $client->base_url ?? 'https://sandbox.partner.api.bri.co.id',
                'common' => [
                    'client_id'     => $client->client_id,
                    'client_secret' => $client->client_secret,
                    'private_key'   => $client->private_key,
                ],
                'qris' => [
                    'partner_id' => $client->qris_partner_id,
                    'channel_id' => $client->qris_channel_id ?? '95221',
                    'merchant_id'=> $client->qris_merchant_id,
                    'terminal_id'=> $client->qris_terminal_id,
                    'public_key' => $client->qris_public_key,
                    'timeout'    => config('bri.qris.timeout', 30),
                ],
                'briva' => [
                    'partner_service_id' => $client->briva_partner_service_id,
                    'partner_id'         => $client->briva_partner_id,
                    'channel_id'         => $client->briva_channel_id,
                    'public_key'         => $client->briva_public_key,
                    'timeout'            => config('bri.briva.timeout', 30),
                ],
            ];
        }

        return config('bri');
    }
}
