<?php

declare(strict_types=1);

namespace App\Release;

use App\Application\Port\BlastDbBuilder;
use App\Application\Port\OrganismLookup;
use App\Application\Port\TextLines;
use App\Domain\Reference\ProteinSource;
use App\Domain\Reference\SourceKind;
use App\Domain\Sequence\FastaFormatter;
use App\Domain\Sequence\FastaReader;
use App\Domain\Sequence\FastaSubset;
use App\Entity\OrganismSet;
use App\Entity\OrganismSetMember;
use App\Entity\ReferenceBlastDb;
use App\Entity\ReferenceSourceFile;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use SplFileObject;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Database BLAST ristretti a un insieme di organismi (ex `SP_<file>.fasta` e `TR_<file>.fasta` di Annocript,
 * §A.7.10): il FASTA completo si legge una volta in streaming e si tengono le sequenze dei soli organismi
 * dell'insieme, che `faSomeRecords` otteneva da un file di id. Il FASTA ristretto serve solo a `makeblastdb` e si
 * cancella subito.
 */
final readonly class OrganismSetDatabaseBuilder
{
    public function __construct(
        private OrganismLookup $lookup,
        private TextLines $lines,
        private BlastDbBuilder $databases,
        private BlastDatabaseRegistry $registry,
        private ReleaseStorage $storage,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<string> nomi dei database costruiti; una sorgente senza sequenze degli organismi non ne ha uno
     */
    public function build(OrganismSet $set): array
    {
        $release = $set->getRelease();
        $releaseId = (int) $release->getId();
        $values = array_map(static fn (OrganismSetMember $member): string => $member->getValue(), $set->getMembers());
        $built = [];

        if ($values === []) {
            return $built;
        }

        foreach ($this->entityManager->getRepository(ReferenceBlastDb::class)->findBy(['release' => $release, 'organismSet' => null]) as $full) {
            $source = ProteinSource::tryFrom($full->getKind()->value);

            if ($source === null) {
                continue;
            }

            $name = \sprintf('%s_set%d', $full->getKind()->value, (int) $set->getId());
            $fasta = $this->storage->databasePath($releaseId, $name . '.fasta');
            $database = $this->storage->databasePath($releaseId, $name);

            try {
                if ($this->writeSubset($releaseId, $source, $set, $values, $fasta) === 0) {
                    continue;
                }

                $info = $this->databases->build($fasta, $database, $name, $full->getKind()->sequenceType());
            } finally {
                $this->storage->remove($fasta);
            }

            $this->registry->register($release, $full->getKind(), $name, $database, $info, $set);
            $built[] = $name;
        }

        $this->entityManager->flush();

        return $built;
    }

    /**
     * @param non-empty-list<string> $values
     *
     * @return int sequenze scritte
     */
    private function writeSubset(int $releaseId, ProteinSource $source, OrganismSet $set, array $values, string $destination): int
    {
        $sourceFile = $this->entityManager->getRepository(ReferenceSourceFile::class)->findOneBy(['release' => $set->getRelease(), 'kind' => $this->sourceKind($source)]);
        $path = $sourceFile?->getLocalPath() ?? throw new RuntimeException(\sprintf('The "%s" source has not been downloaded.', $source->value));
        $subset = new FastaSubset($this->lookup->sequenceIds($releaseId, $source, $set->getMatchMode(), $values));
        $formatter = new FastaFormatter();
        $count = 0;

        new Filesystem()->mkdir(\dirname($destination));
        $file = new SplFileObject($destination, 'w');

        foreach ($subset->select(new FastaReader()->read($this->lines->lines($path))) as $record) {
            $file->fwrite($formatter->format($record));
            ++$count;
        }

        return $count;
    }

    private function sourceKind(ProteinSource $source): SourceKind
    {
        return match ($source) {
            ProteinSource::SwissProt => SourceKind::SwissProt,
            ProteinSource::TrEmbl => SourceKind::TrEmbl,
            ProteinSource::UniRef => SourceKind::UniRef,
        };
    }
}
