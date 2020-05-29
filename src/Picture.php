<?php
declare(strict_types=1);

namespace JCode;

use Nette\Http\Url;
use Nette\InvalidArgumentException;
use Nette\Utils\FileSystem;
use Nette\Utils\Image;
use Nette\Utils\ImageException;
use Nette\Utils\Strings;
use Nette\Utils\UnknownImageFileException;
use Safe\Exceptions\StringsException;
use function Safe\sprintf;


/**
 * Class Picture
 * @package JCode
 */
class Picture
{
	private const MEMORY_RESERVE = 20; // MB

	/** @var bool */
	private $sharpenAfterResize = true;

	/** @var string */
	private $rootPath;

	/** @var string */
	private $reducedDir;

	/** @var string */
	private $blurredDir;


	/**
	 * Picture constructor.
	 *
	 * @param string $rootPath
	 * @param string $reducedDir
	 * @param string $blurredDir
	 */
	public function __construct(string $rootPath, string $reducedDir = '/thumbnails', string $blurredDir = '/blurred')
	{
		$this->rootPath = $rootPath;
		$this->reducedDir = $reducedDir;
		$this->blurredDir = $blurredDir;
	}


	/**
	 * @param bool $sharpenAfterResize
	 */
	public function setSharpenAfterResize(bool $sharpenAfterResize = true): void
	{
		$this->sharpenAfterResize = $sharpenAfterResize;
	}


	/**
	 * @param string   $file
	 * @param string   $extension
	 * @param int|null $width
	 * @param int|null $height
	 * @param int      $flag
	 *
	 * @return array
	 */
	private function settings(string $file, string $extension, int $width = null, int $height = null, int $flag = Image::FIT): array
	{
		$prefixes = [
			Image::EXACT => 'e',
			Image::FIT => 'n',
			Image::FILL => 'f',
			Image::STRETCH => 's',
			Image::SHRINK_ONLY => 'so',
		];

		$md5 = md5($file);

		$fileName = $prefixes[$flag] . '_' . $md5 . '.' . $extension;
		$dir = DIRECTORY_SEPARATOR . $width . 'x' . $height . DIRECTORY_SEPARATOR . Strings::substring($md5, 2, 1);
		$fileUri = $dir . DIRECTORY_SEPARATOR . $fileName;

		return [
			'fileName' => $fileName,
			'fileUri' => $this->reducedDir . $fileUri,
			'file' => $this->rootPath . $this->reducedDir . $fileUri,
			'path' => $this->reducedDir . $dir,
			'dir' => $this->rootPath . $this->reducedDir . $dir,
		];
	}


	/**
	 * @param string $file
	 * @param string $extension
	 * @param int    $depth
	 *
	 * @return array
	 */
	private function blurSettings(string $file, string $extension, int $depth): array
	{
		$md5 = md5($file);

		$fileName = $depth . '_' . $md5 . '.' . $extension;
		$dir = DIRECTORY_SEPARATOR . Strings::substring($md5, 2, 1);
		$fileUri = $dir . DIRECTORY_SEPARATOR . $fileName;

		return [
			'fileName' => $fileName,
			'fileUri' => $this->blurredDir . $fileUri,
			'file' => $this->rootPath . $this->blurredDir . $fileUri,
			'path' => $this->blurredDir . $dir,
			'dir' => $this->rootPath . $this->blurredDir . $dir,
		];
	}


