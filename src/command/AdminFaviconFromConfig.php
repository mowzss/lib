<?php

namespace mowzs\lib\command;


use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;
use think\facade\Request;

class AdminFaviconFromConfig extends Command
{
    protected function configure()
    {
        // 配置命令信息
        $this->setName('admin:favicon')
            ->setDescription('根据配置生成favicon.ico并保存到public目录');
    }

    protected function execute(Input $input, Output $output)
    {
        // 获取配置值
        $logoUrl = sys_config('square_logo');

        if (empty($logoUrl)) {
            $output->writeln("配置项 'square_logo' 的值为空，无法生成 favicon.");
            return;
        }

        // 处理相对协议 URL
        if (strpos($logoUrl, '//') === 0) {
            $scheme = Request::instance()->isSsl() ? 'https:' : 'http:';
            $logoUrl = $scheme . $logoUrl;
        }

        // 设置临时文件路径
        $tempFilePath = tempnam(sys_get_temp_dir(), 'favicon_') . '.png';

        try {
            // 下载远程图片
            $imgContent = file_get_contents($logoUrl);
            if ($imgContent === false) {
                throw new \Exception("无法下载远程图片: {$logoUrl}");
            }
            file_put_contents($tempFilePath, $imgContent);

            if (!file_exists($tempFilePath)) {
                throw new \Exception("无法写入临时图片文件");
            }

            // 加载图像
            $image = imagecreatefromstring(file_get_contents($tempFilePath));
            if (!$image) {
                throw new \Exception("无法加载图像");
            }

            // 创建一个新的真彩色图像
            $newImage = imagecreatetruecolor(32, 32);
            imagecopyresampled($newImage, $image, 0, 0, 0, 0, 32, 32, imagesx($image), imagesy($image));

            // 设置目标路径
            $targetPath = public_path('favicon.ico');

            // 将图像转换为 ICO 格式并保存
            $success = $this->imageico($newImage, $targetPath);

            if ($success) {
                $output->writeln("成功生成 favicon.ico 并保存到: {$targetPath}");
            } else {
                throw new \Exception("无法生成或保存 ICO 文件");
            }

            // 清理临时文件
            unlink($tempFilePath);
        } catch (\Exception $e) {
            Log::error('生成 favicon.ico 失败: ' . $e->getMessage());
            $output->writeln("生成 favicon.ico 失败: " . $e->getMessage());

            // 清理临时文件
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
        }
    }

    /**
     * Convert and save the image as an ICO file.
     *
     * @param resource $image The image resource to convert.
     * @param string $filename The path where to save the ICO file.
     * @return bool Returns TRUE on success or FALSE on failure.
     */
    private function imageico($image, $filename)
    {
        // Create a blank true color image for the icon header
        $header = imagecreatetruecolor(16, 16);
        $white = imagecolorallocate($header, 255, 255, 255);
        imagefill($header, 0, 0, $white);

        // Start packing data
        $icoData = '';
        $icoData .= "\x00\x00"; // Reserved
        $icoData .= "\x01\x00"; // Image type (ICO)
        $icoData .= "\x01\x00"; // Number of images

        // First image entry
        $icoData .= pack("Vvvvv", 32, 32, 0, 0, 40); // Dimensions and bit count
        $icoData .= pack("V", strlen($icoData)); // Size of image data
        $icoData .= pack("V", 22); // Offset of image data

        ob_start();
        imagepng($image);
        $pngData = ob_get_clean();

        $fp = fopen($filename, 'wb');
        fwrite($fp, $icoData);
        fwrite($fp, $pngData);
        fclose($fp);

        return true;
    }
}
