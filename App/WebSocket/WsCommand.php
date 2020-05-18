<?php
/**
 * Created by PhpStorm.
 * User: Siam
 * Date: 2020/5/18
 * Time: 14:06
 */

namespace App\WebSocket;


use EasySwoole\Spl\SplBean;

class WsCommand extends SplBean
{
    protected $class;
    protected $action;
    protected $data;


    /**
     * @return mixed
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param mixed $class
     */
    public function setClass($class): void
    {
        $this->class = $class;
    }

    /**
     * @return mixed
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param mixed $action
     */
    public function setAction($action): void
    {
        $this->action = $action;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData($data): void
    {
        $this->data = $data;
    }

}