	/**
	 * @param string      $file
	 * @param int|null    $width
	 * @param int|null    $height
	 * @param int         $flag
	 * @param string|null $outputFormat
	 *
	 * @return string
	 * @throws \JCode\PictureException
	 */
	public function resize(string $file, int $width = null, int $height = null, int $flag = Image::FIT, ?string $outputFormat = null): string
	{
		$url = new Url($file);
		if ($outputFormat === null) {
			$extension = pathinfo($url->getPath(), PATHINFO_EXTENSION);
			if (Strings::length($extension) === 0) {
				throw new PictureException('No file extension found.');
			}
		} else {
			$extension = $outputFormat;
		}

		$extension = Strings::lower($extension);

		if ($width === null && $height === null) {
			throw new PictureException('Must be filled width or height parameter.');
		}

		if (!in_array($extension, ['jpg', 'png', 'jpeg', 'webp', 'gif'], true)) {
			throw new PictureException('Must be filled width or height parameter.');
		}

		$settings = $this->settings($file, $extension, $width, $height, $flag);

		if (!file_exists($settings['file'])) {
			$isUrl = false;
			$mainFile = $this->rootPath . $file;
			if (Strings::substring(Strings::lower($file), 0, 4) === 'http') {
				$mainFile = $file;
				$isUrl = true;
			} elseif (!file_exists($mainFile)) {
				throw new PictureException('File is not exists.');
			}

			[$ow, $oh] = getimagesize($mainFile);

			if ($flag === Image::EXACT && $width === null) {
				$width = $ow;
			}

			if ($flag === Image::EXACT && $height === null) {
				$height = $oh;
			}

			if (($ow <= $width || $oh <= $height) && $flag !== Image::EXACT) {
				if ($isUrl) {
					FileSystem::copy($file, $settings['file']);
					return $settings['fileUri'];
				} else {
					return $file;
				}
			}

			self::canResize($ow, $oh, $width, $height, true);

			if (!is_dir($settings['dir'])) {
				FileSystem::createDir($settings['dir']);
			}

			try {
				$image = Image::fromFile($mainFile);
				$image->resize($width, $height, $flag);

				if ($this->sharpenAfterResize) {
					$image->sharpen();
				}

				if (in_array($extension, ['jpg', 'jpeg'], true)) {
					imageinterlace($image->getImageResource(), 1);
				} // Progressive JPEG

				$iw = $image->getWidth();
				$quality = 85;
				if ($iw >= 1024) {
					$quality = 75;
				}
				if ($iw >= 1920) {
					$quality = 65;
				}
				if ($iw >= 2560) {
					$quality = 50;
				}

				if ($extension === 'png') {
					$type = Image::PNG;
				} elseif ($extension === 'gif') {
					$type = Image::GIF;
				} elseif ($extension === 'webp') {
					$type = Image::WEBP;
				} else {
					$type = Image::JPEG;
				}

				$image->save($settings['file'], $quality, $type);
			} catch (UnknownImageFileException $e) {
				throw new PictureException($e->getMessage(), 0, $e);
			} catch (ImageException $e) {
				throw new PictureException($e->getMessage(), 0, $e);
			} catch (InvalidArgumentException $e) {
				throw new PictureException($e->getMessage(), 0, $e);
			}
		}

		return $settings['fileUri'];
	}


	/**
	 * @param string $file
	 * @param int    $depth
	 *
	 * @return string
	 * @throws \JCode\PictureException
	 */
	public function blur(string $file, int $depth = 10): string
	{
		if (!class_exists('Imagick')) {
			throw new PictureException('For blurring images is required ext-imagick.');
		}

		$url = new Url($file);
		$extension = pathinfo($url->getPath(), PATHINFO_EXTENSION);
		if (Strings::length($extension) === 0) {
			throw new PictureException('No file extension found.');
		}

		$settings = $this->blurSettings($file, $extension, $depth);

		if (!file_exists($settings['file'])) {
			$mainFile = $this->rootPath . $file;
			if (Strings::substring(Strings::lower($file), 0, 4) == 'http') {
				$mainFile = $file;
			} elseif (!file_exists($mainFile)) {
				throw new PictureException('File is not exists.');
			}

			if (!is_dir($settings['dir'])) {
				FileSystem::createDir($settings['dir']);
			}

			try {
				$image = Image::fromFile($mainFile);

				if (in_array($extension, ['jpg', 'jpeg'], true)) {
					imageinterlace($image->getImageResource(), 1);
				} // Progressive JPEG

				$iw = $image->getWidth();
				$quality = 70;
				if ($iw >= 1920) {
					$quality = 60;
				}
				if ($iw >= 2560) {
					$quality = 50;
				}

				$image->save($settings['file'], $quality);

				$image = new \Imagick($settings['file']);
				for ($x = 1; $x <= $depth; $x++) {
					$image->blurimage(10, 3);
				}
				$image->writeimage($settings['file']);
			} catch (UnknownImageFileException $e) {
				throw new PictureException($e->getMessage(), 0, $e);
			} catch (ImageException $e) {
				throw new PictureException($e->getMessage(), 0, $e);
			} catch (InvalidArgumentException $e) {
				throw new PictureException($e->getMessage(), 0, $e);
			} catch (\ImagickException $e) {
				throw new PictureException($e->getMessage(), 0, $e);
			}
		}

		return $settings['fileUri'];
	}


