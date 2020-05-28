<?php

namespace App\HttpController;


use EasySwoole\Http\AbstractInterface\Controller;

class Base extends Controller
{
    public function onException(\Throwable $throwable): void
    {
        parent::onException($throwable);
    }
}