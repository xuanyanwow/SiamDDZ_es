<?php

namespace App\Events;


use App\Actor\PlayerActor;
use App\Actor\RoomActor;
use App\WebSocket\WsCommand;
use EasySwoole\FastCache\Cache;
use Swoole\Http\Request;
use Swoole\WebSocket\Server;

class Events
{


    public static function onStart($server, $workerId)
    {
        if ($workerId == 1) {
            go(function () {
                \co::sleep(1);
                for ($i = 1; $i <= 2; $i++) {
                    $roomActorId = RoomActor::client()->create();
                    Cache::getInstance()->set("room_{$i}", $roomActorId);
                }
            });
        }
    }

    public static function onConnect(Server $server, $fd, $reactorId)
    {

    }

    public static function onOpen(Server $server, Request $request)
    {
        $actorId = PlayerActor::client()->create([
            "fd" => $request->fd,
        ]);
        Cache::getInstance()->set("player_{$request->fd}", $actorId);
        // 签发一个随机token给前端
        $wsCommand = new WsCommand();
        $wsCommand->setClass("user");
        $wsCommand->setAction("auth");
        $wsCommand->setData([
            'token' => base64_encode($request->fd)
        ]);
        $server->push($request->fd, json_encode($wsCommand, 256));
    }

    public static function onClose(Server $server, int $fd, int $reactorId)
    {
        $info = $server->getClientInfo($fd);
        if ($info && $info['websocket_status'] === WEBSOCKET_STATUS_FRAME) {
            Cache::getInstance()->unset("player_{$fd}");
        }
    }
}