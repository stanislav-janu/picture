<?php

declare(strict_types=1);

use Rector\Core\ValueObject\PhpVersion;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\FunctionLike\ReturnTypeDeclarationRector;


return static function (\Rector\Config\RectorConfig $rectorConfig): void {
	// get parameters
	$rectorConfig->paths([
		__DIR__ . '/src',
		__DIR__ . '/tests',
	]);

	$rectorConfig->importNames();
	$rectorConfig->parallel();
	$rectorConfig->cacheDirectory(__DIR__ . '/temp/rector');

	// Define what rule sets will be applied
	$rectorConfig->import(SetList::PHP_80);
	$rectorConfig->import(SetList::CODE_QUALITY);

	$rectorConfig->phpVersion(PhpVersion::PHP_81);

	$services = $rectorConfig->services();
	$services->set(ReturnTypeDeclarationRector::class);
};
