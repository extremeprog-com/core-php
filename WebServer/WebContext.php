<?php

class WebContext extends Context {

    /** @var  NginxFrontend_Request */
    public $Request;

    static function getRequestUrl() {
        return ($_SERVER['HTTPS']?"https://":"http://").$_SERVER['HTTP_HOST'].$_SERVER['DOCUMENT_URI'].($_SERVER['QUERY_STRING']?"?".$_SERVER['QUERY_STRING']:'');
    }

    static function getRequestMethod() {
        return $_SERVER['REQUEST_METHOD'];
    }

    static function getIP() {
        return $_SERVER['REMOTE_ADDR'];
    }

    static function getCookie($key) {
        return isset($_COOKIE[$key])?$_COOKIE[$key]:null;
    }

    static function addUrlToLog() {
        $Event = CatchEvent([ WebContext::class => Log_Message::class ]); /** @var Log_Message $Event */
        $WebContext = WebContext::getInstance();
        $Event->message['url']    = $WebContext->getRequestUrl();
        $Event->message['method'] = $WebContext->getRequestMethod();
        $Event->message['IP']     = $WebContext->getIP();
    }

    static function getRequest() {
        return WebContext::getInstance()->Request;
    }

}