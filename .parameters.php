<? if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * @var string $componentPath
 * @var string $componentName
 * @var array $arCurrentValues
 * @global CUserTypeManager $USER_FIELD_MANAGER
 */

use Bitrix\Main\Loader,
	Bitrix\Main\Web\Json,
	Bitrix\Iblock,
	Bitrix\Catalog,
	Bitrix\Currency;
use Bitrix\Main\UserConsent\Internals\AgreementTable;

global $USER_FIELD_MANAGER;

if (!Loader::includeModule('iblock'))
	return;

$catalogIncluded = Loader::includeModule('catalog');
CBitrixComponent::includeComponentClass($componentName);

$usePropertyFeatures = Iblock\Model\PropertyFeature::isEnabledFeatures();

$iblockExists = (!empty($arCurrentValues['IBLOCK_ID']) && (int)$arCurrentValues['IBLOCK_ID'] > 0);

$arIBlockType = CIBlockParameters::GetIBlockTypes();

$arIBlock = array();
$iblockFilter = !empty($arCurrentValues['IBLOCK_TYPE'])
	? array('TYPE' => $arCurrentValues['IBLOCK_TYPE'], 'ACTIVE' => 'Y')
	: array('ACTIVE' => 'Y');

$rsIBlock = CIBlock::GetList(array('SORT' => 'ASC'), $iblockFilter);
while ($arr = $rsIBlock->Fetch())
{
	$id = (int)$arr['ID'];
	$arIBlock[$id] = '['.$id.'] '.$arr['NAME'];
}
unset($id, $arr, $rsIBlock, $iblockFilter);

$current = array();
$current['IBLOCK_ID'] = !empty($arCurrentValues['IBLOCK_ID']) && 0 < (int)$arCurrentValues['IBLOCK_ID'] ?
	(int)$arCurrentValues['IBLOCK_ID'] : 0;
/**
 * @todo set multiple?
 */
$current['SECTION_ID'] = !empty($arCurrentValues['SECTION_ID']) && 0 < (int)$arCurrentValues['SECTION_ID'] ?
	(int)$arCurrentValues['SECTION_ID'] : 0;
$current['ELEMENT_ID'] = !empty($arCurrentValues['ELEMENT_ID']) && 0 < (int)$arCurrentValues['ELEMENT_ID'] ?
	(int)$arCurrentValues['ELEMENT_ID'] : 0;

/**
 * Get sections
 */
$arSections = array();
// $arSectionsFilter = array('ACTIVE' => 'Y');
$arFilter = array();
if( $current['IBLOCK_ID'] ) $arFilter['IBLOCK_ID'] = $current['IBLOCK_ID'];

$rsSection = CIBlockSection::GetList(
    array("SORT" => "ASC"),
    $arFilter,
    false,
    array('IBLOCK_ID', 'ID', 'NAME')
);
while ($arr = $rsSection->Fetch())
{
	$id = (int)$arr['ID'];
	$arSections[$id] = '['.$id.'] '.$arr['NAME'];
}
unset($id, $arr, $rsSection);

/**
 * Get elements
 */
$arElements = array();
if( $current['SECTION_ID'] ) $arFilter['SECTION_ID'] = $current['SECTION_ID'];

$rsElement = CIBlockElement::GetList(
    Array("SORT" => "ASC"),
    $arFilter,
    false,
    Array('IBLOCK_ID', 'ID', 'NAME')
);

while ($arr = $rsElement->Fetch())
{
	$id = (int)$arr['ID'];
	$arElements[$id] = '['.$id.'] '.$arr['NAME'];
}
unset($id, $arr, $rsElement);

$defaultValue = array('-' => GetMessage('CP_BCS_EMPTY'));

$arProperty = array();
$arProperty_N = array();
$arProperty_X = array();
$listProperties = array();

