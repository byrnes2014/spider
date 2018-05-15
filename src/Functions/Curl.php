<?php
namespace Spider\Functions;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Log;
/**
 * CURL
 * 封装php curl 方法,用起来会方便些许
 */
class Curl
{
    private $_request = array();
    private $_response = array();
    private $_method;
    private $_allow_redirect = 1;
    private $_login = 0;

    private $_execInfo;
    private $_curl;

    function __construct()
    {
        $this->clean();
    }

    /**
     * 清理上一次请求的参数
     *
     * @return CURL
     */
    function clean()
    {

        $this->_request = array();
        $this->_request['headers'] = array();
        $this->_request['cookies'] = array();

        $this->_execInfo = array();
        return $this;
    }

    /**
     * HTTP 登陆
     *
     * @param string $username
     * @param string $password
     * @return CURL
     */
    function login($username, $password)
    {
        $this->_request['user'] = array('username' => $username, 'password' => $password);
        return $this;
    }

    /**
     * get 请求
     *
     * @param string $url
     * @param array $params
     * @return string
     */
    function get($url, $params = array())
    {
        $this->_request['params'] = $params;
        if($params)
        {
            $params = http_build_query($params);
            $url .= strpos('?', $url) ? "&{$params}" : "?{$params}";
        }
        $this->_request['url'] = $url;
        $this->_method = false;

        return $this->execute();
    }

    /**
     * 如果你需要上传一个文件，只需要把文件路径像一个post变量一样传过去，不过记得在前面加上@符号。
     *
     * @param string $url
     * @param mixed $params
     * @return CURL
     */
    function post($url, $params = array())
    {
        $this->_request['url'] = $url;
        $this->_request['params'] = $params;
        $this->_method = CURLOPT_POST;

        return $this->execute();
    }

    /**
     * @param string URL
     * @param array PUT 参数
     * @return CURL
     */
    function put($url, $params = array())
    {
        $this->_request['url'] = $url;
        $this->_request['params'] = $params;

        $this->_method = CURLOPT_PUT;
        return $this;
    }

    /**
     *
     * @param int 超时 second 默认 10秒
     * @return CURL
     */
    function timeout($sec = 10)
    {
        $this->_request['timeout'] = $sec;
        return $this;
    }

    /**
     * 设置浏览器代理标识
     *
     * @param string agent
     * @RETURN CURL
     */
    function agent($agent)
    {
        $this->_request['agent'] = $agent;
        return $this;
    }

    /**
     * 设置请求cookies
     * @param array $cookies
     * @return CURL
     */
    function cookies($cookies)
    {
        $this->_request['cookies'] = $cookies;
        return $this;
    }

    /**
     * 设置单个cookie
     *
     * @param string $name
     * @param string $value
     * @return CURL
     */
    function setCookie($name, $value)
    {
        $this->_request['cookies'][$name] = $value;
        return $this;
    }

    /**
     * 设置请求 Header
     * @param array @headers
     *
     * @return CURL
     */
    function headers($headers)
    {
        $this->_request['headers'] = $headers;
        return $this;
    }

    /**
     * 设置单个Header
     *
     * @param string $name
     * @param string $value
     * @return CURL
     */
    function setHeader($name, $value)
    {
        $this->_request['headers'][$name] = $value;
        return $this;
    }



    /**
     * 设置代理服务器
     *
     * @param $host
     * @param $port
     * @param $type
     * @param $username
     * @param $password
     * @return CURL
     */
    function proxy($host, $port, $type = CURLPROXY_SOCKS5, $username = '', $password = '')
    {
        $this->_request['proxy'] = array(
            CURLOPT_HTTPPROXYTUNNEL => true,
            CURLOPT_PROXYAUTH => CURLAUTH_BASIC,
            CURLOPT_PROXY => $host,
            CURLOPT_PROXYPORT => $port,
            CURLOPT_PROXYTYPE => $type,
        );

        if($username || $password)
        {
            $user = "{$username}:{$password}";
            $this->_request['proxy'][CURLOPT_PROXYUSERPWD] = $user;
        }

        return $this;
    }

    function noBody($is = true)
    {
        // 页面内容我们并不需要
        $this->_request['nobody'] = $is;
    }


    function execute()
    {

        $this->_curl = curl_init();
        if($this->_method)
        {
            curl_setopt($this->_curl, $this->_method, true);
        }

        curl_setopt($this->_curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->_curl, CURLOPT_URL, $this->_request['url']);
        //是否显示头部信息
        curl_setopt($this->_curl, CURLOPT_HEADER, true);
        // 不想输出返回的内容
        curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, true);

        if( $this->_request['headers'] )
        {
            $headers = array();
            foreach( $this->_request['headers'] as $header_name => $header_value )
            {
                $headers[] = "{$header_name}: {$header_value}";
            }
            curl_setopt($this->_curl, CURLOPT_HTTPHEADER, $headers );
        }

        if(isset($this->_request['params']) && $this->_request['params'] && $this->_method)
        {
            curl_setopt($this->_curl, CURLOPT_POSTFIELDS, http_build_query($this->_request['params']));
        }
        if( $this->_allow_redirect ) {
            curl_setopt($this->_curl, CURLOPT_FOLLOWLOCATION,true);
        }

