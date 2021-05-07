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

// добавляем пустую строку в лог-файл
function empty_line() {
    $file = 'log.txt';
    file_put_contents( $file, PHP_EOL, FILE_APPEND);
    file_put_contents( $file, '', FILE_APPEND);
    file_put_contents( $file, PHP_EOL, FILE_APPEND);
    // file_put_contents( $file, '', FILE_APPEND);
}


function open_connect_db() {
    // Подключаемся к базе
    try {
        $db = new PDO( 'mysql:host=localhost;dbname=avito', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION] );
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


function get_driver($device) {
    $host = 'http://localhost:4444';
    $options = new ChromeOptions();
    $options->addArguments( [
        '–disable-extensions',
        'disable-popup-blocking',
        // 'test-type'
        // 'start-maximized',
        ] );

    $mobile_emulation = [ 'deviceName' => $device ];
    $options->setExperimentalOption('mobileEmulation', $mobile_emulation);
    $caps = DesiredCapabilities::chrome();
    $caps->setCapability(ChromeOptions::CAPABILITY, $options);
    $driver = RemoteWebDriver::create($host, $caps, 90000, 90000);

    return $driver;
}


// получить аккаунт
function get_random_account() {
    try {
        $db = open_connect_db();
        $sql = "SELECT * FROM `accounts`";
        $accounts = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $counter = count($accounts);
        $index = rand(0, $counter - 1);
        $account = $accounts[$index];
        return $account;
        loginza("Из БД получен список аккаунтов");
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
        loginza("Ошибка при поиске объявлений в БД");
        return $account = [];
    }
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
        sleep(5);
    } catch (Exception\WebDriverException $e) {
        loginza("Не удается загрузить сайт.");
        loginza($e);
        close_browser($driver);
    }

    // клик для показа формы авторизации
    try {   
        $driver->findElement(WebDriverBy::xpath("//*[@id='modal']/div/div/div/div[2]"))->click();
        sleep(2);
    } catch (Exception\WebDriverException $e) {
        loginza("Не удается найти форму авторизации.");
        loginza($e);
        close_browser($driver);
    }

    // Заполняем поле авторизации - ЛОГИН
    try {
        $loginInput = $driver->findElement(WebDriverBy::name("login"))->click();
        $loginInput->sendKeys($login);
        sleep(2);
    } catch (Exception\WebDriverException $e) {
        loginza("Не удается заполнить поле авторизации: ЛОГИН");
        loginza($e);
        close_browser($driver);
    }
        
    // Заполняем поле авторизации - ПАРОЛЬ
    try {
        $passwordInput = $driver->findElement(WebDriverBy::name("password"));
        $passwordInput->sendKeys($password);
        sleep(2);
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
    $driver->wait(10, 1000)->until( WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::linkText("Реклама на сайте")));

    $i = 0;
    $w = 1;
    while ( $i < $w ) {
        $driver->executeScript("window.scrollTo(0, document.body.scrollHeight)");
        sleep(5);
        $i++;
    }

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
    loginza("Получены ссылки на объявления");
}

// получаем ссылку на профиль пользователя из его объявления
function get_profile_url ($url, $driver) {
    try {
        // открываем страницу, прокручиваем в самый низ
        // ждем загрузки элементов
        // паузы, чтобы не попасть на капчу
        $driver->get($url);
        // $driver->wait(10, 5000)->until( WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::linkText("Реклама на сайте")));
        sleep( rand(3,6) );
        $driver->executeScript("window.scrollTo(0, document.body.scrollHeight)");
        sleep( rand(3,6) );
        $profile_url = $driver->findElement(WebDriverBy::className("_2xsuC"))->getAttribute("href");
        sleep( rand(2,3) );

        $profile_url = mb_stristr($profile_url, '/user/');
        if ($profile_url !== false) {
            $profile_url = explode('?', $profile_url);
            $profile_url = 'https://m.avito.ru' . $profile_url[0];
            $profile = explode('?', $profile_url);
            $profile_id = $profile[0];
            $lenght = strlen(get_profile_id($profile_url));

            // отсекаем профили, длина id которых 64 символа
            // такие профили - подменные, в объявлениях по продаже авто и недвижимости
            if ($lenght < 40) {
                loginza("ссылка на профиль получена " . $profile_url);
                return $profile_url;
            } else return $profile_url = '';
        }
    } catch (Exception\WebDriverException $e) {
        loginza("ОШИБКА получения ссылки на профиль пользователя");
        loginza("URL объявления: " . $url);
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
    // loginza("id профиля: " . $profile_id);
    return $profile_id;
}


// список профилей в БД
function get_profiles_in_db() {
    $db = open_connect_db();
    $profiles_in_db = $db->query("SELECT url FROM `profiles`")->fetchAll(PDO::FETCH_COLUMN);
    loginza("Из БД получены профили продавцов");
    return $profiles_in_db;
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
        // loginza("Профиль " . $profile_id . " записан в БД");
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
        $driver->wait(10, 2000)->until( WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::className("Nh2WO")));
        // $driver->wait(10, 1000)->until( WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::linkText("Реклама на сайте")));
        sleep(10);
        $element = $driver->findElement(WebDriverBy::className("Nh2WO"))->findElements(WebDriverBy::tagName("div"));
        $counter = $element[0]->getText();
        $counter = filter_var($counter, FILTER_SANITIZE_NUMBER_INT);
        loginza("Кол-во активных объявлений продавца: " . $counter);
        return (int)$counter;
    } catch (Exception\WebDriverException $e) {
        loginza("ОШИБКА: получение кол-ва активных объявлений продавца");
        loginza($e);
        return (int)$counter = 0;
    }
}

