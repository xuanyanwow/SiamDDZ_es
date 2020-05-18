<?php
/**
 * 房间actor
 * User: Siam
 * Date: 2020/5/18
 * Time: 9:58
 */

namespace App\Actor;


use EasySwoole\Actor\AbstractActor;
use EasySwoole\Actor\ActorConfig;
use EasySwoole\Actor\Exception\InvalidActor;
use EasySwoole\Component\Timer;

class RoomActor extends AbstractActor
{

    /** @var int 游戏开始 */
    const GAME_START = 2;
    /** @var int 发牌 */
    const GAME_SEND_CARD = 3;
    /** @var int 询问是否叫地主 */
    const GAME_ASK_CALL_LANDLOAD = 4;
    /** @var int 增加倍数 */
    const GAME_ADD_MULTIPLE = 5;

    /** @var array 洗牌后储存的 */
    private $pokerCard = [];
    /** @var array 玩家列表 */
    private $playerList = [];
    /** @var mixed 定时器id  扫描开始定时器、出牌定时器 */
    private $timerId;
    /** @var array 游戏开始后 每个玩家的牌 key为playerActorId */
    private $playerCard = [];
    /** @var array 三张地主牌 */
    private $landloadCard = [];
    /** @var int 房间倍数 默认1倍 */
    private $multiple = 1;
    /** @var mixed 当前操作用户 */
    private $nowPlayer;

    public static function configure(ActorConfig $actorConfig)
    {
        $actorConfig->setActorName('RoomActor');
        $actorConfig->setWorkerNum(3);
    }

    protected function onStart()
    {
        // echo "房间初始化\n";
        // 房间创建后 每1秒检测一次是否可以开始
        $this->timerId = Timer::getInstance()->loop(1, function () {
            if (count($this->playerList) < 3) {
                return FALSE;
            }
            Timer::getInstance()->clear($this->timerId);
            $this->shufflePoker();
            $this->start();
            return TRUE;
        });
    }

    /**
     * @param Command $msg
     * @return bool
     */
    protected function onMessage($msg)
    {
        if (!($msg instanceof Command)){
            return false;
        }

        switch ($msg->getDo()) {
            case PlayerActor::JOIN_ROOM:
                $this->playerList[] = $msg->getData()['player'];
                break;

            case PlayerActor::CALL_LANDLOAD:
                if(!$this->canDo($msg->getData()['actorId'])) return false;
                if ($msg->getData()['result'] === true){
                    $this->multiple = $this->multiple * 2;
                    // todo 叫地主成功 倍数*2
                    // RoomActor::GAME_ADD_MULTIPLE

                    // todo 地主牌展示，发给地主
                }else{
                    // todo 需要记录重开次数、已经操作的人；如果全部不叫则重开，重开3次则最后一个玩家强制当地主
                }

                break;

            case PlayerActor::SEND_CARD:
                // todo 判断出牌是否符合逻辑、是否玩家拥有所有牌；
                // 是则删除剩余牌
                // 是否增加倍数
                // 判断是否胜利
                // 胜利则结算
                break;

            case PlayerActor::PASS_CADR:
                break;
        }

        return false;
    }

    protected function onExit($arg)
    {

    }

    protected function onException(\Throwable $throwable)
    {
        // TODO: Implement onException() method.
    }

