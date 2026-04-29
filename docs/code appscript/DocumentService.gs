// Xử lý tạo file
function generateDecisionDocs(selectedStudents, soQD, ngayQD_input, typeId, hocKi, namHoc) {
  // Lấy Config tương ứng với loại quyết định
  var configType = typeId === APP_CONFIG.TYPES.TIEP_TUC_HOC.id ? APP_CONFIG.TYPES.TIEP_TUC_HOC : APP_CONFIG.TYPES.BAO_LUU;
  var templateId = configType.templateId;
  var folderId = APP_CONFIG.FOLDER_ID;
  
  try {
    var rootFolder = DriveApp.getFolderById(folderId);
    
    // Hàm phụ trợ: Tìm thư mục con, nếu không có thì tạo mới
    function getOrCreateSubFolder(parent, name) {
      var folders = parent.getFoldersByName(name);
      if (folders.hasNext()) {
        return folders.next();
      }
      return parent.createFolder(name);
    }
    
    var now = new Date();
    
    // Tạo cây thư mục: Năm -> Tháng -> Loại Quyết Định
    var yearName = "Năm " + Utilities.formatDate(now, Session.getScriptTimeZone(), "yyyy");
    var monthName = "Tháng " + Utilities.formatDate(now, Session.getScriptTimeZone(), "MM");
    var typeName = "Quyết định " + configType.name;
    
    var yearFolder = getOrCreateSubFolder(rootFolder, yearName);
    var monthFolder = getOrCreateSubFolder(yearFolder, monthName);
    var finalFolder = getOrCreateSubFolder(monthFolder, typeName);
    
    var prefix = configType.id === APP_CONFIG.TYPES.TIEP_TUC_HOC.id ? 'QD_TiepTucHoc_' : 'QuyetDinh_DS_';
    var fileName = prefix + Utilities.formatDate(now, Session.getScriptTimeZone(), "ddMMyyyy_HHmm");
    
    // Lưu bản Temp vào thư mục sâu nhất
    var tempDoc = DriveApp.getFileById(templateId).makeCopy('Temp_' + fileName, finalFolder);
    var doc = DocumentApp.openById(tempDoc.getId());
    var body = doc.getBody();
    
    // Xử lý ngày tháng tự động ra 2 định dạng
    var ngayQD_dai = ".....";
    var ngayQD_ngan = ".....";
    if (ngayQD_input) {
      var parts = ngayQD_input.split('-'); // Cắt chuỗi YYYY-MM-DD
      if (parts.length === 3) {
        ngayQD_dai = parts[2] + " tháng " + parts[1] + " năm " + parts[0];
        ngayQD_ngan = parts[2] + "/" + parts[1] + "/" + parts[0];
      } else {
        ngayQD_dai = ngayQD_input;
        ngayQD_ngan = ngayQD_input;
      }
    }
    
    // Lấy danh sách các khoa duy nhất từ sinh viên đã chọn
    var dskhoa = [];
    for (var k = 0; k < selectedStudents.length; k++) {
      var tenKhoa = selectedStudents[k].khoa.toString().trim();
      
      // Xóa chữ "Khoa" ở đầu nếu có để câu văn mượt hơn
      if (tenKhoa.toLowerCase().indexOf("khoa ") === 0) {
        tenKhoa = tenKhoa.substring(5).trim();
      }
      
      // Viết hoa chữ cái đầu tiên của khoa
      if (tenKhoa.length > 0) {
        tenKhoa = tenKhoa.charAt(0).toUpperCase() + tenKhoa.slice(1);
      }
      
      // Nếu khoa chưa có trong danh sách thì thêm vào
      if (tenKhoa && dskhoa.indexOf(tenKhoa) === -1) {
        dskhoa.push(tenKhoa);
      }
    }
    var chuoiKhoa = dskhoa.join(', ');
    if (chuoiKhoa === "") chuoiKhoa = "....................";
    
    body.replaceText('{{TongSo}}', selectedStudents.length < 10 ? '0' + selectedStudents.length : selectedStudents.length); 
    body.replaceText('{{SoQD}}', soQD ? soQD : '.....');
    body.replaceText('{{NgayQD}}', ngayQD_dai); 
    body.replaceText('{{NgayQDNgan}}', ngayQD_ngan); 
    body.replaceText('{{CacKhoa}}', chuoiKhoa); 
    body.replaceText('{{HocKi}}', hocKi ? hocKi : '.....'); 
    body.replaceText('{{NamHoc}}', namHoc ? namHoc : '.....'); 
    
    var tables = body.getTables();
    var targetTable = null;
    var templateRowIndex = -1;
    
    // Tìm đúng bảng và đúng dòng chứa biến {{TT}}
    for (var t = 0; t < tables.length; t++) {
      var numRows = tables[t].getNumRows();
      for (var r = 0; r < numRows; r++) {
        if (tables[t].getRow(r).getText().indexOf('{{TT}}') !== -1) {
          targetTable = tables[t];
          templateRowIndex = r;
          break;
        }
      }
      if (targetTable) break;
    }
    
    // 1. Sắp xếp danh sách: Ưu tiên theo Khoa -> Mã Sinh Viên
    selectedStudents.sort(function(a, b) {
      var khoaA = (a.khoa || '').toString().toLowerCase();
      var khoaB = (b.khoa || '').toString().toLowerCase();
      if (khoaA < khoaB) return -1;
      if (khoaA > khoaB) return 1;
      
      var maA = (a.maSV || '').toString().toLowerCase();
      var maB = (b.maSV || '').toString().toLowerCase();
      if (maA < maB) return -1;
      if (maA > maB) return 1;
      return 0;
    });

    // Hàm viết hoa chữ cái đầu mỗi từ cho Họ Tên
    function toTitleCase(str) {
      return str.toLowerCase().replace(/(?:^|\s)\S/g, function(a) { return a.toUpperCase(); });
    }

    if (targetTable && templateRowIndex !== -1) {
      var templateRow = targetTable.getRow(templateRowIndex);
      
      for (var j = 0; j < selectedStudents.length; j++) {
        var stu = selectedStudents[j];
        var newRow = targetTable.insertTableRow(templateRowIndex + j + 1, templateRow.copy());
        
        var hoTenTitle = toTitleCase(stu.hoTen.toString());
        
        newRow.replaceText('{{TT}}', (j + 1).toString());
        newRow.replaceText('{{MaSV}}', stu.maSV.toString());
        newRow.replaceText('{{HoTen}}', hoTenTitle);
        newRow.replaceText('{{NgaySinh}}', stu.ngaySinh.toString());
        newRow.replaceText('{{Nganh}}', stu.nganh.toString());
        newRow.replaceText('{{Lop}}', stu.lop.toString());
        newRow.replaceText('{{LopCu}}', stu.lopCu ? stu.lopCu.toString() : '');
        newRow.replaceText('{{LopMoi}}', stu.lopMoi ? stu.lopMoi.toString() : '');
        newRow.replaceText('{{He}}', stu.he.toString());
        newRow.replaceText('{{Khoa}}', stu.khoa.toString());
        newRow.replaceText('{{ThoiHan}}', stu.thoiHan.toString());
      }
      targetTable.removeRow(templateRowIndex);
    }
    doc.saveAndClose();
    
    // Đợi 2 giây để Google lưu hoàn tất file trên hệ thống trước khi export
    Utilities.sleep(2000);
    
    var url = "https://docs.google.com/document/d/" + tempDoc.getId() + "/export?format=docx";
    var options = { headers: { Authorization: "Bearer " + ScriptApp.getOAuthToken() }, muteHttpExceptions: true };
    var docxBlob = UrlFetchApp.fetch(url, options).getBlob().setName(fileName + ".docx");
    
    var docxFile = finalFolder.createFile(docxBlob);
    tempDoc.setTrashed(true);
    
    // === CẬP NHẬT GOOGLE SHEET ===
    try {
      var sheet = getWorkingSheet();
      var headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
      
      var colTrangThai = -1, colSoQD = -1, colNgayQD = -1;
      
      // Tìm vị trí các cột
      for (var c = 0; c < headers.length; c++) {
        var hName = (headers[c] || '').toString().toLowerCase().trim();
        if (hName === 'trạng thái') colTrangThai = c + 1; // getRange dùng 1-indexed
        if (hName.indexOf('số qđ') !== -1 || hName.indexOf('số quyết định') !== -1) colSoQD = c + 1;
        if (hName.indexOf('ngày qđ') !== -1 || hName.indexOf('ngày quyết định') !== -1) colNgayQD = c + 1;
      }
      
      // Nếu chưa có cột Số QĐ / Ngày QĐ thì tạo thêm ở cuối
      var nextCol = headers.length + 1;
      if (colSoQD === -1) {
        colSoQD = nextCol++;
        sheet.getRange(1, colSoQD).setValue('Số Quyết Định').setFontWeight('bold').setBackground('#f3f4f6');
      }
      if (colNgayQD === -1) {
        colNgayQD = nextCol++;
        sheet.getRange(1, colNgayQD).setValue('Ngày Quyết Định').setFontWeight('bold').setBackground('#f3f4f6');
      }
      
      // Update từng sinh viên
      for (var s = 0; s < selectedStudents.length; s++) {
        var rIdx = selectedStudents[s].rowIdx;
        if (rIdx) {
          if (colTrangThai !== -1) sheet.getRange(rIdx, colTrangThai).setValue('Đã duyệt');
          sheet.getRange(rIdx, colSoQD).setValue(soQD);
          sheet.getRange(rIdx, colNgayQD).setValue(ngayQD_ngan); 
        }
      }
    } catch(sheetErr) {
      // Bỏ qua lỗi cập nhật sheet để không làm vỡ luồng xuất file Word
      Logger.log("Lỗi cập nhật sheet: " + sheetErr.toString());
    }
    // ==============================
    
    return { success: true, url: docxFile.getUrl() };
  } catch(e) {
    return { success: false, message: e.toString() };
  }
}
