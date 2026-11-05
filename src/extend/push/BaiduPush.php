<?php


namespace happy\admin\libs\extend\push;

use happy\admin\libs\Exception\LibsException;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Cache;

/**
 * 百度搜索引擎链接主动推送（快速收录）客户端
 * 官方文档：https://ziyuan.baidu.com/college/courseinfo?id=267&page=2#h2_article_title5
 *
 * 推送按“站点”分渠道：
 *  pc 渠道：baidu_push_domain + baidu_push_token
 *  wap 渠道：baidu_push_wap_domain + baidu_push_token（移动端域名需在站长平台单独验证）
 *
 * 一个渠道的 site/token 只能推送该站点自己的链接，不能拿 pc 站点的 token 推送移动端域名。
 * 当 pc 渠道当日配额用尽时，可把 pc 链接按相同路径转换为移动端域名，用 wap 渠道继续推送。
 *
 * 常见失败返回：
 *  400 site error                     站点未在站长平台验证
 *  400 empty content                  post 内容为空
 *  400 only 2000 urls allowed once    单次最多提交 2000 条链接
 *  400 over quota                     超过每日配额，超配额后再提交都是无效的
 *  401 token is not valid             token 错误
 *  404 not found                      接口地址填写错误
 *  500 internal error, please try later 服务器偶尔异常，通常重试就会成功
 */
class BaiduPush
{
    /**
     * 渠道标识
     */
    public const CHANNEL_PC = 'pc';
    public const CHANNEL_WAP = 'wap';

    /**
     * 当日停止推送的缓存前缀
     */
    protected const CACHE_PREFIX = 'baidu_push_blocked_';

    /**
     * 配额耗尽降级推送的结果标识（pc 链接转 wap 域名推送）
     */
    protected const RESULT_FALLBACK = 'pc_to_wap';

    /**
     * 错误规则：message 关键字 => [是否可重试, 是否当日停止推送, 停止类型, 说明]
     * type=quota 表示配额用完，可切换移动端域名继续推送；type=fatal 表示配置/站点问题
     *
     * @var array<string, array{retryable: bool, block: bool, type: string, desc: string}>
     */
    protected array $errorRules = [
        'over quota' => ['retryable' => false, 'block' => true, 'type' => 'quota', 'desc' => '当日推送配额已用完，超配额后再提交均无效'],
        'site error' => ['retryable' => false, 'block' => true, 'type' => 'fatal', 'desc' => '站点未在站长平台验证'],
        'token is not valid' => ['retryable' => false, 'block' => true, 'type' => 'fatal', 'desc' => 'token 错误'],
        'not found' => ['retryable' => false, 'block' => true, 'type' => 'fatal', 'desc' => '接口地址填写错误'],
        'empty content' => ['retryable' => false, 'block' => false, 'type' => 'fatal', 'desc' => '提交内容为空'],
        'urls allowed' => ['retryable' => false, 'block' => false, 'type' => 'fatal', 'desc' => '单次提交链接数超过 2000 条'],
        'internal error' => ['retryable' => true, 'block' => false, 'type' => 'fatal', 'desc' => '百度服务端异常，稍后重试'],
    ];

    /**
     * 渠道配置：channel => ['site' => string, 'token' => string]
     * @var array<string, array{site: string, token: string}>
     */
    protected array $channels = [];

    protected mixed $site;           // 兼容旧字段，指向 pc 渠道站点
    protected mixed $token;          // 兼容旧字段，指向 pc 渠道 token
    protected string $apiUrl = 'http://data.zz.baidu.com/urls';

    /**
     * @param $site
     * @param $token
     * @throws LibsException|\Throwable
     */
    public function __construct($site = null, $token = null)
    {
        try {
            $pcSite = $site ?: sys_config('baidu_push_domain');
            $pcToken = $token ?: sys_config('baidu_push_token');
        } catch (DataNotFoundException|ModelNotFoundException|DbException $e) {
            throw new LibsException('百度推送配置 site 或 token 获取失败');
        }

        $pcSite = trim((string)$pcSite);
        $pcToken = trim((string)$pcToken);

        if ($pcSite === '' || $pcToken === '') {
            throw new LibsException('百度推送配置错误：缺少 site 或 token。');
        }

        $this->channels[self::CHANNEL_PC] = ['site' => $pcSite, 'token' => $pcToken];

        // 移动端渠道：域名与 token 需同时配置，移动端域名必须已在站长平台验证
        $wapSite = $this->config('baidu_push_wap_domain');
        $wapToken = $this->config('baidu_push_token');
        if ($wapSite !== '' && $wapToken !== '') {
            $this->channels[self::CHANNEL_WAP] = ['site' => $wapSite, 'token' => $wapToken];
        }

        // 兼容旧字段
        $this->site = $pcSite;
        $this->token = $pcToken;
    }

