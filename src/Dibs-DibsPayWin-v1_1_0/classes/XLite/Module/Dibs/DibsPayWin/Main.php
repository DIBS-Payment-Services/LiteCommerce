<?php

/**
 * Dibs A/S
 * Dibs Payment Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   Payments & Gateways Extensions
 * @package    Dibspw_Dibspw
 * @author     Dibs A/S
 * @copyright  Copyright (c) 2012 Dibs A/S. (http://www.dibs.dk/)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


namespace XLite\Module\Dibs\DibsPayWin;

/**
 * Authorize.Net SIM module
 *
 */
abstract class Main extends \XLite\Module\AModule
{
    /**
     * Author name
     *
     * @return string
     */
    public static function getAuthorName()
    {
        return 'Dibs';
    }

    /**
     * Module name
     *
     * @return string
     */
    public static function getModuleName()
    {
        return 'DibsPayWin';
    }

    /**
     * Get module major version
     *
     * @return string
     */
    
    public static function getMajorVersion()
    {
        return '1.1';
    }

    /**
     * Module version
     *
     * @return string
     */
    public static function getMinorVersion()
    {
        return '0';
    }

    /**
     * Module description
     *
     * @return string
     */
    public static function getDescription()
    {
        return 'DIBS Payment Window Module. Accept all types of payments from DIBS - Secure Internet Payment Service Provider';
    }
}
