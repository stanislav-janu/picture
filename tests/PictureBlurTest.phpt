<?php

/**
 * Test: Picture Blur.
 */

declare(strict_types=1);

use JCode\Picture;
use Nette\Utils\FileSystem;
use Tester\Assert;

ini_set('memory_limit', '64M');

require_once __DIR__ . '/bootstrap.php';

FileSystem::delete(__DIR__ . '/pictures/blurred');

$picture = new Picture(__DIR__ . '/pictures');
$file = '/waves-1867285_1920.jpg';

Assert::false($picture->isBlurred($file));
Assert::same('/blurred/8/10_b88bc9210f4cb7d36afc83ffba73a604.jpg', $picture->blur($file));
Assert::true($picture->isBlurred($file));

FileSystem::delete(__DIR__ . '/pictures/blurred');
