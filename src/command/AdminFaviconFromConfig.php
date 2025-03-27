<?php

namespace mowzs\lib\command;

use app\logic\system\SystemConfigLogic;
use Exception;
use mowzs\lib\helper\ImageToIcoHelper;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Request;

class AdminFaviconFromConfig extends Command
{
    /**
     * @return void
     */
    protected function configure(): void
    {
        // 配置命令信息
        $this->setName('admin:favicon')
            ->setDescription('根据配置生成favicon.ico并保存到public目录');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     *
     */
    protected function execute(Input $input, Output $output): void
    {
        // 获取配置值
        $logoUrl = SystemConfigLogic::instance()->getConfigValue('square_logo');

        if (empty($logoUrl)) {
            $output->writeln("配置项 'square_logo' 的值为空，无法生成 favicon.");
            return;
        }

        // 处理相对协议 URL
        if (strpos($logoUrl, '//') === 0) {
            $scheme = Request::instance()->isSsl() ? 'https:' : 'http:';
            $logoUrl = $scheme . $logoUrl;
        }
        // 设置目标路径（确保没有多余的斜杠）
        $targetPath = public_path() . 'favicon.ico'; // 确保使用正确的路径分隔符
        try {
            // 创建ImageToIcoHelper实例
            $icoHelper = new ImageToIcoHelper();

            // 添加图像（可以是本地路径或远程URL），并指定输出尺寸（例如 16x16）
            $icoHelper->addImage($logoUrl, [32, 32]);

            // 将生成的ICO图标保存到指定路径
            if ($icoHelper->saveIco($targetPath)) {
                $output->writeln("favicon.ico 生成成功。");
            } else {
                $output->writeln("favicon.ico 生成失败。");
            }
        } catch (Exception $e) {
            $output->writeln("发生错误：" . $e->getMessage());
        }
    }
}
