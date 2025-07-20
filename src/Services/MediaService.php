<?php

declare(strict_types=1);

namespace DingTalk\Services;

/**
 * 媒体文件管理服务
 * 
 * 提供媒体文件上传、下载等操作
 */
class MediaService extends BaseService
{
    /**
     * 上传媒体文件
     */
    public function upload(string $filePath, string $type = 'image'): array
    {
        $this->validateRequired(['filePath' => $filePath, 'type' => $type], ['filePath', 'type']);
        
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->uploadV2Media($filePath, $type);
        }
        
        return $this->uploadV1Media($filePath, $type);
    }

    /**
     * 下载媒体文件
     */
    public function download(string $downloadCode, string $savePath = null): array
    {
        $this->validateRequired(['downloadCode' => $downloadCode], ['downloadCode']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->downloadV2Media($downloadCode, $savePath);
        }
        
        return $this->downloadV1Media($downloadCode, $savePath);
    }

    /**
     * 获取媒体文件信息
     */
    public function getInfo(string $mediaId): array
    {
        $this->validateRequired(['mediaId' => $mediaId], ['mediaId']);
        
        $cacheKey = $this->buildCacheKey('media_info', ['mediaId' => $mediaId]);
        
        return $this->remember($cacheKey, function () use ($mediaId) {
            $apiVersion = $this->config->get('api_version', 'v1');
            
            if ($apiVersion === 'v2') {
                return $this->getV2MediaInfo($mediaId);
            }
            
            return $this->getV1MediaInfo($mediaId);
        }, 3600); // 缓存1小时
    }

    /**
     * 上传图片
     */
    public function uploadImage(string $imagePath): array
    {
        return $this->upload($imagePath, 'image');
    }

    /**
     * 上传语音
     */
    public function uploadVoice(string $voicePath): array
    {
        return $this->upload($voicePath, 'voice');
    }

    /**
     * 上传视频
     */
    public function uploadVideo(string $videoPath): array
    {
        return $this->upload($videoPath, 'video');
    }

    /**
     * 上传文件
     */
    public function uploadFile(string $filePath): array
    {
        return $this->upload($filePath, 'file');
    }

    /**
     * 批量上传文件
     */
    public function batchUpload(array $files): array
    {
        return $this->batch($files, function ($batch) {
            $results = [];
            foreach ($batch as $file) {
                try {
                    $filePath = $file['path'];
                    $type = $file['type'] ?? 'file';
                    $results[] = $this->upload($filePath, $type);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to upload file', [
                        'file' => $file,
                        'error' => $e->getMessage(),
                    ]);
                    $results[] = ['error' => $e->getMessage()];
                }
            }
            return $results;
        }, 5); // 限制并发数
    }

    /**
     * 获取文件上传进度
     */
    public function getUploadProgress(string $uploadId): array
    {
        $this->validateRequired(['uploadId' => $uploadId], ['uploadId']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->getV2UploadProgress($uploadId);
        }
        
        return $this->getV1UploadProgress($uploadId);
    }

    /**
     * 删除媒体文件
     */
    public function deleteMedia(string $mediaId): array
    {
        $this->validateRequired(['mediaId' => $mediaId], ['mediaId']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            $result = $this->deleteV2Media($mediaId);
        } else {
            $result = $this->deleteV1Media($mediaId);
        }
        
        // 清除缓存
        $this->forgetCache($this->buildCacheKey('media_info', ['mediaId' => $mediaId]));
        
        return $result;
    }

    /**
     * 获取媒体文件URL
     */
    public function getUrl(string $mediaId, int $expires = 3600): string
    {
        $this->validateRequired(['mediaId' => $mediaId], ['mediaId']);
        
        $cacheKey = $this->buildCacheKey('media_url', ['mediaId' => $mediaId, 'expires' => $expires]);
        
        return $this->remember($cacheKey, function () use ($mediaId, $expires) {
            $apiVersion = $this->config->get('api_version', 'v1');
            
            if ($apiVersion === 'v2') {
                return $this->getV2MediaUrl($mediaId, $expires);
            }
            
            return $this->getV1MediaUrl($mediaId, $expires);
        }, min($expires, 3600)); // 缓存时间不超过1小时
    }

    /**
     * 创建分片上传任务
     */
    public function createChunkUpload(string $fileName, int $fileSize, string $type = 'file'): array
    {
        $this->validateRequired(['fileName' => $fileName, 'fileSize' => $fileSize], ['fileName', 'fileSize']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->createV2ChunkUpload($fileName, $fileSize, $type);
        }
        
        return $this->createV1ChunkUpload($fileName, $fileSize, $type);
    }

    /**
     * 上传文件分片
     */
    public function uploadChunk(string $uploadId, int $chunkNumber, string $chunkData): array
    {
        $this->validateRequired(['uploadId' => $uploadId, 'chunkNumber' => $chunkNumber], ['uploadId', 'chunkNumber']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->uploadV2Chunk($uploadId, $chunkNumber, $chunkData);
        }
        
        return $this->uploadV1Chunk($uploadId, $chunkNumber, $chunkData);
    }

    /**
     * 完成分片上传
     */
    public function completeChunkUpload(string $uploadId): array
    {
        $this->validateRequired(['uploadId' => $uploadId], ['uploadId']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->completeV2ChunkUpload($uploadId);
        }
        
        return $this->completeV1ChunkUpload($uploadId);
    }

    /**
     * 取消分片上传
     */
    public function cancelChunkUpload(string $uploadId): array
    {
        $this->validateRequired(['uploadId' => $uploadId], ['uploadId']);
        
        $apiVersion = $this->config->get('api_version', 'v1');
        
        if ($apiVersion === 'v2') {
            return $this->cancelV2ChunkUpload($uploadId);
        }
        
        return $this->cancelV1ChunkUpload($uploadId);
    }

    /**
     * V1版本：上传媒体文件
     */
    private function uploadV1Media(string $filePath, string $type): array
    {
        $accessToken = $this->auth->getAccessToken();
        
        return $this->upload('/media/upload', [
            'type' => $type,
            'media' => new \CURLFile($filePath),
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：上传媒体文件
     */
    private function uploadV2Media(string $filePath, string $type): array
    {
        return $this->upload('/v1.0/robot/messageFiles/upload', [
            'type' => $type,
            'media' => new \CURLFile($filePath),
        ]);
    }

    /**
     * V1版本：下载媒体文件
     */
    private function downloadV1Media(string $downloadCode, string $savePath = null): array
    {
        $accessToken = $this->auth->getAccessToken();
        
        $response = $this->get('/media/downloadFile', [
            'access_token' => $accessToken,
            'download_code' => $downloadCode,
        ]);
        
        if ($savePath && isset($response['media'])) {
            file_put_contents($savePath, $response['media']);
            $response['saved_path'] = $savePath;
        }
        
        return $response;
    }

    /**
     * V2版本：下载媒体文件
     */
    private function downloadV2Media(string $downloadCode, string $savePath = null): array
    {
        $response = $this->get("/v1.0/robot/messageFiles/download", [
            'downloadCode' => $downloadCode,
        ]);
        
        if ($savePath && isset($response['media'])) {
            file_put_contents($savePath, $response['media']);
            $response['saved_path'] = $savePath;
        }
        
        return $response;
    }

    /**
     * V1版本：获取媒体文件信息
     */
    private function getV1MediaInfo(string $mediaId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->get('/media/get', [
            'access_token' => $accessToken,
            'media_id' => $mediaId,
        ]);
    }

    /**
     * V2版本：获取媒体文件信息
     */
    private function getV2MediaInfo(string $mediaId): array
    {
        return $this->get("/v1.0/robot/messageFiles/{$mediaId}");
    }

    /**
     * V1版本：获取上传进度
     */
    private function getV1UploadProgress(string $uploadId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->get('/media/upload/progress', [
            'access_token' => $accessToken,
            'upload_id' => $uploadId,
        ]);
    }

    /**
     * V2版本：获取上传进度
     */
    private function getV2UploadProgress(string $uploadId): array
    {
        return $this->get("/v1.0/robot/messageFiles/uploads/{$uploadId}/progress");
    }

    /**
     * V1版本：删除媒体文件
     */
    private function deleteV1Media(string $mediaId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/media/delete', [
            'media_id' => $mediaId,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：删除媒体文件
     */
    private function deleteV2Media(string $mediaId): array
    {
        return $this->delete("/v1.0/robot/messageFiles/{$mediaId}");
    }

    /**
     * V1版本：获取媒体文件URL
     */
    private function getV1MediaUrl(string $mediaId, int $expires): string
    {
        $accessToken = $this->auth->getAccessToken();
        $result = $this->get('/media/get_url', [
            'access_token' => $accessToken,
            'media_id' => $mediaId,
            'expires' => $expires,
        ]);
        
        return $result['url'] ?? '';
    }

    /**
     * V2版本：获取媒体文件URL
     */
    private function getV2MediaUrl(string $mediaId, int $expires): string
    {
        $result = $this->get("/v1.0/robot/messageFiles/{$mediaId}/downloadUrl", [
            'expires' => $expires,
        ]);
        
        return $result['downloadUrl'] ?? '';
    }

    /**
     * V1版本：创建分片上传任务
     */
    private function createV1ChunkUpload(string $fileName, int $fileSize, string $type): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/media/upload/chunk/init', [
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'type' => $type,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：创建分片上传任务
     */
    private function createV2ChunkUpload(string $fileName, int $fileSize, string $type): array
    {
        return $this->post('/v1.0/robot/messageFiles/uploads', [
            'fileName' => $fileName,
            'fileSize' => $fileSize,
            'type' => $type,
        ]);
    }

    /**
     * V1版本：上传文件分片
     */
    private function uploadV1Chunk(string $uploadId, int $chunkNumber, string $chunkData): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/media/upload/chunk', [
            'upload_id' => $uploadId,
            'chunk_number' => $chunkNumber,
            'chunk_data' => $chunkData,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：上传文件分片
     */
    private function uploadV2Chunk(string $uploadId, int $chunkNumber, string $chunkData): array
    {
        return $this->post("/v1.0/robot/messageFiles/uploads/{$uploadId}/chunks", [
            'chunkNumber' => $chunkNumber,
            'chunkData' => $chunkData,
        ]);
    }

    /**
     * V1版本：完成分片上传
     */
    private function completeV1ChunkUpload(string $uploadId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/media/upload/chunk/complete', [
            'upload_id' => $uploadId,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：完成分片上传
     */
    private function completeV2ChunkUpload(string $uploadId): array
    {
        return $this->post("/v1.0/robot/messageFiles/uploads/{$uploadId}/complete");
    }

    /**
     * V1版本：取消分片上传
     */
    private function cancelV1ChunkUpload(string $uploadId): array
    {
        $accessToken = $this->auth->getAccessToken();
        return $this->post('/media/upload/chunk/cancel', [
            'upload_id' => $uploadId,
        ], [
            'access_token' => $accessToken,
        ]);
    }

    /**
     * V2版本：取消分片上传
     */
    private function cancelV2ChunkUpload(string $uploadId): array
    {
        return $this->delete("/v1.0/robot/messageFiles/uploads/{$uploadId}");
    }
}