<?php

namespace Itb\Parser\ParserUrl;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\Relations\OneToMany;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Loader;
use Bitrix\Sale;
use Goutte\Client;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\SystemException;

class Parser
{
    private int $IdBlock;
    private Client $client;
    private array $classFind;
    private int $maxPages;
    private string $imgPath;
    private string $urlParser;
    private string $domainMain;
    private ?int $getPage;
    private array $data;
    private array $fields = [];
    private string $active;
    private string $imgAtr;

    public function __construct()
    {
        $this->active = \Bitrix\Main\Config\Option::get("itb.parser", "module_active", "", false);
        $this->data = json_decode(\Bitrix\Main\Config\Option::get("itb.parser", "all_select", "", false), true);
        $this->maxPages = (int)\Bitrix\Main\Config\Option::get("itb.parser", "max_page", "", false);
        $this->imgPath = \Bitrix\Main\Config\Option::get("itb.parser", "img_path", "", false);
        $this->getPage = (int)\Bitrix\Main\Config\Option::get("itb.parser", "get_page", "", false);
        $this->urlParser = \Bitrix\Main\Config\Option::get("itb.parser", "url_parser", "", false);
        $this->domainMain = \Bitrix\Main\Config\Option::get("itb.parser", "domain_main", "", false);
        $this->IdBlock = (int)\Bitrix\Main\Config\Option::get("itb.parser", "id_block", "", false);
        $this->imgAtr = \Bitrix\Main\Config\Option::get("itb.parser", "img_atr", "", false);
        $this->client = new Client();

    }

    public function parse(): void
    {

        if (empty($this->active) ) {
         echo GetMessage("OPTIONS_MAIN_ACTIVE");
        } else {

            if (is_string($this->data)) {
                $this->classFind = json_decode($this->data, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception(GetMessage('OPTIONS_MAIN_DESCODE_JSON')  . json_last_error_msg());
                }
            } elseif (is_array($this->data)) {
                $this->classFind = $this->data;
            } else {
                throw new \Exception(GetMessage('OPTIONS_MAIN_DESCODE'));
            }
            foreach ($this->classFind as $key => $classFields) {
                $this->fields[] = $key;
            }

            $result = [];
            if(!empty($this->getPage)){
                $page = $this->getPage;
            }else{
                $page = 1;
            }

            $totalBlocks = 0;
            $totalPages = 0;

            do {
                $crawler = $this->client->request('GET', $this->urlParser . '?PAGEN_1=' . $page);
                $sections = $crawler->filter($this->classFind['section']);
                if ($sections->count() === 0) {
                    echo GetMessage("OPTIONS_MAIN_INPUT_FORM") . "<br>";
                    break;
                }
                $totalPages++;
                $sections->each(function ($node) use (&$result, &$totalBlocks) {
                    $item = ['images' => []];

                    foreach ($this->fields as $field) {
                        if ($field === 'images') {
                            if (isset($this->classFind['images'])) {
                                $node->filter($this->classFind['images'])->each(function ($image) use (&$item) {
                                    $imgSrc = $image->attr($this->imgAtr);
                                    if ($imgSrc) {
                                        $item['images'][] = $imgSrc;
                                    } else {
                                        echo Getmessage('OPTIONS_MAIN_IMG_ATR');
                                    }
                                });

                            }
                        } elseif ($field === 'description') {
                            $linkNode = $node->filter($this->classFind['name'])->attr('href');
                            if ($linkNode) {
                                $descriptionCrawler = $this->client->request('GET', $linkNode);
                                $descriptionNode = $descriptionCrawler->filter($this->classFind['description']);
                                if ($descriptionNode->count() > 0) {
                                    $item['description'] = trim($descriptionNode->text());
                                }
                            }
                        } else {
                            $nodeField = $node->filter($this->classFind[$field]);
                            if ($nodeField->count() > 0) {
                                $item[$field] = trim($nodeField->text());
                            }
                        }
                    }

                    if (!empty($item['name'])) {
                        $result[] = $item;
                        $totalBlocks++;
                    } else {
                        echo GetMessage('OPTIONS_MAIN_NAME_PRODUCT');
                    }
                });

                $page++;
                sleep(1);
            } while ($page <= $this->maxPages);

            echo GetMessage('OPTIONS_MAIN_BLOCK_PARSER') . " $totalBlocks\n";
            echo GetMessage('OPTIONS_MAIN_BLOCK_PAGER')." $totalPages\n";
            $this->addItems($result);
        }

    }

    public function addItems(array $items): void
    {
        if (!\CModule::IncludeModule("iblock")) {
            throw new \Exception(GetMessage('OPTIONS_MAIN_IBLOCK'));
        }

        foreach ($items as $item) {
            $code = \CUtil::translit($item['name'], 'ru', ['replace_space' => '-', 'replace_other' => '-']);
            $existingItem = ElementTable::getList([
                'filter' => [
                    'IBLOCK_ID' => $this->IdBlock,
                    'CODE' => $code,
                ],
                'select' => ['ID']
            ])->fetch();

            if ($existingItem) {
                $this->updateItem($existingItem['ID'], $item);
            } else {
                $this->createItem($item, $code);
            }
        }
    }