    /**
     * 安全读取系统配置（不存在时返回空字符串）
     *
     * @param string $name
     * @return string
     */
    protected function config(string $name): string
    {
        try {
            return trim((string)sys_config($name, ''));
        } catch (DataNotFoundException|ModelNotFoundException|DbException|\Throwable) {
            return '';
        }
    }

    /**
     * 获取全部已配置渠道
     *
     * @return array<string, array{site: string, token: string}>
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * 渠道是否可用
     *
     * @param string $channel
     * @return bool
     */
    public function hasChannel(string $channel): bool
    {
        return isset($this->channels[$channel]);
    }

    /**
     * 获取渠道站点
     *
     * @param string $channel
     * @return string
     */
    public function channelSite(string $channel): string
    {
        return $this->channels[$channel]['site'] ?? '';
    }

    /**
     * 获取渠道可用的 URL 根地址（补全协议）
     *
     * @param string $channel
     * @return string
     */
    public function channelBaseUrl(string $channel): string
    {
        $site = $this->channelSite($channel);
        if ($site === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $site)) {
            return rtrim($site, '/');
        }
        return 'https://' . rtrim($site, '/');
    }

    /**
     * 判断 URL 属于哪个渠道（按域名匹配，未匹配的默认 pc）
     *
     * @param string $url
     * @return string
     */
    public function resolveChannel(string $url): string
    {
        return $this->matchChannel($url) ?? self::CHANNEL_PC;
    }

    /**
     * 精确匹配 URL 所属渠道，未匹配返回 null
     *
     * @param string $url
     * @return string|null
     */
    protected function matchChannel(string $url): ?string
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return null;
        }

        foreach ($this->channels as $channel => $config) {
            if ($this->siteHost($config['site']) === $host) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * 提取站点域名（兼容带协议和不带协议的写法）
     *
     * @param string $site
     * @return string
     */
    protected function siteHost(string $site): string
    {
        $site = trim($site);
        if ($site === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $site)) {
            return strtolower((string)parse_url($site, PHP_URL_HOST));
        }
        return strtolower(explode('/', $site)[0]);
    }

    /**
     * 当日是否已停止推送（配额用完、token 失效等不可继续的错误）
     *
     * @param string $channel
     * @return bool
     */
    public function isBlockedToday(string $channel = self::CHANNEL_PC): bool
    {
        return $this->blockedInfo($channel) !== null;
    }

    /**
     * 是否因当日配额用尽而停止推送
     *
     * @param string $channel
     * @return bool
     */
    public function isQuotaBlocked(string $channel = self::CHANNEL_PC): bool
    {
        $info = $this->blockedInfo($channel);
        return $info !== null && ($info['type'] ?? '') === 'quota';
    }

    /**
     * 获取当日停止推送的详情
     *
     * @param string $channel
     * @return array|null
     */
    public function blockedInfo(string $channel = self::CHANNEL_PC): ?array
    {
        try {
            $info = Cache::get($this->cacheKey($channel));
            return is_array($info) ? $info : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 标记当日剩余时间不再推送（次日 0 点自动失效）
     *
     * @param string $reason 停止原因
     * @param mixed $code 百度返回码
     * @param string $channel 渠道
     * @param string $type quota=配额用尽；fatal=配置或站点问题
     * @return void
     */
    public function blockToday(string $reason, mixed $code = null, string $channel = self::CHANNEL_PC, string $type = 'fatal'): void
    {
        $ttl = max(60, strtotime('tomorrow') - time());
        try {
            Cache::set($this->cacheKey($channel), [
                'reason' => $reason,
                'code' => $code,
                'type' => $type,
                'channel' => $channel,
                'time' => date('Y-m-d H:i:s'),
            ], $ttl);
        } catch (\Throwable) {
            // 缓存不可用时忽略，不影响主流程
        }
    }

    /**
     * 解除当日停止推送标记
     *
     * @param string|null $channel 为 null 时解除所有渠道
     * @return void
     */
    public function unblockToday(?string $channel = self::CHANNEL_PC): void
    {
        try {
            if ($channel === null) {
                foreach (array_keys($this->channels) as $name) {
                    Cache::delete($this->cacheKey($name));
                }
                return;
            }
            Cache::delete($this->cacheKey($channel));
        } catch (\Throwable) {
            // 忽略
        }
    }

    /**
     * 推送单个 URL（自动按域名分配渠道）
     *
     * @param string $url 完整的 URL，如：https://www.example.com/article/123
     * @return array
     */
    public function push(string $url): array
    {
        return $this->pushBatch([$url]);
    }

    /**
     * 批量推送多个 URL
     *
     * 处理流程：
     *  1. 按域名把链接分配到 pc / wap 渠道
     *  2. 各渠道用自己的 site + token 推送
     *  3. pc 渠道当日配额用尽时，把 pc 链接转换为移动端域名，交给 wap 渠道继续推送
     *
     * @param array $urls URL 列表
     * @return array
     */
    public function pushBatch(array $urls): array
    {
        // 过滤空值和无效 URL
        $urls = array_values(array_filter($urls, function ($url) {
            return !empty($url) && is_string($url) && filter_var($url, FILTER_VALIDATE_URL);
        }));

        if (empty($urls)) {
            return [
                'success' => false,
                'msg' => '没有有效的 URL 需要推送',
                'result' => null,
                'results' => [],
                'urls' => [],
                'retryable' => false,
                'block' => false,
                'skipped' => true,
                'http_code' => 0,
                'error_code' => null,
                'desc' => '没有有效的 URL 需要推送',
                'channel' => '',
            ];
        }

        // 按渠道分组：域名必须与已配置渠道站点一致，否则跳过
        // （避免移动端链接用 PC 渠道 token 推送，触发 site error 封锁 PC 当日配额）
        $groups = [];
        $unmatched = [];
        foreach ($urls as $url) {
            $channel = $this->matchChannel($url);
            if ($channel === null) {
                $unmatched[] = $url;
                continue;
            }
            $groups[$channel][] = $url;
        }

        $results = [];
        foreach ($groups as $channel => $channelUrls) {
            $results[$channel] = $this->pushChannel($channel, $channelUrls);
        }

        if (!empty($unmatched)) {
            $results['unmatched'] = [
                'success' => false,
                'msg' => '域名与已配置的推送站点不匹配，已跳过',
                'result' => null,
                'urls' => $unmatched,
                'retryable' => false,
                'block' => false,
                'skipped' => true,
                'http_code' => 0,
                'error_code' => null,
                'desc' => '域名与已配置的推送站点不匹配',
                'channel' => 'unmatched',
            ];
        }

        // pc 配额用尽 → 转移动端域名继续推送
        if ($this->shouldFallbackToWap($results, $groups)) {
            $candidates = $results[self::CHANNEL_PC]['urls'] ?? $groups[self::CHANNEL_PC];
            $converted = $this->convertUrls($candidates, self::CHANNEL_WAP);

            // 已经在 wap 分组里成功推送过的链接不再重复推送
            $converted = array_values(array_diff($converted, $groups[self::CHANNEL_WAP] ?? []));

            if (!empty($converted)) {
                $results[self::RESULT_FALLBACK] = $this->pushChannel(self::CHANNEL_WAP, $converted);
            }
        }

        return $this->aggregate($results, $urls);
    }

    /**
     * 是否需要把 pc 链接降级到 wap 渠道推送
     *
     * @param array $results
     * @param array $groups
     * @return bool
     */
    protected function shouldFallbackToWap(array $results, array $groups): bool
    {
        if (empty($groups[self::CHANNEL_PC])) {
            return false;
        }

        if (!isset($results[self::CHANNEL_PC]) || $results[self::CHANNEL_PC]['success']) {
            return false;
        }

        // 只有配额用尽才降级，站点未验证/token 错误等配置问题不降级
        if (!$this->isQuotaBlocked(self::CHANNEL_PC)) {
            return false;
        }

        return $this->hasChannel(self::CHANNEL_WAP) && !$this->isBlockedToday(self::CHANNEL_WAP);
    }

    /**
     * 把链接按相同的 path/query 转换到目标渠道域名
     *
     * @param array $urls
     * @param string $targetChannel
     * @return array
     */
    public function convertUrls(array $urls, string $targetChannel): array
    {
        $base = $this->channelBaseUrl($targetChannel);
        if ($base === '') {
            return [];
        }

        $result = [];
        foreach ($urls as $url) {
            $path = (string)parse_url($url, PHP_URL_PATH);
            $query = parse_url($url, PHP_URL_QUERY);

            $path = $path === '' ? '/' : $path;
            $result[] = $base . $path . ($query ? '?' . $query : '');
        }

        return array_values(array_unique($result));
    }

    /**
     * 向指定渠道推送链接
     *
     * @param string $channel
     * @param array $urls
     * @return array
     */
    protected function pushChannel(string $channel, array $urls): array
    {
        $site = $this->channelSite($channel);
        $token = $this->channels[$channel]['token'] ?? '';

        if ($site === '' || $token === '') {
            return [
                'success' => false,
                'msg' => "推送渠道 {$channel} 未配置 site 或 token",
                'result' => null,
                'urls' => $urls,
                'retryable' => false,
                'block' => false,
                'skipped' => true,
                'http_code' => 0,
                'error_code' => null,
                'desc' => '渠道未配置',
                'channel' => $channel,
            ];
        }

        // 当日已停止推送，直接跳过，避免无效请求
        if ($this->isBlockedToday($channel)) {
            $blocked = $this->blockedInfo($channel);
            return [
                'success' => false,
                'msg' => "渠道 {$channel} 今日已停止推送：" . ($blocked['reason'] ?? '不可继续推送'),
                'result' => $blocked,
                'urls' => $urls,
                'retryable' => false,
                'block' => true,
                'skipped' => true,
                'http_code' => 0,
                'error_code' => $blocked['code'] ?? null,
                'desc' => $blocked['reason'] ?? '今日已停止推送',
                'channel' => $channel,
            ];
        }

        // 百度接口的 site 只接受站点域名，带协议会被判定为 site error
        $siteParam = $this->siteHost($site) ?: $site;
        $apiUrl = $this->apiUrl . '?site=' . urlencode($siteParam) . '&token=' . urlencode($token);

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => implode("\n", $urls),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: text/plain',
                    'Accept: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return [
                    'success' => false,
                    'msg' => 'CURL 错误: ' . $error,
                    'result' => null,
                    'post' => $apiUrl,
                    'urls' => $urls,
                    'http_code' => 0,
                    'error_code' => null,
                    'desc' => '网络请求失败',
                    'retryable' => true,
                    'block' => false,
                    'skipped' => false,
                    'channel' => $channel,
                ];
            }

            $result = json_decode($response, true);

            // 百度返回 200 并且成功提交了链接
            if ($httpCode === 200 && isset($result['success']) && (int)$result['success'] > 0) {
                $msg = "推送成功，成功提交 {$result['success']} 个链接。";
                if (isset($result['remain']) && is_numeric($result['remain'])) {
                    $msg .= "今日剩余配额 {$result['remain']} 条。";
                    // 剩余配额已为 0，当日后续推送必然无效，直接停止
                    if ((int)$result['remain'] <= 0) {
                        $this->blockToday('当日推送配额已用完', 200, $channel, 'quota');
                    }
                }
                return [
                    'success' => true,
                    'msg' => $msg,
                    'result' => $result,
                    'post' => $apiUrl,
                    'urls' => $urls,
                    'http_code' => $httpCode,
                    'error_code' => null,
                    'desc' => '',
                    'retryable' => false,
                    'block' => false,
                    'skipped' => false,
                    'channel' => $channel,
                ];
            }

            // 失败：百度返回格式为 {"error":400,"message":"over quota"}
            $errorCode = $result['error'] ?? null;
            $errorMessage = trim((string)($result['message'] ?? ''));
            $desc = $errorMessage !== '' ? $errorMessage : (is_string($response) && $response !== '' ? mb_substr($response, 0, 200) : '未知错误');

            [$retryable, $block, $blockType, $knownDesc] = $this->classify($errorCode, $errorMessage, $httpCode);

            // success 为 0 且无错误信息，多为链接已提交过（not_same_site），重试无意义
            if ($httpCode === 200 && isset($result['success']) && (int)$result['success'] === 0 && $errorMessage === '') {
                $retryable = false;
                $block = false;
                $knownDesc = '没有链接被成功提交，可能已推送过';
            }

            if ($knownDesc !== '') {
                $desc = $knownDesc . ($errorMessage !== '' ? '（' . $errorMessage . '）' : '');
            }

            // 不可继续的错误：当日不再推送
            if ($block) {
                $this->blockToday($knownDesc ?: $desc, $errorCode, $channel, $blockType);
            }

            return [
                'success' => false,
                'msg' => "渠道 {$channel} 推送失败：{$desc}",
                'result' => $result,
                'post' => $apiUrl,
                'urls' => $urls,
                'http_code' => $httpCode,
                'error_code' => $errorCode,
                'desc' => $knownDesc ?: $desc,
                'retryable' => $retryable,
                'block' => $block,
                'skipped' => false,
                'channel' => $channel,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'msg' => '推送异常：' . $e->getMessage(),
                'result' => null,
                'post' => $apiUrl,
                'urls' => $urls,
                'http_code' => 0,
                'error_code' => null,
                'desc' => '推送异常',
                'retryable' => true,
                'block' => false,
                'skipped' => false,
                'channel' => $channel,
            ];
        }
    }

    /**
     * 汇总各渠道的推送结果
     *
     * @param array $results
     * @param array $urls
     * @return array
     */
    protected function aggregate(array $results, array $urls): array
    {
        $success = true;
        $retryable = false;
        $block = false;
        $skipped = true;
        $failed = null;
        $messages = [];

        // wap 渠道是否已成功推送（用于判断 pc 配额耗尽时链接是否已被移动端覆盖）
        $wapCovered = !empty($results[self::CHANNEL_WAP]['success'])
            || !empty($results[self::RESULT_FALLBACK]['success']);

        foreach ($results as $key => $item) {
            $messages[] = "[{$key}] " . $item['msg'];

            $skipped = $skipped && !empty($item['skipped']);
            $block = $block || !empty($item['block']);

            // pc 配额用尽，但相同链接已通过移动端推送成功，视为已覆盖，不算失败
            $covered = $key === self::CHANNEL_PC
                && !$item['success']
                && $this->isQuotaBlocked(self::CHANNEL_PC)
                && $wapCovered;

            if (!$item['success'] && !$covered) {
                $success = false;
                if ($failed === null) {
                    $failed = $item;
                }
                $retryable = $retryable || !empty($item['retryable']);
            }
        }

        $msg = implode('；', $messages);
        if ($success && empty($results[self::CHANNEL_PC]['success']) && !empty($results[self::RESULT_FALLBACK]['success'])) {
            $msg .= '；pc 每日配额已用完，已切换移动端域名继续推送';
        }

        return [
            'success' => $success,
            'msg' => $msg,
            'result' => $failed['result'] ?? null,
            'results' => $results,
            'urls' => $urls,
            'retryable' => $retryable,
            'block' => $block,
            'skipped' => $skipped,
            'http_code' => $failed['http_code'] ?? 0,
            'error_code' => $failed['error_code'] ?? null,
            'desc' => $failed['desc'] ?? '',
            'channel' => implode(',', array_keys($results)),
        ];
    }

    /**
     * 根据百度返回码与 message 判断是否可重试、是否当日停止推送
     *
     * @param mixed $code 百度返回的 error 字段
     * @param string $message 百度返回的 message 字段
     * @param int $httpCode HTTP 状态码
     * @return array{0: bool, 1: bool, 2: string, 3: string}
     */
    protected function classify(mixed $code, string $message, int $httpCode): array
    {
        $message = strtolower(trim($message));

        if ($message !== '') {
            foreach ($this->errorRules as $keyword => $rule) {
                if (str_contains($message, $keyword)) {
                    return [$rule['retryable'], $rule['block'], $rule['type'], $rule['desc']];
                }
            }
        }

        // HTTP 5xx 为服务端偶发异常，可重试
        if ($httpCode >= 500 || (int)$code >= 500) {
            return [true, false, 'fatal', '百度服务端异常，稍后重试'];
        }

        // 其它 4xx 视为不可继续，但不封锁当日（可能是本次请求内容问题）
        if ($httpCode >= 400 || (int)$code >= 400) {
            return [false, false, 'fatal', '推送请求被拒绝'];
        }

        return [true, false, 'fatal', '未知的推送错误'];
    }

    /**
     * 当日停止推送的缓存 key（按渠道站点区分）
     *
     * @param string $channel
     * @return string
     */
    protected function cacheKey(string $channel): string
    {
        return self::CACHE_PREFIX . md5($this->channelSite($channel));
    }
}
