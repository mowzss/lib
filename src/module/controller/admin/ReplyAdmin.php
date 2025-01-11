<?php
declare (strict_types=1);

namespace mowzs\lib\module\controller\admin;

use app\common\controllers\BaseAdmin;
use app\common\traits\CrudTrait;
use app\model\article\ArticleReply;
use think\App;

/**
 * 评论管理
 */
abstract class ReplyAdmin extends BaseAdmin
{
    use CrudTrait;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->model = new ArticleReply();
        $this->setParams();
    }

    protected function setParams(): void
    {
        // 定义表格字段
        $this->tables['fields'] = [
            [
                'field' => 'id',
                'title' => 'ID',
                'width' => 80,
                'sort' => true,
            ],
            [
                'field' => 'user_name',
                'title' => '用户名',
            ],
            [
                'field' => 'article_title',
                'title' => '文章标题',
            ],
            [
                'field' => 'content',
                'title' => '评论内容',
                'type' => 'textarea',
            ],
            [
                'field' => 'agree',
                'title' => '支持数',
                'width' => 80,
                'sort' => true,
            ],
            [
                'field' => 'disagree',
                'title' => '反对数',
                'width' => 80,
                'sort' => true,
            ],
            [
                'field' => 'reply_count',
                'title' => '回复数',
                'width' => 80,
                'sort' => true,
            ],
            [
                'field' => 'phone_type',
                'title' => '发表来自',
                'width' => 150,
            ],
            [
                'field' => 'status',
                'title' => '状态',
                'templet' => 'switch'
            ],
            [
                'field' => 'create_time',
                'title' => '创建时间',
                'sort' => true,
            ],
        ];

        // 定义表单字段
        $this->forms['fields'] = [
            [
                'type' => 'text',
                'name' => 'aid',
                'label' => '文章ID',
            ],
            [
                'type' => 'text',
                'name' => 'pid',
                'label' => '上级回复ID (可选)',
            ],
            [
                'type' => 'radio',
                'name' => 'ispic',
                'label' => '是否带组图',
                'options' => [0 => '否', 1 => '是'],
            ],
            [
                'type' => 'number',
                'name' => 'agree',
                'label' => '支持数',
            ],
            [
                'type' => 'number',
                'name' => 'disagree',
                'label' => '反对数',
            ],
            [
                'type' => 'number',
                'name' => 'list',
                'label' => '排序值',
            ],
            [
                'type' => 'text',
                'name' => 'picurl',
                'label' => '封面图URL',
            ],
            [
                'type' => 'textarea',
                'name' => 'content',
                'label' => '评论内容',
            ],
            [
                'type' => 'number',
                'name' => 'reply_count',
                'label' => '回复数',
            ],
            [
                'type' => 'text',
                'name' => 'phone_type',
                'label' => '发表来自什么手机',
            ],
            [
                'type' => 'radio',
                'name' => 'status',
                'label' => '状态',
                'options' => [1 => '审核通过', 0 => '未审核'],
            ],
            [
                'type' => 'datetime',
                'name' => 'create_time',
                'label' => '创建时间',
            ],
        ];

        // 定义搜索条件
        $this->search = [
            'id#=#id',
            'uid#=#user_id',
            'aid#=#article_id',
            'pid#=#parent_id',
            'ispic#=#has_picture',
            'agree#=#like#agree',
            'disagree#=#like#disagree',
            'reply_count#=#like#reply_count',
            'phone_type#like#phone_type',
            'status#=#status',
            'create_time#between#create_time',
        ];
    }

    /**
     * 处理列表数据
     * @param array $data
     * @return void
     */
    protected function _index_list_filter(array &$data): void
    {
        // 获取所有文章的标题映射
        $articleTitles = $this->getArticleTitles();

        // 确保 data['data'] 存在并且是一个数组
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as &$v) {
                // 设置用户名
                if (!empty($v['uid'])) {
                    $user = $this->getUserById($v['uid']);
                    $v['user_name'] = $user ? $user->username : '未知用户';
                } else {
                    $v['user_name'] = '';
                }

                // 设置文章标题
                if (!empty($v['aid']) && isset($articleTitles[$v['aid']])) {
                    $v['article_title'] = $articleTitles[$v['aid']];
                } else {
                    $v['article_title'] = '未知文章';
                }
            }
            unset($v); // 解除引用
        }
    }

    /**
     * 根据用户ID获取用户信息
     * @param int $userId
     * @return mixed|null
     */
    protected function getUserById(int $userId)
    {
        // 假设有一个 User 模型用于获取用户信息
        return \app\model\User::find($userId);
    }

    /**
     * 获取文章标题映射
     * @return array
     */
    protected function getArticleTitles(): array
    {
        // 假设有一个 Article 模型用于获取文章信息
        $articles = \app\model\Article::select()->toArray();
        return array_column($articles, 'title', 'id');
    }

    /**
     * 获取用户选项
     * @return array
     */
    protected function getUserOptions(): array
    {
        // 获取所有用户的选项列表
        $users = \app\model\User::select()->toArray();
        return array_column($users, 'username', 'id');
    }
}
