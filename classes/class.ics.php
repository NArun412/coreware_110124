<?php

/**
 * This is free and unencumbered software released into the public domain.
 *
 * Anyone is free to copy, modify, publish, use, compile, sell, or
 * distribute this software, either in source code form or as a compiled
 * binary, for any purpose, commercial or non-commercial, and by any
 * means.
 *
 * In jurisdictions that recognize copyright laws, the author or authors
 * of this software dedicate any and all copyright interest in the
 * software to the public domain. We make this dedication for the benefit
 * of the public at large and to the detriment of our heirs and
 * successors. We intend this dedication to be an overt act of
 * relinquishment in perpetuity of all present and future rights to this
 * software under copyright law.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS BE LIABLE FOR ANY CLAIM, DAMAGES OR
 * OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
 * ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 *
 * For more information, please refer to <http://unlicense.org>
 *
 * ICS.php
 * =============================================================================
 * Use this class to create an .ics file.
 *
 *
 * Usage
 * -----------------------------------------------------------------------------
 * Basic usage - generate ics file contents (see below for available properties):
 *   $ics = new ICS($props);
 *   $icsFileContents = $ics->toString();
 *
 * Setting properties after instantiation
 *   $ics = new ICS();
 *   $ics->set('summary', 'My awesome event');
 *
 * You can also set multiple properties at the same time by using an array:
 *   $ics->set(array(
 *     'dtstart' => 'now + 30 minutes',
 *     'dtend' => 'now + 1 hour'
 *   ));
 *
 * Available properties
 * -----------------------------------------------------------------------------
 * description
 *   String description of the event.
 * dtend
 *   A date/time stamp designating the end of the event. You can use either a
 *   DateTime object or a PHP datetime format string (e.g. "now + 1 hour").
 * dtstart
 *   A date/time stamp designating the start of the event. You can use either a
 *   DateTime object or a PHP datetime format string (e.g. "now + 1 hour").
 * location
 *   String address or description of the location of the event.
 * summary
 *   String short summary of the event - usually used as the title.
 * url
 *   A url to attach to the the event. Make sure to add the protocol (http://
 *   or https://).
 */

class ICS {
	const DT_FORMAT = 'Ymd\THis\Z';

	protected $properties = array();
	private $availableProperties = array(
		'description',
		'dtend',
		'dtstart',
		'location',
		'summary',
		'url'
	);

	public function __construct($properties = array()) {
		$this->set($properties);
	}

	public function set($key, $value = false) {
		if (is_array($key)) {
			foreach ($key as $k => $v) {
				$this->set($k, $v);
			}
		} else {
			if (in_array($key, $this->availableProperties)) {
				$this->properties[$key] = $this->sanitizeValue($value, $key);
			}
		}
	}

	private function sanitizeValue($value, $key = false) {
		switch ($key) {
			case 'dtend':
			case 'dtstamp':
			case 'dtstart':
				$value = $this->formatTimestamp($value);
				break;
			default:
				$value = $this->escapeString($value);
		}

		return $value;
	}

	private function formatTimestamp($timestamp) {
		$dt = new DateTime($timestamp);
		$offset = date("Z");
		date_sub($dt, date_interval_create_from_date_string($offset . ' seconds'));
		return $dt->format(self::DT_FORMAT);
	}

	private function escapeString($string) {
		return preg_replace('/([\,;])/', '\\\$1', $string);
	}

	public function toString() {
		$rows = $this->buildProps();
		return implode("\r\n", $rows);
	}

	private function buildProps() {
		// Build ICS properties - add header
		$icsProperties = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//hacksw/handcal//NONSGML v1.0//EN',
			'CALSCALE:GREGORIAN',
			'BEGIN:VEVENT'
		);

		// Build ICS properties - add header
		$props = array();
		foreach ($this->properties as $key => $value) {
			$props[strtoupper($key . ($key === 'url' ? ';VALUE=URI' : ''))] = $value;
		}

		// Set some default values
		$props['DTSTAMP'] = $this->formatTimestamp('now');
		$props['UID'] = uniqid();

		// Append properties
		foreach ($props as $key => $value) {
			$icsProperties[] = "$key:$value";
		}

		// Build ICS properties - add footer
		$icsProperties[] = 'END:VEVENT';
		$icsProperties[] = 'END:VCALENDAR';

		return $icsProperties;
	}
}