if ($iblockExists)
{
	$propertyIterator = Iblock\PropertyTable::getList(array(
		'select' => array('ID', 'IBLOCK_ID', 'NAME', 'CODE', 'PROPERTY_TYPE', 'MULTIPLE', 'LINK_IBLOCK_ID', 'USER_TYPE', 'SORT'),
		'filter' => array('=IBLOCK_ID' => $arCurrentValues['IBLOCK_ID'], '=ACTIVE' => 'Y'),
		'order' => array('SORT' => 'ASC', 'NAME' => 'ASC')
	));
	while ($property = $propertyIterator->fetch())
	{
		$propertyCode = (string)$property['CODE'];

		if ($propertyCode === '')
		{
			$propertyCode = $property['ID'];
		}

		$propertyName = '['.$propertyCode.'] '.$property['NAME'];

		if ($property['PROPERTY_TYPE'] != Iblock\PropertyTable::TYPE_FILE)
		{
			$arProperty[$propertyCode] = $propertyName;

			if ($property['MULTIPLE'] === 'Y')
			{
				$arProperty_X[$propertyCode] = $propertyName;
			}
			elseif ($property['PROPERTY_TYPE'] == Iblock\PropertyTable::TYPE_LIST)
			{
				$arProperty_X[$propertyCode] = $propertyName;
			}
			elseif ($property['PROPERTY_TYPE'] == Iblock\PropertyTable::TYPE_ELEMENT && (int)$property['LINK_IBLOCK_ID'] > 0)
			{
				$arProperty_X[$propertyCode] = $propertyName;
			}
		}

		if ($property['PROPERTY_TYPE'] == Iblock\PropertyTable::TYPE_NUMBER)
		{
			$arProperty_N[$propertyCode] = $propertyName;
		}
	}
	unset($propertyCode, $propertyName, $property, $propertyIterator);
}

$arProperty_UF = array();
$arSProperty_LNS = array();
$arSProperty_F = array();
if ($iblockExists)
{
	$arUserFields = $USER_FIELD_MANAGER->GetUserFields('IBLOCK_'.$arCurrentValues['IBLOCK_ID'].'_SECTION', 0, LANGUAGE_ID);

	foreach( $arUserFields as $FIELD_NAME => $arUserField)
	{
		$arUserField['LIST_COLUMN_LABEL'] = (string)$arUserField['LIST_COLUMN_LABEL'];
		$arProperty_UF[$FIELD_NAME] = $arUserField['LIST_COLUMN_LABEL'] ? '['.$FIELD_NAME.']'.$arUserField['LIST_COLUMN_LABEL'] : $FIELD_NAME;

		if ($arUserField['USER_TYPE']['BASE_TYPE'] === 'string')
		{
			$arSProperty_LNS[$FIELD_NAME] = $arProperty_UF[$FIELD_NAME];
		}

		if ($arUserField['USER_TYPE']['BASE_TYPE'] === 'file' && $arUserField['MULTIPLE'] === 'N')
		{
			$arSProperty_F[$FIELD_NAME] = $arProperty_UF[$FIELD_NAME];
		}
	}
	unset($arUserFields);
}

$arSort = CIBlockParameters::GetElementSortFields(
	array('SHOWS', 'SORT', 'TIMESTAMP_X', 'NAME', 'ID', 'ACTIVE_FROM', 'ACTIVE_TO'),
	array('KEY_LOWERCASE' => 'Y')
);

$arAscDesc = array(
	'asc' => GetMessage('IBLOCK_SORT_ASC'),
	'desc' => GetMessage('IBLOCK_SORT_DESC'),
);

