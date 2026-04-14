<?php

namespace UCloud\Storage;

use Illuminate\Support\Facades\Http;
use UCloud\Storage\Auth\Signer;
use UCloud\Storage\Exceptions\UCloudException;

class UCloudClient
{
    protected string $publicKey;
    protected string $privateKey;
    protected string $proxySuffix;
    protected string $bucket;
    protected string $domain;
    protected int $timeout;
    protected Signer $signer;

    public function __construct(array $config)
    {
        $this->publicKey = $config['public_key'] ?? '';
        $this->privateKey = $config['private_key'] ?? '';
        $this->proxySuffix = $config['proxy_suffix'] ?? '';
        $this->bucket = $config['bucket'] ?? '';
        $this->domain = rtrim($config['domain'] ?? '', '/');
        $this->timeout = (int)($config['timeout'] ?? 30);

        if (empty($this->publicKey) || empty($this->privateKey) || empty($this->proxySuffix)) {
            throw new UCloudException("UCloud configuration is missing required keys.", 400);
        }
        
        if (empty($this->bucket)) {
            throw new UCloudException("UCloud bucket is not configured.", 400);
        }

        $this->signer = new Signer($this->publicKey, $this->privateKey);
    }

    protected function getHost(string $bucket = null): string
    {
        $bucket = $bucket ?: $this->bucket;
        return $bucket . $this->proxySuffix;
    }

    protected function sendRequest(
        string $method,
        string $key = '',
        array $query = [],
        array $headers = [],
        $body = null,
        bool $signQuery = false,
        string $bucket = null
    ) {
        $bucket = $bucket ?: $this->bucket;
        $host = $this->getHost($bucket);
        $path = $key ? '/' . ltrim($key, '/') : '/';
        $url = 'https://' . $host . $path;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $contentType = $headers['Content-Type'] ?? '';
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $headers['Date'] = $date;

        if (!$signQuery) {
            $token = $this->signer->signRequest($method, $bucket, $key, $contentType, '', $date, '', $headers);
            $headers['Authorization'] = $token;
        }

        $pendingRequest = Http::timeout($this->timeout)->withHeaders($headers)
            ->withoutVerifying();

        if ($method === 'PUT') {
            $response = $pendingRequest->send('PUT', $url, ['body' => $body]);
        } elseif ($method === 'POST') {
            if (is_array($body) && isset($headers['Content-Type']) && str_starts_with($headers['Content-Type'], 'multipart/form-data')) {
               $response = $pendingRequest->send('POST', $url, ['body' => $body]);
            } elseif (is_string($body)) {
               $response = $pendingRequest->send('POST', $url, ['body' => $body]);
            } else {
               $response = $pendingRequest->post($url, (array)$body);
           }
        } elseif ($method === 'DELETE') {
            $response = $pendingRequest->send('DELETE', $url, ['query' => $query]);
        } elseif ($method === 'HEAD') {
            $response = $pendingRequest->send('HEAD', $url, ['query' => $query]);
        } else {
            $response = $pendingRequest->send('GET', $url, ['query' => $query]);
        }

        if (!$response->successful()) {
            $errRet = $response->json('ErrRet') ?? $response->status();
            $errMsg = $response->json('ErrMsg') ?? $response->body();
            throw new UCloudException($errMsg, $response->status(), $errRet);
        }

        return $response;
    }

    public function putFile(string $key, string $file, string $bucket = null)
    {
        $f = @fopen($file, "r");
        if (!$f) {
            throw new UCloudException("open $file error");
        }

        $mimetype = MimeTypes::get($file);
        
        $headers = [
            'Content-Type' => $mimetype,
            'Expect' => ''
        ];

        $res = $this->sendRequest('PUT', $key, [], $headers, $f, false, $bucket);
        if (is_resource($f)) {
            fclose($f);
        }
        
        return $res->json();
    }

    public function multipartForm(string $key, string $file, string $bucket = null)
    {
        $bucket = $bucket ?: $this->bucket;
        $f = @fopen($file, "r");
        if (!$f) {
            throw new UCloudException("open $file error");
        }
        
        $mimetype = MimeTypes::get($file);
        $token = $this->signer->signRequest('POST', $bucket, $key, $mimetype); 
        $host = $this->getHost($bucket);
        
        $response = Http::timeout($this->timeout)->withoutVerifying()
            ->attach('file', $f, $key)
            ->post('https://' . $host . '/', [
                'Authorization' => $token,
                'FileName' => $key
            ]);
            
        if (is_resource($f)) {
            fclose($f);
        }

        if (!$response->successful()) {
            throw new UCloudException($response->json('ErrMsg') ?? 'Upload failed', $response->status(), $response->json('ErrRet') ?? 0);
        }

        return $response->json();
    }

    public function mInit(string $key, string $bucket = null)
    {
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
        
        $res = $this->sendRequest('POST', $key, ['uploads' => ''], $headers, null, false, $bucket);
        return $res->json();
    }

    public function mUpload(string $key, string $file, string $uploadId, int $blkSize, int $partNumber = 0, string $bucket = null): array
    {
        $f = @fopen($file, "r");
        if (!$f) {
            throw new UCloudException("open $file error");
        }

        $mimetype = MimeTypes::get($file);
        $etagList = [];

        while (true) {
            if (@fseek($f, $blkSize * $partNumber, SEEK_SET) < 0) {
                fclose($f);
                throw new UCloudException("fseek error");
            }
            
            $content = @fread($f, $blkSize);
            if ($content === false) {
                if (feof($f)) break;
                fclose($f);
                throw new UCloudException("read file error");
            }
            if ($content === '') { 
                break;
            }

            $querys = [
                'uploadId' => $uploadId,
                'partNumber' => $partNumber
            ];

            $headers = [
                'Content-Type' => $mimetype,
                'Expect' => ''
            ];

            $res = $this->sendRequest('PUT', $key, $querys, $headers, $content, false, $bucket);
            $data = $res->json();
            
            $etag = $data['ETag'] ?? null;
            $part = $data['PartNumber'] ?? -1;

            if ($part != $partNumber) {
                fclose($f);
                throw new UCloudException("unmatched partnumber");
            }

            $etagList[] = $etag;
            $partNumber++;
            
            if (strlen($content) < $blkSize) {
                break;
            }
        }
        fclose($f);

        return $etagList;
    }

