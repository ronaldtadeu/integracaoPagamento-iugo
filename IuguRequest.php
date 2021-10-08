<?php 

abstract class IuguRequest {
    //Headers da requisição
    private $headers = [
        'Content-Type: application/json; charset=utf-8',
        'Accept: */*',
        'Accept-Language: pt-br;q=0.9,pt-BR',
        'Connection: keep-alive',
    ];
    //EndPoint
    private $url = 'https://api.iugu.com/v1/{req}';

    /**
     * Requisição CURL
     * @param $type string | Método GET ou POST
     * @param $req string | Completa o endpoint
     * @param $data array | Dados a serem passados
     * @param $header array | Método GET ou POST
     * @return object
     */
    public function request($type, $req, $data = array(), $header = array()) {
        $this->headers = array_merge($this->headers, $header);
        
        if(!$req)
            die("Falha");

        $url = str_replace("{req}", $req, $this->url);

        if($type == 'GET' && is_array($data) && count((array) $data) > 0)
            $url = $url . '?' . http_build_query($data);
        
        $data = json_encode($data);

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);

        if($type == 'POST') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
            @curl_setopt($curl, CURLOPT_SAFE_UPLOAD, false);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        
        $res['body'] = curl_exec($curl);
        $res['get_info'] = curl_getinfo($curl);
        
        curl_close($curl);

        return json_decode($res['body']);

    }

}

