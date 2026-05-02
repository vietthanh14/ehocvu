function onOpen() {
  SpreadsheetApp.getUi().createMenu('🛠️ Admin')
    .addItem('Mở Giao Diện Tạo Quyết Định', 'openAdminUI')
    .addSeparator()
    .addItem('Đồng Bộ Thông Tin Sinh Viên', 'syncAllStudentProfiles')
    .addToUi();
}

// Hàm mở giao diện Popup (Không cần deploy)
function openAdminUI() {
  var html = HtmlService.createHtmlOutputFromFile('Index')
      .setWidth(1100)  // Độ rộng của Popup mở rộng để chứa 2 cột
      .setHeight(650); // Chiều cao của Popup
  SpreadsheetApp.getUi().showModalDialog(html, '📚 CÔNG CỤ TẠO QUYẾT ĐỊNH');
}
