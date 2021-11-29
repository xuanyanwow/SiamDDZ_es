<?php
/**
 * websocket游戏逻辑
 *
 * 在这里不应该出现操作RoomActor的情况，不然有权限风险
 *
 * User: Siam
 * Date: 2020/5/18
 * Time: 10:23
 */

namespace App\WebSocket;


use App\Actor\Command;
use App\Actor\PlayerActor;
use App\Actor\RoomActor;
use App\Repository\UserConnectInfoMap;
use EasySwoole\FastCache\Cache;
use EasySwoole\Socket\AbstractInterface\Controller;
use EasySwoole\Spl\SplBean;


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
        $actorId = UserConnectInfoMap::fd_get_actor($fd);
        $userId  = UserConnectInfoMap::fd_get_user($fd);
;
        PlayerActor::client()->send($actorId, Command::make(PlayerActor::JOIN_ROOM, [
            'roomId' => $roomId,
        ]));
        RoomActor::client()->send($roomActorId, Command::make(RoomActor::GAME_JOIN_PLAYER, [
            'userId' => $userId
        ]));
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
     * 准备
     */
    public function do_prepare()
    {
        $result = $this->caller()->getArgs()['result'] ?? false;

        $fd      = $this->caller()->getClient()->getFd();

        $roomId      = $this->caller()->getArgs()['roomId'];
        $roomActorId = Cache::getInstance()->get("room_{$roomId}");
        if (!$roomActorId) {
            $this->response()->setMessage("房间不存在");
        }

        $userId  = UserConnectInfoMap::fd_get_user($fd);
        RoomActor::client()->send($roomActorId, Command::make(RoomActor::GAME_PRE_START, [
            'user_id' => $userId,
            'result'  => $result,
        ]));
    }
    /**
     * 是否叫地主
     */
    public function call_rich()
    {
        $result = $this->caller()->getArgs()['result'] ?? false;

        $fd      = $this->caller()->getClient()->getFd();
        // 通知房间叫地主的是谁
        $roomId      = $this->caller()->getArgs()['roomId'];
        $roomActorId = Cache::getInstance()->get("room_{$roomId}");
        if (!$roomActorId) {
            $this->response()->setMessage("房间不存在");
        }

        $userId  = UserConnectInfoMap::fd_get_user($fd);
        RoomActor::client()->send($roomActorId, Command::make(RoomActor::GAME_CALL_RICH, [
            'userId' => $userId,
            'result' => $result,
        ]));
    }

    /**
     * 过牌
     */
    public function pass_card()
    {
        $fd      = $this->caller()->getClient()->getFd();
        $roomId      = $this->caller()->getArgs()['roomId'];
        $roomActorId = Cache::getInstance()->get("room_{$roomId}");
        if (!$roomActorId) {
            $this->response()->setMessage("房间不存在");
        }

        $userId  = UserConnectInfoMap::fd_get_user($fd);
        RoomActor::client()->send($roomActorId, Command::make(RoomActor::GAME_PLAYER_PASS_CARD, [
            'userId' => $userId,
        ]));
    }

    public function use_card()
    {
        $card_list = $this->caller()->getArgs()['card_list'] ?? [];
        $card_list = json_decode($card_list, true);

        $fd      = $this->caller()->getClient()->getFd();
        $roomId      = $this->caller()->getArgs()['roomId'];
        $roomActorId = Cache::getInstance()->get("room_{$roomId}");
        if (!$roomActorId) {
            $this->response()->setMessage("房间不存在");
        }

        $userId  = UserConnectInfoMap::fd_get_user($fd);
        RoomActor::client()->send($roomActorId, Command::make(RoomActor::GAME_PLAYER_USE_CARD, [
            'userId'    => $userId,
            'card_list' => $card_list,
        ]));
    }
}