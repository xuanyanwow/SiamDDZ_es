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
    /** @var int 加入房间 */
    const JOIN_ROOM = 10000;
    /** @var int 退出房间 */
    const QUIT_ROOM = 10004;
    /** @var int 叫地主 */
    const CALL_LANDLOAD = 10001;
    /** @var int 出牌 */
    const SEND_CARD = 10002;
    /** @var int 不出 */
    const PASS_CADR = 10003;
    /** @var int 超级加倍 */
    const DOUBLE_MULTIPLE = 10005;
    /** @var int 明牌 */
    const OPEN_CADR = 10006;

    private $fd;
    private $roomId;


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
            // self:: 部分的 都是ws发过来操作 自己转发给room的
            // RoomActor:: 部分的  都是Room处理完 要我们转发回前端的
            switch ($msg->getDo()) {
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

                case RoomActor::GAME_ADD_MULTIPLE:
                    $WsCommand = new WsCommand();
                    $WsCommand->setClass("game");
                    $WsCommand->setAction("add_multiple");
                    $WsCommand->setData([
                        'multiple' => $msg->getData()['multiple'],
                    ]);

                    $send[] = $WsCommand;
                    break;

                case RoomActor::GAME_WHO_TOBE_LANDLOAD:
                    $WsCommand = new WsCommand();
                    $WsCommand->setClass("game");
                    $WsCommand->setAction("who_tobe_landload");
                    $WsCommand->setData([
                        'player' => $msg->getData()['player'],
                    ]);

                    $send[] = $WsCommand;
                    break;

                case RoomActor::GAME_SHOW_LANDLOAD_CARD:
                    $WsCommand = new WsCommand();
                    $WsCommand->setClass("game");
                    $WsCommand->setAction("show_landload_card");
                    $WsCommand->setData([
                        'cards' => $msg->getData()['cards'],
                    ]);

                    $send[] = $WsCommand;
                    break;

                case self::JOIN_ROOM:
                    $this->roomId = $msg->getData()['roomId'];
                    $this->sendMyRoom($msg);
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