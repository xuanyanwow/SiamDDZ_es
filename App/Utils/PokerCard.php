<?php


namespace App\Utils;


class PokerCard
{
    public static function make()
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
            //                                               J       Q      K     A       2
            'H3', 'H4', 'H5', 'H6', 'H7', 'H8', 'H9', 'H10', 'H11', 'H12', 'H13', 'H14', 'H15',
            'S3', 'S4', 'S5', 'S6', 'S7', 'S8', 'S9', 'S10', 'S11', 'S12', 'S13', 'S14', 'S15',
            'D3', 'D4', 'D5', 'D6', 'D7', 'D8', 'D9', 'D10', 'D11', 'D12', 'D13', 'D14', 'D15',
            'C3', 'C4', 'C5', 'C6', 'C7', 'C8', 'C9', 'C10', 'C11', 'C12', 'C13', 'C14', 'C15',
            'H16', 'C16'// H16小王 C16大王
        ];
        shuffle($all);
        shuffle($all);
        return $all;

    }


    /**
     * 扑克排序
     * @param $cards
     * @return array
     */
    public static function sort($cards)
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
     * 过滤花色，只返回牌的数字
     * @param $card_list
     * @return array
     */
    public static function ignore_shape($card_list)
    {
        $return = [];
        foreach ($card_list as $card){
            $return[] = substr($card, 1);
        }
        return $return;
    }
}