<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * File sorgente di una release su disco (reference_source_file, §B.4.1).
 */
final readonly class SourceFile
{
    /**
     * @param string $url    URL da cui è stato scaricato (un mirror, se il principale non rispondeva); quello
     *                       richiesto se il file c'era già
     * @param bool   $reused true se il file era già presente e non è stato riscaricato
     */
    public function __construct(
        public string $url,
        public string $path,
        public int $bytes,
        public string $sha256,
        public bool $reused,
    ) {
    }
}
