<?php declare(strict_types=1);

namespace JCode\Tests\Picture;

use JCode\Picture;
use Nette\Utils\FileSystem;
use Nette\Utils\Image;
use PHPUnit\Framework\TestCase;

class PictureTest extends TestCase
{

	public function testResize()
	{
		$picture = new Picture(__DIR__.'/pictures');
		$file = '/dolphin-203875_1920.jpg';
		$this->assertFalse($picture->isResized($file, 128));
		$this->assertSame('/thumbnails/128x/3/n_733f3cd53bde51b539fc7be934aa764a.jpg', $picture->resize($file, 128));
		$this->assertTrue($picture->isResized($file, 128));

		$this->assertFalse($picture->isResized($file, 256, 2, Image::EXACT));
		$this->assertSame('/thumbnails/256x2/3/e_733f3cd53bde51b539fc7be934aa764a.jpg', $picture->resize($file, 256, 2, Image::EXACT));
		$this->assertTrue($picture->isResized($file, 256, 2, Image::EXACT));

		$this->assertFalse($picture->isResized($file, null, 128));
		$this->assertSame('/thumbnails/x128/3/n_733f3cd53bde51b539fc7be934aa764a.jpg', $picture->resize($file, null, 128));
		$this->assertTrue($picture->isResized($file, null, 128));
	}

	public function testBlur()
	{
		$picture = new Picture(__DIR__.'/pictures');
		$file = '/waves-1867285_1920.jpg';
		$this->assertFalse($picture->isBlurred($file));
		$this->assertSame('/blurred/8/10_b88bc9210f4cb7d36afc83ffba73a604.jpg', $picture->blur($file));
		$this->assertTrue($picture->isBlurred($file));
	}

	protected function tearDown()
	{
		FileSystem::delete(__DIR__.'/pictures/thumbnails');
		FileSystem::delete(__DIR__.'/pictures/blurred');
	}

}
