<?php

namespace EasySwoole\EasySwoole;


use App\Actor\PlayerActor;
use App\Actor\RoomActor;
use App\Events\Events;
use App\WebSocket\WsParser;
use EasySwoole\Actor\Actor;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\FastCache\Cache;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Socket\Dispatcher;

class EasySwooleEvent implements Event
{

    public static function initialize()
    {
        // TODO: Implement initialize() method.
        date_default_timezone_set('Asia/Shanghai');
    }

    /**
     * @param EventRegister $register
     * @throws \EasySwoole\Actor\Exception\InvalidActor
     * @throws \EasySwoole\Component\Process\Exception
     * @throws \EasySwoole\FastCache\Exception\RuntimeError
     * @throws \EasySwoole\Socket\Exception\Exception
     * @throws \Exception
     */
    public static function mainServerCreate(EventRegister $register)
    {
        $conf = new \EasySwoole\Socket\Config();
        $conf->setType(\EasySwoole\Socket\Config::WEB_SOCKET);
        $conf->setParser(new WsParser());
        $dispatch = new Dispatcher($conf);
        $register->set(EventRegister::onMessage, function (\swoole_websocket_server $server, \swoole_websocket_frame $frame) use ($dispatch) {
            $dispatch->dispatch($server, $frame->data, $frame);
        });

        // 注册Actor管理器
        $server = ServerManager::getInstance()->getSwooleServer();
        Actor::getInstance()->register(PlayerActor::class);
        Actor::getInstance()->register(RoomActor::class);
        Actor::getInstance()->setTempDir(EASYSWOOLE_TEMP_DIR)
            ->setListenAddress('0.0.0.0')->setListenPort('9900')->attachServer($server);

        Cache::getInstance()->setTempDir(EASYSWOOLE_TEMP_DIR)->attachToServer($server);

        // 初始化100个房间
        $register->set(EventRegister::onWorkerStart, [Events::class, "onStart"]);
        // ws 连接初始化
        $register->set(EventRegister::onOpen, [Events::class, "onOpen"]);
        // ws 连接关闭时
        $register->set(EventRegister::onClose, [Events::class, "onClose"]);
    }

    public static function onRequest(Request $request, Response $response): bool
    {
        return TRUE;
    }

    public static function afterRequest(Request $request, Response $response): void
    {
    }
}