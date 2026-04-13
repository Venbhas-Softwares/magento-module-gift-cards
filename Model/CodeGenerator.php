<?php
declare(strict_types=1);

namespace Venbhas\GiftCard\Model;

class CodeGenerator
{
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

