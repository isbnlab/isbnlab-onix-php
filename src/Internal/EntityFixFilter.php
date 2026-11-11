<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Internal;

use php_user_filter;

/**
 * Stream filter that applies EntityFixer to a file as XMLReader reads it, holding back
 * a possibly cut-off entity at the end of each chunk.
 *
 * @internal
 */
final class EntityFixFilter extends php_user_filter
{
    public const NAME = 'isbnlab.onix.entities';

    private string $pending = '';

    public static function register(): void
    {
        if (!\in_array(self::NAME, stream_get_filters(), true)) {
            stream_filter_register(self::NAME, self::class);
        }
    }

    /**
     * @param resource $in
     * @param resource $out
     * @param int $consumed
     */
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        $data = $this->pending;
        $this->pending = '';
        while ($bucket = stream_bucket_make_writeable($in)) {
            $chunk = $bucket->data;
            if (\is_string($chunk)) {
                $data .= $chunk;
                $consumed += \strlen($chunk);
            }
        }

        if (!$closing) {
            $amp = strrpos($data, '&');
            if (false !== $amp && \strlen($data) - $amp < 40 && !str_contains(substr($data, $amp), ';')) {
                $this->pending = substr($data, $amp);
                $data = substr($data, 0, $amp);
            }
        }

        if ('' !== $data && \is_resource($this->stream)) {
            $bucket = stream_bucket_new($this->stream, EntityFixer::fix($data));
            stream_bucket_append($out, $bucket);
        }

        return \PSFS_PASS_ON;
    }
}
