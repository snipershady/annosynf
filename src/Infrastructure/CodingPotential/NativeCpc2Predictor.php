<?php

declare(strict_types=1);

namespace App\Infrastructure\CodingPotential;

use App\Application\Port\CodingPotentialPredictor;
use App\Application\Port\TextLines;
use App\Domain\CodingPotential\Cpc2Prediction;
use App\Domain\CodingPotential\Cpc2Predictor;
use App\Domain\CodingPotential\LibsvmModel;
use App\Domain\CodingPotential\LibsvmPredictor;
use App\Domain\CodingPotential\LibsvmScaler;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Port nativo di CPC2 con il modello SVM versionato in resources/cpc2 (`cpc2.model`, `cpc2.range`), caricato
 * alla prima predizione e poi riusato.
 */
final class NativeCpc2Predictor implements CodingPotentialPredictor
{
    private ?Cpc2Predictor $predictor = null;

    public function __construct(
        private readonly TextLines $files,
        #[Autowire('%kernel.project_dir%/resources/cpc2')]
        private readonly string $modelDirectory,
    ) {
    }

    #[Override]
    public function predict(string $sequence): Cpc2Prediction
    {
        return ($this->predictor ??= $this->load())->predict($sequence);
    }

    private function load(): Cpc2Predictor
    {
        return new Cpc2Predictor(
            LibsvmScaler::parse($this->files->lines($this->modelDirectory . '/cpc2.range')),
            new LibsvmPredictor(LibsvmModel::parse($this->files->lines($this->modelDirectory . '/cpc2.model'))),
        );
    }
}
