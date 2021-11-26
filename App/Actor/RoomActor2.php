<?php
/**
 * 房间actor
 * User: Siam
 * Date: 2020/5/18
 * Time: 9:58
 */

namespace App\Actor;


use App\WebSocket\WsCommand;
use EasySwoole\Actor\AbstractActor;
use EasySwoole\Actor\ActorConfig;
use EasySwoole\Actor\Exception\InvalidActor;
use EasySwoole\Component\Timer;
use EasySwoole\FastCache\Cache;

class RoomActor2 extends AbstractActor
{
    /** @var int 玩家进入 */
    const GAME_JOIN_PLAYER = 1;
    /** @var int 玩家退出 */
    const GAME_QUIT_PLAYER = 2;
    /** @var int 玩家准备开始 */
    const GAME_PRE_START = 3;
    /** @var int 玩家取消准备 */
    const GAME_CANCEL_START = 4;
    /** @var int 游戏开始 */
    const GAME_START = 5;
    /** @var int 发牌 */
    const GAME_SEND_CARD = 6;
    /** @var int 轮到叫地主操作 */
    const GAME_ASK_CALL_RICH = 7;
    /** @var int 玩家叫地主操作 */
    const GAME_CALL_RICH = 8;
    /** @var int 无人叫地主 重开 */
    const GAME_NOT_RICH_RESTART = 9;
    /** @var int 展示地主牌 */
    const GAME_SHOW_LANDLOAD_CARD = 10;
    /** @var int 玩家加牌(叫地主后) */
    const GAME_PLAYER_ADD_CARD = 11;
    /** @var int 玩家出牌 */
    const GAME_PLAYER_USE_CARD =12;
    /** @var int 玩家不出牌 */
    const GAME_PLAYER_PASS_CARD = 13;
    /** @var int 游戏结束 */
    const GAME_END = 14;
    /** @var int 游戏增加倍数 */
    const GAME_ADD_MULTIPLE = 15;

    /** @var int 发送消息给PlayerActor */
    const Message = 99;
    /** @var int 获取房间状态 */
    const GAME_GET_INFO = 98;


    /** @var array 洗牌后储存的 */
    private $pokerCard = [];
    /** @var array 玩家列表 */
    private $playerList = [];
    /** @var mixed 定时器id  扫描开始定时器、出牌定时器 */
    private $timerId;
    /** @var array 游戏开始后 每个玩家的牌 key为playerActorId */
    private $playerCard = [];
    /** @var array 三张地主牌 */
    private $richCards = [];
    /** @var int 房间倍数 默认1倍 */
    private $multiple = 1;
    /** @var mixed 当前操作用户 */
    private $nowPlayer;
    /** @var mixed 地主玩家 */
    private $richPlayer;

    /** @var int 每轮操作的间隔 */
    const PERIOD_TIME = 10;

    public static function configure(ActorConfig $actorConfig)
    {
        $actorConfig->setActorName('RoomActor');
        $actorConfig->setWorkerNum(3);
    }

