<?php
/**
 * Created by PhpStorm.
 * User: Siam
 * Date: 2020/5/18
 * Time: 13:41
 */

namespace App\Actor;


use EasySwoole\Spl\SplBean;

class Command extends SplBean
{
    protected $do;
    protected $data;

    public static function make($do, $data)
    {
        $return = new static;
        $return->setData($data);
        $return->setDo($do);
        return $return;
    }
    /**
     * @return mixed
     */
    public function getDo()
    {
        return $this->do;
    }

    /**
     * @param mixed $do
     */
    public function setDo($do): void
    {
        $this->do = $do;
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