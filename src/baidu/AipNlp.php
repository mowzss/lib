<?php

namespace mowzs\lib\baidu;

use mowzs\lib\helper\HttpHelper;
use think\Exception;

class AipNlp extends BaseBaiduAip
{
    protected $keywordUrl = 'https://aip.baidubce.com/rpc/2.0/nlp/v1/keyword?charset=UTF-8&access_token=';

    /**
     * 获取字符型Tag
     * @param string $title 标题
     * @param string $content 内容
     * @param int $num 获取数量 默认3个
     * @param string $delimiter 分隔符 默认“,”
     * @return string
     * @throws Exception
     */
    public function getStringTag($title, $content, $num = 3, $delimiter = ',')
    {
        $tag = $this->getTag($title, $content);
        $tags = [];
        if (empty($tag['items'])) {
            return '';
        }
        foreach ($tag['items'] as $item => $value) {
            if ($item < $num) {
                $tags[] = $value['tag'];
            }
        }
        return implode($delimiter, $tags);
    }

    /**
     * 获取tag
     * @param string $title 标题
     * @param string $content 内容
     * @return mixed
     * @throws Exception
     */
    public function getTag($title, $content)
    {
        $access = $this->Auth();

        $this->keywordUrl = $this->keywordUrl . $access;
        $post_data = [
            'title' => $title,
            'content' => $content
        ];

        $data = HttpHelper::instance()->post(
            $this->keywordUrl, ['form_params' => $post_data, 'headers' => ['Content-Type: application/json']]
        );

        return $data['data'];
    }
}
