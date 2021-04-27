<?php
namespace Facebook\WebDriver;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Exception;
use \PDO;

require_once('vendor/autoload.php');
require_once('functions.php');
require_once('settings.php');

empty_line();

// Авторизация
$account = get_random_account();

$login = $account['login'];
$password = $account['password'];
$device = $account['device'];
$driver = get_driver($device);
authorization($driver, $login, $password);


// выбираем несколько случайных профилей
// $profiles = array_rand(array_flip(get_profiles_in_db()), COUNT_RANDOM_PROFILES);

// или один
$profiles[] = array_rand(array_flip(get_profiles_in_db()));

loginza("ITEMS");
loginza("Старт парсинга объявлений из профилей пользователей");

foreach ($profiles as $profile_hash) {
    loginza("* " . $profile_hash);
}

foreach ($profiles as $profile_url) {
    loginza("профиль: " . $profile_url);
    // кол-во и список активных объявлений продавца
    $count_active_items = get_count_active_items($profile_url, $driver);
    if ($count_active_items > 0):
        $list_active_items = get_list_active_items($profile_url, $count_active_items, $driver);

        // список объявлений пользователя в базе данных
        $profile_id = get_profile_id($profile_url);
        $list_items_in_db = get_list_items_in_db($profile_id);
        
        // сравниваем массивы и возвращаем только те объявления,
        // которых нет в базе данных
        if ($list_items_in_db !== 'error') {
            $items_for_add_in_db = array_values(array_diff($list_active_items, $list_items_in_db));

            // если есть что добавить -> добавляем
            if (count($items_for_add_in_db) > 0) {
                foreach ($items_for_add_in_db as $item) {
                    // Парсим информацию со страницы объявления
                    $data_item = get_item_info ($item, $profile_id, $driver);
                    
                    if (count($data_item) > 0) {
                        // записываем инф-ию об объявлении в базу
                        item_insert_in_db($data_item);
                    }
                }
            }
        }
    endif;
    loginza("Парсинг объявлений продавца " . $profile_url . " завершен");

}
close_connect_db();
close_browser($driver);