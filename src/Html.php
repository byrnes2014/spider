<?php
namespace Spider;

use Spider\Functions\simple_html_dom;

class Html extends Base{

    public $dom = '';
    public $data = [];//采集的网页信息
    public $content = '';//url内容
    public $baseNum = '';//如果存在分页，则此为第一原始页数字
    public $forbidStop = false;//停止检测分页

    /**
     * @param array $param 将要测试的数据生成对象
     */
    public function __construct(array $param){
        parent::__construct($param);
    }

    /**
     * 抓取列表内容
     * @return array|bool
     */
    public function getList(){
        $this->content = $this->getContent($this->url,$this->regular->encode);
        if(!$this->content){
            return $this->_parseError('can\'t connect this url');
        }

        //有些网站将src 属性设置成了data-original
        if($this->regular->really_pic){
            $this->content = self::_parseReallyImg($this->regular->really_pic,$this->content);
        }

        $this->dom = new simple_html_dom($this->content);
        $listObj = $this->dom->find($this->regular->list,$this->regular->list_pos);
        if(!$listObj){
            return $this->_parseError('列表 '.$this->regular->list.' 第 '.$this->regular->list_pos.' 个位置不正确');
        }

        $circle = $listObj->find($this->regular->list_cricle);
        if(!$circle){
            return $this->_parseError('列表页循环标识未找到 '.$this->regular->list_cricle);
        }

        $return = [];
        foreach($circle as $key=>$val){
            $arr = [];

            $href = $val->href ? $val : $val->find($this->regular->list_url,$this->regular->list_url_pos);//详情页内容url 有些变态的网站就是循环的a标签 所以加此判断
            if(!$href){
                if($this->test){ //测试模式打印错误
                    return $this->_parseError('详情页url错误 '.$this->regular->list_url.'位置'.$this->regular->list_url_pos);
                }
                continue;
            }
            $arr['url'] = strpos($href->href,'http') === false ? $this->regular->url.'/'.ltrim($href->href,'/') : $href->href;//防止使用相对路径

            //如果列表页存在标题的情况
            $arr['name'] = '';
            if($this->regular->list_name){
                $name = $val->find($this->regular->list_name,$this->regular->list_name_pos);//文章标题
                if(!$name){
                    if($this->test) return $this->_parseError('文章标题标识错误 '.$this->regular->list_name.' 位置 '.$this->regular->list_name_pos);//测试模式下打印错误
                } else {
                    $arr['name'] = trim(strip_tags($name->innertext));
                }
            }

            //判断是否有封面图需要采集
            if($this->regular->list_img){
                $img = $val->find($this->regular->list_img,$this->regular->list_img_pos);
                //如果不存在图片，并且在测试模式下，则打印错误信息 否则跳过封面
                if(!$img){
                    if($this->test)  return $this->_parseError('文章封面标识错误 '.$this->regular->list_img.' 位置 '.$this->regular->list_img_pos);
                } else {
                    $arr['img'] = $img->src;
                }
            }
            if($this->test){
                if(isset($this->regular->assign_url) && $this->regular->assign_url){
                    $arr['url'] = $this->regular->assign_url;
                }
                $this->getDetail($arr);
            } else{
                $return[] = $arr;
            }
        }
        return $return;
    }

