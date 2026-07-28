<?php

namespace App\Models;

use App\PaymentProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSetting extends Model
{
    public const SINGLETON_ID = 1;

    protected $fillable = [
        'provider',
        'is_production',
        'merchant_id',
        'client_key',
        'server_key',
        'updated_by',
    ];

    protected $attributes = [
        'provider' => 'manual',
        'is_production' => false,
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => self::SINGLETON_ID],
            [
                'provider' => PaymentProvider::Manual->value,
                'is_production' => false,
            ],
        );
    }

    public function usesMidtrans(): bool
    {
        return $this->provider === PaymentProvider::Midtrans;
    }

    public function usesXendit(): bool
    {
        return $this->provider === PaymentProvider::Xendit;
    }

    public function usesManual(): bool
    {
        return $this->provider === PaymentProvider::Manual;
    }

    public function isConfigured(): bool
    {
        if ($this->usesManual()) {
            return true;
        }

        if ($this->usesMidtrans()) {
            return filled($this->client_key) && filled($this->server_key);
        }

        if ($this->usesXendit()) {
            // Secret key required; callback token stored in client_key.
            return filled($this->server_key) && filled($this->client_key);
        }

        return false;
    }

    public function maskedServerKey(): ?string
    {
        if (! filled($this->server_key)) {
            return null;
        }

        $key = (string) $this->server_key;
        $suffix = strlen($key) <= 4 ? $key : substr($key, -4);

        return '****'.$suffix;
    }

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'is_production' => 'boolean',
            'server_key' => 'encrypted',
        ];
    }
}
