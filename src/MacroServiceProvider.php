<?php

namespace LiveIntent\LaravelCommon;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class MacroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        Collection::macro('camelCaseKeys', function (): Collection {
            /** @var \Illuminate\Support\Collection $this */
            return $this->mapWithKeys(function ($value, $key) {
                if (is_array($value)) {
                    return [Str::camel($key) => collect($value)->camelCaseKeys()->toArray()];
                }

                return [Str::camel($key) => $value];
            });
        });

        Collection::macro('snakeCaseKeys', function (): Collection {
            /** @var \Illuminate\Support\Collection $this */
            return $this->mapWithKeys(function ($value, $key) {
                if (is_array($value)) {
                    return [Str::snake($key) => collect($value)->snakeCaseKeys()->toArray()];
                }

                return [Str::snake($key) => $value];
            });
        });
    }
}