    public function mFinish(string $key, string $uploadId, array $etagList, string $newKey = '', string $bucket = null)
    {
        $querys = [
            'uploadId' => $uploadId,
            'newKey' => $newKey,
        ];

        $headers = [
            'Content-Type' => 'text/plain'
        ];

        $body = implode(',', $etagList);

        $res = $this->sendRequest('POST', $key, $querys, $headers, $body, false, $bucket);
        return $res->json();
    }

    public function mCancel(string $key, string $uploadId, string $bucket = null)
    {
        $querys = [
            'uploadId' => $uploadId
        ];
        
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];

        $res = $this->sendRequest('DELETE', $key, $querys, $headers, null, false, $bucket);
        return $res->json();
    }

    public function uploadHit(string $key, string $file, string $bucket = null)
    {
        $fileSize = filesize($file);
        $fileHash = FileHash::hash($file);

        $querys = [
            'Hash' => $fileHash,
            'FileName' => $key,
            'FileSize' => $fileSize
        ];

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];

        $res = $this->sendRequest('POST', 'uploadhit', $querys, $headers, null, false, $bucket);
        return $res->json();
    }

    public function deleteFile(string $key, string $bucket = null)
    {
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];

        $res = $this->sendRequest('DELETE', $key, [], $headers, null, false, $bucket);
        return $res->json();
    }

    public function head(string $key, string $bucket = null)
    {
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];

        $res = $this->sendRequest('HEAD', $key, [], $headers, null, false, $bucket);
        return $res->headers();
    }

    public function appendFile(string $key, string $file, int $position, string $bucket = null)
    {
        $f = @fopen($file, "r");
        if (!$f) {
            throw new UCloudException("open $file error");
        }

        $mimetype = MimeTypes::get($file);
        
        $headers = [
            'Content-Type' => $mimetype,
            'Expect' => ''
        ];

        $querys = [
            'append' => '',
            'position' => $position
        ];

        $res = $this->sendRequest('PUT', $key, $querys, $headers, $f, false, $bucket);
        if (is_resource($f)) {
            fclose($f);
        }
        
        return $res->json();
    }

    public function listObjects(string $pathPrefix = '', string $marker = '', int $count = 20, string $delimiter = '', string $bucket = null)
    {
        $querys = [
            'listobjects' => '',
            'prefix' => $pathPrefix,
            'marker' => $marker,
            'max-keys' => $count,
            'delimiter' => $delimiter
        ];

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];

        $res = $this->sendRequest('GET', '', $querys, $headers, null, false, $bucket);
        return $res->json();
    }

    public function makePublicUrl(string $key, string $bucket = null): string
    {
        if (!empty($this->domain)) {
            $host = preg_replace('#^https?://#', '', $this->domain);
            $scheme = parse_url($this->domain, PHP_URL_SCHEME) ?: 'http';
            return $scheme . "://" . $host . "/" . ltrim(rawurlencode($key), '/');
        }

        $bucket = $bucket ?: $this->bucket;
        $host = $this->getHost($bucket);
        return "http://" . $host . "/" . ltrim(rawurlencode($key), '/');
    }

    public function makePrivateUrl(string $key, int $expires = 0, string $bucket = null): string
    {
        $bucket = $bucket ?: $this->bucket;
        $publicUrl = $this->makePublicUrl($key, $bucket);
        $token = $this->signer->signRequest('GET', $bucket, $key, '', '', '', $expires > 0 ? (string)$expires : '');
        $signature = substr($token, -28, 28);
        
        $url = $publicUrl . "?UCloudPublicKey=" . rawurlencode($this->publicKey) . "&Signature=" . rawurlencode($signature);
        if ($expires > 0) {
            $url .= "&Expires=" . rawurlencode($expires);
        }
        
        return $url;
    }

    /**
     * Refresh UCloud CDN Edge Cache for given URLs
     * 
     * @param array $urls List of URLs to refresh
     * @return array Response from UCloud API
     */
    public function refreshCdnUrls(array $urls)
    {
        $params = [
            'Action' => 'RefreshNewUcdnDomainCache',
            'Type' => 'file',
            'PublicKey' => $this->publicKey,
        ];

        foreach (array_values($urls) as $idx => $url) {
            $params["UrlList.{$idx}"] = $url;
        }

        // OpenAPI Signature logic
        ksort($params);
        $str = '';
        foreach ($params as $k => $v) {
            $str .= $k . $v;
        }
        $str .= $this->privateKey;
        $params['Signature'] = sha1($str);

        // UCloud Global API Endpoint
        $response = Http::timeout($this->timeout)->withoutVerifying()
            ->post('https://api.ucloud.cn/', $params);

        if (!$response->successful() || $response->json('RetCode') !== 0) {
            $errRet = $response->json('RetCode') ?? $response->status();
            $errMsg = $response->json('Message') ?? $response->body();
            throw new UCloudException($errMsg ?: 'CDN Refresh Failed', $response->status(), $errRet);
        }

        return $response->json();
    }
}
