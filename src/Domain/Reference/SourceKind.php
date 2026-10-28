<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * File sorgente di una release (le chiavi `*Link` di `program_config.txt`, CLAUDE.md §B.10).
 */
enum SourceKind: string
{
    case ReleaseDate = 'reldate';
    case UnirefReleaseNote = 'uniref_release_note';
    case SwissProt = 'swissprot';
    case TrEmbl = 'trembl';
    case UniRef = 'uniref';
    case IdMapping = 'idmapping';
    case GeneOntology = 'go';
    case Enzyme = 'enzyme';
    case Pathway = 'pathway';
    case Speclist = 'speclist';
    case CddDatabase = 'cdd';
    case CddTable = 'cdd_table';
    case RnaCentral = 'rnacentral';
    case Pfam2Go = 'pfam2go';
}
