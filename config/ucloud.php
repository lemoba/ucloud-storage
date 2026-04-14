<?php

return [
    /*
    |--------------------------------------------------------------------------
    | UCloud API Keys
    |--------------------------------------------------------------------------
    |
    | The public and private keys provided by UCloud console.
    |
    | https://docs.ucloud.cn/storage_cdn/ufile/tools/introduction
    */
    'public_key' => env('UCLOUD_PUBLIC_KEY', ''),
    'private_key' => env('UCLOUD_PRIVATE_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Proxy Suffix
    |--------------------------------------------------------------------------
    |
    | Domain suffix of the bucket. Example: .us-ws.ufileos.com
    | Or specific custom domain.
    */
    'proxy_suffix' => env('UCLOUD_PROXY_SUFFIX', '.us-ws.ufileos.com'),
    
    /*
    |--------------------------------------------------------------------------
    | Default Bucket Name
    |--------------------------------------------------------------------------
    */
    'bucket' => env('UCLOUD_BUCKET', ''),

    /*
    |--------------------------------------------------------------------------
    | CDN / Custom Domain
    |--------------------------------------------------------------------------
    |
    | Used for downloading files (Public/Private Urls). Example: https://cdn.ucloud.cn
    */
    'domain' => env('UCLOUD_DOMAIN', ''),

    /*
    |--------------------------------------------------------------------------
    | Default Request Timeout
    |--------------------------------------------------------------------------
    |
    */
    'timeout' => env('UCLOUD_TIMEOUT', 30),
];
