<?php

namespace mowzs\lib\helper;

use mowzs\lib\Helper;

class TemplateHelper extends Helper
{
    /**
     * 模板类型的映射
     */
    protected array $templateTypes = [
        'admin' => 'admin_style',
        'home' => 'home_style',
        'user' => 'user_style',
    ];

    /**
     * 获取指定类型下的所有风格信息
     *
     * @param string $type 模板类型 (admin, home, user)
     * @return array
     */
    public function getStyleInfo(string $type): array
    {
        if (!isset($this->templateTypes[$type])) {
            throw new \InvalidArgumentException("未知的模板类型: {$type}");
        }

        $styles = [];
        $path = app()->getRootPath() . 'view/' . $this->templateTypes[$type];

        if (is_dir($path)) {
            $iterator = new \DirectoryIterator($path);
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    $styleName = $file->getFilename();
                    $infoFile = $file->getPathname() . '/info.php';

                    if (is_file($infoFile)) {
                        $styles[$styleName] = include $infoFile;
                    }
                }
            }
        }

        return $styles;
    }

    /**
     * 获取指定类型下的默认风格名称
     *
     * @param string $type 模板类型 (admin, home, user)
     * @return string|null
     */
    public function getDefaultStyleName(string $type): ?string
    {
        $styles = $this->getStyleInfo($type);
        return isset($styles['default']) ? 'default' : null;
    }

    /**
     * 获取指定类型下的模板数据
     *
     * @param string $type 模板类型 (admin, home, user)
     * @return array
     */
    public function getTemplateData(string $type): array
    {
        $arr = [];
        $data = $this->getStyleInfo($type);
        foreach ($data as $name => $value) {
            $arr[$name] = $value['name'];
        }
        return $arr;
    }
}
