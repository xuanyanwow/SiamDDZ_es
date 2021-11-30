<?php


namespace App\Utils;

use App\Actor\RoomActor;
use App\Exceptions\DdzPokerCardTypException;

/**
 * 斗地主扑克牌验证器
 * @package App\Utils
 */
class DdzPokerCardValidate
{
    const KING_BOOM = "KING_BOOM";
    const BOOM = "BOOM";
    const SIMPLE = "SIMPLE";
    const DOUBLE_ONE = "DOUBLE_ONE";
    const DOUBLE_MULTIPLE = "DOUBLE_MULTIPLE";
    const CONTINUOUS = "CONTINUOUS";// 顺子
    const TREE = "TREE";// 三同
    const TREE_WITH_ONE = "TREE_WITH_ONE";// 三，1
    const TREE_WITH_TWO = "TREE_WITH_TWO";// 三，2
    const TREE_PLANE = "TREE_PLANE";// 飞机 三同
    const TREE_PLANE_WITH_ONE = "TREE_PLANE_WITH_ONE";// 飞机 三带一
    const TREE_PLANE_WITH_TWO = "TREE_PLANE_WITH_TWO";// 飞机 三带二

    /**
     * @var \App\Actor\RoomActor
     */
    private $room;

    public static function make(RoomActor $room_actor)
    {
        $return = new static();
        $return->room = $room_actor;
        return $return;
    }

    /**
     * 验证类型
     * T: 是否为王炸、炸弹，是则通过
     * T: 是否为合格类型  单牌、对子、连对、顺子、三同、三带一、三带二、飞机
     * T: 是否与上一回合出牌信息类型一致
     */
    public function validate_type($origin_card_list)
    {
        if (empty($origin_card_list)) return false;

        $card_list = PokerCard::ignore_shape($origin_card_list);
        ksort($card_list);

        $count_values = array_count_values($card_list);

        // 只有一种牌型 ： 单牌、对子、三同、王炸、炸弹
        if (count($count_values) === 1) {
            if(count($card_list) === 1) return self::SIMPLE;
            if(count($card_list) === 2){
                if ($card_list[0] === $card_list[1] && $card_list[0] === "16"){
                    return self::KING_BOOM;
                }
                return self::DOUBLE_ONE;
            }
            if(count($card_list) === 3) return self::TREE;
            if(count($card_list) === 4) return self::BOOM;
        }

        $count_values_number = array_values($count_values);
        ksort($count_values_number);

        // 三带一、三带二 [3,1] 三带一  [3,2] 三带二
        if ($count_values_number[0] === 3){
            if ($count_values_number[1] === 1 && count($card_list) === 4) return self::TREE_WITH_ONE;
            if ($count_values_number[1] === 2 && count($card_list) === 5) return self::TREE_WITH_TWO;
        }

        // 顺子   所有牌都只有1张 并且是连续的
        $shun_temp = array_count_values($count_values);
        if (count($shun_temp) === 1
            && isset($shun_temp[1])
            && $shun_temp[1] === count($card_list)
            && count($card_list) >= 5 // 五张以上
        ){
            // 判断是否连续
            for ($i = 0; $i < (count($card_list) - 1); $i++){
                if ((int) $card_list[$i+1] - 1 !==  (int)$card_list[$i]) return false;
            }
            // 不能包含王
            if (in_array('16', $card_list)){
                return false;
            }
            return self::CONTINUOUS;
        }
        // 连对
        $lian_dui_temp = $shun_temp;
        if (count($lian_dui_temp) === 1 && isset($lian_dui_temp[2]) && $lian_dui_temp[2] === (count($card_list) / 2) ){
            // 判断是否连续
            for ($i = 0; $i < (count($card_list) / 2); $i++){
                $dui_index = 2*$i;

                if (
                    $card_list[$dui_index] !== $card_list[$dui_index+1]// 与下一张相同
                    || (
                        $dui_index+2 !== count($card_list)
                        && (int)$card_list[$dui_index] !== (int)($card_list[$dui_index+2]-1)
                    )// 与下一对连续(当前不是最后一对才需要)
                ) return false;
            }
            // 不能包含王
            if (in_array('16', $card_list)) {
                return false;
            }
            return self::DOUBLE_MULTIPLE;
        }

        // 飞机
        $plane_temp = $shun_temp;
        //  三个的都是要连续的
        $before_tree_card = false;
        foreach ($count_values as $card => $number){
            if ($number !== 3) {
                continue;
            }
            // 第一张是3牌的，那么后面的全部要是3牌才行 ，并且key要连续
            if (!!$before_tree_card){
                if ($before_tree_card+1 !== $card){
                    return false;
                }
            }
            $before_tree_card = $card;
        }

        // 兼容多个3 不带
        if (count($plane_temp) === 1 && isset($plane_temp[3]) && $plane_temp[3] > 1 ) return self::TREE_PLANE;
        if (!isset($plane_temp[3])){// 3 4 4 错误牌
            return false;
        }
        $tree_number = $plane_temp[3];
        // 3带1的飞机（带的没有对）
        // 3带1的飞机（带的有对）
        $with_one_number = $plane_temp[1] ?? 0;
        $with_two_number = $plane_temp[2] ?? 0;

        $total_with_number = $with_one_number * 1 + $with_two_number * 2;
        if ($total_with_number === $tree_number) return self::TREE_PLANE_WITH_ONE;// 区分
        // 如果带的是多对
        if ($plane_temp[2] === $plane_temp[3] ) return self::TREE_PLANE_WITH_TWO;// 区分

        return false;
    }

