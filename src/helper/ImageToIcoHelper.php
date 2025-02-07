<?php

namespace mowzs\lib\helper;


use think\Exception;

class ImageToIcoHelper
{
    /**
     * 转换后的图像数据数组。
     *
     * @var array
     */
    private array $images = [];

    /**
     * 添加图像到生成器中。
     *
     * @param string $file 图像文件路径或URL
     * @param array $size 输出尺寸 [width, height]
     * @return self
     * @throws Exception
     */
    public function addImage(string $file, array $size = []): self
    {
        if (filter_var($file, FILTER_VALIDATE_URL)) {
            $imageData = file_get_contents($file);
            if ($imageData === false) {
                throw new Exception("无法下载图片");
            }
            $im = imagecreatefromstring($imageData);
        } else {
            $im = $this->loadImageFile($file);
        }

        if ($im === false) {
            throw new Exception("读取图片文件失败");
        }

        if (empty($size)) {
            $size = [imagesx($im), imagesy($im)];
        }

        [$width, $height] = $size;
        $image = imagecreatetruecolor($width, $height);
        imagecolortransparent($image, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, false);
        imagesavealpha($image, true);

        [$sourceWidth, $sourceHeight] = [imagesx($im), imagesy($im)];
        if (!imagecopyresampled($image, $im, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight)) {
            throw new Exception("解析和处理图片失败");
        }

        $this->addImageData($image, $width, $height);
        return $this;
    }

    /**
     * 将 ICO 内容写入到文件。
     *
     * @param string $file 写入文件路径
     * @return bool
     */
    public function saveIco(string $file): bool
    {
        if (false === ($data = $this->getIcoData())) {
            return false;
        }
        if (false === ($fh = fopen($file, 'w'))) {
            return false;
        }
        if (fwrite($fh, $data) === false) {
            fclose($fh);
            return false;
        }
        fclose($fh);
        return true;
    }

    /**
     * 生成并获取 ICO 图像数据。
     */
    private function getIcoData()
    {
        if (empty($this->images)) {
            return false;
        }
        $pixelData = '';
        $offset = 22; // ICO头大小 + 目录条目大小（每个16字节）
        foreach ($this->images as $image) {
            $pixelData .= pack('CCCCvvVV', $image['width'], $image['height'], 0, 0, 1, 32, $image['size'], $offset);
            $offset += $image['size'];
        }

        $icoHeader = pack('vvv', 0, 1, count($this->images)); // Reserved, Type, Count
        return $icoHeader . $pixelData . implode('', array_map(function ($img) {
                return $img['data'];
            }, $this->images));
    }

    private function addImageData($im, int $width, int $height)
    {
        // ICO文件头大小（40字节）
        $headerSize = 40;

        // 创建一个空字符串用于存储ICO数据
        $data = pack('VVVvvVVVVVV', $headerSize, $width, $height * 2, 1, 32, 0, 0, 0, 0, 0, 0);

        // 遍历每个像素并转换为BGRA格式
        for ($y = $height - 1; $y >= 0; --$y) {
            for ($x = 0; $x < $width; ++$x) {
                // 获取像素的颜色值
                $color = imagecolorat($im, $x, $y);

                // 提取RGB和Alpha值
                $r = ($color >> 16) & 0xFF;
                $g = ($color >> 8) & 0xFF;
                $b = $color & 0xFF;
                $a = (int)((1 - ((($color & 0x7F000000) >> 24) / 127)) * 255); // 计算Alpha值

                // 将颜色值以BGRA顺序打包
                $data .= pack('CCCC', $b, $g, $r, $a);
            }
        }

        // 添加到图像数据数组中
        $this->images[] = [
            'data' => $data,
            'size' => strlen($data),
            'width' => $width,
            'height' => $height,
            'pixel' => 32,
            'colors' => 0,
        ];
    }

    /**
     * 读取图片资源。
     *
     * @param string $file 文件路径
     * @return false|resource
     */
    private function loadImageFile(string $file)
    {
        if (false === getimagesize($file)) {
            return false;
        }
        if (false === ($data = file_get_contents($file))) {
            return false;
        }
        if (false === ($image = @imagecreatefromstring($data))) {
            return false;
        }
        unset($data);
        return $image;
    }
}
