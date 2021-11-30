<?php
/**
 * 房间actor
 * User: Siam
 * Date: 2020/5/18
 * Time: 9:58
 */

namespace App\Actor;


use App\Exceptions\DdzPokerCardTypException;
use App\Repository\UserConnectInfoMap;
use App\Utils\DdzPokerCardValidate;
use App\Utils\PokerCard;
use App\Utils\WsHelper;
use App\WebSocket\WsCommand;
use EasySwoole\Actor\AbstractActor;
use EasySwoole\Actor\ActorConfig;
use EasySwoole\Actor\Exception\InvalidActor;
use EasySwoole\Component\Timer;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\FastCache\Cache;
use EasySwoole\Utility\Time;

class RoomActor extends AbstractActor
{
    /** @var string 发送提示 */
    const GAME_NOTICE = "game_notice";

    /** @var int 玩家进入 */
    const GAME_JOIN_PLAYER = "do_join_player";
    /** @var string 游戏进行中，重连 */
    const GAME_PLAYING_RECONNECT= "reconnect";
    /** @var int 游戏开始 */
    const GAME_START = "game_start";
    /** @var int 玩家叫地主操作 */
    const GAME_CALL_RICH = "call_rich";

    /** @var int 游戏增加倍数 */
    const GAME_CHANGE_MULTIPLE = "change_multiple";
    /** @var int 游戏角色改变地主 */
    const GAME_CHANGE_RICH = "change_rich";
    /** @var int 展示地主牌 */
    const GAME_SHOW_RICH_CARD = "show_rich_card";
    /** @var int 改变玩家出牌权 */
    const GAME_CHANGE_PLAYER_USE_CARD = "change_player_use_card";
    /** @var int 玩家不出牌 */
    const GAME_PLAYER_PASS_CARD = "player_pass_card";
    /** @var int 玩家出牌 */
    const GAME_PLAYER_USE_CARD = "player_use_card";
    /** @var int 游戏结算 */
    const GAME_SETTLE = "settle";

    /** @var int 玩家退出 */
    const GAME_QUIT_PLAYER = 2;
    /** @var int 玩家准备开始 */
    const GAME_PRE_START = "do_prepare";
    /** @var int 玩家取消准备 */
    const GAME_CANCEL_START = 4;
    /** @var int 游戏结束 */
    const GAME_END = 14;
    /**
     * @var string 获取房间信息（第一次加入和掉线的时候）
     */
    const GAME_GET_INFO = 'game_get_info';


    /**
     * @var array 玩家列表
     */
    private $playerList = [];
    /**
     * @var mixed 当前操作用户
     */
    private $nowPlayer;
    /**
     * @var mixed 上一回合谁赢
     */
    private $win_player_before_round;
    /**
     * @var mixed 地主玩家
     */
    private $richPlayer;
    /**
     * @var int 已准备玩家
     */
    private $prepare_player_list = [];

    // ==================== 玩家信息 ==================

    /**
     * @var array 游戏开始后 每个玩家的牌 key为playerActorId
     */
    private $playerCard = [];
    /**
     * @var array 三张地主牌
     */
    private $richCards = [];

    // ==================== 卡牌信息 ==================

    /**
     * @var int 房间倍数 默认1倍
     */
    private $multiple = 1;
    /**
     * @var int 已经玩了几回合
     */
    private $play_round = 1;

    // ==================== 房间信息 结算等场景用 ==================

    /**
     * @var mixed 定时器id  扫描开始定时器、出牌定时器
     */
    private $timerId;
    /** @var int 每轮操作的间隔 */
    private $period_time = 25;

    /** @var string 等待准备 */
    const ROOM_STATUS_WAIT_PREPARE = "wait_prepare";
    /** @var string 等待开始 */
    const ROOM_STATUS_WAIT_START = "wait_start";
    /** @var string 游戏中 */
    const ROOM_STATUS_START = "start";
    /** @var string 叫地主中 */
    const ROOM_STATUS_CALL_RICH = "call_rich";
    /** @var string 决定明牌中 */
    const ROOM_STATUS_SHOW_CARD = "show_card";
    /** @var string 出牌中 */
    const ROOM_STATUS_USE_CARD = "use_card";

    /** @var string 房间状态 */
    private $room_status = self::ROOM_STATUS_WAIT_PREPARE;
    /** @var bool 当前房间状态是否可以过牌 */
    private $can_pass = true;
    /** @var mixed 上一回合出牌人 */
    private $before_use_card_player;
    /** @var array 上一回合出牌内容 */
    private $before_use_card_list = [];

