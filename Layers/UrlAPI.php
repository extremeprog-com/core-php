<?php

class UrlAPI {

    /**
     * @incomplete
     * @param $url
     * @param null $post_data
     */
    static function request($url, $post_data = null) {
        $ch = curl_init($url);
        if($post_data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        Log::info(
            [
                'message'   => "request $url",
                'url'       => $url,
                'result'    => $result,
            ]
            + (
                is_null($post_data)?
                []:
                (
                is_string($post_data) ?
                    [ 'post_string' => $post_data ]:
                    [ 'post_array'  => $post_data ]
                )
            ),
            __CLASS__
        );
        return $result;
    }

}