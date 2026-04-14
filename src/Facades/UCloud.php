<?php

namespace UCloud\Storage\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed putFile(string $key, string $file, string $bucket = null)
 * @method static mixed multipartForm(string $key, string $file, string $bucket = null)
 * @method static mixed mInit(string $key, string $bucket = null)
 * @method static array mUpload(string $key, string $file, string $uploadId, int $blkSize, int $partNumber = 0, string $bucket = null)
 * @method static mixed mFinish(string $key, string $uploadId, array $etagList, string $newKey = '', string $bucket = null)
 * @method static mixed mCancel(string $key, string $uploadId, string $bucket = null)
 * @method static mixed uploadHit(string $key, string $file, string $bucket = null)
 * @method static mixed deleteFile(string $key, string $bucket = null)
 * @method static mixed head(string $key, string $bucket = null)
 * @method static mixed appendFile(string $key, string $file, int $position, string $bucket = null)
 * @method static mixed listObjects(string $pathPrefix = '', string $marker = '', int $count = 20, string $delimiter = '', string $bucket = null)
 * @method static mixed listObjects(string $pathPrefix = '', string $marker = '', int $count = 20, string $delimiter = '', string $bucket = null)
 * @method static string makePublicUrl(string $key, string $bucket = null)
 * @method static string makePrivateUrl(string $key, int $expires = 0, string $bucket = null)
 * @method static array refreshCdnUrls(array $urls)
 *
 * @see \UCloud\Storage\UCloudClient
 */
class UCloud extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'ucloud';
    }
}
