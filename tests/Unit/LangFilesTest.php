<?php
/* Copyright (C) 2026 Aohzan (fork: https://github.com/Aohzan/dolibarr-micro-entreprise-compta)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 */

use PHPUnit\Framework\TestCase;

/**
 * Every translation key exists in every language, exactly once.
 */
final class LangFilesTest extends TestCase
{
	private const LANGS_DIR = __DIR__.'/../../langs';

	/**
	 * Read the keys of a Dolibarr .lang file, in file order.
	 *
	 * @param string $lang Language code, e.g. fr_FR
	 * @return list<string>
	 */
	private static function keys(string $lang): array
	{
		$keys = array();
		foreach (file(self::LANGS_DIR.'/'.$lang.'/automicroent.lang', FILE_IGNORE_NEW_LINES) as $line) {
			$line = trim($line);
			if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
				continue;
			}
			$keys[] = trim(substr($line, 0, strpos($line, '=')));
		}
		return $keys;
	}

	/**
	 * Languages shipped with the module.
	 *
	 * @return array<string, array{string}>
	 */
	public static function languages(): array
	{
		$langs = array();
		foreach (glob(self::LANGS_DIR.'/*', GLOB_ONLYDIR) as $dir) {
			$langs[basename($dir)] = array(basename($dir));
		}
		return $langs;
	}

	/**
	 * @param string $lang Language code
	 * @return void
	 */
	#[PHPUnit\Framework\Attributes\DataProvider('languages')]
	public function testKeysAreUnique(string $lang): void
	{
		$keys = self::keys($lang);
		$this->assertSame(array(), array_keys(array_filter(array_count_values($keys), function ($n) {
			return $n > 1;
		})));
	}

	/**
	 * @param string $lang Language code
	 * @return void
	 */
	#[PHPUnit\Framework\Attributes\DataProvider('languages')]
	public function testSameKeysAsFrench(string $lang): void
	{
		$reference = self::keys('fr_FR');
		$keys = self::keys($lang);
		sort($reference);
		sort($keys);
		$this->assertSame($reference, $keys);
	}
}
