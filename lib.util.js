/*Copyright (C) 2011 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

var EligiusUtils = {};

EligiusUtils.formatHashrate = function(rate, axis) {
	if(rate == 0) {
		return "0 Hashes/sec";
	} else if(rate < 10 * 1000000) {
		return (rate / 1000).toFixed(2) + " Khashes/sec";
	} else if(rate < 10 * 1000000000) {
		return (rate / 1000000).toFixed(2) + " Mhashes/sec";
	} else return (rate / 1000000000).toFixed(2) + " Ghashes/sec";
}

EligiusUtils.formatBTC = function(money, axis) {
	return money.toFixed(3) + " BTC";
}

EligiusUtils.toggleAutorefresh = function() {
	if(EligiusUtils.autorefresh) {
		EligiusUtils.autorefresh = false;
		clearTimeout(EligiusUtils.autorefresh_t);
		$("#autorefresh_message").html(' &mdash; The page will no longer refresh automatically.');
	} else {
		EligiusUtils.autorefresh = true;
		EligiusUtils.autorefresh_t = setTimeout(function() {
			window.location.replace("?autorefresh=1");
		}, 300000);
		$("#autorefresh_message").html(' &mdash; The page will refresh automatically every 5 minutes.');
	}
}

EligiusUtils.movingAverage = function(data, window, interval) {
	var points = [];
	var c = data.length;
	if(c == 0) return points;

	var start = data[0][0];
	var stop = start + window;
	var sum, i;
	var k = 0;
	while(stop <= data[c - 1][0]) {
		sum = 0;
		for(i = k; i < (c - 1); ++i) {
			if(data[i + 1][0] < start) {
				++k;
				continue;
			}

			if(data[i + 1][0] <= stop) {
				sum += data[i][1] * Math.min(data[i + 1][0] - data[i][0], data[i + 1][0] - start);
			} else {
				sum += data[i][1] * Math.min(stop - data[i][0], stop - start);
				break;
			}
		}

		points.push([stop, sum / window]);

		start += interval;
		stop += interval;
	}

	return points;
}

EligiusUtils.shiftData = function(data, shiftAmount) {
	var points = [];
	var c = data.length;
	var i;
	for(i = 0; i < c; ++i) {
		points.push([data[i][0], data[i][1] + shiftAmount]);
	}

	return points;
}

EligiusUtils.findDataMin = function(data) {
	var c = data.length;
	var min = 0;
	for(var i = 1; i < c; ++i) {
		if(data[i][1] < data[min][1]) {
			min = i;
		}
	}

	return min;
}

EligiusUtils.findDataMax = function(data) {
	var c = data.length;
	var max = 0;
	for(var i = 1; i < c; ++i) {
		if(data[i][1] > data[max][1]) {
			max = i;
		}
	}

	return max;
}