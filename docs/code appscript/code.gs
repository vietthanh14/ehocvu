/**
 * Google Apps Script - Upload file vào Google Drive folder
 * 
 * HƯỚNG DẪN CÀI ĐẶT:
 * 1. Vào https://script.google.com → Tạo dự án mới
 * 2. Dán toàn bộ code này vào
 * 3. Deploy → New deployment → Web app
 *    - Execute as: Me (tài khoản của bạn)
 *    - Who has access: Anyone
 * 4. Copy URL deployment → Dán vào config.php (UPLOAD_SCRIPT_URL)
 * 5. Đảm bảo Service Account có quyền Editor trên folder Google Drive
 */

// ID folder Google Drive để lưu file
var FOLDER_ID = '15mtXWOSOdKcf0KhMRuWEWtVorG5U9Qgi';

/**
 * Xử lý POST request từ PHP server
 * Nhận file dạng base64, lưu vào Drive, trả về link
 */
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
    
    // Decode base64 → blob
    var fileBlob = Utilities.newBlob(
      Utilities.base64Decode(fileBase64),
      mimeType,
      fileName
    );
    
    // Tạo tên file: MaSV_LoaiDon_Timestamp
    var timestamp = Utilities.formatDate(new Date(), 'Asia/Ho_Chi_Minh', 'yyyyMMdd_HHmmss');
    var safeName = maSv + '_' + timestamp + '_' + fileName;
    fileBlob.setName(safeName);
    
    // Lưu vào folder
    var folder = DriveApp.getFolderById(FOLDER_ID);
    var file = folder.createFile(fileBlob);
    
    // Set quyền xem cho anyone with link
    file.setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
    
    var fileUrl = file.getUrl();
    var fileId = file.getId();
    
    return ContentService.createTextOutput(
      JSON.stringify({
        success: true,
        fileUrl: fileUrl,
        fileId: fileId,
        fileName: safeName
      })
    ).setMimeType(ContentService.MimeType.JSON);
    
  } catch (error) {
    return ContentService.createTextOutput(
      JSON.stringify({ success: false, message: error.toString() })
    ).setMimeType(ContentService.MimeType.JSON);
  }
}

/**
 * GET handler - hiển thị thông báo khi truy cập trực tiếp
 */
function doGet() {
  return ContentService.createTextOutput(
    JSON.stringify({ status: 'ok', message: 'Upload API is running.' })
  ).setMimeType(ContentService.MimeType.JSON);
}



