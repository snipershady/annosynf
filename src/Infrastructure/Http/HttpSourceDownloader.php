<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Port\SourceDownloader;
use App\Domain\Reference\SourceFile;
use App\Domain\Reference\SourceMirrors;
use App\Infrastructure\Storage\FileNotReadableException;
use App\Infrastructure\Storage\FileNotWritableException;
use HashContext;
use LogicException;
use Override;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Download delle sorgenti con HttpClient in streaming su file (sostituisce LWP, Net::FTP, File::Fetch e curl del
 * legacy).
 *
 * Il file cresce in `<destinazione>.part` e prende il nome definitivo solo quando è completo. Se il server indica
 * un validatore (ETag forte o Last-Modified) lo si salva in `<destinazione>.part.validator`: un download
 * interrotto, anche da un processo precedente, riprende con `Range` + `If-Range`, e se il file sul server è
 * cambiato il server risponde 200 e si riparte da zero. Ogni URL si riprova `$attempts` volte (attesa crescente),
 * poi si passa ai mirror ({@see SourceMirrors}).
 */
final readonly class HttpSourceDownloader implements SourceDownloader
{
    private const string PART = '.part';

    private const string VALIDATOR = '.part.validator';

    /**
     * @param positive-int $attempts    tentativi per URL (il legacy ne faceva due con LWP)
     * @param float        $retryDelay  secondi di attesa dopo il primo tentativo fallito, moltiplicati per il
     *                                  numero del tentativo
     * @param float        $idleTimeout secondi senza dati dopo i quali la connessione si considera caduta
     */
    public function __construct(
        private HttpClientInterface $client,
        // il servizio `clock` è autowirable solo come PSR-20, che non ha sleep()
        #[Autowire(service: 'clock')]
        private ClockInterface $clock,
        private SourceMirrors $mirrors = new SourceMirrors(),
        private int $attempts = 3,
        private float $retryDelay = 5.0,
        private float $idleTimeout = 120.0,
    ) {
    }

    /**
     * @throws SourceDownloadFailedException se nessun server fornisce il file completo
     * @throws FileNotWritableException
     * @throws FileNotReadableException
     */
    #[Override]
    public function download(string $url, string $destination): SourceFile
    {
        if (is_file($destination)) {
            return new SourceFile($url, $destination, $this->size($destination), $this->hashFile($destination), true);
        }

        $directory = \dirname($destination);

        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw FileNotWritableException::at($destination);
        }

        $candidates = $this->mirrors->candidates($url);
        $last = null;

        foreach ($candidates as $candidate) {
            for ($attempt = 1; $attempt <= $this->attempts; ++$attempt) {
                try {
                    return $this->fetch($candidate, $destination);
                } catch (SourceDownloadFailedException $exception) {
                    $last = $exception;
                }

                if (!$last->retryable) {
                    break;
                }

                if ($attempt < $this->attempts) {
                    $this->clock->sleep($this->retryDelay * $attempt);
                }
            }
        }

        // almeno un candidato e almeno un tentativo: $last è sempre valorizzato
        throw SourceDownloadFailedException::exhausted($candidates, $last ?? throw new LogicException('No download attempt was made.'));
    }

    /**
     * @throws SourceDownloadFailedException
     * @throws FileNotWritableException
     * @throws FileNotReadableException
     */
    private function fetch(string $url, string $destination): SourceFile
    {
        $part = $destination . self::PART;
        $validatorFile = $destination . self::VALIDATOR;
        $validator = is_file($part) && is_file($validatorFile) ? (string) file_get_contents($validatorFile) : '';
        $offset = $validator !== '' ? $this->size($part) : 0;

        try {
            $response = $this->client->request('GET', $url, [
                // senza, HttpClient chiede gzip e lo decodifica: Range e dimensioni si riferirebbero ai byte compressi
                'headers' => ['Accept-Encoding' => 'identity', ...($offset > 0 ? ['Range' => \sprintf('bytes=%d-', $offset), 'If-Range' => $validator] : [])],
                'timeout' => $this->idleTimeout,
                'buffer' => false,
            ]);

            return $this->receive($url, $response, $offset, $destination);
        } catch (TransportExceptionInterface $transportException) {
            throw SourceDownloadFailedException::transport($url, $transportException);
        }
    }

    /**
     * @throws TransportExceptionInterface
     * @throws SourceDownloadFailedException
     * @throws FileNotWritableException
     * @throws FileNotReadableException
     */
    private function receive(string $url, ResponseInterface $response, int $offset, string $destination): SourceFile
    {
        $part = $destination . self::PART;
        $status = $response->getStatusCode();
        $headers = $response->getHeaders(false);
        $contentRange = $headers['content-range'][0] ?? '';

        if ($status === 416 && $offset > 0) {
            $response->cancel();

            if ($contentRange === \sprintf('bytes */%d', $offset)) {
                // il .part era già completo: è mancata solo la rinomina
                return $this->complete($url, $destination, $this->hashContext($part));
            }

            $this->forget($destination);

            throw SourceDownloadFailedException::unexpectedRange($url, $contentRange);
        }

        if ($status === 206) {
            if (preg_match('/^bytes (\d+)-\d+\/(\d+|\*)$/', $contentRange, $range) !== 1 || (int) $range[1] !== $offset) {
                $response->cancel();
                $this->forget($destination);

                throw SourceDownloadFailedException::unexpectedRange($url, $contentRange);
            }

            $expected = $range[2] === '*' ? null : (int) $range[2];
        } elseif ($status === 200) {
            $offset = 0;
            $expected = isset($headers['content-length'][0]) ? (int) $headers['content-length'][0] : null;
        } else {
            $response->cancel();

            throw SourceDownloadFailedException::status($url, $status);
        }

        $this->rememberValidator($destination, $headers);
        $hash = $offset > 0 ? $this->hashContext($part) : hash_init('sha256');
        $handle = @fopen($part, $offset > 0 ? 'ab' : 'wb');

        if ($handle === false) {
            throw FileNotWritableException::at($part);
        }

        try {
            foreach ($this->client->stream($response) as $chunk) {
                $data = $chunk->getContent();

                if (@fwrite($handle, $data) !== \strlen($data)) {
                    throw FileNotWritableException::at($part);
                }

                hash_update($hash, $data);
            }
        } finally {
            fclose($handle);
        }

        $received = $this->size($part);

        if ($expected !== null && $received !== $expected) {
            throw SourceDownloadFailedException::incomplete($url, $received, $expected);
        }

        return $this->complete($url, $destination, $hash);
    }

    /**
     * @throws FileNotWritableException
     * @throws FileNotReadableException
     */
    private function complete(string $url, string $destination, HashContext $hash): SourceFile
    {
        if (!@rename($destination . self::PART, $destination)) {
            throw FileNotWritableException::at($destination);
        }

        @unlink($destination . self::VALIDATOR);

        return new SourceFile($url, $destination, $this->size($destination), hash_final($hash), false);
    }

    /**
     * Senza un validatore un .part non si può riprendere in sicurezza: il prossimo tentativo riparte da zero.
     *
     * @param array<string, list<string>> $headers
     *
     * @throws FileNotWritableException
     */
    private function rememberValidator(string $destination, array $headers): void
    {
        $etag = $headers['etag'][0] ?? '';
        // un ETag debole non vale in If-Range (RFC 9110 §13.1.5)
        $validator = $etag !== '' && !str_starts_with($etag, 'W/') ? $etag : $headers['last-modified'][0] ?? '';

        if ($validator === '') {
            @unlink($destination . self::VALIDATOR);

            return;
        }

        if (@file_put_contents($destination . self::VALIDATOR, $validator) === false) {
            throw FileNotWritableException::at($destination . self::VALIDATOR);
        }
    }

    private function forget(string $destination): void
    {
        @unlink($destination . self::PART);
        @unlink($destination . self::VALIDATOR);
    }

    /**
     * @throws FileNotReadableException
     */
    private function hashContext(string $path): HashContext
    {
        $hash = hash_init('sha256');

        if (!@hash_update_file($hash, $path)) {
            throw FileNotReadableException::at($path);
        }

        return $hash;
    }

    /**
     * @throws FileNotReadableException
     */
    private function hashFile(string $path): string
    {
        return hash_final($this->hashContext($path));
    }

    /**
     * Dimensione di un file che esiste (chi chiama lo ha appena verificato o creato).
     */
    private function size(string $path): int
    {
        clearstatcache(true, $path);

        return (int) filesize($path);
    }
}