$arComponentParameters = array(
	'GROUPS' => array(
		'SORT_SETTINGS' => array(
			'NAME' => GetMessage('SORT_SETTINGS'),
			'SORT' => 210
		),
		'EXTENDED_SETTINGS' => array(
			'NAME' => GetMessage('IBLOCK_EXTENDED_SETTINGS'),
			'SORT' => 10000
		)
	),
	'PARAMETERS' => array(
		// Do not delete for future
		// 'SEF_MODE' => array(),
		// 'SEF_RULE' => array(
		// 	'VALUES' => array(
		// 		'SECTION_ID' => array(
		// 			'TEXT' => GetMessage('IBLOCK_SECTION_ID'),
		// 			'TEMPLATE' => '#SECTION_ID#',
		// 			'PARAMETER_LINK' => 'SECTION_ID',
		// 			'PARAMETER_VALUE' => '={$_REQUEST["SECTION_ID"]}',
		// 		),
		// 		'SECTION_CODE' => array(
		// 			'TEXT' => GetMessage('IBLOCK_SECTION_CODE'),
		// 			'TEMPLATE' => '#SECTION_CODE#',
		// 			'PARAMETER_LINK' => 'SECTION_CODE',
		// 			'PARAMETER_VALUE' => '={$_REQUEST["SECTION_CODE"]}',
		// 		),
		// 		'SECTION_CODE_PATH' => array(
		// 			'TEXT' => GetMessage('CP_BCS_SECTION_CODE_PATH'),
		// 			'TEMPLATE' => '#SECTION_CODE_PATH#',
		// 			'PARAMETER_LINK' => 'SECTION_CODE_PATH',
		// 			'PARAMETER_VALUE' => '={$_REQUEST["SECTION_CODE_PATH"]}',
		// 		),
		// 	),
		// ),
		// 'AJAX_MODE' => array(),
		'QUERY_TYPE' => array(
			'PARENT' => 'BASE',
			'NAME' => 'Тип запроса',
			'TYPE' => 'LIST',
			'VALUES' => array(
				'IBLOCK' => 'Инфоблок',
				'AGREEMENT' => 'Соглашения',
			),
			'REFRESH' => 'Y',
		),
		'SECTION_USER_FIELDS' => array(
			'PARENT' => 'DATA_SOURCE',
			'NAME' => GetMessage('CP_BCS_SECTION_USER_FIELDS'),
			'TYPE' => 'LIST',
			'MULTIPLE' => 'Y',
			'ADDITIONAL_VALUES' => 'Y',
			'VALUES' => $arProperty_UF,
		),
		'ELEMENT_SORT_FIELD' => array(
			'PARENT' => 'SORT_SETTINGS',
			'NAME' => GetMessage('IBLOCK_ELEMENT_SORT_FIELD'),
			'TYPE' => 'LIST',
			'VALUES' => $arSort,
			'ADDITIONAL_VALUES' => 'Y',
			'DEFAULT' => 'sort',
		),
		'ELEMENT_SORT_ORDER' => array(
			'PARENT' => 'SORT_SETTINGS',
			'NAME' => GetMessage('IBLOCK_ELEMENT_SORT_ORDER'),
			'TYPE' => 'LIST',
			'VALUES' => $arAscDesc,
			'DEFAULT' => 'asc',
			'ADDITIONAL_VALUES' => 'Y',
		),
		'ELEMENT_SORT_FIELD2' => array(
			'PARENT' => 'SORT_SETTINGS',
			'NAME' => GetMessage('IBLOCK_ELEMENT_SORT_FIELD2'),
			'TYPE' => 'LIST',
			'VALUES' => $arSort,
			'ADDITIONAL_VALUES' => 'Y',
			'DEFAULT' => 'id',
		),
		'ELEMENT_SORT_ORDER2' => array(
			'PARENT' => 'SORT_SETTINGS',
			'NAME' => GetMessage('IBLOCK_ELEMENT_SORT_ORDER2'),
			'TYPE' => 'LIST',
			'VALUES' => $arAscDesc,
			'DEFAULT' => 'desc',
			'ADDITIONAL_VALUES' => 'Y',
		),
		'FILTER_NAME' => array(
			'PARENT' => 'DATA_SOURCE',
			'NAME' => GetMessage('IBLOCK_FILTER_NAME_IN'),
			'TYPE' => 'STRING',
			'DEFAULT' => 'arrFilter',
		),
		'INCLUDE_SUBSECTIONS' => array(
			'PARENT' => 'DATA_SOURCE',
			'NAME' => GetMessage('CP_BCS_INCLUDE_SUBSECTIONS'),
			'TYPE' => 'LIST',
			'VALUES' => array(
				'Y' => GetMessage('CP_BCS_INCLUDE_SUBSECTIONS_ALL'),
				'A' => GetMessage('CP_BCS_INCLUDE_SUBSECTIONS_ACTIVE'),
				'N' => GetMessage('CP_BCS_INCLUDE_SUBSECTIONS_NO'),
			),
			'DEFAULT' => 'Y',
		),
		'SHOW_ALL_WO_SECTION' => array(
			'PARENT' => 'DATA_SOURCE',
			'NAME' => GetMessage('CP_BCS_SHOW_ALL_WO_SECTION'),
			'TYPE' => 'CHECKBOX',
			'DEFAULT' => 'N',
		),
		// 'SECTION_URL' => CIBlockParameters::GetPathTemplateParam(
		// 	'SECTION',
		// 	'SECTION_URL',
		// 	GetMessage('IBLOCK_SECTION_URL'),
		// 	'',
		// 	'URL_TEMPLATES'
		// ),
		// 'DETAIL_URL' => CIBlockParameters::GetPathTemplateParam(
		// 	'DETAIL',
		// 	'DETAIL_URL',
		// 	GetMessage('IBLOCK_DETAIL_URL'),
		// 	'',
		// 	'URL_TEMPLATES'
		// ),
		'SECTION_ID_VARIABLE' => array(
			'PARENT' => 'URL_TEMPLATES',
			'NAME' => GetMessage('IBLOCK_SECTION_ID_VARIABLE'),
			'TYPE' => 'STRING',
			'DEFAULT' => 'SECTION_ID',
		),
		'SET_TITLE' => array(),
		'SET_BROWSER_TITLE' => array(
			'PARENT' => 'ADDITIONAL_SETTINGS',
			'NAME' => GetMessage('CP_BCS_SET_BROWSER_TITLE'),
			'TYPE' => 'CHECKBOX',
			'DEFAULT' => 'Y',
			'REFRESH' => 'Y'
		),
		'BROWSER_TITLE' => array(
			'PARENT' => 'ADDITIONAL_SETTINGS',
			'NAME' => GetMessage('CP_BCS_BROWSER_TITLE'),
			'TYPE' => 'LIST',
			'MULTIPLE' => 'N',
			'DEFAULT' => '-',
			'VALUES' => array_merge($defaultValue, array('NAME' => GetMessage('IBLOCK_FIELD_NAME')), $arSProperty_LNS),
			'HIDDEN' => (isset($arCurrentValues['SET_BROWSER_TITLE']) && $arCurrentValues['SET_BROWSER_TITLE'] === 'N' ? 'Y' : 'N')
		),
		'SET_META_KEYWORDS' => array(
			'PARENT' => 'ADDITIONAL_SETTINGS',
			'NAME' => GetMessage('CP_BCS_SET_META_KEYWORDS'),
			'TYPE' => 'CHECKBOX',
			'DEFAULT' => 'Y',
			'REFRESH' => 'Y',
		),
		'META_KEYWORDS' => array(
			'PARENT' => 'ADDITIONAL_SETTINGS',
			'NAME' => GetMessage('T_IBLOCK_DESC_KEYWORDS'),
			'TYPE' => 'LIST',
			'DEFAULT' => '-',
			'ADDITIONAL_VALUES' => 'Y',
			'VALUES' => array_merge($defaultValue, $arSProperty_LNS),
			'HIDDEN' => (isset($arCurrentValues['SET_META_KEYWORDS']) && $arCurrentValues['SET_META_KEYWORDS'] === 'N' ? 'Y' : 'N')
		),
		'SET_META_DESCRIPTION' => array(
			'PARENT' => 'ADDITIONAL_SETTINGS',
			'NAME' => GetMessage('CP_BCS_SET_META_DESCRIPTION'),
			'TYPE' => 'CHECKBOX',
			'DEFAULT' => 'Y',
			'REFRESH' => 'Y'
		),
		'META_DESCRIPTION' => array(
			'PARENT' => 'ADDITIONAL_SETTINGS',
			'NAME' => GetMessage('T_IBLOCK_DESC_DESCRIPTION'),
			'TYPE' => 'LIST',
			'DEFAULT' => '-',
			'ADDITIONAL_VALUES' => 'Y',
			'VALUES' => array_merge($defaultValue, $arSProperty_LNS),
			'HIDDEN' => (isset($arCurrentValues['SET_META_DESCRIPTION']) && $arCurrentValues['SET_META_DESCRIPTION'] === 'N' ? 'Y' : 'N')
		),
		'SET_LAST_MODIFIED' => array(
			'PARENT' => 'ADDITIONAL_SETTINGS',
			'NAME' => GetMessage('CP_BCS_SET_LAST_MODIFIED'),
			'TYPE' => 'CHECKBOX',
			'DEFAULT' => 'N',
		),
		'USE_MAIN_ELEMENT_SECTION' => array(
			'PARENT' => 'ADDITIONAL_SETTINGS',
			'NAME' => GetMessage('CP_BCS_USE_MAIN_ELEMENT_SECTION'),
			'TYPE' => 'CHECKBOX',
			'DEFAULT' => 'N',
		),
		'ADD_SECTIONS_CHAIN' => array(
			'PARENT' => 'ADDITIONAL_SETTINGS',
			'NAME' => GetMessage('CP_BCS_ADD_SECTIONS_CHAIN'),
			'TYPE' => 'CHECKBOX',
			'DEFAULT' => 'N',
		),
		'PAGE_ELEMENT_COUNT' => array(
			'PARENT' => 'VISUAL',
			'NAME' => GetMessage('IBLOCK_PAGE_ELEMENT_COUNT'),
			'TYPE' => 'STRING',
			'HIDDEN' => isset($templateProperties['PRODUCT_ROW_VARIANTS']) ? 'Y' : 'N',
			'DEFAULT' => '18'
		),
		'LINE_ELEMENT_COUNT' => array(
			'PARENT' => 'VISUAL',
			'NAME' => GetMessage('IBLOCK_LINE_ELEMENT_COUNT'),
			'TYPE' => 'STRING',
			'HIDDEN' => isset($templateProperties['PRODUCT_ROW_VARIANTS']) ? 'Y' : 'N',
			'DEFAULT' => '3'
		),
		'PROPERTY_CODE' => array(
			'PARENT' => 'VISUAL',
			'NAME' => GetMessage('IBLOCK_PROPERTY'),
			'TYPE' => 'LIST',
			'MULTIPLE' => 'Y',
			'REFRESH' => isset($templateProperties['PROPERTY_CODE_MOBILE']) ? 'Y' : 'N',
			'VALUES' => $arProperty,
			'ADDITIONAL_VALUES' => 'Y',
		),
		'PROPERTY_CODE_MOBILE' => array(),

		'CACHE_TIME' => array('DEFAULT' => 36000000),
		'CACHE_FILTER' => array(
			'PARENT' => 'ADDITIONAL_SETTINGS',
			'NAME' => GetMessage('IBLOCK_CACHE_FILTER'),
			'TYPE' => 'CHECKBOX',
			'DEFAULT' => 'N',
		),
		'CACHE_GROUPS' => array(
			'PARENT' => 'CACHE_SETTINGS',
			'NAME' => GetMessage('CP_BCS_CACHE_GROUPS'),
			'TYPE' => 'CHECKBOX',
			'DEFAULT' => 'Y',
		),
		'COMPATIBLE_MODE' => array(
			'PARENT' => 'EXTENDED_SETTINGS',
			'NAME' => GetMessage('CP_BCS_COMPATIBLE_MODE'),
			'TYPE' => 'CHECKBOX',
			'DEFAULT' => 'Y',
			'REFRESH' => 'Y'
		),
		'DISABLE_INIT_JS_IN_COMPONENT' => array(
			'PARENT' => 'EXTENDED_SETTINGS',
			'NAME' => GetMessage('CP_BCS_DISABLE_INIT_JS_IN_COMPONENT'),
			'TYPE' => 'CHECKBOX',
			'DEFAULT' => 'N',
			'HIDDEN' => (isset($arCurrentValues['COMPATIBLE_MODE']) && $arCurrentValues['COMPATIBLE_MODE'] === 'N' ? 'Y' : 'N')
		)
	),
);