        /**
         * AUTH
         */
        if(isset($this->_request['user'] ))
        {
            curl_setopt($this->_curl, CURLOPT_USERPWD, $this->_request['user']['username'] . ':' . $this->_request['user']['password']);
        }

        //Timeout
        if(isset($this->_request['timeout']))
        {
            curl_setopt($this->_curl, CURLOPT_TIMEOUT, intval($this->_request['timeout']));
        }
        //agent
        if(isset($this->_request['agent']))
        {
            curl_setopt($this->_curl, CURLOPT_USERAGENT, $this->_request['agent']);
        }
        //
        if(isset($this->_request['nobody']))
        {
            curl_setopt($this->_curl, CURLOPT_NOBODY, $this->_request['nobody']);
        }
        if(isset($this->_request['proxy']))
        {
            foreach($this->_request['proxy'] as $key => $value)
            {
                curl_setopt($this->_curl, $key, $value);
            }
        }


        $cookies = array();
        foreach($this->_request['cookies'] as $key => $val)
        {
            $cookies[] = "{$key}={$val}";
        }
        $cookies = implode(';', $cookies);

        curl_setopt($this->_curl, CURLOPT_COOKIE, $cookies);         //发送的cookie

        if( function_exists('curlLog') ) {
            curlLog( $this->_request + array(
                    'method' => $this->_method
                ) );
        }
        $result = curl_exec($this->_curl);

        if (curl_errno($this->_curl))
        {
            $error = curl_error($this->_curl);
            $errcode = curl_errno($this->_curl);
            $info = curl_getinfo($this->_curl);
            curl_close($this->_curl);


            $exception_name = 'CURL_Exception';
            if($errcode == 56)
            {
                $exception_name = 'CURL_Conn_Reset_Exception';
            }
            $str = "CURL ERROR, URL: {$this->_request['url']}; ERROR CODE {$errcode} ;ERROR: {$error};";
            if(class_exists('Log') && function_exists('storage_path')) {
                $path = storage_path().'/logs/Spider/SpiderError.log';
                Log::useDailyFiles($path);
                Log::info($str);
            } else {
                $log = new logger('ERROR');
                $saveTo = __DIR__.'/../logs/'.'SpiderError-'.date('Y-m-d',time()).'.log';
                $log->pushHandler(new StreamHandler($saveTo, Logger::WARNING));
                $log->warning($str);
            }
            return false;
        }
        else
        {
            $this->_execInfo = curl_getinfo($this->_curl);
            $this->_response['status'] = curl_getinfo( $this->_curl ,CURLINFO_HTTP_CODE );

            curl_close($this->_curl);
        }

        $this->_response['body'] = substr($result, $this->getHeaderSize());

        $redirect_result = $this->handleResponseHeader(substr($result, 0, $this->getHeaderSize()));

        if($redirect_result)
        {
            return $redirect_result;
        }


        return $this->_response['body'];
    }


    function getHeaderSize()
    {
        return isset($this->_execInfo['header_size']) ? $this->_execInfo['header_size'] : false;
    }

    /**
     * 分析处理头信息
     *
     */
    private function handleResponseHeader($header_str)
    {
        $header = array();

        $header_str = explode("\n", trim($header_str));

        foreach($header_str as $line)
        {
            $colon = strpos($line, ':');
            if( $colon )
            {
                $key = strtolower(trim(substr($line, 0, $colon)));
                $value = trim(substr($line, $colon+1));

                $header[$key] = $value;
            }
            else
            {
                $header[] = $line;
            }
        }

        if(isset($header['content-encoding']) && strtolower($header['content-encoding']) == 'gzip')
        {
            $this->_response['body'] = $this->gzdecode($this->_response['body']);
        }

        if(isset($header['location']))
        {
            //处理跳转,可能不需要将原本求情的数据传递过期.
            if($this->_allow_redirect > 0)
            {
                --$this->_allow_redirect;
                // $this->_request['url'] = $header['location'];
                // return $this->execute();
            }
        }
        $this->_response['header'] = $header;

    }

    function getStatus()
    {
        if(isset($this->_response['status'])){
            return $this->_response['status'];
        }
        return 500;
    }

    function getHeader()
    {
        return $this->_response['header'];
    }

    function allowRedirect($times = 3)
    {
        $this->_allow_redirect = $times;
        return $this;
    }

    function gzdecode ($data) {
        $flags = ord(substr($data, 3, 1));
        $headerlen = 10;
        $extralen = 0;
        $filenamelen = 0;
        if ($flags & 4) {
            $extralen = unpack('v' ,substr($data, 10, 2));
            $extralen = $extralen[1];
            $headerlen += 2 + $extralen;
        }
        if ($flags & 8) // Filename
            $headerlen = strpos($data, chr(0), $headerlen) + 1;
        if ($flags & 16) // Comment
            $headerlen = strpos($data, chr(0), $headerlen) + 1;
        if ($flags & 2) // CRC at end of file
            $headerlen += 2;
        $unpacked = @gzinflate(substr($data, $headerlen));
        if ($unpacked === FALSE)
            $unpacked = $data;
        return $unpacked;
    }

}
