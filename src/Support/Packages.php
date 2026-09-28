<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Support;

use Omniship\Common\Package;

final class Packages
{
    /**
     * Navlungo bills on the shipment-level desi and counts pieces, so fold
     * every package (with its quantity) into those two numbers. Packages
     * without dimensions fall back to their weight.
     *
     * @param Package[] $packages
     * @return array{0: float, 1: int}
     */
    public static function summarize(array $packages): array
    {
        $desi = 0.0;
        $count = 0;

        foreach ($packages as $package) {
            $quantity = max(1, $package->quantity);
            $desi += (float) ($package->getDesi() ?? $package->weight) * $quantity;
            $count += $quantity;
        }

        return [round($desi, 2), $count];
    }
}
