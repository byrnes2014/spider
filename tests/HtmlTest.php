<?php
namespace Spider\Tests;
include '../autoload.php';
use Spider\Html;

/*class HtmlListTest extends \PHPUnit_Framework_TestCase {

    public function testGetList(){
        $this->assertEquals(1,HtmlList::getList());
    }

}*/
$collectConfig = [
    'url'       =>  'http://www.qulishi.com/fengyun/',//要测试的地址
    'test'      =>  true,//是否测试模式
    'regular'   =>  [
        'id'                =>  '1',//如果该测试存放在数据库，则为该规则的id
        'name'              =>  '趣历史采集',//改规则的名字
        'encode'            =>  'utf-8',//采集网站的编码
        'list'              =>  'div.j31List',//列表页位置
        'list_pos'          =>  '0',//列表页位置
        'really_pic'        =>  false,//图片的真实地址 有些网站把data-origin 作为真实地址
        'list_cricle'       =>  'dl',//列表页循环标识
        'list_url'          =>  'dt a',//详情页url
        'list_url_pos'      =>  '0',//详情页url位置
        'url'               =>  'http://www.qulishi.com',//采集地址
        'assign_url'        =>  'http://www.qulishi.com/news/201703/186569.html',//指定链接测试
        'list_name'         =>  'dt a',//文章标题标识
        'list_name_pos'     =>  '0',//文章标题位置
        'list_img'          =>  'img',//文章封面标识
        'list_img_pos'      =>  '0',//文章封面位置

        //详情页面
        'detail_name'       =>  '0',//如果列表页不存在 文章标题 则使用此规则
        'detail_name_pos'   =>  '0',//文章标题位置
        'detail'            =>  'div#news_main',//详情标识 支持多个查找 用 | 分割
        'detail_pos'        =>  '0',//详情位置标识
        'detail_page'       =>  'div.page1',//详情页分页标识
        'detail_page_pos'   =>  '0',//详情页分页位置
        'detail_forbid_tag' =>  'div',//禁止的标签
        'really_pic_detail' =>  '0',//详情页图片的真实地址 游戏网站这两者是有区分的
        'review'            =>  '0',//是否需要审核 0 无需审核直接发布 1 审核发布
        'detail_replace'    =>  '免责声明：以上内容源自网络，版权归原作者所有，如有侵犯您的原创版权请告知，我们将尽快删除相关内容。',//要替换的关键词
        'end_pos'           =>  '相关阅读推荐：',//一篇文章最后的截取位置 支持多个截取位置 用 | 分割
        'start_pos'         =>  '',//一篇文章最开始的截取位置
        'forbid_first_page' =>  1,//禁止首页采集 1 开启
        'is_self_news'      =>  1,//检测分页是否存在不是本新闻的链接 防止出现死链情况
        'mult_content'      =>  '[
                                    {"name":"p.xg_zt","pos":0,"val":"innertext"},
                                    {"name":"div.page1","pos":0,"val":"innertext"}
        ]',//支持详情页返回多个内容 限定于第一页中的内容
    ]
];
$htmlObj = new Html($collectConfig);
$htmlObj->getList();