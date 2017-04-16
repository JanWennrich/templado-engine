<?php declare(strict_types = 1);
namespace Templado\Engine;

require __DIR__ . '/../src/autoload.php';

try {
    $page = Templado::loadFile(
        new FileName(__DIR__ . '/html/basic.xhtml')
    );

    $assetCollection = new AssetListCollection();

    $sample   = new \DOMDocument();
    $fragment = $sample->createDocumentFragment();
    $fragment->appendXML('This is a first test: <span id="nested" />');

    $assetCollection->addAsset(
        new SimpleAsset('test', $fragment)
    );

    $assetCollection->addAsset(
        new SimpleAsset('nested', new \DOMText('Hello world'))
    );
    $page->applyAssets(
        $assetCollection
    );

    echo $page->asString();

} catch (TempladoException $e) {
    foreach($e->getErrorList() as $error) {
        echo (string)$error;
    }
}
