<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tabella nello stile di SymfonyStyle, disegnata con `Table`: `SymfonyStyle::table()` usa le console section, che il
 * `TestOutput` di Symfony 8.1 non supporta (CLAUDE.md, insidie dei tool).
 */
final class ConsoleTable
{
    /**
     * @param list<string>                      $headers
     * @param list<list<string|int|float|null>> $rows
     */
    public static function render(SymfonyStyle $io, array $headers, array $rows): void
    {
        $style = clone Table::getStyleDefinition('symfony-style-guide');
        $style->setCellHeaderFormat('<info>%s</info>');
        new Table($io)->setStyle($style)->setHeaders($headers)->setRows($rows)->render();
        $io->newLine();
    }
}
