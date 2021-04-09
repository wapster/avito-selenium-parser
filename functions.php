<?php

namespace Facebook\WebDriver;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Exception;
use \PDO;

function debug($str){
    echo "<pre>";
    print_r($str);
    echo "</pre>";
}

// текущая директория на сервере
define( "CURRENT_DIR", dirname(__FILE__) .'/' );
// Логируем действия
function loginza( $info ) {
    date_default_timezone_set('Asia/Yekaterinburg');
    $date = date( 'd.m.Y H:i:s' ) . ' - ';
    $file = CURRENT_DIR . 'log.txt';
    file_put_contents( $file, $date . $info . PHP_EOL, FILE_APPEND);
}


function open_connect_db() {
    // Подключаемся к базе
    try {
        $db = new PDO( 'mysql:host=localhost;dbname=avito', 'root', '' );
        return $db;
        loginza("Подключение к БД успешно");
    } catch (PDOException $e) {
        print "Error!: " . $e->getMessage();
        loginza("Ошибка подключения к БД: " . $e->getMessage());
        die();
    }
}

// закрываем соединение с БД
function close_connect_db() {
    $stmt = NULL;
    $db = NULL;
    loginza("Соединение с БД закрыто");
}


function get_driver() {
    $host = 'http://localhost:4444';
    $options = new ChromeOptions();
    $options->addArguments(array(
        '–disable-extensions',
        // 'start-maximized',
        'disable-popup-blocking',
        'test-type'
        ));

    $devices = ['iPhone 6' /*, 'Nexus 5', 'Nexus 6', 'Nexus 7', 'Nokia N9'*/];
    $random_device = $devices[array_rand($devices)];
    $mobile_emulation = [ 'deviceName' => $random_device ];
    $options->setExperimentalOption('mobileEmulation', $mobile_emulation);
    $caps = DesiredCapabilities::chrome();
    $caps->setCapability(ChromeOptions::CAPABILITY, $options);
    $driver = RemoteWebDriver::create($host, $caps, 90000, 90000);

    return $driver;
}



function get_random_account($accounts_list) {
    $i = array_rand($accounts_list);
    $random_account = $accounts_list[$i];
    $login = key($accounts_list[$i]);
    $password = $random_account["$login"];

    $account['login'] = $login;
    $account['password'] = $password;

    return $account;
}


// Авторизация в аккаунте
function authorization($driver, $login, $password) {

    // открываем сайта
    try {
        $driver->get('https://m.avito.ru/kurgan#login');
    } catch (Exception\WebDriverException $e) {
        loginza("ОШИБКА при открытии сайта.");
        loginza($e);
        close_browser($driver);
    }
    
    // ждем, пока прогрузится страница
    try {
        $driver->wait(10, 2000)->until( WebDriverExpectedCondition::titleContains('Авито') );
        sleep(1);
    } catch (Exception\WebDriverException $e) {
        loginza("Не удается загрузить сайт.");
        loginza($e);
        close_browser($driver);
    }

    // клик для показа формы авторизации
    try {   
        $driver->findElement(WebDriverBy::xpath("//*[@id='modal']/div/div/div/div[2]"))->click();
        sleep(1);
    } catch (Exception\WebDriverException $e) {
        loginza("Не удается найти форму авторизации.");
        loginza($e);
        close_browser($driver);
    }

    // Заполняем поле авторизации - ЛОГИН
    try {
        $loginInput = $driver->findElement(WebDriverBy::name("login"))->click();
        $loginInput->sendKeys($login);
        sleep(1);
    } catch (Exception\WebDriverException $e) {
        loginza("Не удается заполнить поле авторизации: ЛОГИН");
        loginza($e);
        close_browser($driver);
    }
        
    // Заполняем поле авторизации - ПАРОЛЬ
    try {
        $passwordInput = $driver->findElement(WebDriverBy::name("password"));
        $passwordInput->sendKeys($password);
        sleep(1);
    } catch (Exception\WebDriverException $e) {
        loginza("Не удается заполнить поле авторизации: ПАРОЛЬ");
        loginza($e);
        close_browser($driver);
    }
    
    // Ищем и кликаем конопку Войти
    try {
        $submitButton = $driver->findElement(WebDriverBy::className("_2vOk-"));
        $submitButton->click();
        sleep(3);
    } catch (Exception\WebDriverException $e) {
        loginza("Не удается найти и кликнуть кнопку ВОЙТИ");
        loginza($e);
        close_browser($driver);
    }

    // Проверяем авторизацию
    try {
        // успешная авторизация
    } catch(Exception\WebDriverException $e) {
        loginza("ОШИБКА Авторизации. Логин: " . $login);
        loginza("Проверить правильность Логина и\или Пароля");
        loginza($e);
        close_browser($driver);
    }

    loginza("Авторизация прошла успешно - " . $login);
}


// закрываем браузер
function close_browser($driver) {
    $driver->quit();
}



// получаем все ссылки с главной страницы
// return array()
function get_links_from_index_page($driver) {
    $urls = $driver->findElements(WebDriverBy::className("_2g1Tz"));

    for ($i = 0; $i<count($urls); $i++) {
        $links[] = $urls[$i]->findElement(WebDriverBy::tagName("a"))->getAttribute("href");
    }

    $urls = [];
    // Получаем все ссылки из нужного региона(/kurgan/)
    foreach($links as $link) {
        $pos = strpos($link, '/kurgan/');
        if ($pos === false) {
            // debug('');
        } else {
            $urls[] = $link;
        }
    }

    return $urls;
}

