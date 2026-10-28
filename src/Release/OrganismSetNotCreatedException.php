<?php

declare(strict_types=1);

namespace App\Release;

use App\Domain\Shared\AnnosynfException;
use RuntimeException;

final class OrganismSetNotCreatedException extends RuntimeException implements AnnosynfException
{
    public static function releaseNotReady(int $releaseId, string $status): self
    {
        return new self(\sprintf('Release %d is %s: organism sets need a release whose import is complete.', $releaseId, $status));
    }

    public static function invalidName(string $name): self
    {
        return new self(\sprintf('"%s" is not a valid set name: use 1 to 128 characters.', $name));
    }

    public static function exists(string $name, int $releaseId): self
    {
        return new self(\sprintf('Release %d already has an organism set called "%s".', $releaseId, $name));
    }

    public static function noOrganisms(): self
    {
        return new self('The list holds no organism.');
    }

    public static function notTaxonIds(string $value): self
    {
        return new self(\sprintf('"%s" is not a taxon id: with --match=taxon_id each organism is a number.', $value));
    }

    public static function noProteins(): self
    {
        return new self('None of the organisms matches a protein of the release: check the spelling, or use --match=name_ci or --match=taxon_id.');
    }
}
