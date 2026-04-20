<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

/**
 * Generates human-friendly gift card codes.
 */
class CodeGenerator
{
    /**
     * Generate a new code.
     *
     * @param int $length Random characters length (before grouping)
     *
     * @return string
     * @throws \Exception
     */
    public function generate(int $length = 16): string
    {
        // Human-friendly, avoids 0/O and 1/I confusion.
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $max = strlen($alphabet) - 1;

        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        // Group like XXXX-XXXX-XXXX-XXXX
        return trim(chunk_split($out, 4, '-'), '-');
    }
}