// получаем ссылку на профиль пользователя из его объявления
function get_profile_url ($url, $driver) {
    try {
        // открываем страницу, прокручиваем в самый низ
        // ждем загрузки элементов
        // паузы, чтобы не попасть на капчу
        $driver->get($url);
        sleep( rand(3,6) );
        $driver->executeScript("window.scrollTo(0, document.body.scrollHeight)");
        sleep( rand(3,6) );
        $profile_url = $driver->findElement(WebDriverBy::className("_2xsuC"))->getAttribute("href");
        sleep( rand(3,6) );

        $profile_url = mb_stristr($profile_url, '/user/');
        if ($profile_url !== false) {
            $profile_url = explode('?', $profile_url);
            $profile_url = 'https://m.avito.ru' . $profile_url[0];
            $profile = explode('?', $profile_url);
            $profile_id = $profile[0];
            return $profile_url;
        }
    } catch (Exception\WebDriverException $e) {
        loginza("ОШИБКА получения ссылки на профиль пользователя");
        loginza("URL: " . $url);
        loginza($e);
        // close_browser($driver);
        // $profile_url = 'Не удалось получить ссылку на профиль пользователя';
        $profile_url = '';
        return $profile_url;
    }

}

// получить id профиля из url
// https://m.avito.ru/user/9fff239ba61d3f64dbee90a22d02fe90/profile
// 9fff239ba61d3f64dbee90a22d02fe90
function get_profile_id ($profile_url) {
    $profile = explode('/', $profile_url);
    $profile_id = $profile[4];
    return $profile_id;
}


// список профилей в БД
function get_profiles_in_db() {
    $db = open_connect_db();
    $profiles_in_db = $db->query("SELECT url FROM `profiles`")->fetchAll(PDO::FETCH_COLUMN);
    return $profiles_in_db;
}

// проверяем, если ли профиль в БД
function is_profile_in_db ($profile_url) {
    $db = open_connect_db();
    $profiles_in_db = $db->query("SELECT url FROM `profiles`")->fetchAll(PDO::FETCH_COLUMN);

    $profile_url = [$profile_url]; // делаем массив для сравнения
    var_dump($profile_url);
    debug("");

    $profile_for_add_in_db = array_values(array_diff($profile_url, $profiles_in_db));
    
    var_dump($profile_for_add_in_db);
    debug("");

    $counter = count($profile_for_add_in_db);
    if ($counter > 0) {
        return true;   // такой профиль уже есть в БД
    } else return false; // профиля нет в БД
}



// запись Профиля в БД
function profile_insert_to_db ($profile_id, $profile_url) {
    
    // PDO
    $data = []; // массив, в к-ый будем добавлять инф-ию
    $data = [
        'profile_id'  => $profile_id,
        'profile_url' => $profile_url
    ];

    $sql = "INSERT INTO `profiles` (profile_id, url) VALUES (:profile_id, :profile_url)";
    try {
        $db = open_connect_db();
        $stmt= $db->prepare($sql);
        $stmt->execute($data);
        loginza("Профиль " . $profile_id . " записан в БД");
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
        loginza("ОШИБКА записи профиля в БД. Профиль " . $profile_id);
        loginza($e);
    }
}

// кол-во АКТИВНЫХ объявлений у продавца
function get_count_active_items ($profile_url, $driver) {
    try {
        $driver->get($profile_url);
        $driver->wait(10, 2000)->until( WebDriverExpectedCondition::titleContains('Профиль пользователя') );
        sleep(3);
        $element = $driver->findElement(WebDriverBy::className("Nh2WO"))->findElements(WebDriverBy::tagName("div"));
        $counter = $element[0]->getText();
        $counter = filter_var($counter, FILTER_SANITIZE_NUMBER_INT);
        return (int)$counter;
    } catch (Exception\WebDriverException $e) {
        loginza("ОШИБКА: получение кол-ва активных объявлений продавца");
        loginza($e);
    }
}

// список url активных объявлений у продавца
function get_list_active_items($profile_url, $count_active_items, $driver) {
    try {
        $driver->get($profile_url);
        $driver->wait(10, 2000)->until( WebDriverExpectedCondition::titleContains('Профиль пользователя') );
        sleep(3);

        $w = intdiv($count_active_items, 8) + 1; //кол-во скроллов вниз с учетом кол-ва объявлений продавца
        $i = 0;
        while ( $i < $w ) {
            $driver->executeScript("window.scrollTo(0, document.body.scrollHeight)");
            sleep(4);
            $i++;
        }
        // получаем все ссылки на объявления
        $elements = $driver->findElements(WebDriverBy::className("_2g1Tz"));
        $list_active_items = [];
        foreach ($elements as $element) {
            $list_active_items[] = $element->findElement(WebDriverBy::tagName("div"))->findElement(WebDriverBy::tagName("a"))->getAttribute("href");
        }

        return (int)count($list_active_items);
    } catch (Exception\WebDriverException $e) {
        loginza("ОШИБКА: получение кол-ва активных объявлений продавца");
        loginza($e);
    }

    
}