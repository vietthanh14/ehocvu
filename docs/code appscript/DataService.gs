// Hàm helper lấy sheet dữ liệu chuẩn
function getWorkingSheet() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  return ss.getSheetByName('DS Yêu cầu') || ss.getSheetByName('Sheet3') || ss.getActiveSheet();
}

// Đọc dữ liệu từ Sheet (Sử dụng tìm kiếm tên cột động để chống lỗi)
function getPendingStudents(typeId) {
  var sheet = getWorkingSheet();
  
  var data = sheet.getDataRange().getValues();
  if (data.length < 2) return [];
  
  var headers = data[0];
  var col = {};
  
  // Tự động tìm đúng vị trí cột dựa trên tiêu đề chính xác do Admin cung cấp
  for (var i = 0; i < headers.length; i++) {
    var h = (headers[i] || '').toString().toLowerCase().trim();
    if (h === 'mã sv') col.maSV = i;
    if (h === 'họ tên') col.hoTen = i;
    if (h === 'ngày sinh') col.ngaySinh = i;
    if (h === 'tên khoa') col.khoa = i;
    if (h === 'tên hệ') col.he = i;
    if (h === 'tên lớp') col.lop = i;
    if (h === 'chuyên ngành') col.nganh = i;
    if (h === 'loại yêu cầu') col.loaiYc = i;
    if (h === 'bảo lưu đến') col.thoiHan = i;
    if (h === 'trạng thái') col.trangThai = i;
    if (h === 'lớp tiếp tục học sau bảo lưu') col.lopTiepTuc = i;
  }
  
  // Nếu không tìm thấy (do đổi tên cột), dùng vị trí cột mặc định (0-indexed)
  if (col.maSV === undefined) col.maSV = 1;
  if (col.hoTen === undefined) col.hoTen = 2;
  if (col.ngaySinh === undefined) col.ngaySinh = 3;
  if (col.khoa === undefined) col.khoa = 5;
  if (col.he === undefined) col.he = 6;
  if (col.lop === undefined) col.lop = 7;
  if (col.nganh === undefined) col.nganh = 8;
  if (col.loaiYc === undefined) col.loaiYc = 10;
  if (col.thoiHan === undefined) col.thoiHan = 11;
  if (col.trangThai === undefined) col.trangThai = 13;

  var students = [];
  
  // Lấy hàm filter tương ứng với loại quyết định (Mặc định là BẢO LƯU nếu không truyền typeId)
  var configType = typeId === APP_CONFIG.TYPES.TIEP_TUC_HOC.id ? APP_CONFIG.TYPES.TIEP_TUC_HOC : APP_CONFIG.TYPES.BAO_LUU;
  var filterFn = configType.sheetFilter;

  for (var i = 1; i < data.length; i++) {
    var row = data[i];
    var maSV = (row[col.maSV] || '').toString().trim();
    var loaiYc = (row[col.loaiYc] || '').toString().toLowerCase();
    var trangThai = (row[col.trangThai] || '').toString().toLowerCase();
    
    // Điều kiện lọc: Mã SV + Logic Loại YC (từ Config) + Trạng thái Chờ duyệt
    if (maSV && filterFn(loaiYc) && trangThai.indexOf('chờ') !== -1) {
      
      var ngaySinhStr = "";
      if (row[col.ngaySinh] instanceof Date) {
        ngaySinhStr = Utilities.formatDate(row[col.ngaySinh], Session.getScriptTimeZone(), "dd/MM/yyyy");
      } else {
        ngaySinhStr = (row[col.ngaySinh] || '').toString();
      }
      
      var thoiHanStr = "";
      if (row[col.thoiHan] instanceof Date) {
        thoiHanStr = Utilities.formatDate(row[col.thoiHan], Session.getScriptTimeZone(), "MM/yyyy");
      } else {
        thoiHanStr = (row[col.thoiHan] || '').toString();
      }

      var lopCuStr = (row[col.lop] || '').toString();
      var lopMoiStr = "";
      if (col.lopTiepTuc !== undefined) {
        lopMoiStr = (row[col.lopTiepTuc] || '').toString().trim();
      }

      students.push({
        rowIdx: i + 1, // Lưu lại vị trí dòng (1-indexed) để update sau này
        maSV: maSV, 
        hoTen: (row[col.hoTen] || '').toString(),
        ngaySinh: ngaySinhStr,
        khoa: (row[col.khoa] || '').toString(), 
        he: (row[col.he] || '').toString(),
        lop: lopCuStr, // Giữ nguyên biến lop mặc định để không hỏng mẫu cũ
        lopCu: lopCuStr,
        lopMoi: lopMoiStr,
        nganh: (row[col.nganh] || '').toString(), 
        thoiHan: thoiHanStr
      });
    }
  }
  return students;
}
