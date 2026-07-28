<?php

namespace Database\Seeders;

use App\Models\PaymentSetting;
use App\PaymentProvider;
use Illuminate\Database\Seeder;

class PaymentSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = PaymentSetting::query()->firstOrNew(['id' => PaymentSetting::SINGLETON_ID]);

        if (! $settings->exists) {
            $settings->fill([
                'provider' => PaymentProvider::Manual->value,
                'is_production' => (bool) config('services.midtrans.is_production', false),
                'merchant_id' => config('services.midtrans.merchant_id')
                    ?: config('services.xendit.merchant_id'),
                'client_key' => config('services.midtrans.client_key')
                    ?: config('services.xendit.callback_token'),
                'server_key' => config('services.midtrans.server_key')
                    ?: config('services.xendit.secret_key'),
            ]);
            $settings->save();
        }
    }
}
