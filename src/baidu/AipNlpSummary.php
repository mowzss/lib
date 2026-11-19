<?php


namespace happy\admin\libs\baidu;

use happy\admin\libs\helper\HttpHelper;
use think\Exception;

class AipNlpSummary extends BaseBaiduAip
{
    protected $summaryUrl = 'https://aip.baidubce.com/rpc/2.0/nlp/v1/news_summary?charset=UTF-8';


    /**
     * 获取tag
     * @param string $title 标题
     * @param string $content 内容
     * @return mixed
     * @throws Exception|\GuzzleHttp\Exception\GuzzleException
     */
    public function getSummary(string $title, string $content): mixed
    {
        $access = $this->Auth(); // 假设 Auth() 返回有效的 access_token

        // 构建完整的请求 URL
        $url = $this->summaryUrl . '&access_token=' . $access;

        // 准备请求数据
        $post_data = [
            'title' => $title,
            'content' => $content,
        ];
        // 发起 POST 请求
        $data = HttpHelper::instance()->post(
            $url,
            ['json' => $post_data, 'headers' => ['Content-Type' => 'application/json']]
        );
        return $data['summary'] ?: '';
    }
}
