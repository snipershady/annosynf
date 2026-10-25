<?php

declare(strict_types=1);

namespace App\Domain\CodingPotential;

/**
 * Modello libsvm 3.18 (file scritto da svm-train), limitato a quello che usa CPC2: C-SVC a 2 classi, kernel
 * RBF, con i parametri di Platt per le probabilità.
 */
final readonly class LibsvmModel
{
    private const string FILE = 'libsvm model';

    /**
     * @param array{int, int}         $labels         etichette nell'ordine del modello (riga `label`)
     * @param list<float>             $coefficients   coefficiente di ogni vettore di supporto
     * @param list<array<int, float>> $supportVectors vettori di supporto sparsi: indice della feature → valore
     */
    public function __construct(
        public float $gamma,
        public float $rho,
        public array $labels,
        public float $probA,
        public float $probB,
        public array $coefficients,
        public array $supportVectors,
    ) {
    }

    /**
     * Legge il modello come svm_load_model.
     *
     * @param iterable<string> $lines
     *
     * @throws InvalidLibsvmFileException
     */
    public static function parse(iterable $lines): self
    {
        $header = [];
        $coefficients = [];
        $supportVectors = [];
        $inVectors = false;
        $number = 0;

        foreach ($lines as $line) {
            ++$number;
            $line = trim($line);

            if ($inVectors) {
                if ($line === '') {
                    continue;
                }

                $fields = self::fields($line);
                $coefficients[] = self::number(array_shift($fields) ?? '', $number);
                $supportVectors[] = self::features($fields, $number);

                continue;
            }

            if ($line === 'SV') {
                $inVectors = true;

                continue;
            }

            $fields = self::fields($line);
            $key = array_shift($fields);

            if ($key === null || $key === '' || isset($header[$key])) {
                throw InvalidLibsvmFileException::at(self::FILE, $number, \sprintf('unexpected header line "%s"', $line));
            }

            $header[$key] = $fields;
        }

        return self::build($header, $coefficients, $supportVectors);
    }

    /**
     * @param array<string, list<string>> $header
     * @param list<float>                 $coefficients
     * @param list<array<int, float>>     $supportVectors
     *
     * @throws InvalidLibsvmFileException
     */
    private static function build(array $header, array $coefficients, array $supportVectors): self
    {
        $expected = ['svm_type' => ['c_svc'], 'kernel_type' => ['rbf'], 'nr_class' => ['2']];

        foreach ($expected as $key => $value) {
            if (($header[$key] ?? null) !== $value) {
                throw InvalidLibsvmFileException::incomplete(self::FILE, \sprintf('only %s models are supported', implode(' ', array_map(static fn (string $key, array $value): string => $key . ' ' . implode(' ', $value), array_keys($expected), $expected))));
            }
        }

        $unknown = array_diff(array_keys($header), ['svm_type', 'kernel_type', 'nr_class', 'gamma', 'total_sv', 'rho', 'label', 'probA', 'probB', 'nr_sv']);

        if ($unknown !== []) {
            throw InvalidLibsvmFileException::incomplete(self::FILE, \sprintf('unsupported header "%s"', implode('", "', $unknown)));
        }

        $labels = array_map(self::integer(...), self::values($header, 'label', 2));
        $total = self::integer(self::values($header, 'total_sv', 1)[0]);
        $perClass = array_map(self::integer(...), self::values($header, 'nr_sv', 2));

        if ($supportVectors === [] || \count($supportVectors) !== $total || array_sum($perClass) !== $total) {
            throw InvalidLibsvmFileException::incomplete(self::FILE, \sprintf('%d support vectors found, total_sv %d, nr_sv %d', \count($supportVectors), $total, array_sum($perClass)));
        }

        return new self(
            self::headerNumber($header, 'gamma'),
            self::headerNumber($header, 'rho'),
            [$labels[0], $labels[1]],
            self::headerNumber($header, 'probA'),
            self::headerNumber($header, 'probB'),
            $coefficients,
            $supportVectors,
        );
    }

    /**
     * @param array<string, list<string>> $header
     *
     * @throws InvalidLibsvmFileException
     *
     * @return non-empty-list<string>
     */
    private static function values(array $header, string $key, int $count): array
    {
        $values = $header[$key] ?? [];

        if (\count($values) !== $count || $values === []) {
            throw InvalidLibsvmFileException::incomplete(self::FILE, \sprintf('"%s" needs %d value(s)', $key, $count));
        }

        return $values;
    }

    /**
     * @param array<string, list<string>> $header
     *
     * @throws InvalidLibsvmFileException
     */
    private static function headerNumber(array $header, string $key): float
    {
        $value = self::values($header, $key, 1)[0];

        if (!is_numeric($value)) {
            throw InvalidLibsvmFileException::incomplete(self::FILE, \sprintf('"%s" is not a number: "%s"', $key, $value));
        }

        return (float) $value;
    }

    /**
     * @param list<string> $fields coppie `indice:valore`
     *
     * @throws InvalidLibsvmFileException
     *
     * @return array<int, float>
     */
    private static function features(array $fields, int $number): array
    {
        $features = [];

        foreach ($fields as $field) {
            if (preg_match('/^([1-9]\d*):(\S+)$/', $field, $match) !== 1) {
                throw InvalidLibsvmFileException::at(self::FILE, $number, \sprintf('"%s" is not an index:value pair', $field));
            }

            $features[(int) $match[1]] = self::number($match[2], $number);
        }

        return $features;
    }

    /**
     * @throws InvalidLibsvmFileException
     */
    private static function number(string $value, int $number): float
    {
        if (!is_numeric($value)) {
            throw InvalidLibsvmFileException::at(self::FILE, $number, \sprintf('"%s" is not a number', $value));
        }

        return (float) $value;
    }

    /**
     * @throws InvalidLibsvmFileException
     */
    private static function integer(string $value): int
    {
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            throw InvalidLibsvmFileException::incomplete(self::FILE, \sprintf('"%s" is not an integer', $value));
        }

        return (int) $value;
    }

    /**
     * @return list<string>
     */
    private static function fields(string $line): array
    {
        $fields = preg_split('/\s+/', trim($line));

        return $fields === false ? [] : $fields;
    }
}
