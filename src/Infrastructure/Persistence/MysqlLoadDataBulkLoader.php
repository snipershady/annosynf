<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\BulkLoader;
use App\Infrastructure\Storage\FileNotWritableException;
use App\Infrastructure\Storage\PendingFile;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use InvalidArgumentException;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * {@see BulkLoader} con `LOAD DATA LOCAL INFILE`: le righe vanno in un TSV temporaneo nella directory che la
 * connessione può leggere (`Pdo\Mysql::ATTR_LOCAL_INFILE_DIRECTORY` in doctrine.yaml) e MySQL lo carica in una sola
 * istruzione.
 *
 * Con `LOCAL` MySQL non si ferma sugli errori dei dati: salta le chiavi duplicate e tronca o converte i valori non
 * validi, segnalandoli solo come avvisi. Qui ogni avviso è un errore. Le righe già caricate restano finché la
 * transazione del chiamante non viene annullata: i task di import sono idempotenti (cancellano e ricaricano).
 */
final readonly class MysqlLoadDataBulkLoader implements BulkLoader
{
    private const string IDENTIFIER = '/^[a-z][a-z0-9_]{0,63}$/';

    /** Sequenze di escape del formato di default di LOAD DATA (`ESCAPED BY '\\'`). */
    private const array ESCAPES = ['\\' => '\\\\', "\t" => '\t', "\n" => '\n', "\r" => '\r', "\0" => '\0'];

    private const int REPORTED_WARNINGS = 3;

    public function __construct(
        private Connection $connection,
        #[Autowire(param: 'annosynf.bulk_dir')]
        private string $directory,
    ) {
    }

    /**
     * @throws InvalidArgumentException se tabella o colonne non sono nomi interni validi, o una riga non ha un valore
     *                                  per colonna
     * @throws FileNotWritableException se non si può scrivere il file temporaneo
     * @throws BulkLoadFailedException  se MySQL rifiuta il caricamento o segnala avvisi
     */
    #[Override]
    public function load(string $table, array $columns, iterable $rows): int
    {
        foreach ([$table, ...$columns] as $identifier) {
            if (preg_match(self::IDENTIFIER, $identifier) !== 1) {
                throw new InvalidArgumentException(\sprintf('"%s" is not a valid table or column name.', $identifier));
            }
        }

        $path = $this->write(\count($columns), $rows, $count);

        try {
            return $count === 0 ? 0 : $this->loadFile($path, $table, $columns);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param non-empty-list<non-empty-string> $columns
     *
     * @throws BulkLoadFailedException
     */
    private function loadFile(string $path, string $table, array $columns): int
    {
        $sql = \sprintf(
            "LOAD DATA LOCAL INFILE %s INTO TABLE %s CHARACTER SET utf8mb4 FIELDS TERMINATED BY '\\t' ESCAPED BY '\\\\' LINES TERMINATED BY '\\n' (%s)",
            $this->connection->quote($path),
            $table,
            implode(', ', $columns),
        );

        try {
            $loaded = (int) $this->connection->executeStatement($sql);
            $warnings = $this->connection->fetchOne('SHOW COUNT(*) WARNINGS');

            if (is_numeric($warnings) && $warnings > 0) {
                $messages = array_column($this->connection->fetchAllAssociative('SHOW WARNINGS LIMIT ' . self::REPORTED_WARNINGS), 'Message');

                throw BulkLoadFailedException::warnings($table, (int) $warnings, array_values(array_filter($messages, \is_string(...))));
            }
        } catch (DbalException $dbalException) {
            throw BulkLoadFailedException::rejected($table, $dbalException);
        }

        return $loaded;
    }

    /**
     * Scrive il TSV nella directory leggibile dalla connessione.
     *
     * @param positive-int                               $columns
     * @param iterable<list<string|int|float|bool|null>> $rows
     *
     * @param-out int                                    $count   righe scritte
     *
     * @throws FileNotWritableException
     *
     * @return string percorso del file
     */
    private function write(int $columns, iterable $rows, ?int &$count): string
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0o770, true);
        }

        $path = \sprintf('%s/bulk-%s.tsv', $this->directory, bin2hex(random_bytes(8)));
        $file = PendingFile::create($path);
        $count = 0;

        try {
            foreach ($rows as $row) {
                if (\count($row) !== $columns) {
                    throw new InvalidArgumentException(\sprintf('Row %d has %d values for %d columns.', $count + 1, \count($row), $columns));
                }

                $file->write(implode("\t", array_map($this->field(...), $row)) . "\n");
                ++$count;
            }

            $file->commit();
        } finally {
            $file->discard();
        }

        return $path;
    }

    private function field(string|int|float|bool|null $value): string
    {
        return match (true) {
            $value === null => '\N',
            \is_bool($value) => $value ? '1' : '0',
            \is_float($value) && !is_finite($value) => throw new InvalidArgumentException(\sprintf('%s cannot be stored.', $value)),
            \is_string($value) => strtr($value, self::ESCAPES),
            default => (string) $value,
        };
    }
}