    /**
     * 洗牌
     */
    private function shufflePoker()
    {
        // 先组合整副牌
        // h -> 黑桃
        // c -> 红桃
        // d -> 方片
        // s -> 梅花
        // $all = [
        //     'HA', 'H2', 'H3', 'H4', 'H5', 'H6', 'H7', 'H8', 'H9', 'H10', 'HJ', 'HQ', 'HK',
        //     'SA', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8', 'S9', 'S10', 'SJ', 'SQ', 'SK',
        //     'DA', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7', 'D8', 'D9', 'D10', 'DJ', 'DQ', 'DK',
        //     'CA', 'C2', 'C3', 'C4', 'C5', 'C6', 'C7', 'C8', 'C9', 'C10', 'CJ', 'CQ', 'CK',
        //     'W1', 'W2'// 1小王 2大王
        // ];
        $all = [
            'H3', 'H4', 'H5', 'H6', 'H7', 'H8', 'H9', 'H10', 'H11', 'H12', 'H13', 'H14', 'H15',
            'S3', 'S4', 'S5', 'S6', 'S7', 'S8', 'S9', 'S10', 'S11', 'S12', 'S13', 'S14', 'S15',
            'D3', 'D4', 'D5', 'D6', 'D7', 'D8', 'D9', 'D10', 'D11', 'D12', 'D13', 'D14', 'D15',
            'C3', 'C4', 'C5', 'C6', 'C7', 'C8', 'C9', 'C10', 'C11', 'C12', 'C13', 'C14', 'C15',
            'W16', 'W17'// 1小王 2大王
        ];
        shuffle($all);
        shuffle($all);
        $this->pokerCard = $all;
        // 分割成三份基础牌
        $tem = array_chunk($all, 17);

        foreach ($this->playerList as $key => $player){
            // 排序
            $final = $this->sortPorker($tem[$key]);
            $this->playerCard[$player] = $final;
        }
        $this->landloadCard = $this->sortPorker($tem[3]);

        // $c = ['H','S','D','C'];
        // $n = [1,2,3,4,5,6,7,8,9,10,11,12,13];
        // for ($i=0; $i<4 ; $i++){
        //     for ($j = 0; $j < 13; $j++){
        //            if ($n[$j] === 1){
        //                $all[] = "{$c[$i]}A";
        //            }else if ($n[$j] === 11){
        //                $all[] = "{$c[$i]}J";
        //            }else if ($n[$j] === 12){
        //                $all[] = "{$c[$i]}Q";
        //            }else if ($n[$j] === 13){
        //                $all[] = "{$c[$i]}K";
        //            }else{
        //                $all[] = "{$c[$i]}{$n[$j]}";
        //            }
        //     }
        // }
    }

    /**
     * 通知开始，计算第一名玩家，通知要不要叫叫地主
     */
    private function start()
    {
        // 通知游戏开始
        $command = new Command();
        $command->setDo(self::GAME_START);

        foreach ($this->playerList as $player){
            try {
                PlayerActor::client()->send($player, [$command]);
            } catch (InvalidActor $e) {
                // 通知失败 游戏结束
            }
        }

        // 叫地主的人 第一次就第一个，后面的就谁赢了谁先叫
        $firstPlayerId = $this->playerList[0];

        foreach ($this->playerList as $player){
            // 发基础牌
            $command = new Command();
            $command->setDo(self::GAME_SEND_CARD);
            $command->setData($this->playerCard[$player]);
            $send = [];
            $send[] = $command;
            // 通知哪个玩家叫地主
            $callLandLoad = new Command();
            $callLandLoad->setDo(self::GAME_ASK_CALL_LANDLOAD);
            $callLandLoad->setData($firstPlayerId);

            $send[] = $callLandLoad;
            try {
                PlayerActor::client()->send($player, $send);
            } catch (InvalidActor $e) {
                // 通知失败 游戏结束
            }
        }
        // 叫地主定时器
        $this->nowPlayer = $firstPlayerId;
    }

    /**
     * 扑克排序
     * @param $cards
     * @return array
     */
    private function sortPorker($cards)
    {
        $new = [];
        foreach ($cards as $card){
            $new[] = [$card[0], substr($card, 1)];
        }

        usort($new, 'sortPokerCard');
        $final = [];
        foreach ($new as  $value){
            $final[] = $value[0].$value[1];
        }
        return $final;
    }

    /**
     * 是否为当前操作用户
     * @param $playerActorId
     * @return bool
     */
    private function canDo($playerActorId)
    {
        return $this->nowPlayer == $playerActorId;
    }
}