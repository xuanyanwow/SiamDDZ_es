<?php
/**
 * 玩家actor
 * User: Siam
 * Date: 2020/5/18
 * Time: 9:58
 */

namespace App\Actor;


use App\WebSocket\WsCommand;
use EasySwoole\Actor\AbstractActor;
use EasySwoole\Actor\ActorConfig;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\FastCache\Cache;

class PlayerActor extends AbstractActor
{

    private $fd;
    private $roomId;

    const CALL_LANDLOAD = 10001;

    public static function configure(ActorConfig $actorConfig)
    {
        $actorConfig->setActorName('PlayerActor');
        $actorConfig->setWorkerNum(3);
    }

    protected function onStart()
    {
        $this->fd = $this->getArg()['fd'];
    }

    protected function onMessage($msgs)
    {
        $send = [];
        foreach ($msgs as $msg){
            if (!($msg instanceof Command)){
                return false;
            }

            switch ($msg->getDo()) {
                case RoomActor::JOIN_ROOM:
                    $this->roomId = $msg->getData()['roomId'];
                    // 通知ROOM
                    $this->sendMyRoom($msg);
                    break;

                case RoomActor::GAME_START:
                    $WsCommand = new WsCommand();
                    $WsCommand->setClass("game");
                    $WsCommand->setAction("start");

                    $send[] = $WsCommand;
                    break;

                case RoomActor::GAME_SEND_CARD:
                    $card = $msg->getData();

                    $WsCommand = new WsCommand();
                    $WsCommand->setClass("game");
                    $WsCommand->setAction("send_card");
                    $WsCommand->setData($card);

                    $send[] = $WsCommand;
                    break;

                case RoomActor::GAME_ASK_CALL_LANDLOAD:
                    $WsCommand = new WsCommand();
                    $WsCommand->setClass("game");
                    $WsCommand->setAction("call_landload");
                    $WsCommand->setData([
                        'isMe' => $msg->getData() == $this->actorId() ? true : false,
                        // 这里可以加一个结束时间 配合后端定时器
                    ]);

                    $send[] = $WsCommand;
                    break;

                case self::CALL_LANDLOAD:
                    $command = new Command();
                    $command->setDo(self::CALL_LANDLOAD);
                    $command->setData([
                        'actorId' => $this->actorId(),
                        'result'  => $msg->getData()['result'],
                    ]);

                    $this->sendMyRoom($command);
                    break;
            }
        }
        if (!empty($send)){
            ServerManager::getInstance()->getSwooleServer()->push($this->fd, json_encode($send, 256));
        }
        return false;
    }

    protected function onExit($arg)
    {
        $actorId = $this->actorId();
        echo "Player Actor {$actorId} onExit\n";
    }

    protected function onException(\Throwable $throwable)
    {
        $actorId = $this->actorId();
        echo "Player Actor {$actorId} onException\n";
    }

    private function sendMyRoom(Command $msg)
    {
        $roomActorId =  Cache::getInstance()->get("room_{$this->roomId}");
        RoomActor::client()->send($roomActorId, $msg);
    }
}