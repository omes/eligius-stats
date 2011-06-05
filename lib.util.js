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