<?php

declare(strict_types=1);

namespace Omniship\Navlungo\Message;

/**
 * Update answers carry the same post shape as create responses (the post
 * object nested under `data`), so the parsing is shared.
 */
class UpdateShipmentResponse extends CreateShipmentResponse
{
}
