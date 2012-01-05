<?php

class Template
{
    public static $template_dir = '.';

    public $content = array();
    
    private $template_filename = false;
    private $dom;
    
    public function __construct($template_name)
    {
        $this->template_filename = self::$template_dir . '/' . $template_name;
    }
    
    public function outputHTML()
    {
        ob_start();
        $content = $this->content;
        include($this->template_filename);
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
}
