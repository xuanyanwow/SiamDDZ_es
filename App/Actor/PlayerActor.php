<?php
/**
 * 玩家actor
 * User: Siam
 * Date: 2020/5/18
 * Time: 9:58
 */

namespace App\Actor;


use App\HttpController\Base;
use App\HttpController\Room;
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
    const CALL_RICH = 10001;
    /** @var int 出牌 */
    const USE_CARD = 10002;
    /** @var int 不出 */
    const PASS_CADR = 10003;
    /** @var int 超级加倍 */
    const DOUBLE_MULTIPLE = 10005;
    /** @var int 明牌 */
    const OPEN_CADR = 10006;
    /** @var int 获取玩家状态 */
    const GET_INFO = 10007;

    private $fd;
    private $userId;
    private $roomId;
    /** @var int 是否准备开始 */
    private $isPrepare = 0;
    /** @var int 是否明牌 */
    private $isOpen = 0;
    /** @var int 是否加倍 */
    private $isDouble = 0;
    /** @var int 胜负的分数 */
    private $record = 0;
    /** @var array 手上的牌 */
    private $cards = [];

    public static function configure(ActorConfig $actorConfig)
    {
        $actorConfig->setActorName('PlayerActor');
        $actorConfig->setWorkerNum(3);
    }

    protected function onStart()
    {
        $this->fd = $this->getArg()['fd'];
        $this->userId = $this->getArg()['userId'];
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
                case RoomActor::Message :
                    $WsCommand = $msg->getData();

                    if ($WsCommand){
                        if (isset($send) && !empty($send)){
                            if (is_array($WsCommand)){
                                $send = array_merge($send, $WsCommand);
                            }else{
                                $send[] = $WsCommand;
                            }
                        }else{
                            $send = $WsCommand;
                        }
                    }
                    break;
                case RoomActor::GAME_SEND_CARD:
                    $this->cards = $msg->getData();
                    $ws = new WsCommand();
                    $ws->setClass("user");
                    $ws->setAction("send_card");
                    $ws->setData($this->cards);
                    $send[] = $ws;
                    break;

                case self::GET_INFO:
                    $replyData = [
                        "userId"    => $this->userId,
                        "isPrepare" => $this->isPrepare,
                        "isOpen"    => $this->isOpen,
                        "isDouble"  => $this->isDouble,
                        "record"    => $this->record,
                    ];
                    break;

                case self::JOIN_ROOM:
                    $this->roomId = $msg->getData()['roomId'];
                    $this->sendMyRoom($msg);
                    break;

                case self::CALL_RICH:
                    $command = new Command();
                    $command->setDo(self::CALL_RICH);
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
        if (isset($replyData)){
            return $replyData;
        }
        return true;
    }

    protected function onExit($arg)
    {
        // 通知RoomActor 我掉线了
        if ($this->roomId){
            $command = new Command();
            $command->setDo(self::QUIT_ROOM);
            $command->setData([
                'player' => $this->userId
            ]);
            $this->sendMyRoom($command);
        }
    }

    protected function onException(\Throwable $throwable)
    {
        $actorId = $this->actorId();
        echo "Player Actor {$actorId} onException\n";
        echo $throwable->getMessage()."\n";
    }

    private function sendMyRoom(Command $msg)
    {
        $roomActorId =  Cache::getInstance()->get("room_{$this->roomId}");
        RoomActor::client()->send($roomActorId, $msg);
    }
}