<?php

use yii\helpers\ArrayHelper;
use yii\helpers\Html;


$modelName = \yii\helpers\StringHelper::basename(get_class($model));

$moduleId = (\sfmobile\ext\fileUploader\Module::getInstance()->id);

$prefixSessionKey = null;
if($prefixSessionKeyAttribute!=null)
{
    $prefixSessionKeyAttributeNameParts = \sfmobile\ext\fileUploader\models\FileInSession::getAttributeNameParts($prefixSessionKeyAttribute);
    if(count($prefixSessionKeyAttributeNameParts)>=3)
    {
        $prefixSessionKeyAttributeName = $prefixSessionKeyAttributeNameParts[2];
        if (property_exists($model,$prefixSessionKeyAttributeName)) {
           $prefixSessionKey = $model->$prefixSessionKeyAttributeName;
        }
    }
}

$initialPreview = [];
$initialPreviewConfig = [];
$filesInSession = \sfmobile\ext\fileUploader\models\FileInSession::listItems($modelName, $attribute, [ 'prefixSessionKey' => $prefixSessionKey ]);


foreach($filesInSession as $fis)
{
    $mimeType = $fis->fileMimeType;

    $initialPreview[] = \yii\helpers\Url::to([$moduleId.'/file-in-session/get', 'model' => $fis->modelName, 'attr' => $fis->attributeName, 'name' => $fis->fileName, 'psk' => $prefixSessionKey], true);

    $previewType = 'image';

    // If enabled detect preview type, use it
    if($detectPreviewType)
    {
        if(strpos($mimeType, 'image/') === 0) $previewType = 'image';
        if(strpos($mimeType, 'video/') === 0) $previewType = 'video';
        if(strpos($mimeType, 'audio/') === 0) $previewType = 'audio';
        if(strpos($mimeType, 'text/html') === 0) $previewType = 'html';
        if(strpos($mimeType, 'application/pdf') === 0) $previewType = 'pdf';
    }

    $initialPreviewConfig[] = [
        'type' => $previewType,
        'caption' => $fis->fileName,
        'size' => $fis->fileSize,
        'url' => \yii\helpers\Url::to([$moduleId.'/file-in-session/delete'], true),
        'extra' => ['model' => $fis->modelName, 'attr' => $fis->attributeName, 'name' => $fis->fileName, 'psk' => $prefixSessionKey]
   ];
}
?>
<?php

if($prefixSessionKeyAttribute!=null)
{
    echo Html::activeHiddenInput($model, $prefixSessionKeyAttribute);
}

echo \kartik\file\FileInput::widget([
    'model' => $model,
    'attribute' => $attribute.'[]',
    'options' => [
        'accept' => $acceptedTypes,
        'multiple' => true,
    ],
    'pluginOptions' => [
        'maxFileCount' => $maxFileCount,
        'minFileCount' => $minFileCount,

        'previewFileType' => 'any',
        'showPreview' => true,
        'showCaption' => true,
        'showRemove' => true,
        'showUpload' => false,
        'overwriteInitial' => false,
        'validateInitialCount' => true,

        'initialPreview'=> $initialPreview,
        'initialPreviewAsData'=>true,
        'initialPreviewConfig' => $initialPreviewConfig,
        'ajaxDeleteSettings' => [
            'type' => 'GET', // Overrides the default POST method
        ],
    ],
]);
?>
