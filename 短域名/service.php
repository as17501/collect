<?php

class ShortUrlService
{
    public $redisClient;
    public $config;

    public function __construct(Redis $redis,ConfigInterface $config)
    {
        $this->redisClient = $redis;
        $this->config = $config;
    }

    /**
     * 生成短网址
     * @param array $params
     * @return string
     */
    public function set($params)
    {
        if (empty($params["url"])){
            throw new UnauthorizedHttpException("url不能为空",40004);
        }
        $preLong = $this->config->get("short_url_redis.pre_long");
        $preShort = $this->config->get("short_url_redis.pre_short");
        //判断url合法性
        if (!Checker::isUrl($params["url"])){
            throw new UnauthorizedHttpException("url不合法",40004);
        }
        //查redis
        $shortUrl = $this->redisClient->get($preLong. urlencode($params["url"]));
        if ($shortUrl){
            return $shortUrl;
        }
        //插入数据
        $dbKey = $this->insertUrl($params["url"]);
        if (empty($dbKey)){
            throw new ServerErrorHttpException("数据库插入失败",30002);
        }
        //根据返回id转短网址
        $shortKey = Convert::obscureTo($dbKey, "62");
        $shortUrl = $this->config->get("short_url_host"). $shortKey;
        //存入redis,缓存1小时
        $this->redisClient->set($preLong. urlencode($params["url"]), $shortUrl, ["EX" => 3600]);
        $this->redisClient->set($preShort. $dbKey, $params["url"], ["EX" => 3600]);
        //返回结果
        return $shortUrl;
    }
    /**
     * 解析短网址，并跳转
     * @param string $shortKey
     * @return string
     */
    public function explain($shortKey)
    {
        //验证短域名合法性
        if (!Checker::OnlyWord($shortKey)){
            return false;
        }
        //解析短域名
        $dbKey = Convert::obscureFrom($shortKey, "62");
        //验证并拆分dbKey
        $idInfo = $this->checkDbkey($dbKey);
        if (empty($idInfo)){
            return false;
        }

        //查询redis
        $preShort = $this->config->get("short_url_redis.pre_short");
        $shortUrl = $this->redisClient->get($preShort. $dbKey);
        if ($shortUrl){
            return $shortUrl;
        }
        //查询数据库
        $url = $this->getUrlById($idInfo["id"], $idInfo["suffix"]);
        if (empty($idInfo)){
            return false;
        }
        //存入redis,缓存1小时
        $this->redisClient->set($preShort. $dbKey, $url->url, ["EX" => 3600]);
        //返回长连接
        return $url->url;
    }

    /**
     * 插入url
     * @param $url
     * @return int
     */
    public function insertUrl($url){
        try {
            $suffix = hexdec(hash("adler32", $url))%8;
            $id = Db::connection('short_url')->table('short_url_'. $suffix)->insertGetId(
                ['url' => $url]
            );
            $dbKey = $this->getDbkey($id, $suffix);
        }catch (\Exception $e){
            $dbKey = 0;
        }
        return $dbKey;
    }

    /**
     * 根据id获取url
     * @param int $id
     * @param int $suffix
     * @return mixed
     */
    public function getUrlById($id, $suffix){
        return Db::connection('short_url')->table('short_url_'. $suffix)
            ->select('id', 'url')
            ->where('id', $id)
            ->first ();
    }
    /**
     * 根据id和后缀生成可验证dbkey
     * @param int $id
     * @param int $suffix
     * @return int
     */
    public function getDbkey($id, $suffix){
        return strval($suffix+1) . $id. $suffix;
    }

    /**
     * 根据dbkey验证并解析成id和后缀
     * @param int $dbKey
     * @return array
     */
    public function checkDbkey($dbKey){
        $suffix = substr($dbKey,-1);
        $id = substr($dbKey, 1, -1);
        $check = substr($dbKey, 0, 1);
        if ($suffix != $check - 1){
            return [];
        }else{
            return [
                "suffix" => $suffix,
                "id" => $id,
            ];
        }
    }
}
