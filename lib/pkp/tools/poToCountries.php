<?php

/**
 * @file tools/poToCountries.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class poToCountries
 * @ingroup tools
 *
 * @brief CLI tool to convert a .PO file for ISO3166 into the countries.xml format
 * supported by the PKP suite. These .po files can be sourced from e.g.:
 * https://packages.debian.org/source/sid/iso-codes
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

define('PO_TO_CSV_TOOL', '/usr/bin/po2csv');

class poToCountries extends CommandLineTool {
	/** @var $locale string */
	var $locale;

	/** @var $translationFile string */
	var $translationFile;

	/**
	 * Constructor
	 */
	function __construct($argv = array()) {
		parent::__construct($argv);

		array_shift($argv); // Shift the tool name off the top

		$this->locale = array_shift($argv);
		$this->translationFile = array_shift($argv);

		if (	!PKPLocale::isLocaleValid($this->locale) ||
			empty($this->translationFile) ||
			!file_exists($this->translationFile)
		) {
			$this->usage();
			exit(1);
		}
	}

	/**
	 * Print command usage information.
	 */
	function usage() {
		echo "Script to convert PO file to PKP's ISO3166 XML format\n"
			. "Usage: {$this->scriptName} locale /path/to/translation.po\n";
	}

	/**
	 * Rebuild the search index for all articles in all journals.
	 */
	function execute() {
		// Read the translated file as a map from English => Whatever
		$ih = popen(PO_TO_CSV_TOOL . ' ' . escapeshellarg($this->translationFile), 'r');
		if (!$ih) die ('Unable to read ' . $this->translationFile . ' using ' . PO_TO_CSV_TOOL . "\n");

		$translationMap = array();
		while ($row = fgetcsv($ih)) {
			if (count($row) != 3) continue;
			list($comment, $english, $translation) = $row;
			$translationMap[$english] = $translation;
		}
		fclose($ih);

		// Get the English map
		$countryDao = DAORegistry::getDAO('CountryDAO');
		$countries = $countryDao->getCountries();

		// Generate a map of code => translation
		$outputMap = array();
		foreach ($countries as $code => $english) {
			if (!isset($translationMap[$english])) {
				echo "WARNING: Unknown country \"$english\"! Using English as default.\n";
				$outputMap[$code] = $english;
			} else {
				$outputMap[$code] = $translationMap[$english];
				unset($translationMap[$english]);
			}
		}

		// Use the map to convert the country list to the new locale
		$tfn = 'locale/' . $this->locale . '/countries.xml';
		$ofn = 'lib/pkp/' . $tfn;
		$oh = fopen($ofn, 'w');
		if (!$oh) die ("Unable to $ofn for writing.\n");

		fwrite($oh, '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE countries SYSTEM "../../dtd/countries.dtd">

<!--
  * ' . $tfn . '
  *
  * Copyright (c) 2014-2018 Simon Fraser University
  * Copyright (c) 2000-2018 John Willinsky
  * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
  *
  * Localized list of countries.
  * Please don\'t edit or translate. This file is automatically generated using
  * the ISO 3166 files of Debian\'s iso-codes package
  * (https://packages.debian.org/sid/all/iso-codes) using the
  * tools/poToCountries.php tool.
  -->

<countries>
');
		foreach ($outputMap as $code => $translation) {
			fwrite($oh, "	<country name=\"$translation\" code=\"$code\"/>\n");
		}

		fwrite($oh, "</countries>");
		fclose($oh);
	}
}

$tool = new poToCountries(isset($argv) ? $argv : array());
$tool->execute();

?>