// список url активных объявлений у продавца
function get_list_active_items($profile_url, $count_active_items, $driver) {
    try {
        $driver->get($profile_url);
        $driver->wait(10, 2000)->until( WebDriverExpectedCondition::titleContains('Профиль пользователя') );
        sleep(rand(5, 10));

        // if ($count_active_items > 128) {
            // $w = 16; // кол-во скроллов вниз, в мобильной версии показывается только 128 объявлений ???
        // }
        
        if ($count_active_items < 8) {
            $w = 0;
        }
        else {
            $w = intdiv($count_active_items, 8) + 1; //кол-во скроллов вниз с учетом кол-ва объявлений продавца
        }
        loginza("Кол-во скроллов: " . $w);
        $i = 1;
        while ( $i < $w ) {
            $driver->executeScript("window.scrollTo(0, document.body.scrollHeight)");
            sleep(5);
            $i++;
        }
        // получаем все ссылки на объявления
        $elements = $driver->findElements(WebDriverBy::className("_2g1Tz"));
        $list_active_items = [];
        foreach ($elements as $element) {
            $list_active_items[] = $element->findElement(WebDriverBy::tagName("div"))->findElement(WebDriverBy::tagName("a"))->getAttribute("href");
        }

        $urls = [];
        // Получаем все ссылки из нужного региона(/kurgan/)
        foreach($list_active_items as $link) {
            $pos = strpos($link, CITY);
            if ($pos === false) {
                // debug('');
            } else {
                $urls[] = $link;
            }
        }

        // loginza("Список URL активных объявлений сформирован");
        return $urls;
    } catch (Exception\WebDriverException $e) {
        loginza("ОШИБКА: получение списка активных объявлений продавца");
        loginza($e);
        return $urls = [];
    }

    
}



// Парсим информацию со страницы объявления
function get_item_info ($item, $profile_id, $driver) {
    try {
        // переходим в объявление
        $driver->get($item);
        sleep(rand(5,9));

        // скроллим в самый низ
        // $driver->executeScript("window.scrollTo(0, document.body.scrollHeight)");
        // sleep(rand(2,2));


        // ПАРСИМ ИНФ-ИЮ
        
        // url объявления
        $url = $item;
        
        // id объявления
        $item_id = explode("_", $url);
        $item_id = array_pop($item_id);
        
        // id профиля
        // $profile_id = get_profile_id($profile_url);
        
        // Заголовок объявления
        $title = $driver->findElement(WebDriverBy::tagName("h1"))->getText();

        // Описание объявления
        $description = $driver->findElement(WebDriverBy::xpath("//*[@id='app']/div/div[2]/div/div[3]/div[1]/div/div"))->getText();
        $description = mb_substr($description, 0, 700);
        $description = preg_replace('/[^a-zA-Zа-яА-Я0-9,.*= ]/ui', '', $description);

        // Цена
        $price = $driver->findElement(WebDriverBy::className("_3CnHz"))->getText();

        // Адрес
        $address = $driver->findElement(WebDriverBy::className("_20LXK"))->getText();

        // Имя продавца
        $name = $driver->findElement(WebDriverBy::className("bbujJ"))->getText();

        // дата парсинга объявления
        $date_scrape = date( 'Y-m-d' );

        
        $data['item_id'] = $item_id;
        $data['profile_id'] = $profile_id;
        $data['url'] = $url;
        $data['title'] = $title;
        $data['description'] = $description;
        $data['price'] = $price;
        $data['address'] = $address;
        $data['name'] = $name;
        $data['date_scrape'] = $date_scrape;
        
        // loginza("Страница с объявлением № " . $item_id. " спарсена");
        return $data;
    } catch (Exception\WebDriverException $e) {
        loginza("ОШИБКА при парсинге страницы с объявлением");
        loginza($item);
        loginza($e);
        return $data = [];
    }

}


