<?php

/*	This software is the unpublished, confidential, proprietary, intellectual
	property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
	or used in any manner without expressed written consent from Kim David Software, LLC.
	Kim David Software, LLC owns all rights to this work and intends to keep this
	software confidential so as to maintain its value as a trade secret.

	Copyright 2004-Present, Kim David Software, LLC.

	WARNING! This code is part of the Kim David Software's Coreware system.
	Changes made to this source file will be lost when new versions of the
	system are installed.
*/

/**
 * class PointLocation
 *
 * Class to determine to location of a point relative to a polygon. The return of pointInPolygon will be "vertex" (on one of the corners of the polygon),
 * "boundary", "inside" or "outside". The points of the polygon and the point being checked should be an associative array with latitude and longitude.
 *
 * @author Kim D Geiger
 */
class PointLocation {
	var $iPolygon = array();

	function __construct($polygon) {
		if (is_array($polygon)) {
			$this->iPolygon = $polygon;
		} else {
			$points = getContentLines($polygon);
			foreach ($points as $thisPoint) {
				$pointParts = explode(",",$thisPoint);
				$this->iPolygon[] = array("latitude"=>$pointParts[0],"longitude"=>$pointParts[1]);
			}
		}
		$firstEntry = $this->iPolygon[0];
		$lastEntry = $this->iPolygon[count($this->iPolygon) - 1];
		if ($firstEntry != $lastEntry) {
			$this->iPolygon[] = $firstEntry;
		}
	}

	function pointInPolygon($point) {
		// Check if the point sits exactly on a vertex
		if ($this->pointOnVertex($point) == true) {
			return "vertex";
		}

		// Check if the point is inside the polygon or on the boundary
		$intersections = 0;
		$verticesCount = count($this->iPolygon);

		for ($i=1; $i < $verticesCount; $i++) {
			$vertex1 = $this->iPolygon[$i-1];
			$vertex2 = $this->iPolygon[$i];
			if ($vertex1['longitude'] == $vertex2['longitude'] && $vertex1['longitude'] == $point['longitude'] &&
				$point['latitude'] > min($vertex1['latitude'], $vertex2['latitude']) && $point['latitude'] < max($vertex1['latitude'], $vertex2['latitude'])) {
				return "boundary";
			}
			if ($point['longitude'] > min($vertex1['longitude'], $vertex2['longitude']) && $point['longitude'] <= max($vertex1['longitude'], $vertex2['longitude']) &&
				$point['latitude'] <= max($vertex1['latitude'], $vertex2['latitude']) && $vertex1['longitude'] != $vertex2['longitude']) {
				$latitudeIntersect = ($point['longitude'] - $vertex1['longitude']) * ($vertex2['latitude'] - $vertex1['latitude']) / ($vertex2['longitude'] - $vertex1['longitude']) + $vertex1['latitude'];
				if ($latitudeIntersect == $point['latitude']) {
					return "boundary";
				}
				if ($vertex1['latitude'] == $vertex2['latitude'] || $point['latitude'] <= $latitudeIntersect) {
					$intersections++;
				}
			}
		}
		// If the number of edges we passed through is odd, then it's in the polygon.
		return ($intersections % 2 != 0 ? "inside" : "outside");
	}

	function isPointInPolygon($point) {
		$pointLocation = $this->pointInPolygon($point);
		return ($pointLocation != "inside");
	}

	function pointOnVertex($point) {
		foreach ($this->iPolygon as $vertex) {
			if ($point['latitude'] == $vertex['latitude'] && $point['longitude'] == $vertex['longitude']) {
				return true;
			}
		}
	}
	
	function getCentralPoint() {
		$minimumLatitude = "";
		$maximumLatitude = "";
		$minimumLongitude = "";
		$maximumLongitude = "";
		foreach ($this->iPolygon as $thisPoint) {
			if (strlen($minimumLatitude) == 0 || $thisPoint['latitude'] < $minimumLatitude) {
				$minimumLatitude = $thisPoint['latitude'];
			}
			if (strlen($minimumLongitude) == 0 || $thisPoint['longitude'] < $minimumLongitude) {
				$minimumLongitude = $thisPoint['longitude'];
			}
			if (strlen($minimumLatitude) == 0 || $thisPoint['latitude'] > $maximumLatitude) {
				$maximumLatitude = $thisPoint['latitude'];
			}
			if (strlen($minimumLongitude) == 0 || $thisPoint['longitude'] > $maximumLongitude) {
				$maximumLongitude = $thisPoint['longitude'];
			}
		}
		return array("latitude"=>$minimumLatitude + (($maximumLatitude - $minimumLatitude) * 2),"longitude"=>$minimumLongitude + (($maximumLongitude - $minimumLongitude) * 2));
	}

	function getEnclosingCircle() {
		$centerPoint = $this->getCentralPoint();
		$radius = 0;
		foreach ($this->iPolygon as $thisPoint) {
			$thisDistance = calculateDistance($thisPoint,$centerPoint);
			if ($thisDistance > $radius) {
				$radius = $thisDistance;
			}
		}
		return array("center"=>$centerPoint,"radius"=>$radius);
	}
}
?>
