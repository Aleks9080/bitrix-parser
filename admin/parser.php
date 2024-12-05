<?require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
// пространство имен для автозагрузки модулей
use \Bitrix\Main\Loader;
// получим права доступа текущего пользователя на модуль
$POST_RIGHT = $APPLICATION->GetGroupRight("itb.parser");

if ($POST_RIGHT == "D") {
    $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));
}
// вывод заголовка
$APPLICATION->SetTitle("Страница модуля парсинга");
// подключаем языковые файлы
IncludeModuleLangFile(__FILE__);
$aTabs = array(
    array(
        // название вкладки в табах
        "TAB" => "Результат",
        // заголовок и всплывающее сообщение вкладки
        "TITLE" => "Страница модуля парсинга"
    )
);
// отрисовываем форму, для этого создаем новый экземпляр класса CAdminTabControl, куда и передаём массив с настройками
$tabControl = new CAdminTabControl(
    "tabControl",
    $aTabs
);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

echo bitrix_sessid_post();
$tabControl->Begin();
$tabControl->BeginNextTab();
?>
<?
if (CModule::IncludeModule('itb.parser')) {
    $parser = new Itb\Parser\ParserUrl\Parser(); // Полное имя класса с пространством имен
    $parser->parse();

    echo "</br> Парсинг завершен.";
}
?>
<?

// завершаем интерфейс закладки
$tabControl->End();
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
?>