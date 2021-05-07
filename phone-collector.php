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

function account_to_click() {
    try {
        $db = open_connect_db();
        $sql = "SELECT * FROM `accounts` WHERE `id` = 8";
        $accounts = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        // выбираем аккаунтЫ (их может быть несколько)
        $accounts_to_click = [];
        foreach ($accounts as $account) {
            $now = date('Y-m-d H:i:s');
            $last_click_time = $account['date_click'];
            $hour_diff = round((strtotime($now) - strtotime($last_click_time))/3600, 1);
            if ($hour_diff > ACCOUNT_TIME_CLICK) {
                $accounts_to_click[] = $account;
            } else {
                loginza("Для аккаунта " . $account['login'] . " время клика на кнопку 'Позвонить' еще не настало");
            }
        }
        $account = [];
        $counter = count($accounts_to_click);
        if ($counter > 0) {
            $index = rand(0, $counter - 1);
            $account = $accounts_to_click[$index];
            loginza("Из БД получен АККАУНТ для парсинга телефонов");
        }
        return $account;
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
        loginza("Ошибка при поиске аккаунтов в БД");
        return $account = [];
    }
}


// выбираем аккаунт с которого будем кликать телефон
// дата прошлого клика > ACCOUNT_TIME_CLICK
$account = account_to_click();

// авторизуемся в аккаунте
$login = $account['login'];
$password = $account['password'];
$device = $account['device'];
$driver = get_driver($device);
authorization($driver, $login, $password);


// выбираем профиль продавца, у которого будет кликать телефон
$profile_url = array_rand(array_flip(get_profiles_in_db()));
// $profile_url = 'https://m.avito.ru/user/d1b8a332481d00650cc6c6ebed4df15e/profile';

$profile_id = get_profile_id($profile_url);

// $profile_id = 'ff06a41414e76cc0692d91a9694dbe19';
// $profile_id = 'f4634fe8dda34164e309322980cbdb43';


// получаем кол-во телефонов продавца в базе данных
$count_phones_for_profile = get_count_phones_for_profile($profile_id);


// добавляем запись в таблицу с телефонами
if ($count_phones_for_profile == 0) {
    $phone = get_phone($profile_url, $driver);
    if ($phone !== '') {
        set_phone($profile_id, $phone);
        update_date_click($login);
    }
}

// если к профилю уже "привязаны" телефоны
if ($count_phones_for_profile > 0) {

    // получаем дату последнего парсинга теефона
    $date_scrape = get_date_scrape_phone($profile_id);
    $now = date('Y-m-d');

    // высчитываем разницу м\у датами в часах
    if (strtotime($now) > strtotime($date_scrape)) {
        $days_diff = round((strtotime($now) - strtotime($date_scrape))/86400);

        // время парсинга телефона подошло
        if ($days_diff > PROFILE_TIME_CLICK) {
            // добавляем телефон
            // если ранее этого телефона не было у продавца

            // далее вызов функции для считывания телефона
            $phone = get_phone($profile_url, $driver);

            if ($phone == '') {
                loginza("ОШИБКА: Не удалось получить номер телефона продавца");
                close_connect_db();
                close_browser($driver);
                exit;
            }

            $new_phone_seller = [$phone]; // телефон, к-ый собираемся добавлять

            // обновляем дату клика по кнопке 'Позвонить'
            // для конкретного аккаунта (login)
            update_date_click($login);

            $phones_in_db = get_all_phones($profile_id);
            $phones_in_db = array_values(array_unique($phones_in_db));

            // находим тот, к-го еще нет в БД
            $phones_for_add_in_db = array_values(array_diff($new_phone_seller, $phones_in_db));

            if ( count($phones_for_add_in_db) > 0 ) {
                // добавляем телефон
                set_phone($profile_id, $phones_for_add_in_db[0]);
            } else {
                // обновляем телефон, если он был у продавца
                // но ставим свежую дату, т.к. проверили телефон только что
                $id = get_id_last_check_phone($profile_id, $phone);
                update_date_scrape_to_phone($id);
            }
        } else {
            loginza("Для аккаунта " . $login . " время парсинга телефона еще не настало");
            
            close_connect_db();
            close_browser($driver);
            exit;
        }

    } else {
        loginza("ОШИБКА сравнения даты: " . $profile_id);
        loginza("дата: " . $date_scrape . " и " . $now);
        
        close_connect_db();
        close_browser($driver);
        exit;
    }



}


close_connect_db();
close_browser($driver);

empty_line();