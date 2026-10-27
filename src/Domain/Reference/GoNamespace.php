<?php

declare(strict_types=1);

namespace App\Domain\Reference;

/**
 * Ontologia di un termine GO, con la lettera usata da Annocript (colonna `division` di goTable).
 */
enum GoNamespace: string
{
    case BiologicalProcess = 'P';
    case MolecularFunction = 'F';
    case CellularComponent = 'C';

    /**
     * Dal valore del tag `namespace:` dell'OBO; null per gli altri namespace.
     */
    public static function fromOboName(string $name): ?self
    {
        return match ($name) {
            'biological_process' => self::BiologicalProcess,
            'molecular_function' => self::MolecularFunction,
            'cellular_component' => self::CellularComponent,
            default => null,
        };
    }
}
