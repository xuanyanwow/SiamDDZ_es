<?php

function sortPokerCard($a, $b){
    // 特殊处理 A和2
    // 第一张牌是A  只小于 2和王
    // if ($a[1] == 1){
    //     if ($b[1] == 2 || $b[1] >= 14){
    //         return -1;
    //     }else{
    //         return 1;
    //     }
    // }
    // if ($b[1] == 1){
    //     if ($a[1] == 2 || $a[1] >= 14){
    //         return 1;// a > b
    //     }else{
    //         return -1;
    //     }
    // }
    // // 第一张牌是2 只小于王
    // if ($a[1] == 2){
    //     if ($b[1] >= 14){
    //         return -1;
    //     }else{
    //         return 1;
    //     }
    // }
    // if ($b[1] == 2){
    //     if ($a[1] >= 14){
    //         return 1;
    //     }else{
    //         return -1;
    //     }
    // }

    if ($a[1] == $b[1]){
        // 大小相等，比花色
        return (ord($a[0]) > ord($b[0])) ? 1 : -1;
    }
    return ( (int) $a[1] > (int) $b[1] ) ? 1 : -1;
}