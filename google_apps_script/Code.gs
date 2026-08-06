/**
 * Google Apps Script Web App — receives synchronized rows from the PHP app.
 *
 * DEPLOYMENT (one-time):
 *   1. Create a Google Spreadsheet and open Extensions > Apps Script.
 *   2. Paste this file into Code.gs.
 *   3. (Optional, recommended) set a shared secret:
 *        Script Properties > SPREADSHEET_TOKEN = <same value as .env SPREADSHEET_TOKEN>
 *   4. Deploy > New deployment > Web app:
 *        - Execute as: Me
 *        - Who has access: Anyone (or "Anyone with link")
 *   5. Copy the /exec URL into .env as SPREADSHEET_WEB_APP_URL.
 *
 * The PHP side posts:
 *   { "worksheet": "Asset_IT", "row": { ... }, "token": "...", "key": "asset_number" }
 *
 * Phase 4.20 — when "key" is present and the row's value for that column is
 * non-empty, the row is UPSERTED: the worksheet is searched for an existing
 * row whose key column equals the payload value, and that row is updated in
 * place. If no match is found (or no key is given), a new row is appended
 * (header-aligned), creating the tab on first use.
 */
var TOKEN_PROPERTY = 'SPREADSHEET_TOKEN';

function doPost(e) {
  var lock = LockService.getScriptLock();
  lock.waitLock(10000);

  try {
    var payload = JSON.parse(e.postData.contents);
    var worksheet = String(payload.worksheet || '');
    var token = String(payload.token || '');
    var row = payload.row || {};
    var key = String(payload.key || '');

    var expected = PropertiesService.getScriptProperties().getProperty(TOKEN_PROPERTY) || '';
    if (expected && token !== expected) {
      return json_('error', 'Invalid token.');
    }
    if (!worksheet) {
      return json_('error', 'Missing worksheet.');
    }
    if (typeof row !== 'object' || Array.isArray(row)) {
      return json_('error', 'Invalid row payload.');
    }

    var ss = SpreadsheetApp.getActiveSpreadsheet();
    var sheet = ss.getSheetByName(worksheet) || ss.insertSheet(worksheet);

    // Only upsert when a key column is requested AND the payload actually has a
    // usable (non-empty) value for it — otherwise append (backward compatible).
    var keyValue = row[key] === undefined || row[key] === null ? '' : String(row[key]).trim();
    if (key && keyValue !== '') {
      upsertRow(sheet, row, key, keyValue);
      return json_('success', 'Row upserted into ' + worksheet + '.');
    }
    appendRow(sheet, row);
    return json_('success', 'Row appended to ' + worksheet + '.');
  } catch (err) {
    return json_('error', err.message);
  } finally {
    lock.releaseLock();
  }
}

/**
 * Upsert one object as a row: search the key column for a matching value and
 * update that row in place; otherwise append a new row (header-aligned).
 *
 * Phase 4.20 — fallback: when the key search misses, also try the always-
 * present "id" column. This keeps edits of the unique key itself (e.g. a
 * changed nomor_tiket) updating the SAME row instead of appending a duplicate.
 */
function upsertRow(sheet, row, key, keyValue) {
  var headers = getHeaders(sheet, row);
  var keyIndex = headers.indexOf(key);
  if (keyIndex < 0) {
    // Key column missing from the existing header — cannot match, append.
    appendRow(sheet, row);
    return;
  }

  var lastRow = sheet.getLastRow();
  if (lastRow > 1) {
    var numData = lastRow - 1; // rows below the header
    var range = sheet.getRange(2, keyIndex + 1, numData, 1);
    var values = range.getValues();
    for (var i = 0; i < values.length; i++) {
      var cell = values[i][0];
      if (cell !== null && cell !== undefined && String(cell).trim() === keyValue) {
        // Found by unique key — overwrite this row with the current payload.
        writeRow(sheet, i + 2, headers, row);
        return;
      }
    }

    // Key not found — fall back to the id column (id is always present).
    var idIndex = headers.indexOf('id');
    var idValue = row['id'] === undefined || row['id'] === null ? '' : String(row['id']).trim();
    if (idIndex >= 0 && idValue !== '') {
      var idRange = sheet.getRange(2, idIndex + 1, numData, 1);
      var idValues = idRange.getValues();
      for (var j = 0; j < idValues.length; j++) {
        var idCell = idValues[j][0];
        if (idCell !== null && idCell !== undefined && String(idCell).trim() === idValue) {
          writeRow(sheet, j + 2, headers, row);
          return;
        }
      }
    }
  }
  // Not found — append a new row.
  appendRow(sheet, row);
}

/**
 * Overwrite one data row with the current payload values (header-aligned),
 * clearing any stale trailing cells from older, wider headers.
 */
function writeRow(sheet, targetRow, headers, row) {
  var lastCol = sheet.getLastColumn();
  if (lastCol > headers.length) {
    sheet.getRange(targetRow, headers.length + 1, 1, lastCol - headers.length).clearContent();
  }
  var rowValues = headers.map(function (h) {
    var v = row[h];
    return v === undefined || v === null ? '' : String(v);
  });
  sheet.getRange(targetRow, 1, 1, headers.length).setValues([rowValues]);
}

/**
 * Append one object as a row, keeping the header row aligned.
 * Header row is seeded from the first object's keys.
 */
function appendRow(sheet, row) {
  var headers = getHeaders(sheet, row);
  var values = headers.map(function (h) {
    var v = row[h];
    return v === undefined || v === null ? '' : String(v);
  });
  sheet.appendRow(values);
}

function getHeaders(sheet, row) {
  var lastRow = sheet.getLastRow();
  if (lastRow > 0) {
    var firstRow = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    if (firstRow.join('').trim() !== '') {
      // Existing header row — reuse its order.
      return firstRow.map(String);
    }
  }
  // No header yet — seed it from this row's keys.
  var headers = Object.keys(row);
  if (headers.length > 0) {
    sheet.appendRow(headers);
  }
  return headers;
}

function json_(status, message) {
  return ContentService
    .createTextOutput(JSON.stringify({ status: status, message: message }))
    .setMimeType(ContentService.MimeType.JSON);
}
