<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function canViewProfit(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    protected function hideProfitFields(mixed $value): mixed
    {
        $hiddenKeys = [
            'cost_price',
            'cost_reversed',
            'net_profit',
            'profit',
            'retail_profit',
            'total_cost',
            'total_profit',
            'wholesale_profit',
        ];

        if ($value instanceof \Illuminate\Database\Eloquent\Model) {
            $value = $value->toArray();
        }

        if ($value instanceof \Illuminate\Support\Collection) {
            $value = $value->toArray();
        }

        if (!is_array($value)) {
            return $value;
        }

        foreach ($hiddenKeys as $key) {
            unset($value[$key]);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->hideProfitFields($item);
        }

        return $value;
    }
}
