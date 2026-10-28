<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence;

use App\Infrastructure\Persistence\BulkLoadFailedException;
use App\Infrastructure\Persistence\MysqlLoadDataBulkLoader;
use App\Infrastructure\Storage\FileNotWritableException;
use App\Infrastructure\Storage\PendingFile;
use App\Tests\Support\DatabaseTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Generator;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(MysqlLoadDataBulkLoader::class)]
#[CoversClass(BulkLoadFailedException::class)]
#[UsesClass(PendingFile::class)]
#[UsesClass(FileNotWritableException::class)]
final class MysqlLoadDataBulkLoaderTest extends DatabaseTestCase
{
    private const array COLUMNS = ['id', 'name', 'note', 'score', 'flag'];

    private Connection $connection;

    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->entityManager->getConnection();
        // una tabella temporanea non chiude la transazione del test, a differenza di CREATE TABLE
        $this->connection->executeStatement('CREATE TEMPORARY TABLE bulk_probe (id INT UNSIGNED NOT NULL PRIMARY KEY, name VARCHAR(16) NOT NULL, note TEXT NULL, score DOUBLE NULL, flag TINYINT(1) NOT NULL)');

        $bulk = self::getContainer()->getParameter('annosynf.bulk_dir');
        self::assertIsString($bulk);
        $this->directory = $bulk . '/test-' . bin2hex(random_bytes(6));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TEMPORARY TABLE IF EXISTS bulk_probe');
        new Filesystem()->remove($this->directory);
        parent::tearDown();
    }

    public function testRowsArriveExactlyAsTheyWere(): void
    {
        $rows = [
            [1, 'tab	and \ slash', "line\nbreak\r\nand nul \0 end", 1.0E-5, true],
            [2, 'Ångström αβγ', '', 0.1, false],
            [3, '\N', null, null, false],
            [4, '42', 'NULL', -3.25, true],
        ];

        self::assertSame(4, $this->loader()->load('bulk_probe', self::COLUMNS, $this->generate($rows)));

        // JSON_ARRAY conserva i tipi di MySQL, che PDO restituirebbe come stringhe
        $loaded = array_map(
            static fn (mixed $row): mixed => json_decode(\is_string($row) ? $row : '', true, flags: \JSON_THROW_ON_ERROR),
            $this->connection->fetchFirstColumn('SELECT JSON_ARRAY(id, name, note, score, IF(flag, CAST(TRUE AS JSON), CAST(FALSE AS JSON))) FROM bulk_probe ORDER BY id'),
        );

        self::assertSame($rows, $loaded);
        self::assertSame([], $this->leftovers(), 'the temporary file is removed');
    }

    public function testNoRowsLoadNothing(): void
    {
        self::assertSame(0, $this->loader()->load('bulk_probe', self::COLUMNS, []));
        self::assertSame([], $this->leftovers());
    }

    public function testADuplicateKeyIsAnErrorEvenThoughMysqlOnlyWarns(): void
    {
        try {
            $this->loader()->load('bulk_probe', self::COLUMNS, [[1, 'a', null, null, 0], [1, 'b', null, null, 0]]);
            self::fail('duplicate accepted');
        } catch (BulkLoadFailedException $bulkLoadFailedException) {
            self::assertStringContainsString('Loading rows into "bulk_probe" raised 1 warning(s): Duplicate entry \'1\'', $bulkLoadFailedException->getMessage());
        }

        self::assertSame([], $this->leftovers());
    }

    public function testAnInvalidOrTruncatedValueIsAnError(): void
    {
        foreach (["Incorrect DOUBLE value: 'not a number' for column 'score'" => [1, 'a', 'not a number', 0], "Data truncated for column 'name'" => [1, str_repeat('n', 17), null, 0]] as $warning => $row) {
            try {
                $this->loader()->load('bulk_probe', ['id', 'name', 'score', 'flag'], [$row]);
                self::fail($warning);
            } catch (BulkLoadFailedException $exception) {
                self::assertStringContainsString($warning, $exception->getMessage());
            }
        }
    }

    public function testARejectedLoadIsReported(): void
    {
        try {
            $this->loader()->load('missing_table', ['id'], [[1]]);
            self::fail('missing table accepted');
        } catch (BulkLoadFailedException $bulkLoadFailedException) {
            self::assertStringStartsWith('Loading rows into "missing_table" failed: ', $bulkLoadFailedException->getMessage());
            self::assertInstanceOf(DbalException::class, $bulkLoadFailedException->getPrevious());
        }
    }

    public function testTheConnectionReadsNoFileOutsideTheBulkDirectory(): void
    {
        $this->expectException(DbalException::class);

        $this->connection->executeStatement(\sprintf('LOAD DATA LOCAL INFILE %s INTO TABLE bulk_probe (id)', $this->connection->quote(\dirname(__DIR__, 4) . '/composer.json')));
    }

    public function testNamesMustBeInternalIdentifiers(): void
    {
        foreach (['bulk_probe; DROP TABLE user', 'Bulk', '1bulk'] as $table) {
            try {
                $this->loader()->load($table, ['id'], [[1]]);
                self::fail($table);
            } catch (InvalidArgumentException $exception) {
                self::assertStringEndsWith('is not a valid table or column name.', $exception->getMessage());
            }
        }

        $this->expectException(InvalidArgumentException::class);

        $this->loader()->load('bulk_probe', ['id', 'name`'], [[1, 'a']]);
    }

    public function testEachRowNeedsOneValuePerColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Row 2 has 1 values for 2 columns.');

        $this->loader()->load('bulk_probe', ['id', 'name'], [[1, 'a'], [2]]);
    }

    public function testInfiniteNumbersCannotBeStored(): void
    {
        try {
            $this->loader()->load('bulk_probe', ['id', 'name', 'score', 'flag'], [[1, 'a', \INF, 0]]);
            self::fail('INF accepted');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('INF cannot be stored.', $invalidArgumentException->getMessage());
        }

        self::assertSame([], $this->leftovers(), 'the partial file is discarded');
    }

    public function testAnUnwritableDirectoryIsReported(): void
    {
        $this->expectException(FileNotWritableException::class);

        new MysqlLoadDataBulkLoader($this->connection, '/dev/null/bulk')->load('bulk_probe', ['id'], [[1]]);
    }

    private function loader(): MysqlLoadDataBulkLoader
    {
        return new MysqlLoadDataBulkLoader($this->connection, $this->directory);
    }

    /**
     * @param list<list<string|int|float|bool|null>> $rows
     *
     * @return Generator<int, list<string|int|float|bool|null>>
     */
    private function generate(array $rows): Generator
    {
        yield from $rows;
    }

    /**
     * @return list<string>
     */
    private function leftovers(): array
    {
        $entries = is_dir($this->directory) ? scandir($this->directory) : [];

        return array_values(array_diff(\is_array($entries) ? $entries : [], ['.', '..']));
    }
}
