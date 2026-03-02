<?php

namespace sfmobile\ext\fileUploader\models;

use Yii;
use yii\helpers\ArrayHelper;

/**
* Handle files in SESSION
* Options can contain: prefixSessionKey to add a prefix to session key (to distinguish different page using same browser)
* @package sfmobile\ext\fileUploader\models
* @version 1.0.5
*/
class FileInSession extends \yii\base\Model
{
    public $formInputInfo;
    public $fileUploadAttributes;

    public $modelName;
    public $attributeName;
    public $data;

    /**
    * Catch all attributes name parts, splitting tabular index from name
    * @since 1.0.3
    */
    public static function getAttributeNameParts($attributeName)
    {
        $matches = [];
        if (!preg_match(\yii\helpers\BaseHtml::$attributeRegex, $attributeName, $matches)) {
             throw new InvalidArgumentException('Attribute name must contain word characters only.');
         }
         /*
         $prefix = $matches[1];
         $attribute = $matches[2];
         $suffix = $matches[3];
         */
         return $matches;
    }

	public function getFileName()
	{
		$retVal = null;
		if(($this->formInputInfo!=null)&&(isset($this->formInputInfo['name']))&&($this->formInputInfo['name']!=''))
		{
			$retVal = $this->formInputInfo['name'];
		}
		else if(($this->fileUploadAttributes!=null)&&(isset($this->fileUploadAttributes['file_name_original']))&&($this->fileUploadAttributes['file_name_original']!=null))
		{
			$retVal = $this->fileUploadAttributes['file_name_original'];
		}
		return $retVal;
	}

	public function getFileSize()
	{
		$retVal = null;
		if(($this->formInputInfo!=null)&&(isset($this->formInputInfo['size']))&&($this->formInputInfo['size']!=''))
		{
			$retVal = $this->formInputInfo['size'];
		}
		else if(($this->fileUploadAttributes!=null)&&(isset($this->fileUploadAttributes['file_size']))&&($this->fileUploadAttributes['file_size']!=null))
		{
			$retVal = $this->fileUploadAttributes['file_size'];
		}
		return $retVal;
	}

	public function getFileMimeType()
	{
		$retVal = null;
		if(($this->formInputInfo!=null)&&(isset($this->formInputInfo['type']))&&($this->formInputInfo['type']!=''))
		{
			$retVal = $this->formInputInfo['type'];
		}
		else if(($this->fileUploadAttributes!=null)&&(isset($this->fileUploadAttributes['mime_type']))&&($this->fileUploadAttributes['mime_type']!=null))
		{
			$retVal = $this->fileUploadAttributes['mime_type'];
		}
		return $retVal;
	}

    /**
    * @param $options Contains : prefixSessionKey
    */
    public static function createListFromModel($lstFilesUpload, $modelName, $attributeName, $options = null)
    {
        $arrObj = [];

        foreach($lstFilesUpload as $fu)
        {
            if(file_exists($fu->absolutePathFile) == false) continue;

            $data = file_get_contents($fu->absolutePathFile);

            $fis = new self();
            $fis->modelName = $modelName;
            $fis->attributeName = $attributeName;
            $fis->fileUploadAttributes = $fu->attributes;
			$fis->data = $data;

            $arrObj[$fu->file_name_original] = $fis;
        }

        $prefixSessionKey = ArrayHelper::getValue($options, 'prefixSessionKey', '');

        // Salva in sessione
        $key = $prefixSessionKey.$modelName.'.'.$attributeName;
        $session = \Yii::$app->session;
        $session->set($key, $arrObj);

        return $arrObj;
    }

    public static function createListFromForm($modelName, $attributeName, $options = null)
    {
        $attributeNamePartTabularIndex = null;

        $attributeNameParts = self::getAttributeNameParts($attributeName);
        if($attributeNameParts[1]!='') $attributeNamePartTabularIndex = substr($attributeNameParts[1], 1, strlen($attributeNameParts[1])-2);
        if($attributeNameParts[2]!='') $attributeNamePartName = $attributeNameParts[2];

        $arrOut = [];
        if(isset($_FILES[$modelName]))
        {
            $lstFuName = ($attributeNamePartTabularIndex == null)?$_FILES[$modelName]['name']:($_FILES[$modelName]['name'][$attributeNamePartTabularIndex] ?? []);
            $lstFuType = ($attributeNamePartTabularIndex == null)?$_FILES[$modelName]['type']:($_FILES[$modelName]['type'][$attributeNamePartTabularIndex] ?? []);
            $lstFuTmpName = ($attributeNamePartTabularIndex == null)?$_FILES[$modelName]['tmp_name']:($_FILES[$modelName]['tmp_name'][$attributeNamePartTabularIndex] ?? []);
            $lstFuError = ($attributeNamePartTabularIndex == null)?$_FILES[$modelName]['error']:($_FILES[$modelName]['error'][$attributeNamePartTabularIndex] ?? []);
            $lstFuSize = ($attributeNamePartTabularIndex == null)?$_FILES[$modelName]['size']:($_FILES[$modelName]['size'][$attributeNamePartTabularIndex] ?? []);

            for($k=0;$k<count($lstFuTmpName);$k++)
            {
                foreach($lstFuTmpName as $key=>$temp)
                {
                    if(is_array($temp) == false) continue;

                    if($key != $attributeNamePartName) continue;

                    for($j=0;$j<count($lstFuTmpName[$key]);$j++)
                    {
                        $fuName = $lstFuName[$key][$j];
                        $fuType = $lstFuType[$key][$j];
                        $fuError = $lstFuError[$key][$j];
                        $fuTmpName = $lstFuTmpName[$key][$j];
                        $fuSize = $lstFuSize[$key][$j];
                        if(($fuError == 0)&&($fuSize>0)&&file_exists($fuTmpName))
                        {
                            $formInputInfo = [ 'name' => $fuName, 'type' => $fuType, 'error' => $fuError, 'tmpName' => $fuTmpName, 'size' => $fuSize ];

                            $fuData = file_get_contents($fuTmpName);
                            $fileInSession = self::create($modelName, $attributeName, $formInputInfo, null, $fuData, $options);

                            $arrOut[] = $fileInSession;
                        }
                   }
                }
            }
         }

        return $arrOut;
    }

