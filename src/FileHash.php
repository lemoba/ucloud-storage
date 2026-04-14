<?php

namespace UCloud\Storage;

use UCloud\Storage\Exceptions\UCloudException;

class FileHash
{
    const BLKSIZE = 4194304; // 4 * 1024 * 1024

    public static function urlSafeEncode(string $data): string
    {
        return str_replace(['+', '/'], ['-', '_'], $data);
    }

    public static function urlSafeDecode(string $data): string
    {
        return str_replace(['-', '_'], ['+', '/'], $data);
    }

    public static function hash(string $file): string
    {
        $f = fopen($file, "r");
        if (!$f) {
            throw new UCloudException("open $file error", -1);
        }

        $fileSize = filesize($file);
        $buffer = '';
        $sha = '';
        $blkcnt = intval($fileSize / self::BLKSIZE);
        if ($fileSize % self::BLKSIZE > 0) {
            $blkcnt += 1;
        }

        $buffer .= pack("L", $blkcnt);

        if ($fileSize <= self::BLKSIZE) {
            $content = fread($f, self::BLKSIZE);
            if ($content === false) {
                fclose($f);
                throw new UCloudException("read $file error", -1);
            }
            $sha .= sha1($content, true);
        } else {
            for ($i = 0; $i < $blkcnt; $i++) {
                $content = fread($f, self::BLKSIZE);
                if ($content === false) {
                    if (feof($f)) break;
                    fclose($f);
                    throw new UCloudException("read $file error", -1);
                }
                $sha .= sha1($content, true);
            }
            $sha = sha1($sha, true);
        }
        $buffer .= $sha;
        $hash = self::urlSafeEncode(base64_encode($buffer));
        fclose($f);

        return $hash;
    }
}
