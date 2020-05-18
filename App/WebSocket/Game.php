<?php
/**
 * websocket游戏逻辑
 * User: Siam
 * Date: 2020/5/18
 * Time: 10:23
 */

namespace App\WebSocket;


use App\Actor\Command;
use App\Actor\PlayerActor;
use App\Actor\RoomActor;
use EasySwoole\FastCache\Cache;
use EasySwoole\Socket\AbstractInterface\Controller;


class Game extends Controller
{
    /**
     * 加入房间
     * @throws \EasySwoole\Actor\Exception\InvalidActor
     */
    public function joinRoom()
    {
        // 房间id
        $roomId      = $this->caller()->getArgs()['roomId'];
        $roomActorId = Cache::getInstance()->get("room_{$roomId}");
        if (!$roomActorId) {
            $this->response()->setMessage("房间不存在");
        }

        $fd      = $this->caller()->getClient()->getFd();
        $actorId = Cache::getInstance()->get("player_{$fd}");

        $command = new Command();
        $command->setDo(RoomActor::JOIN_ROOM);
        $command->setData([
            'player' => $actorId,
            'roomId' => $roomId,
        ]);
        PlayerActor::client()->send($actorId, [$command]);
    }

    /**
     * 退出房间
     */
    public function exitRoom()
    {

    }

    /**
     * 准备开始
     */
    public function preStart()
    {

    }

    /**
     * 取消准备开始
     */
    public function cancelPreStart()
    {

    }

    /**
     * 出牌
     */
    public function sendCards()
    {

    }

    /**
     * 是否叫地主
     */
    public function callLandLoad()
    {
        $result = $this->caller()->getArgs()['result'] ?? false;

        $fd      = $this->caller()->getClient()->getFd();
        $actorId = Cache::getInstance()->get("player_{$fd}");

        $command = new Command();
        $command->setDo(PlayerActor::CALL_LANDLOAD);
        $command->setData([
            'result' => $result
        ]);
        PlayerActor::client()->send($actorId, [$command]);
    }

    /**
     * 过牌
     */
    public function passCards()
    {

    }
}