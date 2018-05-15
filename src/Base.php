<?php
namespace Spider;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Spider\Functions\Curl;
use HTMLPurifier_Config,HTMLPurifier;
use Log;

class Base{

    public $request_retry_times = 2;//网络错误重连次数
    public $test = false;//是否测试模式
    public $regular = '';//提取网页的规则
    public $url = '';//要链接的url

    public function __construct(array $param) {
        $this->test = isset($param['test']) ? $param['test'] : false;
        $this->url = $param['url'];
        $this->regular = (object)$param['regular'];
    }

    public function _parseError($msg){
        $str = 'SPIDER_ERROR --- ID：'.$this->regular->id.' NAME：'.$this->regular->name.' MES :'.$msg.' URL:'.$this->url;
        if($this->test){
            die($str);
        }
        //如果是laravel框架引用，则调用laravel默认日志记录
        if(class_exists('Log') && function_exists('storage_path')){
            $path = storage_path().'/logs/Spider/SpiderError.log';
            Log::useDailyFiles($path);
            Log::info($str);
        } else{
            $log = new logger('ERROR');
            $path = __DIR__.'/../logs/';
            if(!is_dir($path)){
                mkdir($path,0777,true);
            }
            $saveTo = $path.'SpiderError-'.date('Y-m-d',time()).'.log';
            $log->pushHandler(new StreamHandler($saveTo, Logger::WARNING));
            $log->warning($str);
        }
        return false;
    }

    /**
     * @param $dirtyHtml html代码
     * @param array $dom 要去除的标签
     * @param array $forbidElement 暂时无用
     * @return mixed
     */
    public function htmlpurifier($dirtyHtml,$dom = [],$forbidElement = []){
        $defaultDom = ['center', 'a', 'font', 'span', 'div','strong'];
        $doms = array_merge($defaultDom,$dom);

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.HiddenElements',$forbidElement);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true);
        $config->set('HTML.ForbiddenElements', $doms);
        $config->set('HTML.SafeObject',true);
        $config->set('HTML.SafeEmbed',true);
        $config->set('HTML.ForbiddenAttributes', ['id', 'width','alt', 'height','style', 'on*','class']);
        $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true);
        $purifier = new HTMLPurifier($config);
        return $purifier->purify($dirtyHtml);
    }

    /**
     * 获取网页内容
     * @param $url
     * @param string $encoding
     * @return string
     */
    public function getContent($url, $encoding = 'utf-8') {
        $url = html_entity_decode($url);
        if(strpos($url,'?') !== false){
            $url .= '&cdndone='.mt_rand(0,100000);
        } else {
            $url .= '?cdndone='.mt_rand(0,100000);
        }
        $curl = new Curl();
        $try_times = 0;
        $cip = '106.14.31.'.mt_rand(0,254);
        $xip = '106.24.31.'.mt_rand(0,254);
        do {
            try {
                $response = $curl
                    ->cookies(array('JSESSIONID' => 'constant-session-1'))
                    ->setHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_2) AppleWebKit/600.4.10 (KHTML, like Gecko) Version/8.0.4 Safari/600.4.10')
                    ->setHeader('CLIENT-IP',$cip)//模拟请求ip
                    ->setHeader('X-FORWARDED-FOR',$xip)//模拟请求ip
                    ->get($url);
            } catch (Exception $e) {
                sleep(1);
            }
        } while ((!isset($response) || !$response) && ++$try_times < $this->request_retry_times);

        if ($curl->getStatus() >= 300 || $curl->getStatus() < 200) {
            return false;
        }

        if (!isset($response) || !$response) {
            if (isset($e) && $e) {
                $str = '未知错误 URL：'.$url.' 返回值：'.$e->getMessage();
                $this->_parseError($str);
                return false;
            } else {
                $str = '未知错误 URL：'.$url.' 无返回值';
                $this->_parseError($str);
                return false;
            }
        }
        if ($encoding == false) {
            $headers = $curl->getHeader();

            if (isset($headers['content-type'])) {
                if (preg_match('~charset=([^"]+)~', $headers['content-type'], $encoding)) {
                    $encoding = $encoding[1];
                } else {
                    $encoding = 'utf-8';
                }
            } else {
                if (preg_match('~charset=([^"]+)"~', $response, $encoding)) {
                    $encoding = $encoding[1];
                } else {
                    $encoding = 'utf-8';
                }
            }

        }
        if ($encoding != 'utf-8') {
            $response = mb_convert_encoding($response, 'utf-8', $encoding);
        }
        return $response;
    }
}