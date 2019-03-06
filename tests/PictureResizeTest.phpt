<?php

/**
 * Test: Picture resize.
 */

declare(strict_types=1);

use JCode\Picture;
use Nette\Utils\FileSystem;
use Nette\Utils\Image;
use Tester\Assert;

ini_set('memory_limit', '64M');

require_once __DIR__ . '/bootstrap.php';

FileSystem::delete(__DIR__ . '/pictures/thumbnails');

$picture = new Picture(__DIR__ . '/pictures');
$file = '/dolphin-203875_1920.jpg';
Assert::false($picture->isResize($file, 128));
Assert::same('/thumbnails/128x/3/n_733f3cd53bde51b539fc7be934aa764a.jpg', $picture->resize($file, 128));
Assert::true($picture->isResize($file, 128));

Assert::false($picture->isResize($file, 256, 2, Image::EXACT));
Assert::same('/thumbnails/256x2/3/e_733f3cd53bde51b539fc7be934aa764a.jpg', $picture->resize($file, 256, 2, Image::EXACT));
Assert::true($picture->isResize($file, 256, 2, Image::EXACT));

Assert::false($picture->isResize($file, null, 128));
Assert::same('/thumbnails/x128/3/n_733f3cd53bde51b539fc7be934aa764a.jpg', $picture->resize($file, null, 128));
Assert::true($picture->isResize($file, null, 128));

FileSystem::delete(__DIR__ . '/pictures/thumbnails');