    protected function onStart()
    {
        // 房间创建后 每1秒检测一次是否可以开始
        $this->timerId = Timer::getInstance()->loop(1000, function () {
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
     * @return mixed
     */
    protected function onMessage($msg)
    {
        if (!($msg instanceof Command)) {
            return FALSE;
        }

        switch ($msg->getDo()) {
            case PlayerActor::JOIN_ROOM: // 进入房间
                if (count($this->playerList) >= 3){
                    return false;
                }
                if (in_array($msg->getData()['player'], $this->playerList)){
                    return false;
                }
                $this->playerList[] = $msg->getData()['player'];
                break;
            case PlayerActor::QUIT_ROOM: // 退出房间
                // 未开始则删除
                // todo 开始了则更改为托管状态
                $index = array_search($msg->getData()['player'], $this->playerList);
                if ($index){
                    unset($this->playerList[$index]);
                }
                $playerQuitCommand = new Command();
                $playerQuitCommand->setDo(self::GAME_QUIT_PLAYER);
                $playerQuitCommand->setData([
                    'player' => $msg->getData()['player']
                ]);
                $send[] = $playerQuitCommand;
                break;

            case PlayerActor::CALL_RICH: // 叫地主
                $this->clearTimer();
                if (!$this->canDo($msg->getData()['actorId'])) return FALSE;

                if ($msg->getData()['result'] === TRUE) {
                    $send   = [];
                    $this->multiple = $this->multiple * 2;

                    $this->richPlayer = $msg->getData()['actorId'];
                    $this->multiple   = $this->multiple * 2;
                    $multipleCommand  = new Command();
                    $multipleCommand->setDo(self::GAME_ADD_MULTIPLE);
                    $multipleCommand->setData([
                        'multiple' => 2
                    ]);
                    $send[] = $multipleCommand;

                    $playerQuitCommand = new Command();
                    $playerQuitCommand->setDo(self::GAME_CALL_RICH);
                    $playerQuitCommand->setData([
                        'player' => $this->richPlayer
                    ]);
                    $send[] = $playerQuitCommand;

                    $showCardCommand = new Command();
                    $showCardCommand->setDo(self::GAME_SHOW_LANDLOAD_CARD);
                    $showCardCommand->setData([
                        'cards' => $this->richCards
                    ]);
                    $send[] = $showCardCommand;

                    foreach ($this->richCards as $card) {
                        $this->playerCard[$this->richPlayer][] = $card;
                    }

                    $this->sendToPlayer($send);
                } else {
                    // todo 需要记录重开次数、已经操作的人；如果全部不叫则重开，重开3次则最后一个玩家强制当地主
                }

                break;
            //
            // case PlayerActor::SEND_CARD:
            //     // todo 判断出牌是否符合逻辑、是否玩家拥有所有牌；
            //     // 是则删除剩余牌
            //     // 是否增加倍数
            //     // 判断是否胜利
            //     // 胜利则结算
            //     break;

            case PlayerActor::PASS_CADR:
                break;
            /** 获取房间状态：玩家信息、准备状态 */
            case self::GAME_GET_INFO:
                $command = new Command();
                $command->setDo(PlayerActor::GET_INFO);
                $replyData = $this->sendToPlayer([$command]);
                break;
        }

        if (isset($replyData)){
            return $replyData;
        }
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

        foreach ($this->playerList as $key => $player) {
            // 排序
            $final                     = $this->sortPorker($tem[$key]);
            $this->playerCard[$player] = $final;
        }
        $this->richCards = $this->sortPorker($tem[3]);

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
        echo "游戏开始\n";
        // 通知游戏开始
        $ws = new WsCommand();
        $ws->setClass("game");
        $ws->setAction("start");

        $command = new Command();
        $command->setDo(self::Message);
        $command->setData($ws);
        $this->sendToPlayer([$command]);

        // 叫地主的人 第一次就第一个，后面的就谁赢了谁先叫
        $firstPlayerId = $this->playerList[0];

        foreach ($this->playerList as $player) {
            // 发基础牌
            $command = new Command();
            $command->setDo(self::GAME_SEND_CARD);
            $command->setData($this->playerCard[$player]);
            $send   = [];
            $send[] = $command;
            // 通知哪个玩家叫地主
            $ws = new WsCommand();
            $ws->setClass("game");
            $ws->setAction("ask_call_rich");
            $ws->setData([
                'player'=>$firstPlayerId
            ]);

            $callLandLoad = new Command();
            $callLandLoad->setDo(self::Message);
            $callLandLoad->setData($ws);

            $send[] = $callLandLoad;
            try {
                $playerActorId = Cache::getInstance()->get("player_{$player}");
                PlayerActor::client()->send($playerActorId, $send);
            } catch (InvalidActor $e) {
                // 通知失败 游戏结束
            }
        }
        // 叫地主定时器
        $this->timerId = Timer::getInstance()->after(self::PERIOD_TIME * 1000, function(){
            echo "无人叫地主\n";
        });
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
        foreach ($cards as $card) {
            $new[] = [$card[0], substr($card, 1)];
        }

        usort($new, 'sortPokerCard');
        $final = [];
        foreach ($new as $value) {
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

    /**
     * 房间状态重置，在一局结束后清理
     */
    private function reset()
    {
        $this->nowPlayer  = NULL;
        $this->timerId    = NULL;
        $this->playerCard = [];
        $this->richCards  = [];
        $this->multiple   = 1;
    }

    /**
     * 轮到下一个玩家，并且更改nowPlayer属性
     * @return mixed|string
     */
    private function getNextPlayer()
    {
        $index = array_search($this->nowPlayer, $this->playerList);
        if ($index === false){
            return "";
        }
        if ($index===count($this->playerList) - 1){
            $this->nowPlayer = $this->playerList[0];
        }else{
            $this->nowPlayer = $this->playerList[++$index];
        }
        return $this->nowPlayer;
    }

    private function clearTimer()
    {
        Timer::getInstance()->clear($this->timerId);
    }

    /**
     * 每一次通知必须是全部用户公平通知 不能私发
     * @param $command
     */
    private function sendToPlayer(array $command)
    {
        $return = [];
        foreach ($this->playerList as $player) {
            try {
                $playerActorId = Cache::getInstance()->get("player_{$player}");
                $reply = PlayerActor::client()->send($playerActorId, $command);
                if ($reply !== null){
                    $return[] = $reply;
                }
            } catch (InvalidActor $e) {
                echo $e->getMessage().PHP_EOL;
            }
        }
        return $return;
    }
}