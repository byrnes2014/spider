<?php
namespace Spider\Tests;
include '../autoload.php';
use Spider\Json;


$collectConfig = [
    'url'       =>  'http://www.toutiao.com/search_content/?offset=0&format=json&keyword=今年年假&autoload=true&count=5&_=1470108036164',//要测试的地址
    'test'      =>  true,//是否测试模式
    'regular'   =>  [
        'id'                =>  '2',//如果该测试存放在数据库，则为该规则的id
        'name'              =>  '今日头条Json采集',//改规则的名字
        'encode'            =>  'utf-8',//采集网站的编码
        'url'               =>  'http://www.toutiao.com',//采集地址
        'start'             =>  '0',//json 开始截取位置（标准json无需填写）
        'end'               =>  '0',//json 结束截取位置（标准json无需填写）
        'list'              =>  'data',//json 循环对象(循环本身无需填写)
        'img'               =>  'image_list 0 url',//图片地址
        'imgPath'           =>  '',//图片地址前缀(如完整链接无需填写)
        'url'               =>  'display_url',//详情地址
        'urlPath'           =>  '',//详情页地址前缀(如完整链接无需填写)
        'name'              =>  'title',//文章标题
        'urlPathSuffix'     =>  '',//详情页地址后缀(如完整链接并且无需后缀无需填写)
        'test'              =>  false,//测试json格式是否标准，接下来方便书写

        //详情页面
        'detail_name'       =>  '0',//如果列表页不存在 文章标题 则使用此规则
        'detail_name_pos'   =>  '0',//文章标题位置
        'detail'            =>  'div.article-content',//详情标识
        'detail_pos'        =>  '0',//详情位置标识
        'detail_page'       =>  '',//详情页分页标识
        'detail_page_pos'   =>  '0',//详情页分页位置
        'detail_forbid_tag' =>  '',//禁止的标签
        'really_pic_detail' =>  '0',//详情页图片的真实地址 游戏网站这两者是有区分的
        'review'            =>  '0',//是否需要审核 0 无需审核直接发布 1 审核发布
        'detail_replace'    =>  '',//要替换的关键词
        'end_pos'           =>  '',//一篇文章最后的截取位置
        'start_pos'         =>  '',//一篇文章最开始的截取位置
    ]
];
$htmlObj = new Json($collectConfig);
$data = $htmlObj->getList();