    private function updateItem(int $elementId, array $item): void
    {
        $el = new \CIBlockElement();
        $fields = [
            "ACTIVE" => "Y",
            "NAME" => $item['name'],
            "DETAIL_TEXT" => $item['description'],
        ];

        $uploadedImageIds = $this->saveImg($item['images']);
        if (!empty($uploadedImageIds)) {
            $fields["PREVIEW_PICTURE"] = $uploadedImageIds[0];
        }

        // Обновляем элемент
        $isUpdated = $el->Update($elementId, $fields);

        if ($isUpdated) {
            $this->addCustomProperties($elementId, $item); // Передаем ID обновленного элемента
            // Логируем успешное обновление элемента
            error_log("Элемент ID $elementId успешно обновлен.");
        } else {
            // Логируем ошибку при обновлении элемента
            error_log(GetMessage('OPTIONS_MAIN_ERROR') . $el->LAST_ERROR);
        }
    }


    private function createItem(array $item, string $code): void
    {
        $el = new \CIBlockElement();
        $fields = [
            "IBLOCK_ID" => $this->IdBlock,
            "NAME" => $item['name'],
            "CODE" => $code,
            "ACTIVE" => "Y",
            "DETAIL_TEXT" => $item['description'],
        ];

        $uploadedImageIds = $this->saveImg($item['images']);
        if (!empty($uploadedImageIds)) {
            $fields["PREVIEW_PICTURE"] = $uploadedImageIds[0];
        }

        // Создаем элемент
        $elementId = $el->Add($fields);

        if ($elementId) {
            $this->addCustomProperties($elementId, $item); // Передаем ID созданного элемента
            // Логируем успешное создание элемента
            error_log("Элемент ID $elementId успешно создан.");
        } else {
            // Логируем ошибку при создании элемента
            error_log(GetMessage('OPTIONS_MAIN_ERROR') . $el->LAST_ERROR);
        }
    }

    private function addCustomProperties(int $elementId, array $item): void
    {
        $properties = [];
        $excludedFields = ['description', 'images', 'name', 'section'];

        foreach ($this->fields as $field) {
            if (!in_array($field, $excludedFields)) {
                $properties[$field] = $item[$field] ?? null;
            }
        }

        foreach ($properties as $code => $value) {
            if ($value !== null) {
                $propertyId = $this->getPropertyId($code);
                if ($propertyId) {
                    \CIBlockElement::SetPropertyValueCode($elementId, $code, $value);
                } else {
                    $this->createProperty($code, $value);
                }
            }
        }
    }

    private function getPropertyId(string $code): ?int
    {
        $property = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $this->IdBlock, 'CODE' => $code])->Fetch();
        return $property ? $property['ID'] : null;
    }

    private function createProperty(string $code, $value): void
    {
        $iblockProperty = new \CIBlockProperty();
        $arFields = [
            "NAME" => ucfirst($code),
            "ACTIVE" => "Y",
            "CODE" => $code,
            "PROPERTY_TYPE" => "S",
            "IBLOCK_ID" => $this->IdBlock,
        ];

        $propertyId = $iblockProperty->Add($arFields);
        if ($propertyId) {
            \CIBlockElement::SetPropertyValueCode($propertyId, $code, $value);
        }
    }

    private function saveImg(array $images, ?string $subfolder = null): array
    {
        if ($subfolder === null) {
            $subfolder = $this->imgPath;
        }

        if (empty($images)) {
            return [];
        }

        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . trim($subfolder, '/');

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploadedImages = [];
        $imagPath = [];
        foreach ($images as $image) {
            $imageUrl = $this->domainMain . $image;
            $imageData = $this->getImageData($imageUrl);

            if ($imageData === false) {
                error_log(GetMessage('OPTIONS_MAIN_IMG_DATA'). " $imageUrl");
                continue;
            }

            $filePath = $uploadDir . '/' . basename($image);
            if (file_put_contents($filePath, $imageData) === false) {
                error_log(GetMessage('ERROR_SAVE_IMAGE')."$filePath");
                continue;
            }

            $fileArray = \CFile::MakeFileArray($filePath);
            $uploadedId = \CFile::SaveFile($fileArray, "upload/" . $subfolder);
            $uploadedImages[] = $uploadedId;

            if ($uploadedId === false) {
                error_log(GetMessage('OPTIONS_MAIN_IMG_UPLOAD')." $filePath");
            } else {
                $imagPath[] = \CFile::MakeFileArray($uploadedId);
            }
        }

        return $imagPath;
    }

    private function getImageData(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $data = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log('cURL ошибка: ' . curl_error($ch));
            $data = null;
        }

        curl_close($ch);
        return $data;
    }
}
