<?php
/**
 * 头像上传后台接口
 * 使用方法：将此文件放在服务器上，前端通过AJAX调用
 */

// 允许跨域请求（开发环境使用）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 只接受POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只支持POST请求']);
    exit;
}

// 检查是否有文件上传
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '没有上传文件或上传出错']);
    exit;
}

$file = $_FILES['avatar'];

// 验证文件类型
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '只支持JPG、PNG、GIF和WebP格式的图片']);
    exit;
}

// 验证文件大小（最大5MB）
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '图片大小不能超过5MB']);
    exit;
}

// 验证图片尺寸
$imageInfo = getimagesize($file['tmp_name']);
if (!$imageInfo) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '无法读取图片信息']);
    exit;
}

$minWidth = 100;
$minHeight = 100;
if ($imageInfo[0] < $minWidth || $imageInfo[1] < $minHeight) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "图片尺寸过小，请选择至少{$minWidth}×{$minHeight}像素的图片"]);
    exit;
}

// 创建保存目录
$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 生成唯一文件名
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$fileName = 'avatar_' . uniqid() . '_' . time() . '.' . $fileExtension;
$filePath = $uploadDir . $fileName;

// 移动上传的文件
if (move_uploaded_file($file['tmp_name'], $filePath)) {
    // 生成缩略图（可选）
    createAvatarThumbnail($filePath, $uploadDir . 'thumb_' . $fileName, 150, 150);

    // 返回成功响应
    echo json_encode([
        'success' => true,
        'message' => '头像上传成功',
        'avatarUrl' => $filePath,
        'avatarThumbUrl' => $uploadDir . 'thumb_' . $fileName,
        'originalName' => $file['name'],
        'fileSize' => $file['size'],
        'width' => $imageInfo[0],
        'height' => $imageInfo[1]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '文件保存失败']);
}

/**
 * 创建头像缩略图
 */
function createAvatarThumbnail($sourcePath, $destPath, $width, $height) {
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) return false;

    $sourceType = $imageInfo[2];

    // 根据图片类型创建图像资源
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            $sourceImage = imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }

    if (!$sourceImage) return false;

    // 获取原图尺寸
    $srcWidth = imagesx($sourceImage);
    $srcHeight = imagesy($sourceImage);

    // 创建缩略图
    $thumbImage = imagecreatetruecolor($width, $height);

    // 保持透明背景（PNG和GIF）
    if ($sourceType == IMAGETYPE_PNG || $sourceType == IMAGETYPE_GIF) {
        imagealphablending($thumbImage, false);
        imagesavealpha($thumbImage, true);
        $transparent = imagecolorallocatealpha($thumbImage, 0, 0, 0, 127);
        imagefill($thumbImage, 0, 0, $transparent);
    }

    // 计算缩放比例并居中裁剪
    $srcRatio = $srcWidth / $srcHeight;
    $dstRatio = $width / $height;

    if ($srcRatio > $dstRatio) {
        // 原图更宽，裁剪宽度
        $newHeight = $srcHeight;
        $newWidth = $srcHeight * $dstRatio;
        $srcX = ($srcWidth - $newWidth) / 2;
        $srcY = 0;
    } else {
        // 原图更高，裁剪高度
        $newWidth = $srcWidth;
        $newHeight = $srcWidth / $dstRatio;
        $srcX = 0;
        $srcY = ($srcHeight - $newHeight) / 2;
    }

    // 调整到目标尺寸
    imagecopyresampled($thumbImage, $sourceImage, 0, 0, $srcX, $srcY, $width, $height, $newWidth, $newHeight);

    // 保存缩略图
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            imagejpeg($thumbImage, $destPath, 90);
            break;
        case IMAGETYPE_PNG:
            imagepng($thumbImage, $destPath, 9);
            break;
        case IMAGETYPE_GIF:
            imagegif($thumbImage, $destPath);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($thumbImage, $destPath, 90);
            break;
    }

    // 释放内存
    imagedestroy($sourceImage);
    imagedestroy($thumbImage);

    return true;
}
?>