    /**
     * 抓取详情页的内容
     * @param $arr 条件数组
     * @return array|bool
     */
    public function getDetail($arr){
        $this->url = trim($arr['url']);
        $content = $this->getContent($this->url,$this->regular->encode);
        if(!$content){
            return $this->_parseError('详情页地址错误 '.$arr['url']);
        }

        $this->dom = new simple_html_dom(str_replace('\\','',$content));

        //如果列表页取不到标题
        if($this->regular->detail_name){
            $detailName = $this->dom->find($this->regular->detail_name,$this->regular->detail_name_pos);
            if(!$detailName){
                return $this->_parseError('内容标题标识错误 '.$this->regular->detail_name.'第'.$this->regular->detail_name_pos.'个位置不正确');
            }
            $arr['name'] = $detailName->innertext;
        }

        $str = '内容详情标识错误 '.$this->regular->detail.'第'.$this->regular->detail_pos.'个位置不正确';
        $detailObj = $this->_parseMultMark($this->dom,$this->regular->detail,$this->regular->detail_pos,$str);//处理内容详情文字
        if(!$detailObj) return false;

        //截取开始和结束的制定标识
        $this->data['content'] = $this->_cutEndStartPos($detailObj->innertext);

        //寻找第一页中要得到的另外一些内容
        if(isset($this->regular->mult_content) && $this->regular->mult_content){
            $this->_parseMultContent();
        }

        //提前切割好需要替换的字符
        $this->regular->detail_replace = array_filter(explode("\r\n",$this->regular->detail_replace));

        //SEO关键词
        $key = $this->dom->find('meta[name="keywords"]',0);
        $this->data['keywords'] = '';
        if($key){
            $this->data['keywords'] = $this->_replace(str_replace(['_',';',' '],',',$key->content));
            $this->data['keywords'] = trim($this->data['keywords']);
        }
        //SEO描述
        $description = $this->dom->find('meta[name="description"]',0);
        $this->data['description'] = '';
        if($description){
            $this->data['description'] = $this->_replace($description->content);
            $this->data['description'] = trim($this->data['description']);
        }
        //SEO标题
        $title = $this->dom->find('title',0);
        $this->data['title'] = '';
        if($title){
            $this->data['title'] = $this->_replace($title->innertext);
            $this->data['title'] = trim($this->data['title']);
        }

        //如果文章存在分页则 寻找下一页内容
        if($this->regular->detail_page){
            $pages = $this->dom->find($this->regular->detail_page,$this->regular->detail_page_pos);
            if($pages){
                $pageHtml = $pages->innertext;
                $this->baseNum = self::_extractNum($this->url);//获取第一页原始数字
                $nextUrl = $this->_haveNextPage($pageHtml,$this->url);
                if($nextUrl){
                    $this->_parsePage($nextUrl);
                }
            }
        }

        //需要禁止标签里的标签里的内容 或者 需要禁止内容中的class 或者是id
        $forbidElement = array_filter(explode(' ',$this->regular->detail_forbid_tag));
        $this->data['content'] = $this->forbidClassAndTag($forbidElement,$this->data['content']);

        //有些网站将src 属性设置成了data-original
        if($this->regular->really_pic_detail){
            $this->data['content'] = self::_parseReallyImg($this->regular->really_pic_detail,$this->data['content']);
        }
        $this->data['content'] = $this->htmlpurifier($this->data['content'],[],$forbidElement);//去除乱七八糟的标签 链接

        //替换内容中不需要的词语
        $this->data['content'] = $this->_replace($this->data['content']);

        $this->data['name'] = $this->_replace($arr['name']);
        $this->data['url'] = $arr['url'];
        $this->data['img'] = isset($arr['img']) ? $arr['img'] : '';
        $this->data['description'] = isset($this->data['description']) ? $this->data['description'] : mb_substr(strip_tags($this->data['content']),0,125);
        if($this->test){
            echo '<pre>';
            print_r($this->data);exit;
        } else {
            return $this->data;
        }
    }

    /**
     * 处理图片惰性加载或者真实路径不是src情况
     * @param $reallyPic 真实图片地址
     * @param $content 文章详情
     * @return mixed
     */
    public static function _parseReallyImg($reallyPic,$content){
        $content = preg_replace('/\ssrc=/',' data=',$content);//防止出现data-src情况造成图片无法采集
        $content = str_replace($reallyPic.'=','src=',$content);
        return $content;
    }

