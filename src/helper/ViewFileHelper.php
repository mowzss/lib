<?php

namespace mowzs\lib\helper;

use mowzs\lib\Helper;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

class ViewFileHelper extends Helper
{
    /**
     * 获取主题路径
     * @param string $module 模块
     * @param string $controller 控制器
     * @param bool $mobile 是否手机端
     * @return string
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    protected function getThemePath(string $module = 'article', string $controller = 'column', bool $mobile = false): string
    {
        $view_root_path = $this->app->getRootPath() . 'view' . DIRECTORY_SEPARATOR . 'home_style';
        if ($mobile) {
            $theme = sys_config('home_wap_style', 'default');
        } else {
            $theme = sys_config('home_pc_style', 'default');
        }
        return $view_root_path . DIRECTORY_SEPARATOR . $theme . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . $controller;
    }

    /**
     * 获取主题视图下的模板文件列表
     * @param string $module 模块名
     * @param string $controller 控制器名
     * @param bool $mobile 是否为移动端
     * @return array 返回符合条件的文件列表
     */
    public function getThemeView(string $module = 'article', string $controller = 'column', bool $mobile = false): array
    {
        try {
            $path = $this->getThemePath($module, $controller, $mobile);
        } catch (DataNotFoundException|ModelNotFoundException|DbException $e) {
            return [];
        }

        // 检查路径是否存在
        if (!is_dir($path)) {
            return [];
        }

        // 初始化结果数组
        $result = [];

        // 打开目录并读取文件
        if ($handle = opendir($path)) {
            while (false !== ($file = readdir($handle))) {
                // 跳过 "." 和 ".."
                if ($file === '.' || $file === '..') {
                    continue;
                }
                // 筛选以 "index" 开头且以 ".html" 结尾的文件
                if (strpos($file, 'index') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'html') {
                    // 移除文件扩展名并添加到结果数组
                    $fileNameWithoutExtension = pathinfo($file, PATHINFO_FILENAME);
                    $result[$fileNameWithoutExtension] = $fileNameWithoutExtension;
                }
            }
            closedir($handle);
        }

        return $result;
    }
}
