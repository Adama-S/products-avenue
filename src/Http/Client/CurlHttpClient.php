<?php

declare(strict_types=1);

namespace App\Http\Client;

/**
 * HTTP client based on cURL, with short timeouts (US-20).
 *
 * A product page must not hang because the API is slow: after 3 s without a connection
 * or 5 s in total, the call fails and the provider decides what to do (retry, then 503).
 */
final readonly class CurlHttpClient implements HttpClientInterface
{
    private const int CONNECT_TIMEOUT_MS = 3000;

    private const int TOTAL_TIMEOUT_MS = 5000;

    /** An explicit User-Agent: the API owner can identify and contact us instead of blocking us. */
    private const string USER_AGENT = 'ProductsAvenue/1.0 (+https://github.com/Adama-S/products-avenue)';

    private const array OPTIONS = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT_MS => self::CONNECT_TIMEOUT_MS,
        CURLOPT_TIMEOUT_MS => self::TOTAL_TIMEOUT_MS,
        CURLOPT_USERAGENT => self::USER_AGENT,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        // Only web protocols: a misconfigured URL cannot read a local file (file://).
        CURLOPT_PROTOCOLS_STR => 'https,http',
    ];

    /**
     * Any HTTP status comes back as a response; only the absence of a response is an exception.
     */
    public function get(string $url): HttpResponse
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new HttpTransportException('Cannot initialise cURL for ' . $url, 0.0);
        }

        curl_setopt_array($handle, self::OPTIONS);

        $body = curl_exec($handle);
        $durationMs = (float) curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000;

        if (!\is_string($body)) {
            throw new HttpTransportException(curl_error($handle), $durationMs);
        }

        return new HttpResponse((int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $body, $durationMs);
    }
}