    /**
     * 处理多种可能的标示（有些网站可能的样式定位可能有很多种，比如说有些是div.content 而有些则是div.news 处理方式则是多种 div.content|div.news ...）
     * @dom obj simple_html_dom 加载的内容
     * @$element string|int 网站定位元素
     * @pos int 网站定位元素位置
     * @mess string 如果发生错误，写明错误的原因
     * @return bool
     */
    private function _parseMultMark($dom,$element,$pos,$mess){
        $mark = explode('|',$element);
        $obj = false;//返回的对象
        if($mark){
            foreach ($mark as $key=>$val){
                $obj = $dom->find($val,$pos);
                if($obj) break;//如果正确找到标示，则退出
            }
        }
        if(!$obj){
            return $this->_parseError($mess);
        }
        return $obj;
    }

    /**
     * 寻找第一页中需要的另外一下内容信息
     */
    private function _parseMultContent(){
        $mult_contents = json_decode($this->regular->mult_content,true);
        foreach($mult_contents as $key=>$val){
            $obj = $this->dom->find($val['name'],$val['pos']);
            if(!$obj) {
                $this->data['content'.$key] = '';
                continue;
            }
            $attr = $val['val'];
            $this->data['content'.$key] = $obj->$attr;
        }
    }

    /**
     * 循环抓取解析下一个分页的内容
     * @param $nextUrl 下一页的url
     * @return bool|string
     */
    private function _parsePage($nextUrl){
        $content = $this->getContent($nextUrl,$this->regular->encode);
        if(!$content){
            return '';
        }
        $dom = new simple_html_dom(str_replace('\\','',$content));

        $str = '内容详情标识错误 '.$this->regular->detail.'第'.$this->regular->detail_pos.'个位置不正确（第二页）';
        $detailObj = $this->_parseMultMark($dom,$this->regular->detail,$this->regular->detail_pos,$str);//处理内容详情文字
        if(!$detailObj) return false;//如果发生错误，则从页也开始往下一页不在采集

        //如果被采集站点含有分页，则加上分页标识，在后续程序中替换$$$$ 为<hr/>标签(此步防止采集网站中存在<hr/> 标签，扰乱本网站的分页情况)
        $this->data['content'] .= '$$$$'.$this->_cutEndStartPos($detailObj->innertext);
        $pages = $dom->find($this->regular->detail_page,$this->regular->detail_page_pos);
        if($pages){
            $pageHtml = $pages->innertext;
            $nextUrl = $this->_haveNextPage($pageHtml,$nextUrl);
            if($nextUrl){
                $this->_parsePage($nextUrl);
            }
        }
    }

    /**
     * 判断是否还有下一页 取出分页html 提取分页链接
     * @param $html 分页html
     * @param $currentPage 当前是第几页
     * @return bool|mixed|string
     */
    private function _haveNextPage($html,$currentPage){
        if(!$html) return false;
        $currentNum = self::_extractNum($currentPage);//当前的页码
        $dom = new simple_html_dom($html);
        $nextUrl = false;
        foreach($dom->find('a') as $val){
            //找不到连接则跳过
            $href = $val->href;
            if(!$href) {
                continue;
            }
            //如果链接中不是数字跳过
            $num = self::_extractNum($href);
            if(!$num){
                continue;
            }
            if($currentNum<$num){
                //如果存在禁止首个链接采集（第一页是原来的页面 类似于 http://www.yuexw.com/ent/42/1470490.htm 跳过）
                if(isset($this->regular->forbid_first_page) && $this->regular->forbid_first_page && !$this->forbidStop){
                    $this->forbidStop = true;
                    continue;
                }
                //判断此链接是否是这篇文章了
                if(isset($this->regular->is_self_news) && $this->regular->is_self_news){
                    $href = $this->isSelfNews($href);
                    //如果不是这篇文章，则跳过改链接采集
                    if(!$href){
                        continue;
                    }
                }
                $nextUrl = $href;//获取到下一页分页链接则停止循环搜索
                break;
            }
        }
        if($nextUrl){
            if(count(explode('/',$nextUrl)) == 1){ //针对此种url解析为正常路径 http://www.qulishi.com/news/201610/129119.html 以免其他网站使用的相对路径
                $arrs = array_filter(explode('/',$currentPage));
                array_pop($arrs);
                $baseUrl = implode('/',$arrs);

                $nextUrls = array_filter(explode('/',$nextUrl));
                $nextUrl = array_pop($nextUrls);
                $nextUrl = str_replace(':',':/',$baseUrl.'/'.$nextUrl);
            } else {
                if(strpos($nextUrl,'http') === false){
                    $nextUrl = $this->regular->url.$nextUrl;//针对没有http情况下的路径 例如 http://yule.youbian.com/news65166/
                }
            }
        }
        return $nextUrl;
    }

