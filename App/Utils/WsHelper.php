<?php


namespace App\Utils;


use EasySwoole\Component\Context\ContextItemHandlerInterface;
use EasySwoole\Component\Context\ContextManager;
use EasySwoole\EasySwoole\ServerManager;
use Swoole\Coroutine;

class WsHelper
{
    const PUSH = "PUSH_";
    const NEED_PUSH_FD = "NEED_PUSH_FD";


    public static function register_defer()
    {
        Coroutine::defer([WsHelper::class, 'defer_push']);
    }

    public static function set_push_list($fd_list, $msg)
    {
        foreach ($fd_list as $fd){
            static::set_push($fd, $msg);
        }
    }

    public static function set_push($fd, $msg)
    {
        $temp = ContextManager::getInstance()->get(static::NEED_PUSH_FD) ?? [];
        $temp[$fd][] = $msg;
        ContextManager::getInstance()->set(static::NEED_PUSH_FD, $temp);
    }

    public static function defer_push()
    {
        $msg_list = ContextManager::getInstance()->get(static::NEED_PUSH_FD) ?? [];

        foreach ($msg_list as $fd => $msg_array){
            ServerManager::getInstance()->getSwooleServer()->push((int) $fd, json_encode($msg_array, 256));
        }
        // 清空消息，免得多次触发defer事件
        ContextManager::getInstance()->set(static::NEED_PUSH_FD, []);
    }

}