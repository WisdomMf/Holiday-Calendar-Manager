/**
 * Holiday Calendar - dependency-free interactive month calendar.
 */
( function () {
	'use strict';

	if ( typeof HC_DATA === 'undefined' ) {
		return;
	}

	var data = HC_DATA;

	function isEnhancedTheme() {
		return 'enhanced' === data.calendarTheme;
	}

	function pad( n ) {
		return n < 10 ? '0' + n : '' + n;
	}

	function ymd( y, m, d ) {
		return y + '-' + pad( m + 1 ) + '-' + pad( d );
	}

	function escapeHtml( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function isLightMarkColor( hex ) {
		var raw = String( hex ).replace( /^#/, '' );
		if ( 3 === raw.length ) {
			raw = raw[ 0 ] + raw[ 0 ] + raw[ 1 ] + raw[ 1 ] + raw[ 2 ] + raw[ 2 ];
		}
		if ( 6 !== raw.length ) {
			return false;
		}
		var r = parseInt( raw.substr( 0, 2 ), 16 );
		var g = parseInt( raw.substr( 2, 2 ), 16 );
		var b = parseInt( raw.substr( 4, 2 ), 16 );
		var luminance = ( 0.299 * r + 0.587 * g + 0.114 * b ) / 255;
		return luminance > 0.58;
	}

	// Weekday numbers (0-6) re-ordered to honour the "week starts on" setting.
	function orderedWeekdays() {
		var out = [];
		for ( var i = 0; i < 7; i++ ) {
			out.push( ( data.weekStartsOn + i ) % 7 );
		}
		return out;
	}

	function formatListDate( dateKey ) {
		var dt = new Date( dateKey + 'T00:00:00' );
		return data.monthNames[ dt.getMonth() ] + ' ' + dt.getDate();
	}

	function formatListDateFull( dateKey ) {
		var dt = new Date( dateKey + 'T00:00:00' );
		return data.monthNames[ dt.getMonth() ] + ' ' + dt.getDate() + ', ' + dt.getFullYear();
	}

	function entryEndDate( entry ) {
		return entry.date_end && entry.date_end !== entry.date ? entry.date_end : entry.date;
	}

	function formatRangeListDate( startKey, endKey ) {
		var start = new Date( startKey + 'T00:00:00' );
		var end = new Date( endKey + 'T00:00:00' );
		if ( start.getFullYear() === end.getFullYear() && start.getMonth() === end.getMonth() ) {
			return data.monthNames[ start.getMonth() ] + ' ' + start.getDate() + ' – ' + end.getDate();
		}
		if ( start.getFullYear() === end.getFullYear() ) {
			return data.monthNames[ start.getMonth() ] + ' ' + start.getDate() + ' – ' +
				data.monthNames[ end.getMonth() ] + ' ' + end.getDate();
		}
		return formatListDateFull( startKey ) + ' – ' + formatListDateFull( endKey );
	}

	function formatRangeListDateFull( startKey, endKey ) {
		var start = new Date( startKey + 'T00:00:00' );
		var end = new Date( endKey + 'T00:00:00' );
		if ( start.getFullYear() === end.getFullYear() && start.getMonth() === end.getMonth() ) {
			return data.monthNames[ start.getMonth() ] + ' ' + start.getDate() + ' – ' + end.getDate() + ', ' + start.getFullYear();
		}
		if ( start.getFullYear() === end.getFullYear() ) {
			return data.monthNames[ start.getMonth() ] + ' ' + start.getDate() + ' – ' +
				data.monthNames[ end.getMonth() ] + ' ' + end.getDate() + ', ' + start.getFullYear();
		}
		return formatListDateFull( startKey ) + ' – ' + formatListDateFull( endKey );
	}

	function formatEntryListDate( entry ) {
		if ( 'range' === entry.type && entry.date_end && entry.date_end !== entry.date ) {
			return formatRangeListDate( entry.date, entry.date_end );
		}
		return formatListDate( entry.date );
	}

	function formatEntryListDateFull( entry ) {
		if ( 'range' === entry.type && entry.date_end && entry.date_end !== entry.date ) {
			return formatRangeListDateFull( entry.date, entry.date_end );
		}
		return formatListDateFull( entry.date );
	}

	function entryOverlapsMonth( entry, year, month ) {
		var monthStart = ymd( year, month, 1 );
		var daysInMonth = new Date( year, month + 1, 0 ).getDate();
		var monthEnd = ymd( year, month, daysInMonth );
		var end = entryEndDate( entry );
		return entry.date <= monthEnd && end >= monthStart;
	}

	function getHolidayEntries() {
		if ( data.entries && data.entries.length ) {
			return data.entries.slice();
		}
		var fallback = [];
		var key;
		for ( key in data.dates ) {
			if ( ! Object.prototype.hasOwnProperty.call( data.dates, key ) ) {
				continue;
			}
			fallback.push( {
				id: key,
				date: key,
				date_end: key,
				label: data.dates[ key ].label,
				color: data.dates[ key ].color,
				type: 'single',
			} );
		}
		return fallback;
	}

	function getAllHolidays() {
		var holidays = getHolidayEntries().map( function ( entry ) {
			return {
				date: entry.date,
				date_end: entryEndDate( entry ),
				label: entry.label,
				color: entry.color,
				type: entry.type || 'single',
			};
		} );

		holidays.sort( function ( a, b ) {
			return a.date.localeCompare( b.date );
		} );

		return holidays;
	}

	var EXPORT = {
		width: 900,
		padding: 36,
		colors: {
			gold: '#977d0a',
			purpleDeep: '#260f53',
			purple: '#670086',
			muted: '#6b5b7a',
			border: '#e8e0f0',
			weekendBg: '#f7f2fa',
			white: '#ffffff',
		},
	};

	function exportBrandColor() {
		var brand = data.brand || {};
		return brand.headerColor || EXPORT.colors.purpleDeep;
	}

	function exportBrandBorderColor( brandHex ) {
		return mixTwoHex( EXPORT.colors.gold, 40, brandHex );
	}

	function exportColors() {
		var brandHex = exportBrandColor();
		return {
			gold: exportBrandBorderColor( brandHex ),
			purpleDeep: brandHex,
			purple: EXPORT.colors.purple,
			muted: EXPORT.colors.muted,
			border: EXPORT.colors.border,
			weekendBg: EXPORT.colors.weekendBg,
			white: EXPORT.colors.white,
		};
	}

	function exportContentWidth() {
		return EXPORT.width - EXPORT.padding * 2;
	}

	function exportGridLayout( contentW ) {
		var gridGap = 6;
		var cols = 7;
		var cell = ( contentW - ( cols - 1 ) * gridGap ) / cols;
		return {
			gridGap: gridGap,
			cell: cell,
			gridW: contentW,
			gridX: 0,
		};
	}

	function hexToRgb( hex ) {
		var raw = String( hex ).replace( /^#/, '' );
		if ( 3 === raw.length ) {
			raw = raw[ 0 ] + raw[ 0 ] + raw[ 1 ] + raw[ 1 ] + raw[ 2 ] + raw[ 2 ];
		}
		if ( 6 !== raw.length ) {
			return { r: 151, g: 125, b: 10 };
		}
		return {
			r: parseInt( raw.substr( 0, 2 ), 16 ),
			g: parseInt( raw.substr( 2, 2 ), 16 ),
			b: parseInt( raw.substr( 4, 2 ), 16 ),
		};
	}

	function mixHex( hex, whitePct ) {
		var c = hexToRgb( hex );
		var w = Math.max( 0, Math.min( 100, whitePct ) ) / 100;
		var r = Math.round( c.r + ( 255 - c.r ) * w );
		var g = Math.round( c.g + ( 255 - c.g ) * w );
		var b = Math.round( c.b + ( 255 - c.b ) * w );
		return 'rgb(' + r + ',' + g + ',' + b + ')';
	}

	function mixTwoHex( hexA, pctA, hexB ) {
		var a = hexToRgb( hexA );
		var b = hexToRgb( hexB );
		var t = Math.max( 0, Math.min( 100, pctA ) ) / 100;
		var r = Math.round( a.r * t + b.r * ( 1 - t ) );
		var g = Math.round( a.g * t + b.g * ( 1 - t ) );
		var bl = Math.round( a.b * t + b.b * ( 1 - t ) );
		return 'rgb(' + r + ',' + g + ',' + bl + ')';
	}

	function truncateText( ctx, text, maxWidth ) {
		if ( ctx.measureText( text ).width <= maxWidth ) {
			return text;
		}
		var ellipsis = '…';
		var trimmed = text;
		while ( trimmed.length > 1 && ctx.measureText( trimmed + ellipsis ).width > maxWidth ) {
			trimmed = trimmed.slice( 0, -1 );
		}
		return trimmed + ellipsis;
	}

	function loadImage( url ) {
		return new Promise( function ( resolve ) {
			if ( ! url ) {
				resolve( null );
				return;
			}
			var img = new Image();
			img.crossOrigin = 'anonymous';
			img.onload = function () {
				resolve( img );
			};
			img.onerror = function () {
				resolve( null );
			};
			img.src = url;
		} );
	}

	function slugify( text ) {
		return String( text )
			.toLowerCase()
			.replace( /[^a-z0-9]+/g, '-' )
			.replace( /^-+|-+$/g, '' ) || 'calendar';
	}

	function drawRoundRect( ctx, x, y, w, h, r ) {
		var radius = Math.min( r, w / 2, h / 2 );
		ctx.beginPath();
		ctx.moveTo( x + radius, y );
		ctx.lineTo( x + w - radius, y );
		ctx.quadraticCurveTo( x + w, y, x + w, y + radius );
		ctx.lineTo( x + w, y + h - radius );
		ctx.quadraticCurveTo( x + w, y + h, x + w - radius, y + h );
		ctx.lineTo( x + radius, y + h );
		ctx.quadraticCurveTo( x, y + h, x, y + h - radius );
		ctx.lineTo( x, y + radius );
		ctx.quadraticCurveTo( x, y, x + radius, y );
		ctx.closePath();
	}

	function listSectionHeight( holidays ) {
		var enhanced = isEnhancedTheme();
		var dividerGap = 24;
		var listTitleH = enhanced ? 36 : 32;
		var listItemH = enhanced ? 44 : 38;
		var listGap = enhanced ? 10 : 8;
		var listCount = holidays.length ? holidays.length : 1;
		return dividerGap + listTitleH + listCount * listItemH + ( listCount - 1 ) * listGap;
	}

	function exportHeaderHeight() {
		return isEnhancedTheme() ? 84 : 76;
	}

	function exportHeightAll( holidays ) {
		var p = EXPORT.padding;
		var sectionGap = 24;
		return p + exportHeaderHeight() + sectionGap + listSectionHeight( holidays ) + p;
	}

	function exportHeightMonth( holidays ) {
		var p = EXPORT.padding;
		var contentW = exportContentWidth();
		var layout = exportGridLayout( contentW );
		var monthTitleH = isEnhancedTheme() ? 50 : 44;
		var dowH = isEnhancedTheme() ? 32 : 28;
		var gridRows = 6;
		var gridH = gridRows * layout.cell + ( gridRows - 1 ) * layout.gridGap;
		var sectionGap = 24;
		return p + exportHeaderHeight() + sectionGap + monthTitleH + dowH + gridH + sectionGap + listSectionHeight( holidays ) + p;
	}

	function drawBrandingBand( ctx, logoImg, y, width, p, contentW, colors ) {
		var brand = data.brand || {};
		var headerH = exportHeaderHeight();
		var radius = isEnhancedTheme() ? 14 : 12;
		var padX = 24;
		var logoGap = 16;
		var logoSize = isEnhancedTheme() ? 56 : 48;
		var fontFamily = ' -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';

		drawRoundRect( ctx, p, y, contentW, headerH, radius );

		ctx.fillStyle = colors.purpleDeep;
		ctx.fill();

		if ( isEnhancedTheme() ) {
			ctx.fillStyle = colors.gold;
			ctx.fillRect( p, y, 5, headerH );
		}

		ctx.strokeStyle = colors.gold;
		ctx.lineWidth = isEnhancedTheme() ? 4 : 3;
		ctx.stroke();

		var textX = p + padX;
		var maxTextW = contentW - padX * 2;

		if ( logoImg ) {
			var aspect = logoImg.width / logoImg.height;
			var drawW = logoSize;
			var drawH = logoSize;
			if ( aspect > 1 ) {
				drawH = logoSize / aspect;
			} else {
				drawW = logoSize * aspect;
			}
			ctx.drawImage( logoImg, p + padX, y + ( headerH - drawH ) / 2, drawW, drawH );
			textX = p + padX + logoSize + logoGap;
			maxTextW = contentW - ( textX - p ) - padX;
		}

		var subParts = [];
		if ( brand.tagline ) {
			subParts.push( brand.tagline );
		}
		if ( brand.siteUrl ) {
			subParts.push( brand.siteUrl.replace( /^https?:\/\//, '' ).replace( /\/$/, '' ) );
		}
		var hasSub = subParts.length > 0;
		var nameLineH = isEnhancedTheme() ? 32 : 28;
		var subLineH = isEnhancedTheme() ? 18 : 16;
		var lineGap = 4;
		var textBlockH = nameLineH + ( hasSub ? lineGap + subLineH : 0 );
		var textY = y + ( headerH - textBlockH ) / 2;

		ctx.textAlign = 'left';
		ctx.textBaseline = 'top';
		ctx.fillStyle = '#ffffff';
		ctx.font = ( isEnhancedTheme() ? 'bold 28px' : 'bold 26px' ) + fontFamily;
		ctx.fillText( truncateText( ctx, brand.siteName || '', maxTextW ), textX, textY );

		if ( hasSub ) {
			ctx.fillStyle = 'rgba(255,255,255,0.85)';
			ctx.font = ( isEnhancedTheme() ? '15px' : '14px' ) + fontFamily;
			ctx.fillText( truncateText( ctx, subParts.join( ' · ' ), maxTextW ), textX, textY + nameLineH + lineGap );
		}

		return y + headerH + 24;
	}

	function drawHolidaysList( ctx, holidays, listTitle, emptyText, y, width, p, contentW, colors ) {
		var enhanced = isEnhancedTheme();
		var listTitleSize = enhanced ? 17 : 16;
		var listItemH = enhanced ? 44 : 38;
		var listGap = enhanced ? 10 : 8;
		var itemRadius = enhanced ? 12 : 10;

		ctx.strokeStyle = colors.border;
		ctx.lineWidth = enhanced ? 2 : 1;
		ctx.beginPath();
		ctx.moveTo( p, y );
		ctx.lineTo( width - p, y );
		ctx.stroke();
		y += 24;

		ctx.fillStyle = enhanced ? colors.purple : colors.purpleDeep;
		ctx.font = 'bold ' + listTitleSize + 'px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
		ctx.textAlign = 'left';
		ctx.textBaseline = 'top';
		ctx.fillText( enhanced ? listTitle.toUpperCase() : listTitle, p, y );
		y += enhanced ? 36 : 32;

		if ( ! holidays.length ) {
			drawRoundRect( ctx, p, y, contentW, listItemH, itemRadius );
			ctx.fillStyle = colors.weekendBg;
			ctx.fill();
			if ( enhanced ) {
				ctx.strokeStyle = colors.border;
				ctx.lineWidth = 1;
				ctx.setLineDash( [ 6, 4 ] );
				ctx.stroke();
				ctx.setLineDash( [] );
			}
			ctx.fillStyle = colors.muted;
			ctx.font = '14px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
			ctx.textAlign = 'center';
			ctx.textBaseline = 'middle';
			ctx.fillText( emptyText, width / 2, y + listItemH / 2 );
			ctx.textAlign = 'left';
			return y + listItemH;
		}

		for ( var i = 0; i < holidays.length; i++ ) {
			var item = holidays[ i ];
			drawRoundRect( ctx, p, y, contentW, listItemH, itemRadius );
			ctx.fillStyle = colors.white;
			ctx.fill();
			ctx.strokeStyle = colors.border;
			ctx.lineWidth = 1;
			ctx.stroke();

			if ( enhanced ) {
				ctx.save();
				ctx.shadowColor = 'rgba(38,15,83,0.12)';
				ctx.shadowBlur = 8;
				ctx.shadowOffsetY = 3;
				drawRoundRect( ctx, p, y, contentW, listItemH, itemRadius );
				ctx.strokeStyle = 'rgba(103,0,134,0.12)';
				ctx.stroke();
				ctx.restore();

				ctx.strokeStyle = colors.gold;
				ctx.lineWidth = 3;
				ctx.beginPath();
				ctx.moveTo( p + 1.5, y + itemRadius );
				ctx.lineTo( p + 1.5, y + listItemH - itemRadius );
				ctx.stroke();
			}

			var swatchR = enhanced ? 7 : 6;
			ctx.beginPath();
			ctx.arc( p + 22, y + listItemH / 2, swatchR, 0, Math.PI * 2 );
			ctx.fillStyle = item.color;
			ctx.fill();
			if ( enhanced ) {
				ctx.strokeStyle = 'rgba(255,255,255,0.9)';
				ctx.lineWidth = 2;
				ctx.stroke();
			}

			ctx.fillStyle = colors.purple;
			ctx.font = 'bold ' + ( enhanced ? 15 : 14 ) + 'px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
			ctx.textBaseline = 'middle';
			ctx.textAlign = 'left';
			var dateText = formatEntryListDateFull( item );
			ctx.fillText( dateText, p + 40, y + listItemH / 2 );

			var dateW = ctx.measureText( dateText ).width;
			ctx.fillStyle = colors.purpleDeep;
			ctx.font = ( enhanced ? '15px' : '14px' ) + ' -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
			ctx.fillText(
				truncateText( ctx, item.label, contentW - 40 - dateW - 24 ),
				p + 40 + dateW + 16,
				y + listItemH / 2
			);

			y += listItemH + listGap;
		}

		return y;
	}

	function drawExportImageAll( logoImg ) {
		var holidays = getAllHolidays();
		var width = EXPORT.width;
		var height = exportHeightAll( holidays );
		var canvas = document.createElement( 'canvas' );
		var ctx = canvas.getContext( '2d' );
		var p = EXPORT.padding;
		var colors = exportColors();
		var contentW = exportContentWidth();

		canvas.width = width;
		canvas.height = height;

		var enhanced = isEnhancedTheme();
		if ( enhanced ) {
			ctx.fillStyle = '#f8f4fc';
		} else {
			ctx.fillStyle = colors.white;
		}
		ctx.fillRect( 0, 0, width, height );

		var y = drawBrandingBand( ctx, logoImg, p, width, p, contentW, colors );
		drawHolidaysList( ctx, holidays, data.i18n.allHolidays, data.i18n.noHolidaysAll, y, width, p, contentW, colors );

		return canvas;
	}

	function drawExportImageMonth( cal, logoImg ) {
		var holidays = cal.getMonthHolidays();
		var width = EXPORT.width;
		var height = exportHeightMonth( holidays );
		var canvas = document.createElement( 'canvas' );
		var ctx = canvas.getContext( '2d' );
		var p = EXPORT.padding;
		var colors = exportColors();
		var y = p;
		var contentW = exportContentWidth();
		var layout = exportGridLayout( contentW );
		var cell = layout.cell;
		var gridGap = layout.gridGap;
		var gridX = p + layout.gridX;
		var weekdayOrder = orderedWeekdays();

		canvas.width = width;
		canvas.height = height;

		var enhanced = isEnhancedTheme();
		if ( enhanced ) {
			ctx.fillStyle = '#f8f4fc';
		} else {
			ctx.fillStyle = colors.white;
		}
		ctx.fillRect( 0, 0, width, height );

		y = drawBrandingBand( ctx, logoImg, y, width, p, contentW, colors );

		// Month title.
		var monthLabel = data.monthNames[ cal.month ] + ' ' + cal.year;
		if ( enhanced ) {
			ctx.fillStyle = colors.purpleDeep;
			ctx.font = 'bold 28px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
		} else {
			ctx.fillStyle = colors.purpleDeep;
			ctx.font = 'bold 24px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
		}
		ctx.textAlign = 'center';
		ctx.textBaseline = 'top';
		ctx.fillText( monthLabel, width / 2, y );
		ctx.textAlign = 'left';
		y += enhanced ? 50 : 44;

		// Day-of-week headers.
		for ( var w = 0; w < 7; w++ ) {
			var dn = weekdayOrder[ w ];
			var cx = gridX + w * ( cell + gridGap ) + cell / 2;
			var isWknd = data.highlightWeekends && data.weekendDays.indexOf( dn ) !== -1;
			ctx.fillStyle = isWknd ? colors.purple : colors.muted;
			ctx.font = 'bold ' + ( enhanced ? 13 : 12 ) + 'px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
			ctx.textAlign = 'center';
			ctx.textBaseline = 'middle';
			ctx.fillText( data.dayNamesShort[ dn ].toUpperCase(), cx, y + ( enhanced ? 16 : 14 ) );
		}
		y += enhanced ? 32 : 28;

		// Month grid.
		var gridY = y;
		var first = new Date( cal.year, cal.month, 1 );
		var offset = ( first.getDay() - data.weekStartsOn + 7 ) % 7;
		var daysInMonth = new Date( cal.year, cal.month + 1, 0 ).getDate();
		var dayNum = 1;

		for ( var row = 0; row < 6; row++ ) {
			for ( var col = 0; col < 7; col++ ) {
				var slot = row * 7 + col;
				var x = gridX + col * ( cell + gridGap );
				var cy = gridY + row * ( cell + gridGap );

				if ( slot < offset || dayNum > daysInMonth ) {
					continue;
				}

				var key = ymd( cal.year, cal.month, dayNum );
				var weekday = new Date( cal.year, cal.month, dayNum ).getDay();
				var marked = data.dates[ key ];
				var isWeekend = data.highlightWeekends && data.weekendDays.indexOf( weekday ) !== -1;
				var isToday = key === data.today;

				drawRoundRect( ctx, x, cy, cell, cell, enhanced ? 12 : 10 );
				if ( marked ) {
					if ( enhanced ) {
						ctx.fillStyle = isWeekend
							? mixTwoHex( marked.color, 68, colors.purpleDeep )
							: mixHex( marked.color, 45 );
					} else {
						ctx.fillStyle = isWeekend
							? mixTwoHex( marked.color, 65, colors.purpleDeep )
							: mixHex( marked.color, 52 );
					}
				} else if ( isWeekend ) {
					ctx.fillStyle = enhanced
						? mixTwoHex( colors.weekendBg, 70, colors.purple )
						: colors.weekendBg;
				} else {
					ctx.fillStyle = enhanced ? '#faf8fc' : colors.white;
				}
				ctx.fill();

				if ( enhanced && ! marked ) {
					ctx.strokeStyle = 'rgba(103,0,134,0.08)';
					ctx.lineWidth = 1;
					ctx.stroke();
				}

				if ( isToday && ! marked ) {
					ctx.strokeStyle = colors.purple;
					ctx.lineWidth = enhanced ? 3 : 2;
					ctx.stroke();
					if ( enhanced ) {
						ctx.strokeStyle = mixTwoHex( colors.gold, 50, colors.purple );
						ctx.lineWidth = 1;
						drawRoundRect( ctx, x + 2, cy + 2, cell - 4, cell - 4, 10 );
						ctx.stroke();
					}
				} else if ( marked && isToday ) {
					ctx.strokeStyle = 'rgba(255,255,255,0.9)';
					ctx.lineWidth = enhanced ? 3 : 2;
					ctx.stroke();
				} else if ( enhanced && marked ) {
					ctx.strokeStyle = 'rgba(255,255,255,0.18)';
					ctx.lineWidth = 1;
					ctx.stroke();
				}

				var numColor = colors.purpleDeep;
				if ( marked ) {
					numColor = isLightMarkColor( marked.color ) && ! isWeekend ? colors.purpleDeep : '#ffffff';
				} else if ( isToday ) {
					numColor = colors.purple;
				}

				ctx.fillStyle = numColor;
				ctx.font = ( marked || isToday ? 'bold ' : '' ) + '22px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
				ctx.textAlign = 'center';
				ctx.textBaseline = 'middle';
				ctx.fillText( String( dayNum ), x + cell / 2, cy + cell / 2 );

				dayNum++;
			}
		}

		y = gridY + 6 * cell + 5 * gridGap + 24;
		drawHolidaysList( ctx, holidays, data.i18n.holidaysThisMonth, data.i18n.noHolidays, y, width, p, contentW, colors );

		return canvas;
	}

	function exportIconSvg( type ) {
		if ( 'month' === type ) {
			return '<svg class="hc-export-svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
				'<path d="M7 3h2v2H7V3zm8 0h2v2h-2V3zM5 7h14v12H5V7zm2 2v8h10V9H7zm2 2h2v2H9v-2zm4 0h2v2h-2v-2zm-4 4h2v2H9v-2zm4 0h2v2h-2v-2z"/>' +
				'<path d="M12 16v4M9 19l3 3 3-3" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>' +
				'</svg>';
		}
		return '<svg class="hc-export-svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
			'<path d="M5 6h14v2H5V6zm0 5h14v2H5v-2zm0 5h10v2H5v-2z"/>' +
			'<path d="M12 15v5M9 18l3 3 3-3" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>' +
			'</svg>';
	}

	function downloadCanvas( canvas, filename ) {
		canvas.toBlob(
			function ( blob ) {
				if ( ! blob ) {
					return;
				}
				var url = URL.createObjectURL( blob );
				var link = document.createElement( 'a' );
				link.href = url;
				link.download = filename;
				link.style.display = 'none';
				document.body.appendChild( link );
				link.click();
				document.body.removeChild( link );
				URL.revokeObjectURL( url );
			},
			'image/png',
			1
		);
	}

	function setExportBtnBusy( btn, busy ) {
		btn.disabled = busy;
		btn.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
	}

	function exportCalendarImage( cal, btn, mode ) {
		if ( btn.disabled ) {
			return;
		}

		var originalLabel = btn.getAttribute( 'aria-label' ) || '';
		setExportBtnBusy( btn, true );
		btn.setAttribute( 'aria-label', data.i18n.savingImage );

		var brand = data.brand || {};
		loadImage( brand.logoUrl ).then( function ( logoImg ) {
			var canvas = 'all' === mode ? drawExportImageAll( logoImg ) : drawExportImageMonth( cal, logoImg );
			var siteSlug = slugify( brand.siteName || 'site' );
			var filename = 'all' === mode
				? 'holiday-calendar-all-' + siteSlug + '.png'
				: 'holiday-calendar-' + cal.year + '-' + pad( cal.month + 1 ) + '-' + siteSlug + '.png';
			downloadCanvas( canvas, filename );
			setExportBtnBusy( btn, false );
			btn.setAttribute( 'aria-label', originalLabel );
		} );
	}

	function renderHolidayListHtml( holidays, title, emptyMsg, dateFormatter, linkable ) {
		var html = '';
		var i;

		html += '<div class="hc-holidays">';
		html += '<h3 class="hc-holidays-title">' + escapeHtml( title ) + '</h3>';

		if ( ! holidays.length ) {
			html += '<p class="hc-holidays-empty">' + escapeHtml( emptyMsg ) + '</p>';
		} else {
			html += '<ul class="hc-holiday-list">';
			for ( i = 0; i < holidays.length; i++ ) {
				html += '<li class="hc-holiday-item" data-date="' + escapeHtml( holidays[ i ].date ) + '"' +
					( linkable ? ' tabindex="0"' : '' ) + '>';
				html += '<span class="hc-holiday-swatch" style="--hc-mark:' + escapeHtml( holidays[ i ].color ) + '"></span>';
				html += '<span class="hc-holiday-date">' + escapeHtml( dateFormatter( holidays[ i ] ) ) + '</span>';
				html += '<span class="hc-holiday-label">' + escapeHtml( holidays[ i ].label ) + '</span>';
				html += '</li>';
			}
			html += '</ul>';
		}

		html += '</div>';
		return html;
	}

	function Calendar( el ) {
		this.el = el;
		this.view = 'calendar';
		var now = new Date();
		this.year = now.getFullYear();
		this.month = now.getMonth(); // 0-11
		this.render();
	}

	Calendar.prototype.getMonthHolidays = function () {
		var y = this.year,
			m = this.month,
			holidays = [],
			i,
			entries = getHolidayEntries();

		for ( i = 0; i < entries.length; i++ ) {
			if ( ! entryOverlapsMonth( entries[ i ], y, m ) ) {
				continue;
			}
			holidays.push( {
				date: entries[ i ].date,
				date_end: entryEndDate( entries[ i ] ),
				label: entries[ i ].label,
				color: entries[ i ].color,
				type: entries[ i ].type || 'single',
			} );
		}

		holidays.sort( function ( a, b ) {
			return a.date.localeCompare( b.date );
		} );

		return holidays;
	};

	Calendar.prototype.renderHolidayList = function () {
		return renderHolidayListHtml(
			this.getMonthHolidays(),
			data.i18n.holidaysThisMonth,
			data.i18n.noHolidays,
			formatEntryListDate,
			true
		);
	};

	Calendar.prototype.renderAllHolidaysList = function () {
		return renderHolidayListHtml(
			getAllHolidays(),
			data.i18n.allHolidays,
			data.i18n.noHolidaysAll,
			formatEntryListDateFull,
			false
		);
	};

	Calendar.prototype.renderViewToggle = function () {
		var isCalendar = 'calendar' === this.view;
		var html = '';

		html += '<div class="hc-view-toggle" role="tablist" aria-label="' + escapeHtml( data.i18n.viewCalendar ) + ' / ' + escapeHtml( data.i18n.viewAll ) + '">';
		html += '<button type="button" class="hc-view-tab" role="tab" id="hc-tab-calendar" data-view="calendar" aria-selected="' + ( isCalendar ? 'true' : 'false' ) + '" aria-controls="hc-panel-calendar" tabindex="' + ( isCalendar ? '0' : '-1' ) + '">' + escapeHtml( data.i18n.viewCalendar ) + '</button>';
		html += '<button type="button" class="hc-view-tab" role="tab" id="hc-tab-all" data-view="all" aria-selected="' + ( isCalendar ? 'false' : 'true' ) + '" aria-controls="hc-panel-all" tabindex="' + ( isCalendar ? '-1' : '0' ) + '">' + escapeHtml( data.i18n.viewAll ) + '</button>';
		html += '</div>';

		return html;
	};

	Calendar.prototype.renderCalendarPanel = function () {
		var y = this.year,
			m = this.month;

		var first = new Date( y, m, 1 );
		var offset = ( first.getDay() - data.weekStartsOn + 7 ) % 7;
		var daysInMonth = new Date( y, m + 1, 0 ).getDate();
		var weekdayOrder = orderedWeekdays();
		var hidden = 'calendar' !== this.view;
		var html = '';

		html += '<div class="hc-view-panel hc-view-calendar" id="hc-panel-calendar" role="tabpanel" aria-labelledby="hc-tab-calendar"' + ( hidden ? ' hidden' : '' ) + '>';
		html += '<div class="hc-cal-head">';
		html += '<div class="hc-cal-nav-group">';
		html += '<button type="button" class="hc-nav hc-prev" aria-label="' + escapeHtml( data.i18n.prev ) + '">&#8249;</button>';
		html += '<div class="hc-title">' + escapeHtml( data.monthNames[ m ] ) + ' ' + y + '</div>';
		html += '<button type="button" class="hc-nav hc-next" aria-label="' + escapeHtml( data.i18n.next ) + '">&#8250;</button>';
		html += '</div>';
		html += '<div class="hc-export-actions">';
		html += '<button type="button" class="hc-export-icon hc-export-month" data-export-mode="month" aria-label="' + escapeHtml( data.i18n.exportMonth ) + '" title="' + escapeHtml( data.i18n.exportMonth ) + '">' + exportIconSvg( 'month' ) + '</button>';
		html += '</div>';
		html += '</div>';

		html += '<div class="hc-grid-head">';
		for ( var w = 0; w < 7; w++ ) {
			var dn = weekdayOrder[ w ];
			var headWknd = data.highlightWeekends && data.weekendDays.indexOf( dn ) !== -1;
			html += '<div class="hc-dow' + ( headWknd ? ' hc-dow-weekend' : '' ) + '">' + escapeHtml( data.dayNamesShort[ dn ] ) + '</div>';
		}
		html += '</div>';

		html += '<div class="hc-grid-body">';
		for ( var b = 0; b < offset; b++ ) {
			html += '<div class="hc-cell hc-empty"></div>';
		}
		for ( var d = 1; d <= daysInMonth; d++ ) {
			var key = ymd( y, m, d );
			var weekday = new Date( y, m, d ).getDay();
			var classes = 'hc-cell hc-day';
			var marked = data.dates[ key ];
			var style = '';
			var labelAttr = '';

			if ( data.highlightWeekends && data.weekendDays.indexOf( weekday ) !== -1 ) {
				classes += ' hc-weekend';
			}
			if ( key === data.today ) {
				classes += ' hc-today';
			}
			if ( marked ) {
				classes += ' hc-marked';
				classes += isLightMarkColor( marked.color ) ? ' hc-mark-light' : ' hc-mark-dark';
				style = ' style="--hc-mark:' + escapeHtml( marked.color ) + '"';
				labelAttr = ' data-label="' + escapeHtml( marked.label ) + '"';
			}

			html += '<div class="' + classes + '"' + style + ' data-date="' + key + '"' + labelAttr + ' tabindex="0">';
			html += '<span class="hc-num">' + d + '</span>';
			html += '</div>';
		}
		html += '</div>';

		html += this.renderHolidayList();
		html += '</div>';

		return html;
	};

	Calendar.prototype.renderAllHolidaysPanel = function () {
		var hidden = 'all' !== this.view;
		var html = '';

		html += '<div class="hc-view-panel hc-view-all" id="hc-panel-all" role="tabpanel" aria-labelledby="hc-tab-all"' + ( hidden ? ' hidden' : '' ) + '>';
		html += '<div class="hc-all-head">';
		html += '<h2 class="hc-all-title">' + escapeHtml( data.i18n.allHolidays ) + '</h2>';
		html += '<div class="hc-export-actions">';
		html += '<button type="button" class="hc-export-icon hc-export-all" data-export-mode="all" aria-label="' + escapeHtml( data.i18n.exportAll ) + '" title="' + escapeHtml( data.i18n.exportAll ) + '">' + exportIconSvg( 'all' ) + '</button>';
		html += '</div>';
		html += '</div>';
		html += this.renderAllHolidaysList();
		html += '</div>';

		return html;
	};

	Calendar.prototype.setView = function ( view ) {
		if ( view === this.view ) {
			return;
		}
		this.view = view;
		this.render();
	};

	Calendar.prototype.render = function () {
		var html = '';

		html += this.renderViewToggle();
		html += '<div class="hc-views">';
		html += this.renderCalendarPanel();
		html += this.renderAllHolidaysPanel();
		html += '</div>';

		this.el.innerHTML = html;
		this.el.classList.toggle( 'hc-view-mode-calendar', 'calendar' === this.view );
		this.el.classList.toggle( 'hc-view-mode-all', 'all' === this.view );

		this.tip = document.createElement( 'div' );
		this.tip.className = 'hc-tooltip';
		this.tip.hidden = true;
		this.el.appendChild( this.tip );

		this.bind();
	};

	Calendar.prototype.bind = function () {
		var self = this;

		var tabs = this.el.querySelectorAll( '.hc-view-tab' );
		Array.prototype.forEach.call( tabs, function ( tab ) {
			tab.addEventListener( 'click', function () {
				self.setView( tab.getAttribute( 'data-view' ) || 'calendar' );
			} );
			tab.addEventListener( 'keydown', function ( e ) {
				var nextTab = null;
				if ( 'ArrowLeft' === e.key || 'ArrowUp' === e.key ) {
					nextTab = tab.previousElementSibling;
				} else if ( 'ArrowRight' === e.key || 'ArrowDown' === e.key ) {
					nextTab = tab.nextElementSibling;
				} else if ( 'Home' === e.key ) {
					nextTab = tabs[ 0 ];
				} else if ( 'End' === e.key ) {
					nextTab = tabs[ tabs.length - 1 ];
				}
				if ( ! nextTab || nextTab === tab ) {
					return;
				}
				e.preventDefault();
				self.setView( nextTab.getAttribute( 'data-view' ) || 'calendar' );
			} );
		} );

		var prevBtn = this.el.querySelector( '.hc-prev' );
		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', function () {
				self.month--;
				if ( self.month < 0 ) {
					self.month = 11;
					self.year--;
				}
				self.render();
			} );
		}

		var nextBtn = this.el.querySelector( '.hc-next' );
		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', function () {
				self.month++;
				if ( self.month > 11 ) {
					self.month = 0;
					self.year++;
				}
				self.render();
			} );
		}

		var marks = this.el.querySelectorAll( '.hc-view-calendar .hc-marked' );
		Array.prototype.forEach.call( marks, function ( cell ) {
			cell.addEventListener( 'mouseenter', function () {
				self.showTip( cell );
			} );
			cell.addEventListener( 'mouseleave', function () {
				self.hideTip();
			} );
			cell.addEventListener( 'focus', function () {
				self.showTip( cell );
			} );
			cell.addEventListener( 'blur', function () {
				self.hideTip();
			} );
		} );

		var listItems = this.el.querySelectorAll( '.hc-view-calendar .hc-holiday-item[tabindex]' );
		Array.prototype.forEach.call( listItems, function ( item ) {
			var activate = function () {
				var dateKey = item.getAttribute( 'data-date' );
				var cell = self.el.querySelector( '.hc-day[data-date="' + dateKey + '"]' );
				if ( cell ) {
					cell.focus();
				}
			};

			item.addEventListener( 'click', activate );
			item.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key || ' ' === e.key ) {
					e.preventDefault();
					activate();
				}
			} );
		} );

		var exportBtns = this.el.querySelectorAll( '.hc-export-icon' );
		Array.prototype.forEach.call( exportBtns, function ( exportBtn ) {
			exportBtn.addEventListener( 'click', function () {
				var mode = exportBtn.getAttribute( 'data-export-mode' ) || 'month';
				exportCalendarImage( self, exportBtn, mode );
			} );
		} );
	};

	Calendar.prototype.showTip = function ( cell ) {
		var label = cell.getAttribute( 'data-label' );
		if ( ! label ) {
			return;
		}
		this.tip.textContent = label;
		this.tip.hidden = false;

		var cRect = cell.getBoundingClientRect();
		var pRect = this.el.getBoundingClientRect();
		var top = cRect.top - pRect.top - this.tip.offsetHeight - 8;
		var left = cRect.left - pRect.left + cRect.width / 2 - this.tip.offsetWidth / 2;
		this.tip.style.top = top + 'px';
		this.tip.style.left = Math.max( 0, left ) + 'px';
	};

	Calendar.prototype.hideTip = function () {
		this.tip.hidden = true;
	};

	function init() {
		var els = document.querySelectorAll( '[data-hc-calendar]' );
		Array.prototype.forEach.call( els, function ( el ) {
			new Calendar( el );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