    /**
     * 验证牌大小
     * T: 王炸最大
     * T: 验证炸弹大小
     * T: 验证同类型牌的大小
     */
    public function validate_big($origin_card_list, $origin_old_card_list)
    {

        $card_list     = PokerCard::ignore_shape($origin_card_list);
        $old_card_list = PokerCard::ignore_shape($origin_old_card_list);

        $card_type = $this->validate_type($origin_card_list);
        $old_card_type = $this->validate_type($origin_old_card_list);

        if (empty($origin_old_card_list)) return true;// 没有旧牌，则出啥都是最大的

        if ($card_type === self::KING_BOOM) return true;
        if ($card_type === self::BOOM){
            if ($old_card_type === self::BOOM){
                // 比较两个炸弹的大小
                return (int)$card_list[0] > (int) $old_card_list[0];
            }elseif ($old_card_type === self::KING_BOOM){
                return false;
            }else{
                return true;
            }
        }
        if ($card_type === false || $card_type !== $old_card_type){
            // 牌类型不同
            throw new DdzPokerCardTypException("选择牌不符合");
        }
        // 比较器
        switch ($card_type){
            case self::SIMPLE:
                // 如果是王，则需要比花色，其他的只比数值
                if((int) $card_list[0] === 16 && (int) $old_card_list[0] === 16){
                    return $origin_card_list[0] === 'C16';
                }
                return (int)$card_list[0] > (int) $old_card_list[0];
            case self::DOUBLE_ONE:
            case self::DOUBLE_MULTIPLE:
            case self::CONTINUOUS:
            case self::TREE:
                // 第一张牌比原来第一张牌，则每一对都大
                return ((int)$card_list[0] > (int) $old_card_list[0]) && count($card_list) === count($old_card_list);
            case self::TREE_WITH_ONE:
            case self::TREE_WITH_TWO:
            case self::TREE_PLANE:
            case self::TREE_PLANE_WITH_ONE:
            case self::TREE_PLANE_WITH_TWO:
                // 判断最小的3张牌是多少数字，比原来大即可
                $card_count_values = array_count_values($card_list);
                $old_card_count_values = array_count_values($old_card_list);

                return key($card_count_values) > key($old_card_count_values);
        }
        return false;
    }
}