    // ==================== 房间信息 设置参数等 ==================
    private $rich_round = 0;
    /**
     * 牌型验证器
     * @var \App\Utils\DdzPokerCardValidate
     */
    private $pokerCardValidate;


    public static function configure(ActorConfig $actorConfig)
    {
        $actorConfig->setActorName('RoomActor');
        $actorConfig->setWorkerNum(3);
    }

    protected function onStart()
    {
        // 房间创建后 每1秒检测一次是否可以开始
        $this->timerId = Timer::getInstance()->loop(1000, function () {
            if (count($this->prepare_player_list) < 3) {
                return FALSE;
            }
            Timer::getInstance()->clear($this->timerId);
            $this->start();
            return TRUE;
        });
        $this->pokerCardValidate = DdzPokerCardValidate::make($this);
    }

    /**
     * @param Command $msg
     */
    protected function onMessage($msg)
    {
        if (!($msg instanceof Command)){
            return null;
        }
        $action = $msg->getDo();
        // TODO 校验权限，是否为游戏玩家，是否为当前操作玩家

        $this->$action($msg->getData());
        WsHelper::defer_push();
    }

    protected function onExit($arg)
    {

    }

    protected function onException(\Throwable $throwable)
    {
    }

    /**
     * 玩家加入房间
     * @param $data
     */
    private function do_join_player($data)
    {
        if ($this->room_status !== self::ROOM_STATUS_WAIT_START
            && in_array($data['userId'], $this->playerList)
        ){
            $this->_push_one($data['userId'], Command::make(self::GAME_PLAYING_RECONNECT, [

            ]));
            return ;
        }
        if (count($this->playerList) >= 3){
            return ;
        }
        if (in_array($data['userId'], $this->playerList)){
            return ;
        }
        $this->playerList[] = $data['userId'];


        $player_info = [];
        foreach ($this->playerList as $user_id){
            $player_info[] = [
                "user_id"     => (string) $user_id,
                "role"        => "",
                "card_number" => 0,
                "status_text" => in_array($user_id, $this->prepare_player_list) ? "准备" : '',
            ];
        }
        $this->_push_all(Command::make(self::GAME_GET_INFO, [
            'room_status'      => $this->room_status,
            'player_info_list' => $player_info,
        ]));
    }

    private function do_prepare($data)
    {
        $user_id = $data['user_id'];
        $result = $data['result'];
        if ($result){
            $this->prepare_player_list[] = $user_id;
        }else{
            // 删除已准备
            $index = array_search($user_id, $this->prepare_player_list);
            unset($this->prepare_player_list[$index]);
        }

        // 通知玩家 切换准备状态
        $this->_push_all(Command::make(self::GAME_PRE_START,[
            'user_id' => $user_id,
            'result'  => $result,
        ]));
    }

    /**
     * 玩家操作出牌
     * @param $data
     */
    private function player_use_card($data)
    {
        $user_id    = $data['userId'];
        $card_array = $data['card_list'];

        // 验证牌类型
        if ($this->pokerCardValidate->validate_type($card_array) === false){
            $this->_push_one($user_id, Command::make(self::GAME_NOTICE,[
                'msg' => '牌型错误'
            ]));
            return ;
        }

        // 验证牌是玩家拥有的

        // 验证牌大小 可能会抛出异常 牌类型错误
        try {
            $temp_before_use_card_list = $this->before_use_card_list;
            if ($this->before_use_card_player === $user_id){
                $temp_before_use_card_list = [];
            }
            if (!$this->pokerCardValidate->validate_big($card_array, $temp_before_use_card_list)){
                // 回推客户端 大小不符
                $this->_push_one($user_id, Command::make(self::GAME_NOTICE,[
                    'msg' => '出牌大小不符'
                ]));
                return ;
            }
        }catch (DdzPokerCardTypException $e){
            // 回推客户端 不符合牌型
            $this->_push_one($user_id, Command::make(self::GAME_NOTICE,[
                'msg' => $e->getMessage()
            ]));
            return ;
        }
        $this->clearTimer();
        $this->before_use_card_player = $user_id;
        $this->before_use_card_list = $card_array;
        $this->can_pass = true;// 有人出牌 下家就可以过牌了
        $this->playerCard[$user_id] = array_diff($this->playerCard[$user_id], $card_array);
        $this->nowPlayer = $user_id;

        // 回推客户端 出牌成功
        $this->_push_all(Command::make(self::GAME_PLAYER_USE_CARD,[
            'user_id'    => $user_id,
            'card_array' => $card_array,
            'card_type'  => $this->pokerCardValidate->validate_type($card_array)
        ]));
        // 判断是否结束
        if ( $this->can_settle() ){
            var_dump("可以结算了");
            $this->settle();
            return;
        }

        // 轮到下一个人出牌
        $next_user_id = $this->next_user();
        $this->_push_all(Command::make(self::GAME_CHANGE_PLAYER_USE_CARD,[
            'user_id'  => $next_user_id,
            'can_pass' => $this->can_pass,
            'endTime'  => $this->_get_end_time(function() use($next_user_id){
                // 超时则默认处理叫地主 不叫
                call_user_func([$this,'use_card_timeout'], [
                    'userId' => $next_user_id,
                ]);
            }),
        ]));

    }

