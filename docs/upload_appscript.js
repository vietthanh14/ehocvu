/**
 * Google Apps Script - Upload file + Thống kê
 * 
 * HƯỚNG DẪN:
 * - Upload file: Đã hoạt động qua Web App (doPost)
 * - Tạo thống kê: Chạy hàm createThongKeSheet() (▶ Run)
 */

// =============================================
//  CẤU HÌNH
// =============================================
var FOLDER_ID = '15mtXWOSOdKcf0KhMRuWEWtVorG5U9Qgi';

// =============================================
//  UPLOAD FILE (Web App)
// =============================================

function doPost(e) {
  try {
    var data = JSON.parse(e.postData.contents);
    var fileName = data.fileName || 'don_dang_ky.pdf';
    var mimeType = data.mimeType || 'application/pdf';
    var fileBase64 = data.fileBase64;
    var maSv = data.maSv || 'unknown';
    
    if (!fileBase64) {
      return ContentService.createTextOutput(
        JSON.stringify({ success: false, message: 'Không có dữ liệu file.' })
      ).setMimeType(ContentService.MimeType.JSON);
    }
    
    var fileBlob = Utilities.newBlob(Utilities.base64Decode(fileBase64), mimeType, fileName);
    var timestamp = Utilities.formatDate(new Date(), 'Asia/Ho_Chi_Minh', 'yyyyMMdd_HHmmss');
    var safeName = maSv + '_' + timestamp + '_' + fileName;
    fileBlob.setName(safeName);
    
    var folder = DriveApp.getFolderById(FOLDER_ID);
    var file = folder.createFile(fileBlob);
    file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
    
    return ContentService.createTextOutput(
      JSON.stringify({ success: true, fileUrl: file.getUrl(), fileId: file.getId(), fileName: safeName })
    ).setMimeType(ContentService.MimeType.JSON);
  } catch (error) {
    return ContentService.createTextOutput(
      JSON.stringify({ success: false, message: error.toString() })
    ).setMimeType(ContentService.MimeType.JSON);
  }
}

function doGet() {
  return ContentService.createTextOutput(
    JSON.stringify({ status: 'ok', message: 'Upload API is running.' })
  ).setMimeType(ContentService.MimeType.JSON);
}

// =============================================
//  TẠO SHEET THỐNG KÊ
// =============================================

