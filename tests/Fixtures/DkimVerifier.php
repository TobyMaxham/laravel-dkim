<?php

declare(strict_types=1);

namespace TobyMaxham\LaravelDkimSigner\Tests\Fixtures;

use RuntimeException;

/**
 * A small, independent DKIM verifier (RFC 6376, relaxed/relaxed and simple/simple) for the tests.
 */
final class DkimVerifier
{
    /**
     * @return array{tags: array<string, string>, bodyHash: string, valid: bool}
     */
    public static function verify(string $raw, string $publicKeyPem): array
    {
        [$headerBlock, $body] = explode("\r\n\r\n", $raw, 2) + [1 => ''];
        $headers = self::headers($headerBlock);

        $signature = null;
        foreach ($headers as $header) {
            if ('dkim-signature' === strtolower($header[0])) {
                $signature = $header;
            }
        }

        if (null === $signature) {
            throw new RuntimeException('No DKIM-Signature header.');
        }

        $tags = self::tags($signature[1]);
        [$headerCanon, $bodyCanon] = explode('/', $tags['c'] ?? 'simple/simple') + [1 => 'simple'];

        $bodyHash = base64_encode(hash('sha256', self::canonBody($body, $bodyCanon), true));

        $data = '';
        $used = [];
        foreach (explode(':', $tags['h']) as $name) {
            $name = strtolower(trim($name));
            // repeated headers are signed from the bottom up
            for ($i = count($headers) - 1; $i >= 0; $i--) {
                if (strtolower($headers[$i][0]) === $name && ! isset($used[$i])) {
                    $used[$i] = true;
                    $data .= self::canonHeader($headers[$i][0], $headers[$i][1], $headerCanon)."\r\n";
                    break;
                }
            }
        }

        $unsigned = (string) preg_replace('/((?:^|;)\s*b\s*=)[^;]*/', '$1', $signature[1]);
        $data .= self::canonHeader($signature[0], $unsigned, $headerCanon);

        $valid = 1 === openssl_verify($data, (string) base64_decode($tags['b']), $publicKeyPem, OPENSSL_ALGO_SHA256);

        return ['tags' => $tags, 'bodyHash' => $bodyHash, 'valid' => $valid];
    }

    /**
     * @return list<array{string, string}> name and raw value (with folding)
     */
    private static function headers(string $block): array
    {
        $headers = [];

        foreach (explode("\r\n", $block) as $line) {
            if (('' !== $line) && (' ' === $line[0] || "\t" === $line[0]) && [] !== $headers) {
                $headers[count($headers) - 1][1] .= "\r\n".$line;

                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[] = [$name, $value];
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private static function tags(string $value): array
    {
        $tags = [];

        foreach (explode(';', $value) as $part) {
            if (str_contains($part, '=')) {
                [$name, $tagValue] = explode('=', $part, 2);
                $tags[trim($name)] = (string) preg_replace('/\s+/', '', $tagValue);
            }
        }

        return $tags;
    }

    private static function canonHeader(string $name, string $value, string $canon): string
    {
        if ('simple' === $canon) {
            return $name.':'.$value;
        }

        $value = str_replace("\r\n", '', $value);
        $value = trim((string) preg_replace('/[ \t]+/', ' ', $value));

        return strtolower(trim($name)).':'.$value;
    }

    private static function canonBody(string $body, string $canon): string
    {
        if ('relaxed' === $canon) {
            $lines = explode("\r\n", $body);
            $lines = array_map(fn (string $line) => rtrim((string) preg_replace('/[ \t]+/', ' ', $line), " \t"), $lines);
            $body = implode("\r\n", $lines);
        }

        $body = rtrim($body, "\r\n");

        return '' === $body ? ('simple' === $canon ? "\r\n" : '') : $body."\r\n";
    }
}
