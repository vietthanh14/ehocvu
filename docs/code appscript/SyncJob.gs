/**
 * Tự động đồng bộ thông tin Sinh viên (Họ tên, Ngày sinh, Số điện thoại)
 * từ Danh sách sinh viên gốc (Sheet1) sang tất cả các Sheet yêu cầu lịch sử.
 * 
 * Hướng dẫn cài đặt:
 * 1. Copy toàn bộ đoạn code này dán vào một file mới (ví dụ: SyncJob.gs) trong Google Apps Script của bạn.
 * 2. Lưu lại (Ctrl + S).
 * 3. Chạy thử hàm `syncAllStudentProfiles` bằng tay một lần để cấp quyền truy cập.
 * 4. Vào mục Trình kích hoạt (Triggers - biểu tượng đồng hồ bên trái).
 * 5. Chọn "Thêm trình kích hoạt":
 *    - Hàm cần chạy: syncAllStudentProfiles
 *    - Nguồn sự kiện: Theo thời gian (Time-driven)
 *    - Loại trình kích hoạt: Trình kích hoạt theo ngày (Day timer)
 *    - Thời gian trong ngày: Nửa đêm đến 1 giờ sáng (Hoặc bất cứ giờ nào bạn muốn).
 */

function syncAllStudentProfiles() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  
  // 1. Lấy dữ liệu từ Danh sách sinh viên (Sheet1)
  // Hãy đổi 'Sheet1' thành tên sheet gốc của bạn nếu cần
  var studentSheet = ss.getSheetByName('Sheet1') || ss.getSheetByName('Danh sách sinh viên');
  if (!studentSheet) {
    Logger.log("Lỗi: Không tìm thấy Sheet danh sách sinh viên.");
    return;
  }
  
  var studentData = studentSheet.getDataRange().getValues();
  if (studentData.length < 2) {
    Logger.log("Sheet danh sách sinh viên trống.");
    return;
  }
  
  var stHeaders = studentData[0];
  var stCol = {};
  
  // Tự động tìm vị trí cột dựa trên Header
  for (var i = 0; i < stHeaders.length; i++) {
    var h = (stHeaders[i] || '').toString().toLowerCase().trim().replace(/\s+/g, ''); // Bỏ hết dấu cách để dễ so sánh
    if (h.indexOf('mãsv') !== -1 || h.indexOf('mãsinhviên') !== -1 || h.indexOf('masv') !== -1) stCol.maSV = i;
    if (h.indexOf('họtên') !== -1 || h.indexOf('họvàtên') !== -1 || h.indexOf('hoten') !== -1) stCol.hoTen = i;
    if (h.indexOf('ngàysinh') !== -1 || h.indexOf('ngaysinh') !== -1 || h.indexOf('nămsinh') !== -1) stCol.ngaySinh = i;
    if (h.indexOf('sđt') !== -1 || h.indexOf('sdt') !== -1 || h.indexOf('điệnthoại') !== -1) stCol.sdt = i;
  }
  
  // Fallback nếu không có cột Mã SV
  if (stCol.maSV === undefined) stCol.maSV = 1; // Mặc định cột B (index 1)
  
  // Build từ điển map thông tin sinh viên mới nhất
  var studentMap = {};
  for (var r = 1; r < studentData.length; r++) {
    var row = studentData[r];
    var maSv = (row[stCol.maSV] || '').toString().trim().toLowerCase();
    if (maSv) {
      studentMap[maSv] = {
        hoTen: stCol.hoTen !== undefined ? row[stCol.hoTen] : '',
        ngaySinh: stCol.ngaySinh !== undefined ? row[stCol.ngaySinh] : '',
        sdt: stCol.sdt !== undefined ? row[stCol.sdt] : ''
      };
    }
  }
  
  // 2. Định nghĩa các sheet đơn từ/yêu cầu cần được cập nhật hồi tố
  // Hãy đảm bảo tên các sheet này khớp chính xác với dự án của bạn
  var targetSheets = ['Sheet3', 'HuyHocPhan_Requests', 'LeTotNghiep_Requests'];
  
  targetSheets.forEach(function(sheetName) {
    var tSheet = ss.getSheetByName(sheetName);
    if (!tSheet) {
      Logger.log("Bỏ qua: Không tìm thấy sheet '" + sheetName + "'");
      return;
    }
    
    var tData = tSheet.getDataRange().getValues();
    if (tData.length < 2) return;
    
    var tHeaders = tData[0];
    var tCol = {};
    
    // Tìm vị trí cột tương ứng trên Sheet đích
    for (var i = 0; i < tHeaders.length; i++) {
      var h = (tHeaders[i] || '').toString().toLowerCase().trim().replace(/\s+/g, ''); // Bỏ hết dấu cách
      if (h.indexOf('mãsv') !== -1 || h.indexOf('mãsinhviên') !== -1 || h.indexOf('masv') !== -1) tCol.maSV = i;
      if (h.indexOf('họtên') !== -1 || h.indexOf('họvàtên') !== -1 || h.indexOf('hoten') !== -1) tCol.hoTen = i;
      if (h.indexOf('ngàysinh') !== -1 || h.indexOf('ngaysinh') !== -1 || h.indexOf('nămsinh') !== -1) tCol.ngaySinh = i;
      if (h.indexOf('sđt') !== -1 || h.indexOf('sdt') !== -1 || h.indexOf('điệnthoại') !== -1) tCol.sdt = i;
    }
    
    // Nếu sheet đích không có cột Mã SV để đối chiếu, bỏ qua sheet này
    if (tCol.maSV === undefined) {
      Logger.log("Lỗi: Sheet '" + sheetName + "' không có cột Mã SV để đối chiếu.");
      return; 
    }
    
    var hasChanges = false;
    
    // Quét từng dòng của Sheet đích để đối chiếu
    for (var r = 1; r < tData.length; r++) {
      var row = tData[r];
      var maSv = (row[tCol.maSV] || '').toString().trim().toLowerCase();
      
      // Nếu sinh viên này tồn tại trong danh sách gốc
      if (maSv && studentMap[maSv]) {
        var st = studentMap[maSv];
        
        // Hàm chuẩn hóa để so sánh (đặc biệt hữu ích với kiểu Date)
        var normalizeVal = function(v) {
          if (v instanceof Date) return v.getTime();
          return (v || '').toString().trim();
        };

        // Kiểm tra và cập nhật nếu có sự sai khác giữa Sheet đích và Sheet gốc
        if (tCol.hoTen !== undefined && normalizeVal(row[tCol.hoTen]) !== normalizeVal(st.hoTen) && st.hoTen !== '') {
          tData[r][tCol.hoTen] = st.hoTen;
          hasChanges = true;
        }
        if (tCol.ngaySinh !== undefined && normalizeVal(row[tCol.ngaySinh]) !== normalizeVal(st.ngaySinh) && st.ngaySinh !== '') {
          tData[r][tCol.ngaySinh] = st.ngaySinh;
          hasChanges = true;
        }
        if (tCol.sdt !== undefined && normalizeVal(row[tCol.sdt]) !== normalizeVal(st.sdt) && st.sdt !== '') {
          tData[r][tCol.sdt] = st.sdt;
          hasChanges = true;
        }
      }
    }
    
    // Nếu phát hiện có dữ liệu bị thay đổi, ghi đè lại toàn bộ mảng dữ liệu 1 lần duy nhất
    if (hasChanges) {
      // getRange(row, col, numRows, numCols)
      tSheet.getRange(1, 1, tData.length, tData[0].length).setValues(tData);
      Logger.log("Thành công: Đã cập nhật đồng bộ hồi tố cho Sheet '" + sheetName + "'");
    } else {
      Logger.log("Không có thay đổi nào cần đồng bộ cho Sheet '" + sheetName + "'");
    }
  });
  
  Logger.log("Hoàn tất tiến trình Batch Sync!");
}