    public static function initFromModelOrCreateFromForm($modelOrModelName, $attributeName, $lstModelFiles, $options = null)
    {
        $modelName = null;
        if(is_string($modelOrModelName))
        {
            $modelName = $modelOrModelName;
        }
        else if(is_object($modelOrModelName))
        {
            $modelName = (new \ReflectionClass($modelOrModelName))->getShortName();

            $prefixSessionKeyAttribute = ArrayHelper::getValue($options, 'prefixSessionKeyAttribute');
            if($prefixSessionKeyAttribute!=null)
            {
                if (property_exists($modelOrModelName,$prefixSessionKeyAttribute)) {

                    $indexFormModel = ArrayHelper::getValue($options, 'indexFormModel');

                    if($indexFormModel !== null)
                    {
                        $options['prefixSessionKey'] = ArrayHelper::getValue($_REQUEST, [ $modelName, $indexFormModel, $prefixSessionKeyAttribute ], Yii::$app->getSecurity()->generateRandomString());
                    }
                    else
                    {
                        $options['prefixSessionKey'] = ArrayHelper::getValue($_REQUEST, [ $modelName, $prefixSessionKeyAttribute ], Yii::$app->getSecurity()->generateRandomString());
                    }

                    $modelOrModelName->$prefixSessionKeyAttribute = $options['prefixSessionKey'];
                }
            }
        }

        $arrFilesInSessionPerAttributeName = FileInSession::createListFromForm($modelName, $attributeName, $options);

        if((count($arrFilesInSessionPerAttributeName) == 0)&&(isset($_POST[$modelName]) == false))
        {
            // Inizializza i files
            FileInSession::createListFromModel($lstModelFiles, $modelName, $attributeName, $options);
        }
    }

    public static function create($modelName, $attributeName, $formInputInfo, $fileUploadAttributes, $data, $options = null)
    {
        $obj = new self();
        $obj->modelName = $modelName;
        $obj->attributeName = $attributeName;
        $obj->formInputInfo = $formInputInfo;
        $obj->fileUploadAttributes = $fileUploadAttributes;
        $obj->data = $data;

        $filename = $formInputInfo['name'];

        $prefixSessionKey = ArrayHelper::getValue($options, 'prefixSessionKey', '');

        // Salva in sessione
        $key = $prefixSessionKey.$modelName.'.'.$attributeName;
        $session = \Yii::$app->session;
        $arrObj = [];
        if($session->has($key)) $arrObj = $session->get($key);
        $arrObj[$filename] = $obj;
        $session->set($key, $arrObj);

        return $obj;
    }

    public static function listItems($modelName, $attributeName, $options = null)
    {
        $prefixSessionKey = ArrayHelper::getValue($options, 'prefixSessionKey', '');

        // Recupera dalla sessione
        $key = $prefixSessionKey.$modelName.'.'.$attributeName;
        $session = \Yii::$app->session;
        $arrObj = [];
        if($session->has($key)) $arrObj = $session->get($key);
        return $arrObj;
    }

    public static function getItem($modelName, $attributeName, $filename, $options = null)
    {
        $prefixSessionKey = ArrayHelper::getValue($options, 'prefixSessionKey', '');

        // Recupera dalla sessione
        $key = $prefixSessionKey.$modelName.'.'.$attributeName;
        $session = \Yii::$app->session;
        $arrObj = [];
        if($session->has($key)) $arrObj = $session->get($key);

        $obj = (isset($arrObj[$filename]))?$arrObj[$filename]:null;

        return $obj;
    }

    public static function deleteListItems($modelName, $attributeName, $options = null)
    {
        $prefixSessionKey = ArrayHelper::getValue($options, 'prefixSessionKey', '');

        $key = $prefixSessionKey.$modelName.'.'.$attributeName;
        $session = \Yii::$app->session;
        $session->remove($key);
    }

    public static function deleteItem($modelName, $attributeName, $filename, $options = null)
    {
        $prefixSessionKey = ArrayHelper::getValue($options, 'prefixSessionKey', '');

        $key = $prefixSessionKey.$modelName.'.'.$attributeName;
        $session = \Yii::$app->session;
        $arrObj = [];
        if($session->has($key)) $arrObj = $session->get($key);

        $obj = (isset($arrObj[$filename]))?$arrObj[$filename]:null;

        if($obj!=null)
        {
            unset($arrObj[$filename]);
            $session->set($key, $arrObj);
        }

        return $obj;
    }

}

?>
