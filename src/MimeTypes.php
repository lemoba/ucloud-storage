<?php

namespace UCloud\Storage;

class MimeTypes
{
    /**
     * Get the mime type of a file
     */
    public static function get(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'zip' => 'application/zip',
        ];

        if (array_key_exists($ext, $map)) {
            return $map[$ext];
        }

        if (function_exists('mime_content_type')) {
            return mime_content_type($filename) ?: 'application/octet-stream';
        }

        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype ?: 'application/octet-stream';
        }

        return 'application/octet-stream';
    }
}
