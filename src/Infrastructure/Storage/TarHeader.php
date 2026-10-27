<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

/**
 * Header di una voce tar (blocco di 512 byte), nei formati ustar POSIX e GNU. Nomi lunghi e dimensioni oltre
 * gli 8 GiB arrivano da voci a parte (GNU `L`, pax `x`) o in base 256: li gestisce {@see StreamingArchiveExtractor}.
 */
final readonly class TarHeader
{
    public const int BLOCK = 512;

    /**
     * @param string $type flag di tipo: `0` file, `5` directory, `L` nome lungo GNU, `x`/`g` pax, …
     */
    public function __construct(
        public string $name,
        public int $size,
        public string $type,
    ) {
    }

    /**
     * @throws CorruptArchiveException se il checksum o i campi numerici non sono validi
     *
     * @return self|null null per un blocco di zeri (fine dell'archivio)
     */
    public static function parse(string $block, string $archive): ?self
    {
        if ($block === str_repeat("\0", self::BLOCK)) {
            return null;
        }

        // il checksum si calcola con il proprio campo pieno di spazi
        $sum = 0;

        foreach (count_chars(substr_replace($block, '        ', 148, 8), 1) as $byte => $occurrences) {
            $sum += $byte * $occurrences;
        }

        if (self::octal(substr($block, 148, 8), $archive) !== $sum) {
            throw CorruptArchiveException::corrupt($archive, 'tar header checksum mismatch');
        }

        $name = self::text(substr($block, 0, 100));
        $prefix = substr($block, 257, 6) === "ustar\0" ? self::text(substr($block, 345, 155)) : '';
        $type = $block[156] === "\0" ? '0' : $block[156];

        return new self($prefix !== '' ? $prefix . '/' . $name : $name, self::size(substr($block, 124, 12), $archive), $type);
    }

    /**
     * @throws CorruptArchiveException
     */
    private static function size(string $field, string $archive): int
    {
        if ((\ord($field[0]) & 0x80) === 0) {
            return self::octal($field, $archive);
        }

        // base 256 (GNU, star): big-endian senza il bit di marcatura
        $size = \ord($field[0]) & 0x7F;

        foreach (str_split(substr($field, 1)) as $byte) {
            $size = ($size << 8) | \ord($byte);
        }

        return $size;
    }

    /**
     * @throws CorruptArchiveException
     */
    private static function octal(string $field, string $archive): int
    {
        $digits = trim($field, " \0");

        if (preg_match('/^[0-7]+$/', $digits) !== 1) {
            throw CorruptArchiveException::corrupt($archive, \sprintf('invalid number "%s" in a tar header', addcslashes($field, "\0..\37")));
        }

        return (int) octdec($digits);
    }

    private static function text(string $field): string
    {
        $end = strpos($field, "\0");

        return $end === false ? $field : substr($field, 0, $end);
    }
}
