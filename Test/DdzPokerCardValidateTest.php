<?php
include_once __DIR__."/../App/Utils/DdzPokerCardValidate.php";
include_once __DIR__."/../App/Utils/PokerCard.php";
use App\Utils\DdzPokerCardValidate;

class DdzPokerCardValidateTest
{
    public function run()
    {
        $validate =  new DdzPokerCardValidate();
        $card_list = ['H3'];// 单牌测试
        if ($validate->validate_type($card_list) !== DdzPokerCardValidate::SIMPLE){
            echo "单牌测试\n";
        }
        $card_list = ['H3','D3'];// 对子测试
        if ($validate->validate_type($card_list) !== DdzPokerCardValidate::DOUBLE_ONE){
            echo "对子测试\n";
        }
        $card_list = ['D3', 'D4', 'D5', 'D6', 'D7'];// 顺子测试
        if ($validate->validate_type($card_list) !== DdzPokerCardValidate::CONTINUOUS){
            echo "顺子测试\n";
        }
        $card_list = ['H3', 'S3', 'D3'];// 三同测试
        if ($validate->validate_type($card_list) !== DdzPokerCardValidate::TREE){
            echo "三同测试\n";
        }
        $card_list = ['H3', 'S3', 'D3', 'C4'];// 三带一测试
        if ($validate->validate_type($card_list) !== DdzPokerCardValidate::TREE_WITH_ONE){
            echo "三带一测试\n";
        }
        $card_list = ['H3', 'S3', 'D3', 'C4', 'D4'];// 三带二测试
        if ($validate->validate_type($card_list) !== DdzPokerCardValidate::TREE_WITH_TWO){
            echo "三带二测试\n";
        }
        $card_list = ['H3', 'S3', 'H4', 'S4','H5', 'S5','H6', 'S6'];// 连对测试
        if ($validate->validate_type($card_list) !== DdzPokerCardValidate::DOUBLE_MULTIPLE){
            echo "连对测试\n";
        }
        $card_list = ['H3', 'S3', 'D3','H4', 'S4', 'D4', 'S5','S5'];// 飞机测试  带2只(有对)
        if ($validate->validate_type($card_list) !== DdzPokerCardValidate::TREE_PLANE_WITH_ONE){
            echo "飞机测试  带2只(有对)\n";
        }
        $card_list = ['H3', 'S3', 'D3','H4', 'S4', 'D4', 'S5','S7'];// 飞机测试2 带2只(无对)
        if ($validate->validate_type($card_list) !== DdzPokerCardValidate::TREE_PLANE_WITH_ONE){
            echo "飞机测试2 带2只(无对)\n";
        }
        $card_list = ['H3', 'S3', 'D3','H4', 'S4', 'D4', 'S5','S5','S6','S6'];// 飞机测试2 带2对
        if ($validate->validate_type($card_list) !== DdzPokerCardValidate::TREE_PLANE_WITH_TWO){
            echo "飞机测试2 带2对\n";
        }
        // $card_list = ['H3', 'S3', 'D3','H4', 'S4', 'D5', 'S5','S5','S6','S6'];// 飞机测试2 带2对
        // if ($validate->validate_type($card_list) !== DdzPokerCardValidate::TREE_PLANE_WITH_TWO){
        //     echo "飞机测试2 带2对 不连续 错误检测\n";
        // }
        $card_list = ['H3', 'S3', 'D3','H4', 'S4', 'D4', 'S5','S5','S5'];// 飞机测试3 无带
        if ($validate->validate_type($card_list) !== DdzPokerCardValidate::TREE_PLANE){
            echo "飞机测试3\n";
        }
        // $card_list = ['H3', 'S3', 'D3','H4', 'S4', 'D4', 'S6','S6','S6'];// 飞机测试3 无带
        // if ($validate->validate_type($card_list) !== DdzPokerCardValidate::TREE_PLANE){
        //     echo "飞机测试3 不连续\n";
        // }
        $card_list = ['H3', 'S3', 'D3','C3'];// 炸弹测试
        if ($validate->validate_type($card_list) !== DdzPokerCardValidate::BOOM){
            echo "炸弹测试\n";
        }
        $card_list = [ 'H16', 'C16'];// 王炸测试
        if ($validate->validate_type($card_list) !== DdzPokerCardValidate::KING_BOOM){
            echo "王炸测试\n";
        }
    }

    public function test_big()
    {
        $validate =  new DdzPokerCardValidate();
        if ($validate->validate_big(['A3'],['A4']) !== false){
            echo "单牌比较错误";
        }
        if ($validate->validate_big(['A4'],['A3']) !== true){
            echo "单牌比较错误";
        }
        if ($validate->validate_big(['H16'],['A3']) !== true){
            echo "单牌比较错误";
        }
        if ($validate->validate_big(['C16'],['H16']) !== true){
            echo "单牌比较错误";
        }

        if ($validate->validate_big(['H4', 'S4'],['H3', 'S3']) !== true){
            echo "对子比较错误";
        }
        if ($validate->validate_big(['H12', 'S12'],['H3', 'S3']) !== true){
            echo "对子比较错误";
        }

        if ($validate->validate_big(['H12', 'S12', 'H13', 'S13'],['H3', 'S3','H4', 'S4']) !== true){
            echo "连对";
        }
        if ($validate->validate_big(['H12', 'S12', 'H13', 'S13'],['H12', 'S12', 'H13', 'S13']) !== false){
            echo "连对";
        }

        if ($validate->validate_big(['D4', 'D5', 'D6', 'D7','D8'], ['D3', 'D4', 'D5', 'D6', 'D7']) !== true){
            echo "顺子比较错误";
        }
        if ($validate->validate_big(['D4', 'D5', 'D6', 'D7','D8','D9'], ['D3', 'D4', 'D5', 'D6', 'D7']) !== false){
            echo "顺子数量不同，应该是错误";
        }
        if ($validate->validate_big(['D11', 'D12', 'D13', 'D14','D15','H16'], ['D3', 'D4', 'D5', 'D6', 'D7']) !== false){
            echo "顺子包含王 应该是错误";
        }


        if ($validate->validate_big(['H11', 'S11', 'D11'], ['H10', 'S10', 'D10']) !== true){
            echo "三同比较错误";
        }
        if ($validate->validate_big(['H11', 'S11', 'D11','H16'], ['H10', 'S10', 'D10', 'H3']) !== true){
            echo "三带一比较错误";
        }
        if ($validate->validate_big(['H11', 'S11', 'D11','H16','C16'], ['H10', 'S10', 'D10', 'H3', 'D3']) !== true){
            echo "三带二比较错误";
        }

        if ($validate->validate_big(['H11', 'S11', 'D11','H12', 'S12', 'D12'], ['H9', 'S9', 'D9', 'H10', 'S10', 'D10']) !== true){
            echo "飞机不带比较错误";
        }
        if ($validate->validate_big(
            ['H11', 'S11', 'D11','H12', 'S12', 'D12','H3','D3'],
            ['H9', 'S9', 'D9', 'H10', 'S10', 'D10','S3','C3']
        ) !== true){
            echo "飞机 三带一 比较错误";
        }
        if ($validate->validate_big(
                ['H11', 'S11', 'D11','H12', 'S12', 'D12','H3','D3','H4','D4'],
                ['H9', 'S9', 'D9', 'H10', 'S10', 'D10','S3','C3','H8','D8']
            ) !== true){
            echo "飞机 三带二 比较错误";
        }

    }
}

(new DdzPokerCardValidateTest)->run();
(new DdzPokerCardValidateTest)->test_big();