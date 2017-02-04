<?php

/**
 * interface EtcdClient {
 *  public function set($key, $value, $ttl);
 *  public function get($key);
 *  public function keys($dir = '/');
 *  public function delete($key);
 * }
 *
 */
class EtcdClient {
    /** @property array $servers */
    protected $servers = [];

    /** @const REQUEST_CONNECT_TIMEOUT таймаут запроса по курлу */
    const REQUEST_CONNECT_TIMEOUT = 500;
    const REQUEST_OPERATION_TIMEOUT = 5000;

    /**
     * @param string $key
     * @param string $val
     * @param int $ttl
     * @return $this
     */
    public function set($key, $val, $ttl = null, $prevIndex = null) {
        $args['value'] = JSON::encode($val);
        if ($ttl) {
            $args['ttl'] = $ttl;
        }
        $get = [];
        if($prevIndex) {
            $get['prevIndex'] = $prevIndex;
        }

        return isset($this->doRequest('PUT', $key, $args, $get)['node-runner']);
    }
    
    /**
     * Check and set operation
     * @param	string  	$key
     * @param string $param
     * @param	mixed	$value
     */
    public function modify($key, $param, $value) {
        $index = null;
        // защищаемся от блокировок - используем cas и пробуем сохраниться максиум 10 раз
        for ($i = 0; $i < 10; $i++) {
            try {
                $val = $this->get($key, $index);
                if ($val === null) {
                    $val = [];
                }
            } catch (Exception $e) {
                Log::warn($e);
            }
            
            $val = [$param => $value] + $val;
            if ($this->set($key, $val, null, $index)) {
                return;
            }
        }
        throw new Exception('Cannot check and set value');
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key, &$modifiedIndex = null) {
        $response = $this->doRequest('', $key, [], ["consistent" => "true"]);
        if (isset($response['errorCode']) && $response['errorCode'] === 100) {
            return null;
        }
        if (!isset($response['node-runner'])) {
            throw new Exception('Get etcd key '.$key.' response was '.JSON::encode($response));
        }
        $response = $response['node-runner'];
        $modifiedIndex = $response['modifiedIndex'];
        return JSON::decode($response['value']);
    }

    /**
     * @param string $ns
     * @return array
     */
    public function keys($ns = '') {
        $response = $this->doRequest('', $ns);
        if(!isset($response['node-runner'])) {
            throw new Exception('Keys etcd ns '.$ns.' response was '.JSON::encode($response));
        }
        $keys = [];
        if (isset($response['node-runner']['nodes'])) {
            foreach ($response['node-runner']['nodes'] as $node) {
                $keys[] = $node['key'];
            }
        }
        return $keys;
    }

    /**
     * @param string $key
     * @return $this
     */
    public function delete($key) {
        $this->doRequest('DELETE', $key, [], ['recursive' => true]);
        return $this;
    }

    /**
     * @param string $key
     * @param callable $exec($old_value, $new_value)
     * @return $this
     */
    public function watch($key, &$wait_index) {
        a:
        $response = $this->doRequest('', $key, [], ['consistent' => "true", 'wait' => 'true', 'waitIndex' => $wait_index]);
        if(!isset($response['node-runner'])) {
            if(preg_match("/outdated|WAIT_TIMEOUT/", $response['message'])) {
                if (isset($response['index'])) {
                    $wait_index = $response['index'];
                }
                goto a;
            } else {
                throw new Exception('Get etcd key '.$key.' response was '.JSON::encode($response));
            }
        }
        $response = $response['node-runner'];

        $wait_index = $response['modifiedIndex'];
        return JSON::decode($response['value']);
    }

    /**
     * @param array $servers
     * @return $this
     */
    public function setServers(array $servers) {
        $this->servers = $servers;
        return $this;
    }

    /**
     * CURL HTTP requests to ETCD
     *
     * @param string $request
     * @param string $key
     * @param array $args
     * @param array $get
     * @return string
     * @throws Exception
     */
    public function doRequest($request = '', $key = '', array $args = [], array $get = []) {
        $response = false;
        $query = '/v2/keys/' . ltrim($key, '/') . ($get ? '?' . http_build_query($get) : '');
        foreach ($this->servers as $index => $server) {
            $ch = curl_init('http://' . $server . $query);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, self::REQUEST_CONNECT_TIMEOUT);
            curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
            curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 15);
            curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 5);
            
            if (!isset($get['wait'])) {
                curl_setopt($ch, CURLOPT_TIMEOUT_MS, self::REQUEST_OPERATION_TIMEOUT);
            } else {
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            }

            if ($request) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($request));
            }

            if ($args) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args));
            }

            $response = curl_exec($ch);
            
            if (isset($get['wait']) && curl_errno($ch) === CURLE_OPERATION_TIMEOUTED) {
                curl_close($ch);
                return ['message' => 'WAIT_TIMEOUT'];    
            }

            $curl_error = curl_error($ch);

            curl_close($ch);
            
            // Если получили ответ от первого сервера
            if ($response) {
                break;
            }
        }

        if ($response === false) {
            throw new Exception("Every Etcd server [" . implode(',', $this->servers) . "] fails " . (isset($curl_error)? 'with ' . $curl_error : ''). " on query " . ($request?$request." ":'') . $query . ($args?" ".JSON::encode($args):''));
        }
        $response = JSON::decode($response);

        /*  
        if (!$response) {
            Log::error([
                'message'  => $m = 'Error on http request to etcd servers ' . implode(',', $this->servers),
                'response' => $response,
            ], __CLASS__);

            throw new Exception($m);
        }
        */

        return $response;
    }
    
    /**
     * Return peer url of leader from current host
     * @return	object		      
     */
    public function getLeader() {
        $leader = null;
        $timeout = static::REQUEST_CONNECT_TIMEOUT;
        foreach ($this->servers as $server) {
            list($host, $port) = explode(':', $server);
            $leader = `curl -L --connect-timeout $timeout http://$host:$port/v2/leader 2>/dev/null`;
        }
        
        return $leader;
    }
}
