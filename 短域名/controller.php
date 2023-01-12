class ShortUrlController extends AbstractController
{
    /**
     * 根据url生成短地址
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function set(ShortUrlService $shortUrlService)
    {
        $params = $this->request->all();
    
        $re = $shortUrlService->set($params);
        return $this->pubResp(["short_url" => $re]);
    }
    /**
     * 解析短地址之后跳转
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function explain(ShortUrlService $shortUrlService): Psr7ResponseInterface
    {
        $shortKey = $this->request->route('short_key');
        $re = $shortUrlService->explain($shortKey);
        //跳转，由于不做记录统计，直接301跳转
        if ($re){
            //todo 测试环境返回json
//            return $this->response->json(['redirect_url' => $re])->withStatus(200);
            return $this->response->redirect($re, 301);
        }else{
            //todo 测试环境，先不跳转，后续改成跳转到默认地址
//            return $this->response->json(['redirect_url' => "异常短域名，执行跳转默认地址"])->withStatus(200);
            return $this->response->redirect("https://baidu.com", 302);
        }
    }

}
