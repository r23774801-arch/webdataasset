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
 *   { "worksheet": "Asset_IT", "row": { ... }, "token": "..." }
 * The row is appended (header-aligned) to the matching worksheet tab,
 * creating the tab on first use.
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
    appendRow(sheet, row);

    return json_('success', 'Row appended to ' + worksheet + '.');
  } catch (err) {
    return json_('error', err.message);
  } finally {
    lock.releaseLock();
  }
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
