<?php

class SysConfigPage {

    const URL = '/vkadmin/config';

    protected $authenticationRequired = false;
    protected $layout_template_file_name = 'layouts/systemLayout';


    public function run() {

        return true;
    }


}