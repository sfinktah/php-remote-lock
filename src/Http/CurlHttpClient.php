<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace Sfinktah\Shopify\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class CurlHttpClient
{
    /**
     * Send an HTTP request and return the response.
     *
     * @param RequestInterface $request PSR-7 HTTP request.
     *
     * @return ResponseInterface PSR-7 HTTP response.
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $request->getUri());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->getMethod());

        // Set request headers
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[] = "$name: " . implode(', ', $values);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Set request body
        if ($request->getBody()->getSize() > 0) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$request->getBody());
        }

        // Execute cURL request
        $responseString = curl_exec($ch);

        // Check for cURL errors
        if ($responseString === false) {
            throw new \RuntimeException('cURL error: ' . curl_error($ch));
        }

        // Close cURL session
        curl_close($ch);

        // Parse the raw response string into PSR-7 response
        list($headers, $body) = explode("\r\n\r\n", $responseString, 2);
        return $this->createResponse($headers, $body);
    }

    /**
     * Create a PSR-7 HTTP response from raw headers and body.
     *
     * @param string $headers Raw headers string.
     * @param string $body    Raw body string.
     *
     * @return ResponseInterface PSR-7 HTTP response.
     */
    protected function createResponse(string $headers, string $body): ResponseInterface
    {
        list($statusLine, $headerLines) = explode("\r\n", $headers, 2);
        list($protocol, $statusCode, $reasonPhrase) = explode(' ', $statusLine, 3);

        // Create a PSR-7 Stream for the response body
        $stream = $this->createStream($body);

        // Create a PSR-7 Response with headers, status code, and body
        $response = new HttpResponse($statusCode, $this->parseHeaders($headerLines), $stream, $reasonPhrase);
        $response->setProtocolVersion($protocol);

        return $response;
    }

    /**
     * Create a PSR-7 Stream from raw body string.
     *
     * @param string $body Raw body string.
     *
     * @return StreamInterface PSR-7 Stream.
     */
    protected function createStream(string $body): StreamInterface
    {
        return new MemoryStream($body);
    }

    /**
     * Parse raw header lines into an associative array.
     *
     * @param string $headerLines Raw header lines.
     *
     * @return array Associative array of headers.
     */
    protected function parseHeaders(string $headerLines): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerLines);
        foreach ($lines as $line) {
            list($name, $value) = explode(':', $line, 2);
            $headers[trim($name)][] = trim($value);
        }

        return $headers;
    }
}

// Example usage
// $request = new HttpRequest('GET', 'https://api.example.com');
// $client = new CurlHttpClient();
// $response = $client->sendRequest($request);
//
// // Output response
// echo $response->getStatusCode() . ' ' . $response->getReasonPhrase() . PHP_EOL;
// echo implode("\n", $response->getHeaders()) . PHP_EOL;
// echo $response->getBody()->getContents();