    /**
     * 验证当前链接是否属于本条新闻  防止分页中插入其他新闻链接而导致的死循环
     * @param $href 当前需要验证的链接
     * @return bool|正确的网址
     */
    private function isSelfNews($href){
        preg_match("/\d+/i",$href,$match);
        if(isset($match[0]) && $match[0] == $this->baseNum){
            return $href;
        }
        return false;
    }

    /**
     * 把字符串中的数字抽取出来
     * @param $string 要抽取的字符串
     * @return string
     */
    private static function _extractNum($string){
        $strings = explode('/',$string);//取这个链接最后部分用于比较大小，会避免一些错误
        $string = array_pop($strings);
        $num = 0;
        for($i=0;$i<strlen($string);$i++) {
            if (is_numeric($string[$i])) {
                $num .= $string[$i];
            }
        }
        return intval($num);
    }

    /**
     * 需要禁止内容中的class 或者是id 或者禁止的标签
     * @param array $forbids 要禁止的标签或者是 class
     * @param $content 内容
     * @return string
     */
    public function forbidClassAndTag($forbids = [],$content){
        $forbids = array_merge(['style','script'],$forbids);//默认去除style  和script 这两个标签
        $contentObj = new simple_html_dom($content);
        foreach ($forbids as $val){
            foreach($contentObj->find($val) as $v){
                $v->innertext = '';
            }
        }
        return $contentObj->innertext;
    }


    /**
     * 替换内容中不需要的词语
     * @param $content 要替换的内容
     * @return mixed
     */
    public function _replace($content){
        $content = str_replace('$$$$','<hr/>',$content);//将分页符替换成 本站需要的分页符
        if($this->regular->detail_replace){
            foreach($this->regular->detail_replace as $key=>$val){
                $replace = array_filter(explode(' ',$val));
                $replace[1] = isset($replace[1]) ? $replace[1] : '';
                //如果是正则替换
                if(strpos($replace[0],'.*?') !== false){
                    $content = preg_replace($replace[0],$replace[1],$content);
                } else {
                    $content = str_ireplace($replace[0],$replace[1],$content);
                }
            }
        }
        $content = str_replace('<p></p>','',$content);//取出空p标签对
        return $content;
    }

    /**
     * 截取内容前后位置
     * @param $content 内容
     * @return string
     */
    public function _cutEndStartPos($content){
        //最后的截取位置 舍去此位置后内容
        $endPos = explode('|',$this->regular->end_pos);
        if($endPos[0]){
            foreach ($endPos as $key=>$val){
                $pos = mb_strripos($content,$val);
                if($pos !== false){//如果循环中找到要分割的字符串，则截取并停止循环
                    $content = mb_substr($content,0,$pos);
                    break;
                }
            }
        }
        //开始的截取位置 舍去此位置前内容
        $startPos = explode('|',$this->regular->start_pos);
        if($startPos[0]){
            foreach ($startPos as $key=>$val){
                $pos = mb_stripos($content,$val);
                if($pos !== false){//如果循环中找到要分割的字符串，则截取并停止循环
                    $content = mb_substr($content,$pos);
                    break;
                }
            }
        }
        return $content;
    }

}