<?php

namespace Group\Common\Library;

class LoggerFileLib
{

    private $fd = null;

    private $errfd = null;

    public function __construct($config)
    {
        $this->fd = fopen($config->path, 'a+');
        $this->errfd = fopen($config->path . ".wf", 'a+');
    }

    public function write($content, $level)
    {
        if ($level === 'WARN' || $level === 'ERROR') {
            fwrite($this->errfd, date('[Y-m-d H:i:s]')." {$content}\n");
        } else {
            fwrite($this->fd, date('[Y-m-d H:i:s]')." {$content}\n");
        }
    }

    public function __destruct()
    {
        fclose($this->fd);
        fclose($this->errfd);
    }

}