// Список всех объявлений продавца в базе данных
function get_list_items_in_db($profile_id) {
    try {
        $db = open_connect_db();
        $sql = "SELECT `url` FROM `items` WHERE `profile_id` = '$profile_id'";
        $list_items_in_db = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        return $list_items_in_db;
        loginza("Из БД получен список объявлений пользователя");
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
        loginza("Ошибка при поиске объявлений в БД");
        return $list_items_in_db = 'error';
    }

}


// Список ВСЕХ объявлений в базе данных
function get_url_list_all_items() {
    try {
        $db = open_connect_db();
        $sql = "SELECT `url` FROM `items`";
        $list = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        return $list;
        loginza("Из БД получен список объявлений пользователя");
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
        loginza("Ошибка при поиске объявлений в БД");
        return $list = 'error';
    }

}




// запись инф-ции об объявлении в базу
function item_insert_in_db($data) {
    $sql = "INSERT INTO `items` 
    (item_id, profile_id, url, title, description, price, address, name, date_scrape) 
    VALUES 
    (:item_id, :profile_id, :url, :title, :description, :price, :address, :name, :date_scrape)";

    try {
        $db = open_connect_db();
        $stmt= $db->prepare($sql);
        $stmt->execute($data);
        // loginza("Объявление добавлено в БД. ID - " . $data['item_id']);
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
        loginza("Ошибка при добавлении объявления в БД");
    }
}



// получаем аккаунт для скликивания телефона
// с учетом времени, прошедшего с последнего скликивания
function get_account_to_click() {
    try {
        $db = open_connect_db();
        $sql = "SELECT * FROM `accounts`";
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
        }
        loginza("Из БД получен АККАУНТ для парсинга телефонов");
        return $account;
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
        loginza("Ошибка при поиске аккаунтов в БД");
        return $account = [];
    }
}



// ищем записи в таблице с телефонами
// для конкретного профиля
function get_count_phones_for_profile($profile_id) {
    try {
        $db = open_connect_db();
        $sql = "SELECT COUNT(`phone`) FROM `phones` WHERE `profile_id` = '$profile_id'";
        $count = $db->query($sql)->fetch(PDO::FETCH_NUM);
        loginza("Для профиля: " . $profile_id);
        loginza("найдено телефонов: " . (int)$count[0]);
        return (int)$count[0];
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
        loginza("ОШИБКА при подсчете кол-ва записей в таблице `phones`");
        return (int)$count = 0;
    }
}



// добавляем номер телефона продавца в таблицу телефонов
function set_phone ($profile_id, $phone) {
    $date_scrape = date('Y-m-d');

    $data = [];
    $data = [
        'profile_id'  => $profile_id,
        'phone'       => $phone,
        'date_scrape' => $date_scrape
    ];

    $sql = "INSERT INTO `phones` (profile_id, phone, date_scrape) VALUES (:profile_id, :phone, :date_scrape)";
    try {
        $db = open_connect_db();
        $stmt= $db->prepare($sql);
        $stmt->execute($data);
        loginza("Телефон " . $phone . " записан в БД");
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
        loginza("ОШИБКА при записи в таблицу `phones`: " . $phone . " | " . $profile_id);
    }

}


// получаем id записи последнего обновления телефона продавца
function get_id_last_check_phone ($profile_id, $phone) {
    try {
        $db = open_connect_db();
        $sql = "SELECT `id` FROM `phones` WHERE (`profile_id` = '$profile_id' AND `phone` = '$phone' ) ORDER BY `date_scrape` DESC LIMIT 1";
        $id = $db->query($sql)->fetch(PDO::FETCH_COLUMN);
        loginza("Найден ID записи последней проверки телефона у профиля " . $profile_id);
        return (int)$id[0];
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
        loginza("ОШИБКА при поиске ID в таблице `phones`");
        return (int)$id = 0;
    }
}

