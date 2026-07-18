<?php

declare(strict_types=1);

namespace Celema\Verba\Tests;

use Celema\Verba\Verba;
use FilesystemIterator;
use PHPUnit\Framework\TestCase as BaseTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * @internal
 */
class TestCase extends BaseTestCase
{
	private string $tmp = '';

	protected function tearDown(): void
	{
		Verba::deactivate();

		if ($this->tmp !== '' && is_dir($this->tmp)) {
			$this->remove($this->tmp);
		}
	}

	protected function i18n(): string
	{
		return __DIR__ . '/Fixtures/i18n';
	}

	protected function tmpDir(): string
	{
		if ($this->tmp === '') {
			$this->tmp = sys_get_temp_dir() . '/verba-' . bin2hex(random_bytes(6));
			mkdir($this->tmp, 0o755, true);
		}

		return $this->tmp;
	}

	protected function write(string $relative, string $contents): string
	{
		$path = $this->tmpDir() . '/' . $relative;
		$dir = dirname($path);

		if (!is_dir($dir)) {
			mkdir($dir, 0o755, true);
		}

		file_put_contents($path, $contents);

		return $path;
	}

	private function remove(string $dir): void
	{
		$tree = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($tree as $entry) {
			if (!$entry instanceof SplFileInfo) {
				continue;
			}

			if ($entry->isDir()) {
				rmdir($entry->getPathname());
			} else {
				unlink($entry->getPathname());
			}
		}

		rmdir($dir);
	}
}
