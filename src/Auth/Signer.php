<?php

namespace UCloud\Storage\Auth;

class Signer
{
    protected string $publicKey;
    protected string $privateKey;

    public function __construct(string $publicKey, string $privateKey)
    {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
    }

    /**
     * Canonicalize headers starting with x-ucloud
     */
    protected function canonicalizedUCloudHeaders(array $headers): string
    {
        $keys = [];
        $ucloudHeaders = [];

        foreach ($headers as $k => $v) {
            $lowerKey = strtolower($k);
            if (str_starts_with($lowerKey, 'x-ucloud')) {
                $keys[] = $lowerKey;
                $ucloudHeaders[$lowerKey] = is_array($v) ? $v[0] : $v;
            }
        }

        if (empty($keys)) {
            return '';
        }

        sort($keys, SORT_STRING);
        $c = '';
        foreach ($keys as $k) {
            $c .= $k . ":" . trim($ucloudHeaders[$k]) . "\n";
        }

        return $c;
    }

    /**
     * Canonicalized resource
     */
    protected function canonicalizedResource(string $bucket, string $key): string
    {
        $key = ltrim($key, '/');
        return "/" . $bucket . "/" . $key;
    }

    /**
     * General sign data
     */
    public function sign(string $data): string
    {
        $sign = base64_encode(hash_hmac('sha1', $data, $this->privateKey, true));
        return "UCloud " . $this->publicKey . ":" . $sign;
    }

    /**
     * Sign HTTP Request components
     */
    public function signRequest(
        string $method,
        string $bucket,
        string $key,
        string $contentType = '',
        string $contentMd5 = '',
        string $date = '',
        string $expires = '',
        array  $headers = []
    ): string {
        $data = strtoupper($method) . "\n";
        $data .= $contentMd5 . "\n";
        $data .= $contentType . "\n";
        
        if (!empty($date)) {
            $data .= $date . "\n";
        } elseif (!empty($expires)) {
            $data .= $expires . "\n";
        } else {
            $data .= "\n"; 
        }

        $data .= $this->canonicalizedUCloudHeaders($headers);
        $data .= $this->canonicalizedResource($bucket, $key);

        return $this->sign($data);
    }
}
