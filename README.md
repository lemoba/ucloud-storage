# UCloud Storage for Laravel

这是一个针对 UCloud 对象存储 (UFile) 专门适配的原生 Laravel 扩展包。它支持 Swoole / Laravel Octane 等常驻内存运行环境的高并发调用，全量摒弃了原 SDK 老旧的函数式 `global` 参数以及阻塞式原生 `curl_exec` 的写法。

## 🌟 特性

- **原生 Laravel 风格**：完全支持 ServiceProvider 与纯净好用的 Facade 静态调用（`UCloud::putFile()`）。
- **Swoole / Octane 兼容**：底层基于 `Illuminate\Support\Facades\Http` 提供请求支持。在使用 Swoole Hook (如 Laravel Octane) 开启的情况下，所有网络请求将自动实现协程级非阻塞调度。
- **现代化异常处理**：错误统一封装为 `UCloudException` 抛出，优雅融入 Laravel 的统一错误处理机制中。
- **自动推断**：内置自动判断和构建文件与扩展名相应的 `MimeType` 头属性。

---

## 🛠 安装与配置

你可以通过 Composer 轻松在主项目里安装这个扩展包：

1. 运行安装命令将包引入：
   ```bash
   composer require lemoba/ucloud-storage
   ```

2. 导出包的配置文件到你的 `config` 目录 (可选):
   ```bash
   php artisan vendor:publish --tag=ucloud-config
   ```

3. 在主项目的 `.env` 中加入必须要配置的环境变量信息：
   ```env
   # 控制台提供的公钥
   UCLOUD_PUBLIC_KEY=your_public_key
   # 控制台提供的私钥
   UCLOUD_PRIVATE_KEY=your_private_key
   # 你 Bucket 的空间域名后缀或自定义域名，如 .cn-bj.ufileos.com
   UCLOUD_PROXY_SUFFIX=.us-ws.ufileos.com
   # 全局设定请求的超时时间（秒）
   UCLOUD_TIMEOUT=30
   # 默认使用的存储空间名 (Bucket)
   UCLOUD_BUCKET=my-bucket
   # 获取下载链接时使用的自带 CDN / 自定义域名（可选）
   UCLOUD_DOMAIN=https://cdn.example.com
   ```

---

## 🚀 基础使用方法

只要引入对应的 Facade：
```php
use UCloud\Storage\Facades\UCloud;
use UCloud\Storage\Exceptions\UCloudException;
```

就可以直接进行全套的存储服务调用：

### 1. 普通文件上传 (PutFile)
适用于普通大小文件上传（一次性读入流）。
```php
try {
    $result = UCloud::putFile('upload/cloud_filename.jpg', '/local/path/to/demo.jpg');
    // 返回包含 ETag 等信息的数组
} catch (UCloudException $e) {
    echo "上传失败: " . $e->getMessage() . " 错误码: " . $e->getErrRet();
}
```

### 2. 表单文件上传 (MultipartForm)
传统的表单模式上传，利用 `Content-Type: multipart/form-data` 传输文件。
```php
UCloud::multipartForm('upload/cloud_filename.png', '/local/path/to/demo.png');
```

---

## 📦 大文件分片上传 (Multipart Upload)

当面临特别巨大的文件（例如长视频）时，强烈建议采用分片上传以防内存超限或请求时间中途断开：

```php
try {
    $key = 'large_video.mp4';
    $file = '/local/path/to/huge_video.mp4';
    $blkSize = 4 * 1024 * 1024; // 每片 4MB

    // 1. 初始化分片上传任务
    $initResult = UCloud::mInit($key);
    $uploadId = $initResult['UploadId'];

    // 2. 将数据切片并逐片上传（程序内部通过 fseek 无感知自动拆解）
    $etagList = UCloud::mUpload($key, $file, $uploadId, $blkSize);

    // 3. 通知云端完成分片合并过程
    $finishResult = UCloud::mFinish($key, $uploadId, $etagList);

    // $finishResult 将返回新文件的地址等整合信息
    return response()->json($finishResult);
    
} catch (UCloudException $e) {
    // 中途出现网络异常可以选择中止或者进行 UCloud::mCancel($key, $uploadId)
    return response()->json(['error' => $e->getMessage()], 500);
}
```

---

## 🎯 秒传 (UploadHit)

UCloud 独有特性。预先检查待传文件 Hash 是否已在云端集群网络库中存在，命中可跳过漫长的物理传输过程以达到"秒传"的效果：

```php
try {
    // 这将从本地文件中动态计算 SHA1 Hash 然后询问 UCloud 服务端
    $result = UCloud::uploadHit('fast_video.mp4', '/local/video/demo.mp4');
    echo "秒传发生成功!";
} catch (UCloudException $e) {
    if ($e->getCode() == 404) {
         echo "还未有过该文件存在，需要重新进行正规 PutFile 上传";
    }
}
```

---

## 🗃 实用工具函数

### 获取公有空间的下载链接
可以直接获得资源 CDN URL，无过期时间设定。
```php
$url = UCloud::makePublicUrl('image.jpg');
// e.g. http://my-bucket.us-ws.ufileos.com/image.jpg
```

### 获取私有空间的授权防盗链链接
对于启用了私有权鉴防御的 Bucket 用户，通过数字签名的方式生成带有失效时间的临时访问 URL。
```php
// url 一小时 (3600秒级失效限制) 以后将不再允许访问
$expires = time() + 3600; 
$secureUrl = UCloud::makePrivateUrl('secret.pdf', $expires);
```

### 删除文件
```php
UCloud::deleteFile('remove-me.jpg');
```

### 获取文件元信息 (Head)
常用于提前验证文件是否上传完整或仅获取体积属性。
```php
$headers = UCloud::head('info.jpg');
// $headers 将包含一个包含 Content-Length 和 ETag 信息的数组。
```

### 按前缀读取云端列表目录
```php
// 获取 20 个根目录下 `pics/` 开头的文件或目录
$list = UCloud::listObjects('pics/', '', 20, '/');
```
