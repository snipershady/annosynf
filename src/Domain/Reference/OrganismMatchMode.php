<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Come i valori di un insieme di organismi si confrontano con le proteine (ex `blastedOrganism`, §A.7.10):
 * il legacy conosce solo il nome esatto e sensibile alle maiuscole.
 */
enum OrganismMatchMode: string
{
    case ExactName = 'name_exact';
    case CaseInsensitiveName = 'name_ci';
    case TaxonId = 'taxon_id';
}