function createThongKeSheet() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var dataSheet = ss.getSheetByName('Sheet3');
  
  if (!dataSheet || dataSheet.getLastRow() < 2) {
    SpreadsheetApp.getUi().alert('⚠️ Sheet3 không có dữ liệu!');
    return;
  }
  
  // === ĐỌC DỮ LIỆU THỰC TẾ (không dùng công thức cố định) ===
  var allData = dataSheet.getDataRange().getValues();
  var headers = allData[0];
  var rows = allData.slice(1); // bỏ header
  
  // Tìm index cột theo tên header (linh hoạt)
  var colIdx = {};
  headers.forEach(function(h, i) {
    var name = (h || '').toString().toLowerCase().trim();
    if (name.indexOf('thời gian') > -1 || name.indexOf('thoi gian') > -1 || name === 'timestamp') colIdx.thoiGian = i;
    if (name.indexOf('mã sv') > -1 || name.indexOf('mã sinh viên') > -1 || name === 'ma sv') colIdx.maSv = i;
    if (name.indexOf('khoa') > -1 && name.indexOf('niên') === -1) colIdx.khoa = i;
    if (name.indexOf('loại') > -1 || name.indexOf('yêu cầu') > -1) colIdx.loai = i;
    if (name.indexOf('trạng thái') > -1 || name.indexOf('trang thai') > -1) colIdx.trangThai = i;
  });
  
  // Fallback nếu không tìm thấy header → dùng index mặc định
  if (colIdx.thoiGian === undefined) colIdx.thoiGian = 0;
  if (colIdx.maSv === undefined) colIdx.maSv = 1;
  if (colIdx.khoa === undefined) colIdx.khoa = 5;
  if (colIdx.loai === undefined) colIdx.loai = 10;
  if (colIdx.trangThai === undefined) colIdx.trangThai = 13;
  
  // === TÍNH TOÁN TRỰC TIẾP ===
  var totalDon = rows.length;
  var uniqueSV = {};
  var choDuyet = 0, daDuyet = 0, tuChoi = 0;
  var baoLuu = 0, tiepTuc = 0;
  var khoaMap = {};
  var donHomNay = 0;
  
  var today = Utilities.formatDate(new Date(), 'Asia/Ho_Chi_Minh', 'dd/MM/yyyy');
  
  rows.forEach(function(row) {
    // Mã SV unique
    var ma = (row[colIdx.maSv] || '').toString().trim();
    if (ma) uniqueSV[ma] = true;
    
    // Trạng thái
    var tt = (row[colIdx.trangThai] || '').toString().toLowerCase().trim();
    if (tt.indexOf('chờ') > -1) choDuyet++;
    else if (tt.indexOf('duyệt') > -1 || tt.indexOf('xong') > -1 || tt.indexOf('thành công') > -1) daDuyet++;
    else if (tt.indexOf('từ chối') > -1 || tt.indexOf('hủy') > -1) tuChoi++;
    
    // Loại thủ tục
    var loai = (row[colIdx.loai] || '').toString().toLowerCase();
    if (loai.indexOf('bảo lưu') > -1) baoLuu++;
    if (loai.indexOf('tiếp tục') > -1) tiepTuc++;
    
    // Theo khoa  
    var khoa = (row[colIdx.khoa] || '').toString().trim();
    if (khoa) {
      if (!khoaMap[khoa]) khoaMap[khoa] = { total: 0, cho: 0, duyet: 0, tuchoi: 0 };
      khoaMap[khoa].total++;
      if (tt.indexOf('chờ') > -1) khoaMap[khoa].cho++;
      else if (tt.indexOf('duyệt') > -1 || tt.indexOf('xong') > -1 || tt.indexOf('thành công') > -1) khoaMap[khoa].duyet++;
      else if (tt.indexOf('từ chối') > -1 || tt.indexOf('hủy') > -1) khoaMap[khoa].tuchoi++;
    }
    
    // Đơn hôm nay
    var tg = (row[colIdx.thoiGian] || '').toString();
    if (tg.indexOf(today) > -1) donHomNay++;
  });
  
  var uniqueCount = Object.keys(uniqueSV).length;
  var tyLeDuyet = totalDon > 0 ? Math.round(daDuyet / totalDon * 1000) / 10 : 0;
  
  // === TẠO SHEET ===
  var existing = ss.getSheetByName('Thống kê');
  if (existing) ss.deleteSheet(existing);
  var sheet = ss.insertSheet('Thống kê');
  
  sheet.setColumnWidth(1, 30);
  sheet.setColumnWidth(2, 220);
  sheet.setColumnWidth(3, 100);
  sheet.setColumnWidth(4, 30);
  sheet.setColumnWidth(5, 220);
  sheet.setColumnWidth(6, 100);
  sheet.setColumnWidth(7, 30);
  sheet.setColumnWidth(8, 220);
  sheet.setColumnWidth(9, 100);
  
  // Header
  sheet.getRange('B1:I1').merge()
    .setValue('📊 BÁO CÁO THỐNG KÊ - HỆ THỐNG QUẢN LÝ THỦ TỤC HỌC VỤ')
    .setFontSize(14).setFontWeight('bold').setFontColor('#0f766e')
    .setHorizontalAlignment('center').setVerticalAlignment('middle');
  sheet.setRowHeight(1, 50);
  
  var now = Utilities.formatDate(new Date(), 'Asia/Ho_Chi_Minh', 'dd/MM/yyyy HH:mm');
  sheet.getRange('B2:I2').merge()
    .setValue('Cập nhật lần cuối: ' + now + ' · Chạy lại hàm createThongKeSheet() để cập nhật')
    .setFontSize(9).setFontColor('#94a3b8').setHorizontalAlignment('center');
  
  // === TỔNG QUAN ===
  _header(sheet, 4, 'B', 'C', '📋 TỔNG QUAN');
  _val(sheet, 5, 'B', 'C', 'Tổng số đơn đã nộp', totalDon);
  _val(sheet, 6, 'B', 'C', 'Số SV đã nộp đơn', uniqueCount);
  _val(sheet, 7, 'B', 'C', 'Đơn nộp hôm nay', donHomNay);
  
  // === THEO TRẠNG THÁI ===
  _header(sheet, 4, 'E', 'F', '🔖 THEO TRẠNG THÁI');
  _val(sheet, 5, 'E', 'F', '⏳ Chờ duyệt', choDuyet);
  _val(sheet, 6, 'E', 'F', '✅ Đã duyệt', daDuyet);
  _val(sheet, 7, 'E', 'F', '❌ Từ chối / Hủy', tuChoi);
  
  sheet.getRange('F5').setBackground('#fef3c7').setFontColor('#92400e');
  sheet.getRange('F6').setBackground('#d1fae5').setFontColor('#065f46');
  sheet.getRange('F7').setBackground('#fee2e2').setFontColor('#991b1b');
  
  // === THEO LOẠI ===
  _header(sheet, 4, 'H', 'I', '📝 THEO LOẠI');
  _val(sheet, 5, 'H', 'I', 'Bảo lưu kết quả', baoLuu);
  _val(sheet, 6, 'H', 'I', 'Tiếp tục học', tiepTuc);
  _val(sheet, 7, 'H', 'I', 'Tỷ lệ duyệt', tyLeDuyet + '%');
  sheet.getRange('I7').setBackground('#e0f2fe').setFontColor('#0c4a6e');
  
  // === THEO KHOA ===
  sheet.getRange('B10:F10').merge()
    .setValue('🏫 THỐNG KÊ THEO KHOA')
    .setFontSize(11).setFontWeight('bold').setFontColor('#0f766e').setBackground('#f0fdfa');
  sheet.setRowHeight(10, 32);
  
  ['Tên Khoa', 'Tổng đơn', 'Chờ duyệt', 'Đã duyệt', 'Từ chối'].forEach(function(h, i) {
    sheet.getRange(11, 2 + i).setValue(h).setFontWeight('bold').setFontSize(9)
      .setFontColor('#64748b').setHorizontalAlignment(i > 0 ? 'center' : 'left');
  });
  
  var khoaList = Object.keys(khoaMap).sort();
  var khoaRow = 12;
  
  khoaList.forEach(function(khoa, idx) {
    var r = khoaRow + idx;
    var d = khoaMap[khoa];
    sheet.getRange('B' + r).setValue(khoa);
    sheet.getRange('C' + r).setValue(d.total).setHorizontalAlignment('center');
    sheet.getRange('D' + r).setValue(d.cho).setHorizontalAlignment('center');
    sheet.getRange('E' + r).setValue(d.duyet).setHorizontalAlignment('center');
    sheet.getRange('F' + r).setValue(d.tuchoi).setHorizontalAlignment('center');
    if (idx % 2 === 0) sheet.getRange('B' + r + ':F' + r).setBackground('#f8fafc');
  });
  
  var totalRow = khoaRow + khoaList.length;
  sheet.getRange('B' + totalRow).setValue('TỔNG CỘNG').setFontWeight('bold');
  sheet.getRange('C' + totalRow).setValue(totalDon).setFontWeight('bold').setHorizontalAlignment('center');
  sheet.getRange('D' + totalRow).setValue(choDuyet).setFontWeight('bold').setHorizontalAlignment('center');
  sheet.getRange('E' + totalRow).setValue(daDuyet).setFontWeight('bold').setHorizontalAlignment('center');
  sheet.getRange('F' + totalRow).setValue(tuChoi).setFontWeight('bold').setHorizontalAlignment('center');
  sheet.getRange('B' + totalRow + ':F' + totalRow).setBackground('#f0fdfa');
  
  // === BIỂU ĐỒ 1: Pie - Trạng thái ===
  var chartDataRow = totalRow + 2;
  sheet.getRange('B' + chartDataRow).setValue('Trạng thái').setFontSize(8).setFontColor('#94a3b8');
  sheet.getRange('C' + chartDataRow).setValue('Số lượng').setFontSize(8).setFontColor('#94a3b8');
  sheet.getRange('B' + (chartDataRow + 1)).setValue('Chờ duyệt').setFontSize(8).setFontColor('#94a3b8');
  sheet.getRange('C' + (chartDataRow + 1)).setValue(choDuyet).setFontSize(8).setFontColor('#94a3b8');
  sheet.getRange('B' + (chartDataRow + 2)).setValue('Đã duyệt').setFontSize(8).setFontColor('#94a3b8');
  sheet.getRange('C' + (chartDataRow + 2)).setValue(daDuyet).setFontSize(8).setFontColor('#94a3b8');
  sheet.getRange('B' + (chartDataRow + 3)).setValue('Từ chối').setFontSize(8).setFontColor('#94a3b8');
  sheet.getRange('C' + (chartDataRow + 3)).setValue(tuChoi).setFontSize(8).setFontColor('#94a3b8');
  
  var chartRow = chartDataRow + 5;
  
  var pie = sheet.newChart()
    .setChartType(Charts.ChartType.PIE)
    .addRange(sheet.getRange('B' + chartDataRow + ':C' + (chartDataRow + 3)))
    .setPosition(chartRow, 2, 0, 0)
    .setOption('title', 'Tỷ lệ trạng thái đơn')
    .setOption('pieHole', 0.4)
    .setOption('colors', ['#f59e0b', '#10b981', '#ef4444'])
    .setOption('width', 400).setOption('height', 280)
    .setOption('legend', { position: 'bottom' })
    .build();
  sheet.insertChart(pie);
  
  // === BIỂU ĐỒ 2: Bar - Theo Khoa ===
  if (khoaList.length > 0) {
    // Dữ liệu cho bar chart
    var barDataRow = chartDataRow;
    sheet.getRange('E' + barDataRow).setValue('Khoa').setFontSize(8).setFontColor('#94a3b8');
    sheet.getRange('F' + barDataRow).setValue('Số đơn').setFontSize(8).setFontColor('#94a3b8');
    khoaList.forEach(function(khoa, idx) {
      sheet.getRange('E' + (barDataRow + 1 + idx)).setValue(khoa).setFontSize(8).setFontColor('#94a3b8');
      sheet.getRange('F' + (barDataRow + 1 + idx)).setValue(khoaMap[khoa].total).setFontSize(8).setFontColor('#94a3b8');
    });
    
    var bar = sheet.newChart()
      .setChartType(Charts.ChartType.BAR)
      .addRange(sheet.getRange('E' + barDataRow + ':F' + (barDataRow + khoaList.length)))
      .setPosition(chartRow, 5, 0, 0)
      .setOption('title', 'Số đơn theo Khoa')
      .setOption('colors', ['#0f766e'])
      .setOption('width', 450).setOption('height', 280)
      .setOption('legend', { position: 'none' })
      .build();
    sheet.insertChart(bar);
  }
  
  // Style
  sheet.getRange('A:I').setFontFamily('Arial');
  sheet.setFrozenRows(2);
  
  SpreadsheetApp.getActiveSpreadsheet().setActiveSheet(sheet);
  SpreadsheetApp.getUi().alert('✅ Đã tạo Sheet "Thống kê" thành công!\n\nĐã phát hiện ' + headers.length + ' cột trong Sheet3.\nCột trạng thái: ' + (headers[colIdx.trangThai] || 'cột ' + (colIdx.trangThai + 1)));
}

// === Helpers ===
function _header(sheet, row, c1, c2, title) {
  sheet.getRange(c1 + row + ':' + c2 + row).merge()
    .setValue(title).setFontSize(11).setFontWeight('bold')
    .setFontColor('#0f766e').setBackground('#f0fdfa');
  sheet.setRowHeight(row, 32);
}

function _val(sheet, row, cLabel, cValue, label, value) {
  sheet.getRange(cLabel + row).setValue(label).setFontSize(10).setFontColor('#334155');
  sheet.getRange(cValue + row).setValue(value)
    .setFontSize(12).setFontWeight('bold').setFontColor('#0f172a')
    .setHorizontalAlignment('center').setBackground('#f8fafc')
    .setBorder(true, true, true, true, false, false, '#e2e8f0', SpreadsheetApp.BorderStyle.SOLID);
}