    public function settle()
    {
        // 判断是农民还是地主赢
        // 地主赢 加倍数*2 农民各减倍数*1
        // 农民赢 加倍数*1 地主减倍数*2
        $settle = [];
        $rich_win = false;
        if (count($this->playerCard[$this->richPlayer]) === 0){
            $rich_win = true;
        }
        foreach ($this->playerList as $user_id){
            if (count($this->playerCard[$user_id]) === 0) {
                $this->win_player_before_round = $user_id;
            }
            if ($user_id == $this->richPlayer){
                $settle[$user_id] = [
                    'type' => $rich_win ? '+' : '-',
                    'number'=> $this->multiple * 2,
                ];
            }else{
                $settle[$user_id] =  [
                    'type' => !$rich_win ? '+' : '-',
                    'number'=> $this->multiple,
                ];
            }
        }
        $this->_push_all(Command::make(self::GAME_SETTLE, [
            'settle' => $settle
        ]));
    }

    /**
     * 玩家过牌
     */
    private function player_pass_card($data)
    {
        $user_id = $data['userId'];
        $this->clearTimer();

        $this->nowPlayer = $user_id;
        $next_user_id = $this->next_user();

        // 如果上一回出牌的人为空，则不能pass，
        // 如果上一回出牌的人是自己，（其他两家pass）则不能pass
        if (!$this->before_use_card_player || ($this->before_use_card_player === $next_user_id)){
            $this->can_pass = false;
        }else{
            $this->can_pass = true;
        }

        $this->_push_all(Command::make(self::GAME_PLAYER_PASS_CARD, [
            'user_id' => $user_id,
        ]));
        $this->_push_all(Command::make(self::GAME_CHANGE_PLAYER_USE_CARD, [
            'user_id'  => $next_user_id,
            'can_pass' => $this->can_pass,
            'endTime'  => $this->_get_end_time(function() use($next_user_id){
                // 超时则默认处理叫地主 不叫
                call_user_func([$this,'use_card_timeout'], [
                    'userId' => $next_user_id,
                ]);
            }),
        ]));
    }

    /**
     * 洗牌、发牌
     */
    private function shufflePoker()
    {
        var_dump("发牌\n");
        $pokerCard = PokerCard::make();
        // 分割成三份基础牌
        $tem = array_chunk($pokerCard, 17);

        foreach ($this->playerList as $key => $player) {
            // 排序
            $final                     = PokerCard::sort($tem[$key]);
            $this->playerCard[$player] = $final;
            // 告诉PlayerActor发牌
            $this->_push_one($player, Command::make(PlayerActor::GET_CARD, $final));
        }
        $this->richCards = PokerCard::sort($tem[3]);
    }

    /**
     * 通知开始，计算第一名玩家，通知要不要叫叫地主
     */
    private function start()
    {
        var_dump("游戏开始\n");
        // 游戏开始
        $this->_push_all(Command::make(self::GAME_START, []));
        $this->room_status = self::ROOM_STATUS_START;

        // 洗牌发牌
        $this->shufflePoker();

        // 通知叫地主
        var_dump("计算谁叫地主");
        $this->room_status = self::ROOM_STATUS_CALL_RICH;
        $this->rich_round++;
        $user_id = $this->who_call_rich_first();
        $this->notice_call_rich($user_id);

        WsHelper::defer_push();
    }

