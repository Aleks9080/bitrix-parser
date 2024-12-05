<?
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
Loc::loadMessages(__FILE__);
$request = HttpApplication::getInstance()->getContext()->getRequest();
$module_id = htmlspecialcharsbx($request["mid"] != "" ? $request["mid"] : $request["id"]);
$POST_RIGHT = $APPLICATION->GetGroupRight($module_id);
if ($POST_RIGHT < "S") {
    $APPLICATION->AuthForm(Loc::getMessage("ACCESS_DENIED"));
}
Loader::includeModule($module_id);
$aTabs = array(
    array(
        "DIV" => "edit1",
        "TAB" => GetMessage("OPTIONS_MAIN_TAB_TITLE"),
        "TITLE" => GetMessage("OPTIONS_MAIN_TAB_NAME"),
        "OPTIONS" => array(
            GetMessage("OPTIONS_MAIN_SECTION_NAME"),
            array(
                "module_active",
                GetMessage("OPTIONS_MAIN_SECTION_ACTIVE"),
                "N",
                array("checkbox"),
            ),
            array(
                "domain_main",
                GetMessage("OPTIONS_PERMISSION_DOMEN_PARSER"),
                "",
                array(
                    "text",
                    10,
                    50
                )
            ),
            
            GetMessage("OPTIONS_PERMISSION_B24_SECTION_NAME"),
            array(
                "img_path",
                GetMessage("OPTIONS_PERMISSION_IMG_PATH"),
                "",
                array(
                    "text",
                    10,
                    50
                )
            ),
            array(
                "max_page",
                GetMessage("OPTIONS_PERMISSION_MAX_PAGE"),
                "",
                array(
                    "text",
                    10,
                    50
                )
            ),
            array(
                "get_page",
                GetMessage("OPTIONS_PERMISSION_PAGE"),
                "",
                array(
                    "text",
                    10,
                    50
                )
            ),
            array(
                "img_atr",
                GetMessage("OPTIONS_PARAMS_IMG_ATR"),
                "",
                array(
                    "text",
                    10,
                    50
                )
            ),
            array(
                "id_block",
                GetMessage("OPTIONS_PARAMS_FIELD_BLOCK_ID"),
                "",
                array(
                    "text",
                    10,
                    50
                )
            ),
            array(
                "url_parser",
                GetMessage("OPTIONS_PARAMS_URL_PARSER"),
                "",
                array(
                    "text",
                    10,
                    50
                )
            ),
            GetMessage("OPTIONS_PARAMS_URL_ITEMS"),
            array(
                "all_select",
                GetMessage("OPTIONS_PARAMS_URL_ITEMS_EXAMPLE"),
                "",
                array(
                    "textarea",
                    10,
                    50
                )
            ),
         
        )
    ),
    array(
        "DIV" => "edit2",
        "TAB" => Loc::getMessage("MAIN_TAB_RIGHTS"),
        "TITLE" => Loc::getMessage("MAIN_TAB_TITLE_RIGHTS")
    )
);
if ($request->isPost() && check_bitrix_sessid()) {
    foreach ($aTabs as $aTab) {
        foreach ($aTab["OPTIONS"] as $arOption) {
            if (!is_array($arOption)) {
                continue;
            }
            if ($request["Update"]) {
                $optionValue = $request->getPost($arOption[0]);
                if ($arOption[0] == "parser_checkbox") {
                    if ($optionValue == "") {
                        $optionValue = "N";
                    }
                }
                Option::set($module_id, $arOption[0], is_array($optionValue) ? implode(",", $optionValue) : $optionValue);
            }
            if ($request["default"]) {
                Option::set($module_id, $arOption[0], $arOption[2]);
            }
        }
    }
}
$tabControl = new CAdminTabControl(
    "tabControl",
    $aTabs
);
$tabControl->Begin();
?>
<form action="<? echo ($APPLICATION->GetCurPage()); ?>?mid=<? echo ($module_id); ?>&lang=<? echo (LANG); ?>"
    method="post">
    <? foreach ($aTabs as $aTab) {
        if ($aTab["OPTIONS"]) {
            $tabControl->BeginNextTab();
            __AdmSettingsDrawList($module_id, $aTab["OPTIONS"]);
        }
    }
    $tabControl->BeginNextTab();
    require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/admin/group_rights.php";
    $tabControl->Buttons();
    echo (bitrix_sessid_post());
    ?>
    <input class="adm-btn-save" type="submit" name="Update" value="Применить" />
    <input type="submit" name="default" value="По умолчанию" />
</form>
<?
if ($request["Update"]) {
    LocalRedirect('/bitrix/admin/parser.php');
}
?>
<?
$tabControl->End();