if( !$arCurrentValues['QUERY_TYPE'] || 'IBLOCK' == strtoupper($arCurrentValues['QUERY_TYPE']) ) {
	$arComponentParameters['PARAMETERS']['IBLOCK_TYPE'] = array(
		'PARENT' => 'BASE',
		'NAME' => GetMessage('IBLOCK_TYPE'),
		'TYPE' => 'LIST',
		'VALUES' => $arIBlockType,
		'REFRESH' => 'Y',
	);

	$arComponentParameters['PARAMETERS']['IBLOCK_ID'] = array(
		'PARENT' => 'BASE',
		'NAME' => GetMessage('IBLOCK_IBLOCK'),
		'TYPE' => 'LIST',
		'ADDITIONAL_VALUES' => 'Y',
		'VALUES' => $arIBlock,
		'REFRESH' => 'Y',
	);

	$arComponentParameters['PARAMETERS']['SECTION_ID'] = array(
		'PARENT' => 'BASE',
		'NAME' => 'Раздел', // GetMessage('IBLOCK_SECTION_ID')
		'TYPE' => 'LIST',
		'DEFAULT' => '={$_REQUEST["SECTION_ID"]}',
		'ADDITIONAL_VALUES' => 'Y',
		'VALUES' => $arSections,
		'REFRESH' => 'Y',
	);

	$arComponentParameters['PARAMETERS']['ELEMENT_ID'] = array(
		'PARENT' => 'BASE',
		'NAME' => 'Элемент',
		'TYPE' => 'LIST',
		'DEFAULT' => '={$_REQUEST["ELEMENT_ID"]}',
		'ADDITIONAL_VALUES' => 'Y',
		'VALUES' => $arElements,
	);

	$arComponentParameters['PARAMETERS']['SECTION_CODE'] = array(
		'PARENT' => 'BASE',
		'NAME' => GetMessage('IBLOCK_SECTION_CODE'),
		'TYPE' => 'STRING',
		'DEFAULT' => '',
	);
}
elseif( 'AGREEMENT' == strtoupper($arCurrentValues['QUERY_TYPE']) ) {
	$arAgreementList = array();
	$rsList = AgreementTable::getList(array(
		'select' => array('ID', 'DATE_INSERT', 'ACTIVE', 'NAME', 'TYPE', 'AGREEMENT_TEXT'),
		'filter' => array(), // $this->getDataFilter()
		// 'offset' => $nav->getOffset(),
		// 'limit' => $nav->getLimit(),
		'count_total' => true,
		'cache' => array('ttl' => 3600),
		'order' => array(
			'ID' => 'ASC'
		)
	));

	foreach ($rsList as $item)
	{
		if( !isset($item['ID']) ) continue;

		$arAgreementList[ $item['ID'] ][] = "[{$item['ID']}] {$item['NAME']}";
	}

	$arComponentParameters['PARAMETERS']['ELEMENT_ID'] = array(
		'PARENT' => 'BASE',
		'NAME' => 'Элемент',
		'TYPE' => 'LIST',
		'DEFAULT' => '={$_REQUEST["ELEMENT_ID"]}',
		'ADDITIONAL_VALUES' => 'Y',
		'VALUES' => $arAgreementList,
	);
}