    /**
     * 玩家操作叫地主
     */
    private function call_rich($data): bool
    {
        $user_id = $data['userId'];
        $is_call = $data['result'];
        var_dump($user_id."操作叫地主：", $is_call);
        $this->clearTimer();
        if($is_call){
            // 倍数基本信息处理
            $this->multiple = $this->multiple * 2;
            $this->richPlayer = $user_id;

            // 牌合并
            foreach ($this->richCards as $card){
                $this->playerCard[$this->richPlayer][] = $card;
            }
            $this->playerCard[$this->richPlayer] = PokerCard::sort($this->playerCard[$this->richPlayer]);

            // TODO 抢地主逻辑

            // 广播 倍数、谁是地主、地主牌
            $this->_push_all(Command::make(self::GAME_CHANGE_MULTIPLE, [
                'multiple' => $this->multiple
            ]));
            $this->_push_all(Command::make(self::GAME_CHANGE_RICH, [
                'user_id' => $this->richPlayer
            ]));
            $this->_push_all(Command::make(self::GAME_SHOW_RICH_CARD, [
                'card' => $this->richCards
            ]));
            // 是否要明牌
            $this->room_status = self::ROOM_STATUS_SHOW_CARD;

            // 轮到谁出牌 地主不能pass
            $this->can_pass = false;
            $this->_push_all(Command::make(self::GAME_CHANGE_PLAYER_USE_CARD, [
                'user_id'  => $this->richPlayer,
                'can_pass' => $this->can_pass,
                'endTime'  => $this->_get_end_time(function() use($user_id){
                    // 超时则默认处理叫地主 不叫
                    call_user_func([$this,'use_card_timeout'], [
                        'userId'   => $user_id,
                        'is_first' => true,
                    ]);
                }),
            ]));


            // 私发 新的牌
            $this->_push_one($this->richPlayer, Command::make(PlayerActor::GET_CARD, $this->playerCard[$this->richPlayer]));
            WsHelper::defer_push();
            return true;
        }

        // 没叫地主
        // 本轮叫地主还没结束，下一位
        if ($this->next_user() !== $this->who_call_rich_first()){
            $user_id = $this->next_user();
            $this->notice_call_rich($user_id);
            WsHelper::defer_push();
            return true;
        }

        if ($this->rich_round > 1){
            // 强制第一个玩家当地主
            return $this->call_rich([
                'userId' => $this->who_call_rich_first(),
                'result' => true
            ]);
        }

        // 如果已经是最后一个玩家了，则需要重发牌，计数
        $this->start();
        return true;
    }

    /**
     * 通知用户叫地主
     * @param $user_id
     */
    private function notice_call_rich($user_id)
    {
        var_dump($user_id);
        $this->nowPlayer = $user_id;
        $this->_push_all(Command::make(self::GAME_CALL_RICH, [
            'userId'  => $user_id,
            'test'    => "test",
            'endTime' => $this->_get_end_time(function() use($user_id){
                // 超时则默认处理叫地主 不叫
                call_user_func([$this,'call_rich'], [
                    'userId' => $user_id,
                    'result' => false,
                ]);
            }),
        ]));
    }

    /**
     * 玩家出牌超时
     * T: v1直接不出跳过
     * T: V2 智能计数 三次不出则AI托管
     * @param $data
     */
    private function use_card_timeout($data)
    {
        // 当前房间状态不能过牌的，超时则直接认输
        if (!$this->can_pass){
            var_dump($data['userId']);
            echo "超时，当前不能过牌，直接认输";
            // TODO
        }else{
            $this->player_pass_card($data);
        }

    }


    // =======================================================
    //    ================  系统辅助   ======================
    // =======================================================

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

    private function clearTimer()
    {
        Timer::getInstance()->clear($this->timerId);
    }

    /**
     * 计算轮到谁叫地主
     * T: 首轮则第一位玩家，后面则为上一回合赢的玩家
     * T: 三家不叫则重发牌
     * T: 不叫三回合则第一位玩家强制叫地主
     */
    private function who_call_rich_first()
    {
        if ($this->play_round === 1) {
            $user_id = $this->playerList[0];
        }else{
            $user_id = $this->win_player_before_round;
        }
        return $user_id;
    }

    /**
     * 下一个操作用户是谁
     * @return mixed
     */
    private function next_user()
    {
        $before_player = $this->nowPlayer;
        $before_index  = array_search($before_player, $this->playerList);

        if ($before_index+1 === count($this->playerList)){
            return $this->playerList[0];
        }
        return $this->playerList[++$before_index];
    }

    /**
     * 返回此轮操作结束时间戳
     * @param $timeout_event
     * @return int
     */
    private function _get_end_time($timeout_event): int
    {
        // 注册超时定时器
        $this->timerId = Timer::getInstance()->after($this->period_time * 1000, $timeout_event);
        return $this->period_time;
    }

    /**
     *广播消息
     * @param $data
     */
    private function _push_all($data)
    {
        WsHelper::set_push_list(UserConnectInfoMap::userList_to_fdList($this->playerList), $data);
    }

    /**
     * 私发消息
     * @param $user_id
     * @param $data
     */
    private function _push_one($user_id, $data){
        WsHelper::set_push(UserConnectInfoMap::user_get_fd($user_id), $data);
    }

    private function can_settle()
    {
        // 任何一个人没牌了就可以结算
        foreach ($this->playerCard as $user_id => $card_array){
            if (count($card_array) === 0) return true;
        }
        return false;
    }
}