<?php

declare(strict_types=1);

/**
 * Reva Magento 2
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 *
 * @category   Reva
 * @package    Reva_Core
 * @author     Extension Team
 * @copyright  © 2024 - RevaMagento - All rights reserved
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Reva_Core', __DIR__);
