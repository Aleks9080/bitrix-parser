<?

use Bitrix\Main\Localization\Loc;

use Bitrix\Main\ModuleManager;

use Bitrix\Main\Config\Option;

use Bitrix\Main\Application;

use \Bitrix\Main\Entity\Base;

use \Bitrix\Main\Loader;

use \Bitrix\Main\EventManager;

use Itb\parser\Tables\ParamsTable;
use Itb\parser\Tables\WebhookTable;

Loc::loadMessages(__FILE__);

class itb_parser extends CModule
{
    public $MODULE_ID;
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;
    public $SHOW_SUPER_ADMIN_GROUP_RIGHTS;
    public $MODULE_GROUP_RIGHTS;

    function __construct()
    {
        $arModuleVersion = array();
        include_once(__DIR__ . '/version.php');

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_ID = "itb.parser";
        $this->MODULE_NAME = GetMessage("MODULE_NAME");
        $this->MODULE_DESCRIPTION = GetMessage("MODULE_DESCRIPTION");
        $this->PARTNER_NAME = GetMessage("PARTNER_NAME");
        $this->PARTNER_URI = "https://itb-company.com/";
        $this->SHOW_SUPER_ADMIN_GROUP_RIGHTS = 'Y';
        $this->MODULE_GROUP_RIGHTS = 'Y';

        Loader::includeModule($this->MODULE_ID);
    }

    function DoInstall()
    {
        ModuleManager::RegisterModule($this->MODULE_ID);
        Loader::includeModule($this->MODULE_ID);

        $this->InstallFiles();

        return true;
    }

    function DoUninstall()
    {

        Option::delete($this->MODULE_ID);
        ModuleManager::UnRegisterModule($this->MODULE_ID);


        return true;
    }

    function InstallFiles()
    {
        CopyDirFiles(
            __DIR__ . '/../admin/parser.php',
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin/parser.php",
            true,
            true
        );
    }

}
