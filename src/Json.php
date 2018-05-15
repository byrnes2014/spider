<?php
namespace Spider;

class Json extends Html  {
    public $dom = '';
    public $data = [];//采集的网页信息
    public $content = '';//url内容

    /**
     * @param array $param 将要测试的数据生成对象
     */
    public function __construct(array $param){
        parent::__construct($param);
    }

    public function getList(){
        $this->content = $this->getContent($this->url,$this->regular->encode);
        if(!$this->content){
            return $this->_parseError('can\'t connect this url');
        }
        //如果得到的不是一个json字符串，则后台要声明从何开始为json 从何结束为json
        if($this->regular->start){
            $startPos = mb_strpos($this->content,$this->regular->start);
            $this->content = mb_substr($this->content,$startPos);
        }
        if($this->regular->end){
            $endPos = mb_strrpos($this->content,$this->regular->end);
            $this->content = mb_substr($this->content,0,$endPos+1);
        }
        $this->content = self::prepareJSON($this->content);
        $data = json_decode($this->content,true);
        //测试模式打印json
        if($this->regular->test){
            echo '<pre>';
            print_r($data);exit;
        }

        $lists = array_filter(explode(' ',$this->regular->list));
        foreach($lists as $val){
            if(isset($data[$val])){
                $data = $data[$val];
            } else {
                return $this->_parseError('json循环标示错误 '.$this->regular->list);
            }
        }
        if(!is_array($data) || !$data){
            return $this->_parseError('没有搜索到相关内容');
        }
        $imgs = explode(' ',$this->regular->img);//封面规则
        $urls = explode(' ',$this->regular->url);//文章详情也采集规则
        $names = explode(' ',$this->regular->name);//文章详情也采集规则

        $return = [];
        foreach($data as $key=>$val){
            $arr = [];

            //要取出的详情地址
            $url = $val;
            foreach ($urls as $v){
                if(isset($url[$v])){
                    $url = $url[$v];
                } else {
                    if($key == 0) {
                        return $this->_parseError('json详情页链接标示错误 '.$this->regular->url);
                    } else {
                        $url = false;
                        break;
                    }
                }
            }
            // 如果没有找到url的情况，跳过这条循环
            if($url){
                $arr['url'] = $url;
                //判断链接是否是真实的链接，否则按照后台规则进行拼接
                if(strpos($arr['url'],'http') === false){
                    $arr['url'] = $this->regular->urlPath.$arr['url'].$this->regular->urlPathSuffix;
                }
            } else {
                continue;
            }

            //要取出的标题
            $arr['name'] = $val;
            foreach ($names as $v){
                if(isset($arr['name'][$v])){
                    $arr['name'] = $arr['name'][$v];
                } else {
                    if($key == 0) return $this->_parseError('json文章标题标示错误 '.$this->regular->name);
                }
            }

            //要取出的封面
            $img = $val;
            foreach ($imgs as $v){
                if(isset($img[$v])){
                    $img = $img[$v];
                } else {
                    if($key == 0 && $this->test){
                        return $this->_parseError('json封面标示错误 '.$this->regular->img);
                    } else {
                        $img = false;
                        break;
                    }
                }
            }
            if($img){  //如果列表中确实存在图片 则进行以下替换
                //判断图片是否真实图片地址 否则按照后台规则进行拼接
                $arr['img'] = strpos($img,'http') === false ? $this->regular->imgPath.$img : $img;
            }

            //创建采集详情任务
            if($this->test){
                $this->getDetail($arr);
            } else{
                $return[] = $arr;
            }
        }
        return $return;
    }

    /**
     * 如果网络请求json不是 json格式，则需要此进行解码
     * @param $input json 字符串
     * @return bool|string
     */
    public static function prepareJSON($input){
        if(substr($input,0,3) == pack("CCC",0xEF,0xBB,0xBF)) $input = substr($input,3);
        return $input;
    }
}