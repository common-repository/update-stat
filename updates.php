<?php
/*
Plugin Name: Update Stat
Plugin URI: http://zilkerok.com.ua
Description: Плагин выводит ежедневную статистику апдейтов, а также показатели тИЦ и PR российских и украинских поисковых систем Google и Яндекс.
Version: 1.2
Author: Валерий Заочный
Author URI: http://zilkerok.com.ua

Copyright 2012-2013  Valery Zaochnuy  (email: valery.zaochnuy@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['up_number_of_days'])) {
    $up_number_of_days = $_POST['up_number_of_days'];
    update_option('up_number_of_days', $up_number_of_days);
}

function up_view_stat() {
    $up_number_of_days = get_option('up_number_of_days');
    if (!$up_number_of_days) {
        $up_number_of_days = 7;
        update_option('up_number_of_days', 7);
    }
    $puzo = getPuzo();
    print "<table class='seolib_table'><tr><th style='width: 50%'>тИЦ</td><th style='width: 50%'>PR</td></tr><tr><td class='bbz'>".$puzo['cy']."</td><td class='bbz'>".$puzo['pr']."</td></tr></table>";
    print "<script type='text/javascript' src='http://updates.seolib.ru/widgets/".$up_number_of_days.".js?date=".date("Y-m-d")."'></script>";
}

function add_up_view_stat() {
    wp_add_dashboard_widget('up_view_stat', 'Update Stat', 'up_view_stat');
    update_option('up_number_of_days', get_option('up_number_of_days'));
}
function add_up_menu_item(){
    add_submenu_page('options-general.php', 'Update Stat', 'Update Stat', 'manage_options', 'update-stat', 'up_view_stat_settings');
}
function up_view_stat_settings(){
    $up_number_of_days = get_option('up_number_of_days');
    if (!$up_number_of_days) {
        $up_number_of_days = 7;
        update_option('up_number_of_days', 7);
    }
    print "<br>Здесь вы можете указать, за какое количество дней показывать апдейты.<br><br><form method='post'><input class='up_number_of_days' name='up_number_of_days' type='text' maxlength='1' value=".$up_number_of_days."><input type='submit' value='Сохранить'></form>
     <br><span class='up_number_of_days_link'>* максимум - 7, по умолчанию - 7</span>";
}

function getPuzo() {
    $puzo_cache = get_option('up_puzo_cache');
    $puzo = array();
    // cache cy & pr for 30m
    if ($puzo_cache && (time() - (int)$puzo_cache < 1800)) {
        $temp = explode('|', get_option('up_puzo'));
        $puzo['cy'] = $temp[0] == -1 ? 'Нет данных' : $temp[0];
        $puzo['pr'] = $temp[1] == -1 ? 'Нет данных' : $temp[1];
    } else {
        $url = get_site_url();
        $url = str_replace("www.", "", $url);
        $url = str_replace("http://", "", $url);
        $cy = getCy($url);
        $pr = getPageRank($url);
        update_option('up_puzo_cache', time());
        update_option('up_puzo', $cy.'|'.$pr);
        $puzo['cy'] = $cy == -1 ? 'Нет данных' : $cy;
        $puzo['pr'] = $pr == -1 ? 'Нет данных' : $pr;
    }
    return $puzo;
}

function getCy($url) {
    $ci_url = "http://bar-navig.yandex.ru/u?ver=2&show=32&url=http://".$url."/";
    if (ini_get('allow_url_fopen') == 1) {
        $ci_data = implode("", file("$ci_url")); 
    } else {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $ci_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        $ci_data = curl_exec($curl);
        curl_close($curl);
    }
    preg_match("/value=\"(.\d*)\"/", $ci_data, $ci);
    if ($ci[1] == "") 
        return -1;
    else 
        return $ci[1]; 
}

function getPageRank($q, $host='toolbarqueries.google.com', $context=NULL) {
    $seed = "Mining PageRank is AGAINST GOOGLE'S TERMS OF SERVICE. Yes, I'm talking to you, scammer.";
    $result = 0x01020345;
    $len = strlen($q);
    for ($i=0; $i<$len; $i++) {
        $result ^= ord($seed{$i%strlen($seed)}) ^ ord($q{$i});
        $result = (($result >> 23) & 0x1ff) | $result << 9;
    }
    if (PHP_INT_MAX != 2147483647) { $result = -(~($result & 0xFFFFFFFF) + 1); }
    $ch=sprintf('8%x', $result);
    $url='http://%s/tbr?client=navclient-auto&ch=%s&features=Rank&q=info:%s';
    $url=sprintf($url,$host,$ch,$q);
    if (ini_get('allow_url_fopen') == 1) {
        @$pr=file_get_contents($url,false,$context);
    } else {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        @$pr = curl_exec($curl);
        curl_close($curl);
    }
    return $pr ? substr(strrchr($pr, ':'), 1) : -1;
}

add_action('wp_dashboard_setup', 'add_up_view_stat' );
add_action('admin_menu', 'add_up_menu_item');
wp_enqueue_style('updatestat', plugins_url('update-stat').'/updatestat.css', array(), '1.2' );