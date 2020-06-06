<?php

/* this file is part of pipelines */

namespace Ktomk\Pipelines;

/**
 * Class LibFsStream - stream utility functions
 *
 * @package Ktomk\Pipelines
 */
class LibFsStream
{
    /**
     * similar to a readable file, a readable path allows stream-urls
     * as well, e.g. php://stdin or php://fd/0 but only for local
     * streams.
     *
     * @param string $path
     *
     * @return bool
     */
    public static function isReadable($path)
    {
        if (LibFs::isReadableFile($path)) {
            return true;
        }

        return LibFs::canFopen($path, 'rb');
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public static function isUri($path)
    {
        $scheme = parse_url($path, PHP_URL_SCHEME);
        if (null === $scheme) {
            return false;
        }

        return in_array($scheme, stream_get_wrappers(), true);
    }

    /**
     * map a file path to a stream path (if applicable)
     *
     * some file paths aren't readable for PHP, e.g. "-" for
     * standard in or out or device files from process substitution.
     *
     * this method maps such paths to php:// stream uris that PHP can
     * process better (e.g. file_get_contents() works)
     *
     * @param string $file
     * @param null|string $dashRepresent use NULL to not do any '-' processing
     *
     * @return string original $file if there was no mapping, php:// URI otherwise
     */
    public static function mapFile($file, $dashRepresent = 'php://stdin')
    {
        if (self::isUri($file)) {
            return $file;
        }

        $path = $file;
        (null !== $dashRepresent) && ('-' === $file) && $path = $dashRepresent;

        /* @link https://bugs.php.net/bug.php?id=53465 */
        $path = preg_replace('(^/(?:proc/self|dev)/(fd/\d+))', 'php://\1', $path);

        return stream_is_local($path) ? $path : $file;
    }
}
