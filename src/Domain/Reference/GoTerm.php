<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Stanza `[Term]` di un file OBO della Gene Ontology.
 */
final readonly class GoTerm
{
    /**
     * @param string       $goId   `GO:0000075`
     * @param list<string> $altIds identificativi secondari (`alt_id:`), nell'ordine del file
     */
    public function __construct(
        public string $goId,
        public string $name,
        public GoNamespace $namespace,
        public bool $isObsolete = false,
        public array $altIds = [],
    ) {
    }
}
