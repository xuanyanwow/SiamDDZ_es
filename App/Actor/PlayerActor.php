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
    const JOIN_ROOM = "join_room";
    /** @var int 退出房间 */
    const QUIT_ROOM = "quit_room";
    /** @var int 叫地主 */
    const CALL_RICH = "call_rich";
    /** @var int 出牌 */
    const USE_CARD = "use_card";
    /** @var int 不出 */
    const PASS_CADR = "pass_card";
    /** @var int 超级加倍 */
    const DOUBLE_MULTIPLE = "double_multiple";
    /** @var int 明牌 */
    const OPEN_CADR = "open_card";
    /** @var int 获取玩家状态 */
    const GET_INFO = "get_info";
    /** @var int 得到牌 */
    const GET_CARD = "get_card";

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
        $this->userId = $this->getArg()['userId'];
    }

    protected function onMessage($msg)
    {
        $send = [];
        if (!($msg instanceof Command)){
            return $send;
        }
        $action = $msg->getDo();
        $send[] = $this->$action($msg->getData());

        return $send;
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


    public function join_room($data)
    {
    }

    public function get_card($data)
    {
        echo $this->userId."获得牌".var_export($data,true) ."\n";
    }
}