if ($usePropertyFeatures)
{
	unset($arComponentParameters['PARAMETERS']['PROPERTY_CODE']);
}

// hack for correct sort
if (isset($templateProperties['PROPERTY_CODE_MOBILE']))
{
	$arComponentParameters['PARAMETERS']['PROPERTY_CODE_MOBILE'] = $templateProperties['PROPERTY_CODE_MOBILE'];
	unset($templateProperties['PROPERTY_CODE_MOBILE']);
}
else
{
	unset($arComponentParameters['PARAMETERS']['PROPERTY_CODE_MOBILE']);
}

/**
 * Do not delete for future
 */
// CIBlockParameters::AddPagerSettings(
// 	$arComponentParameters,
// 	GetMessage('T_IBLOCK_DESC_PAGER_CATALOG'), //$pager_title
// 	true, //$bDescNumbering
// 	true, //$bShowAllParam
// 	true, //$bBaseLink
// 	$arCurrentValues['PAGER_BASE_LINK_ENABLE'] === 'Y' //$bBaseLinkEnabled
// );

// CIBlockParameters::Add404Settings($arComponentParameters, $arCurrentValues);

// if ($arCurrentValues['SEF_MODE'] === 'Y')
// {
// 	$arComponentParameters['PARAMETERS']['SECTION_CODE_PATH'] = array(
// 		'NAME' => GetMessage('CP_BCS_SECTION_CODE_PATH'),
// 		'TYPE' => 'STRING',
// 		'DEFAULT' => '',
// 	);
// }

// $arComponentParameters['PARAMETERS']['DISPLAY_COMPARE'] = array(
// 	'PARENT' => 'COMPARE',
// 	'NAME' => GetMessage('CP_BCS_DISPLAY_COMPARE'),
// 	'TYPE' => 'CHECKBOX',
// 	'REFRESH' => 'Y',
// 	'DEFAULT' => 'N'
// );

// if (isset($arCurrentValues['DISPLAY_COMPARE']) && $arCurrentValues['DISPLAY_COMPARE'] === 'Y')
// {
// 	$arComponentParameters['PARAMETERS']['COMPARE_PATH'] = array(
// 		'PARENT' => 'COMPARE',
// 		'NAME' => GetMessage('CP_BCS_COMPARE_PATH'),
// 		'TYPE' => 'STRING',
// 		'DEFAULT' => ''
// 	);
// }