// обновляем номер телефона продавца в базе данных
function update_date_scrape_to_phone($id) {
    $new_date = date('Y-m-d');
    // $sql = "UPDATE `phones` SET (`date_scrape` = :new_date) WHERE `id` = :id";
    $sql = "UPDATE `phones` SET `date_scrape` = '$new_date' WHERE id = $id";
    try {
        $db = open_connect_db();
        $db->exec($sql);
        
        // $stmt= $db->prepare($sql);
        // $stmt->bindParam(':new_date', $new_date);
        // $stmt->bindParam(':id', $id);
        // $stmt->execute();
        loginza("Запись ID " . $id . " обновлена");
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
        loginza("ОШИБКА при обновлении даты в таблице `phones`: ");
    }
}


// получаем свежую дату парсинга телефона для конкретного профиля продавца
function get_date_scrape_phone ($profile_id) {
    $sql = "SELECT `date_scrape` FROM `phones` WHERE `profile_id` = '$profile_id' ORDER BY `date_scrape` DESC";
    try {
        $db = open_connect_db();
        $date = $db->query($sql)->fetch(PDO::FETCH_NUM);
        loginza("Для профиля: " . $profile_id);
        loginza("последняя дата парсинга телефона: " . $date[0]);
        return $date[0];
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
        loginza("ОШИБКА при получении даты парсинга телефона: " . $profile_id);
    }

}

// получаем все телефоны продавца из базы
function get_all_phones ($profile_id) {
    $sql = "SELECT `phone` FROM `phones` WHERE `profile_id` = '$profile_id'";
    try {
        $db = open_connect_db();
        $phones = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        loginza("Для профиля: " . $profile_id);
        loginza("найдены телефоны");
        return $phones;
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
        loginza("ОШИБКА при получении телефонов продавца " . $profile_id . " из базы данных");
    }
}


// на странице с объявление кликаем
// чтобы получить телефон продавца
function click_to_phone($url, $driver) {
    try {
        $driver->get($url);
        $driver->wait(20, 1000)->until( WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::linkText("Реклама на сайте")));
        sleep(5);

        // $driver->findElement(WebDriverBy::xpath("//*[@id='app']/div/div[2]/div/div[2]/div/div[2]/div/div/div[1]/div/div/div[1]/a/span"))->click();
        $driver->findElement(WebDriverBy::xpath("//*[@id='app']/div/div[2]/div/div[2]/div/div[2]/div/div/div[1]/div/div/div[1]/button/span"))->click();
        $driver->wait(20, 1000)->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::xpath("//*[@id='modal']/div[2]/div/div[1]/span[1]")));
        sleep(5);
        $phone = $driver->findElement(WebDriverBy::xpath("//*[@id='modal']/div[2]/div/div[1]/span[2]"))->getText();
        $phone = str_replace(['+7', ' ', '-'] , '', $phone);
        return $phone;
    } catch (Exception\NoSuchElementException $e) {
        loginza("ОШИБКА получения телефона продавца на странице");
        loginza("URL объявления: " . $url);
        loginza($e);
        return $phone = '';
    }
}

// получаем телефон
function get_phone($profile_url, $driver) {
    $count_active_items = get_count_active_items($profile_url, $driver);
    if ($count_active_items > 0) {
        $list_active_items = get_list_active_items($profile_url, $count_active_items, $driver);
        
        $i = 0;
        foreach ($list_active_items as $url) {
            $i++;
            // получаем телефон из объявления
            $phone = click_to_phone($url, $driver);
            if ($phone !== '') break;
        }
        loginza("Кол-во попыток запроса телефона: " . $i);
        return $phone;
    } else {
        return $phone = '';
    }
}

// обновляем дату скликивания телефона для аккаунта
function update_date_click($login) {
    $sql = "UPDATE `accounts` SET `date_click` = NOW() WHERE `login` = '$login'";
    try {
        $db = open_connect_db();
        $db->exec($sql);
        loginza("Дата клика по кнопке 'Позвонить' обновлена");
        loginza("аккаунт: " . $login);
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
        loginza("ОШИБКА при обновлении даты клика по кнопке 'Позвонить'");
    }
}