	/**
	 * @param int      $ow
	 * @param int      $oh
	 * @param int|null $nw
	 * @param int|null $nh
	 * @param bool     $throws
	 *
	 * @return bool
	 * @throws \JCode\PictureException
	 */
	public static function canResize(int $ow, int $oh, int $nw = null, int $nh = null, $throws = false): bool
	{
		try {
			if ($nw === null && $nh === null) {
				if ($throws) {
					throw new PictureException('Must be filled width or height parameter.');
				}

				return false;
			}

			$imi = (int) ini_get('memory_limit');
			if ($imi <= 0) {
				if ($throws) {
					throw new PictureException(sprintf('Available memory is %s MB.', number_format($imi / 1024 / 1024, 0)));
				}

				return false;
			}

			$memory_limit = 1024 * 1024 * ($imi - self::MEMORY_RESERVE);
			$constant = 3 * 1.8;

			if ($nh === null && $nw !== null) {
				$nh = $nw * ($oh / $ow);
			}

			if ($nw === null && $nh !== null) {
				$nw = $nh * ($ow / $oh);
			}

			if ($nw === null || $nh === null) {
				if ($throws) {
					throw new PictureException('Is impossible to count new width or height parameter.');
				}

				return false;
			}

			$original_memory = $ow * $oh * $constant;
			$resize_memory = $nw * $nh * $constant;

			$need_memory = $original_memory + $resize_memory;

			if ($throws && $memory_limit < $need_memory) {
				throw new PictureException(sprintf('Available memory is %s MB and needed memory is %s MB.', number_format($memory_limit / 1024 / 1024, 0), number_format($need_memory / 1024 / 1024, 0)));
			}

			return $memory_limit >= $need_memory;
		} catch (StringsException $exception) {
			if ($throws) {
				throw new PictureException($exception->getMessage(), $exception->getCode(), $exception);
			}

			return false;
		}
	}


	/**
	 * @param string      $file
	 * @param int|null    $width
	 * @param int|null    $height
	 * @param int         $flag
	 * @param string|null $outputFormat
	 *
	 * @return bool
	 */
	public function isResize(string $file, int $width = null, int $height = null, int $flag = Image::FIT, ?string $outputFormat = null): bool
	{
		$url = new Url($file);
		if ($outputFormat === null) {
			$extension = pathinfo($url->getPath(), PATHINFO_EXTENSION);
			if (Strings::length($extension) === 0) {
				return false;
			}
		} else {
			$extension = $outputFormat;
		}
		$extension = Strings::lower($extension);
		if (Strings::length($extension) > 1) {
			return file_exists($this->settings($file, $extension, $width, $height, $flag)['file']);
		}

		return false;
	}


	/**
	 * @param string $file
	 * @param int    $depth
	 *
	 * @return bool
	 */
	public function isBlurred(string $file, int $depth = 10): bool
	{
		$url = new Url($file);
		$extension = pathinfo($url->getPath(), PATHINFO_EXTENSION);
		if (Strings::length($extension) > 1) {
			return file_exists($this->blurSettings($file, $extension, $depth)['file']);
		}

		return false;
	}
}
