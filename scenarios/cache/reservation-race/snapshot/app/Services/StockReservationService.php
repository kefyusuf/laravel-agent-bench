<?php

namespace App\Services;

use Redis;

class StockReservationService
{
    private Redis $redis;

    public function __construct()
    {
        $target = (string) config('database.redis.race_host', 'redis:6379');
        [$host, $port] = array_map('trim', explode(':', $target));

        $this->redis = new Redis;
        $this->redis->connect($host, (int) $port);
    }

    public function redis(): Redis
    {
        return $this->redis;
    }

    public function stock(string $sku): int
    {
        return (int) $this->redis->get("stock:{$sku}");
    }

    public function granted(string $sku): int
    {
        return (int) $this->redis->scard("reserved:{$sku}");
    }

    /**
     * Grants a unit of the SKU's stock: true + a recorded reservation when a
     * unit is available, false when the SKU is sold out.
     */
    public function reserve(string $sku): bool
    {
        $stock = (int) $this->redis->get("stock:{$sku}");

        if ($stock <= 0) {
            return false;
        }

        $this->redis->decr("stock:{$sku}");
        $this->redis->sadd("reserved:{$sku}", uniqid('', true));

        return true;
    }
}
