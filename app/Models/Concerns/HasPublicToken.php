<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model an unguessable token used in the public (QR code) URL.
 */
trait HasPublicToken
{
    public static function bootHasPublicToken(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_token)) {
                $model->public_token = Str::random(40);
            }
        });
    }

    public function ensurePublicToken(): string
    {
        if (empty($this->public_token)) {
            $this->forceFill(['public_token' => Str::random(40)])->saveQuietly();
        }

        return $this->public_token;
    }

    abstract public function publicRouteName(): string;

    public function getPublicUrlAttribute(): ?string
    {
        return $this->public_token ? route($this->publicRouteName(), $this->public_token) : null;
    }
}
