<?php

/**
 * @see       https://github.com/laminas/laminas-feed for the canonical source repository
 * @copyright https://github.com/laminas/laminas-feed/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-feed/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Feed\PubSubHubbub;

use Laminas\Escaper\Escaper;

class PubSubHubbub
{
    /**
     * Verification Modes
     */
    const VERIFICATION_MODE_SYNC  = 'sync';
    const VERIFICATION_MODE_ASYNC = 'async';

    /**
     * Subscription States
     */
    const SUBSCRIPTION_VERIFIED    = 'verified';
    const SUBSCRIPTION_NOTVERIFIED = 'not_verified';
    const SUBSCRIPTION_TODELETE    = 'to_delete';
    const SUBSCRIPTION_DENIED    = 'denied';

    const PROTOCOL04 = '0.4';
    const PROTOCOL03 = '0.3';

    /**
     * @var Escaper
     */
    protected static $escaper;

    /**
     * Set the Escaper instance
     *
     * If null, resets the instance
     */
    public static function setEscaper(Escaper $escaper = null)
    {
        static::$escaper = $escaper;
    }

    /**
     * Get the Escaper instance
     *
     * If none registered, lazy-loads an instance.
     *
     * @return Escaper
     */
    public static function getEscaper()
    {
        if (null === static::$escaper) {
            static::setEscaper(new Escaper());
        }
        return static::$escaper;
    }

    /**
     * RFC 3986 safe url encoding method
     *
     * @param  string $string
     * @return string
     */
    public static function urlencode($string)
    {
        $escaper    = static::getEscaper();
        $rawencoded = $escaper->escapeUrl($string);
        $rfcencoded = str_replace('%7E', '~', $rawencoded);
        return $rfcencoded;
    }
}
